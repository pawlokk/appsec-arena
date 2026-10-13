CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS items (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    category TEXT
);

CREATE TABLE IF NOT EXISTS report_log (
    id SERIAL PRIMARY KEY,
    result TEXT
);

CREATE TABLE IF NOT EXISTS out (
    o TEXT
);

INSERT INTO items (name, description, category) VALUES
    ('Widget A', 'Standard widget', 'hardware'),
    ('Widget B', 'Premium widget', 'hardware'),
    ('Service Pack', 'Annual support contract', 'software'),
    ('License Pro', 'Professional license', 'software')
ON CONFLICT DO NOTHING;
