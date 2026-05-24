<?php
// backend/api/reports.php
// Handles all 5 regulatory report types via ?type= param.
// Default response: JSON. Add ?format=csv for CSV streaming download (REPORT-05).
// Token-in-query-param supported for CSV download links (D-03).

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';

cors();

// D-03: Token-in-query-param injection for CSV download via browser navigation.
// Browser navigation cannot send an Authorization header, so the frontend appends
// ?token=RAW_TOKEN to the URL. Inject it as a Bearer header before require_auth().
$_httpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (empty($_httpAuth) && !empty($_GET['token'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . trim($_GET['token']);
}

$db   = getDB();
$user = require_auth($db);
require_cap($db, 'LOAN_OFFICER', $user);

$type   = $_GET['type'] ?? '';
$format = strtolower($_GET['format'] ?? 'json');

switch ($type) {
    case 'portfolio':
        handlePortfolioReport($db, $user, $format);
        break;
    case 'collection':
        handleCollectionReport($db, $user, $format);
        break;
    case 'par':
        handleParReport($db, $user, $format);
        break;
    case 'member':
        handleMemberReport($db, $user, $format);
        break;
    default:
        json_err('Unknown report type', 400);
}

// -------------------------------------------------------
// REPORT-01: Outstanding loan portfolio
// All active loans with member, principal, outstanding balance, next due date,
// days past due, and PAR bucket.
// -------------------------------------------------------
function handlePortfolioReport(PDO $db, array $user, string $format): void
{
    $stmt = $db->query("
        SELECT
            l.loan_no,
            l.amount AS principal,
            CONCAT(m.first_name, ' ', m.last_name) AS member_name,
            m.member_no,
            COALESCE(
                (
                    SELECT SUM(s2.amount_due) - COALESCE(SUM(p2.amount_paid), 0)
                    FROM amortization_schedule s2
                    LEFT JOIN payments p2 ON p2.schedule_id = s2.id
                    WHERE s2.loan_id = l.id
                      AND s2.restructuring_id IS NULL
                ),
                l.outstanding_balance,
                l.amount
            ) AS outstanding_balance,
            (
                SELECT MIN(s3.due_date)
                FROM amortization_schedule s3
                WHERE s3.loan_id = l.id
                  AND s3.status NOT IN ('PAID')
                  AND s3.restructuring_id IS NULL
            ) AS next_due_date,
            (
                SELECT DATEDIFF(CURDATE(), MIN(s.due_date))
                FROM amortization_schedule s
                WHERE s.loan_id        = l.id
                  AND s.status         NOT IN ('PAID')
                  AND s.due_date       < CURDATE()
                  AND s.restructuring_id IS NULL
            ) AS days_past_due
        FROM loans l
        JOIN members m ON l.member_id = m.id
        WHERE l.status = 'ACTIVE'
        ORDER BY m.last_name, m.first_name
    ");

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $dpd = $row['days_past_due'] !== null ? (int)$row['days_past_due'] : null;
        $row['days_past_due'] = $dpd;
        $row['par_bucket']    = parBucket($dpd);
    }
    unset($row);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="report-portfolio-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Loan No', 'Member', 'Member No', 'Principal', 'Outstanding Balance', 'Next Due Date', 'Days Past Due', 'PAR Bucket']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['loan_no'],
                $row['member_name'],
                $row['member_no'],
                $row['principal'],
                $row['outstanding_balance'],
                $row['next_due_date'],
                $row['days_past_due'],
                $row['par_bucket'],
            ]);
        }
        fclose($out);
        exit;
    }

    json_ok($rows);
}

// -------------------------------------------------------
// REPORT-02: Collection report — payments received in a date range
// -------------------------------------------------------
function handleCollectionReport(PDO $db, array $user, string $format): void
{
    // Default to current month if no range provided
    $from = !empty($_GET['from']) ? $_GET['from'] : date('Y-m-01');
    $to   = !empty($_GET['to'])   ? $_GET['to']   : date('Y-m-t');

    $stmt = $db->prepare("
        SELECT
            p.id,
            p.payment_date,
            p.amount_paid,
            p.or_number,
            p.payment_type AS method,
            l.loan_no,
            CONCAT(m.first_name, ' ', m.last_name) AS member_name,
            m.member_no,
            s.period_no,
            s.due_date,
            s.amount_due
        FROM payments p
        JOIN amortization_schedule s ON s.id = p.schedule_id
        JOIN loans l ON l.id = s.loan_id
        JOIN members m ON m.id = l.member_id
        WHERE p.payment_date BETWEEN :from AND :to
        ORDER BY p.payment_date DESC, l.loan_no
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    $rows = $stmt->fetchAll();

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="report-collection-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Payment Date', 'OR Number', 'Member', 'Member No', 'Loan No', 'Period No', 'Due Date', 'Amount Due', 'Amount Paid', 'Method']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['payment_date'],
                $row['or_number'],
                $row['member_name'],
                $row['member_no'],
                $row['loan_no'],
                $row['period_no'],
                $row['due_date'],
                $row['amount_due'],
                $row['amount_paid'],
                $row['method'],
            ]);
        }
        fclose($out);
        exit;
    }

    json_ok($rows);
}

