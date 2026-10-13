const express = require('express');
const session = require('express-session');
const bcrypt = require('bcrypt');
const busboy = require('busboy');
const yaml = require('js-yaml');
const { execSync } = require('child_process');
const { v4: uuid } = require('uuid');
const pgp = require('pg-promise')();
const path = require('path');

const app = express();
const c = pgp(process.env.DATABASE_URL);
const PORT = process.env.PORT || 5000;

const attackerFiles = new Map();
const attackerData = [];

app.use(express.json());
app.use(express.urlencoded({ extended: false }));
app.use(session({
  secret: process.env.SESSION_SECRET || 'schist-default-secret',
  resave: false,
  saveUninitialized: false,
  cookie: { httpOnly: true, sameSite: 'none', secure: false }
}));

app.use((req, res, next) => {
  const z = req.headers.origin;
  if (req.method === 'OPTIONS') {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', '*');
    res.setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Range, Cookie, Origin');
    res.setHeader('Access-Control-Max-Age', '0');
    return res.sendStatus(204);
  }
  if (z) {
    res.setHeader('Access-Control-Allow-Origin', z);
    res.setHeader('Access-Control-Allow-Credentials', 'true');
    res.setHeader('Access-Control-Expose-Headers', 'cache-control,content-language,expires,last-modified,content-range,content-length,accept-ranges');
  } else {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', '*');
    res.setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Range, Cookie, Origin');
  }
  res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
  next();
});

app.use(express.static(path.join(__dirname, 'public')));

async function ru(req) {
  if (req.session.userId) {
    const u = await c.oneOrNone('SELECT * FROM users WHERE id = $1', [req.session.userId]);
    if (u) return u;
  }
  const z = req.headers.authorization;
  if (z) {
    if (z.startsWith('Basic ')) {
      const b = Buffer.from(z.slice(6), 'base64').toString();
      const k = b.indexOf(':');
      if (k > 0) {
        const n = b.slice(0, k);
        const p = b.slice(k + 1);
        const u = await c.oneOrNone('SELECT * FROM users WHERE username = $1 AND realm = $2', [n, 'local']);
        if (u && u.password_hash && await bcrypt.compare(p, u.password_hash)) return u;
      }
    } else {
      const ks = await c.any('SELECT ak.key_hash, u.* FROM api_keys ak JOIN users u ON ak.user_id = u.id');
      for (const v of ks) {
        if (await bcrypt.compare(z, v.key_hash)) {
          return { id: v.id, username: v.username, display_name: v.display_name, is_admin: v.is_admin, realm: 'apikey' };
        }
      }
    }
  }
  return null;
}

async function authenticate(req, res, next) {
  req.user = await ru(req);
  if (!req.user) return res.status(401).end();
  next();
}

async function requireAdmin(req, res, next) {
  req.user = await ru(req);
  if (!req.user || !req.user.is_admin) return res.status(403).end();
  next();
}

app.get('/api/service/console/whoami', async (req, res) => {
  const u = await ru(req);
  if (!u) return res.status(401).end();
  res.json({ realm: u.realm || 'local', username: u.username, displayName: u.display_name });
});

app.post('/api/service/console/whoami', async (req, res) => {
  const u = await ru(req);
  if (!u) return res.status(401).end();
  req.session.userId = u.id;
  res.json({ realm: u.realm || 'local', username: u.username, displayName: u.display_name });
});

app.get('/api/v1/apikey', authenticate, async (req, res) => {
  const ks = await c.any('SELECT id, name, created_at FROM api_keys WHERE user_id = $1', [req.user.id]);
  res.json(ks);
});

app.post('/api/v1/apikey', authenticate, async (req, res) => {
  const i = uuid();
  const t = Buffer.from(uuid().replace(/-/g, '') + uuid().replace(/-/g, '')).toString('base64').slice(0, 22);
  const h = await bcrypt.hash(t, 10);
  const n = (req.body && req.body.name) || 'key-' + Date.now();
  await c.none('INSERT INTO api_keys (id, user_id, key_hash, name) VALUES ($1, $2, $3, $4)', [i, req.user.id, h, n]);
  res.status(201).json({ id: i, name: n, key: t });
});

