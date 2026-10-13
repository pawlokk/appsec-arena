<?php
require_once __DIR__ . '/config.php';
$user = currentUser();
if ($user) { header('Location: /mail/'); exit; }
header('Location: /login.php'); exit;
