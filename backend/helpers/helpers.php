<?php
// backend/helpers/helpers.php

function cors(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=UTF-8');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

function json_ok(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function json_err(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function body(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// ── Loan Calculator (mirrors loan-calc.js) ──────────────────
function computeSchedule(float $principal, int $termMonths, string $frequency, float $annualRate = 0.12): array {
    $monthlyRate = $annualRate / 12;

    [$periodsPerMonth, $periodRateFactor] = match($frequency) {
        'bimonthly' => [2, 0.5],
        'weekly'    => [4, 0.25],
        default     => [1, 1.0],  // monthly
    };

    $nPeriods           = $termMonths * $periodsPerMonth;
    $principalPerPeriod = $principal / $nPeriods;
    $schedule           = [];
    $remaining          = $principal;
    $totalInterest      = 0.0;

    for ($i = 0; $i < $nPeriods; $i++) {
        $interest  = $remaining * $monthlyRate * $periodRateFactor;
        $payment   = $principalPerPeriod + $interest;
        $balance   = max(0, $remaining - $principalPerPeriod);
        $schedule[] = [
            'period'    => $i + 1,
            'principal' => round($principalPerPeriod, 2),
            'interest'  => round($interest, 2),
            'payment'   => round($payment, 2),
            'balance'   => round($balance, 2),
        ];
        $remaining    -= $principalPerPeriod;
        $totalInterest += $interest;
    }

    return [
        'schedule'      => $schedule,
        'n_periods'     => $nPeriods,
        'total_interest'=> round($totalInterest, 2),
        'total_payment' => round($principal + $totalInterest, 2),
        'first_payment' => $schedule[0]['payment'],
        'last_payment'  => $schedule[$nPeriods - 1]['payment'],
    ];
}

function generateLoanNo(PDO $db): string {
    $year = date('Y');
    $stmt = $db->query("SELECT COUNT(*) FROM loans WHERE YEAR(created_at) = $year");
    $count = (int)$stmt->fetchColumn() + 1;
    return 'LN-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

function generateDueDates(string $firstDate, int $nPeriods, string $frequency): array {
    $dates = [];
    $current = new DateTime($firstDate);
    for ($i = 0; $i < $nPeriods; $i++) {
        $dates[] = $current->format('Y-m-d');
        match($frequency) {
            'bimonthly' => $current->modify('+15 days'),
            'weekly'    => $current->modify('+7 days'),
            default     => $current->modify('+1 month'),
        };
    }
    return $dates;
}


function audit_log(
    PDO $db,
    string $module,
    string $action,
    string $recordType,
    string $recordId,
    string $recordLabel,
    string $detail,
    array $payload = [],
    ?int $actorUserId = null,
    ?string $actorName = null,
    string $risk = 'LOW'
): void {
    try {
        $exists = $db->query("SHOW TABLES LIKE 'audit_logs'")->fetchColumn();
        if (!$exists) return;
        $stmt = $db->prepare("\n            INSERT INTO audit_logs\n              (module, action, record_type, record_id, record_label, actor_user_id, actor_name, detail, risk, payload_json, ip_address, user_agent)\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n        ");
        $stmt->execute([
            strtoupper($module), strtoupper($action), $recordType, $recordId, $recordLabel,
            $actorUserId, $actorName ?: 'System', $detail, strtoupper($risk),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (Throwable $e) {
        // Audit logging must never block the operational transaction.
    }
}
