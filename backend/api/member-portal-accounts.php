<?php
// backend/api/member-portal-accounts.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db = getDB();
$user = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';

function portalModules(array|string|null $value): string {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) return json_encode(array_values($decoded));
    }
    if (is_array($value) && count($value)) return json_encode(array_values($value));
    return json_encode(['dashboard', 'loans', 'payments', 'shareCapital', 'beneficiaries', 'profile']);
}

function portalAccountRow(PDO $db, int $id): array {
    $stmt = $db->prepare("
        SELECT a.id, a.member_id, a.username, a.email, a.force_password_change,
               a.modules_json, a.is_active, a.last_login_at, a.created_at, a.updated_at,
               m.member_no, m.first_name, m.middle_name, m.last_name, m.company, m.department, m.position
        FROM member_portal_accounts a
        JOIN members m ON m.id = a.member_id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_err('Member portal account not found', 404);
    return formatPortalAccount($row);
}

function formatPortalAccount(array $row): array {
    $row['member_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $row['modules'] = json_decode($row['modules_json'] ?? '[]', true) ?: [];
    $row['active'] = (int)$row['is_active'] === 1;
    unset($row['modules_json'], $row['is_active']);
    return $row;
}

if ($method === 'GET') {
    if ($id) json_ok(portalAccountRow($db, $id));

    $where = [];
    $params = [];
    if (!empty($_GET['search'])) {
        $where[] = "(a.username LIKE ? OR a.email LIKE ? OR m.member_no LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
        $where[] = 'a.is_active = ?';
        $params[] = $_GET['is_active'] === 'true' ? 1 : 0;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT a.id, a.member_id, a.username, a.email, a.force_password_change,
               a.modules_json, a.is_active, a.last_login_at, a.created_at, a.updated_at,
               m.member_no, m.first_name, m.middle_name, m.last_name, m.company, m.department, m.position
        FROM member_portal_accounts a
        JOIN members m ON m.id = a.member_id
        $whereSql
        ORDER BY m.last_name, m.first_name
        LIMIT 300
    ");
    $stmt->execute($params);
    json_ok(array_map('formatPortalAccount', $stmt->fetchAll()));
}

if ($method === 'POST') {
    if ($action === 'toggle-active' && $id) {
        require_cap($db, 'ADMIN', $user);
        $row = portalAccountRow($db, $id);
        $next = !empty($row['active']) ? 0 : 1;
        $db->prepare('UPDATE member_portal_accounts SET is_active = ? WHERE id = ?')->execute([$next, $id]);
        json_ok(portalAccountRow($db, $id));
    }

    if ($action === 'reset-password' && $id) {
        require_cap($db, 'ADMIN', $user);
        $temp = 'MEM-' . random_int(100000, 999999);
        $db->prepare('UPDATE member_portal_accounts SET password_hash = ?, force_password_change = 1 WHERE id = ?')
           ->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
        json_ok(['temp_password' => $temp, 'account' => portalAccountRow($db, $id)]);
    }

    require_cap($db, 'ADMIN', $user);
    $d = body();
    foreach (['member_id', 'username', 'password'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }

    $stmt = $db->prepare("
        INSERT INTO member_portal_accounts
          (member_id, username, email, password_hash, force_password_change, modules_json, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int)$d['member_id'],
        trim($d['username']),
        $d['email'] ?? null,
        password_hash($d['password'], PASSWORD_DEFAULT),
        !empty($d['force_password_change']) ? 1 : 0,
        portalModules($d['modules'] ?? null),
        isset($d['is_active']) ? (!empty($d['is_active']) ? 1 : 0) : 1,
        isset($d['created_by']) ? (int)$d['created_by'] : null,
    ]);

    json_ok(portalAccountRow($db, (int)$db->lastInsertId()), 201);
}

if ($method === 'PUT' && $id) {
    require_cap($db, 'ADMIN', $user);
    $d = body();
    foreach (['member_id', 'username'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }

    $params = [
        (int)$d['member_id'],
        trim($d['username']),
        $d['email'] ?? null,
        !empty($d['force_password_change']) ? 1 : 0,
        portalModules($d['modules'] ?? null),
        isset($d['is_active']) ? (!empty($d['is_active']) ? 1 : 0) : 1,
    ];

    $passwordSql = '';
    if (!empty($d['password'])) {
        $passwordSql = ', password_hash = ?';
        $params[] = password_hash($d['password'], PASSWORD_DEFAULT);
    }
    $params[] = $id;

    $db->prepare("
        UPDATE member_portal_accounts
        SET member_id = ?, username = ?, email = ?, force_password_change = ?, modules_json = ?, is_active = ? $passwordSql
        WHERE id = ?
    ")->execute($params);

    json_ok(portalAccountRow($db, $id));
}

json_err('Method not allowed', 405);
