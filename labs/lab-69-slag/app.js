'use strict';

const express      = require('express');
const session      = require('express-session');
const bodyParser   = require('body-parser');
const cookieParser = require('cookie-parser');
const mongoose     = require('mongoose');
const Handlebars   = require('handlebars');
const fs           = require('fs');
const crypto       = require('crypto');

const { corsValidator } = require('./middleware/cors_validator');
const settingsRouter    = require('./routes/settings');

const app = express();

const FLAG_LOCAL = process.env.FLAG_LOCAL || 'ARENA{LOCAL_dev}';
const FLAG_PROOF = process.env.FLAG_PROOF || 'ARENA{PROOF_dev}';

function gf(n) {
  try {
    return fs.readFileSync(`/flag_${n}.txt`, 'utf8').trim();
  } catch (e) {
    return process.env[`FLAG_${n.toUpperCase()}`] || `ARENA{${n.toUpperCase()}_dev}`;
  }
}

const ALLOWED_ORIGINS = ['https://slag.internal', 'https://app.slag.io'];

function mergeConfig(dst, src) {
  const cl = Object.create(null);
  for (const k of Object.keys(src)) {
    if (['__proto__', 'constructor', 'prototype'].includes(k)) continue;
    cl[k] = src[k];
  }
  Object.assign(dst, cl);
}

