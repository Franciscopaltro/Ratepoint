<?php
/**
 * ============================================================
 * Agent Handlers — Businesses, Collections, Location, Stats
 * ============================================================
 */

/**
 * GET /agent/businesses (Authenticated — Agent)
 * Returns businesses assigned to the agent's zone
 * Query: ?status=paid|unpaid|pending
 */
function handle_agent_businesses(PDO $pdo) {
    $user = authenticate($pdo);

    if (!$user['zone_id']) {
        jsonError('No zone assigned', 'You have not been assigned to a zone. Contact your supervisor.', 403);
    }

    $sql = "SELECT b.*, z.name as zone_name FROM businesses b 
            JOIN zones z ON z.id = b.zone_id 
            WHERE b.zone_id = ?";
    $params = [$user['zone_id']];

    // Optional status filter
    if (!empty($_GET['status']) && in_array($_GET['status'], ['paid', 'unpaid', 'pending'])) {
        $sql .= " AND b.status = ?";
        $params[] = $_GET['status'];
    }

    $sql .= " ORDER BY b.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $businesses = $stmt->fetchAll();

    // Format response
    $formatted = array_map(function($b) {
        return [
            'id'             => (int)$b['id'],
            'name'           => $b['name'],
            'owner_name'     => $b['owner_name'],
            'gps_lat'        => (float)$b['gps_lat'],
            'gps_lng'        => (float)$b['gps_lng'],
            'zone_id'        => (int)$b['zone_id'],
            'zone_name'      => $b['zone_name'],
            'structure_type' => $b['structure_type'],
            'levy_type'      => $b['levy_type'],
            'fee_amount'     => (float)$b['fee_amount'],
            'status'         => $b['status'],
        ];
    }, $businesses);

    jsonResponse([
        'success' => true,
        'data'    => $formatted,
        'count'   => count($formatted),
    ]);
}

/**
 * GET /agent/collections (Authenticated — Agent)
 * Returns the agent's own collections history
 * Query: ?date=YYYY-MM-DD  (filter by date, defaults to today)
 *        ?limit=N           (default 50)
 */
function handle_agent_collections(PDO $pdo) {
    $user = authenticate($pdo);

    $date  = $_GET['date'] ?? date('Y-m-d');
    $limit = min((int)($_GET['limit'] ?? 50), 200);

    $stmt = $pdo->prepare("
        SELECT c.*, b.name as business_name, b.owner_name 
        FROM collections c
        JOIN businesses b ON b.id = c.business_id
        WHERE c.agent_id = ? AND DATE(c.collected_at) = ?
        ORDER BY c.collected_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user['id'], $date, $limit]);
    $collections = $stmt->fetchAll();

    $formatted = array_map(function($c) {
        return [
            'id'              => (int)$c['id'],
            'business_id'     => (int)$c['business_id'],
            'business_name'   => $c['business_name'],
            'owner_name'      => $c['owner_name'],
            'amount'          => (float)$c['amount'],
            'payment_method'  => $c['payment_method'],
            'receipt_number'  => $c['receipt_number'],
            'gps_lat'         => (float)$c['gps_lat'],
            'gps_lng'         => (float)$c['gps_lng'],
            'offline_sync_id' => $c['offline_sync_id'],
            'collected_at'    => $c['collected_at'],
        ];
    }, $collections);

    jsonResponse([
        'success' => true,
        'data'    => $formatted,
        'count'   => count($formatted),
        'date'    => $date,
    ]);
}

/**
 * POST /agent/collections (Authenticated — Agent)
 * Record a single revenue collection
 * Body: {
 *   "business_id": 1,
 *   "amount": 150.00,
 *   "payment_method": "cash",
 *   "gps_lat": 5.60370,
 *   "gps_lng": -0.18700,
 *   "collected_at": "2026-05-22T10:30:00Z",
 *   "offline_sync_id": "local-uuid-123" (optional, for dedup)
 * }
 */
