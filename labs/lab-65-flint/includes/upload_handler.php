<?php
const BLOCKED_EXTENSIONS = ['php', 'phtml', 'php3', 'php4', 'php5', 'pht', 'shtml', 'cgi', 'pl'];

function handle_upload(array $file, int $owner_id): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload error: ' . $file['error']];
    }

    $original_name = $file['name'];
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (in_array($ext, BLOCKED_EXTENSIONS, true)) {
        return ['ok' => false, 'error' => "File type .$ext is not allowed."];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $blocked_mimes = ['application/x-php', 'text/x-php', 'application/x-executable', 'application/x-elf'];
    if (in_array($mime, $blocked_mimes, true)) {
        return ['ok' => false, 'error' => 'File content type not allowed.'];
    }

    $stored_name = uniqid('att_', true) . '.' . $ext;
    $dest = __DIR__ . '/../uploads_queue/' . $stored_name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Failed to store uploaded file.'];
    }

    return ['ok' => true, 'filename' => $stored_name, 'original' => $original_name];
}
