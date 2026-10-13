<?php

define('APP_USER', 'admin');
define('APP_PASS', 'studentlab');

function requireAuth()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['authenticated'])) {
        header('Location: /index.php');
        exit;
    }
}

function verifyCredentials($username, $password)
{
    return $username === APP_USER && $password === APP_PASS;
}