function handle_agent_store_collection(PDO $pdo) {
    $user = authenticate($pdo);
    $body = getJsonBody();

    // Validation
    $required = ['business_id', 'amount', 'gps_lat', 'gps_lng', 'collected_at'];
    $missing = [];
    foreach ($required as $field) {
        if (!isset($body[$field]) || $body[$field] === '') {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        jsonError('Validation error', 'Missing required fields: ' . implode(', ', $missing), 422);
    }

    // Check business exists
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
    $stmt->execute([$body['business_id']]);
    $business = $stmt->fetch();
    if (!$business) {
        jsonError('Not found', 'Business not found.', 404);
    }

    // Deduplication check (offline_sync_id)
    if (!empty($body['offline_sync_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM collections WHERE offline_sync_id = ? LIMIT 1");
        $stmt->execute([$body['offline_sync_id']]);
        if ($stmt->fetch()) {
            jsonResponse([
                'success' => true,
                'message' => 'Collection already synced (duplicate detected).',
                'duplicate' => true,
            ]);
            return;
        }
    }

    // Geo-fencing check (500m threshold)
    $distance = haversineKm(
        (float)$body['gps_lat'], (float)$body['gps_lng'],
        (float)$business['gps_lat'], (float)$business['gps_lng']
    );

    $geoFlagged = false;
    if ($distance > 0.5) {
        $geoFlagged = true;
        // Log suspicious activity
        $pdo->prepare("
            INSERT INTO suspicious_activities (type, related_id, description, severity, status, created_at, updated_at) 
            VALUES ('GPS_MISMATCH', ?, ?, 'medium', 'open', NOW(), NOW())
        ")->execute([
            $user['id'],
            "Agent '{$user['name']}' collected from '{$business['name']}' at " . round($distance, 2) . "km away"
        ]);
    }

    // Generate receipt number
    $receipt = 'REC-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Ymd');

    // Insert collection
    $stmt = $pdo->prepare("
        INSERT INTO collections 
        (business_id, agent_id, amount, payment_method, receipt_number, gps_lat, gps_lng, offline_sync_id, collected_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $body['business_id'],
        $user['id'],
        $body['amount'],
        $body['payment_method'] ?? 'cash',
        $receipt,
        $body['gps_lat'],
        $body['gps_lng'],
        $body['offline_sync_id'] ?? null,
        $body['collected_at'],
    ]);

    $collectionId = $pdo->lastInsertId();

    // Update business status
    $pdo->prepare("UPDATE businesses SET status = 'paid', updated_at = NOW() WHERE id = ?")
        ->execute([$body['business_id']]);

    jsonResponse([
        'success'        => true,
        'message'        => 'Collection recorded successfully.',
        'data'           => [
            'collection_id'  => (int)$collectionId,
            'receipt_number' => $receipt,
            'amount'         => (float)$body['amount'],
            'business_name'  => $business['name'],
            'geo_flagged'    => $geoFlagged,
            'collected_at'   => $body['collected_at'],
        ]
    ], 201);
}

/**
 * POST /agent/collections/bulk (Authenticated — Agent)
 * Sync multiple offline collections at once
 * Body: { "collections": [ {collection1}, {collection2}, ... ] }
 */
function handle_agent_bulk_sync(PDO $pdo) {
    $user = authenticate($pdo);
    $body = getJsonBody();

    if (empty($body['collections']) || !is_array($body['collections'])) {
        jsonError('Validation error', 'Provide an array of collections in the "collections" key.', 422);
    }

    $results = [];
    $synced  = 0;
    $skipped = 0;
    $failed  = 0;

    foreach ($body['collections'] as $index => $item) {
        try {
            // Check required fields
            if (empty($item['business_id']) || empty($item['amount']) || 
                empty($item['gps_lat']) || empty($item['gps_lng']) || empty($item['collected_at'])) {
                $results[] = ['index' => $index, 'status' => 'failed', 'error' => 'Missing required fields'];
                $failed++;
                continue;
            }

            // Dedup check
            if (!empty($item['offline_sync_id'])) {
                $stmt = $pdo->prepare("SELECT id FROM collections WHERE offline_sync_id = ? LIMIT 1");
                $stmt->execute([$item['offline_sync_id']]);
                if ($stmt->fetch()) {
                    $results[] = ['index' => $index, 'status' => 'skipped', 'reason' => 'duplicate'];
                    $skipped++;
                    continue;
                }
            }

            $receipt = 'REC-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Ymd');

            $stmt = $pdo->prepare("
                INSERT INTO collections 
                (business_id, agent_id, amount, payment_method, receipt_number, gps_lat, gps_lng, offline_sync_id, collected_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $item['business_id'],
                $user['id'],
                $item['amount'],
                $item['payment_method'] ?? 'cash',
                $receipt,
                $item['gps_lat'],
                $item['gps_lng'],
                $item['offline_sync_id'] ?? null,
                $item['collected_at'],
            ]);

            $pdo->prepare("UPDATE businesses SET status = 'paid', updated_at = NOW() WHERE id = ?")
                ->execute([$item['business_id']]);

            $results[] = [
                'index'          => $index,
                'status'         => 'synced',
                'receipt_number' => $receipt,
                'collection_id'  => (int)$pdo->lastInsertId(),
            ];
            $synced++;

        } catch (Exception $e) {
            $results[] = ['index' => $index, 'status' => 'failed', 'error' => 'Server error'];
            $failed++;
        }
    }

    jsonResponse([
        'success' => true,
        'message' => "Bulk sync complete: $synced synced, $skipped skipped, $failed failed.",
        'summary' => [
            'total'   => count($body['collections']),
            'synced'  => $synced,
            'skipped' => $skipped,
            'failed'  => $failed,
        ],
        'results' => $results,
    ]);
}

