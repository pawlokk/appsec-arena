CREATE TABLE IF NOT EXISTS import_jobs (
    id SERIAL PRIMARY KEY,
    raw_xml TEXT NOT NULL,
    filename TEXT,
    submitted_by INTEGER,
    status TEXT NOT NULL DEFAULT 'pending',
    result TEXT,
    status_detail TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS report_queue (
    id SERIAL PRIMARY KEY,
    report_url TEXT NOT NULL,
    requested_by INTEGER,
    status TEXT NOT NULL DEFAULT 'pending',
    status_detail TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS report_results (
    id SERIAL PRIMARY KEY,
    queue_id INTEGER REFERENCES report_queue(id),
    status_code INTEGER,
    content_length INTEGER,
    content_snippet TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
