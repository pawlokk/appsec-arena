<?php
require_once __DIR__ . '/config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /admin.php');
    } else {
        header('Location: /search.php');
    }
    exit;
}
header('Location: /login.php');
exit;
