CREATE TABLE IF NOT EXISTS tenants (
    id SERIAL PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user' NOT NULL,
    organization_role VARCHAR(64) DEFAULT 'member',
    display_name VARCHAR(255),
    bio TEXT,
    tenant_id INTEGER,
    feature_flags JSONB DEFAULT '{}'
);

CREATE TABLE IF NOT EXISTS reports (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    content TEXT,
    owner_id INTEGER REFERENCES users(id),
    tenant_id INTEGER,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    owner_id INTEGER REFERENCES users(id),
    tenant_id INTEGER,
    status VARCHAR(32) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS report_schedules (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER,
    project_id INTEGER REFERENCES projects(id) ON DELETE SET NULL,
    sort_preference VARCHAR(128) NOT NULL DEFAULT 'created_at',
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    result TEXT,
    status_detail TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS document_objects (
    id SERIAL PRIMARY KEY,
    report_id INTEGER REFERENCES reports(id) ON DELETE CASCADE,
    object_oid OID,
    filename VARCHAR(255),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS accounts (
    id SERIAL PRIMARY KEY,
    first_name TEXT,
    last_name TEXT,
    owner_id INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    token VARCHAR(64) NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

INSERT INTO users (username, email, password, role)
VALUES ('admin', 'admin@seam.internal', 'placeholder_replaced_at_startup', 'admin')
ON CONFLICT (username) DO NOTHING;

INSERT INTO users (username, email, password, role)
VALUES ('guest', 'guest@seam.internal', '$2a$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lAP6', 'user')
ON CONFLICT (username) DO NOTHING;

INSERT INTO reports (title, category, content, owner_id)
SELECT 'Q1 Revenue Report', 'finance', 'Revenue summary for Q1.', id FROM users WHERE username='admin'
ON CONFLICT DO NOTHING;

INSERT INTO reports (title, category, content, owner_id)
SELECT 'API Usage Stats', 'api', 'API calls for the quarter.', id FROM users WHERE username='admin'
ON CONFLICT DO NOTHING;

INSERT INTO reports (title, category, content, owner_id)
SELECT 'Web Traffic Analysis', 'web', 'Web traffic breakdown.', id FROM users WHERE username='admin'
ON CONFLICT DO NOTHING;

INSERT INTO tenants (name) VALUES ('AlphaCorp'), ('BetaOrg'), ('GammaTech') ON CONFLICT DO NOTHING;

INSERT INTO report_schedules (tenant_id, sort_preference, status, created_by)
SELECT 1, 'created_at', 'pending', id FROM users WHERE username='admin'
ON CONFLICT DO NOTHING;

INSERT INTO report_schedules (tenant_id, sort_preference, status, created_by)
SELECT 1, 'title', 'done', id FROM users WHERE username='admin'
ON CONFLICT DO NOTHING;

INSERT INTO accounts (first_name, last_name, owner_id)
SELECT 'Seam', 'Support Bot', id FROM users WHERE username='admin'
ON CONFLICT DO NOTHING;
