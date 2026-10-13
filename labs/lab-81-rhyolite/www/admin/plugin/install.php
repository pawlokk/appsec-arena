<?php
require_once __DIR__ . '/../../config.php';
$admin = currentAdmin();
if (!$admin) { http_response_code(403); die('Forbidden'); }

if (!isset($_FILES['pluginZip']) || $_FILES['pluginZip']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); die('No plugin file uploaded');
}

$orig = basename($_FILES['pluginZip']['name']);
$pluginDir = APP_ROOT . 'plugins/';
$dest = $pluginDir . $orig;

if (!@move_uploaded_file($_FILES['pluginZip']['tmp_name'], $dest)) {
    http_response_code(500); die('Install failed');
}

$webPath = '/plugins/' . $orig;
echo json_encode(['ok' => true, 'installed' => $webPath]);
