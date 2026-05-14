<?php
// backend/api/member-portal.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') json_err('Method not allowed', 405);

function bearerToken(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $matches)) return trim($matches[1]);
    return trim($_GET['token'] ?? '');
}

function memberPortalAccount(PDO $db): array {
    $token = bearerToken();
    if ($token === '') json_err('Missing member portal token.', 401);

    $stmt = $db->prepare("
        SELECT a.*, m.member_no, m.first_name, m.middle_name, m.last_name, m.email AS member_email,
               m.contact, m.address, m.company, m.branch, m.department, m.position, m.status,
               m.member_status, m.monthly_salary, m.share_capital
        FROM member_portal_sessions s
        JOIN member_portal_accounts a ON a.id = s.account_id
        JOIN members m ON m.id = a.member_id
        WHERE s.token_hash = ?
          AND s.expires_at > CURRENT_TIMESTAMP
          AND a.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([hash('sha256', $token)]);
    $account = $stmt->fetch();
    if (!$account) json_err('Member portal session expired or invalid.', 401);
    return $account;
}

function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

$account = memberPortalAccount($db);
$memberId = (int)$account['member_id'];

$member = [
    'id' => $memberId,
    'member_no' => $account['member_no'],
    'first_name' => $account['first_name'],
    'middle_name' => $account['middle_name'],
    'last_name' => $account['last_name'],
    'email' => $account['member_email'],
    'contact' => $account['contact'],
    'address' => $account['address'],
    'company' => $account['company'],
    'branch' => $account['branch'],
    'department' => $account['department'],
    'position' => $account['position'],
    'status' => $account['status'],
    'member_status' => $account['member_status'],
    'monthly_salary' => (float)$account['monthly_salary'],
    'share_capital' => (float)$account['share_capital'],
];

$loanStmt = $db->prepare("
    SELECT l.id, l.loan_no, lt.label AS type, l.amount, l.status, l.first_due_date,
           COALESCE(SUM(a.amount_due), l.total_payment, l.amount) AS total_payable,
           COALESCE(SUM(a.amount_due - a.paid_amount), l.total_payment, l.amount) AS outstanding,
           MIN(CASE WHEN a.status IN ('PENDING','PARTIAL','BILLED','OVERDUE') THEN a.due_date END) AS next_due_date,
           MIN(CASE WHEN a.status IN ('PENDING','PARTIAL','BILLED','OVERDUE') THEN a.amount_due - a.paid_amount END) AS next_due_amount
    FROM loans l
    JOIN loan_types lt ON lt.id = l.loan_type_id
    LEFT JOIN amortization_schedule a ON a.loan_id = l.id
    WHERE l.member_id = ?
    GROUP BY l.id, l.loan_no, lt.label, l.amount, l.status, l.first_due_date, l.total_payment
    ORDER BY l.created_at DESC
");
$loanStmt->execute([$memberId]);
$loans = array_map(function ($row) {
    $row['amount'] = (float)$row['amount'];
    $row['total_payable'] = (float)$row['total_payable'];
    $row['outstanding'] = (float)$row['outstanding'];
    $row['next_due_amount'] = (float)($row['next_due_amount'] ?? 0);
    return $row;
}, $loanStmt->fetchAll());

$paymentStmt = $db->prepare("
    SELECT p.payment_date AS date, p.or_number AS reference, l.loan_no,
           p.amount_paid AS amount, 'POSTED' AS status
    FROM payments p
    JOIN loans l ON l.id = p.loan_id
    WHERE l.member_id = ?
    ORDER BY p.payment_date DESC, p.id DESC
    LIMIT 50
");
$paymentStmt->execute([$memberId]);
$payments = array_map(function ($row) {
    $row['amount'] = (float)$row['amount'];
    return $row;
}, $paymentStmt->fetchAll());

$shareStmt = $db->prepare("
    SELECT transaction_date AS date, reference, type, amount, balance_after AS balance, remarks
    FROM share_capital_ledger
    WHERE member_id = ? AND voided = 0
    ORDER BY transaction_date DESC, id DESC
    LIMIT 50
");
$shareStmt->execute([$memberId]);
$shareCapital = array_map(function ($row) {
    $row['amount'] = (float)$row['amount'];
    $row['balance'] = (float)$row['balance'];
    return $row;
}, $shareStmt->fetchAll());

$beneficiaries = [];
if (tableExists($db, 'member_beneficiaries')) {
    $beneficiaryStmt = $db->prepare("
        SELECT full_name AS name, relationship, allocation_percent AS allocation, beneficiary_type AS type, contact
        FROM member_beneficiaries
        WHERE member_id = ?
        ORDER BY beneficiary_type, full_name
    ");
    $beneficiaryStmt->execute([$memberId]);
    $beneficiaries = $beneficiaryStmt->fetchAll();
}

json_ok([
    'member' => $member,
    'access' => [
        'username' => $account['username'],
        'modules' => json_decode($account['modules_json'] ?? '[]', true) ?: [],
        'force_password_change' => (int)$account['force_password_change'] === 1,
    ],
    'loans' => $loans,
    'payments' => $payments,
    'shareCapital' => $shareCapital,
    'beneficiaries' => $beneficiaries,
]);
