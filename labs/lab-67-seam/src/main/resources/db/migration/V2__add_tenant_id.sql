CREATE TABLE IF NOT EXISTS tenants (
    id SERIAL PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS tenant_id INTEGER REFERENCES tenants(id);
ALTER TABLE users ADD COLUMN IF NOT EXISTS organization_role VARCHAR(64) DEFAULT 'member';
ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT;

ALTER TABLE reports ADD COLUMN IF NOT EXISTS tenant_id INTEGER REFERENCES tenants(id);

CREATE TABLE IF NOT EXISTS projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    owner_id INTEGER REFERENCES users(id),
    tenant_id INTEGER REFERENCES tenants(id),
    status VARCHAR(32) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS report_schedules (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER REFERENCES tenants(id),
    sort_preference VARCHAR(128) NOT NULL DEFAULT 'created_at',
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    result TEXT,
    status_detail TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
