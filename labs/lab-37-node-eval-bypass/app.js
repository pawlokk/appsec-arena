'use strict';

const express = require('express');
const bodyParser = require('body-parser');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');

const app = express();
app.use(bodyParser.json());

const JWT_SECRET = process.env.JWT_SECRET;
if (!JWT_SECRET) {
  console.error('FATAL: JWT_SECRET environment variable is not set');
  process.exit(1);
}

const FLAG_LOCAL = process.env.FLAG_LOCAL || 'ARENA{LOCAL_placeholder}';

// In-memory user store
const users = [];

// ---- Auth endpoints ----

app.post('/register', (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) {
    return res.status(400).json({ error: 'username and password required' });
  }
  if (users.find(u => u.username === username)) {
    return res.status(409).json({ error: 'user already exists' });
  }
  const hash = bcrypt.hashSync(password, 10);
  users.push({ username, password: hash });
  res.json({ message: 'registered', username });
});

app.post('/login', (req, res) => {
  const { username, password } = req.body || {};
  const user = users.find(u => u.username === username);
  if (!user || !bcrypt.compareSync(password, user.password)) {
    return res.status(401).json({ error: 'invalid credentials' });
  }
  const token = jwt.sign({ username }, JWT_SECRET, { expiresIn: '1h' });
  res.json({ token });
});

function requireAuth(req, res, next) {
  const auth = req.headers['authorization'] || '';
  const token = auth.replace(/^Bearer\s+/i, '');
  if (!token) return res.status(401).json({ error: 'missing token' });
  try {
    req.user = jwt.verify(token, JWT_SECRET);
    next();
  } catch (e) {
    res.status(401).json({ error: 'invalid token' });
  }
}

// ---- Protected dashboard ----

app.get('/api/dashboard', requireAuth, (req, res) => {
  res.json({
    message: `Welcome, ${req.user.username}!`,
    status: 'authenticated'
  });
});

// ---- Public user list ----

app.get('/api/users', (req, res) => {
  res.json({
    result: users.map(u => ({ username: u.username })),
    count: users.length
  });
});

// ---- Expression evaluator ----

app.post('/api/eval', requireAuth, (req, res) => {
  const { expr } = req.body || {};
  if (!expr) {
    return res.status(400).json({ error: 'expr required' });
  }

  // Block forward slash to prevent direct path injection
  if (expr.includes('/')) {
    return res.status(403).json({ error: 'blocked: forward slash not allowed in expressions' });
  }

  let result;
  try {
    result = eval(expr); // eslint-disable-line no-eval
  } catch (e) {
    return res.status(500).json({ error: e.message });
  }

  res.json({ result });
});

// ---- Batch endpoint ----
// Body: array of request descriptors
// Each descriptor: { method, path, body?, headers? }
// References like $0, $0.field, $1.result etc. are resolved from previous responses

function resolveReference(ref, responses) {
  const match = ref.match(/^\$(\d+)(\.(.+))?$/);
  if (!match) return ref;

  const idx = parseInt(match[1], 10);
  if (idx >= responses.length) return null;

  const result = responses[idx];

  if (!match[3]) return result;

  try {
    return eval('result.' + match[3]); // eslint-disable-line no-eval
  } catch (e) {
    return null;
  }
}

function deepResolve(obj, responses) {
  if (typeof obj === 'string' && obj.startsWith('$')) {
    return resolveReference(obj, responses);
  }
  if (Array.isArray(obj)) {
    return obj.map(item => deepResolve(item, responses));
  }
  if (obj && typeof obj === 'object') {
    const out = {};
    for (const key of Object.keys(obj)) {
      out[key] = deepResolve(obj[key], responses);
    }
    return out;
  }
  return obj;
}

// Mini internal request dispatcher
function dispatchInternal(method, path, body, headers, req) {
  return new Promise((resolve) => {
    const fakeReq = {
      method: method.toUpperCase(),
      path,
      url: path,
      headers: Object.assign({}, req.headers, headers || {}),
      body: body || {},
      user: req.user
    };

    let statusCode = 200;

    const fakeRes = {
      statusCode: 200,
      status(code) { statusCode = code; return fakeRes; },
      json(data) { resolve({ status: statusCode, body: data }); },
      send(data) { resolve({ status: statusCode, body: data }); }
    };

    if (fakeReq.method === 'GET' && path === '/api/users') {
      fakeRes.json({
        result: users.map(u => ({ username: u.username })),
        count: users.length
      });
    } else if (fakeReq.method === 'GET' && path === '/api/dashboard') {
      if (!fakeReq.user) {
        fakeRes.status(401).json({ error: 'missing token' });
      } else {
        fakeRes.json({
          message: `Welcome, ${fakeReq.user.username}!`,
          status: 'authenticated'
        });
      }
    } else if (fakeReq.method === 'POST' && path === '/api/eval') {
      if (!fakeReq.user) {
        fakeRes.status(401).json({ error: 'missing token' });
        return;
      }
      const expr = (fakeReq.body || {}).expr;
      if (!expr) {
        fakeRes.status(400).json({ error: 'expr required' });
        return;
      }
      if (expr.includes('/')) {
        fakeRes.status(403).json({ error: 'blocked: forward slash not allowed in expressions' });
        return;
      }
      try {
        const result = eval(expr); // eslint-disable-line no-eval
        fakeRes.json({ result });
      } catch (e) {
        fakeRes.status(500).json({ error: e.message });
      }
    } else {
      fakeRes.status(404).json({ error: `route not found` });
    }
  });
}

app.post('/batch', requireAuth, async (req, res) => {
  const requests = req.body;
  if (!Array.isArray(requests)) {
    return res.status(400).json({ error: 'body must be an array of request descriptors' });
  }

  const responses = [];

  for (const descriptor of requests) {
    const { method, path, body, headers } = descriptor;

    const resolvedBody = body ? deepResolve(body, responses) : undefined;

    try {
      const result = await dispatchInternal(method, path, resolvedBody, headers, req);
      responses.push(result.body);
    } catch (e) {
      responses.push({ error: e.message });
    }
  }

  res.json({ results: responses });
});

// ---- Info endpoint ----

app.get('/', (req, res) => {
  res.json({
    lab: 'lab-37-node-eval-bypass',
    description: 'Batch processing API with reference chaining support',
    endpoints: [
      'POST /register',
      'POST /login',
      'GET  /api/dashboard  (JWT required)',
      'GET  /api/users',
      'POST /api/eval       { expr }  (JWT required)',
      'POST /batch          [ { method, path, body } ]  (JWT required)'
    ]
  });
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`Lab 37 listening on port ${PORT}`);
});
