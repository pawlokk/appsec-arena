<?php
require_once __DIR__ . '/../config.php';
$admin = currentAdmin();
if (!$admin) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

$fields = $_POST['fields'] ?? [];
if (!is_array($fields) || empty($fields)) {
    http_response_code(400); echo json_encode(['error' => 'No fields']); exit;
}

$db = db();
$section = 'global';
$get = $db->prepare('SELECT key_name FROM config WHERE section=? AND key_name=?');
$upd = $db->prepare('UPDATE config SET key_value=? WHERE section=? AND key_name=?');
$ins = $db->prepare('INSERT INTO config (section, key_name, key_value) VALUES (?,?,?)');

foreach ($fields as $key => $value) {
    $get->bind_param('ss', $section, $key);
    $get->execute();
    if ($get->get_result()->num_rows > 0) {
        $upd->bind_param('sss', $value, $section, $key);
        $upd->execute();
    } else {
        $ins->bind_param('sss', $section, $key, $value);
        $ins->execute();
    }
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    echo json_encode(['ok' => true]);
} else {
    header('Location: /admin/');
}
