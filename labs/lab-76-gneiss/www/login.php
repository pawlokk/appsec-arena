<?php
require_once __DIR__ . '/lib/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (verifyCredentials($u, $p)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $u;
        header('Location: /dashboard.php');
        exit;
    }
}
header('Location: /index.php?err=1');
exit;
