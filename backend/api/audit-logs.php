<?php
// backend/api/audit-logs.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

function auditTableExists(PDO $db): bool {
    return (bool)$db->query("SHOW TABLES LIKE 'audit_logs'")->fetchColumn();
}

function decodePayload(?string $json): mixed {
    if (!$json) return null;
    $decoded = json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $json;
}

function auditRow(PDO $db, int $id): array {
    if (!auditTableExists($db)) json_err('Audit log table is not installed', 404);
    $stmt = $db->prepare("\n        SELECT al.*, u.name AS actor_user_name, u.email AS actor_user_email\n        FROM audit_logs al\n        LEFT JOIN users u ON u.id = al.actor_user_id\n        WHERE al.id = ?\n    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_err('Audit event not found', 404);
    $row['payload'] = decodePayload($row['payload_json'] ?? null);
    return $row;
}

if ($method === 'GET') {
    if (!auditTableExists($db)) json_ok([]);
    if ($id) json_ok(auditRow($db, $id));

    $where = [];
    $params = [];
    if (!empty($_GET['module'])) { $where[] = 'al.module = ?'; $params[] = strtoupper($_GET['module']); }
    if (!empty($_GET['action'])) { $where[] = 'al.action = ?'; $params[] = strtoupper($_GET['action']); }
    if (!empty($_GET['risk'])) { $where[] = 'al.risk = ?'; $params[] = strtoupper($_GET['risk']); }
    if (!empty($_GET['date_from'])) { $where[] = 'DATE(al.created_at) >= ?'; $params[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = 'DATE(al.created_at) <= ?'; $params[] = $_GET['date_to']; }
    if (!empty($_GET['search'])) {
        $where[] = '(al.module LIKE ? OR al.action LIKE ? OR al.record_type LIKE ? OR al.record_label LIKE ? OR al.actor_name LIKE ? OR al.detail LIKE ?)';
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit = min(1000, max(1, (int)($_GET['limit'] ?? 500)));

    $stmt = $db->prepare("\n        SELECT al.*, u.name AS actor_user_name, u.email AS actor_user_email\n        FROM audit_logs al\n        LEFT JOIN users u ON u.id = al.actor_user_id\n        $whereSql\n        ORDER BY al.created_at DESC, al.id DESC\n        LIMIT $limit\n    ");
    $stmt->execute($params);
    $rows = array_map(function ($row) {
        $row['payload'] = decodePayload($row['payload_json'] ?? null);
        return $row;
    }, $stmt->fetchAll());
    json_ok($rows);
}

if ($method === 'POST') {
    $d = body();
    foreach (['module', 'action', 'record_type', 'record_id', 'record_label', 'detail'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }
    audit_log(
        $db,
        $d['module'],
        $d['action'],
        $d['record_type'],
        (string)$d['record_id'],
        $d['record_label'],
        $d['detail'],
        $d['payload'] ?? [],
        isset($d['actor_user_id']) ? (int)$d['actor_user_id'] : null,
        $d['actor_name'] ?? null,
        $d['risk'] ?? 'LOW'
    );
    json_ok(['created' => true], 201);
}

json_err('Method not allowed', 405);
