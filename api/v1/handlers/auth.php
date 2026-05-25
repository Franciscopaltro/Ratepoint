<?php
/**
 * ============================================================
 * Auth Handlers — Login, Logout, Profile
 * ============================================================
 */

/**
 * POST /auth/login
 * Body: { "email": "...", "password": "..." }
 * Returns: user profile + API token
 */
function handle_login(PDO $pdo) {
    $body = getJsonBody();
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$password) {
        jsonError('Validation error', 'Email and password are required.', 422);
    }

    // Find user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonError('Authentication failed', 'Invalid email or password.', 401);
    }

    if (!$user['is_active']) {
        jsonError('Account disabled', 'Your account has been deactivated. Contact your administrator.', 403);
    }

    // Check role — only field agents can use the mobile app
    if (!in_array($user['role'], ['field_agent', 'agent'])) {
        jsonError('Unauthorized role', 'Only field agents can log in via the mobile app.', 403);
    }

    // Generate API token (64-char random string)
    $plainToken = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $plainToken);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    $stmt = $pdo->prepare("
        INSERT INTO personal_access_tokens (user_id, name, token, abilities, expires_at, created_at, updated_at) 
        VALUES (?, 'mobile-app', ?, '[\"*\"]', ?, NOW(), NOW())
    ");
    $stmt->execute([$user['id'], $hashedToken, $expiresAt]);

    // Update last login
    $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?")
        ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);

    // Build user response (strip sensitive fields)
    unset($user['password'], $user['remember_token']);

    // Fetch zone info
    $zone = null;
    if ($user['zone_id']) {
        $stmt = $pdo->prepare("SELECT id, name FROM zones WHERE id = ?");
        $stmt->execute([$user['zone_id']]);
        $zone = $stmt->fetch();
    }

    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'user' => [
                'id'           => (int)$user['id'],
                'name'         => $user['name'],
                'email'        => $user['email'],
                'role'         => $user['role'],
                'phone_number' => $user['phone_number'],
                'zone'         => $zone,
                'is_active'    => (bool)$user['is_active'],
            ],
            'token'      => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt,
        ]
    ]);
}

/**
 * POST /auth/logout (Authenticated)
 * Revokes the current token
 */
function handle_logout(PDO $pdo) {
    $user = authenticate($pdo);

    // Revoke token used in this request
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    preg_match('/^Bearer\s+(.+)$/i', $header, $m);
    $hashed = hash('sha256', $m[1]);

    $pdo->prepare("DELETE FROM personal_access_tokens WHERE token = ?")
        ->execute([$hashed]);

    jsonResponse([
        'success' => true,
        'message' => 'Logged out successfully. Token has been revoked.'
    ]);
}

/**
 * GET /auth/me (Authenticated)
 * Returns the current user's profile
 */
function handle_me(PDO $pdo) {
    $user = authenticate($pdo);

    $zone = null;
    if ($user['zone_id']) {
        $stmt = $pdo->prepare("SELECT id, name FROM zones WHERE id = ?");
        $stmt->execute([$user['zone_id']]);
        $zone = $stmt->fetch();
    }

    jsonResponse([
        'success' => true,
        'data' => [
            'id'           => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'phone_number' => $user['phone_number'],
            'zone'         => $zone,
            'is_active'    => (bool)$user['is_active'],
            'last_login_at'=> $user['last_login_at'] ?? null,
        ]
    ]);
}
