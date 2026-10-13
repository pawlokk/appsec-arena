const express = require('express');
const bodyParser = require('body-parser');
const Datastore = require('nedb');
const jwt = require('jsonwebtoken');
const { execSync } = require('child_process');

const app = express();
const JWT_SECRET = process.env.JWT_SECRET;
const PORT = 3000;

if (!JWT_SECRET) {
  console.error('FATAL: JWT_SECRET environment variable is not set');
  process.exit(1);
}

app.use(bodyParser.json());

const db = new Datastore({ inMemoryOnly: true });

const ADMIN_PASS = process.env.ADMIN_PASS;
if (!ADMIN_PASS) {
  console.error('FATAL: ADMIN_PASS environment variable is not set');
  process.exit(1);
}

db.insert({ username: 'admin', password: ADMIN_PASS, role: 'admin' });
db.insert({ username: 'user', password: 'password', role: 'user' });

const FLAG_LOCAL = process.env.FLAG_LOCAL || 'ARENA{LOCAL_placeholder}';

app.post('/login', (req, res) => {
  const { username, password } = req.body;

  db.findOne({ username: username, password: password }, (err, user) => {
    if (err) {
      return res.status(500).json({ error: 'Database error' });
    }
    if (!user) {
      return res.status(401).json({ error: 'Invalid credentials' });
    }

    const token = jwt.sign(
      { username: user.username, role: user.role },
      JWT_SECRET,
      { algorithm: 'HS256', expiresIn: '1h' }
    );

    return res.status(200).json({ token, username: user.username, role: user.role });
  });
});

app.get('/api/me', (req, res) => {
  const authHeader = req.headers['authorization'];
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'No token provided' });
  }
  const token = authHeader.slice(7);
  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    if (decoded.role !== 'admin') {
      return res.status(403).json({ error: 'Access denied' });
    }
    return res.status(200).json({
      username: decoded.username,
      role: decoded.role,
      flag: FLAG_LOCAL
    });
  } catch (e) {
    return res.status(401).json({ error: 'Invalid token' });
  }
});

app.post('/admin/shell', (req, res) => {
  const authHeader = req.headers['authorization'];
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'No token provided' });
  }
  const token = authHeader.slice(7);
  let decoded;
  try {
    decoded = jwt.verify(token, JWT_SECRET);
  } catch (e) {
    return res.status(401).json({ error: 'Invalid token' });
  }

  if (decoded.role !== 'admin') {
    return res.status(403).json({ error: 'Admin only' });
  }

  const { cmd } = req.body;
  if (!cmd) {
    return res.status(400).json({ error: 'No cmd provided' });
  }

  try {
    const output = execSync(cmd, { encoding: 'utf8' });
    return res.status(200).json({ output });
  } catch (e) {
    return res.status(500).json({ error: e.message, output: e.stdout || '' });
  }
});

app.listen(PORT, () => {
  console.log(`Sieve lab running on port ${PORT}`);
});
