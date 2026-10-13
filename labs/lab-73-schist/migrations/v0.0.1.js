module.exports = async (db) => {
  await db.none(`
    CREATE TABLE IF NOT EXISTS users (
      id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
      username VARCHAR(64) UNIQUE NOT NULL,
      display_name VARCHAR(128),
      realm VARCHAR(32) DEFAULT 'local',
      is_admin BOOLEAN DEFAULT false,
      password_hash VARCHAR(256),
      created_at TIMESTAMP DEFAULT NOW()
    )
  `);
  await db.none(`
    CREATE TABLE IF NOT EXISTS api_keys (
      id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
      user_id UUID REFERENCES users(id),
      name VARCHAR(64) DEFAULT 'default',
      key_hash VARCHAR(256) NOT NULL,
      created_at TIMESTAMP DEFAULT NOW()
    )
  `);
  await db.none(`
    CREATE TABLE IF NOT EXISTS processes (
      id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
      user_id UUID REFERENCES users(id),
      status VARCHAR(32) DEFAULT 'RUNNING',
      output TEXT DEFAULT '',
      created_at TIMESTAMP DEFAULT NOW()
    )
  `);
  await db.none(`
    CREATE TABLE IF NOT EXISTS review_queue (
      id SERIAL PRIMARY KEY,
      url TEXT NOT NULL,
      visited BOOLEAN DEFAULT false,
      created_at TIMESTAMP DEFAULT NOW()
    )
  `);

  const bcrypt = require('bcrypt');
  const adminHash = await bcrypt.hash('schistadmin', 10);
  await db.none(`
    INSERT INTO users (id, username, display_name, is_admin, password_hash, realm)
    VALUES ('a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'admin', 'Administrator', true, $1, 'local')
    ON CONFLICT DO NOTHING
  `, [adminHash]);
};
