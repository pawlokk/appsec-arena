<?php
session_start();
require_once __DIR__ . '/../config.php';
require_admin();

header('Content-Type: application/json');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $token = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token validation failed']);
        exit;
    }

    $new_pass = $data['new_password'] ?? '';
    $confirm  = $data['confirm_password'] ?? '';

    if (!$new_pass || !$confirm) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
        exit;
    }
    if ($new_pass !== $confirm) {
        http_response_code(400);
        echo json_encode(['error' => 'Passwords do not match']);
        exit;
    }
    if (strlen($new_pass) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters']);
        exit;
    }

    $hash = password_hash($new_pass, PASSWORD_BCRYPT);
    $db   = get_db();
    $stmt = $db->prepare("UPDATE users SET password=? WHERE username='admin'");
    $stmt->execute([$hash]);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    echo json_encode(['status' => 'ok', 'message' => 'Password updated successfully']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
