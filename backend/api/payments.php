<?php
// backend/api/payments.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db     = getDB();
$user   = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

function loadPayment(PDO $db, int $id): array {
    $stmt = $db->prepare("\n        SELECT p.*, s.period_no, s.amount_due, l.loan_no,\n               m.member_no, CONCAT(m.first_name, ' ', m.last_name) AS member_name,\n               u.name AS received_by_name\n        FROM payments p\n        JOIN amortization_schedule s ON p.schedule_id = s.id\n        JOIN loans l ON p.loan_id = l.id\n        JOIN members m ON l.member_id = m.id\n        LEFT JOIN users u ON p.received_by = u.id\n        WHERE p.id = ?\n    ");
    $stmt->execute([$id]);
    $payment = $stmt->fetch();
    if (!$payment) json_err('Payment not found', 404);
    return $payment;
}

if ($method === 'GET') {
    if ($id) json_ok(loadPayment($db, $id));

    $where = [];
    $params = [];
    if (!empty($_GET['loan_id'])) { $where[] = 'p.loan_id = ?'; $params[] = (int)$_GET['loan_id']; }
    if (!empty($_GET['payment_date'])) { $where[] = 'p.payment_date = ?'; $params[] = $_GET['payment_date']; }
    if (!empty($_GET['payment_type'])) { $where[] = 'p.payment_type = ?'; $params[] = $_GET['payment_type']; }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("\n        SELECT p.*, s.period_no, s.amount_due, l.loan_no,\n               m.member_no, CONCAT(m.first_name, ' ', m.last_name) AS member_name,\n               u.name AS received_by_name\n        FROM payments p\n        JOIN amortization_schedule s ON p.schedule_id = s.id\n        JOIN loans l ON p.loan_id = l.id\n        JOIN members m ON l.member_id = m.id\n        LEFT JOIN users u ON p.received_by = u.id\n        $whereSql\n        ORDER BY p.payment_date DESC, p.created_at DESC\n        LIMIT 500\n    ");
    $stmt->execute($params);
    json_ok($stmt->fetchAll());
}

if ($method === 'POST') {
    require_cap($db, 'LOAN_OFFICER', $user);
    $d = body();
    $errors = [];
    foreach (['loan_id', 'period_no', 'amount_paid', 'payment_date'] as $field) {
        if (empty($d[$field])) $errors[$field] = "Field '$field' is required.";
    }
    if (!$errors && (float)$d['amount_paid'] <= 0) {
        $errors['amount_paid'] = 'Amount paid must be greater than zero.';
    }
    if ($errors) json_validation_err($errors);

    $sched = $db->prepare('SELECT * FROM amortization_schedule WHERE loan_id = ? AND period_no = ?');
    $sched->execute([(int)$d['loan_id'], (int)$d['period_no']]);
    $schedule = $sched->fetch();
    if (!$schedule) json_err('Amortization period not found', 404);

    $db->beginTransaction();
    try {
        $paymentType = $d['payment_type'] ?? ($d['method'] ?? 'direct');
        $db->prepare("\n            INSERT INTO payments (loan_id, schedule_id, amount_paid, payment_type, or_number, payment_date, received_by)\n            VALUES (?, ?, ?, ?, ?, ?, ?)\n        ")->execute([
            (int)$d['loan_id'],
            (int)$schedule['id'],
            (float)$d['amount_paid'],
            $paymentType,
            $d['or_number'] ?? null,
            $d['payment_date'],
            (int)$user['id'],
        ]);
        $paymentId = (int)$db->lastInsertId();

        $paidStmt = $db->prepare('SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE schedule_id = ?');
        $paidStmt->execute([(int)$schedule['id']]);
        $paid = round((float)$paidStmt->fetchColumn(), 2);
        $amountDue = round((float)$schedule['amount_due'], 2);
        $status = $paid >= $amountDue ? 'PAID' : ($paid > 0 ? 'PARTIAL' : $schedule['status']);

        $db->prepare("\n            UPDATE amortization_schedule\n            SET paid_amount = ?, paid_date = ?, or_number = ?, status = ?\n            WHERE id = ?\n        ")->execute([$paid, $d['payment_date'], $d['or_number'] ?? null, $status, (int)$schedule['id']]);

        $remaining = $db->prepare("SELECT COUNT(*) FROM amortization_schedule WHERE loan_id = ? AND status <> 'PAID'");
        $remaining->execute([(int)$d['loan_id']]);
        if ((int)$remaining->fetchColumn() === 0) {
            $db->prepare("UPDATE loans SET status = 'CLOSED' WHERE id = ?")->execute([(int)$d['loan_id']]);
        }

        $db->commit();
        $payment = loadPayment($db, $paymentId);
        audit_log(
            $db, 'Payments', 'POSTED', 'Payment', (string)$paymentId,
            $payment['or_number'] ?: ('Payment #' . $paymentId),
            'Loan payment posted for ' . $payment['loan_no'] . ' period #' . $payment['period_no'] . '.',
            $payment, (int)$user['id'], $user['name'], 'HIGH'
        );
        json_ok($payment, 201);
    } catch (Exception $e) {
        $db->rollBack();
        json_err('Could not post payment: ' . $e->getMessage(), 500);
    }
}

json_err('Method not allowed', 405);
