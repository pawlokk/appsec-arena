CREATE DATABASE IF NOT EXISTS maildb;
USE maildb;

CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  email VARCHAR(128) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS sessions (
  token VARCHAR(64) NOT NULL PRIMARY KEY,
  account_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE IF NOT EXISTS admin_sessions (
  token VARCHAR(64) NOT NULL PRIMARY KEY,
  account_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES accounts(id)
);

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipient_email VARCHAR(128) NOT NULL,
  sender_email VARCHAR(128) NOT NULL,
  subject VARCHAR(255) NOT NULL DEFAULT '',
  date_header TEXT NOT NULL DEFAULT '',
  body LONGTEXT NOT NULL DEFAULT '',
  received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS config (
  section VARCHAR(64) NOT NULL,
  key_name VARCHAR(64) NOT NULL,
  key_value TEXT NOT NULL DEFAULT '',
  PRIMARY KEY (section, key_name)
);

CREATE TABLE IF NOT EXISTS event_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  data TEXT,
  received_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO config (section, key_name, key_value) VALUES
  ('global', 'tmpFolderBaseName', 'tmp/'),
  ('global', 'admin_email', 'postmaster@hexmail.local'),
  ('global', 'session_timeout', '120');

INSERT IGNORE INTO accounts (username, email, password, is_admin) VALUES
  ('admin', 'admin@hexmail.local', SHA1(CONCAT('Admin', RAND())), 1),
  ('alice', 'alice@hexmail.local', SHA1('alice123'), 0);
