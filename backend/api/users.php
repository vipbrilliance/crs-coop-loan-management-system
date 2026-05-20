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


function generateStaffPassword(): string {
    $colors  = ['Blue','Red','Green','Gold','Silver','Coral','Teal','Rose','Sage','Pearl'];
    $animals = ['Eagle','Tiger','Whale','Crane','Raven','Lynx','Bison','Quail','Finch','Gecko'];
    $words1  = ['River','Storm','Meadow','Summit','Forest','Harbor','Sunset','Valley','Spring','Canyon'];
    $words2  = ['Stone','Grove','Ridge','Shore','Bloom','Creek','Falls','Plain','Drift','Crest'];
    $nouns   = ['Moon','Star','Cloud','Wave','Flame','Frost','Dawn','Dusk','Tide','Wind'];
    $format  = random_int(1, 3);
    if ($format === 1) return $colors[array_rand($colors)] . $animals[array_rand($animals)] . random_int(100, 999);
    if ($format === 2) return $words1[array_rand($words1)] . '@' . random_int(1000, 9999);
    return $nouns[array_rand($nouns)] . '-' . $words2[array_rand($words2)] . '-' . random_int(100, 999);
}

function normalizeRole(?string $role): string {
    $value = strtoupper(trim((string)$role));
    $value = preg_replace('/[^A-Z0-9]+/', '_', $value);
    $value = trim($value, '_');
    $allowed = ['SUPER_ADMIN', 'ADMIN', 'MANAGER', 'LOAN_OFFICER', 'STAFF', 'AUDITOR'];
    return in_array($value, $allowed, true) ? $value : 'STAFF';
}

function userRow(PDO $db, int $id): array {
    $stmt = $db->prepare('SELECT id, name, email, role, is_active, temp_password, created_at FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_err('User not found', 404);
    return $row;
}

// ── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $isSuperAdmin = ($user['role'] === 'SUPER_ADMIN');

    if ($id) {
        $row = userRow($db, $id);
        if (!$isSuperAdmin) $row['temp_password'] = null;
        json_ok($row);
    }

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
    $stmt = $db->prepare("SELECT id, name, email, role, is_active, temp_password, created_at FROM users $whereSql ORDER BY name LIMIT 300");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Hide temp_password from non-super-admin callers
    if (!$isSuperAdmin) {
        $users = array_map(function($u) { $u['temp_password'] = null; return $u; }, $users);
    }

    json_ok([
        'users' => $users,
        'meta' => [
            'total' => count($users),
            'active' => count(array_filter($users, fn($u) => (int)$u['is_active'] === 1)),
            'inactive' => count(array_filter($users, fn($u) => (int)$u['is_active'] !== 1)),
        ],
    ]);
}

