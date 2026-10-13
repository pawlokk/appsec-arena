<?php
require_once __DIR__ . '/config.php';
$token = $_COOKIE['hexmail6'] ?? '';
if ($token) {
    $s = db()->prepare('DELETE FROM sessions WHERE token=?');
    $s->bind_param('s', $token); $s->execute();
}
setcookie('hexmail6', '', time()-3600, '/');
setcookie('hexmail6_admin', '', time()-3600, '/');
header('Location: /login.php'); exit;
