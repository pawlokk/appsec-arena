<?php
require_once __DIR__ . '/auth.php';

function handleAvatarUpload() {
    $user = requireLogin();

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo renderLayout('Upload Error', '<p class="error">No file uploaded or upload error.</p>', $user);
        exit;
    }

    $file    = $_FILES['avatar'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo renderLayout('Upload Error', '<p class="error">Only image files allowed.</p>', $user);
        exit;
    }

    $uploadDir = '/var/www/html/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $uploadDir . $filename);

    $meta = [
        'filename' => $filename,
        'original' => $file['name'],
        'size'     => $file['size'],
        'uploaded' => time(),
    ];

    $pdo = getDB();
    $avatarUrl = '/uploads/' . $filename;
    $pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$avatarUrl, $user['id']]);

    $metaCookie = base64_encode(serialize((object)$meta));
    setcookie('avatar_meta', $metaCookie, 0, '/');

    header('Location: /?page=profile');
    exit;
}
