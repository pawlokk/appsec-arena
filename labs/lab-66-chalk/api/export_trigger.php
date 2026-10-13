<?php
session_start();
require_once __DIR__ . '/../config.php';
require_admin();

$db = get_db();
$error = $msg = '';

$csrf = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF validation failed']);
    exit;
}

$schedule_id = (int)($_POST['schedule_id'] ?? 0);
if (!$schedule_id) {
    http_response_code(400);
    echo json_encode(['error' => 'schedule_id required']);
    exit;
}

$stmt = $db->prepare("SELECT * FROM export_schedules WHERE id = ? AND status = 'pending'");
$stmt->execute([$schedule_id]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    http_response_code(404);
    echo json_encode(['error' => 'Schedule not found or already processed']);
    exit;
}

define('EXPORT_WORKER_INTERNAL', true);
require_once __DIR__ . '/../workers/export_worker.php';

$result = run_export($schedule);

$db->prepare("UPDATE export_schedules SET status = 'done', result = ? WHERE id = ?")
   ->execute([json_encode($result), $schedule_id]);

header('Content-Type: application/json');
echo json_encode(['status' => 'done', 'result' => $result]);
