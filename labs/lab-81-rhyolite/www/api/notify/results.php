<?php
require_once __DIR__ . '/../../config.php';
$db = db();
$rows = $db->query('SELECT data, received_at FROM event_log ORDER BY id DESC LIMIT 100')->fetch_all(MYSQLI_ASSOC);
header('Content-Type: application/json');
echo json_encode($rows);
