<?php
// backend/api/loan-types.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/helpers.php';
cors();

$db = getDB();
$stmt = $db->query("SELECT * FROM loan_types WHERE is_active = 1 ORDER BY id");
json_ok($stmt->fetchAll());
