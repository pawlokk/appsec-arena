CREATE DATABASE IF NOT EXISTS arena;
USE arena;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(512) NOT NULL UNIQUE,
    password VARCHAR(256) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'user'
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(512) NOT NULL,
    item VARCHAR(256) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'pending'
);

INSERT IGNORE INTO users (username, password, role) VALUES ('admin', @ADMIN_PASS, 'admin');
INSERT IGNORE INTO orders (username, item, status) VALUES ('admin', 'Widget A', 'shipped');
INSERT IGNORE INTO orders (username, item, status) VALUES ('admin', 'Gadget B', 'pending');
