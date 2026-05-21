<?php
// backend/api/co-makers.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db     = getDB();
$user   = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// GET /api/co-makers.php?loan_id=X  → list co-makers for loan (with joined member name)
// GET /api/co-makers.php?id=X       → single co-maker record with joined member name
// POST /api/co-makers.php           → add co-maker (LOAN_OFFICER+)
// DELETE /api/co-makers.php?id=X    → remove co-maker (LOAN_OFFICER+)

if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare("
            SELECT cm.*, m.first_name, m.last_name, m.member_no
            FROM co_makers cm
            JOIN members m ON m.id = cm.member_id
            WHERE cm.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_err('Co-maker not found', 404);
        json_ok($row);
    }

    $loanId = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : null;
    if (!$loanId) json_err('loan_id is required', 400);

    $stmt = $db->prepare("
        SELECT cm.*, m.first_name, m.last_name, m.member_no
        FROM co_makers cm
        JOIN members m ON m.id = cm.member_id
        WHERE cm.loan_id = ?
        ORDER BY cm.created_at
    ");
    $stmt->execute([$loanId]);
    json_ok($stmt->fetchAll());
}

if ($method === 'POST') {
    require_cap($db, 'LOAN_OFFICER', $user);

    $d      = body();
    $errors = [];
    foreach (['loan_id', 'member_id'] as $f) {
        if (empty($d[$f])) {
            $errors[$f] = "Field '$f' is required.";
        }
    }
    if ($errors) json_validation_err($errors);

    // Verify loan exists and get the borrower's member_id
    $loanChk = $db->prepare("SELECT member_id FROM loans WHERE id = ?");
    $loanChk->execute([(int)$d['loan_id']]);
    $loan = $loanChk->fetch();
    if (!$loan) json_err('Loan not found', 404);

    // Self-co-maker guard: borrower cannot be their own co-maker
    if ((int)$loan['member_id'] === (int)$d['member_id']) {
        json_err('The borrower cannot be their own co-maker.', 422);
    }

    // Insert with try/catch to convert UNIQUE constraint violation to 422
    try {
        $stmt = $db->prepare("
            INSERT INTO co_makers (loan_id, member_id, role, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$d['loan_id'],
            (int)$d['member_id'],
            $d['role'] ?? 'Co-maker',
            (int)$user['id'],  // created_by always from server session, never from $d
        ]);
    } catch (PDOException $e) {
        // SQLSTATE 23000: UNIQUE constraint violation
        if ($e->getCode() === '23000') {
            json_err('This member is already a co-maker on this loan.', 422);
        }
        throw $e;
    }

    $newId = $db->lastInsertId();
    audit_log(
        $db, 'CO_MAKERS', 'CREATED', 'CoMaker', (string)$newId, "Loan #{$d['loan_id']}",
        "Co-maker member {$d['member_id']} added to loan {$d['loan_id']}",
        $d, (int)$user['id'], $user['name'], 'LOW'
    );
    json_ok(['id' => $newId], 201);
}

if ($method === 'DELETE' && $id) {
    require_cap($db, 'LOAN_OFFICER', $user);

    $stmt = $db->prepare("DELETE FROM co_makers WHERE id = ?");
    $stmt->execute([$id]);

    audit_log(
        $db, 'CO_MAKERS', 'DELETED', 'CoMaker', (string)$id, "ID $id",
        "Co-maker record removed.",
        [], (int)$user['id'], $user['name'], 'LOW'
    );
    json_ok(['deleted' => true]);
}

json_err('Method not allowed', 405);