app.use((req, res, next) => {
  res.setHeader('Access-Control-Allow-Origin', req.headers.origin || '*');
  res.setHeader('Access-Control-Allow-Credentials', 'true');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Bootstrap-Token');
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

app.use(cookieParser());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use('/api/settings', settingsRouter);
app.use(session({
  secret: 'slag-workspace-secret-2024',
  resave: false,
  saveUninitialized: false,
  cookie: { httpOnly: false, sameSite: false }
}));

const userSchema = new mongoose.Schema({
  username: String,
  password: String,
  role:     String,
});
const User = mongoose.model('User', userSchema);

const workspaceSchema = new mongoose.Schema({
  name:     String,
  owner:    String,
  settings: mongoose.Schema.Types.Mixed,
  template: String,
});
const Workspace = mongoose.model('Workspace', workspaceSchema);

const userPreferenceSchema = new mongoose.Schema({
  userId:                String,
  notificationTemplate:        String,
  notificationFrequency: { type: String, default: 'daily' },
  emailAddress:          String,
  updatedAt:             { type: Date, default: Date.now },
});
const UserPreference = mongoose.model('UserPreference', userPreferenceSchema);

const notificationQueueSchema = new mongoose.Schema({
  userId:    String,
  type:      String,
  context:   mongoose.Schema.Types.Mixed,
  status:    { type: String, default: 'pending' },
  createdAt: { type: Date, default: Date.now },
});
const NotificationQueue = mongoose.model('NotificationQueue', notificationQueueSchema);

const inviteSchema = new mongoose.Schema({
  workspaceId: String,
  token:       String,
  createdBy:   String,
  used:        { type: Boolean, default: false },
  createdAt:   { type: Date, default: Date.now },
});
const Invite = mongoose.model('Invite', inviteSchema);

const BOOTSTRAP_TOKEN = crypto.randomBytes(16).toString('hex');

function deepMerge(dst, src) {
  for (let k in src) {
    if (k === 'constructor') continue;
    if (typeof src[k] === 'object' && src[k] !== null) {
      if (!dst[k] || typeof dst[k] !== 'object') dst[k] = {};
      deepMerge(dst[k], src[k]);
    } else {
      dst[k] = src[k];
    }
  }
}

const cors = require('cors');
const publicRouter = express.Router();
const publicCors = cors({ origin: ['https://slag.internal', 'https://app.slag.io'], credentials: true });
publicRouter.use(publicCors);

publicRouter.get('/status', (req, res) => {
  res.json({ status: 'ok', service: 'slag-workspace', version: '2.1.4' });
});

publicRouter.get('/info', (req, res) => {
  res.json({ name: 'Slag', description: 'Shared workspace platform', public: true });
});

app.use('/api/public', publicRouter);

function requireAuth(req, res, next) {
  if (!req.session.user) return res.status(401).json({ error: 'Authentication required' });
  next();
}

function requireAdmin(req, res, next) {
  if (!req.session.user || req.session.user.role !== 'admin') {
    const t = req.headers['x-bootstrap-token'];
    if (t && t === BOOTSTRAP_TOKEN) return next();
    return res.status(403).json({ error: 'Admin access required' });
  }
  next();
}

async function cs() {
  await mongoose.connect('mongodb://127.0.0.1:27017/slag');
  const cnt = await User.countDocuments();
  if (cnt === 0) {
    await User.insertMany([
      { username: 'admin', password: 'adm1n-sl@g!', role: 'admin' },
      { username: 'alice', password: 'alicepass',   role: 'user'  },
      { username: 'bob',   password: 'bobpass',     role: 'user'  },
    ]);
    await Workspace.create({
      name: 'default',
      owner: 'admin',
      settings: { theme: 'dark', maxMembers: 10, notifications: { email: true } },
      template: '<h1>{{title}}</h1><p>Welcome to {{workspace}}!</p>',
    });
    await UserPreference.insertMany([
      { userId: 'admin', notificationTemplate: 'Hello {{username}}, you have a new {{type}} notification.', notificationFrequency: 'instant', emailAddress: 'admin@slag.internal' },
      { userId: 'alice', notificationTemplate: 'Hi {{username}}! Workspace update: {{type}}', notificationFrequency: 'daily', emailAddress: 'alice@example.com' },
      { userId: 'bob',   notificationTemplate: '{{type}} from Slag', notificationFrequency: 'weekly', emailAddress: 'bob@example.com' },
    ]);
    await NotificationQueue.insertMany([
      { userId: 'alice', type: 'workspace_update', context: { workspace: 'default' }, status: 'sent' },
      { userId: 'bob',   type: 'member_added',     context: { workspace: 'default' }, status: 'sent' },
      { userId: 'admin', type: 'system_alert',     context: { message: 'Quota warning' }, status: 'pending' },
    ]);
  }
}

let currentTemplate = '<h1>{{title}}</h1><p>Welcome to <strong>{{workspace}}</strong>!</p><p>Members: {{memberCount}}</p>';

app.get('/', (req, res) => {
  res.send(`<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Slag — Shared Workspaces</title>
<style>
body{font-family:Arial,sans-serif;background:#0f1117;color:#c9d1d9;margin:0;padding:2rem;max-width:900px;margin:0 auto;padding:2rem}
h1{color:#58a6ff}h2{color:#79c0ff}
.card{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:1.5rem;margin:1rem 0}
input,textarea{width:100%;background:#0d1117;border:1px solid #30363d;border-radius:6px;
  padding:8px 10px;color:#c9d1d9;font-family:monospace;margin:4px 0;box-sizing:border-box}
button{background:#1f6feb;color:#fff;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;margin:4px 0}
button:hover{background:#388bfd}
pre{background:#0d1117;border:1px solid #30363d;border-radius:6px;padding:1rem;overflow-x:auto;color:#c9d1d9}
.tag{display:inline-block;background:#21262d;border:1px solid #30363d;border-radius:4px;
  padding:2px 8px;font-size:.8em;color:#8b949e;margin:2px}
.warn{background:#3d2302;border:1px solid #7d4e0b;border-radius:6px;padding:.75rem;color:#e3b341;margin:.5rem 0}
</style>
</head>
<body>
<h1>Slag</h1>
<p>Shared workspace platform for teams. Configure workspaces, manage members, render pages.</p>

<div class="card">
<h2>Login</h2>
<input id="l-user" placeholder="Username" />
<input id="l-pass" type="password" placeholder="Password" />
<button onclick="doLogin()">Login</button>
<div id="login-out"></div>
</div>

<div class="card">
<h2>Register</h2>
<input id="r-user" placeholder="Username" />
<input id="r-pass" type="password" placeholder="Password" />
<button onclick="doRegister()">Register</button>
<div id="reg-out"></div>
</div>

<div class="card">
<h2>Workspace Settings</h2>
<p class="tag">POST /api/workspace/settings</p>
<textarea id="ws-settings" rows="6">{
  "theme": "light",
  "notifications": {
    "email": false
  }
}</textarea>
<button onclick="updateSettings()">Update Settings</button>
<div id="settings-out"></div>
</div>

<div class="card">
<h2>Admin Template Editor</h2>
<p class="warn">Changes apply immediately to all users in this workspace</p>
<textarea id="tpl-src" rows="4">{{title}} — {{workspace}}</textarea>
<button onclick="saveTemplate()">Save Template</button>
<button onclick="renderPage()">Render Page</button>
<div id="tpl-out"></div>
</div>

<div class="card">
<h2>API Reference</h2>
<pre>POST /api/login              { username, password }
POST /api/register           { username, password }
POST /api/workspace/settings { ...settings... }
GET  /api/workspace          returns workspace + rendered page
GET  /api/whoami</pre>
</div>

<script>
async function doLogin() {
  const r = await fetch('/api/login', {
    method: 'POST', credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({username: document.getElementById('l-user').value,
                          password: document.getElementById('l-pass').value})
  });
  const d = await r.json();
  document.getElementById('login-out').innerHTML = '<pre>' + JSON.stringify(d,null,2) + '</pre>';
}
async function doRegister() {
  const r = await fetch('/api/register', {
    method: 'POST', credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({username: document.getElementById('r-user').value,
                          password: document.getElementById('r-pass').value})
  });
  const d = await r.json();
  document.getElementById('reg-out').innerHTML = '<pre>' + JSON.stringify(d,null,2) + '</pre>';
}
async function updateSettings() {
  let body;
  try { body = JSON.parse(document.getElementById('ws-settings').value); }
  catch(e) { alert('Invalid JSON'); return; }
  const r = await fetch('/api/workspace/settings', {
    method: 'POST', credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(body)
  });
  const d = await r.json();
  document.getElementById('settings-out').innerHTML = '<pre>' + JSON.stringify(d,null,2) + '</pre>';
}
async function saveTemplate() {
  const r = await fetch('/admin/template', {
    method: 'POST', credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({template: document.getElementById('tpl-src').value})
  });
  const d = await r.json();
  document.getElementById('tpl-out').innerHTML = '<pre>' + JSON.stringify(d,null,2) + '</pre>';
}
async function renderPage() {
  const r = await fetch('/api/workspace', {credentials: 'include'});
  const d = await r.json();
  document.getElementById('tpl-out').innerHTML = '<pre>' + JSON.stringify(d,null,2) + '</pre>';
}
</script>
</body>
</html>`);
});

app.post('/api/login', async (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) return res.status(400).json({ error: 'Username and password required' });
  const u = await User.findOne({ username, password });
  if (!u) return res.status(401).json({ error: 'Invalid credentials' });
  req.session.user = { username: u.username, role: u.role };
  res.json({ success: true, username: u.username, role: u.role });
});

app.post('/api/register', async (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) return res.status(400).json({ error: 'Username and password required' });
  if (await User.findOne({ username })) return res.status(409).json({ error: 'User already exists' });
  await User.create({ username, password, role: 'user' });
  req.session.user = { username, role: 'user' };
  res.json({ success: true, username, role: 'user' });
});

