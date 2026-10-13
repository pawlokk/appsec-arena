<?php
require_once __DIR__ . '/../config.php';
session_start();

if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

define('PROCESS_IMPORT_INTERNAL', true);
require_once __DIR__ . '/../process_import.php';

$pdo = get_db();
$stmt = $pdo->query("SELECT id, xml_data FROM import_queue WHERE status = 'pending' LIMIT 10");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];
foreach ($jobs as $job) {
    $results[] = process_import_job((int)$job['id'], $job['xml_data']);
}

header('Content-Type: application/json');
echo json_encode(['processed' => count($results), 'results' => $results]);