/**
 * POST /agent/location (Authenticated — Agent)
 * Update the agent's live GPS position (for admin tracking)
 * Body: {
 *   "latitude": 5.60370,
 *   "longitude": -0.18700,
 *   "accuracy": 12.5,      (optional, meters)
 *   "battery_level": 78    (optional, 0-100)
 * }
 */
function handle_agent_update_location(PDO $pdo) {
    $user = authenticate($pdo);
    $body = getJsonBody();

    if (!isset($body['latitude']) || !isset($body['longitude'])) {
        jsonError('Validation error', 'latitude and longitude are required.', 422);
    }

    // Upsert agent location
    $stmt = $pdo->prepare("SELECT id FROM agent_locations WHERE agent_id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $exists = $stmt->fetch();

    if ($exists) {
        $pdo->prepare("
            UPDATE agent_locations 
            SET latitude = ?, longitude = ?, accuracy = ?, battery_level = ?, 
                is_online = 1, last_seen_at = NOW(), updated_at = NOW()
            WHERE agent_id = ?
        ")->execute([
            $body['latitude'],
            $body['longitude'],
            $body['accuracy'] ?? null,
            $body['battery_level'] ?? null,
            $user['id'],
        ]);
    } else {
        $pdo->prepare("
            INSERT INTO agent_locations 
            (agent_id, latitude, longitude, accuracy, battery_level, is_online, last_seen_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())
        ")->execute([
            $user['id'],
            $body['latitude'],
            $body['longitude'],
            $body['accuracy'] ?? null,
            $body['battery_level'] ?? null,
        ]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Location updated.',
    ]);
}

/**
 * POST /agent/heartbeat (Authenticated — Agent)
 * Lightweight ping to keep agent marked as online.
 * Body (optional): { "battery_level": 65 }
 */
function handle_agent_heartbeat(PDO $pdo) {
    $user = authenticate($pdo);
    $body = getJsonBody();

    $pdo->prepare("
        UPDATE agent_locations 
        SET is_online = 1, last_seen_at = NOW(), 
            battery_level = COALESCE(?, battery_level),
            updated_at = NOW()
        WHERE agent_id = ?
    ")->execute([
        $body['battery_level'] ?? null,
        $user['id'],
    ]);

    // Count unread notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE (recipient_id = ? OR recipient_role = ? OR recipient_id IS NULL) 
        AND read_at IS NULL
    ");
    $stmt->execute([$user['id'], $user['role']]);
    $unreadCount = (int)$stmt->fetchColumn();

    jsonResponse([
        'success'          => true,
        'message'          => 'Heartbeat received.',
        'server_time'      => date('c'),
        'unread_notifications' => $unreadCount,
    ]);
}

/**
 * GET /agent/notifications (Authenticated — Agent)
 * Query: ?unread_only=1  (optional)
 *        ?limit=20       (default 20)
 */
