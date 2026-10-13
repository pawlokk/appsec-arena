CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS projects (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    report_template TEXT NOT NULL DEFAULT '',
    owner_id INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

INSERT INTO users (username, email, password_hash, role)
VALUES ('admin', 'admin@mantle.local', 'PLACEHOLDER_HASH', 'admin')
ON CONFLICT DO NOTHING;

INSERT INTO projects (name, description, report_template, owner_id)
VALUES (
    'Platform Migration Q3',
    'Migrate legacy monolith to microservices. Track progress, blockers, and deliverables.',
    '<h2>{{ project_name }}</h2><p>{{ description }}</p><p><em>Owner: {{ owner }}</em></p>',
    1
) ON CONFLICT DO NOTHING;

INSERT INTO projects (name, description, report_template, owner_id)
VALUES (
    'Security Audit 2024',
    'Annual penetration test scope and findings tracker.',
    '<h2>{{ project_name }}</h2><p>{{ description }}</p>',
    1
) ON CONFLICT DO NOTHING;
