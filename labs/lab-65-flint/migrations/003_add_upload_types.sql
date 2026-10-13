CREATE TABLE IF NOT EXISTS import_queue (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    xml_data   MEDIUMTEXT NOT NULL,
    filename   VARCHAR(255) DEFAULT NULL,
    status     ENUM('pending','processing','done','error') NOT NULL DEFAULT 'pending',
    result     TEXT DEFAULT NULL,
    status_detail TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS attachments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    contact_id    INT REFERENCES contacts(id),
    original_name VARCHAR(255) NOT NULL,
    stored_name   VARCHAR(255) NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);
