<?php
require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

$c = get_db();

$s = $c->prepare("SELECT id, name, email, phone, company FROM customers WHERE name LIKE ? AND owner_id = ?");
$s->execute(["%$q%", $_SESSION['user_id']]);
$r = $s->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'query'   => $q,
    'count'   => count($r),
    'results' => $r,
]);
