const express = require('express');
const bodyParser = require('body-parser');
const fetch = require('node-fetch');
const { exec } = require('child_process');
const fs = require('fs');
const crypto = require('crypto');

// ── Internal Metadata Service ──────────────────────────────────────────────
const meta = express();
meta.use(bodyParser.json());

// Plausible internal health check
meta.get('/health', (req, res) => {
  res.json({ status: 'ok', service: 'metadata', uptime: process.uptime() });
});

// Internal token endpoint — returns a short-lived service credential used by
// the exec relay.  Accessible only from loopback.
meta.get('/service-token', (req, res) => {
  const ts = Date.now();
  const raw = `internal:${ts}:${process.env.SERVICE_SECRET || 'changeme'}`;
  const token = crypto.createHash('sha256').update(raw).digest('hex').slice(0, 32);
  res.json({ token, issued_at: ts });
});


// Internal relay endpoint for service-to-service calls.
meta.get('/relay', async (req, res) => {
  const { cmd, token } = req.query;
  if (!cmd || !token) {
    return res.status(400).json({ error: 'Bad request' });
  }
  try {
    const r = await fetch('http://127.0.0.1:3002/exec', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Service-Token': token },
      body: JSON.stringify({ cmd }),
      timeout: 10000
    });
    const data = await r.json();
    res.json(data);
  } catch {
    res.status(502).json({ error: 'Relay unavailable' });
  }
});

meta.listen(3001, '127.0.0.1');

// ── Internal Exec Service ──────────────────────────────────────────────────
const execSvc = express();
execSvc.use(bodyParser.json());

execSvc.get('/health', (req, res) => {
  res.json({ status: 'ok' });
});

execSvc.post('/exec', (req, res) => {
  const token = req.headers['x-service-token'];
  const ts = Date.now();
  // Accept tokens issued within the last 60 seconds
  let valid = false;
  for (let delta = 0; delta <= 60000; delta += 1000) {
    const raw = `internal:${ts - delta}:${process.env.SERVICE_SECRET || 'changeme'}`;
    const expected = crypto.createHash('sha256').update(raw).digest('hex').slice(0, 32);
    if (expected === token) { valid = true; break; }
  }
  if (!valid) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  const cmd = req.body && req.body.cmd;
  if (!cmd) {
    return res.status(400).json({ error: 'Missing cmd' });
  }
  exec(cmd, { timeout: 10000 }, (err, stdout, stderr) => {
    res.json({
      stdout: stdout || '',
      stderr: stderr || '',
      exit_code: err ? (err.code || 1) : 0
    });
  });
});

execSvc.listen(3002, '127.0.0.1');

// ── JWT helpers ───────────────────────────────────────────────────────────
const JWT_SECRET = process.env.JWT_SECRET || crypto.randomBytes(32).toString('hex');

function b64url(str) {
  return Buffer.from(str).toString('base64').replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
}

