CREATE DATABASE IF NOT EXISTS lmsdb;
CREATE USER IF NOT EXISTS 'appuser'@'127.0.0.1' IDENTIFIED BY 'apppass';
GRANT ALL PRIVILEGES ON lmsdb.* TO 'appuser'@'127.0.0.1';
FLUSH PRIVILEGES;
USE lmsdb;

CREATE TABLE IF NOT EXISTS AT_members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(32) NOT NULL,
    first_name VARCHAR(64) NOT NULL DEFAULT '',
    last_name VARCHAR(64) NOT NULL DEFAULT '',
    email VARCHAR(128) NOT NULL DEFAULT '',
    password VARCHAR(40) NOT NULL,
    creation_date DATETIME NOT NULL,
    last_login DATETIME DEFAULT NULL
);

INSERT INTO AT_members (login, first_name, last_name, email, password, creation_date)
VALUES ('teacher', 'Jane', 'Holloway', 'teacher@academy.local', SHA1('Teach3r!Passw0rd'), '2023-10-13 04:00:00');

CREATE TABLE IF NOT EXISTS AT_password_reset (
    member_id INT NOT NULL PRIMARY KEY,
    token VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL
);
