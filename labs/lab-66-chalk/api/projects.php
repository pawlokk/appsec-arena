<?php
session_start();
require_once __DIR__ . '/../config.php';
require_login();

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'name required']);
        exit;
    }
    $uid = (int)$_SESSION['user_id'];
    $stmt = $db->prepare(
        "INSERT INTO projects (name, description, owner_id) VALUES (?, ?, ?)"
    );
    $stmt->execute([$name, $desc, $uid]);
    echo json_encode(['id' => $db->lastInsertId(), 'name' => $name]);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$stmt = $db->prepare("SELECT id, name, description, created_at FROM projects WHERE owner_id = ? ORDER BY created_at DESC");
$stmt->execute([$uid]);
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
