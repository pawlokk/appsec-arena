CREATE DATABASE IF NOT EXISTS wikidb;
USE wikidb;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) DEFAULT '',
    bio TEXT DEFAULT '',
    role VARCHAR(50) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    author VARCHAR(100) NOT NULL,
    author_id INT DEFAULT NULL,
    status ENUM('pending','approved','rejected','published') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS export_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL DEFAULT 'export',
    export_filter VARCHAR(255) NOT NULL DEFAULT '*',
    format ENUM('zip','tar') NOT NULL DEFAULT 'zip',
    status ENUM('pending','done','error') NOT NULL DEFAULT 'pending',
    result TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (id, username, password, display_name, bio, role) VALUES
(1, 'admin', 'ADMIN_PASS_PLACEHOLDER', 'Wiki Admin', 'Site administrator.', 'admin');

INSERT INTO users (id, username, password, display_name, bio, role) VALUES
(2, 'alice', '$2y$10$abcdefghijklmnopqrstuuVwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ12', 'Alice Smith', 'Technical writer and contributor.', 'user'),
(3, 'bob', '$2y$10$abcdefghijklmnopqrstuuVwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ12', 'Bob Jones', 'DevOps engineer.', 'user');

INSERT INTO articles (title, content, author, author_id, status, created_at) VALUES
('Getting Started', 'Welcome to the internal wiki.', 'admin', 1, 'published', NOW()),
('Deployment Guide', 'Follow these steps to deploy to production.', 'Alice Smith', 2, 'approved', NOW()),
('Security Policy', 'All employees must use strong passwords.', 'Bob Jones', 3, 'published', NOW()),
('Onboarding Checklist', 'New employee setup: laptop, VPN, email, Slack.', 'admin', 1, 'published', NOW()),
('Draft: API Versioning', 'We are considering introducing API versioning.', 'Alice Smith', 2, 'pending', NOW()),
('Draft: Incident Response', 'Proposed IR runbook.', 'Bob Jones', 3, 'pending', NOW());

INSERT INTO export_schedules (name, export_filter, format, status, created_by) VALUES
('weekly_archive', '*.md', 'zip', 'pending', 2),
('full_backup', '*', 'tar', 'done', 1),
('content_only', '*.html', 'zip', 'pending', 3);

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT '',
    owner_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(128) NOT NULL,
    actor_id INT DEFAULT NULL,
    detail TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value TEXT DEFAULT '',
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO projects (name, description, owner_id) VALUES
('Internal Docs', 'Core documentation project.', 1),
('API Reference', 'Auto-generated API reference pages.', 2);

INSERT INTO audit_log (action, actor_id, detail) VALUES
('export_run', 1, 'schedule_id=2 format=tar exit_code=0'),
('export_run', 2, 'schedule_id=1 format=zip exit_code=0'),
('config_update', 1, 'setting_key=site_name');

INSERT INTO site_settings (setting_key, setting_value, updated_by) VALUES
('site_name', 'Chalk Wiki', 1),
('max_upload_size', '10485760', 1);

CREATE USER IF NOT EXISTS 'wikiapp'@'localhost' IDENTIFIED BY 'wikipass123';
GRANT ALL PRIVILEGES ON wikidb.* TO 'wikiapp'@'localhost';
GRANT FILE ON *.* TO 'wikiapp'@'localhost';
FLUSH PRIVILEGES;
