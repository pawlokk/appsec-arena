CREATE TABLE IF NOT EXISTS document_objects (
    id          SERIAL PRIMARY KEY,
    report_id   INTEGER REFERENCES reports(id) ON DELETE CASCADE,
    object_oid  OID,
    filename    VARCHAR(255),
    created_at  TIMESTAMP DEFAULT NOW()
);
