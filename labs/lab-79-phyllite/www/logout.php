<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/include/session.php');
session_destroy();
header('Location: /login.php');
exit;
