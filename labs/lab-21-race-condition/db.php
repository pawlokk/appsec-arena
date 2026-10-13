<?php
/**
 * CreditVault - Database helper
 * Provides a shared PDO instance for SQLite operations.
 */

define('DB_PATH', '/tmp/creditvault.db');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 2000');
    }
    return $pdo;
}
