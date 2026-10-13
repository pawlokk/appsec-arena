CREATE DATABASE IF NOT EXISTS meridian;
CREATE USER IF NOT EXISTS 'appuser'@'localhost' IDENTIFIED BY 'apppass';
GRANT ALL PRIVILEGES ON meridian.* TO 'appuser'@'localhost';
FLUSH PRIVILEGES;
USE meridian;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password VARCHAR(64) NOT NULL,
    role ENUM('user','admin') DEFAULT 'user'
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(64) NOT NULL,
    description TEXT
);

INSERT INTO products (name, price, category, description) VALUES
('Enterprise Firewall X1', 2499.00, 'Network', 'Next-gen stateful inspection firewall'),
('SecureVPN Gateway', 899.00, 'Network', 'Hardware VPN concentrator'),
('Log Aggregator Pro', 1299.00, 'Monitoring', 'Centralised log management platform'),
('Patch Manager Suite', 599.00, 'Compliance', 'Automated patch deployment system'),
('Identity Vault', 799.00, 'IAM', 'Privileged access management solution'),
('Endpoint Shield', 349.00, 'Endpoint', 'Next-gen endpoint detection and response'),
('Vulnerability Scanner', 1499.00, 'Assessment', 'Continuous vulnerability assessment tool'),
('SIEM Console', 3999.00, 'Monitoring', 'Security information and event management'),
('WAF Appliance', 1999.00, 'Network', 'Web application firewall hardware appliance'),
('Secrets Manager', 499.00, 'IAM', 'Automated secrets rotation and storage');
