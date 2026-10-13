<?php
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $host = '127.0.0.1';
        $db   = 'solvent';
        $user = 'solvent';
        $pass = 'solventpass';
        $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $pdo  = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    return $pdo;
}
