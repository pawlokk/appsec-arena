CREATE TABLE IF NOT EXISTS analytics (
    id SERIAL PRIMARY KEY,
    category TEXT NOT NULL,
    event TEXT NOT NULL,
    value INTEGER,
    date TIMESTAMP DEFAULT NOW()
);

INSERT INTO analytics (category, event, value, date) VALUES
('web', 'page_view', 1024, NOW() - INTERVAL '1 day'),
('web', 'click', 512, NOW() - INTERVAL '2 days'),
('api', 'request', 8000, NOW() - INTERVAL '1 day'),
('api', 'error', 32, NOW() - INTERVAL '3 days'),
('mobile', 'session_start', 750, NOW() - INTERVAL '1 day'),
('mobile', 'crash', 5, NOW() - INTERVAL '4 days'),
('web', 'form_submit', 200, NOW() - INTERVAL '5 days'),
('api', 'timeout', 18, NOW() - INTERVAL '2 days');
