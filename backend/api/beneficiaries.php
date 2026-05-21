<?php
// backend/api/beneficiaries.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db     = getDB();
$user   = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// GET /api/beneficiaries.php?member_id=X  → list beneficiaries for member
// GET /api/beneficiaries.php?id=X         → single beneficiary record
// POST /api/beneficiaries.php             → create (LOAN_OFFICER+)
// PUT  /api/beneficiaries.php?id=X        → update (LOAN_OFFICER+)
// DELETE /api/beneficiaries.php?id=X      → delete (LOAN_OFFICER+)

if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM member_beneficiaries WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_err('Beneficiary not found', 404);
        json_ok($row);
    }

    $memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : null;
    if (!$memberId) json_err('member_id is required', 400);

    $stmt = $db->prepare("
        SELECT * FROM member_beneficiaries
        WHERE member_id = ?
        ORDER BY beneficiary_type, id
    ");
    $stmt->execute([$memberId]);
    json_ok($stmt->fetchAll());
}

if ($method === 'POST') {
    require_cap($db, 'LOAN_OFFICER', $user);

    $d      = body();
    $errors = [];
    foreach (['member_id', 'full_name', 'beneficiary_type'] as $f) {
        if (empty($d[$f])) {
            $errors[$f] = "Field '$f' is required.";
        }
    }
    if ($errors) json_validation_err($errors);

    // Validate allocation_percent range
    if (isset($d['allocation_percent']) && ((float)$d['allocation_percent'] < 0 || (float)$d['allocation_percent'] > 100)) {
        json_err('allocation_percent must be 0–100', 422);
    }

    // Verify member exists
    $chk = $db->prepare("SELECT id FROM members WHERE id = ?");
    $chk->execute([(int)$d['member_id']]);
    if (!$chk->fetch()) json_err('Member not found', 404);

    // Idempotency check (D-03 migration safety): if same member_id+full_name+beneficiary_type already exists, return it
    $dup = $db->prepare("SELECT id FROM member_beneficiaries WHERE member_id = ? AND full_name = ? AND beneficiary_type = ?");
    $dup->execute([(int)$d['member_id'], $d['full_name'], $d['beneficiary_type']]);
    if ($existingId = $dup->fetchColumn()) {
        json_ok(['id' => $existingId]);
    }

    $stmt = $db->prepare("
        INSERT INTO member_beneficiaries
          (member_id, full_name, relationship, allocation_percent, beneficiary_type,
           contact, birth_date, address, id_type, id_number, registered_name, guardian, remarks, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int)$d['member_id'],
        $d['full_name'],
        $d['relationship']    ?? null,
        isset($d['allocation_percent']) ? (float)$d['allocation_percent'] : 0,
        $d['beneficiary_type'],
        $d['contact']         ?? null,
        $d['birth_date']      ?? null,
        $d['address']         ?? null,
        $d['id_type']         ?? null,
        $d['id_number']       ?? null,
        $d['registered_name'] ?? null,
        $d['guardian']        ?? null,
        $d['remarks']         ?? null,
        (int)$user['id'],  // created_by always from server session, never from $d
    ]);

    $newId = $db->lastInsertId();
    audit_log(
        $db, 'BENEFICIARIES', 'CREATED', 'Beneficiary', (string)$newId, $d['full_name'],
        "Beneficiary {$d['full_name']} added for member {$d['member_id']}",
        $d, (int)$user['id'], $user['name'], 'LOW'
    );
    json_ok(['id' => $newId], 201);
}

if ($method === 'PUT' && $id) {
    require_cap($db, 'LOAN_OFFICER', $user);

    $d = body();

    $stmt = $db->prepare("
        UPDATE member_beneficiaries SET
          full_name = ?, relationship = ?, allocation_percent = ?, beneficiary_type = ?,
          contact = ?, birth_date = ?, address = ?, id_type = ?, id_number = ?,
          registered_name = ?, guardian = ?, remarks = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $d['full_name']       ?? null,
        $d['relationship']    ?? null,
        isset($d['allocation_percent']) ? (float)$d['allocation_percent'] : 0,
        $d['beneficiary_type'] ?? null,
        $d['contact']         ?? null,
        $d['birth_date']      ?? null,
        $d['address']         ?? null,
        $d['id_type']         ?? null,
        $d['id_number']       ?? null,
        $d['registered_name'] ?? null,
        $d['guardian']        ?? null,
        $d['remarks']         ?? null,
        $id,
    ]);

    audit_log(
        $db, 'BENEFICIARIES', 'UPDATED', 'Beneficiary', (string)$id, $d['full_name'] ?? "ID $id",
        "Beneficiary ID $id updated",
        $d, (int)$user['id'], $user['name'], 'LOW'
    );
    json_ok(['updated' => true]);
}

if ($method === 'DELETE' && $id) {
    require_cap($db, 'LOAN_OFFICER', $user);

    $stmt = $db->prepare("DELETE FROM member_beneficiaries WHERE id = ?");
    $stmt->execute([$id]);

    audit_log(
        $db, 'BENEFICIARIES', 'DELETED', 'Beneficiary', (string)$id, "ID $id",
        "Beneficiary record deleted.",
        [], (int)$user['id'], $user['name'], 'MEDIUM'
    );
    json_ok(['deleted' => true]);
}

json_err('Method not allowed', 405);
