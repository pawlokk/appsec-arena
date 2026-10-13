<?php
session_start();

if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== 'user' || $_SERVER['PHP_AUTH_PW'] !== getenv('APP_PASSWORD')) {
    header('WWW-Authenticate: Basic realm="Document Management System"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
}

$uploadDir = '/var/www/html/uploads/';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $originalName = basename($file['name']);
        $tmpPath = $file['tmp_name'];

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'txt'];

        if (!in_array($ext, $allowedExtensions)) {
            $message = 'Error: File extension not allowed. Allowed: ' . implode(', ', $allowedExtensions);
            $messageType = 'error';
        } else {
            $mime = mime_content_type($tmpPath);
            $allowedMimes = [
                'image/jpeg', 'image/png', 'image/gif',
                'application/pdf', 'application/msword',
                'text/plain'
            ];

            if (!in_array($mime, $allowedMimes)) {
                $message = 'Error: MIME type not allowed.';
                $messageType = 'error';
            } else {
                $destPath = $uploadDir . $originalName;
                if (move_uploaded_file($tmpPath, $destPath)) {
                    $message = 'File uploaded successfully: ' . htmlspecialchars($originalName);
                    $messageType = 'success';
                } else {
                    $message = 'Error: Failed to move uploaded file.';
                    $messageType = 'error';
                }
            }
        }
    } elseif (isset($_FILES['zipfile'])) {
        $zipFile = $_FILES['zipfile'];
        $tmpPath = $zipFile['tmp_name'];

        $zip = new ZipArchive();
        if ($zip->open($tmpPath) === true) {
            $zip->extractTo($uploadDir);
            $zip->close();
            $message = 'Archive extracted successfully.';
            $messageType = 'success';
        } else {
            $message = 'Error: Failed to open zip archive.';
            $messageType = 'error';
        }
    }
}

$uploadedFiles = [];
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..') {
            $uploadedFiles[] = $f;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocVault — Document Management System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #1a1a2e; color: #e0e0e0; min-height: 100vh; }
        .header { background: #16213e; border-bottom: 2px solid #0f3460; padding: 16px 32px; display: flex; align-items: center; gap: 16px; }
        .header h1 { color: #e94560; font-size: 1.6rem; letter-spacing: 1px; }
        .header span { color: #a0a0b0; font-size: 0.9rem; }
        .container { max-width: 960px; margin: 40px auto; padding: 0 24px; }
        .card { background: #16213e; border: 1px solid #0f3460; border-radius: 8px; padding: 28px; margin-bottom: 28px; }
        .card h2 { color: #e94560; margin-bottom: 18px; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; color: #a0a0b0; margin-bottom: 6px; font-size: 0.9rem; }
        input[type="file"] { background: #0f3460; border: 1px solid #2a4a7f; border-radius: 4px; padding: 8px; color: #e0e0e0; width: 100%; cursor: pointer; }
        button { background: #e94560; color: white; border: none; padding: 10px 24px; border-radius: 4px; cursor: pointer; font-size: 0.95rem; transition: background 0.2s; }
        button:hover { background: #c73652; }
        .message { padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; }
        .message.success { background: #1a4731; border: 1px solid #2d6a4f; color: #74c69d; }
        .message.error { background: #4a1a1a; border: 1px solid #6a2d2d; color: #e07070; }
        .file-list { list-style: none; }
        .file-list li { padding: 8px 12px; border-bottom: 1px solid #0f3460; display: flex; justify-content: space-between; align-items: center; }
        .file-list li:last-child { border-bottom: none; }
        .file-list a { color: #64b5f6; text-decoration: none; }
        .file-list a:hover { text-decoration: underline; }
        .badge { background: #0f3460; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; color: #a0a0b0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>DocVault</h1>
        <span>Document Management System v2.3.1</span>
    </div>
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <h2>Upload Document</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="file">Select File</label>
                        <input type="file" name="file" id="file" required>
                        <p style="color:#6a6a8a;font-size:0.82rem;margin-top:6px;">Allowed types: JPG, JPEG, PNG, GIF, PDF, DOC, TXT</p>
                    </div>
                    <button type="submit">Upload File</button>
                </form>
            </div>

            <div class="card">
                <h2>Extract Archive</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="zipfile">Select ZIP Archive</label>
                        <input type="file" name="zipfile" id="zipfile" accept=".zip" required>
                        <p style="color:#6a6a8a;font-size:0.82rem;margin-top:6px;">Upload a ZIP archive to extract documents in bulk.</p>
                    </div>
                    <button type="submit">Extract Archive</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2>Uploaded Documents</h2>
            <?php if (empty($uploadedFiles)): ?>
                <p style="color:#6a6a8a;">No documents uploaded yet.</p>
            <?php else: ?>
                <ul class="file-list">
                    <?php foreach ($uploadedFiles as $f): ?>
                        <li>
                            <a href="/uploads/<?= urlencode($f) ?>" target="_blank"><?= htmlspecialchars($f) ?></a>
                            <span class="badge"><?= htmlspecialchars(strtoupper(pathinfo($f, PATHINFO_EXTENSION) ?: 'file')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
