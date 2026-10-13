const express = require('express');
const session = require('express-session');
const bodyParser = require('body-parser');
const { execSync } = require('child_process');
const fs = require('fs');
const crypto = require('crypto');

const app = express();
const PORT = 5000;

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(session({
  secret: process.env.SESSION_SECRET || crypto.randomBytes(32).toString('hex'),
  resave: false,
  saveUninitialized: false,
  cookie: { secure: false }
}));

// In-memory user store
const users = {
  alice: { id: 'alice', username: 'alice', password: 'alice123', createdAt: '2024-01-10T08:00:00Z' },
  bob:   { id: 'bob',   username: 'bob',   password: 'bob123',   createdAt: '2024-01-11T09:15:00Z' },
};

// Per-user settings store
const userSettings = {};

// Audit log (in-memory)
const auditLog = [
  { ts: '2024-01-10T08:01:12Z', user: 'alice', action: 'login',           detail: 'success' },
  { ts: '2024-01-10T08:05:33Z', user: 'alice', action: 'settings_update', detail: 'theme changed' },
  { ts: '2024-01-11T09:16:02Z', user: 'bob',   action: 'login',           detail: 'success' },
  { ts: '2024-01-12T14:22:48Z', user: 'bob',   action: 'config_export',   detail: 'full export' },
];

function recordAudit(user, action, detail) {
  auditLog.push({ ts: new Date().toISOString(), user, action, detail });
  if (auditLog.length > 500) auditLog.shift();
}

function mergeConfig(target, source) {
  for (const key of Object.keys(source)) {
    if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
      if (!target[key]) target[key] = {};
      mergeConfig(target[key], source[key]);
    } else {
      target[key] = source[key];
    }
  }
  return target;
}

function requireAuth(req, res, next) {
  if (!req.session.userId) {
    return res.status(401).json({ error: 'Not authenticated' });
  }
  next();
}

function requireAdmin(req, res, next) {
  const user = users[req.session.userId];
  if (!user || !user.isAdmin) {
    return res.status(403).send(renderPage('Access Denied', '<p style="color:#ff5c5c">Administrator access required.</p>'));
  }
  next();
}

