<?php
// backend/api/loans.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db     = getDB();
$user   = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';

function nextDeductionDate(?string $from = null): string {
    $date = new DateTime($from ?: date('Y-m-d'));
    $day = (int)$date->format('j');
    if ($day <= 15) {
        return $date->format('Y-m-15');
    }
    $lastDay = (int)$date->format('t');
    if ($day <= min(30, $lastDay)) {
        return $date->format('Y-m-') . str_pad((string)min(30, $lastDay), 2, '0', STR_PAD_LEFT);
    }
    $date->modify('first day of next month');
    return $date->format('Y-m-15');
}


function tableColumns(PDO $db, string $table): array {
    $stmt = $db->query("SHOW COLUMNS FROM $table");
    return array_column($stmt->fetchAll(), 'Field');
}

function syncLoanSchedule(PDO $db, int $loanId, ?string $firstDueDate = null): void {
    $stmt = $db->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    if (!$loan) json_err('Loan not found', 404);

    $rate = (float)$loan['annual_rate'];
    $calc = computeSchedule((float)$loan['amount'], (int)$loan['term_months'], $loan['frequency'], $rate);
    $start = $firstDueDate ?: ($loan['first_due_date'] ?: nextDeductionDate($loan['approval_date'] ?? null));
    $dueDates = generateDueDates($start, $calc['n_periods'], $loan['frequency']);
    $endDate = end($dueDates) ?: null;

    $lock = $db->prepare("\n        SELECT COUNT(*)\n        FROM amortization_schedule s\n        LEFT JOIN bill_items bi ON bi.schedule_id = s.id\n        WHERE s.loan_id = ?\n          AND (s.paid_amount > 0 OR s.status IN ('PAID','PARTIAL','BILLED') OR bi.id IS NOT NULL)\n    ");
    $lock->execute([$loanId]);
    $hasPostedActivity = (int)$lock->fetchColumn() > 0;

    if (!$hasPostedActivity) {
        $db->prepare('DELETE FROM amortization_schedule WHERE loan_id = ?')->execute([$loanId]);
        $ins = $db->prepare("\n            INSERT INTO amortization_schedule\n              (loan_id, period_no, due_date, principal, interest, amount_due, balance, status, paid_amount)\n            VALUES (?,?,?,?,?,?,?,?,0)\n        ");
        foreach ($calc['schedule'] as $i => $row) {
            $ins->execute([
                $loanId, $row['period'], $dueDates[$i] ?? null,
                $row['principal'], $row['interest'], $row['payment'], $row['balance'], 'PENDING',
            ]);
        }
    }

    $db->prepare("\n        UPDATE loans\n        SET first_due_date = ?, end_date = ?, total_payment = ?, total_interest = ?,\n            n_periods = ?, first_payment_amt = ?, last_payment_amt = ?\n        WHERE id = ?\n    ")->execute([
        $start, $endDate, $calc['total_payment'], $calc['total_interest'],
        $calc['n_periods'], $calc['first_payment'], $calc['last_payment'], $loanId,
    ]);
}


// GET /api/loans.php            → list all loans
// GET /api/loans.php?id=5       → loan + schedule
// GET /api/loans.php?action=pipeline → pipeline grouped by status
// POST /api/loans.php           → create loan (saves schedule too)
// PUT  /api/loans.php?id=5      → update loan status / details
// POST /api/loans.php?action=calc → compute schedule without saving

