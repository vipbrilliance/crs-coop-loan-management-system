<?php
// backend/api/share-capital.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db = getDB();
$user = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

function signedAmount(array $row): float {
    return $row['type'] === 'WITHDRAWAL' ? -(float)$row['amount'] : (float)$row['amount'];
}

function recomputeMemberLedger(PDO $db, int $memberId): void {
    $stmt = $db->prepare("SELECT * FROM share_capital_ledger WHERE member_id = ? ORDER BY transaction_date, id");
    $stmt->execute([$memberId]);
    $balance = 0.0;
    foreach ($stmt->fetchAll() as $row) {
        if (!(int)$row['voided']) $balance += signedAmount($row);
        $db->prepare('UPDATE share_capital_ledger SET balance_after = ? WHERE id = ?')
           ->execute([round($balance, 2), $row['id']]);
    }
    $db->prepare('UPDATE members SET share_capital = ? WHERE id = ?')->execute([round($balance, 2), $memberId]);
}

function loadLedgerRow(PDO $db, int $id): array {
    $stmt = $db->prepare("\n        SELECT scl.*, scl.transaction_date AS date, m.member_no, CONCAT(m.first_name, ' ', m.last_name) AS member_name\n        FROM share_capital_ledger scl\n        JOIN members m ON m.id = scl.member_id\n        WHERE scl.id = ?\n    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_err('Ledger entry not found', 404);
    return $row;
}

if ($method === 'GET') {
    if ($id) json_ok(loadLedgerRow($db, $id));
    $where = [];
    $params = [];
    if (!empty($_GET['member_id'])) { $where[] = 'scl.member_id = ?'; $params[] = (int)$_GET['member_id']; }
    if (!empty($_GET['type'])) { $where[] = 'scl.type = ?'; $params[] = $_GET['type']; }
    if (!empty($_GET['date_from'])) { $where[] = 'scl.transaction_date >= ?'; $params[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = 'scl.transaction_date <= ?'; $params[] = $_GET['date_to']; }
    if (!empty($_GET['search'])) {
        $where[] = "(scl.reference LIKE ? OR scl.remarks LIKE ? OR m.member_no LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ?)";
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $db->prepare("\n        SELECT scl.*, scl.transaction_date AS date, m.member_no, CONCAT(m.first_name, ' ', m.last_name) AS member_name\n        FROM share_capital_ledger scl\n        JOIN members m ON m.id = scl.member_id\n        $whereSql\n        ORDER BY scl.transaction_date DESC, scl.id DESC\n        LIMIT 1000\n    ");
    $stmt->execute($params);
    json_ok($stmt->fetchAll());
}

if ($method === 'POST') {
    require_cap($db, 'LOAN_OFFICER', $user);
    $d = body();
    foreach (['member_id','amount','date'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }
    if ((float)$d['amount'] <= 0) json_err('Amount must be greater than zero');
    $type = $d['type'] ?? 'DEPOSIT';
    if (!in_array($type, ['OPENING','DEPOSIT','WITHDRAWAL','DIVIDEND','ADJUSTMENT'], true)) json_err('Invalid transaction type');

    $db->beginTransaction();
    try {
        $sourceKey = $d['source_key'] ?? null;
        if ($sourceKey) {
            $existing = $db->prepare('SELECT id FROM share_capital_ledger WHERE source_key = ?');
            $existing->execute([$sourceKey]);
            $existingId = $existing->fetchColumn();
            if ($existingId) {
                $db->commit();
                json_ok(loadLedgerRow($db, (int)$existingId));
            }
        }
        $stmt = $db->prepare("\n            INSERT INTO share_capital_ledger\n              (member_id, transaction_date, type, amount, reference, source, company, remarks, source_key, posted_by)\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n        ");
        $stmt->execute([
            (int)$d['member_id'], $d['date'], $type, (float)$d['amount'],
            $d['reference'] ?? null, $d['source'] ?? null, $d['company'] ?? null,
            $d['remarks'] ?? null, $sourceKey, (int)$user['id'],
        ]);
        $entryId = (int)$db->lastInsertId();
        recomputeMemberLedger($db, (int)$d['member_id']);
        $db->commit();
        $entry = loadLedgerRow($db, $entryId);
        audit_log(
            $db, 'Share Capital', 'POSTED', 'Share capital ledger', (string)$entryId,
            $entry['reference'] ?: ('Share capital #' . $entryId),
            $type . ' share capital entry posted for ' . $entry['member_name'] . '.',
            $entry, (int)$user['id'], $user['name'], 'HIGH'
        );
        json_ok($entry, 201);
    } catch (Exception $e) {
        $db->rollBack();
        json_err('Could not post share capital entry: ' . $e->getMessage(), 500);
    }
}

if ($method === 'PUT' && $id) {
    require_cap($db, 'LOAN_OFFICER', $user);
    $d = body();
    $row = loadLedgerRow($db, $id);
    $voided = !empty($d['voided']) ? 1 : 0;
    $db->beginTransaction();
    try {
        $db->prepare('UPDATE share_capital_ledger SET voided = ?, voided_at = ? WHERE id = ?')
           ->execute([$voided, $voided ? date('Y-m-d H:i:s') : null, $id]);
        recomputeMemberLedger($db, (int)$row['member_id']);
        $db->commit();
        $entry = loadLedgerRow($db, $id);
        audit_log(
            $db, 'Share Capital', 'UPDATED', 'Share capital ledger', (string)$id,
            $entry['reference'] ?: ('Share capital #' . $id),
            'Share capital entry was ' . ($voided ? 'voided' : 'restored') . ' for ' . $entry['member_name'] . '.',
            $entry, (int)$user['id'], $user['name'], 'HIGH'
        );
        json_ok($entry);
    } catch (Exception $e) {
        $db->rollBack();
        json_err('Could not update share capital entry: ' . $e->getMessage(), 500);
    }
}

json_err('Method not allowed', 405);
