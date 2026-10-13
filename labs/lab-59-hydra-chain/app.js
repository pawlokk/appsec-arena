const express = require('express');
const bodyParser = require('body-parser');
const Datastore = require('nedb');
const fetch = require('node-fetch');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(bodyParser.json());

const db = new Datastore({ inMemoryOnly: true });

const FLAG_LOCAL = process.env.FLAG_LOCAL || 'FLAG_LOCAL_NOT_SET';
const INTERNAL_API_KEY = process.env.INTERNAL_API_KEY;

db.insert([
  { tag: 'internal', apiKey: INTERNAL_API_KEY, type: 'service-credential' },
  { tag: 'public', info: 'This is the search endpoint', type: 'info' }
]);

const users = {};

app.post('/api/register', (req, res) => {
  const { username, password } = req.body;
  if (!username || !password) return res.status(400).json({ error: 'Missing fields' });
  if (users[username]) return res.status(409).json({ error: 'User exists' });
  users[username] = password;
  res.json({ ok: true });
});

app.post('/api/login', (req, res) => {
  const { username, password } = req.body;
  if (users[username] && users[username] === password) {
    res.json({ ok: true, token: Buffer.from(username).toString('base64') });
  } else {
    res.status(401).json({ error: 'Invalid credentials' });
  }
});

function authMiddleware(req, res, next) {
  const auth = req.headers['authorization'];
  if (!auth) return res.status(401).json({ error: 'Unauthorized' });
  const token = auth.replace('Bearer ', '');
  const username = Buffer.from(token, 'base64').toString('utf8');
  if (!users[username]) return res.status(401).json({ error: 'Invalid token' });
  req.username = username;
  next();
}

app.get('/api/info', authMiddleware, (req, res) => {
  res.json({ welcome: `Hello ${req.username}` });
});

app.post('/api/search', (req, res) => {
  const { filter } = req.body;
  if (!filter || typeof filter !== 'object') {
    return res.status(400).json({ error: 'Invalid filter' });
  }
  db.find(filter, (err, docs) => {
    if (err) return res.status(500).json({ error: 'DB error' });
    if (!docs || docs.length === 0) return res.status(404).json({ results: [] });
    res.json({ results: docs });
  });
});

app.post('/api/webhook/test', authMiddleware, (req, res) => {
  const { url } = req.body;
  if (!url || typeof url !== 'string') return res.status(400).json({ error: 'Missing url' });
  fetch(url)
    .then(r => r.text())
    .then(body => res.json({ ok: true, body }))
    .catch(e => res.status(500).json({ error: e.message }));
});

app.get('/api/flag', authMiddleware, (req, res) => {
  const flagPath = '/flag_local.txt';
  if (!fs.existsSync(flagPath)) return res.status(404).json({ error: 'Flag not found' });
  const flag = fs.readFileSync(flagPath, 'utf8').trim();
  res.json({ flag });
});

app.get('/api/result', (req, res) => {
  const pwned = '/tmp/pwned';
  if (!fs.existsSync(pwned)) return res.status(404).json({ error: 'Not found' });
  const content = fs.readFileSync(pwned, 'utf8');
  res.json({ result: content });
});

app.get('/tools/ysoserial.jar', (req, res) => {
  const jarPath = '/app/ysoserial.jar';
  if (!fs.existsSync(jarPath)) return res.status(404).json({ error: 'Not found' });
  res.setHeader('Content-Type', 'application/java-archive');
  res.setHeader('Content-Disposition', 'attachment; filename="ysoserial.jar"');
  fs.createReadStream(jarPath).pipe(res);
});

app.listen(3000, () => console.log('App running on port 3000'));
