CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    api_key TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS report_templates (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    template_body TEXT NOT NULL DEFAULT '',
    owner_id INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS reports (
    id SERIAL PRIMARY KEY,
    template_id INTEGER REFERENCES report_templates(id),
    run_by INTEGER REFERENCES users(id),
    result TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS scheduled_reports (
    id SERIAL PRIMARY KEY,
    template_ref INTEGER REFERENCES report_templates(id),
    owner_id INTEGER REFERENCES users(id),
    report_data JSONB NOT NULL DEFAULT '{}',
    status TEXT NOT NULL DEFAULT 'pending',
    result TEXT,
    status_detail TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS notification_preferences (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    notification_type TEXT NOT NULL,
    message_template TEXT NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, notification_type)
);

CREATE TABLE IF NOT EXISTS invite_tokens (
    id SERIAL PRIMARY KEY,
    token TEXT UNIQUE NOT NULL,
    email TEXT NOT NULL,
    invited_by INTEGER REFERENCES users(id),
    used BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS audit_log (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    action TEXT NOT NULL,
    target TEXT,
    ip TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

INSERT INTO users (username, email, password_hash, role, api_key)
VALUES ('admin', 'admin@analytics.internal', 'PLACEHOLDER_HASH', 'admin', 'stratum-api-k3y-8f2a9c1d')
ON CONFLICT DO NOTHING;

INSERT INTO users (username, email, password_hash, role, api_key)
VALUES ('analyst', 'analyst@analytics.internal', 'PLACEHOLDER_HASH', 'user', 'stratum-api-user-7e4b1f')
ON CONFLICT DO NOTHING;

INSERT INTO users (username, email, password_hash, role, api_key)
VALUES ('alice', 'alice@analytics.internal', 'PLACEHOLDER_HASH', 'user', NULL)
ON CONFLICT DO NOTHING;

INSERT INTO report_templates (name, description, template_body, owner_id)
VALUES (
    'Monthly KPI Summary',
    'Standard monthly KPI report showing key performance indicators.',
    '<h2>{report_title}</h2><p>Period: {date_range}</p><ul><li>Revenue: {revenue}</li><li>Users: {user_count}</li></ul>',
    1
) ON CONFLICT DO NOTHING;

INSERT INTO report_templates (name, description, template_body, owner_id)
VALUES (
    'Funnel Analysis',
    'Conversion funnel breakdown by stage.',
    '<h2>{report_title}</h2><p>Funnel stages: {stages}</p>',
    1
) ON CONFLICT DO NOTHING;

INSERT INTO report_templates (name, description, template_body, owner_id)
VALUES (
    'Q3 Cohort Retention',
    'Cohort retention analysis for Q3.',
    '<h2>{report_title}</h2><p>Cohort: {cohort}</p><p>Retention: {retention_rate}%</p>',
    2
) ON CONFLICT DO NOTHING;

INSERT INTO report_templates (name, description, template_body, owner_id)
VALUES (
    'Executive Summary',
    'High-level executive summary for board reporting.',
    '<h2>{report_title}</h2><p>Prepared for: {audience}</p><p>Period: {period}</p>',
    1
) ON CONFLICT DO NOTHING;

INSERT INTO report_templates (name, description, template_body, owner_id)
VALUES (
    'Team Performance',
    'Per-team performance metrics and KPIs.',
    '<h2>{report_title}</h2><p>Team: {team_name}</p><p>Score: {performance_score}</p>',
    3
) ON CONFLICT DO NOTHING;

INSERT INTO notification_preferences (user_id, notification_type, message_template)
VALUES (1, 'report_ready', 'Your report "{{ report_name }}" is ready. Generated at {{ generated_at }}.')
ON CONFLICT DO NOTHING;

INSERT INTO notification_preferences (user_id, notification_type, message_template)
VALUES (2, 'report_ready', 'Your report "{{ report_name }}" is ready. Generated at {{ generated_at }}.')
ON CONFLICT DO NOTHING;

INSERT INTO notification_preferences (user_id, notification_type, message_template)
VALUES (2, 'invite', 'You have been invited to workspace {{ workspace_name }} by {{ invited_by }}.')
ON CONFLICT DO NOTHING;

INSERT INTO scheduled_reports (template_ref, owner_id, report_data, status)
VALUES (1, 2, '{"report_title": "April KPIs", "date_range": "Apr 2025", "revenue": "142k", "user_count": "3200"}', 'done')
ON CONFLICT DO NOTHING;

INSERT INTO scheduled_reports (template_ref, owner_id, report_data, status)
VALUES (2, 2, '{"report_title": "Q1 Funnel", "stages": "Awareness > Trial > Paid"}', 'done')
ON CONFLICT DO NOTHING;

INSERT INTO scheduled_reports (template_ref, owner_id, report_data, status)
VALUES (3, 3, '{"report_title": "Q3 Cohorts", "cohort": "Jul 2024", "retention_rate": "62"}', 'pending')
ON CONFLICT DO NOTHING;

INSERT INTO audit_log (user_id, action, target)
VALUES (1, 'login', NULL),
       (1, 'template_preview', 'template:1'),
       (2, 'login', NULL),
       (2, 'report_schedule', 'template:1')
ON CONFLICT DO NOTHING;

ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_key TEXT;

CREATE TABLE IF NOT EXISTS content_index (
    id SERIAL PRIMARY KEY,
    doctype TEXT NOT NULL,
    name TEXT NOT NULL,
    content TEXT NOT NULL DEFAULT '',
    title TEXT NOT NULL DEFAULT '',
    route TEXT NOT NULL DEFAULT '',
    published BOOLEAN NOT NULL DEFAULT TRUE
);

INSERT INTO content_index (doctype, name, content, title, route) VALUES
('Report', 'monthly-kpi', 'KPI summary for monthly reporting cycles and performance tracking', 'Monthly KPI Summary', 'reports/monthly-kpi'),
('Dashboard', 'exec-dash', 'Executive dashboard with revenue growth and user acquisition metrics', 'Executive Dashboard', 'dashboards/exec'),
('Template', 'funnel-tmpl', 'Conversion funnel analysis template for product and marketing teams', 'Funnel Analysis', 'templates/funnel'),
('Guide', 'api-guide', 'REST API integration guide for analytics data ingestion and export', 'API Integration Guide', 'docs/api-guide'),
('Guide', 'export-guide', 'Data export formats including CSV JSON and Parquet column reference', 'Export Guide', 'docs/export-guide')
ON CONFLICT DO NOTHING;

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO stratum;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO stratum;
