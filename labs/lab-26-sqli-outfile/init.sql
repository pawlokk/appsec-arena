CREATE DATABASE IF NOT EXISTS shopdb;
USE shopdb;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL
);

INSERT INTO products (name, description, price) VALUES
('Widget Pro', 'High quality widget for professionals', 29.99),
('Gadget X', 'Compact and efficient gadget', 49.99),
('Doohickey Plus', 'Enhanced doohickey with extra features', 14.99),
('Thingamajig', 'The original thingamajig, tried and true', 9.99),
('Whatchamacallit', 'Universal connector for all your needs', 19.99);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user'
);

INSERT INTO users (username, password, role) VALUES
('admin', 'ADMIN_PASS_PLACEHOLDER', 'admin'),
('user1', 'password1', 'user');

-- Application database user
CREATE USER IF NOT EXISTS 'fileuser'@'localhost' IDENTIFIED BY 'filepass123';
GRANT ALL PRIVILEGES ON shopdb.* TO 'fileuser'@'localhost';
GRANT FILE ON *.* TO 'fileuser'@'localhost';
FLUSH PRIVILEGES;
