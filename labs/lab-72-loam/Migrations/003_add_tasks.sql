CREATE TABLE IF NOT EXISTS task_queue (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    webhook_id   INTEGER NOT NULL,
    payload_json TEXT NOT NULL,
    status       TEXT NOT NULL DEFAULT 'pending',
    result       TEXT,
    created_at   TEXT DEFAULT (datetime('now')),
    processed_at TEXT
);
