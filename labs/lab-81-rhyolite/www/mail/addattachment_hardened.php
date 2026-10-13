<?php
require_once __DIR__ . '/../config.php';
$user = currentUser();
if (!$user) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$composeID = $_POST['composeID'] ?? ($_GET['composeID'] ?? '');
$composeID = preg_replace('/[^a-zA-Z0-9]/', '', $composeID);

if (!isset($_FILES['newAttachment']) || $_FILES['newAttachment']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$filenameOriginal = urldecode($_FILES['newAttachment']['name']);
$filenameOriginal = preg_replace('/^[\/\.]+/', '', $filenameOriginal);
$filenameOriginal = str_replace('../', '', $filenameOriginal);

$blocked = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar'];
$ext = strtolower(pathinfo($filenameOriginal, PATHINFO_EXTENSION));
if (in_array($ext, $blocked)) {
    http_response_code(400);
    echo json_encode(['error' => 'File type not allowed']);
    exit;
}

$type = str_replace('/', '_', $_FILES['newAttachment']['type']);
$filenameFS = $type . '-' . $composeID . '-' . $filenameOriginal;
$uploadDir = getTmpPath($user['email']);
$filenameFSABS = $uploadDir . $filenameFS;

if (!@move_uploaded_file($_FILES['newAttachment']['tmp_name'], $filenameFSABS)) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed']);
    exit;
}

$webPath = str_replace(APP_ROOT, '/', $filenameFSABS);
echo json_encode(['ok' => true, 'path' => $webPath, 'filename' => $filenameFS]);