// -------------------------------------------------------
// REPORT-03: Delinquency / PAR report — overdue accounts by PAR bucket
// Only returns loans where par_bucket != 'Current' (i.e., overdue loans only).
// -------------------------------------------------------
function handleParReport(PDO $db, array $user, string $format): void
{
    $stmt = $db->query("
        SELECT
            l.loan_no,
            CONCAT(m.first_name, ' ', m.last_name) AS member_name,
            m.member_no,
            l.amount AS principal,
            (
                SELECT DATEDIFF(CURDATE(), MIN(s.due_date))
                FROM amortization_schedule s
                WHERE s.loan_id        = l.id
                  AND s.status         NOT IN ('PAID')
                  AND s.due_date       < CURDATE()
                  AND s.restructuring_id IS NULL
            ) AS days_past_due
        FROM loans l
        JOIN members m ON l.member_id = m.id
        WHERE l.status = 'ACTIVE'
        ORDER BY m.last_name, m.first_name
    ");

    $all  = $stmt->fetchAll();
    $rows = [];
    foreach ($all as $row) {
        $dpd    = $row['days_past_due'] !== null ? (int)$row['days_past_due'] : null;
        $bucket = parBucket($dpd);
        if ($bucket === 'Current') continue;   // PAR report shows overdue only
        $row['days_past_due'] = $dpd;
        $row['par_bucket']    = $bucket;
        $rows[] = $row;
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="report-par-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Loan No', 'Member', 'Member No', 'Principal', 'Days Past Due', 'PAR Bucket']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['loan_no'],
                $row['member_name'],
                $row['member_no'],
                $row['principal'],
                $row['days_past_due'],
                $row['par_bucket'],
            ]);
        }
        fclose($out);
        exit;
    }

    json_ok($rows);
}

// -------------------------------------------------------
// REPORT-04: Per-member loan history
// Full amortization schedule for all loans belonging to a given member.
// -------------------------------------------------------
function handleMemberReport(PDO $db, array $user, string $format): void
{
    if (empty($_GET['member_id'])) {
        json_err('member_id required', 400);
    }
    $memberId = (int)$_GET['member_id'];

    $stmt = $db->prepare("
        SELECT
            l.loan_no,
            l.amount,
            l.term_months,
            l.frequency,
            l.annual_rate,
            l.status,
            l.application_date,
            l.first_due_date,
            s.period_no,
            s.due_date,
            s.amount_due,
            s.paid_amount,
            s.status AS period_status,
            s.paid_date,
            s.or_number
        FROM loans l
        JOIN amortization_schedule s ON s.loan_id = l.id
        WHERE l.member_id = :member_id
          AND s.restructuring_id IS NULL
        ORDER BY l.created_at DESC, s.period_no ASC
    ");
    $stmt->execute([':member_id' => $memberId]);
    $rows = $stmt->fetchAll();

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="report-member-' . $memberId . '-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Loan No', 'Loan Amount', 'Terms', 'Frequency', 'Annual Rate', 'Status', 'Application Date', 'Due Date', 'Period', 'Amount Due', 'Paid', 'Balance', 'Period Status', 'OR Number', 'Paid Date']);
        foreach ($rows as $row) {
            $balance = round((float)$row['amount_due'] - (float)($row['paid_amount'] ?? 0), 2);
            fputcsv($out, [
                $row['loan_no'],
                $row['amount'],
                $row['term_months'],
                $row['frequency'],
                $row['annual_rate'],
                $row['status'],
                $row['application_date'],
                $row['due_date'],
                $row['period_no'],
                $row['amount_due'],
                $row['paid_amount'],
                $balance,
                $row['period_status'],
                $row['or_number'],
                $row['paid_date'],
            ]);
        }
        fclose($out);
        exit;
    }

    json_ok($rows);
}