// ── POST actions ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $d = body();

    // toggle-active
    if ($action === 'toggle-active' && $id) {
        require_cap($db, 'SUPER_ADMIN', $user);
        if ($id === (int)$user['id']) json_err('Cannot suspend your own account', 400);
        $targetUser = userRow($db, $id);
        $next = (int)$targetUser['is_active'] ? 0 : 1;
        $db->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?')->execute([$next, $id]);
        // Kill sessions if suspending
        if (!$next) {
            try { $db->prepare('DELETE FROM admin_sessions WHERE user_id = ?')->execute([$id]); } catch (\Throwable $e) {}
        }
        $updatedUser = userRow($db, $id);
        try { audit_log($db, 'Users', $next ? 'ACTIVATED' : 'SUSPENDED', 'User', (string)$id, $updatedUser['email'], ($next ? 'Account activated' : 'Account suspended') . ' by SUPER_ADMIN.', [], (int)$user['id'], $user['name'], 'HIGH'); } catch (\Throwable $e) {}
        json_ok(['id' => $id, 'is_active' => $next, 'user' => $updatedUser, 'message' => $next ? 'User reactivated.' : 'User deactivated.']);
    }

    // reset-password (SUPER_ADMIN only)
    if ($action === 'reset-password' && $id) {
        require_cap($db, 'SUPER_ADMIN', $user);
        $temp = generateStaffPassword();
        $db->prepare('UPDATE users SET password_hash = ?, temp_password = ?, updated_at = NOW() WHERE id = ?')
           ->execute([password_hash($temp, PASSWORD_DEFAULT), $temp, $id]);
        $updatedUser = userRow($db, $id);
        try { audit_log($db, 'Users', 'RESET_PASSWORD', 'User', (string)$id, $updatedUser['email'], 'SUPER_ADMIN reset password.', [], (int)$user['id'], $user['name'], 'HIGH'); } catch (\Throwable $e) {}
        json_ok(['temp_password' => $temp, 'message' => 'Temporary password generated.']);
    }

    // change-own-password (any authenticated user)
    if ($action === 'change-own-password') {
        if (empty($d['current_password']) || empty($d['new_password'])) json_err('current_password and new_password required', 422);
        if (strlen($d['new_password']) < 8) json_err('New password must be at least 8 characters', 422);
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([(int)$user['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($d['current_password'], $row['password_hash'])) {
            json_err('Current password is incorrect', 401);
        }
        $db->prepare('UPDATE users SET password_hash = ?, temp_password = NULL, updated_at = NOW() WHERE id = ?')
           ->execute([password_hash($d['new_password'], PASSWORD_DEFAULT), (int)$user['id']]);
        try { audit_log($db, 'Users', 'CHANGE_PASSWORD', 'User', (string)$user['id'], $user['email'], 'User changed own password.', [], (int)$user['id'], $user['name'], 'LOW'); } catch (\Throwable $e) {}
        json_ok(['message' => 'Password changed successfully.']);
    }

    // save-setting (SUPER_ADMIN only)
    if ($action === 'save-setting') {
        require_cap($db, 'SUPER_ADMIN', $user);
        if (empty($d['key'])) json_err('key required', 422);
        // Only allow perm_* keys for configurable roles
        if (!preg_match('/^perm_[a-z_]+_(MANAGER|LOAN_OFFICER|STAFF)$/', $d['key'])) json_err('Invalid setting key', 400);
        $db->prepare("INSERT INTO system_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
           ->execute([$d['key'], $d['value'] ?? '0']);
        json_ok(['saved' => true]);
    }

    // get-settings (SUPER_ADMIN only)
    if ($action === 'get-settings') {
        require_cap($db, 'SUPER_ADMIN', $user);
        $rows = $db->query("SELECT `key`, `value` FROM system_settings WHERE `key` LIKE 'perm_%'")->fetchAll();
        json_ok($rows);
    }

    // create user (SUPER_ADMIN only — falls through to here)
    require_cap($db, 'SUPER_ADMIN', $user);
    foreach (['name','email','role'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }
    $temp = isset($d['password']) && $d['password'] !== '' ? $d['password'] : generateStaffPassword();
    $hash = password_hash($temp, PASSWORD_DEFAULT);
    $role = normalizeRole($d['role']);
    $db->prepare('INSERT INTO users (name, email, password_hash, temp_password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
       ->execute([$d['name'], $d['email'], $hash, $temp, $role, !empty($d['is_active']) ? 1 : 1]);
    $newUser = userRow($db, (int)$db->lastInsertId());
    try { audit_log($db, 'Users', 'CREATED', 'User', (string)$newUser['id'], $newUser['email'], 'User account created with role ' . $role . '.', $newUser, (int)$user['id'], $user['name'], 'HIGH'); } catch (\Throwable $e) {}
    json_ok(array_merge($newUser, ['temp_password' => $temp]), 201);
}

// ── PUT ───────────────────────────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    require_cap($db, 'SUPER_ADMIN', $user);
    $d = body();
    foreach (['name','email','role'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }
    $role = normalizeRole($d['role']);
    if (!empty($d['password'])) {
        // SUPER_ADMIN set manual password — clear temp_password
        $db->prepare('UPDATE users SET name = ?, email = ?, role = ?, is_active = ?, password_hash = ?, temp_password = NULL, updated_at = NOW() WHERE id = ?')
           ->execute([$d['name'], $d['email'], $role, !empty($d['is_active']) ? 1 : 0, password_hash($d['password'], PASSWORD_DEFAULT), $id]);
    } else {
        $db->prepare('UPDATE users SET name = ?, email = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?')
           ->execute([$d['name'], $d['email'], $role, !empty($d['is_active']) ? 1 : 0, $id]);
    }
    $updatedUser = userRow($db, $id);
    try { audit_log($db, 'Users', 'UPDATED', 'User', (string)$id, $updatedUser['email'], 'User account updated.', ['changes' => $d, 'user' => $updatedUser], (int)$user['id'], $user['name'], 'HIGH'); } catch (\Throwable $e) {}
    json_ok($updatedUser);
}

json_err('Method not allowed', 405);