function issueJwt(payload) {
  const header = b64url(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
  const body = b64url(JSON.stringify(payload));
  const sig = crypto.createHmac('sha256', JWT_SECRET).update(`${header}.${body}`).digest('base64')
    .replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
  return `${header}.${body}.${sig}`;
}

function verifyJwt(token) {
  if (!token) return null;
  const parts = token.split('.');
  if (parts.length !== 3) return null;
  try {
    const header = JSON.parse(Buffer.from(parts[0], 'base64').toString());
    const payload = JSON.parse(Buffer.from(parts[1], 'base64').toString());
    // Algorithm dispatch
    if (header.alg === 'none') {
      if (payload.exp && Date.now() / 1000 > payload.exp) return null;
      return payload;
    }
    const expected = crypto.createHmac('sha256', JWT_SECRET)
      .update(`${parts[0]}.${parts[1]}`).digest('base64')
      .replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
    if (expected !== parts[2]) return null;
    if (payload.exp && Date.now() / 1000 > payload.exp) return null;
    return payload;
  } catch {
    return null;
  }
}

function requireAuth(req, res, next) {
  const auth = req.headers['authorization'] || '';
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : null;
  const payload = verifyJwt(token);
  if (!payload) return res.status(401).json({ error: 'Authentication required' });
  req.user = payload;
  next();
}

// ── In-memory user store ───────────────────────────────────────────────────
const USERS = {
  'demo@example.com': {
    id: 'u_demo',
    email: 'demo@example.com',
    name: 'Demo User',
    role: 'user',
    passwordHash: crypto.createHash('sha256').update(process.env.DEMO_PASSWORD || 'demo1234').digest('hex')
  }
};

// ── Public Application ─────────────────────────────────────────────────────
const app = express();
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// ── Auth routes ────────────────────────────────────────────────────────────
app.post('/api/auth/login', (req, res) => {
  const { email, password } = req.body || {};
  if (!email || !password) {
    return res.status(400).json({ error: 'email and password required' });
  }
  const user = USERS[email];
  if (!user) return res.status(401).json({ error: 'Invalid credentials' });
  const hash = crypto.createHash('sha256').update(password).digest('hex');
  if (hash !== user.passwordHash) return res.status(401).json({ error: 'Invalid credentials' });
  const now = Math.floor(Date.now() / 1000);
  const token = issueJwt({ sub: user.id, email: user.email, role: user.role, iat: now, exp: now + 3600 });
  res.json({ token });
});

app.post('/api/auth/logout', requireAuth, (req, res) => {
  res.json({ message: 'Logged out' });
});

// ── User routes ────────────────────────────────────────────────────────────
app.get('/api/users/me', requireAuth, (req, res) => {
  const user = USERS[req.user.email];
  if (!user) return res.status(404).json({ error: 'Not found' });
  res.json({
    id: user.id,
    email: user.email,
    name: user.name,
    role: user.role
  });
});

app.patch('/api/users/me', requireAuth, (req, res) => {
  const user = USERS[req.user.email];
  if (!user) return res.status(404).json({ error: 'Not found' });
  const { name } = req.body || {};
  if (name) user.name = name;
  res.json({ id: user.id, email: user.email, name: user.name, role: user.role });
});

// ── Webhook management routes ──────────────────────────────────────────────
const webhooks = {};
let webhookSeq = 1;

app.get('/api/webhooks', requireAuth, (req, res) => {
  const owned = Object.values(webhooks).filter(w => w.owner === req.user.sub);
  res.json({ webhooks: owned });
});

app.post('/api/webhooks', requireAuth, (req, res) => {
  const { name, target_url, events } = req.body || {};
  if (!name || !target_url) return res.status(400).json({ error: 'name and target_url required' });
  const id = `wh_${webhookSeq++}`;
  const wh = { id, name, target_url, events: events || [], owner: req.user.sub, created_at: new Date().toISOString() };
  webhooks[id] = wh;
  res.status(201).json(wh);
});

app.get('/api/webhooks/:id', requireAuth, (req, res) => {
  const wh = webhooks[req.params.id];
  if (!wh || wh.owner !== req.user.sub) return res.status(404).json({ error: 'Not found' });
  res.json(wh);
});

app.delete('/api/webhooks/:id', requireAuth, (req, res) => {
  const wh = webhooks[req.params.id];
  if (!wh || wh.owner !== req.user.sub) return res.status(404).json({ error: 'Not found' });
  delete webhooks[req.params.id];
  res.status(204).end();
});

// ── Webhook delivery logs ──────────────────────────────────────────────────
app.get('/api/webhooks/:id/deliveries', requireAuth, (req, res) => {
  const wh = webhooks[req.params.id];
  if (!wh || wh.owner !== req.user.sub) return res.status(404).json({ error: 'Not found' });
  res.json({ deliveries: [] });
});

// ── Settings ───────────────────────────────────────────────────────────────
app.get('/api/settings', requireAuth, (req, res) => {
  res.json({ notifications: true, retry_count: 3, timeout_ms: 5000 });
});

app.put('/api/settings', requireAuth, (req, res) => {
  res.json({ message: 'Settings updated' });
});

// ── Webhook preview ───────────────────────────────────────────────────────
app.post('/api/webhook/preview', requireAuth, async (req, res) => {
  const { url } = req.body;
  if (!url) {
    return res.status(400).json({ error: 'Missing url parameter' });
  }
  try {
    const response = await fetch(url, {
      method: 'GET',
      timeout: 8000,
      headers: { 'User-Agent': 'WebhookPreview/1.0' }
    });
    const contentType = response.headers.get('content-type') || '';
    let body;
    if (contentType.includes('application/json')) {
      body = await response.json();
    } else {
      body = await response.text();
    }
    res.json({
      status: response.status,
      content_type: contentType,
      body: body
    });
  } catch {
    res.status(500).json({ error: 'Request failed' });
  }
});

// ── Public UI ──────────────────────────────────────────────────────────────
app.get('/', (req, res) => {
  res.send(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>WebHook Manager</title>
  <style>
    body { font-family: sans-serif; background: #1a1a2e; color: #eaeaea; padding: 2rem; }
    h1 { color: #e94560; }
    .card { background: #16213e; border: 1px solid #0f3460; padding: 1.5rem; border-radius: 6px; margin-top: 1rem; }
    input[type=text], input[type=email], input[type=password] { width: 60%; padding: 0.5rem; background: #0f3460; color: #eaeaea; border: 1px solid #e94560; border-radius: 4px; }
    button { padding: 0.5rem 1rem; background: #e94560; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-left: 0.5rem; }
    pre { background: #0f3460; padding: 1rem; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; }
    label { display: block; margin-top: 0.5rem; }
  </style>
</head>
<body>
  <h1>WebHook Manager</h1>
  <p>Manage your webhook integrations.</p>
  <div class="card">
    <h2>Login</h2>
    <form id="loginForm">
      <label>Email <input type="email" id="loginEmail" placeholder="user@example.com" /></label>
      <label>Password <input type="password" id="loginPass" /></label>
      <button type="submit">Sign in</button>
    </form>
    <pre id="loginOut"></pre>
  </div>
  <div class="card">
    <h2>Webhook Preview</h2>
    <form id="previewForm">
      <label>Provide the webhook endpoint URL.
        <input type="text" id="urlInput" placeholder="https://example.com/webhook" />
      </label>
      <button type="submit">Preview</button>
    </form>
    <pre id="output">Response will appear here...</pre>
  </div>
  <script>
    let authToken = '';
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const out = document.getElementById('loginOut');
      try {
        const resp = await fetch('/api/auth/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: document.getElementById('loginEmail').value, password: document.getElementById('loginPass').value })
        });
        const data = await resp.json();
        if (data.token) { authToken = data.token; out.textContent = 'Authenticated.'; }
        else out.textContent = JSON.stringify(data, null, 2);
      } catch (err) { out.textContent = 'Error: ' + err.message; }
    });
    document.getElementById('previewForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const url = document.getElementById('urlInput').value;
      const out = document.getElementById('output');
      out.textContent = 'Fetching...';
      try {
        const resp = await fetch('/api/webhook/preview', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
          body: JSON.stringify({ url })
        });
        const data = await resp.json();
        out.textContent = JSON.stringify(data, null, 2);
      } catch (err) { out.textContent = 'Error: ' + err.message; }
    });
  </script>
</body>
</html>`);
});

app.listen(3000, '0.0.0.0', () => {
  console.log('[app] Public application listening on 0.0.0.0:3000');
});
