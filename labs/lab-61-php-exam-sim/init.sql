CREATE DATABASE IF NOT EXISTS solvent;
CREATE USER IF NOT EXISTS 'solvent'@'localhost' IDENTIFIED BY 'solventpass';
GRANT ALL PRIVILEGES ON solvent.* TO 'solvent'@'localhost';
FLUSH PRIVILEGES;

USE solvent;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(64)  NOT NULL,
    email         VARCHAR(128) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    bio           TEXT,
    avatar_url    VARCHAR(255) DEFAULT '/static/default_avatar.png',
    is_admin      TINYINT(1)   DEFAULT 0,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(128) NOT NULL,
    description TEXT,
    price       DECIMAL(10,2) NOT NULL,
    stock       INT DEFAULT 100,
    emoji       VARCHAR(8) DEFAULT '🧪'
);

CREATE TABLE IF NOT EXISTS orders (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    total      DECIMAL(10,2) NOT NULL,
    status     VARCHAR(32) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT IGNORE INTO products (name, description, price, emoji) VALUES
('Acetone (500ml)',     'High-purity acetone for laboratory use.',    12.99, '🧪'),
('Ethanol 95% (1L)',    'Pharmaceutical-grade ethanol, 95% purity.',  24.50, '🍶'),
('Methanol (1L)',       'ACS reagent grade methanol.',                18.75, '⚗️'),
('Isopropanol (1L)',    'Electronic-grade isopropanol, 99.9% pure.',  15.00, '🔬'),
('Diethyl Ether (1L)', 'Anhydrous diethyl ether, HPLC grade.',       34.99, '💧'),
('Chloroform (500ml)', 'Stabilised chloroform, reagent grade.',       29.99, '🧫');

-- Default admin account
INSERT IGNORE INTO users (username, email, password_hash, is_admin)
VALUES ('admin', 'admin@solvent.lab', '$2y$12$Xk3p9Qm7Lv2NwRtYjB8sDuF4oHcA1eZnW6gKs0iTqPxEyMlVrCbO', 1);

-- Demo customer
INSERT IGNORE INTO users (username, email, password_hash)
VALUES ('alice', 'alice@example.com', '$2y$12$Xk3p9Qm7Lv2NwRtYjB8sDuF4oHcA1eZnW6gKs0iTqPxEyMlVrCbO');

-- Sample orders
INSERT IGNORE INTO orders (user_id, total, status) VALUES
(2, 37.74, 'shipped'),
(2, 24.50, 'pending');
