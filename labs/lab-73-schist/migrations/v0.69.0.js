module.exports = async (db) => {
  const bcrypt = require('bcrypt');
  const userId = 'd4f123c1-f8d4-40b2-8a12-b8947b9ce2d8';
  // "mK4pQnLr3vWxZy9B"
  const keyHash = await bcrypt.hash('mK4pQnLr3vWxZy9B', 10);
  await db.none(`
    INSERT INTO users (id, username, display_name, is_admin, realm)
    VALUES ($1, 'concordBot', 'concordBot', true, 'apikey')
    ON CONFLICT DO NOTHING
  `, [userId]);
  await db.none(`
    INSERT INTO api_keys (user_id, key_hash, name)
    VALUES ($1, $2, 'key-1')
    ON CONFLICT DO NOTHING
  `, [userId, keyHash]);
};