app.get('/api/v1/user', requireAdmin, async (req, res) => {
  const us = await c.any('SELECT id, username, display_name, is_admin, realm, created_at FROM users');
  res.json(us);
});

app.post('/api/v1/user', requireAdmin, async (req, res) => {
  const { username, password, admin, displayName } = req.body || {};
  if (!username) return res.status(400).json({ error: 'username required' });
  const i = uuid();
  let h = null;
  if (password) h = await bcrypt.hash(password, 10);
  try {
    await c.none(
      'INSERT INTO users (id, username, display_name, is_admin, password_hash, realm) VALUES ($1, $2, $3, $4, $5, $6)',
      [i, username, displayName || username, !!admin, h, 'local']
    );
    res.status(201).json({ id: i, username });
  } catch (e) {
    res.status(409).json({ error: 'username taken' });
  }
});

app.post('/api/v1/process', authenticate, (req, res) => {
  const x = req.headers['content-type'] || '';
  if (!x.includes('multipart')) {
    return res.status(400).json({ error: 'multipart/form-data required' });
  }
  const i = uuid();
  const bb = busboy({ headers: req.headers });
  let o = '';

  bb.on('file', (n, f) => {
    const ch = [];
    f.on('data', d => ch.push(d));
    f.on('end', () => { if (n === 'concord.yml') o = Buffer.concat(ch).toString(); });
  });

  bb.on('close', async () => {
    if (!o) return res.status(400).json({ error: 'concord.yml required' });
    await c.none('INSERT INTO processes (id, user_id) VALUES ($1, $2)', [i, req.user.id]);
    res.json({ instanceId: i, ok: true });
    rp(i, o);
  });

  req.pipe(bb);
});

app.get('/api/v1/process/:id', authenticate, async (req, res) => {
  const v = await c.oneOrNone('SELECT * FROM processes WHERE id = $1', [req.params.id]);
  if (!v) return res.status(404).end();
  res.json({ instanceId: v.id, status: v.status, output: v.output });
});

async function rp(i, o) {
  let out = '';
  try {
    const b = yaml.load(o);
    const st = (b && b.flows && b.flows.default) || [];
    for (const v of st) {
      if (v.script && v.body) {
        try {
          const r = execSync(v.body, { timeout: 8000, shell: '/bin/sh' });
          out += r.toString();
        } catch (e) {
          out += ((e.stdout || Buffer.alloc(0)).toString() + (e.stderr || Buffer.alloc(0)).toString());
        }
      }
    }
  } catch (e) {
    out = 'Error: ' + e.message;
  }
  await c.none("UPDATE processes SET status = 'FINISHED', output = $1 WHERE id = $2", [out, i]);
}

app.post('/api/v1/review', (req, res) => {
  const u = req.body && req.body.url;
  if (!u) return res.status(400).json({ error: 'url required' });
  c.none('INSERT INTO review_queue (url) VALUES ($1)', [u])
    .then(() => res.json({ ok: true }))
    .catch(() => res.status(500).end());
});

app.post('/attacker/files', (req, res) => {
  const x = req.headers['content-type'] || '';
  if (!x.includes('multipart')) return res.status(400).end();
  const bb = busboy({ headers: req.headers });
  bb.on('file', (n, f, info) => {
    const ch = [];
    f.on('data', d => ch.push(d));
    f.on('end', () => {
      const fn = info.filename || n;
      attackerFiles.set(fn, { content: Buffer.concat(ch), type: info.mimeType || 'text/html' });
    });
  });
  bb.on('close', () => res.json({ ok: true }));
  req.pipe(bb);
});

app.get('/attacker/files/:name', (req, res) => {
  const f = attackerFiles.get(req.params.name);
  if (!f) return res.status(404).end();
  res.setHeader('Content-Type', f.type);
  res.send(f.content);
});

app.get('/attacker/collect', (req, res) => {
  const b = req.query.data || '';
  attackerData.push({ data: b, ts: new Date().toISOString(), ip: req.ip });
  res.json({ ok: true });
});

app.get('/api/attacker/results', (req, res) => {
  res.json(attackerData);
});

app.listen(PORT, () => {
  console.log(`schist running on :${PORT}`);
  require('./workers/bot_worker');
});
