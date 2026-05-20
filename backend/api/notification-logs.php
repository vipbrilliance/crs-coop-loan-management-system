<?php
// backend/api/notification-logs.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db = getDB();
$user = require_auth($db);
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';

function notificationTableExists(PDO $db): bool {
    return (bool)$db->query("SHOW TABLES LIKE 'notification_logs'")->fetchColumn();
}

function decodeNotificationPayload(?string $json): mixed {
    if (!$json) return null;
    $decoded = json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $json;
}

function normalizeNotificationStatus(?string $status): string {
    $value = strtoupper(trim((string)$status));
    return in_array($value, ['QUEUED','SENT','FAILED','DISABLED'], true) ? $value : 'QUEUED';
}

function normalizeNotificationChannel(?string $channel): string {
    $value = strtoupper(trim((string)$channel));
    return in_array($value, ['SMS','EMAIL','SYSTEM','DISABLED'], true) ? $value : 'SYSTEM';
}

function loadNotification(PDO $db, int $id): array {
    if (!notificationTableExists($db)) json_err('Notification log table is not installed', 404);
    $stmt = $db->prepare("\n        SELECT nl.*, u.name AS created_by_name\n        FROM notification_logs nl\n        LEFT JOIN users u ON u.id = nl.created_by\n        WHERE nl.id = ?\n    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) json_err('Notification not found', 404);
    $row['payload'] = decodeNotificationPayload($row['payload_json'] ?? null);
    return $row;
}

function saveNotification(PDO $db, array $d): array {
    if (!notificationTableExists($db)) json_err('Notification log table is not installed', 404);
    foreach (['event_key','channel','recipient_name','message'] as $field) {
        if (empty($d[$field])) json_err("Field '$field' is required");
    }
    $status = normalizeNotificationStatus($d['status'] ?? 'QUEUED');
    $channel = normalizeNotificationChannel($d['channel'] ?? 'SYSTEM');
    if ($channel === 'DISABLED') $status = 'DISABLED';
    $sentAt = $status === 'SENT' ? date('Y-m-d H:i:s') : ($d['sent_at'] ?? null);
    $failedAt = $status === 'FAILED' ? date('Y-m-d H:i:s') : ($d['failed_at'] ?? null);
    $payload = json_encode($d['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sourceKey = $d['source_key'] ?? null;

    if ($sourceKey) {
        $existing = $db->prepare('SELECT id FROM notification_logs WHERE source_key = ?');
        $existing->execute([$sourceKey]);
        $existingId = $existing->fetchColumn();
        if ($existingId) {
            $db->prepare("\n                UPDATE notification_logs\n                SET event_key=?, event_label=?, channel=?, recipient_name=?, destination=?, reference=?,\n                    message=?, status=?, payload_json=?, sent_at=?, failed_at=?, created_by=?\n                WHERE id=?\n            ")->execute([
                $d['event_key'], $d['event_label'] ?? null, $channel, $d['recipient_name'], $d['destination'] ?? null,
                $d['reference'] ?? null, $d['message'], $status, $payload, $sentAt, $failedAt,
                isset($d['created_by']) ? (int)$d['created_by'] : null, (int)$existingId,
            ]);
            return loadNotification($db, (int)$existingId);
        }
    }

    $db->prepare("\n        INSERT INTO notification_logs\n          (source_key, event_key, event_label, channel, recipient_name, destination, reference, message, status, payload_json, created_by, sent_at, failed_at)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ")->execute([
        $sourceKey, $d['event_key'], $d['event_label'] ?? null, $channel, $d['recipient_name'], $d['destination'] ?? null,
        $d['reference'] ?? null, $d['message'], $status, $payload, isset($d['created_by']) ? (int)$d['created_by'] : null,
        $sentAt, $failedAt,
    ]);
    return loadNotification($db, (int)$db->lastInsertId());
}

if ($method === 'GET') {
    if (!notificationTableExists($db)) json_ok([]);
    if ($id) json_ok(loadNotification($db, $id));

    $where = [];
    $params = [];
    if (!empty($_GET['channel'])) { $where[] = 'nl.channel = ?'; $params[] = normalizeNotificationChannel($_GET['channel']); }
    if (!empty($_GET['status'])) { $where[] = 'nl.status = ?'; $params[] = normalizeNotificationStatus($_GET['status']); }
    if (!empty($_GET['event_key'])) { $where[] = 'nl.event_key = ?'; $params[] = $_GET['event_key']; }
    if (!empty($_GET['date_from'])) { $where[] = 'DATE(nl.created_at) >= ?'; $params[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = 'DATE(nl.created_at) <= ?'; $params[] = $_GET['date_to']; }
    if (!empty($_GET['search'])) {
        $where[] = '(nl.event_label LIKE ? OR nl.recipient_name LIKE ? OR nl.destination LIKE ? OR nl.reference LIKE ? OR nl.message LIKE ?)';
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit = min(1000, max(1, (int)($_GET['limit'] ?? 500)));
    $stmt = $db->prepare("\n        SELECT nl.*, u.name AS created_by_name\n        FROM notification_logs nl\n        LEFT JOIN users u ON u.id = nl.created_by\n        $whereSql\n        ORDER BY nl.created_at DESC, nl.id DESC\n        LIMIT $limit\n    ");
    $stmt->execute($params);
    $rows = array_map(function ($row) {
        $row['payload'] = decodeNotificationPayload($row['payload_json'] ?? null);
        return $row;
    }, $stmt->fetchAll());
    json_ok($rows);
}

if ($method === 'POST') {
    $notice = saveNotification($db, body());
    audit_log(
        $db, 'Notifications', 'SYSTEM', 'Notification', (string)$notice['id'],
        $notice['reference'] ?: ($notice['event_label'] ?: $notice['event_key']),
        'Notification ' . strtolower($notice['status']) . ' for ' . $notice['recipient_name'] . '.',
        $notice, isset($notice['created_by']) ? (int)$notice['created_by'] : null, $notice['created_by_name'] ?? null, 'LOW'
    );
    json_ok($notice, 201);
}

if ($method === 'PUT' && $id) {
    $d = body();
    $current = loadNotification($db, $id);
    $status = normalizeNotificationStatus($d['status'] ?? $current['status']);
    $sentAt = $status === 'SENT' ? date('Y-m-d H:i:s') : ($status === 'QUEUED' ? null : $current['sent_at']);
    $failedAt = $status === 'FAILED' ? date('Y-m-d H:i:s') : ($status === 'QUEUED' ? null : $current['failed_at']);
    $db->prepare('UPDATE notification_logs SET status = ?, sent_at = ?, failed_at = ? WHERE id = ?')
       ->execute([$status, $sentAt, $failedAt, $id]);
    $notice = loadNotification($db, $id);
    audit_log(
        $db, 'Notifications', 'UPDATED', 'Notification', (string)$notice['id'],
        $notice['reference'] ?: ($notice['event_label'] ?: $notice['event_key']),
        'Notification status changed from ' . $current['status'] . ' to ' . $notice['status'] . '.',
        ['before' => $current, 'after' => $notice], isset($notice['created_by']) ? (int)$notice['created_by'] : null, $notice['created_by_name'] ?? null, 'LOW'
    );
    json_ok($notice);
}

json_err('Method not allowed', 405);
