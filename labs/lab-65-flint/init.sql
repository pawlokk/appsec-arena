CREATE DATABASE IF NOT EXISTS crmdb;
USE crmdb;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    role VARCHAR(50) DEFAULT 'user',
    reset_token VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    company VARCHAR(255),
    notes TEXT,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (id, username, password, email, role) VALUES
(1, 'admin', 'ADMIN_PASS_PLACEHOLDER', 'admin@flint.crm', 'admin');

INSERT INTO users (id, username, password, email, role) VALUES
(2, 'jsmith', '$2y$10$abcdefghijklmnopqrstuuVwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ12', 'jsmith@example.com', 'user');

INSERT INTO customers (name, email, phone, company, notes, owner_id) VALUES
('Acme Corp', 'contact@acme.com', '555-0100', 'Acme Corp', 'Key enterprise account', 1),
('Globex', 'info@globex.com', '555-0200', 'Globex Inc', 'Prospect Q3', 1),
('Initech', 'hello@initech.com', '555-0300', 'Initech LLC', 'Renewal due', 1);

CREATE TABLE IF NOT EXISTS import_queue (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    xml_data   MEDIUMTEXT NOT NULL,
    filename   VARCHAR(255) DEFAULT NULL,
    status     ENUM('pending','processing','done','error') NOT NULL DEFAULT 'pending',
    result     TEXT DEFAULT NULL,
    status_detail TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contacts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL DEFAULT '',
    email      VARCHAR(255) DEFAULT NULL,
    phone      VARCHAR(64) DEFAULT NULL,
    company    VARCHAR(255) DEFAULT NULL,
    tenant_id  INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO import_queue (xml_data, filename, status) VALUES
('<contacts><contact><name>Draft Contact A</name><email>a@example.com</email></contact></contacts>', 'batch_a.xml', 'pending'),
('<contacts><contact><name>Draft Contact B</name><email>b@example.com</email></contact></contacts>', 'batch_b.xml', 'pending'),
('<contacts><contact><name>Historical Import</name><email>hist@example.com</email></contact></contacts>', 'archive_2023.xml', 'done');

CREATE USER IF NOT EXISTS 'crmapp'@'localhost' IDENTIFIED BY 'crmpass123';
GRANT ALL PRIVILEGES ON crmdb.* TO 'crmapp'@'localhost';
GRANT FILE ON *.* TO 'crmapp'@'localhost';
FLUSH PRIVILEGES;
