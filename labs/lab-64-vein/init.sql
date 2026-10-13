CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(64) UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'user',
    created_at    TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS documents (
    id         SERIAL PRIMARY KEY,
    title      TEXT NOT NULL DEFAULT 'Untitled',
    content    TEXT,
    raw_xml    TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS import_jobs (
    id           SERIAL PRIMARY KEY,
    raw_xml      TEXT NOT NULL,
    filename     TEXT,
    submitted_by INTEGER,
    status       TEXT NOT NULL DEFAULT 'pending',
    retry_count  INTEGER NOT NULL DEFAULT 0,
    result       TEXT,
    status_detail TEXT,
    created_at   TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS report_queue (
    id           SERIAL PRIMARY KEY,
    report_url   TEXT NOT NULL,
    requested_by INTEGER,
    status       TEXT NOT NULL DEFAULT 'pending',
    status_detail TEXT,
    created_at   TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS report_results (
    id              SERIAL PRIMARY KEY,
    queue_id        INTEGER REFERENCES report_queue(id),
    status_code     INTEGER,
    content_length  INTEGER,
    content_snippet TEXT,
    created_at      TIMESTAMP DEFAULT NOW()
);

INSERT INTO users(username, password_hash, role)
VALUES('analyst', 'PLACEHOLDER_HASH', 'user')
ON CONFLICT DO NOTHING;

INSERT INTO users(username, password_hash, role)
VALUES('compliance', 'PLACEHOLDER_HASH', 'user')
ON CONFLICT DO NOTHING;

INSERT INTO documents(title, content, raw_xml)
VALUES('Q1 Balance Sheet', 'Assets: 1,240,000', '<document><title>Q1 Balance Sheet</title><content>Assets: 1,240,000</content></document>'),
      ('Audit Trail 2024', 'Reference audit log entries for 2024 fiscal year.', '<document><title>Audit Trail 2024</title><content>Reference audit log.</content></document>'),
      ('Compliance Report Mar', 'Compliance status: PASS', '<document><title>Compliance Report Mar</title><content>Compliance status: PASS</content></document>')
ON CONFLICT DO NOTHING;

INSERT INTO import_jobs(raw_xml, filename, status)
VALUES('<document><title>Draft Budget</title><content>TBD</content></document>', 'draft_budget.xml', 'done'),
      ('<document><title>Risk Register</title><content>Low</content></document>', 'risk_register.xml', 'done')
ON CONFLICT DO NOTHING;

INSERT INTO report_queue(report_url, status)
VALUES('http://127.0.0.1:5000/admin', 'done'),
      ('http://127.0.0.1:5000/', 'done')
ON CONFLICT DO NOTHING;
