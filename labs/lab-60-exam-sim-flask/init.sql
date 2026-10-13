-- Titan Commerce Platform - Database Initialization

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(128) UNIQUE NOT NULL,
    password VARCHAR(256) NOT NULL,
    email VARCHAR(256),
    role VARCHAR(32) DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(256) NOT NULL,
    price NUMERIC(10,2) NOT NULL,
    stock INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    product_id INTEGER REFERENCES products(id),
    qty INTEGER DEFAULT 1,
    status VARCHAR(64) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT NOW()
);

-- Seed product catalog
INSERT INTO products (name, price, stock) VALUES
    ('Titanium Widget Pro', 49.99, 120),
    ('Arena Access Pass', 199.00, 50),
    ('Security Toolkit Bundle', 89.95, 75),
    ('Enterprise License', 999.00, 10),
    ('Developer API Key', 29.99, 500)
ON CONFLICT DO NOTHING;

-- Admin account (password set at runtime via env)
INSERT INTO users (username, password, email, role)
VALUES ('admin', 'PLACEHOLDER_ADMIN_HASH', 'admin@titan.local', 'admin')
ON CONFLICT (username) DO NOTHING;
