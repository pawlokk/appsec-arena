<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'crmdb');
define('DB_USER', 'crmapp');
define('DB_PASS', 'crmpass123');

function get_db() {
    static $c = null;
    if ($c === null) {
        $d = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8';
        $c = new PDO($d, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
    return $c;
}
