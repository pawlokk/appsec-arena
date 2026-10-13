CREATE DATABASE IF NOT EXISTS lmsdb;
CREATE USER IF NOT EXISTS 'appuser'@'127.0.0.1' IDENTIFIED BY 'apppass';
GRANT ALL PRIVILEGES ON lmsdb.* TO 'appuser'@'127.0.0.1';
FLUSH PRIVILEGES;
USE lmsdb;

CREATE TABLE IF NOT EXISTS AT_members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(32) NOT NULL,
    first_name VARCHAR(64) NOT NULL DEFAULT '',
    second_name VARCHAR(64) NOT NULL DEFAULT '',
    last_name VARCHAR(64) NOT NULL DEFAULT '',
    email VARCHAR(128) NOT NULL DEFAULT '',
    password VARCHAR(40) NOT NULL,
    status TINYINT NOT NULL DEFAULT 1,
    language VARCHAR(16) NOT NULL DEFAULT 'en',
    preferences TEXT,
    last_login VARCHAR(20) NOT NULL DEFAULT ''
);

INSERT INTO AT_members (login, first_name, last_name, email, password)
VALUES ('teacher', 'Jane', 'Holloway', 'teacher@academy.local', SHA1('Ac4d3my!R3s3arch'));

INSERT INTO AT_members (login, first_name, last_name, email, password)
VALUES ('admin', 'System', 'Administrator', 'admin@academy.local', SHA1('Adm1n!S3cr3t2024'));

CREATE TABLE IF NOT EXISTS AT_member_login_attempt (
    login VARCHAR(32) NOT NULL PRIMARY KEY,
    attempt INT NOT NULL DEFAULT 0,
    expiry INT NOT NULL DEFAULT 0
);
