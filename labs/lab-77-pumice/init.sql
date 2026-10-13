CREATE TABLE IF NOT EXISTS wh_uploads (
    id SERIAL PRIMARY KEY,
    source_id VARCHAR(64) NOT NULL,
    destination_id VARCHAR(64) NOT NULL,
    start_staging_file_id INTEGER NOT NULL,
    end_staging_file_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

INSERT INTO wh_uploads (source_id, destination_id, start_staging_file_id, end_staging_file_id) VALUES
    ('src_01HXKJ2MNP', 'dst_01HXKJ2SNW', 1001, 1048),
    ('src_01HXKJ2MNP', 'dst_01HXKJ2SNW', 1049, 1091),
    ('src_02HXLM3PQR', 'dst_02HXLM3VYZ', 2001, 2034);

CREATE TABLE IF NOT EXISTS flags (
    id SERIAL PRIMARY KEY,
    flag_name VARCHAR(64) NOT NULL,
    flag_value TEXT NOT NULL
);

INSERT INTO flags (flag_name, flag_value) VALUES ('local', 'ARENA{placeholder}');
