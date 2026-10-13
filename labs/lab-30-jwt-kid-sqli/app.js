const express = require('express');
const bodyParser = require('body-parser');
const jwt = require('jsonwebtoken');
const Database = require('better-sqlite3');
const { execSync } = require('child_process');
const fs = require('fs');

const app = express();
app.use(bodyParser.json());

const JWT_SECRET = process.env.JWT_SECRET || 'runtime-secret-must-be-set';

const db = new Database(':memory:');

db.exec(`
  CREATE TABLE keys (
    kid TEXT PRIMARY KEY,
    secret TEXT NOT NULL
  );
  INSERT INTO keys (kid, secret) VALUES ('default', '${JWT_SECRET}');
`);

function getToken(req) {
  const auth = req.headers['authorization'] || '';
  if (auth.startsWith('Bearer ')) return auth.slice(7);
  return null;
}

function verifyToken(token) {
  const decoded = jwt.decode(token, { complete: true });
  if (!decoded) throw new Error('Invalid token');

  const kid = decoded.header.kid;

  const query = "SELECT secret FROM keys WHERE kid = '" + kid + "'";
  const row = db.prepare(query).get();

  if (!row) throw new Error('Unknown kid');

  return jwt.verify(token, row.secret, { algorithms: ['HS256'] });
}

app.post('/login', (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) {
    return res.status(400).json({ error: 'username and password required' });
  }

  const token = jwt.sign(
    { username, role: 'user' },
    JWT_SECRET,
    { algorithm: 'HS256', header: { alg: 'HS256', kid: 'default' } }
  );

  res.json({ token });
});

app.get('/dashboard', (req, res) => {
  const token = getToken(req);
  if (!token) return res.status(401).json({ error: 'No token provided' });

  let payload;
  try {
    payload = verifyToken(token);
  } catch (e) {
    return res.status(401).json({ error: 'Invalid token: ' + e.message });
  }

  if (payload.role !== 'admin') {
    return res.status(403).json({ error: 'Access denied' });
  }

  const flagLocal = fs.existsSync('/flag_local.txt')
    ? fs.readFileSync('/flag_local.txt', 'utf8').trim()
    : process.env.FLAG_LOCAL || 'ARENA{LOCAL_placeholder}';

  res.json({
    message: 'Welcome, ' + payload.username,
    flag_local: flagLocal
  });
});

app.post('/admin/exec', (req, res) => {
  const token = getToken(req);
  if (!token) return res.status(401).json({ error: 'No token provided' });

  let payload;
  try {
    payload = verifyToken(token);
  } catch (e) {
    return res.status(401).json({ error: 'Invalid token: ' + e.message });
  }

  if (payload.role !== 'admin') {
    return res.status(403).json({ error: 'Admin role required' });
  }

  const { cmd } = req.body || {};
  if (!cmd) return res.status(400).json({ error: 'cmd field required' });

  let output;
  try {
    output = execSync(cmd, { timeout: 5000 }).toString();
  } catch (e) {
    output = e.stderr ? e.stderr.toString() : e.message;
  }

  res.json({ output });
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log('Application server started on port ' + PORT);
});
