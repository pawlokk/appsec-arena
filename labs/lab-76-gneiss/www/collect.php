<?php
require_once __DIR__ . '/lib/store.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
$data = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $data = $body['data'] ?? ($_POST['data'] ?? '');
} else {
    $data = $_GET['data'] ?? '';
}
appendCollected($data);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
