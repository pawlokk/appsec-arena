<?php
require_once __DIR__ . '/db.php';

function handleResetRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        echo json_encode(['error' => 'Email required']);
        exit;
    }
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $token = substr(md5(uniqid()), 0, 8);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);
        $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')->execute([$user['id'], $token, $expires]);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'If that email exists, a reset token has been issued.', 'debug_token' => $token]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['message' => 'If that email exists, a reset token has been issued.']);
    }
    exit;
}

function handleResetVerify() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }
    $email    = trim($_POST['email'] ?? '');
    $token    = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$email || $token === '' || !$password) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'email, token and password required']);
        exit;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT u.id, pr.token FROM users u JOIN password_resets pr ON pr.user_id = u.id WHERE u.email = ? AND pr.expires_at > NOW()');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['token'] == $token) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $row['id']]);
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$row['id']]);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Password updated successfully.']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or expired token.']);
    }
    exit;
}
