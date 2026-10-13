<?php
/**
 * CreditVault - Database initializer
 * Run once on startup to create schema and seed data.
 */

require_once 'db.php';

$db = get_db();

// Users table
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT    NOT NULL UNIQUE,
    password TEXT    NOT NULL,
    role     TEXT    NOT NULL DEFAULT 'employee',
    balance  INTEGER NOT NULL DEFAULT 0
)");

// Gift codes table
$db->exec("CREATE TABLE IF NOT EXISTS gift_codes (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    code     TEXT    NOT NULL UNIQUE,
    value    INTEGER NOT NULL DEFAULT 100,
    redeemed INTEGER NOT NULL DEFAULT 0
)");

// Purchases table — tracks store item redemptions
$db->exec("CREATE TABLE IF NOT EXISTS purchases (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    item       TEXT    NOT NULL,
    created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
)");

// Seed regular employee accounts
// Passwords are loaded from ENV so literals do not live in source.
// The Dockerfile sets intentional demo values (ALICE_PASS, BOB_PASS) that are
// shown in plain text on the login page as known starting-point credentials.
$alicePass = getenv('ALICE_PASS') ?: 'alicepass';
$bobPass   = getenv('BOB_PASS')   ?: 'bobpass';
$users = [
    ['alice', password_hash($alicePass, PASSWORD_BCRYPT), 'employee'],
    ['bob',   password_hash($bobPass,   PASSWORD_BCRYPT), 'employee'],
];

$stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password, role, balance) VALUES (?, ?, ?, 0)");
foreach ($users as $u) {
    $stmt->execute($u);
}

// Seed admin account with runtime-supplied password
$adminPass = getenv('ADMIN_PASS') ?: bin2hex(random_bytes(16));
$stmtAdmin = $db->prepare("INSERT OR IGNORE INTO users (username, password, role, balance) VALUES (?, ?, 'admin', 0)");
$stmtAdmin->execute(['admin', password_hash($adminPass, PASSWORD_BCRYPT)]);

// Seed a known demo gift code worth 100 credits each — ten codes total.
// The first is a fixed, well-known code students can use as the race target.
$knownCodes = ['DEMO-GIFT-2024'];
$stmtKnown  = $db->prepare("INSERT OR IGNORE INTO gift_codes (code, value, redeemed) VALUES (?, 100, 0)");
foreach ($knownCodes as $c) {
    $stmtKnown->execute([$c]);
}

// Additional random codes (not advertised)
$stmtRand = $db->prepare("INSERT OR IGNORE INTO gift_codes (code, value, redeemed) VALUES (?, 100, 0)");
for ($i = 0; $i < 9; $i++) {
    $code = strtoupper(bin2hex(random_bytes(8)));
    $stmtRand->execute([$code]);
}

echo "Database initialized.\n";