app.post('/api/logout', (req, res) => {
  req.session.destroy(() => res.json({ success: true }));
});

app.get('/api/whoami', (req, res) => {
  if (!req.session.user) return res.status(401).json({ error: 'Not authenticated' });
  res.json({ username: req.session.user.username, role: req.session.user.role });
});

app.get('/api/bootstrap/token', (req, res) => {
  res.json({ value: BOOTSTRAP_TOKEN });
});

app.get('/api/bootstrap/dashboard', requireAdmin, (req, res) => {
  res.json({
    dashboard: 'Slag Admin Dashboard',
    workspaces: 1,
    users: 3,
    flag: gf('local'),
  });
});

app.post('/api/workspace/settings', requireAuth, async (req, res) => {
  const w = await Workspace.findOne({ owner: req.session.user.username }) ||
            await Workspace.findOne({});
  if (!w) return res.status(404).json({ error: 'No workspace found' });
  const cur = w.settings || {};
  deepMerge(cur, req.body);
  w.settings = cur;
  w.markModified('settings');
  await w.save();
  res.json({ success: true, settings: cur });
});

app.post('/api/workspace/defaults', requireAuth, async (req, res) => {
  const w = await Workspace.findOne({ owner: req.session.user.username }) ||
            await Workspace.findOne({});
  if (!w) return res.status(404).json({ error: 'No workspace found' });
  const dfl = w.settings || {};
  mergeConfig(dfl, req.body);
  w.settings = dfl;
  w.markModified('settings');
  await w.save();
  res.json({ success: true, defaults: dfl });
});

