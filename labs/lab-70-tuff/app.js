'use strict';

const express = require('express');
const http = require('http');
const path = require('path');
const session = require('express-session');
const WebSocket = require('ws');
const { exec } = require('child_process');
const { Pool } = require('pg');
const bcrypt = require('bcryptjs');
const { v4: uuidv4 } = require('uuid');
const fs = require('fs');

const app = express();
const server = http.createServer(app);

const pool = new Pool({
  connectionString: process.env.DATABASE_URL || 'postgresql://tuff:tuffpass@localhost/tuff'
});

app.use(session({
  secret: process.env.SESSION_SECRET || 'tuff-internal-secret-2024',
  resave: false,
  saveUninitialized: false,
  cookie: { httpOnly: true, sameSite: false }
}));

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use('/vendor', express.static(path.join(__dirname, 'public', 'vendor')));

const MONITOR_KEY = process.env.MONITOR_KEY || 'tuff-mon-k3y-7f2a1b9c';
const SCANNER_SESSION = process.env.SCANNER_SESSION || '';

const hostedFiles = new Map();
const eventLog = [];

function gf(n) {
  try {
    return fs.readFileSync(`/flag_${n}.txt`, 'utf8').trim();
  } catch (e) {
    return process.env[`FLAG_${n.toUpperCase()}`] || `ARENA{${n.toUpperCase()}_placeholder}`;
  }
}

function requireAuth(req, res, next) {
  if (!req.session || !req.session.userId) {
    return res.status(401).json({ error: 'not authenticated' });
  }
  next();
}

function requireAdmin(req, res, next) {
  if (!req.session || !req.session.userId) {
    return res.status(401).json({ error: 'not authenticated' });
  }
  if (!req.session.isAdmin) {
    return res.status(403).json({ error: 'admin only' });
  }
  next();
}

