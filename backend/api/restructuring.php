<?php
// backend/api/restructuring.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db     = getDB();
$user   = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// GET /api/restructuring.php?loan_id=X  → list all restructurings for a loan
// GET /api/restructuring.php?id=N       → single restructuring record
// POST /api/restructuring.php           → create restructuring (MANAGER only)

if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare("
            SELECT r.*, u.name AS created_by_name
            FROM loan_restructurings r
            LEFT JOIN users u ON u.id = r.created_by
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_err('Restructuring record not found', 404);
        json_ok($row);
    }

    $loanId = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : null;
    $whereSql = $loanId ? 'WHERE r.loan_id = ?' : '';
    $params   = $loanId ? [$loanId] : [];

    $stmt = $db->prepare("
        SELECT r.*, u.name AS created_by_name
        FROM loan_restructurings r
        LEFT JOIN users u ON u.id = r.created_by
        $whereSql
        ORDER BY r.created_at DESC
    ");
    $stmt->execute($params);
    json_ok($stmt->fetchAll());
}

if ($method === 'POST') {
    require_cap($db, 'MANAGER', $user);

    $d = body();

    // Validate required fields
    $errors = [];
    $required = ['loan_id', 'new_amount', 'new_annual_rate', 'new_term_months', 'new_frequency', 'first_due_date'];
    foreach ($required as $f) {
        if (empty($d[$f]) && $d[$f] !== 0) {
            $errors[$f] = "Field '$f' is required.";
        }
    }
    if ($errors) json_validation_err($errors);

    // Load current loan — original_* values come from the DB row, NEVER from $d (D-08)
    $loanStmt = $db->prepare("SELECT * FROM loans WHERE id = ?");
    $loanStmt->execute([(int)$d['loan_id']]);
    $loan = $loanStmt->fetch();
    if (!$loan) json_err('Loan not found', 404);

    // Only ACTIVE or APPROVED loans can be restructured (T-03-13)
    if (!in_array($loan['status'], ['ACTIVE', 'APPROVED'], true)) {
        json_err('Only ACTIVE or APPROVED loans can be restructured.', 422);
    }

    // Generate restructuring_no: RST-{YEAR}-{NNNNN}
    $year      = date('Y');
    $countStmt = $db->query("SELECT COUNT(*) FROM loan_restructurings WHERE YEAR(created_at) = $year");
    $restructuringNo = 'RST-' . $year . '-' . str_pad((int)$countStmt->fetchColumn() + 1, 5, '0', STR_PAD_LEFT);

    // Compute new schedule BEFORE the transaction — fail fast without any DB writes
    $calc     = computeSchedule(
        (float)$d['new_amount'],
        (int)$d['new_term_months'],
        $d['new_frequency'],
        (float)$d['new_annual_rate']
    );
    $dueDates = generateDueDates($d['first_due_date'], $calc['n_periods'], $d['new_frequency']);

    // ── 5-step atomic transaction ──────────────────────────────
    $db->beginTransaction();
    try {
        // Step 1: INSERT INTO loan_restructurings
        // original_* bound from $loan DB row (T-03-12 audit trail integrity)
        $insRestr = $db->prepare("
            INSERT INTO loan_restructurings
              (loan_id, restructuring_no,
               original_amount, original_annual_rate, original_term_months, original_frequency,
               new_amount, new_annual_rate, new_term_months, new_frequency,
               first_due_date, reason, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $insRestr->execute([
            (int)$d['loan_id'],
            $restructuringNo,
            $loan['amount'],        // original_* from DB row — never from $d
            $loan['annual_rate'],
            $loan['term_months'],
            $loan['frequency'],
            $d['new_amount'],
            $d['new_annual_rate'],
            $d['new_term_months'],
            $d['new_frequency'],
            $d['first_due_date'],
            $d['reason'] ?? null,
            $d['notes'] ?? null,
            (int)$user['id'],       // created_by from server session — never from $d (T-03-11)
        ]);
        $restructuringId = (int)$db->lastInsertId();

        // Step 2: Mark existing active schedule rows as superseded
        // Any row with restructuring_id IS NULL is a "current" period — stamp them with this restructuring
        $db->prepare("
            UPDATE amortization_schedule
            SET restructuring_id = ?
            WHERE loan_id = ? AND restructuring_id IS NULL
        ")->execute([$restructuringId, (int)$d['loan_id']]);

        // Step 3: Update loan in-place with new terms
        $db->prepare("
            UPDATE loans
            SET amount = ?, annual_rate = ?, term_months = ?, frequency = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([
            $d['new_amount'],
            $d['new_annual_rate'],
            $d['new_term_months'],
            $d['new_frequency'],
            (int)$d['loan_id'],
        ]);

        // Steps 4+5: Bulk-insert new amortization schedule rows
        // New rows have restructuring_id = NULL (they are the "current active" schedule)
        $insSchedule = $db->prepare("
            INSERT INTO amortization_schedule
              (loan_id, period_no, due_date, principal, interest, amount_due, balance)
            VALUES (?,?,?,?,?,?,?)
        ");
        foreach ($calc['schedule'] as $i => $row) {
            $insSchedule->execute([
                (int)$d['loan_id'],
                $row['period'],
                $dueDates[$i] ?? null,
                $row['principal'],
                $row['interest'],
                $row['payment'],    // schedule 'payment' → amortization_schedule 'amount_due'
                $row['balance'],
            ]);
        }

        $db->commit();

        // audit_log called AFTER commit — never inside the transaction (D-15)
        audit_log(
            $db,
            'RESTRUCTURING',
            'CREATED',
            'LoanRestructuring',
            (string)$restructuringId,
            $restructuringNo,
            "Loan {$loan['loan_no']} restructured. New amount: {$d['new_amount']}.",
            ['input' => $d, 'original_amount' => $loan['amount'], 'original_rate' => $loan['annual_rate']],
            (int)$user['id'],
            $user['name'],
            'HIGH'
        );

        // Fetch and return the saved record with created_by_name join
        $retStmt = $db->prepare("
            SELECT r.*, u.name AS created_by_name
            FROM loan_restructurings r
            LEFT JOIN users u ON u.id = r.created_by
            WHERE r.id = ?
        ");
        $retStmt->execute([$restructuringId]);
        $retRow = $retStmt->fetch();

        json_ok($retRow, 201);

    } catch (\Exception $e) {
        $db->rollBack();
        json_err('Failed to save restructuring: ' . $e->getMessage(), 500);
    }
}

json_err('Method not allowed', 405);
