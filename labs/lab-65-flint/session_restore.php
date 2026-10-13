<?php
require_once __DIR__ . '/config.php';

define('SESSION_RESTORE_KEY', getenv('SESSION_RESTORE_KEY') ?: 'sr-key-9mXq3pW7kR');

$b = $_COOKIE['session_restore'] ?? '';

if (!$b) {
    http_response_code(400);
    echo json_encode(['error' => 'No session_restore cookie']);
    exit;
}

$p = explode(':', $b, 2);
if (count($p) !== 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Malformed session blob']);
    exit;
}

[$sg, $db] = $p;

$sg = base64_decode($sg, true);
$db = base64_decode($db, true);

if ($sg === false || $db === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Base64 decode error']);
    exit;
}

$ex = hash_hmac('sha256', $db, SESSION_RESTORE_KEY, true);
if (!hash_equals($ex, $sg)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session signature']);
    exit;
}

$sd = unserialize($db);
if (!is_array($sd) || empty($sd['uid'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid session data']);
    exit;
}

session_start();
$_SESSION = $sd;

header('Content-Type: application/json');
echo json_encode(['status' => 'restored', 'user' => $_SESSION['username'] ?? '']);
