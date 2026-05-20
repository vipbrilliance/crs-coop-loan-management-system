<?php
// backend/api/bills.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db     = getDB();
$user   = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';

function billNo(PDO $db): string {
    $year = date('Y');
    $stmt = $db->query("SELECT bill_no FROM bills WHERE YEAR(created_at) = $year ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int)substr($last, -5) + 1) : 1;
    return 'BL-' . $year . '-' . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
}

function companyName(PDO $db, int $companyId): string {
    $stmt = $db->prepare('SELECT name FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $name = $stmt->fetchColumn();
    if (!$name) json_err('Company not found', 404);
    return $name;
}

function loadBill(PDO $db, int $id): array {
    $stmt = $db->prepare("\n        SELECT b.*, c.name AS company_name, u.name AS prepared_by_name,\n               (b.total_amount - b.amount_remitted) AS balance\n        FROM bills b\n        JOIN companies c ON b.company_id = c.id\n        LEFT JOIN users u ON b.prepared_by = u.id\n        WHERE b.id = ?\n    ");
    $stmt->execute([$id]);
    $bill = $stmt->fetch();
    if (!$bill) json_err('Bill not found', 404);

    $items = $db->prepare("\n        SELECT bi.*, m.member_no, m.first_name, m.last_name,\n               CONCAT(m.first_name, ' ', m.last_name) AS member_name,\n               l.loan_no, s.period_no, s.due_date, s.principal, s.interest\n        FROM bill_items bi\n        JOIN members m ON bi.member_id = m.id\n        JOIN loans l ON bi.loan_id = l.id\n        JOIN amortization_schedule s ON bi.schedule_id = s.id\n        WHERE bi.bill_id = ?\n        ORDER BY m.last_name, m.first_name, l.loan_no, s.period_no\n    ");
    $items->execute([$id]);
    $bill['items'] = $items->fetchAll();

    $remit = $db->prepare("\n        SELECT r.*, u.name AS posted_by_name\n        FROM bill_remittances r\n        LEFT JOIN users u ON r.posted_by = u.id\n        WHERE r.bill_id = ?\n        ORDER BY r.created_at DESC\n    ");
    $remit->execute([$id]);
    $bill['remittances'] = $remit->fetchAll();
    $bill['item_count'] = count($bill['items']);
    return $bill;
}

function updateLoanClosure(PDO $db, int $billId): void {
    $loanStmt = $db->prepare('SELECT DISTINCT loan_id FROM bill_items WHERE bill_id = ?');
    $loanStmt->execute([$billId]);
    foreach ($loanStmt->fetchAll() as $row) {
        $pending = $db->prepare("SELECT COUNT(*) FROM amortization_schedule WHERE loan_id = ? AND status <> 'PAID'");
        $pending->execute([$row['loan_id']]);
        if ((int)$pending->fetchColumn() === 0) {
            $db->prepare("UPDATE loans SET status = 'CLOSED' WHERE id = ?")->execute([$row['loan_id']]);
        }
    }
}

function syncScheduleStatus(PDO $db, int $scheduleId, ?string $orNumber, string $paymentDate): void {
    $paidStmt = $db->prepare('SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE schedule_id = ?');
    $paidStmt->execute([$scheduleId]);
    $paid = round((float)$paidStmt->fetchColumn(), 2);

    $dueStmt = $db->prepare('SELECT amount_due FROM amortization_schedule WHERE id = ?');
    $dueStmt->execute([$scheduleId]);
    $amountDue = round((float)$dueStmt->fetchColumn(), 2);
    $status = $paid >= $amountDue ? 'PAID' : ($paid > 0 ? 'PARTIAL' : 'BILLED');

    $db->prepare("
        UPDATE amortization_schedule
        SET paid_amount = ?, paid_date = ?, or_number = ?, status = ?
        WHERE id = ?
    ")->execute([$paid, $paymentDate, $orNumber, $status, $scheduleId]);
}

function applyBillRemittance(PDO $db, int $billId, string $billNo, int $userId, float $amount, ?string $orNumber, string $paymentDate): void {
    $remaining = round($amount, 2);
    if ($remaining <= 0) return;

    $stmt = $db->prepare("
        SELECT * FROM bill_items
        WHERE bill_id = ? AND amount_paid < amount_due
        ORDER BY id
        FOR UPDATE
    ");
    $stmt->execute([$billId]);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        if ($remaining <= 0) break;
        $itemBalance = round((float)$item['amount_due'] - (float)$item['amount_paid'], 2);
        if ($itemBalance <= 0) continue;
        $applied = min($itemBalance, $remaining);

        $db->prepare("
            INSERT INTO payments (loan_id, schedule_id, amount_paid, payment_type, or_number, payment_date, received_by)
            VALUES (?, ?, ?, 'billing', ?, ?, ?)
        ")->execute([$item['loan_id'], $item['schedule_id'], $applied, $orNumber ?: 'BILL-' . $billNo, $paymentDate, $userId]);

        $newPaid = round((float)$item['amount_paid'] + $applied, 2);
        $itemStatus = $newPaid >= round((float)$item['amount_due'], 2) ? 'PAID' : 'PARTIAL';
        $db->prepare("UPDATE bill_items SET status = ?, amount_paid = ? WHERE id = ?")
           ->execute([$itemStatus, $newPaid, $item['id']]);

        syncScheduleStatus($db, (int)$item['schedule_id'], $orNumber ?: 'BILL-' . $billNo, $paymentDate);
        $remaining = round($remaining - $applied, 2);
    }

    updateLoanClosure($db, $billId);
}

if ($method === 'GET') {
    if ($action === 'companies') {
        $rows = $db->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
        json_ok($rows);
    }

    if ($id) json_ok(loadBill($db, $id));

    $where = [];
    $params = [];
    if (!empty($_GET['company_id'])) { $where[] = 'b.company_id = ?'; $params[] = (int)$_GET['company_id']; }
    if (!empty($_GET['status'])) { $where[] = 'b.status = ?'; $params[] = $_GET['status']; }
    if (!empty($_GET['date_from'])) { $where[] = 'b.billing_period_start >= ?'; $params[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = 'b.billing_period_end <= ?'; $params[] = $_GET['date_to']; }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("\n        SELECT b.*, c.name AS company_name,\n               COUNT(bi.id) AS item_count,\n               (b.total_amount - b.amount_remitted) AS balance\n        FROM bills b\n        JOIN companies c ON b.company_id = c.id\n        LEFT JOIN bill_items bi ON bi.bill_id = b.id\n        $whereSql\n        GROUP BY b.id\n        ORDER BY b.created_at DESC\n        LIMIT 300\n    ");
    $stmt->execute($params);
    json_ok($stmt->fetchAll());
}

if ($method === 'POST') {
    require_cap($db, 'LOAN_OFFICER', $user);
    $d = body();
    $userId = (int)$user['id'];

    if ($action === 'create') {
        foreach (['company_id','billing_period_start','billing_period_end'] as $field) {
            if (empty($d[$field])) json_err("Field '$field' is required");
        }
        $company = companyName($db, (int)$d['company_id']);
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("\n                SELECT s.*, l.member_id, l.id AS loan_id\n                FROM amortization_schedule s\n                JOIN loans l ON s.loan_id = l.id\n                JOIN members m ON l.member_id = m.id\n                LEFT JOIN bill_items existing ON existing.schedule_id = s.id\n                WHERE l.status = 'ACTIVE'\n                  AND m.company = ?\n                  AND s.status IN ('PENDING','OVERDUE')\n                  AND s.due_date BETWEEN ? AND ?\n                  AND existing.id IS NULL\n                ORDER BY s.due_date, m.last_name, m.first_name\n            ");
            $stmt->execute([$company, $d['billing_period_start'], $d['billing_period_end']]);
            $schedules = $stmt->fetchAll();
            if (!$schedules) throw new Exception('No pending amortization periods found for this company in the selected billing period.');

            $total = array_reduce($schedules, fn($sum, $row) => $sum + (float)$row['amount_due'], 0.0);
            $billNo = billNo($db);
            $db->prepare("\n                INSERT INTO bills (bill_no, company_id, status, billing_period_start, billing_period_end, total_amount, amount_remitted, prepared_by, notes)\n                VALUES (?, ?, 'DRAFT', ?, ?, ?, 0, ?, ?)\n            ")->execute([$billNo, $d['company_id'], $d['billing_period_start'], $d['billing_period_end'], round($total, 2), $userId, $d['notes'] ?? null]);
            $billId = (int)$db->lastInsertId();

            $ins = $db->prepare("\n                INSERT INTO bill_items (bill_id, schedule_id, member_id, loan_id, amount_due, amount_paid, status)\n                VALUES (?, ?, ?, ?, ?, 0, 'PENDING')\n            ");
            foreach ($schedules as $row) {
                $ins->execute([$billId, $row['id'], $row['member_id'], $row['loan_id'], $row['amount_due']]);
            }

            $db->commit();
            $createdBill = loadBill($db, $billId);
            audit_log(
                $db, 'Billing', 'CREATED', 'Bill', (string)$billId, $createdBill['bill_no'],
                'Billing cycle created for ' . $company . ' with ' . count($schedules) . ' line item(s).',
                $createdBill, $userId, $createdBill['prepared_by_name'] ?? null, 'HIGH'
            );
            json_ok($createdBill, 201);
        } catch (Exception $e) {
            $db->rollBack();
            json_err($e->getMessage(), 422);
        }
    }

    if (!$id) json_err('Bill id is required');
    $bill = loadBill($db, $id);

    if ($action === 'issue') {
        if ($bill['status'] !== 'DRAFT') json_err('Only DRAFT bills can be issued', 422);
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE bills SET status = 'ISSUED', issued_at = NOW() WHERE id = ?")->execute([$id]);
            $db->prepare("\n                UPDATE amortization_schedule s\n                JOIN bill_items bi ON bi.schedule_id = s.id\n                SET s.status = 'BILLED', s.bill_item_id = bi.id\n                WHERE bi.bill_id = ?\n            ")->execute([$id]);
            $db->commit();
            $issuedBill = loadBill($db, $id);
            audit_log(
                $db, 'Billing', 'ISSUED', 'Bill', (string)$id, $issuedBill['bill_no'],
                'Billing cycle issued and included schedules marked as BILLED.',
                $issuedBill, $userId, $issuedBill['prepared_by_name'] ?? null, 'HIGH'
            );
            json_ok($issuedBill);
        } catch (Exception $e) {
            $db->rollBack();
            json_err('Could not issue bill: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'remittance') {
        require_cap($db, 'LOAN_OFFICER', $user);
        if (!in_array($bill['status'], ['ISSUED','PARTIAL'], true)) json_err('Only ISSUED or PARTIAL bills can receive remittance', 422);
        if (empty($d['amount']) || (float)$d['amount'] <= 0) json_err('Valid amount is required');
        if (empty($d['remittance_date'])) json_err('Remittance date is required');
        $db->beginTransaction();
        try {
            $currentBalance = max(0, round((float)$bill['total_amount'] - (float)$bill['amount_remitted'], 2));
            $appliedAmount = min($currentBalance, round((float)$d['amount'], 2));
            if ($appliedAmount <= 0) throw new Exception('Bill is already fully remitted.');

            $db->prepare("
                INSERT INTO bill_remittances (bill_id, or_number, amount, remittance_date, notes, file_name, posted_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$id, $d['or_number'] ?? null, $appliedAmount, $d['remittance_date'], $d['notes'] ?? null, $d['file_name'] ?? null, $userId]);

            applyBillRemittance($db, $id, $bill['bill_no'], $userId, $appliedAmount, $d['or_number'] ?? null, $d['remittance_date']);

            $newRemitted = round((float)$bill['amount_remitted'] + $appliedAmount, 2);
            $settled = $newRemitted >= (float)$bill['total_amount'];
            $db->prepare("UPDATE bills SET amount_remitted = ?, status = ?, settled_at = ? WHERE id = ?")
               ->execute([$settled ? $bill['total_amount'] : $newRemitted, $settled ? 'SETTLED' : 'PARTIAL', $settled ? date('Y-m-d H:i:s') : null, $id]);
            $db->commit();
            $remittedBill = loadBill($db, $id);
            audit_log(
                $db, 'Billing', 'POSTED', 'Bill remittance', (string)$id, $remittedBill['bill_no'],
                'Company remittance posted for amount ' . number_format($appliedAmount, 2) . '.',
                ['bill' => $remittedBill, 'input' => $d], $userId, null, 'HIGH'
            );
            json_ok($remittedBill);
        } catch (Exception $e) {
            $db->rollBack();
            json_err('Could not record remittance: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'settle') {
        if (!in_array($bill['status'], ['ISSUED','PARTIAL'], true)) json_err('Only issued or partial bills can be settled', 422);
        $db->beginTransaction();
        try {
            $remaining = max(0, round((float)$bill['total_amount'] - (float)$bill['amount_remitted'], 2));
            if ($remaining > 0) {
                applyBillRemittance($db, $id, $bill['bill_no'], $userId, $remaining, 'BILL-' . $bill['bill_no'], date('Y-m-d'));
            }
            $db->prepare("UPDATE bills SET amount_remitted = total_amount, status = 'SETTLED', settled_at = NOW() WHERE id = ?")->execute([$id]);
            $db->commit();
            $settledBill = loadBill($db, $id);
            audit_log(
                $db, 'Billing', 'POSTED', 'Bill settlement', (string)$id, $settledBill['bill_no'],
                'Billing cycle was fully settled.',
                $settledBill, $userId, null, 'HIGH'
            );
            json_ok($settledBill);
        } catch (Exception $e) {
            $db->rollBack();
            json_err('Could not settle bill: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'cancel') {
        if (!in_array($bill['status'], ['DRAFT','ISSUED'], true)) json_err('Only draft or issued bills can be cancelled', 422);
        $db->beginTransaction();
        try {
            if ($bill['status'] === 'ISSUED') {
                $db->prepare("\n                    UPDATE amortization_schedule s\n                    JOIN bill_items bi ON bi.schedule_id = s.id\n                    SET s.status = 'PENDING', s.bill_item_id = NULL\n                    WHERE bi.bill_id = ? AND s.status = 'BILLED'\n                ")->execute([$id]);
            }
            $db->prepare("DELETE FROM bill_items WHERE bill_id = ?")->execute([$id]);
            $db->prepare("UPDATE bills SET status = 'CANCELLED' WHERE id = ?")->execute([$id]);
            $db->commit();
            $cancelledBill = loadBill($db, $id);
            audit_log(
                $db, 'Billing', 'UPDATED', 'Bill', (string)$id, $cancelledBill['bill_no'],
                'Billing cycle was cancelled.',
                $cancelledBill, $userId, null, 'HIGH'
            );
            json_ok($cancelledBill);
        } catch (Exception $e) {
            $db->rollBack();
            json_err('Could not cancel bill: ' . $e->getMessage(), 500);
        }
    }
}

json_err('Method not allowed', 405);