function renderPage(title, content) {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>${title} — Hollow</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0d0f14; color: #c9d1d9; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
    .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
    h1 { color: #5c8aff; margin-bottom: 24px; font-size: 1.8rem; }
    h2 { color: #5c8aff; margin-bottom: 16px; }
    a { color: #5c8aff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    nav { margin-bottom: 32px; display: flex; gap: 20px; flex-wrap: wrap; }
    .card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
    input, select { background: #0d1117; border: 1px solid #30363d; color: #c9d1d9; padding: 8px 12px; border-radius: 4px; width: 100%; margin-bottom: 12px; font-size: 14px; }
    button { background: #5c8aff; color: #fff; border: none; padding: 9px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; }
    button:hover { background: #4a78ee; }
    button.secondary { background: #30363d; }
    button.secondary:hover { background: #444c56; }
    pre { background: #0d1117; border: 1px solid #30363d; border-radius: 4px; padding: 16px; overflow-x: auto; font-size: 13px; white-space: pre-wrap; word-break: break-all; }
    .msg { padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; font-size: 14px; }
    .err { background: #2d1b1b; border: 1px solid #ff5c5c; color: #ff5c5c; }
    .ok  { background: #1b2d1b; border: 1px solid #3fb950; color: #3fb950; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #30363d; }
    th { color: #8b949e; font-weight: 500; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .badge-admin { background: #2d2d1b; border: 1px solid #e3b341; color: #e3b341; }
    .badge-user  { background: #1b1b2d; border: 1px solid #5c8aff; color: #5c8aff; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Hollow</h1>
    <nav>
      <a href="/">Home</a>
      <a href="/dashboard">Dashboard</a>
      <a href="/api/profile">Profile</a>
      <a href="/users">Users</a>
      <a href="/admin">Admin</a>
      <a href="/logout">Logout</a>
    </nav>
    ${content}
  </div>
</body>
</html>`;
}

// Landing / login page
app.get('/', (req, res) => {
  const loggedIn = !!req.session.userId;
  res.send(renderPage('Home', `
    <div class="card">
      <h2>Welcome to Hollow</h2>
      <p style="margin-bottom:16px;color:#8b949e;">Hollow is a lightweight configuration management platform for teams. Store, version, and deploy application preferences across environments.</p>
      ${loggedIn
        ? `<p>Logged in as <strong>${req.session.userId}</strong>. <a href="/dashboard">Go to dashboard</a> &nbsp;|&nbsp; <a href="/logout">Logout</a></p>`
        : `<p>Please <a href="#" onclick="showLogin()">sign in</a> to continue, or <a href="#" onclick="showReg()">create an account</a>.</p>`
      }
    </div>
    ${loggedIn ? '' : `
    <div class="card" id="loginCard">
      <h2>Sign In</h2>
      <form id="loginForm">
        <input type="text" id="lu" placeholder="Username" autocomplete="username" />
        <input type="password" id="lp" placeholder="Password" autocomplete="current-password" />
        <button type="button" onclick="doLogin()">Sign In</button>
      </form>
      <div id="loginMsg"></div>
    </div>
    <div class="card" id="regCard" style="display:none">
      <h2>Create Account</h2>
      <form id="regForm">
        <input type="text" id="ru" placeholder="Username (letters, numbers, _ -)" autocomplete="username" />
        <input type="password" id="rp" placeholder="Password" autocomplete="new-password" />
        <button type="button" onclick="doReg()">Create Account</button>
      </form>
      <div id="regMsg"></div>
    </div>
    <script>
      function showLogin() { document.getElementById('loginCard').style.display=''; document.getElementById('regCard').style.display='none'; }
      function showReg()   { document.getElementById('regCard').style.display='';   document.getElementById('loginCard').style.display='none'; }
      async function doLogin() {
        const r = await fetch('/api/login', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:document.getElementById('lu').value,password:document.getElementById('lp').value})});
        const d = await r.json();
        const el = document.getElementById('loginMsg');
        if (r.ok) { el.className='msg ok'; el.textContent='Signed in. Redirecting...'; setTimeout(()=>location.href='/dashboard',800); }
        else { el.className='msg err'; el.textContent=d.error||'Sign-in failed'; }
      }
      async function doReg() {
        const r = await fetch('/api/register', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:document.getElementById('ru').value,password:document.getElementById('rp').value})});
        const d = await r.json();
        const el = document.getElementById('regMsg');
        if (r.ok) { el.className='msg ok'; el.textContent='Account created. You can now sign in.'; }
        else { el.className='msg err'; el.textContent=d.error||'Registration failed'; }
      }
    </script>
    `}
  `));
});

// Dashboard
app.get('/dashboard', requireAuth, (req, res) => {
  const user = users[req.session.userId];
  const settings = userSettings[req.session.userId] || {};
  const configCount = Object.keys(settings).length;
  res.send(renderPage('Dashboard', `
    <div class="card">
      <h2>Dashboard</h2>
      <p style="color:#8b949e;margin-bottom:16px;">Welcome back, <strong>${user.username}</strong>.</p>
      <table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Config keys stored</td><td>${configCount}</td></tr>
        <tr><td>Account role</td><td><span class="badge ${user.isAdmin ? 'badge-admin' : 'badge-user'}">${user.isAdmin ? 'Admin' : 'User'}</span></td></tr>
        <tr><td>Recent audit events</td><td>${auditLog.filter(e=>e.user===user.username).length}</td></tr>
      </table>
    </div>
    <div class="card">
      <h2>Quick Links</h2>
      <p><a href="/api/profile">Edit profile &amp; preferences</a></p>
      <p style="margin-top:8px"><a href="/api/config/export">Export configuration</a></p>
      <p style="margin-top:8px"><a href="/users">Browse user directory</a></p>
    </div>
  `));
});

// Register
app.post('/api/register', (req, res) => {
  const { username, password } = req.body;
  if (!username || !password) return res.status(400).json({ error: 'Username and password required' });
  if (users[username]) return res.status(409).json({ error: 'Username already taken' });
  if (/[^a-zA-Z0-9_\-]/.test(username)) return res.status(400).json({ error: 'Username contains invalid characters' });
  users[username] = { id: username, username, password, createdAt: new Date().toISOString() };
  recordAudit(username, 'register', 'new account');
  res.json({ message: 'Account created' });
});

// Login
app.post('/api/login', (req, res) => {
  const { username, password } = req.body;
  const user = users[username];
  if (!user || user.password !== password) {
    return res.status(401).json({ error: 'Invalid credentials' });
  }
  req.session.userId = user.id;
  recordAudit(username, 'login', 'success');
  res.json({ message: 'Authenticated', username: user.username });
});

// Logout
app.get('/logout', (req, res) => {
  if (req.session.userId) recordAudit(req.session.userId, 'logout', '');
  req.session.destroy();
  res.redirect('/');
});

// Profile + settings UI
app.get('/api/profile', requireAuth, (req, res) => {
  const user = users[req.session.userId];
  const settings = userSettings[req.session.userId] || {};
  res.send(renderPage('Profile', `
    <div class="card">
      <h2>Profile</h2>
      <table>
        <tr><th>Field</th><th>Value</th></tr>
        <tr><td>Username</td><td>${user.username}</td></tr>
        <tr><td>Role</td><td><span class="badge ${user.isAdmin ? 'badge-admin' : 'badge-user'}">${user.isAdmin ? 'Administrator' : 'User'}</span></td></tr>
        <tr><td>Member since</td><td>${user.createdAt || 'N/A'}</td></tr>
      </table>
    </div>
    <div class="card">
      <h2>Preferences</h2>
      <p style="color:#8b949e;margin-bottom:16px;font-size:13px;">Update your stored preferences. Provide a JSON object with the keys you want to change.</p>
      <textarea id="settingsJson" style="background:#0d1117;border:1px solid #30363d;color:#c9d1d9;width:100%;height:120px;padding:10px;border-radius:4px;font-family:monospace;font-size:13px;">${JSON.stringify(settings, null, 2)}</textarea>
      <br><br>
      <button onclick="saveSettings()">Save Preferences</button>
      <div id="settingsMsg"></div>
    </div>
    <script>
      async function saveSettings() {
        let val;
        try { val = JSON.parse(document.getElementById('settingsJson').value); } catch(e) { document.getElementById('settingsMsg').className='msg err'; document.getElementById('settingsMsg').textContent='Invalid JSON'; return; }
        const r = await fetch('/api/settings', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(val)});
        const d = await r.json();
        const el = document.getElementById('settingsMsg');
        if (r.ok) { el.className='msg ok'; el.textContent='Preferences saved.'; }
        else { el.className='msg err'; el.textContent=d.error||'Save failed'; }
      }
    </script>
  `));
});

// Preferences update
app.post('/api/settings', requireAuth, (req, res) => {
  const userId = req.session.userId;
  if (!userSettings[userId]) {
    userSettings[userId] = {};
  }
  mergeConfig(userSettings[userId], req.body);
  recordAudit(userId, 'settings_update', 'preferences saved');
  res.json({ message: 'Preferences updated', settings: userSettings[userId] });
});

// Config export
app.get('/api/config/export', requireAuth, (req, res) => {
  const userId = req.session.userId;
  const settings = userSettings[userId] || {};
  recordAudit(userId, 'config_export', 'full export');
  res.setHeader('Content-Type', 'application/json');
  res.setHeader('Content-Disposition', `attachment; filename="hollow-config-${userId}.json"`);
  res.send(JSON.stringify({ exported_by: userId, exported_at: new Date().toISOString(), config: settings }, null, 2));
});

// Config import
app.post('/api/config/import', requireAuth, (req, res) => {
  const userId = req.session.userId;
  const { config } = req.body;
  if (!config || typeof config !== 'object') {
    return res.status(400).json({ error: 'A "config" object is required' });
  }
  if (!userSettings[userId]) userSettings[userId] = {};
  mergeConfig(userSettings[userId], config);
  recordAudit(userId, 'config_import', 'import applied');
  res.json({ message: 'Configuration imported', keys: Object.keys(userSettings[userId]).length });
});

// User directory (authenticated users can view list without sensitive fields)
app.get('/users', requireAuth, (req, res) => {
  const rows = Object.values(users).map(u =>
    `<tr>
       <td>${u.username}</td>
       <td>${u.createdAt || '—'}</td>
       <td><span class="badge ${u.isAdmin ? 'badge-admin' : 'badge-user'}">${u.isAdmin ? 'Admin' : 'User'}</span></td>
     </tr>`
  ).join('');
  res.send(renderPage('Users', `
    <div class="card">
      <h2>User Directory</h2>
      <table>
        <tr><th>Username</th><th>Member Since</th><th>Role</th></tr>
        ${rows}
      </table>
    </div>
  `));
});

// System status (public, no auth required — a realistic health endpoint)
app.get('/api/status', (req, res) => {
  res.json({
    status: 'ok',
    version: '1.4.2',
    uptime: process.uptime(),
    users: Object.keys(users).length,
    timestamp: new Date().toISOString(),
  });
});

// Admin panel
app.get('/admin', requireAdmin, (req, res) => {
  const recentLog = auditLog.slice(-10).reverse();
  const logRows = recentLog.map(e =>
    `<tr><td>${e.ts}</td><td>${e.user}</td><td>${e.action}</td><td>${e.detail}</td></tr>`
  ).join('');

  res.send(renderPage('Admin Panel', `
    <div class="card">
      <h2>Administration</h2>
      <p style="color:#8b949e;font-size:13px;">System management for administrators.</p>
    </div>
    <div class="card">
      <h2>Recent Activity</h2>
      <table>
        <tr><th>Timestamp</th><th>User</th><th>Action</th><th>Detail</th></tr>
        ${logRows || '<tr><td colspan="4" style="color:#8b949e">No entries</td></tr>'}
      </table>
    </div>
    <div class="card">
      <h2>Network Diagnostics</h2>
      <p style="color:#8b949e;margin-bottom:16px;font-size:13px;">Test connectivity to internal and external hosts.</p>
      <div style="display:flex;gap:8px;align-items:flex-start">
        <input type="text" id="host" placeholder="Hostname or IP" value="127.0.0.1" style="width:280px;margin-bottom:0" />
        <button onclick="doPing()">Ping</button>
      </div>
      <pre id="pingOut" style="margin-top:16px;min-height:40px;"></pre>
    </div>
    <script>
      async function doPing() {
        const host = document.getElementById('host').value;
        const r = await fetch('/admin/ping', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({host})});
        const d = await r.json();
        document.getElementById('pingOut').textContent = d.output || d.error || '';
      }
    </script>
  `));
});

// Admin network check
app.post('/admin/ping', requireAdmin, (req, res) => {
  const { host } = req.body;
  if (!host) return res.status(400).json({ error: 'Host required' });
  try {
    const raw = execSync('ping -c 1 ' + host, { timeout: 8000 }).toString();
    // Return only the summary line to keep output concise
    const lines = raw.split('\n').filter(l => l.trim());
    const summary = lines.slice(-3).join('\n');
    res.json({ output: summary });
  } catch (err) {
    const out = err.stdout ? err.stdout.toString() : err.message;
    const lines = out.split('\n').filter(l => l.trim());
    res.json({ output: lines.slice(-3).join('\n') });
  }
});

app.listen(PORT, () => {
  console.log(`Hollow running on port ${PORT}`);
});
