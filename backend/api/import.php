<?php
// backend/api/import.php
// CSV import endpoint — SUPER_ADMIN only.
// Handles ?type=members (IMPORT-01) and ?type=loans (IMPORT-02).
// Each type: validates file, strips BOM (D-10/IMPORT-05), parses rows,
// performs INSERT ... ON DUPLICATE KEY UPDATE (IMPORT-04), and returns
// {inserted, updated, skipped, errors}.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db   = getDB();
$user = require_auth($db);
require_cap($db, 'SUPER_ADMIN', $user);  // T-05-05: gate before any file is read

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('Method not allowed', 405);
}

$type = $_GET['type'] ?? '';

// ── 1. Validate file upload ──────────────────────────────────
if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    json_err('No CSV file uploaded', 400);
}

// T-05-06: 5 MB size limit enforced before reading file content
if ($_FILES['csv']['size'] > 5 * 1024 * 1024) {
    json_err('File too large (max 5 MB)', 400);
}

// T-05-08: MIME + extension check
$allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'];
$fileMime     = $_FILES['csv']['type'] ?? '';
$origName     = $_FILES['csv']['name'] ?? '';
$ext          = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($fileMime, $allowedMimes, true) && $ext !== 'csv') {
    json_err('File must be a CSV', 400);
}
if ($ext !== 'csv') {
    json_err('File must be a CSV', 400);
}

// ── 2. Read + BOM-strip (D-10, IMPORT-05, RESEARCH Pattern 5) ─
$content = file_get_contents($_FILES['csv']['tmp_name']);
$content = ltrim($content, "\xEF\xBB\xBF");  // strip UTF-8 BOM silently

// Normalise line endings, then split; drop blank trailing lines
$lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));
$lines = array_values($lines);

if (count($lines) < 2) {
    json_err('CSV has no data rows', 400);
}

// ── 3. Parse header row ─────────────────────────────────────
$headers = str_getcsv(trim($lines[0]));
$headers = array_map('trim', $headers);

// ── 4. Route to handler ─────────────────────────────────────
switch ($type) {
    case 'members':
        handleMemberImport($db, $user, $headers, $lines);
        break;
    case 'loans':
        handleLoanImport($db, $user, $headers, $lines);
        break;
    default:
        json_err('Unknown import type', 400);
}

