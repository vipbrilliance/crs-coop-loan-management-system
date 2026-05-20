<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// POST /admin-auth.php?action=logout
if ($method === 'POST' && $action === 'logout') {
    $user = require_auth($db);
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) $token = trim($m[1]);
    if ($token !== '') {
        $db->prepare('DELETE FROM admin_sessions WHERE token_hash = ?')
           ->execute([hash('sha256', $token)]);
    }
    try { audit_log($db, 'AUTH', 'LOGOUT', 'Session', '', $user['email'], 'Admin logged out.', [], (int)$user['id'], $user['name'], 'LOW'); } catch (\Throwable $e) {}
    json_ok(['message' => 'Logged out.']);
}

// POST /admin-auth.php (login)
if ($method === 'POST') {
    $d        = body();
    $email    = strtolower(trim($d['email'] ?? ''));
    $password = (string)($d['password'] ?? '');

    if ($email === '' || $password === '') json_err('Email and password are required.', 422);

    $stmt = $db->prepare('SELECT * FROM users WHERE LOWER(email) = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        try { audit_log($db, 'AUTH', 'FAILED_LOGIN', 'User', '', $email, 'Failed admin login attempt.', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null], null, $email, 'HIGH'); } catch (\Throwable $e) {}
        json_err('Invalid email or password.', 401);
    }

    // Lazy cleanup: remove expired sessions for this user (D-14)
    $db->prepare('DELETE FROM admin_sessions WHERE user_id = ? AND expires_at <= CURRENT_TIMESTAMP')
       ->execute([(int)$user['id']]);

    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    // CRITICAL: Use 2-arg DateTimeZone form -- never the bare 1-arg form from member-auth.php (Pitfall 6)
    $expiresAt = (new DateTime('+8 hours', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');

    $db->prepare('INSERT INTO admin_sessions (user_id, token_hash, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)')
       ->execute([
           (int)$user['id'], $tokenHash,
           $_SERVER['REMOTE_ADDR'] ?? null,
           substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
           $expiresAt,
       ]);

    try { audit_log($db, 'AUTH', 'LOGIN', 'User', (string)$user['id'], $user['email'], 'Admin login successful.', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null], (int)$user['id'], $user['name'], 'LOW'); } catch (\Throwable $e) {}

    json_ok([
        'token'      => $rawToken,
        'expires_at' => $expiresAt,
        'user'       => [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ],
    ]);
}

json_err('Method not allowed', 405);
