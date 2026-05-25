<?php
/**
 * ============================================================
 * Admin Monitoring Handlers — Live Agent Tracking & Dashboard
 * These endpoints power the web dashboard's real-time views.
 * ============================================================
 */

/**
 * GET /admin/agents/live (Authenticated — Admin)
 * Returns all agents with their current status, location, and today's stats.
 * This is the primary endpoint for the admin dashboard's "Agent Monitor" panel.
 */
function handle_admin_agents_live(PDO $pdo) {
    $user = authenticate($pdo);

    // Only admin roles
    if (!in_array($user['role'], ['super_admin', 'finance_officer', 'supervisor'])) {
        jsonError('Forbidden', 'Admin access required.', 403);
    }

    $agents = $pdo->query("
        SELECT 
            u.id, u.name, u.email, u.phone_number, u.role, u.is_active,
            z.name as zone_name, z.id as zone_id,
            al.latitude, al.longitude, al.accuracy, al.battery_level,
            al.is_online, al.last_seen_at,
            COALESCE(today_stats.collections_count, 0) as today_collections,
            COALESCE(today_stats.total_collected, 0) as today_amount
        FROM users u
        LEFT JOIN zones z ON z.id = u.zone_id
        LEFT JOIN agent_locations al ON al.agent_id = u.id
        LEFT JOIN (
            SELECT agent_id, 
                   COUNT(*) as collections_count, 
                   SUM(amount) as total_collected
            FROM collections 
            WHERE DATE(collected_at) = CURDATE()
            GROUP BY agent_id
        ) today_stats ON today_stats.agent_id = u.id
        WHERE u.role IN ('field_agent', 'agent')
        ORDER BY al.is_online DESC, u.name ASC
    ")->fetchAll();

    $formatted = array_map(function($a) {
        // Determine online status based on last_seen_at (5 min threshold)
        $isOnline = false;
        if ($a['last_seen_at']) {
            $lastSeen = strtotime($a['last_seen_at']);
            $isOnline = (time() - $lastSeen) < 300; // 5 minutes
        }

        return [
            'id'             => (int)$a['id'],
            'name'           => $a['name'],
            'email'          => $a['email'],
            'phone_number'   => $a['phone_number'],
            'zone'           => $a['zone_name'] ? ['id' => (int)$a['zone_id'], 'name' => $a['zone_name']] : null,
            'is_active'      => (bool)$a['is_active'],
            'status'         => $isOnline ? 'online' : ($a['last_seen_at'] ? 'offline' : 'never_connected'),
            'location'       => $a['latitude'] ? [
                'latitude'      => (float)$a['latitude'],
                'longitude'     => (float)$a['longitude'],
                'accuracy'      => $a['accuracy'] ? (float)$a['accuracy'] : null,
                'battery_level' => $a['battery_level'] ? (int)$a['battery_level'] : null,
                'last_seen_at'  => $a['last_seen_at'],
            ] : null,
            'today_stats' => [
                'collections' => (int)$a['today_collections'],
                'amount'      => (float)$a['today_amount'],
            ],
        ];
    }, $agents);

    // Summary counts
    $online  = count(array_filter($formatted, fn($a) => $a['status'] === 'online'));
    $offline = count(array_filter($formatted, fn($a) => $a['status'] === 'offline'));
    $never   = count(array_filter($formatted, fn($a) => $a['status'] === 'never_connected'));

    jsonResponse([
        'success' => true,
        'data'    => $formatted,
        'summary' => [
            'total'           => count($formatted),
            'online'          => $online,
            'offline'         => $offline,
            'never_connected' => $never,
        ],
        'server_time' => date('c'),
    ]);
}

/**
 * GET /admin/agents/locations (Authenticated — Admin)
 * Returns only GPS coordinates for all online agents.
 * Lightweight endpoint for map updates (polling every 10-15 seconds).
 */
function handle_admin_agents_locations(PDO $pdo) {
    $user = authenticate($pdo);

    if (!in_array($user['role'], ['super_admin', 'finance_officer', 'supervisor'])) {
        jsonError('Forbidden', 'Admin access required.', 403);
    }

    $agents = $pdo->query("
        SELECT u.id, u.name, al.latitude, al.longitude, al.accuracy, 
               al.battery_level, al.is_online, al.last_seen_at
        FROM agent_locations al
        JOIN users u ON u.id = al.agent_id
        WHERE al.last_seen_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ORDER BY al.last_seen_at DESC
    ")->fetchAll();

    $markers = array_map(function($a) {
        $isOnline = (time() - strtotime($a['last_seen_at'])) < 300;
        return [
            'agent_id'      => (int)$a['id'],
            'agent_name'    => $a['name'],
            'latitude'      => (float)$a['latitude'],
            'longitude'     => (float)$a['longitude'],
            'accuracy'      => $a['accuracy'] ? (float)$a['accuracy'] : null,
            'battery_level' => $a['battery_level'] ? (int)$a['battery_level'] : null,
            'is_online'     => $isOnline,
            'last_seen_at'  => $a['last_seen_at'],
        ];
    }, $agents);

    jsonResponse([
        'success'     => true,
        'data'        => $markers,
        'count'       => count($markers),
        'server_time' => date('c'),
    ]);
}

/**
 * GET /admin/collections/live (Authenticated — Admin)
 * Returns recent collections across all agents for live feed.
 * Query: ?since=2026-05-22T10:00:00Z  (ISO timestamp, returns only newer)
 *        ?limit=50                      (default 50)
 */
function handle_admin_collections_live(PDO $pdo) {
    $user = authenticate($pdo);

    if (!in_array($user['role'], ['super_admin', 'finance_officer', 'supervisor'])) {
        jsonError('Forbidden', 'Admin access required.', 403);
    }

    $limit = min((int)($_GET['limit'] ?? 50), 200);
    $since = $_GET['since'] ?? date('Y-m-d 00:00:00');

    $stmt = $pdo->prepare("
        SELECT c.*, 
               b.name as business_name, b.owner_name,
               u.name as agent_name, u.phone_number as agent_phone,
               z.name as zone_name
        FROM collections c
        JOIN businesses b ON b.id = c.business_id
        JOIN users u ON u.id = c.agent_id
        LEFT JOIN zones z ON z.id = b.zone_id
        WHERE c.created_at >= ?
        ORDER BY c.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$since, $limit]);
    $collections = $stmt->fetchAll();

    $formatted = array_map(function($c) {
        return [
            'id'              => (int)$c['id'],
            'receipt_number'  => $c['receipt_number'],
            'amount'          => (float)$c['amount'],
            'payment_method'  => $c['payment_method'],
            'business' => [
                'id'         => (int)$c['business_id'],
                'name'       => $c['business_name'],
                'owner_name' => $c['owner_name'],
            ],
            'agent' => [
                'id'           => (int)$c['agent_id'],
                'name'         => $c['agent_name'],
                'phone_number' => $c['agent_phone'],
            ],
            'zone_name'       => $c['zone_name'],
            'gps_lat'         => (float)$c['gps_lat'],
            'gps_lng'         => (float)$c['gps_lng'],
            'collected_at'    => $c['collected_at'],
            'synced_at'       => $c['created_at'],
        ];
    }, $collections);

    jsonResponse([
        'success'     => true,
        'data'        => $formatted,
        'count'       => count($formatted),
        'server_time' => date('c'),
    ]);
}

/**
 * GET /admin/dashboard/stats (Authenticated — Admin)
 * Returns comprehensive dashboard statistics
 */
function handle_admin_dashboard_stats(PDO $pdo) {
    $user = authenticate($pdo);

    if (!in_array($user['role'], ['super_admin', 'finance_officer', 'supervisor'])) {
        jsonError('Forbidden', 'Admin access required.', 403);
    }

    // Revenue stats
    $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM collections")->fetchColumn();
    $todayRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM collections WHERE DATE(collected_at) = CURDATE()")->fetchColumn();
    $weekRevenue  = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM collections WHERE YEARWEEK(collected_at, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn();
    $monthRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM collections WHERE MONTH(collected_at) = MONTH(CURDATE()) AND YEAR(collected_at) = YEAR(CURDATE())")->fetchColumn();

    // Agent counts
    $totalAgents  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('field_agent','agent')")->fetchColumn();
    $activeAgents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('field_agent','agent') AND is_active = 1")->fetchColumn();
    $onlineAgents = (int)$pdo->query("SELECT COUNT(*) FROM agent_locations WHERE is_online = 1 AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();

    // Collection counts
    $todayCollections = (int)$pdo->query("SELECT COUNT(*) FROM collections WHERE DATE(collected_at) = CURDATE()")->fetchColumn();
    $pendingRecon     = (int)$pdo->query("SELECT COUNT(*) FROM collections WHERE id NOT IN (SELECT COALESCE(collection_id,0) FROM reconciliations)")->fetchColumn();

    // Suspicious activities
    $openAlerts = (int)$pdo->query("SELECT COUNT(*) FROM suspicious_activities WHERE status = 'open'")->fetchColumn();

    // Top 5 agents today
    $topAgents = $pdo->query("
        SELECT u.id, u.name, COUNT(c.id) as count, COALESCE(SUM(c.amount), 0) as total
        FROM collections c
        JOIN users u ON u.id = c.agent_id
        WHERE DATE(c.collected_at) = CURDATE()
        GROUP BY u.id, u.name
        ORDER BY total DESC
        LIMIT 5
    ")->fetchAll();

    // Revenue by zone
    $zoneRevenue = $pdo->query("
        SELECT z.id, z.name, COALESCE(SUM(c.amount), 0) as total, COUNT(c.id) as count
        FROM zones z
        LEFT JOIN businesses b ON b.zone_id = z.id
        LEFT JOIN collections c ON c.business_id = b.id AND DATE(c.collected_at) = CURDATE()
        GROUP BY z.id, z.name
        ORDER BY total DESC
    ")->fetchAll();

    jsonResponse([
        'success' => true,
        'data' => [
            'revenue' => [
                'total'      => $totalRevenue,
                'today'      => $todayRevenue,
                'this_week'  => $weekRevenue,
                'this_month' => $monthRevenue,
            ],
            'agents' => [
                'total'   => $totalAgents,
                'active'  => $activeAgents,
                'online'  => $onlineAgents,
            ],
            'collections' => [
                'today_count'     => $todayCollections,
                'pending_recon'   => $pendingRecon,
            ],
            'alerts' => [
                'open_suspicious' => $openAlerts,
            ],
            'top_agents_today' => array_map(function($a) {
                return [
                    'id'          => (int)$a['id'],
                    'name'        => $a['name'],
                    'collections' => (int)$a['count'],
                    'amount'      => (float)$a['total'],
                ];
            }, $topAgents),
            'zone_revenue_today' => array_map(function($z) {
                return [
                    'zone_id'     => (int)$z['id'],
                    'zone_name'   => $z['name'],
                    'amount'      => (float)$z['total'],
                    'collections' => (int)$z['count'],
                ];
            }, $zoneRevenue),
        ],
        'server_time' => date('c'),
    ]);
}

/**
 * POST /admin/notifications/send (Authenticated — Admin)
 * Send a notification to a specific agent or broadcast to all agents
 * Body: {
 *   "recipient_id": 3,        (specific agent, omit for broadcast)
 *   "title": "Urgent Task",
 *   "message": "Please collect from Zone B today.",
 *   "type": "task",           (info|warning|alert|task|broadcast)
 *   "priority": "high"        (low|normal|high|urgent)
 * }
 */
function handle_admin_send_notification(PDO $pdo) {
    $user = authenticate($pdo);

    if (!in_array($user['role'], ['super_admin', 'finance_officer', 'supervisor'])) {
        jsonError('Forbidden', 'Admin access required.', 403);
    }

    $body = getJsonBody();

    if (empty($body['title']) || empty($body['message'])) {
        jsonError('Validation error', 'Title and message are required.', 422);
    }

    $type     = $body['type'] ?? 'info';
    $priority = $body['priority'] ?? 'normal';

    if (!in_array($type, ['info', 'warning', 'alert', 'task', 'broadcast'])) {
        jsonError('Validation error', 'Invalid type. Use: info, warning, alert, task, broadcast.', 422);
    }

    if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
        jsonError('Validation error', 'Invalid priority. Use: low, normal, high, urgent.', 422);
    }

    if (!empty($body['recipient_id'])) {
        // Send to specific agent
        $stmt = $pdo->prepare("
            INSERT INTO notifications (sender_id, recipient_id, title, message, type, priority, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $user['id'],
            $body['recipient_id'],
            $body['title'],
            $body['message'],
            $type,
            $priority,
        ]);
    } else {
        // Broadcast to all agents
        $stmt = $pdo->prepare("
            INSERT INTO notifications (sender_id, recipient_role, title, message, type, priority, created_at, updated_at)
            VALUES (?, 'field_agent', ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $user['id'],
            $body['title'],
            $body['message'],
            $type,
            $priority,
        ]);
    }

    jsonResponse([
        'success' => true,
        'message' => !empty($body['recipient_id']) 
            ? 'Notification sent to agent.' 
            : 'Notification broadcast to all field agents.',
    ], 201);
}
