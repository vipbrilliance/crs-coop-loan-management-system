<?php
// backend/api/members.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// GET /api/members.php          → list all
// GET /api/members.php?id=3     → single member
// GET /api/members.php?search=  → search
// POST /api/members.php         → create
// PUT  /api/members.php?id=3    → update
// DELETE /api/members.php?id=3  → delete

if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        if (!$member) json_err('Member not found', 404);
        // also get their loans
        $lstmt = $db->prepare("
            SELECT l.*, lt.label as loan_type_label
            FROM loans l
            JOIN loan_types lt ON l.loan_type_id = lt.id
            WHERE l.member_id = ?
            ORDER BY l.created_at DESC
        ");
        $lstmt->execute([$id]);
        $member['loans'] = $lstmt->fetchAll();
        json_ok($member);
    }

    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $where  = [];
    $params = [];

    if ($search) {
        $where[]  = "(m.member_no LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ?)";
        $like     = "%$search%";
        $params   = array_merge($params, [$like, $like, $like, $like]);
    }
    if ($status) { $where[] = 'm.member_status = ?'; $params[] = $status; }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT m.*,
            (SELECT COUNT(*) FROM loans WHERE member_id = m.id AND status = 'ACTIVE') AS active_loans,
            (SELECT COUNT(*) FROM loans WHERE member_id = m.id) AS total_loans
        FROM members m
        $whereSql
        ORDER BY m.member_no
        LIMIT 200
    ");
    $stmt->execute($params);
    json_ok($stmt->fetchAll());
}

if ($method === 'POST') {
    $d = body();
    $required = ['member_no','last_name','first_name'];
    foreach ($required as $f) {
        if (empty($d[$f])) json_err("Field '$f' is required");
    }
    // check unique member_no
    $chk = $db->prepare("SELECT id FROM members WHERE member_no = ?");
    $chk->execute([$d['member_no']]);
    if ($chk->fetch()) json_err('Member number already exists');

    $stmt = $db->prepare("
        INSERT INTO members
          (member_no, last_name, first_name, middle_name, address, contact, email,
           company, branch, department, status, position, supervisor, date_hired,
           monthly_salary, share_capital, member_status, profile_image_url, employment_history)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $d['member_no'], $d['last_name'], $d['first_name'],
        $d['middle_name'] ?? null, $d['address'] ?? null, $d['contact'] ?? null,
        $d['email'] ?? null, $d['company'] ?? null, $d['branch'] ?? null,
        $d['department'] ?? null, $d['status'] ?? 'PROBI', $d['position'] ?? null,
        $d['supervisor'] ?? null, $d['date_hired'] ?? null,
        $d['monthly_salary'] ?? 0, $d['share_capital'] ?? 0,
        $d['member_status'] ?? 'ACTIVE',
        $d['profile_image_url'] ?? null,
        $d['employment_history'] ?? null,
    ]);
    json_ok(['id' => $db->lastInsertId()], 201);
}

if ($method === 'PUT' && $id) {
    $d = body();
    $stmt = $db->prepare("
        UPDATE members SET
          last_name=?, first_name=?, middle_name=?, address=?, contact=?, email=?,
          company=?, branch=?, department=?, status=?, position=?, supervisor=?,
          date_hired=?, monthly_salary=?, share_capital=?, member_status=?, profile_image_url=?, employment_history=?
        WHERE id=?
    ");
    $stmt->execute([
        $d['last_name'], $d['first_name'], $d['middle_name'] ?? null,
        $d['address'] ?? null, $d['contact'] ?? null, $d['email'] ?? null,
        $d['company'] ?? null, $d['branch'] ?? null, $d['department'] ?? null,
        $d['status'] ?? 'REGULAR', $d['position'] ?? null, $d['supervisor'] ?? null,
        $d['date_hired'] ?? null, $d['monthly_salary'] ?? 0,
        $d['share_capital'] ?? 0, $d['member_status'] ?? 'ACTIVE',
        $d['profile_image_url'] ?? null,
        $d['employment_history'] ?? null,
        $id,
    ]);
    json_ok(['updated' => true]);
}

if ($method === 'DELETE' && $id) {
    // soft delete
    $stmt = $db->prepare("UPDATE members SET member_status='INACTIVE' WHERE id=?");
    $stmt->execute([$id]);
    json_ok(['deleted' => true]);
}

json_err('Method not allowed', 405);
