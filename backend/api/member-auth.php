<?php
// backend/api/member-auth.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') json_err('Method not allowed', 405);

$d = body();
$identifier = strtolower(trim($d['identifier'] ?? ''));
$password = (string)($d['password'] ?? '');

if ($identifier === '' || $password === '') {
    json_err('Enter your member number/email and password.', 422);
}

$stmt = $db->prepare("
    SELECT a.*, m.member_no, m.first_name, m.middle_name, m.last_name, m.email AS member_email,
           m.contact, m.address, m.company, m.branch, m.department, m.position, m.status,
           m.member_status, m.monthly_salary, m.share_capital
    FROM member_portal_accounts a
    JOIN members m ON m.id = a.member_id
    WHERE a.is_active = 1
      AND (LOWER(a.username) = ? OR LOWER(a.email) = ? OR LOWER(m.member_no) = ? OR LOWER(m.email) = ?)
    LIMIT 1
");
$stmt->execute([$identifier, $identifier, $identifier, $identifier]);
$account = $stmt->fetch();

if (!$account || !password_verify($password, $account['password_hash'])) {
    json_err('Invalid member login.', 401);
}

$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);
// Use PHT (UTC+8) as base so expires_at is stored in the same timezone as MySQL CURRENT_TIMESTAMP
$expiresAt = (new DateTime('now', new DateTimeZone('+08:00')))->modify('+8 hours')->format('Y-m-d H:i:s');

$db->prepare('INSERT INTO member_portal_sessions (account_id, token_hash, expires_at) VALUES (?, ?, ?)')
   ->execute([(int)$account['id'], $tokenHash, $expiresAt]);
$db->prepare('UPDATE member_portal_accounts SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?')
   ->execute([(int)$account['id']]);

try {
    $db->prepare("
        INSERT INTO member_portal_audit_logs (account_id, member_id, action, detail, ip_address, user_agent)
        VALUES (?, ?, 'LOGIN', 'Member portal login successful.', ?, ?)
    ")->execute([
        (int)$account['id'],
        (int)$account['member_id'],
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
    ]);
} catch (Throwable $e) {
    // Portal audit logging should not block login.
}

$member = [
    'id' => (int)$account['member_id'],
    'member_no' => $account['member_no'],
    'first_name' => $account['first_name'],
    'middle_name' => $account['middle_name'],
    'last_name' => $account['last_name'],
    'email' => $account['member_email'],
    'contact' => $account['contact'],
    'address' => $account['address'],
    'company' => $account['company'],
    'branch' => $account['branch'],
    'department' => $account['department'],
    'position' => $account['position'],
    'status' => $account['status'],
    'member_status' => $account['member_status'],
    'monthly_salary' => (float)$account['monthly_salary'],
    'share_capital' => (float)$account['share_capital'],
];

json_ok([
    'token' => $rawToken,
    'expires_at' => $expiresAt,
    'member' => $member,
    'access' => [
        'id' => (int)$account['id'],
        'username' => $account['username'],
        'modules' => json_decode($account['modules_json'] ?? '[]', true) ?: [],
        'force_password_change' => (int)$account['force_password_change'] === 1,
    ],
]);
