<?php
$db_path = '/var/www/html/prism.db';
if (file_exists($db_path)) {
    exit(0);
}

$pdo = new PDO('sqlite:' . $db_path);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user'
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    owner TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$admin_pass = password_hash(getenv('ADMIN_PASSWORD') ?: 'admin', PASSWORD_DEFAULT);
$pdo->exec("INSERT OR IGNORE INTO users (username, password, role) VALUES ('admin', '$admin_pass', 'admin')");
$alice_pass = password_hash(getenv('ALICE_PASSWORD') ?: 'alice123', PASSWORD_DEFAULT);
$pdo->exec("INSERT OR IGNORE INTO users (username, password, role) VALUES ('alice', '$alice_pass', 'admin')");

$pdo->exec("INSERT INTO documents (title, content, owner) VALUES ('Project Prism Overview', 'This document outlines the Prism document management system.', 'admin')");
$pdo->exec("INSERT INTO documents (title, content, owner) VALUES ('Onboarding Guide', 'Welcome to Prism. Please read all documentation carefully.', 'alice')");
$pdo->exec("INSERT INTO documents (title, content, owner) VALUES ('Security Policy', 'All data must be handled according to company security policy.', 'admin')");
$pdo->exec("INSERT INTO documents (title, content, owner) VALUES ('API Reference', 'The Prism API supports document CRUD and search operations.', 'alice')");

chmod($db_path, 0666);
echo "Database initialized.\n";
