CREATE TABLE IF NOT EXISTS webhooks (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_id    INTEGER NOT NULL,
    tenant_id   TEXT NOT NULL DEFAULT 'default',
    url         TEXT NOT NULL,
    event_type  TEXT NOT NULL DEFAULT 'generic',
    payload_json TEXT NOT NULL DEFAULT '{}',
    active      INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT DEFAULT (datetime('now'))
);
