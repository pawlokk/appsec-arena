CREATE DATABASE IF NOT EXISTS arena;
CREATE USER IF NOT EXISTS 'appuser'@'localhost' IDENTIFIED BY 'apppass';
GRANT ALL PRIVILEGES ON arena.* TO 'appuser'@'localhost';
FLUSH PRIVILEGES;
USE arena;
CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50),
  password_hash VARCHAR(32),
  role VARCHAR(10)
);
INSERT INTO users VALUES (1,'admin',MD5('__ADMIN_PASS__'),'admin'),(2,'user',MD5('password'),'user');
