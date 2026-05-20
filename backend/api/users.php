<?php
// backend/api/users.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db = getDB();
$user = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';


function normalizeRole(?string $role): string {
    $value = strtoupper(trim((string)$role));
    $value = preg_replace('/[^A-Z0-9]+/', '_', $value);
    $value = trim($value, '_');
    $allowed = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'LOAN_OFFICER', 'STAFF', 'AUDITOR'];
    return in_array($value, $allowed, true) ? $value : 'STAFF';
}

function userRow(PDO $db, int $id): array {
    $stmt = $db->prepare('SELECT id, name, email, role, is_active, created_at FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_err('User not found', 404);
    return $row;
}

if ($method === 'GET') {
    if ($id) json_ok(userRow($db, $id));
    $where = [];
    $params = [];
    if (!empty($_GET['search'])) {
        $where[] = '(name LIKE ? OR email LIKE ?)';
        $like = '%' . $_GET['search'] . '%';
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($_GET['role'])) { $where[] = 'role = ?'; $params[] = normalizeRole($_GET['role']); }
    if (isset($_GET['is_active']) && $_GET['is_active'] !== '') { $where[] = 'is_active = ?'; $params[] = $_GET['is_active'] === 'true' ? 1 : 0; }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $db->prepare("SELECT id, name, email, role, is_active, created_at FROM users $whereSql ORDER BY name LIMIT 300");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    json_ok([
        'users' => $users,
        'meta' => [
            'total' => count($users),
            'active' => count(array_filter($users, fn($u) => (int)$u['is_active'] === 1)),
            'inactive' => count(array_filter($users, fn($u) => (int)$u['is_active'] !== 1)),
        ],
    ]);
}

if ($method === 'POST') {
    $d = body();
    if ($action === 'toggle-active' && $id) {
        require_cap($db, 'SUPER_ADMIN', $user);
        $targetUser = userRow($db, $id);
        $next = (int)$targetUser['is_active'] ? 0 : 1;
        $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$next, $id]);
        $updatedUser = userRow($db, $id);
        audit_log($db, 'Users', 'UPDATED', 'User', (string)$id, $updatedUser['email'], ($next ? 'User reactivated.' : 'User deactivated.'), $updatedUser, (int)$user['id'], $user['name'], 'HIGH');
        json_ok(['user' => $updatedUser, 'message' => $next ? 'User reactivated.' : 'User deactivated.']);
    }
    if ($action === 'reset-password' && $id) {
        require_cap($db, 'SUPER_ADMIN', $user);
        $temp = 'CRS-' . random_int(100000, 999999);
        $hash = password_hash($temp, PASSWORD_DEFAULT);
        $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
        $updatedUser = userRow($db, $id);
        audit_log($db, 'Users', 'UPDATED', 'User', (string)$id, $updatedUser['email'], 'Temporary password was generated.', ['user' => $updatedUser], (int)$user['id'], $user['name'], 'HIGH');
        json_ok(['temp_password' => $temp, 'message' => 'Temporary password generated.']);
    }
    require_cap($db, 'SUPER_ADMIN', $user);
    foreach (['name','email','role'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }
    $password = $d['password'] ?? 'ChangeMe123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $role = normalizeRole($d['role']);
    $db->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)')
       ->execute([$d['name'], $d['email'], $hash, $role, !empty($d['is_active']) ? 1 : 0]);
    $newUser = userRow($db, (int)$db->lastInsertId());
    audit_log($db, 'Users', 'CREATED', 'User', (string)$newUser['id'], $newUser['email'], 'User account created with role ' . $role . '.', $newUser, (int)$user['id'], $user['name'], 'HIGH');
    json_ok($newUser, 201);
}

if ($method === 'PUT' && $id) {
    require_cap($db, 'SUPER_ADMIN', $user);
    $d = body();
    foreach (['name','email','role'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }
    $role = normalizeRole($d['role']);
    if (!empty($d['password'])) {
        $db->prepare('UPDATE users SET name = ?, email = ?, role = ?, is_active = ?, password_hash = ? WHERE id = ?')
           ->execute([$d['name'], $d['email'], $role, !empty($d['is_active']) ? 1 : 0, password_hash($d['password'], PASSWORD_DEFAULT), $id]);
    } else {
        $db->prepare('UPDATE users SET name = ?, email = ?, role = ?, is_active = ? WHERE id = ?')
           ->execute([$d['name'], $d['email'], $role, !empty($d['is_active']) ? 1 : 0, $id]);
    }
    $updatedUser = userRow($db, $id);
    audit_log($db, 'Users', 'UPDATED', 'User', (string)$id, $updatedUser['email'], 'User account updated.', ['changes' => $d, 'user' => $updatedUser], (int)$user['id'], $user['name'], 'HIGH');
    json_ok($updatedUser);
}

json_err('Method not allowed', 405);
