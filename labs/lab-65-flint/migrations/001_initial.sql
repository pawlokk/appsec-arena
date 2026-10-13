CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(64) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    reset_token VARCHAR(64) DEFAULT NULL,
    role        ENUM('user','admin') NOT NULL DEFAULT 'user',
    tenant_id   INT DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contacts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL DEFAULT '',
    email      VARCHAR(255) DEFAULT NULL,
    phone      VARCHAR(64) DEFAULT NULL,
    company    VARCHAR(255) DEFAULT NULL,
    tenant_id  INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