app.get('/api/workspaces/find', requireAuth, async (req, res) => {
  const { name } = req.query;
  if (!name) return res.status(400).json({ error: 'name parameter required' });
  try {
    const ws = await Workspace.find(
      { name: { $regex: name }, owner: req.session.user.username },
      'name owner'
    );
    res.json({ workspaces: ws });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

app.get('/api/workspace', requireAuth, async (req, res) => {
  const w = await Workspace.findOne({}) || { name: 'default', settings: {} };
  const ctx = {
    title:       'Slag Workspace',
    workspace:   w.name,
    memberCount: 3,
    settings:    w.settings,
  };
  let rendered = '';
  try {
    rendered = Handlebars.compile(currentTemplate)(ctx);
  } catch (e) {
    rendered = 'Render error: ' + e.message;
  }
  res.json({ workspace: w.name, settings: w.settings, rendered });
});

app.post('/admin/template', requireAdmin, (req, res) => {
  const { template } = req.body || {};
  if (!template) return res.status(400).json({ error: 'template required' });
  currentTemplate = template;
  res.json({ success: true, message: 'Template saved' });
});

app.get('/admin/template', requireAdmin, (req, res) => {
  res.json({ template: currentTemplate });
});

app.get('/api/proof', (req, res) => {
  try {
    res.json({ output: fs.readFileSync('/tmp/out.txt', 'utf8').trim() });
  } catch (e) {
    res.json({ output: gf('proof') });
  }
});

app.get('/api/directory/workspaces', corsValidator, requireAuth, async (req, res) => {
  const ws = await Workspace.find({}, 'name owner');
  res.json({ workspaces: ws.map(w => ({ name: w.name, owner: w.owner })) });
});

app.get('/api/preferences', requireAuth, async (req, res) => {
  const pf = await UserPreference.findOne({ userId: req.session.user.username });
  if (!pf) return res.json({ notificationTemplate: '', notificationFrequency: 'daily', emailAddress: '' });
  res.json({ notificationTemplate: pf.notificationTemplate, notificationFrequency: pf.notificationFrequency, emailAddress: pf.emailAddress });
});

app.post('/api/preferences', requireAuth, async (req, res) => {
  const { notificationTemplate, notificationFrequency, emailAddress, layoutName } = req.body || {};
  let fl = (notificationTemplate || '');
  fl = fl.replace(/\{\{/g, '').replace(/\}\}/g, '');
  await UserPreference.findOneAndUpdate(
    { userId: req.session.user.username },
    {
      notificationTemplate:        fl,
      notificationFrequency: notificationFrequency || 'daily',
      emailAddress:          emailAddress || '',
      ...(layoutName !== undefined ? { layoutName } : {}),
      updatedAt: new Date(),
    },
    { upsert: true, new: true }
  );
  res.json({ success: true, filtered: fl });
});

app.post('/api/notifications/test', requireAuth, async (req, res) => {
  const tgt = (req.body && req.body.recipient) || req.session.user.username;
  const j = await NotificationQueue.create({
    userId:  tgt,
    type:    'test',
    context: { username: tgt, source: 'manual', initiator: req.session.user.username },
    status:  'pending',
  });
  res.json({ success: true, job_id: j._id, message: 'notification queued' });
});

app.get('/api/notifications/queue', requireAuth, async (req, res) => {
  const js = await NotificationQueue.find(
    { userId: req.session.user.username },
    'type status createdAt'
  ).sort({ createdAt: -1 }).limit(10);
  res.json({ queue: js });
});

app.post('/api/invites', requireAuth, async (req, res) => {
  const { workspaceId } = req.body || {};
  if (!workspaceId) return res.status(400).json({ error: 'workspaceId required' });
  const t = crypto.randomBytes(20).toString('hex');
  await Invite.create({ workspaceId, token: t, createdBy: req.session.user.username });
  res.json({ success: true, token: t });
});

app.get('/api/invites/:token', async (req, res) => {
  const iv = await Invite.findOne({ token: req.params.token });
  if (!iv || iv.used) return res.status(404).json({ error: 'invalid or used invite' });
  res.json({ workspace: iv.workspaceId, createdBy: iv.createdBy });
});

cs()
  .then(() => {
    app.listen(5000, () => console.log('[slag] running on :5000'));
  })
  .catch(e => {
    console.error('[slag] DB connection failed:', e.message);
    process.exit(1);
  });