function handle_agent_notifications(PDO $pdo) {
    $user = authenticate($pdo);

    $limit     = min((int)($_GET['limit'] ?? 20), 100);
    $unreadOnly = !empty($_GET['unread_only']);

    $sql = "
        SELECT n.*, s.name as sender_name FROM notifications n
        LEFT JOIN users s ON s.id = n.sender_id
        WHERE (n.recipient_id = ? OR n.recipient_role = ? OR (n.recipient_id IS NULL AND n.recipient_role IS NULL))
    ";
    $params = [$user['id'], $user['role']];

    if ($unreadOnly) {
        $sql .= " AND n.read_at IS NULL";
    }

    $sql .= " ORDER BY n.created_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    $formatted = array_map(function($n) {
        return [
            'id'          => (int)$n['id'],
            'title'       => $n['title'],
            'message'     => $n['message'],
            'type'        => $n['type'],
            'priority'    => $n['priority'],
            'sender_name' => $n['sender_name'],
            'data'        => $n['data'] ? json_decode($n['data'], true) : null,
            'is_read'     => $n['read_at'] !== null,
            'created_at'  => $n['created_at'],
        ];
    }, $notifications);

    jsonResponse([
        'success' => true,
        'data'    => $formatted,
        'count'   => count($formatted),
    ]);
}

/**
 * PATCH /agent/notifications/read (Authenticated — Agent)
 * Mark notifications as read
 * Body: { "notification_ids": [1, 2, 3] }  or  { "mark_all": true }
 */
function handle_agent_mark_notifications_read(PDO $pdo) {
    $user = authenticate($pdo);
    $body = getJsonBody();

    if (!empty($body['mark_all'])) {
        $pdo->prepare("
            UPDATE notifications SET read_at = NOW() 
            WHERE (recipient_id = ? OR recipient_role = ?) AND read_at IS NULL
        ")->execute([$user['id'], $user['role']]);
    } elseif (!empty($body['notification_ids']) && is_array($body['notification_ids'])) {
        $placeholders = implode(',', array_fill(0, count($body['notification_ids']), '?'));
        $params = array_merge($body['notification_ids'], [$user['id']]);
        $pdo->prepare("
            UPDATE notifications SET read_at = NOW() 
            WHERE id IN ($placeholders) AND recipient_id = ? AND read_at IS NULL
        ")->execute($params);
    } else {
        jsonError('Validation error', 'Provide notification_ids array or mark_all: true.', 422);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Notifications marked as read.',
    ]);
}

/**
 * GET /agent/stats (Authenticated — Agent)
 * Returns the agent's performance statistics
 */
function handle_agent_stats(PDO $pdo) {
    $user = authenticate($pdo);

    // Today's collections
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
        FROM collections WHERE agent_id = ? AND DATE(collected_at) = CURDATE()
    ");
    $stmt->execute([$user['id']]);
    $today = $stmt->fetch();

    // This week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
        FROM collections WHERE agent_id = ? AND YEARWEEK(collected_at, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $stmt->execute([$user['id']]);
    $week = $stmt->fetch();

    // This month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
        FROM collections WHERE agent_id = ? AND MONTH(collected_at) = MONTH(CURDATE()) AND YEAR(collected_at) = YEAR(CURDATE())
    ");
    $stmt->execute([$user['id']]);
    $month = $stmt->fetch();

    // All time
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
        FROM collections WHERE agent_id = ?
    ");
    $stmt->execute([$user['id']]);
    $allTime = $stmt->fetch();

    // Total businesses in zone
    $bizCount = 0;
    $unpaidCount = 0;
    if ($user['zone_id']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE zone_id = ?");
        $stmt->execute([$user['zone_id']]);
        $bizCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE zone_id = ? AND status = 'unpaid'");
        $stmt->execute([$user['zone_id']]);
        $unpaidCount = (int)$stmt->fetchColumn();
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'today' => [
                'collections'   => (int)$today['count'],
                'total_amount'  => (float)$today['total'],
            ],
            'this_week' => [
                'collections'   => (int)$week['count'],
                'total_amount'  => (float)$week['total'],
            ],
            'this_month' => [
                'collections'   => (int)$month['count'],
                'total_amount'  => (float)$month['total'],
            ],
            'all_time' => [
                'collections'   => (int)$allTime['count'],
                'total_amount'  => (float)$allTime['total'],
            ],
            'zone' => [
                'total_businesses'  => $bizCount,
                'unpaid_businesses' => $unpaidCount,
            ],
        ]
    ]);
}
