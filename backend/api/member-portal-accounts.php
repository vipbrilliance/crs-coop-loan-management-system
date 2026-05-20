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

function generatePortalPassword(): string {
    $colors  = ['Blue','Red','Green','Gold','Silver','Coral','Teal','Rose','Sage','Pearl'];
    $animals = ['Eagle','Tiger','Whale','Crane','Raven','Lynx','Bison','Quail','Finch','Gecko'];
    $words1  = ['River','Storm','Meadow','Summit','Forest','Harbor','Sunset','Valley','Spring','Canyon'];
    $words2  = ['Stone','Grove','Ridge','Shore','Bloom','Creek','Falls','Plain','Drift','Crest'];
    $nouns   = ['Moon','Star','Cloud','Wave','Flame','Frost','Dawn','Dusk','Tide','Wind'];

    $format = random_int(1, 3);

    if ($format === 1) {
        // Format A: Color + Animal + 3 digits — e.g. BlueEagle472
        return $colors[array_rand($colors)] . $animals[array_rand($animals)] . random_int(100, 999);
    } elseif ($format === 2) {
        // Format B: Word + @ + 4 digits — e.g. River@8291
        return $words1[array_rand($words1)] . '@' . random_int(1000, 9999);
    } else {
        // Format C: Word-Word-3digits — e.g. Star-Moon-634
        return $nouns[array_rand($nouns)] . '-' . $words2[array_rand($words2)] . '-' . random_int(100, 999);
    }
}

function portalAccountRow(PDO $db, int $id): array {
    $stmt = $db->prepare("
        SELECT a.id, a.member_id, a.username, a.email, a.force_password_change,
               a.modules_json, a.is_active, a.temp_password, a.last_login_at, a.created_at, a.updated_at,
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
    $row['password_visible'] = $row['temp_password'] ?? null; // null means member changed it
    unset($row['modules_json'], $row['is_active']);
    return $row;
}

// GET: all-members (members table joined with portal accounts — includes unprovisioned)
if ($method === 'GET' && $action === 'all-members') {
    $search = $_GET['search'] ?? '';
    $like = '%' . $search . '%';
    $whereSearch = $search ? "AND (m.member_no LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ?)" : '';
    $params = $search ? [$like, $like, $like, $like] : [];

    $stmt = $db->prepare("
        SELECT m.id AS member_id, m.member_no, m.first_name, m.middle_name, m.last_name,
               m.email AS member_email, m.member_status,
               a.id AS account_id, a.username, a.email AS portal_email,
               a.is_active, a.temp_password, a.force_password_change, a.last_login_at
        FROM members m
        LEFT JOIN member_portal_accounts a ON a.member_id = m.id
        WHERE m.member_status = 'ACTIVE'
        $whereSearch
        ORDER BY m.last_name, m.first_name
        LIMIT 500
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $result = array_map(function($r) {
        $r['member_name'] = trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $r['has_account'] = !empty($r['account_id']);
        $r['active'] = (int)($r['is_active'] ?? 0) === 1;
        $r['password_visible'] = $r['temp_password'] ?? null;
        return $r;
    }, $rows);
    json_ok($result);
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
               a.modules_json, a.is_active, a.temp_password, a.last_login_at, a.created_at, a.updated_at,
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
        $temp = generatePortalPassword();
        $db->prepare('UPDATE member_portal_accounts SET password_hash = ?, temp_password = ?, force_password_change = 1 WHERE id = ?')
           ->execute([password_hash($temp, PASSWORD_DEFAULT), $temp, $id]);
        json_ok(['temp_password' => $temp, 'account' => portalAccountRow($db, $id)]);
    }

    if ($action === 'provision-all') {
        require_cap($db, 'ADMIN', $user);
        // Find all active members without a portal account
        $members = $db->query("
            SELECT m.id, m.member_no, m.first_name, m.last_name, m.email
            FROM members m
            LEFT JOIN member_portal_accounts a ON a.member_id = m.id
            WHERE a.id IS NULL AND m.member_status = 'ACTIVE'
        ")->fetchAll();

        $provisioned = 0;
        foreach ($members as $m) {
            $rawPass = generatePortalPassword();
            $username = strtolower(str_replace('-', '', $m['member_no'])); // e.g. crs00081
            // Ensure unique username
            $check = $db->prepare("SELECT COUNT(*) FROM member_portal_accounts WHERE username = ?");
            $check->execute([$username]);
            if ((int)$check->fetchColumn() > 0) {
                $username .= random_int(10, 99);
            }
            $db->prepare("
                INSERT INTO member_portal_accounts
                  (member_id, username, email, password_hash, temp_password, force_password_change, modules_json, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, 1, ?, 1, ?)
            ")->execute([
                (int)$m['id'],
                $username,
                $m['email'] ?? null,
                password_hash($rawPass, PASSWORD_DEFAULT),
                $rawPass,
                json_encode(['dashboard','loans','payments','shareCapital','beneficiaries','profile']),
                (int)$user['id'],
            ]);
            $provisioned++;
        }
        audit_log($db, $user['id'], 'PROVISION_ALL', 'member_portal_accounts', null, "Provisioned $provisioned member portal accounts");
        json_ok(['provisioned' => $provisioned, 'message' => "$provisioned member(s) provisioned."]);
    }

    if ($action === 'provision-one' && $id) {
        require_cap($db, 'ADMIN', $user);
        // $id here is member_id
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $m = $stmt->fetch();
        if (!$m) json_err('Member not found', 404);

        // Check not already provisioned
        $check = $db->prepare("SELECT id FROM member_portal_accounts WHERE member_id = ?");
        $check->execute([$id]);
        if ($check->fetch()) json_err('Member already has a portal account', 409);

        $rawPass = generatePortalPassword();
        $username = strtolower(str_replace('-', '', $m['member_no']));
        $chk2 = $db->prepare("SELECT COUNT(*) FROM member_portal_accounts WHERE username = ?");
        $chk2->execute([$username]);
        if ((int)$chk2->fetchColumn() > 0) $username .= random_int(10, 99);

        $db->prepare("
            INSERT INTO member_portal_accounts
              (member_id, username, email, password_hash, temp_password, force_password_change, modules_json, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, 1, ?, 1, ?)
        ")->execute([
            (int)$m['id'],
            $username,
            $m['email'] ?? null,
            password_hash($rawPass, PASSWORD_DEFAULT),
            $rawPass,
            json_encode(['dashboard','loans','payments','shareCapital','beneficiaries','profile']),
            (int)$user['id'],
        ]);
        $newId = (int)$db->lastInsertId();
        audit_log($db, $user['id'], 'PROVISION_ONE', 'member_portal_accounts', $newId, "Provisioned portal account for member ID $id");
        json_ok(portalAccountRow($db, $newId));
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
