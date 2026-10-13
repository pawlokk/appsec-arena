<?php
require_once __DIR__ . '/../../config.php';
$data = $_GET['data'] ?? ($_POST['data'] ?? '');
$db = db();
$s = $db->prepare('INSERT INTO event_log (data) VALUES (?)');
$s->bind_param('s', $data); $s->execute();
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