if ($method === 'GET') {
    if ($action === 'pipeline') {
        $stmt = $db->query("
            SELECT l.*, m.first_name, m.last_name, m.member_no, m.company,
                   lt.label as loan_type_label
            FROM loans l
            JOIN members m ON l.member_id = m.id
            JOIN loan_types lt ON l.loan_type_id = lt.id
            ORDER BY l.updated_at DESC
        ");
        $rows = $stmt->fetchAll();
        $pipeline = ['DRAFT'=>[], 'PENDING'=>[], 'APPROVED'=>[], 'ACTIVE'=>[], 'CLOSED'=>[], 'REJECTED'=>[]];
        foreach ($rows as $r) { $pipeline[$r['status']][] = $r; }
        json_ok($pipeline);
    }

    if ($id) {
        $stmt = $db->prepare("
            SELECT l.*, m.first_name, m.last_name, m.member_no, m.contact,
                   m.email, m.address, m.company, m.position, m.status as emp_status,
                   m.supervisor, m.monthly_salary,
                   lt.label as loan_type_label, lt.code as loan_type_code,
                   cm1.first_name as co1_first, cm1.last_name as co1_last,
                   cm2.first_name as co2_first, cm2.last_name as co2_last
            FROM loans l
            JOIN members m  ON l.member_id = m.id
            JOIN loan_types lt ON l.loan_type_id = lt.id
            LEFT JOIN members cm1 ON l.co_maker_1_id = cm1.id
            LEFT JOIN members cm2 ON l.co_maker_2_id = cm2.id
            WHERE l.id = ?
        ");
        $stmt->execute([$id]);
        $loan = $stmt->fetch();
        if (!$loan) json_err('Loan not found', 404);

        $sched = $db->prepare("SELECT * FROM amortization_schedule WHERE loan_id = ? ORDER BY period_no");
        $sched->execute([$id]);
        $loan['schedule'] = $sched->fetchAll();
        json_ok($loan);
    }

    // list with optional filters
    $memberId = $_GET['member_id'] ?? null;
    $status   = $_GET['status'] ?? null;
    $where = []; $params = [];
    if ($memberId) { $where[] = 'l.member_id = ?'; $params[] = $memberId; }
    if ($status)   { $where[] = 'l.status = ?';    $params[] = $status; }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT l.*, m.first_name, m.last_name, m.member_no, m.company, lt.label as loan_type_label
        FROM loans l
        JOIN members m ON l.member_id = m.id
        JOIN loan_types lt ON l.loan_type_id = lt.id
        $whereSql
        ORDER BY l.created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    json_ok($stmt->fetchAll());
}

if ($method === 'POST') {
    $d = body();

    // ── Calc-only endpoint ─────────────────────────────────
    if ($action === 'calc') {
        $required = ['amount','term_months','frequency','annual_rate'];
        foreach ($required as $f) { if (!isset($d[$f])) json_err("Missing: $f"); }
        json_ok(computeSchedule((float)$d['amount'], (int)$d['term_months'], $d['frequency'], (float)$d['annual_rate']));
    }

    // ── Create loan ────────────────────────────────────────
    $required = ['member_id','loan_type_id','amount','term_months','frequency'];
    foreach ($required as $f) { if (empty($d[$f])) json_err("Field '$f' is required"); }

    // fetch loan type rate
    $ltStmt = $db->prepare("SELECT * FROM loan_types WHERE id = ?");
    $ltStmt->execute([$d['loan_type_id']]);
    $lt = $ltStmt->fetch();
    if (!$lt) json_err('Invalid loan type');

    $rate = (float)($d['annual_rate'] ?? $lt['annual_rate']);
    $calc = computeSchedule((float)$d['amount'], (int)$d['term_months'], $d['frequency'], $rate);

    $loanNo       = generateLoanNo($db);
    $appDate      = $d['application_date'] ?? date('Y-m-d');
    $firstDueDate = $d['first_due_date'] ?? null;
    $endDate      = null;

    if ($firstDueDate) {
        $dates = generateDueDates($firstDueDate, $calc['n_periods'], $d['frequency']);
        $endDate = end($dates);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO loans
              (loan_no, member_id, loan_type_id, amount, term_months, frequency, annual_rate,
               purpose, co_maker_1_id, co_maker_2_id, status,
               total_payment, total_interest, n_periods, first_payment_amt, last_payment_amt,
               application_date, first_due_date, end_date, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $loanNo, $d['member_id'], $d['loan_type_id'],
            $d['amount'], $d['term_months'], $d['frequency'], $rate,
            $d['purpose'] ?? null, $d['co_maker_1_id'] ?? null, $d['co_maker_2_id'] ?? null,
            $d['status'] ?? 'DRAFT',
            $calc['total_payment'], $calc['total_interest'], $calc['n_periods'],
            $calc['first_payment'], $calc['last_payment'],
            $appDate, $firstDueDate, $endDate,
            $d['notes'] ?? null, (int)$user['id'],
        ]);
        $loanId = $db->lastInsertId();

        // insert schedule
        $dueDates = $firstDueDate ? generateDueDates($firstDueDate, $calc['n_periods'], $d['frequency']) : [];
        $ins = $db->prepare("
            INSERT INTO amortization_schedule (loan_id, period_no, due_date, principal, interest, amount_due, balance)
            VALUES (?,?,?,?,?,?,?)
        ");
        foreach ($calc['schedule'] as $i => $row) {
            $ins->execute([
                $loanId, $row['period'],
                $dueDates[$i] ?? null,
                $row['principal'], $row['interest'], $row['payment'], $row['balance'],
            ]);
        }

        $db->commit();
        audit_log(
            $db, 'Loans', 'CREATED', 'Loan', (string)$loanId, $loanNo,
            'Loan application created for amount ' . number_format((float)$d['amount'], 2) . '.',
            ['input' => $d, 'calc' => $calc], (int)$user['id'], $user['name'], 'MEDIUM'
        );
        json_ok(['id' => $loanId, 'loan_no' => $loanNo, 'calc' => $calc], 201);
    } catch (\Exception $e) {
        $db->rollBack();
        json_err('Failed to save loan: ' . $e->getMessage(), 500);
    }
}

if ($method === 'PUT' && $action === 'schedule-status') {
    $d = body();
    foreach (['loan_id','period_no','status'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }
    $status = strtoupper($d['status']);
    if (!in_array($status, ['PENDING','BILLED','PAID','PARTIAL','OVERDUE'], true)) json_err('Invalid schedule status');

    $stmt = $db->prepare('SELECT * FROM amortization_schedule WHERE loan_id = ? AND period_no = ?');
    $stmt->execute([(int)$d['loan_id'], (int)$d['period_no']]);
    $period = $stmt->fetch();
    if (!$period) json_err('Amortization period not found', 404);

    $paidAmount = (float)($period['paid_amount'] ?? 0);
    if ($status === 'PAID') $paidAmount = (float)$period['amount_due'];
    if ($status === 'PENDING' || $status === 'OVERDUE' || $status === 'BILLED') $paidAmount = 0;

    $db->prepare("\n        UPDATE amortization_schedule\n        SET status = ?, paid_amount = ?, paid_date = ?, or_number = ?\n        WHERE id = ?\n    ")->execute([
        $status,
        $paidAmount,
        $status === 'PAID' ? ($d['payment_date'] ?? date('Y-m-d')) : null,
        $status === 'PAID' ? ($d['or_number'] ?? null) : null,
        (int)$period['id'],
    ]);

    audit_log(
        $db, 'Loans', 'UPDATED', 'Amortization period', (string)$period['id'],
        'Loan #' . (int)$d['loan_id'] . ' period #' . (int)$d['period_no'],
        'Amortization period status changed to ' . $status . '.',
        ['before' => $period, 'after' => ['status' => $status, 'paid_amount' => $paidAmount]], (int)$user['id'], $user['name'], 'MEDIUM'
    );
    json_ok(['updated' => true, 'loan_id' => (int)$d['loan_id'], 'period_no' => (int)$d['period_no'], 'status' => $status]);
}

if ($method === 'PUT' && $id) {
    $d = body();
    if (in_array($d['status'] ?? '', ['APPROVED', 'ACTIVE'], true)) {
        require_cap($db, 'MANAGER', $user);
    }
    $allowed = ['status','purpose','co_maker_1_id','co_maker_2_id','approved_by_hr',
                'approved_by_coop','approval_date','signed_form_url','signed_form_name',
                'signed_form_data','approval_attachment_name','approval_attachment_data','notes',
                'first_due_date','application_date'];
    $sets = []; $params = [];

    if (($d['status'] ?? null) === 'APPROVED' && empty($d['approval_date'])) {
        $d['approval_date'] = date('Y-m-d');
    }
    if (($d['status'] ?? null) === 'ACTIVE') {
        if (empty($d['approval_date'])) $d['approval_date'] = date('Y-m-d');
        if (empty($d['first_due_date'])) $d['first_due_date'] = nextDeductionDate($d['approval_date']);
    }
    if (!empty($d['approval_attachment_name']) && empty($d['signed_form_name'])) {
        $d['signed_form_name'] = $d['approval_attachment_name'];
    }
    if (!empty($d['approval_attachment_data']) && empty($d['signed_form_data'])) {
        $d['signed_form_data'] = $d['approval_attachment_data'];
    }
    if (!empty($d['signed_form_name']) && empty($d['signed_form_url'])) {
        $d['signed_form_url'] = $d['signed_form_name'];
    }

    $db->beginTransaction();
    try {
        $loanColumns = tableColumns($db, 'loans');
        foreach ($allowed as $f) {
            if (array_key_exists($f, $d) && in_array($f, $loanColumns, true)) { $sets[] = "$f = ?"; $params[] = $d[$f]; }
        }
        if (!$sets) json_err('Nothing to update');
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $id;
        $db->prepare("UPDATE loans SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

        if (($d['status'] ?? null) === 'ACTIVE') {
            syncLoanSchedule($db, $id, $d['first_due_date'] ?? null);
        }

        $db->commit();
        audit_log(
            $db, 'Loans', 'UPDATED', 'Loan', (string)$id, 'Loan #' . $id,
            'Loan record updated' . (!empty($d['status']) ? ' to status ' . $d['status'] : '') . '.',
            ['changes' => $d], (int)$user['id'], $user['name'], 'HIGH'
        );
        json_ok(['updated' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        json_err('Failed to update loan: ' . $e->getMessage(), 500);
    }
}

json_err('Method not allowed', 405);