app.get('/', (req, res) => {
  const loggedIn = !!(req.session && req.session.userId);
  const isAdmin = loggedIn && req.session.isAdmin;
  res.setHeader('Content-Type', 'text/html');
  res.send(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>TUFF — Team Unified Feature Framework</title>
  <style>
    body { font-family: monospace; background: #0f0f1a; color: #c8d0e0; margin: 0; padding: 20px; }
    h1 { color: #7eb3ff; border-bottom: 1px solid #2a2a3a; padding-bottom: 10px; }
    .card { background: #16162a; border: 1px solid #2a2a3a; border-radius: 6px; padding: 20px; margin: 16px 0; }
    input, textarea { background: #0a0a14; color: #c8d0e0; border: 1px solid #3a3a5a; padding: 8px; width: 300px; font-family: monospace; }
    button { background: #1a4a8a; color: #c8d0e0; border: none; padding: 8px 18px; cursor: pointer; font-family: monospace; border-radius: 4px; }
    button:hover { background: #2a5a9a; }
    .nav { margin-bottom: 20px; }
    .nav a { color: #7eb3ff; margin-right: 16px; text-decoration: none; }
    pre { background: #0a0a14; padding: 12px; border-radius: 4px; overflow-x: auto; color: #88ff88; }
    .label { color: #7eb3ff; display: inline-block; width: 100px; }
  </style>
</head>
<body>
  <h1>TUFF — Team Unified Feature Framework</h1>
  <div class="nav">
    <a href="/">Home</a>
    <a href="/operations">Operations</a>
    ${isAdmin ? '<a href="/admin">Admin</a>' : ''}
    ${loggedIn ? '<a href="#" onclick="logout()">Logout</a>' : ''}
  </div>

  ${loggedIn ? `<p>Logged in as <strong>${req.session.username}</strong>${isAdmin ? ' (admin)' : ''}</p>` : ''}

  <div class="card">
    <h2>Register</h2>
    <input id="reg-user" placeholder="username" /><br><br>
    <input id="reg-pass" type="password" placeholder="password" /><br><br>
    <button onclick="register()">Register</button>
    <div id="reg-out"></div>
  </div>

  <div class="card">
    <h2>Login</h2>
    <input id="login-user" placeholder="username" /><br><br>
    <input id="login-pass" type="password" placeholder="password" /><br><br>
    <button onclick="login()">Login</button>
    <div id="login-out"></div>
  </div>

  ${loggedIn ? `
  <div class="card">
    <h2>Formula Evaluator ${isAdmin ? '' : '(Admin only)'}</h2>
    <p>Run automation expressions for your DevOps scripts.</p>
    <textarea id="formula" rows="4" cols="60" placeholder="e.g. 2 + 2 * 10"></textarea><br><br>
    <button onclick="evalFormula()">Evaluate</button>
    <pre id="eval-out"></pre>
  </div>
  ` : ''}

<script>
async function register() {
  const u = document.getElementById('reg-user').value;
  const p = document.getElementById('reg-pass').value;
  const r = await fetch('/api/register', {
    method: 'POST', credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({username: u, password: p})
  });
  const d = await r.json();
  document.getElementById('reg-out').textContent = JSON.stringify(d);
}
async function login() {
  const u = document.getElementById('login-user').value;
  const p = document.getElementById('login-pass').value;
  const r = await fetch('/api/login', {
    method: 'POST', credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({username: u, password: p})
  });
  const d = await r.json();
  document.getElementById('login-out').textContent = JSON.stringify(d);
  if (r.ok) setTimeout(() => location.reload(), 800);
}
async function logout() {
  await fetch('/api/logout', {method: 'POST', credentials: 'include'});
  location.reload();
}
async function evalFormula() {
  const formula = document.getElementById('formula').value;
  const r = await fetch('/api/eval', {
    method: 'POST', credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({formula})
  });
  const d = await r.json();
  document.getElementById('eval-out').textContent = JSON.stringify(d, null, 2);
}
</script>
</body>
</html>`);
});

app.get('/operations', requireAuth, (req, res) => {
  res.setHeader('Content-Type', 'text/html');
  res.send(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>TUFF Operations</title>
  <style>
    body { font-family: monospace; background: #0f0f1a; color: #c8d0e0; margin: 0; padding: 20px; }
    h1 { color: #7eb3ff; }
    .card { background: #16162a; border: 1px solid #2a2a3a; border-radius: 6px; padding: 20px; margin: 16px 0; }
    input { background: #0a0a14; color: #c8d0e0; border: 1px solid #3a3a5a; padding: 8px; width: 300px; font-family: monospace; }
    button { background: #1a4a8a; color: #c8d0e0; border: none; padding: 8px 18px; cursor: pointer; font-family: monospace; border-radius: 4px; }
    button:hover { background: #2a5a9a; }
    pre { background: #0a0a14; padding: 12px; border-radius: 4px; color: #88ff88; min-height: 40px; }
    #ws-status { color: #ff4444; }
    #ws-log { max-height: 200px; overflow-y: auto; }
    a { color: #7eb3ff; }
  </style>
</head>
<body>
  <h1>TUFF Operations</h1>
  <p><a href="/">Back to Home</a></p>

  <div class="card">
    <h2>Service Filter</h2>
    <div id="filter-display"></div>
    <input id="filter" placeholder="Filter services..." oninput="updateFilter(this.value)">
    <p style="color:#555; font-size:0.85em">Tip: use ?filter= in URL to pre-filter</p>
  </div>

  <div class="card">
    <h2>WebSocket Terminal <span id="ws-status"> disconnected</span></h2>

    <input type="text" id="ws-cmd" placeholder="enter command" style="width:350px">
    <button onclick="sendCmd()">Run</button>
    <pre id="ws-log"></pre>
  </div>

<script>
  function normalizeFilterDisplay(val) {
    document.getElementById('filter-display').textContent = decodeURIComponent(val);
  }

  function updateFilter(val) {
    document.getElementById('filter').innerHTML = val;
  }

  const raw = location.search.split('filter=')[1];
  if (raw) {
    document.getElementById('filter-display').innerHTML = 'Active filter: ' + raw;
    document.getElementById('filter').value = decodeURIComponent(raw);
  }

  let ws = null;
  connectWs();

  function connectWs() {
    const proto = location.protocol === 'https:' ? 'wss' : 'ws';
    ws = new WebSocket(proto + '://' + location.host + '/ws/operations');
    ws.onopen = function() {
      document.getElementById('ws-status').style.color = '#88ff88';
      document.getElementById('ws-status').textContent = ' connected';
    };
    ws.onmessage = function(e) {
      const log = document.getElementById('ws-log');
      log.textContent += e.data + '\\n';
      log.scrollTop = log.scrollHeight;
    };
    ws.onclose = function() {
      document.getElementById('ws-status').style.color = '#ff4444';
      document.getElementById('ws-status').textContent = ' disconnected';
    };
  }

  function sendCmd() {
    if (!ws || ws.readyState !== WebSocket.OPEN) { alert('WS not connected'); return; }
    const cmd = document.getElementById('ws-cmd').value.trim();
    ws.send(JSON.stringify({cmd: cmd}));
  }
</script>
</body>
</html>`);
});

app.get('/admin', requireAdmin, (req, res) => {
  res.setHeader('Content-Type', 'text/html');
  const flag = gf('local');
  res.send(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>TUFF Admin</title>
  <style>
    body { font-family: monospace; background: #0f0f1a; color: #c8d0e0; margin: 0; padding: 20px; }
    h1 { color: #ff7eb3; }
    .card { background: #16162a; border: 1px solid #2a2a3a; border-radius: 6px; padding: 20px; margin: 16px 0; }
    .flag { background: #0a200a; border: 1px solid #22aa22; color: #88ff88; padding: 14px; border-radius: 4px; font-size: 1.1em; }
    a { color: #7eb3ff; }
  </style>
</head>
<body>
  <h1>TUFF Admin Panel</h1>
  <p><a href="/">Back</a> | <a href="/operations">Operations</a></p>
  <div class="card">
    <h2>Admin Access Confirmed</h2>
    <p>Welcome to the internal admin panel. Flag for elevated access:</p>
    <div class="flag">${flag}</div>
  </div>
  <div class="card">
    <h2>Team Members</h2>
    <p>Use the formula evaluator to run admin automation scripts.</p>
    <button onclick="window.location='/'">Go to Evaluator</button>
  </div>
</body>
</html>`);
});

app.post('/api/register', async (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) return res.status(400).json({ error: 'username and password required' });
  try {
    const ex = await pool.query('SELECT id FROM users WHERE username=$1', [username]);
    if (ex.rows.length) return res.status(409).json({ error: 'username taken' });
    const h = bcrypt.hashSync(password, 10);
    const i = uuidv4();
    await pool.query('INSERT INTO users (id, username, password_hash, is_admin) VALUES ($1,$2,$3,false)', [i, username, h]);
    res.json({ ok: true, userId: i });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/api/login', async (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) return res.status(400).json({ error: 'username and password required' });
  try {
    const r = await pool.query('SELECT * FROM users WHERE username=$1', [username]);
    const u = r.rows[0];
    if (!u || !bcrypt.compareSync(password, u.password_hash)) {
      return res.status(401).json({ error: 'invalid credentials' });
    }
    req.session.userId = u.id;
    req.session.username = u.username;
    req.session.isAdmin = u.is_admin;
    res.json({ ok: true, userId: u.id, username: u.username, isAdmin: u.is_admin });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/api/logout', (req, res) => {
  req.session.destroy();
  res.json({ ok: true });
});

app.get('/api/whoami', requireAuth, async (req, res) => {
  res.json({ userId: req.session.userId, username: req.session.username, isAdmin: req.session.isAdmin });
});

app.get('/api/users', requireAuth, async (req, res) => {
  try {
    const r = await pool.query('SELECT id, username, is_admin FROM users ORDER BY username');
    res.json({ users: r.rows });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/admin/promote', requireAuth, async (req, res) => {
  const i = req.body.userId || (req.body && req.body['userId']);
  if (!i) return res.status(400).json({ error: 'userId required' });
  try {
    const r = await pool.query('UPDATE users SET is_admin=true WHERE id=$1 RETURNING id, username', [i]);
    if (!r.rows.length) return res.status(404).json({ error: 'user not found' });
    if (i === req.session.userId) req.session.isAdmin = true;
    res.json({ ok: true, promoted: r.rows[0].username });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/api/formula/processor', requireAuth, (req, res) => {
  const formula = req.body.formula;
  if (!formula) return res.status(400).json({ error: 'formula required' });

  if (/require|process|exec|spawn|fork|child|__proto__|constructor\.prototype|global\[/i.test(formula)) {
    return res.status(403).json({ error: 'blocked: expression not permitted' });
  }

  let r;
  try {
    r = eval(formula);
  } catch (e) {
    return res.status(500).json({ error: e.message });
  }
  res.json({ result: String(r) });
});

app.post('/api/eval', requireAdmin, (req, res) => {
  const formula = req.body.formula;
  if (!formula) return res.status(400).json({ error: 'formula required' });

  if (/require|process|__dirname|child_process/.test(formula)) {
    return res.status(403).json({ error: 'blocked: forbidden expression detected' });
  }

  let r;
  try {
    r = eval(formula);
  } catch (e) {
    return res.status(500).json({ error: e.message });
  }
  res.json({ result: String(r) });
});

const ALLOWED_CMDS = ['uptime', 'free -m', 'df -h', 'ps aux'];

const wssAll = new WebSocket.Server({ noServer: true });

server.on('upgrade', (req, socket, head) => {
  const url = req.url.split('?')[0];
  if (url === '/ws/status' || url === '/ws/operations' || url === '/ws/sudo_server') {
    wssAll.handleUpgrade(req, socket, head, (ws) => {
      wssAll.emit('connection', ws, req);
    });
  } else {
    socket.destroy();
  }
});

wssAll.on('connection', (ws, req) => {
  const url = req.url.split('?')[0];
  const z = req.headers.cookie || '';
  const hs = /connect\.sid=/.test(z);

  if (url === '/ws/status') {
    if (!hs) { ws.send('error: unauthenticated'); ws.close(); return; }
    ws.send('TUFF Status Terminal ready. Allowed commands: ' + ALLOWED_CMDS.join(', '));
    ws.on('message', (raw) => {
      let msg;
      try { msg = JSON.parse(raw); } catch (e) { ws.send('error: invalid JSON'); return; }
      const cmd = msg.cmd || '';
      if (!ALLOWED_CMDS.includes(cmd)) { ws.send('Command not permitted'); return; }
      exec(cmd, { timeout: 5000 }, (err, stdout, stderr) => {
        ws.send(err ? 'error: ' + (stderr || err.message) : (stdout || '(no output)'));
      });
    });

  } else if (url === '/ws/operations') {
    if (!hs) { ws.send('error: unauthenticated'); ws.close(); return; }
    ws.send('Operations channel ready. Type a command.');
    ws.on('message', (raw) => {
      let msg;
      try { msg = JSON.parse(raw); } catch (e) { ws.send('error: invalid JSON'); return; }
      const cmd  = msg.cmd  || '';
      const room = msg.room || 'public';
      if (!cmd) { ws.send('error: no cmd provided'); return; }
      if (room !== 'admin') {
        const ALLOWED = ['uptime', 'hostname', 'date', 'whoami'];
        if (!ALLOWED.includes(cmd.trim().split(' ')[0])) { ws.send('error: command not available in this room'); return; }
      }
      if (cmd.includes(';') || cmd.includes('&&') || cmd.includes('||')) { ws.send('error: chaining operators not allowed'); return; }
      exec(cmd, { timeout: 5000 }, (err, stdout, stderr) => {
        ws.send(err ? 'error: ' + (stderr || err.message) : (stdout || '(no output)'));
      });
    });

  } else if (url === '/ws/sudo_server') {
    const uid = Math.random().toString(16).slice(2) + '.' + Math.random().toString(16).slice(2, 10);
    ws.send(JSON.stringify({ payload: 'Connection established', type: 'connection', task: '', uniqid: uid }));
    const iv = setInterval(() => {
      if (ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify({ type: 'dispatcher', running: false }));
    }, 5000);
    ws.on('close', () => clearInterval(iv));
    ws.on('message', (raw) => {
      let msg;
      try { msg = JSON.parse(raw); } catch (e) {
        ws.send(JSON.stringify({ type: 'response', payload: 'ERROR: invalid JSON\n' })); return;
      }
      const { task, data, key } = msg;
      if (key !== MONITOR_KEY) {
        ws.send(JSON.stringify({ type: 'response', payload: '\x1b[0;31mERROR: Unauthorized\x1b[0m\n' })); return;
      }
      if (task === 'execute_nagios_command') {
        const cmd = (data || '').trim();
        if (!cmd) return;
        const first = cmd.trim().split(/\s+/)[0];
        if (first !== 'ls' && !first.startsWith('./check_')) {
          ws.send(JSON.stringify({ type: 'response', payload: '\x1b[0;31mERROR: Forbidden command!\x1b[0m\n' })); return;
        }
        if (cmd.includes(';') || cmd.includes('&&') || cmd.includes('||')) {
          ws.send(JSON.stringify({ type: 'response', payload: '\x1b[0;31mERROR: Forbidden character detected!\x1b[0m\n' })); return;
        }
        const xc = first === 'ls' ? 'ls -1 /app/plugins' : 'su tuffmon -c \'' + cmd + '\'';
        exec(xc, { timeout: 10000, cwd: '/app/plugins' }, (err, stdout, stderr) => {
          ws.send(JSON.stringify({ type: 'response', payload: stdout || stderr || (err && err.message) || '' }));
        });
      }
    });

  } else {
    ws.close();
  }
});

app.post('/api/content/report', requireAuth, async (req, res) => {
  const { url, reason } = req.body || {};
  if (!url) return res.status(400).json({ error: 'url required' });
  try {
    const r = await pool.query(
      "INSERT INTO review_queue (submitted_by, url, reason, status, created_at) VALUES ($1, $2, $3, 'pending', NOW()) RETURNING id",
      [req.session.userId, url, reason || '']
    );
    res.json({ success: true, queue_id: r.rows[0].id, message: 'submitted for review' });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.get('/api/review/status/:id', requireAuth, async (req, res) => {
  try {
    const q = await pool.query('SELECT * FROM review_queue WHERE id = $1', [req.params.id]);
    if (!q.rows.length) return res.status(404).json({ error: 'not found' });
    const r = await pool.query('SELECT * FROM review_results WHERE queue_id = $1', [req.params.id]);
    res.json({ queue: q.rows[0], result: r.rows[0] || null });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.get('/api/csrf-token', requireAuth, (req, res) => {
  const { generateToken } = require('./middleware/csrf');
  res.json({ csrf_token: generateToken(req) });
});

app.post('/team/promote', requireAuth, async (req, res) => {
  const { userId } = req.body || {};
  if (!userId) return res.status(400).json({ error: 'userId required' });
  try {
    const r = await pool.query(
      'UPDATE users SET is_admin = true WHERE id = $1 RETURNING id, username',
      [userId]
    );
    if (!r.rows.length) return res.status(404).json({ error: 'user not found' });
    if (userId === req.session.userId) req.session.isAdmin = true;
    res.json({ ok: true, promoted: r.rows[0].username });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/api/preview', requireAuth, (req, res) => {
  const { markdown } = req.body || {};
  if (!markdown) return res.status(400).json({ error: 'markdown required' });

  let o = String(markdown)
    .replace(/&/g, '&amp;')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/`(.+?)`/g, '<code>$1</code>')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
    .replace(/\n/g, '<br>');

  o = o.replace(/javascript:/gi, '').replace(/\bon\w+\s*=/gi, '');

  res.json({ html: o });
});

app.get('/api/logs', requireAuth, async (req, res) => {
  const lm  = Math.min(parseInt(req.query.limit  || '20', 10), 100);
  const off = Math.max(parseInt(req.query.offset || '0',  10), 0);
  try {
    const q = await pool.query(
      'SELECT id, action, actor_id, created_at FROM activity_log ORDER BY created_at DESC LIMIT $1 OFFSET $2',
      [lm, off]
    );
    res.json({ logs: q.rows });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.get('/api/profile/:username', requireAuth, async (req, res) => {
  try {
    const q = await pool.query(
      'SELECT username, display_name, bio, created_at FROM users WHERE username = $1',
      [req.params.username]
    );
    if (!q.rows.length) return res.status(404).json({ error: 'not found' });
    res.json(q.rows[0]);
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.get('/monitoring', (req, res) => {
  const sa = req.session && req.session.userId;
  const mt = req.headers['x-monitor-token'];
  const sc = mt && SCANNER_SESSION && mt === SCANNER_SESSION;
  if (!sa && !sc) return res.status(401).send('Unauthorized');
  const isAdmin = (sa && req.session.isAdmin) || sc;
  res.setHeader('Content-Type', 'text/html');
  res.send(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>TUFF Monitoring</title>
  <style>
    body { font-family: monospace; background: #0f0f1a; color: #c8d0e0; margin: 0; padding: 20px; }
    h1 { color: #7eb3ff; }
    .card { background: #16162a; border: 1px solid #2a2a3a; border-radius: 6px; padding: 20px; margin: 16px 0; }
    code { background: #0a0a14; padding: 4px 8px; border-radius: 3px; color: #88ff88; }
    a { color: #7eb3ff; }
    .status { color: #88ff88; }
  </style>
  <script src="/vendor/tuff-core.js"></script>
</head>
<body>
  <h1>TUFF Monitoring Dashboard</h1>
  <p><a href="/">Back</a></p>
  <div class="card">
    <h2>Service Health</h2>
    <p><span class="status">&#9679;</span> API Gateway: operational</p>
    <p><span class="status">&#9679;</span> Scheduler: operational</p>
    <p><span class="status">&#9679;</span> Database: operational</p>
  </div>
  <div class="card">
    <h2>Monitoring Checks</h2>
    <p>Plugins directory: <code>/app/plugins</code></p>
    <p>Check execution via WebSocket at <code>/ws/sudo_server</code></p>
    <p>Tip: use URL parameter <code>?build=</code> to load a custom UI bundle.</p>
  </div>
  ${isAdmin ? `
  <div class="card">
    <h2>API Configuration</h2>
    <p>Monitor Access Key: <code id="monitor-key">${MONITOR_KEY}</code></p>
    <p>Use this key to authenticate WebSocket check execution requests.</p>
  </div>
  ` : ''}
</body>
</html>`);
});

app.post('/cdn/files', (req, res) => {
  const { filename, content } = req.body || {};
  if (!filename || !content) return res.status(400).json({ error: 'filename and content required' });
  if (!/^[\w.-]+\.js$/.test(filename)) return res.status(400).json({ error: 'js files only, alphanumeric names' });
  hostedFiles.set(filename, content);
  res.json({ ok: true, url: `/cdn/files/${filename}` });
});

app.get('/cdn/files/:name', (req, res) => {
  const o = hostedFiles.get(req.params.name);
  if (!o) return res.status(404).send('not found');
  res.setHeader('Content-Type', 'application/javascript');
  res.send(o);
});

app.get('/beacon/collect', (req, res) => {
  const b = req.query.data || '';
  if (b) eventLog.push({ data: b, ts: new Date().toISOString(), ip: req.ip });
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.send('ok');
});

app.get('/api/beacon/results', requireAuth, (req, res) => {
  res.json({ results: eventLog.slice(-50) });
});

app.get('/api/monitor/commands', requireAuth, async (req, res) => {
  try {
    const q = await pool.query('SELECT id, name, command_line, created_by, active, created_at FROM monitor_commands ORDER BY id');
    res.json({ commands: q.rows });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/api/monitor/commands', requireAdmin, async (req, res) => {
  const { name, command_line } = req.body || {};
  if (!name || !command_line) return res.status(400).json({ error: 'name and command_line required' });
  try {
    const q = await pool.query(
      'INSERT INTO monitor_commands (name, command_line, created_by) VALUES ($1, $2, $3) RETURNING id',
      [name, command_line, req.session.username]
    );
    res.json({ ok: true, id: q.rows[0].id });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.delete('/api/monitor/commands/:id', requireAdmin, async (req, res) => {
  try {
    await pool.query('DELETE FROM monitor_commands WHERE id = $1', [req.params.id]);
    res.json({ ok: true });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.post('/api/monitor/run', requireAdmin, async (req, res) => {
  const { name } = req.body || {};
  const b = req.body.args || {};
  if (!name) return res.status(400).json({ error: 'name required' });
  try {
    const q = await pool.query('SELECT command_line FROM monitor_commands WHERE name = $1 AND active = true', [name]);
    if (!q.rows.length) return res.status(404).json({ error: 'command not found' });
    let cmd = q.rows[0].command_line;
    for (const [k, v] of Object.entries(b)) {
      cmd = cmd.split('{' + k + '}').join(String(v));
    }
    exec(cmd, { timeout: 8000, cwd: '/app/plugins' }, (err, stdout, stderr) => {
      res.json({ output: stdout || stderr || (err && err.message) || '' });
    });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

const PORT = process.env.PORT || 5000;
server.listen(PORT, () => {
  console.log(`TUFF running on port ${PORT}`);
});