// ════════════════════════════════════════════════════════════
// MEMBER IMPORT  (IMPORT-01 / IMPORT-03 / IMPORT-04 / IMPORT-05)
// ════════════════════════════════════════════════════════════
function handleMemberImport(PDO $db, array $user, array $headers, array $lines): never
{
    $required = ['member_no', 'last_name', 'first_name'];
    foreach ($required as $col) {
        if (!in_array($col, $headers, true)) {
            json_err("Required column '{$col}' not found in CSV header row", 422);
        }
    }

    // Build column-index map
    $colIdx = array_flip($headers);

    $inserted = 0;
    $updated  = 0;
    $skipped  = [];  // per-row skip reasons

    // T-05-07: All values go through PDO prepared statements — no concatenation
    $stmt = $db->prepare("
        INSERT INTO members
            (member_no, last_name, first_name, middle_name, contact, email,
             company, date_hired, monthly_salary, share_capital)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            last_name      = VALUES(last_name),
            first_name     = VALUES(first_name),
            middle_name    = VALUES(middle_name),
            contact        = VALUES(contact),
            email          = VALUES(email),
            company        = VALUES(company),
            date_hired     = VALUES(date_hired),
            monthly_salary = VALUES(monthly_salary),
            share_capital  = VALUES(share_capital),
            updated_at     = CURRENT_TIMESTAMP
    ");

    foreach (array_slice($lines, 1) as $rowNum => $line) {
        $line = trim($line);
        if ($line === '') continue;

        $fields = str_getcsv($line);
        $rowNo  = $rowNum + 2;  // human-readable row number (header = row 1)

        // ── Per-row validation (IMPORT-03) ──────────────────
        $memberNo = trim($fields[$colIdx['member_no']] ?? '');
        if ($memberNo === '') {
            $skipped[] = ['row' => $rowNo, 'reason' => 'member_no is required'];
            continue;
        }

        $lastName = trim($fields[$colIdx['last_name']] ?? '');
        if ($lastName === '') {
            $skipped[] = ['row' => $rowNo, 'reason' => 'last_name is required'];
            continue;
        }

        $firstName = trim($fields[$colIdx['first_name']] ?? '');
        if ($firstName === '') {
            $skipped[] = ['row' => $rowNo, 'reason' => 'first_name is required'];
            continue;
        }

        $middleName    = trim($fields[$colIdx['middle_name'] ?? -1] ?? '');
        $contact       = trim($fields[$colIdx['contact']       ?? -1] ?? '');
        $email         = trim($fields[$colIdx['email']         ?? -1] ?? '');
        $company       = trim($fields[$colIdx['company']       ?? -1] ?? '');
        $dateHiredRaw  = trim($fields[$colIdx['date_hired']    ?? -1] ?? '');
        $salaryRaw     = trim($fields[$colIdx['monthly_salary']?? -1] ?? '');
        $shareCapRaw   = trim($fields[$colIdx['share_capital'] ?? -1] ?? '');

        if ($salaryRaw !== '' && !is_numeric($salaryRaw)) {
            $skipped[] = ['row' => $rowNo, 'reason' => "monthly_salary '{$salaryRaw}' is not a valid number"];
            continue;
        }
        if ($shareCapRaw !== '' && !is_numeric($shareCapRaw)) {
            $skipped[] = ['row' => $rowNo, 'reason' => "share_capital '{$shareCapRaw}' is not a valid number"];
            continue;
        }

        $dateHired  = ($dateHiredRaw !== '' && strtotime($dateHiredRaw) !== false)
                        ? date('Y-m-d', strtotime($dateHiredRaw))
                        : null;
        $salary     = ($salaryRaw   !== '') ? (float)$salaryRaw   : null;
        $shareCap   = ($shareCapRaw !== '') ? (float)$shareCapRaw : null;

        // ── Upsert (IMPORT-04) ──────────────────────────────
        $stmt->execute([
            $memberNo, $lastName, $firstName, $middleName ?: null,
            $contact ?: null, $email ?: null, $company ?: null,
            $dateHired, $salary, $shareCap,
        ]);

        // MySQL rowCount(): 1 = inserted, 2 = updated, 0 = unchanged
        $rc = $stmt->rowCount();
        if ($rc === 2) {
            $updated++;
        } elseif ($rc === 1) {
            $inserted++;
        } else {
            // Unchanged (same data re-imported) — count as updated
            $updated++;
        }
    }

    $skippedCount = count($skipped);

    audit_log(
        $db, 'IMPORT', 'MEMBER_IMPORT', 'members', '', 'batch',
        "{$inserted} inserted, {$updated} updated, {$skippedCount} skipped",
        [], (int)$user['id'], $user['name'], 'MEDIUM'
    );

    json_ok([
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skippedCount,
        'errors'   => $skipped,
    ]);
}

// ════════════════════════════════════════════════════════════
// LOAN IMPORT  (IMPORT-02 / D-07 / D-08 / D-09)
// ════════════════════════════════════════════════════════════
function handleLoanImport(PDO $db, array $user, array $headers, array $lines): never
{
    $required = ['loan_no', 'member_no', 'principal', 'annual_rate',
                 'term_months', 'frequency', 'disbursement_date', 'outstanding_balance'];
    foreach ($required as $col) {
        if (!in_array($col, $headers, true)) {
            json_err("Required column '{$col}' not found in CSV header row", 422);
        }
    }

    $colIdx           = array_flip($headers);
    $allowedFrequency = ['monthly', 'bimonthly', 'weekly'];

    $inserted = 0;
    $updated  = 0;
    $skipped  = [];

    // T-05-07: PDO prepared statement — no CSV-value concatenation
    // D-07: no amortization_schedule rows created for imported loans
    // D-08: status derived from outstanding_balance
    // D-09: upsert on loan_no UNIQUE key
    // Open Q3 / A2: disbursement_date → approval_date (conceptually equivalent for historical import)
    $stmt = $db->prepare("
        INSERT INTO loans
            (loan_no, member_id, amount, annual_rate, term_months, frequency,
             approval_date, outstanding_balance, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            outstanding_balance = VALUES(outstanding_balance),
            status              = VALUES(status),
            updated_at          = CURRENT_TIMESTAMP
    ");

    // Member existence lookup — T-05-09: never trust client-supplied member_id
    $memberLookup = $db->prepare("SELECT id FROM members WHERE member_no = ? LIMIT 1");

    foreach (array_slice($lines, 1) as $rowNum => $line) {
        $line = trim($line);
        if ($line === '') continue;

        $fields = str_getcsv($line);
        $rowNo  = $rowNum + 2;

        // ── Per-row validation (IMPORT-03) ──────────────────
        $loanNo  = trim($fields[$colIdx['loan_no']]  ?? '');
        if ($loanNo === '') {
            $skipped[] = ['row' => $rowNo, 'reason' => 'loan_no is required'];
            continue;
        }

        $memberNo = trim($fields[$colIdx['member_no']] ?? '');
        if ($memberNo === '') {
            $skipped[] = ['row' => $rowNo, 'reason' => 'member_no is required'];
            continue;
        }

        $principalRaw = trim($fields[$colIdx['principal']] ?? '');
        if (!is_numeric($principalRaw) || (float)$principalRaw <= 0) {
            $skipped[] = ['row' => $rowNo, 'reason' => "principal '{$principalRaw}' must be a positive number"];
            continue;
        }

        $annualRateRaw = trim($fields[$colIdx['annual_rate']] ?? '');
        if (!is_numeric($annualRateRaw) || (float)$annualRateRaw < 0) {
            $skipped[] = ['row' => $rowNo, 'reason' => "annual_rate '{$annualRateRaw}' must be a non-negative number"];
            continue;
        }

        $termMonthsRaw = trim($fields[$colIdx['term_months']] ?? '');
        if (!ctype_digit($termMonthsRaw) || (int)$termMonthsRaw <= 0) {
            $skipped[] = ['row' => $rowNo, 'reason' => "term_months '{$termMonthsRaw}' must be a positive integer"];
            continue;
        }

        $frequency = strtolower(trim($fields[$colIdx['frequency']] ?? ''));
        if (!in_array($frequency, $allowedFrequency, true)) {
            $skipped[] = ['row' => $rowNo, 'reason' => "frequency '{$frequency}' must be one of: " . implode(', ', $allowedFrequency)];
            continue;
        }

        $disbursementDateRaw = trim($fields[$colIdx['disbursement_date']] ?? '');
        if ($disbursementDateRaw === '' || strtotime($disbursementDateRaw) === false) {
            $skipped[] = ['row' => $rowNo, 'reason' => "disbursement_date '{$disbursementDateRaw}' is not a valid date"];
            continue;
        }

        $outstandingBalanceRaw = trim($fields[$colIdx['outstanding_balance']] ?? '');
        if (!is_numeric($outstandingBalanceRaw) || (float)$outstandingBalanceRaw < 0) {
            $skipped[] = ['row' => $rowNo, 'reason' => "outstanding_balance '{$outstandingBalanceRaw}' must be a non-negative number"];
            continue;
        }

        // ── Server-side member existence check (Pitfall 4, T-05-09) ──
        $memberLookup->execute([$memberNo]);
        $memberRow = $memberLookup->fetch();
        if (!$memberRow) {
            $skipped[] = ['row' => $rowNo, 'reason' => "Member '{$memberNo}' not found in database"];
            continue;
        }
        $memberId = (int)$memberRow['id'];

        // ── Derived values ──────────────────────────────────
        $approvalDate       = date('Y-m-d', strtotime($disbursementDateRaw));  // Open Q3 A2
        $outstandingBalance = (float)$outstandingBalanceRaw;
        // D-08: status from outstanding_balance
        $status = ($outstandingBalance > 0) ? 'ACTIVE' : 'CLOSED';

        // ── Upsert (IMPORT-04, D-09) ────────────────────────
        $stmt->execute([
            $loanNo,
            $memberId,
            (float)$principalRaw,
            (float)$annualRateRaw,
            (int)$termMonthsRaw,
            $frequency,
            $approvalDate,
            $outstandingBalance,
            $status,
            (int)$user['id'],
        ]);

        $rc = $stmt->rowCount();
        if ($rc === 2) {
            $updated++;
        } elseif ($rc === 1) {
            $inserted++;
        } else {
            $updated++;
        }
    }

    $skippedCount = count($skipped);

    audit_log(
        $db, 'IMPORT', 'LOAN_IMPORT', 'loans', '', 'batch',
        "{$inserted} inserted, {$updated} updated, {$skippedCount} skipped",
        [], (int)$user['id'], $user['name'], 'MEDIUM'
    );

    json_ok([
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skippedCount,
        'errors'   => $skipped,
    ]);
}
