<?php
// backend/api/dashboard.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db = getDB();
$user = require_auth($db);

function scalar(PDO $db, string $sql, array $params = []): float {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (float)($stmt->fetchColumn() ?: 0);
}

$activeLoans = scalar($db, "SELECT COUNT(*) FROM loans WHERE status = 'ACTIVE'");
$totalMembers = scalar($db, "SELECT COUNT(*) FROM members WHERE member_status = 'ACTIVE'");
$pendingLoans = scalar($db, "SELECT COUNT(*) FROM loans WHERE status IN ('DRAFT','PENDING','APPROVED')");
$outstanding = scalar($db, "\n    SELECT COALESCE(SUM(amount_due - paid_amount), 0)\n    FROM amortization_schedule s\n    JOIN loans l ON l.id = s.loan_id\n    WHERE l.status = 'ACTIVE' AND s.status IN ('PENDING','BILLED','PARTIAL','OVERDUE')\n");
$overdueCount = scalar($db, "\n    SELECT COUNT(DISTINCT l.id)\n    FROM loans l\n    JOIN amortization_schedule s ON s.loan_id = l.id\n    WHERE l.status = 'ACTIVE' AND s.status = 'OVERDUE'\n");
$overdueBalance = scalar($db, "\n    SELECT COALESCE(SUM(s.amount_due - s.paid_amount), 0)\n    FROM amortization_schedule s\n    JOIN loans l ON l.id = s.loan_id\n    WHERE l.status = 'ACTIVE' AND s.status = 'OVERDUE'\n");
$newThisMonth = scalar($db, "\n    SELECT COUNT(*) FROM loans\n    WHERE MONTH(application_date) = MONTH(CURDATE()) AND YEAR(application_date) = YEAR(CURDATE())\n");

$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');
$expected = scalar($db, "SELECT COALESCE(SUM(amount_due), 0) FROM amortization_schedule WHERE due_date BETWEEN ? AND ?", [$monthStart, $monthEnd]);
$collected = scalar($db, "SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_date BETWEEN ? AND ?", [$monthStart, $monthEnd]);
$rate = $expected > 0 ? round(($collected / $expected) * 100, 1) : 0;

$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    $label = date('M Y', strtotime($start));
    $exp = scalar($db, "SELECT COALESCE(SUM(amount_due), 0) FROM amortization_schedule WHERE due_date BETWEEN ? AND ?", [$start, $end]);
    $coll = scalar($db, "SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_date BETWEEN ? AND ?", [$start, $end]);
    $monthly[] = [
        'month' => date('M', strtotime($start)),
        'label' => $label,
        'expected' => round($exp, 2),
        'collected' => round($coll, 2),
        'rate' => $exp > 0 ? round(($coll / $exp) * 100, 1) : 0,
    ];
}

$loanStatus = $db->query("SELECT status, COUNT(*) AS count FROM loans GROUP BY status ORDER BY status")->fetchAll();
$loanTypes = $db->query("\n    SELECT lt.label, COUNT(l.id) AS count, COALESCE(SUM(l.amount), 0) AS amount\n    FROM loans l\n    JOIN loan_types lt ON lt.id = l.loan_type_id\n    WHERE l.status = 'ACTIVE'\n    GROUP BY lt.id, lt.label\n    ORDER BY amount DESC\n")->fetchAll();
$recent = $db->query("\n    SELECT l.*, m.first_name, m.last_name, m.member_no, lt.label AS loan_type_label\n    FROM loans l\n    JOIN members m ON m.id = l.member_id\n    JOIN loan_types lt ON lt.id = l.loan_type_id\n    ORDER BY l.created_at DESC\n    LIMIT 8\n")->fetchAll();
$topOverdue = $db->query("\n    SELECT l.loan_no, m.first_name, m.last_name, m.member_no,\n           COUNT(s.id) AS overdue_periods, COALESCE(SUM(s.amount_due - s.paid_amount), 0) AS balance\n    FROM amortization_schedule s\n    JOIN loans l ON l.id = s.loan_id\n    JOIN members m ON m.id = l.member_id\n    WHERE s.status = 'OVERDUE'\n    GROUP BY l.id, m.id\n    ORDER BY balance DESC\n    LIMIT 5\n")->fetchAll();

json_ok([
    'stats' => [
        'active_loans' => (int)$activeLoans,
        'total_members' => (int)$totalMembers,
        'pending_loans' => (int)$pendingLoans,
        'total_outstanding' => round($outstanding, 2),
        'collection_rate' => $rate,
        'overdue_count' => (int)$overdueCount,
        'overdue_balance' => round($overdueBalance, 2),
        'new_loans_this_month' => (int)$newThisMonth,
        'collections_this_month' => round($collected, 2),
    ],
    'monthly_collections' => $monthly,
    'loan_status' => $loanStatus,
    'loan_types' => $loanTypes,
    'recent_loans' => $recent,
    'top_overdue' => $topOverdue,
    'generated_at' => date(DATE_ATOM),
]);
