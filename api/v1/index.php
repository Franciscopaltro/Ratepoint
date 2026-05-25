<?php
/**
 * ============================================================
 * Ratepoint Mobile API v1 — Entry Point & Router
 * ============================================================
 * Base URL: http://<your-server>/RatepointSystem/api/v1/
 * 
 * All endpoints return JSON. Authenticated endpoints require:
 *   Authorization: Bearer <token>
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Database Connection ─────────────────────────────────────
$host    = '127.0.0.1';
$db      = 'ratepoint_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// ── Helpers ─────────────────────────────────────────────────

function jsonResponse($data, int $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $error, string $message = '', int $code = 400) {
    jsonResponse([
        'success' => false,
        'error'   => $error,
        'message' => $message
    ], $code);
}

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Authenticate the request using Bearer token.
 * Returns the authenticated user row or exits with 401.
 */
function authenticate(PDO $pdo): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        $plainToken = $m[1];
    } else {
        jsonError('Unauthenticated', 'Missing or invalid Authorization header. Use: Bearer <token>', 401);
    }

    $hashed = hash('sha256', $plainToken);
    $stmt = $pdo->prepare("SELECT * FROM personal_access_tokens WHERE token = ? LIMIT 1");
    $stmt->execute([$hashed]);
    $tokenRow = $stmt->fetch();

    if (!$tokenRow) {
        jsonError('Invalid token', 'The provided API token is not valid.', 401);
    }

    // Check expiry
    if ($tokenRow['expires_at'] && strtotime($tokenRow['expires_at']) < time()) {
        $pdo->prepare("DELETE FROM personal_access_tokens WHERE id = ?")->execute([$tokenRow['id']]);
        jsonError('Token expired', 'Your session has expired. Please log in again.', 401);
    }

    // Fetch user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$tokenRow['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonError('Account disabled', 'Your account has been deactivated.', 403);
    }

    // Mark token as used
    $pdo->prepare("UPDATE personal_access_tokens SET last_used_at = NOW() WHERE id = ?")
        ->execute([$tokenRow['id']]);

    // Strip sensitive fields
    unset($user['password'], $user['remember_token']);
    return $user;
}

/**
 * Simple Haversine distance in km
 */
function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $theta = $lon1 - $lon2;
    $dist  = sin(deg2rad($lat1)) * sin(deg2rad($lat2))
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist  = acos(min(1, max(-1, $dist)));
    return rad2deg($dist) * 60 * 1.1515 * 1.609344;
}

// ── URL Parsing ─────────────────────────────────────────────
// Strips /RatepointSystem/api/v1 to extract the route
$requestUri = $_SERVER['REQUEST_URI'];
$basePath   = '/RatepointSystem/api/v1';
$path       = parse_url($requestUri, PHP_URL_PATH);
$route      = '/' . trim(substr($path, strlen($basePath)), '/');
$method     = $_SERVER['REQUEST_METHOD'];

// ── Route Dispatch ──────────────────────────────────────────
// Include all endpoint handler files
require_once __DIR__ . '/handlers/auth.php';
require_once __DIR__ . '/handlers/agent.php';
require_once __DIR__ . '/handlers/admin_monitor.php';

// Build route table
$routes = [
    // ─── Public (no auth required) ───
    'POST /auth/login'             => 'handle_login',
    
    // ─── Agent Endpoints (auth required) ───
    'POST /auth/logout'            => 'handle_logout',
    'GET  /auth/me'                => 'handle_me',
    'GET  /agent/businesses'       => 'handle_agent_businesses',
    'GET  /agent/collections'      => 'handle_agent_collections',
    'POST /agent/collections'      => 'handle_agent_store_collection',
    'POST /agent/collections/bulk' => 'handle_agent_bulk_sync',
    'POST /agent/location'         => 'handle_agent_update_location',
    'POST /agent/heartbeat'        => 'handle_agent_heartbeat',
    'GET  /agent/notifications'    => 'handle_agent_notifications',
    'PATCH /agent/notifications/read' => 'handle_agent_mark_notifications_read',
    'GET  /agent/stats'            => 'handle_agent_stats',

    // ─── Admin Monitoring Endpoints (auth required, admin role) ───
    'GET  /admin/agents/live'      => 'handle_admin_agents_live',
    'GET  /admin/agents/locations' => 'handle_admin_agents_locations',
    'GET  /admin/collections/live' => 'handle_admin_collections_live',
    'GET  /admin/dashboard/stats'  => 'handle_admin_dashboard_stats',
    'POST /admin/notifications/send' => 'handle_admin_send_notification',
];

// Normalize the method + route key
$routeKey = "$method $route";

// Try to match
$handler = null;
foreach ($routes as $pattern => $fn) {
    // Normalize spaces
    $patternNorm = preg_replace('/\s+/', ' ', trim($pattern));
    if (strtoupper($patternNorm) === strtoupper($routeKey)) {
        $handler = $fn;
        break;
    }
}

if ($handler && is_callable($handler)) {
    $handler($pdo);
} else {
    jsonError('Not found', "Endpoint $method $route does not exist. Check the API documentation.", 404);
}
