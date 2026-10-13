'use strict';

const express = require('express');
const bodyParser = require('body-parser');
const cookieParser = require('cookie-parser');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const Handlebars = require('handlebars');
const fs = require('fs');
const crypto = require('crypto');

const app = express();
const PORT = 3000;
const JWT_SECRET = process.env.JWT_SECRET || crypto.randomBytes(32).toString('hex');

// In-memory user store
const users = {};
let userIdCounter = 1;

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(cookieParser());

// ------------------------------------------------------------------ helpers

function readFlag(name) {
  try {
    const path = name === 'FLAG_LOCAL' ? '/tmp/flag_local.txt' : '/tmp/flag_proof.txt';
    return fs.readFileSync(path, 'utf8').trim();
  } catch (e) {
    return process.env[name] || `ARENA{${name}_NOT_SET}`;
  }
}

function authMiddleware(req, res, next) {
  const token = req.cookies.token || (req.headers.authorization || '').replace('Bearer ', '');
  if (!token) return res.status(401).json({ error: 'Not authenticated' });
  try {
    req.user = jwt.verify(token, JWT_SECRET);
    const stored = users[req.user.username];
    if (!stored) return res.status(401).json({ error: 'User not found' });
    req.user = { ...req.user, ...stored };
    next();
  } catch (e) {
    return res.status(401).json({ error: 'Invalid token' });
  }
}

function adminMiddleware(req, res, next) {
  if (req.user.role !== 'admin') {
    return res.status(403).json({ error: 'Admins only' });
  }
  next();
}

function rceDoneMiddleware(req, res, next) {
  if (!req.user.rceDone) {
    return res.status(403).json({ error: 'Admins only' });
  }
  next();
}

// ------------------------------------------------------------------ routes

// Home
app.get('/', (req, res) => {
  res.send(`
<!DOCTYPE html>
<html>
<head><title>ProfileHub</title>
<style>body{font-family:monospace;background:#0d0d0d;color:#00ff41;padding:2rem;}
a{color:#00ff41;}input{background:#111;color:#0f0;border:1px solid #0f0;padding:6px;}
button{background:#003300;color:#0f0;border:1px solid #0f0;padding:8px 16px;cursor:pointer;}
.box{border:1px solid #333;padding:1rem;margin:1rem 0;max-width:400px;}
</style></head>
<body>
<h1>[ ProfileHub ]</h1>
<p>User profile management platform. Register to create your account and customise your public bio.</p>
<div class="box">
  <h3>Register</h3>
  <form action="/register" method="POST">
    <input name="username" placeholder="username" required/><br><br>
    <input name="password" type="password" placeholder="password" required/><br><br>
    <button type="submit">Register</button>
  </form>
</div>
<div class="box">
  <h3>Login</h3>
  <form action="/login" method="POST">
    <input name="username" placeholder="username" required/><br><br>
    <input name="password" type="password" placeholder="password" required/><br><br>
    <button type="submit">Login</button>
  </form>
</div>
<p><a href="/dashboard">Dashboard</a></p>
</body>
</html>
  `);
});

// POST /register
app.post('/register', async (req, res) => {
  const { username, password } = req.body;
  if (!username || !password) {
    return res.status(400).json({ error: 'username and password required' });
  }
  if (users[username]) {
    return res.status(409).json({ error: 'Username already taken' });
  }

  const hash = await bcrypt.hash(password, 10);

  const newUser = {
    id: userIdCounter++,
    username,
    password: hash,
    role: 'user',
    bio: '',
    displayName: '',
    avatarUrl: '',
    preferences: {},
  };
  const { bio, displayName, avatarUrl, preferences } = req.body;
  if (bio !== undefined) newUser.bio = bio;
  if (displayName !== undefined) newUser.displayName = displayName;
  if (avatarUrl !== undefined) newUser.avatarUrl = avatarUrl;
  if (preferences !== undefined) newUser.preferences = preferences;

  users[username] = newUser;

  // JWT is always issued with role:'user' at registration regardless of stored role.
  // The stored role only takes effect on subsequent logins, enforcing a two-step chain.
  const token = jwt.sign({ username, role: 'user' }, JWT_SECRET, { expiresIn: '2h' });
  res
    .cookie('token', token, { httpOnly: true })
    .json({ message: 'Registered', token });
});

// POST /login
app.post('/login', async (req, res) => {
  const { username, password } = req.body;
  const user = users[username];
  if (!user) return res.status(401).json({ error: 'Invalid credentials' });

  const match = await bcrypt.compare(password, user.password);
  if (!match) return res.status(401).json({ error: 'Invalid credentials' });

  const token = jwt.sign({ username, role: user.role }, JWT_SECRET, { expiresIn: '2h' });
  res
    .cookie('token', token, { httpOnly: true })
    .json({ message: 'Logged in', token });
});

// GET /dashboard — requires authentication; FLAG_LOCAL only shown after second exploitation step
app.get('/dashboard', authMiddleware, (req, res) => {
  const isAdmin = req.user.role === 'admin';
  const hasRceDone = !!req.user.rceDone;
  const flagSection = hasRceDone
    ? `<div class="flag"><b>FLAG_LOCAL:</b> ${readFlag('FLAG_LOCAL')}</div>`
    : '';
  res.send(`
<!DOCTYPE html>
<html>
<head><title>Dashboard</title>
<style>body{font-family:monospace;background:#0d0d0d;color:#00ff41;padding:2rem;}
.flag{background:#003300;border:1px solid #0f0;padding:1rem;margin:1rem 0;}
</style></head>
<body>
<h1>Dashboard</h1>
<p>Welcome, <b>${req.user.username}</b> | Role: <b>${req.user.role}</b></p>
${flagSection}
${isAdmin ? '<p><a href="/admin/panel">Admin Panel</a></p>' : ''}
</body>
</html>
  `);
});

// GET /admin/panel — admin only, bio update form
app.get('/admin/panel', authMiddleware, adminMiddleware, (req, res) => {
  const user = users[req.user.username];
  res.send(`
<!DOCTYPE html>
<html>
<head><title>Admin Panel</title>
<style>body{font-family:monospace;background:#0d0d0d;color:#ff4444;padding:2rem;}
textarea{width:100%;height:120px;background:#111;color:#f00;border:1px solid #f00;}
button{background:#330000;color:#f00;border:1px solid #f00;padding:8px 16px;cursor:pointer;}
.output{background:#1a0000;border:1px solid #f00;padding:1rem;margin-top:1rem;white-space:pre-wrap;}
</style></head>
<body>
<h1>[ Admin Panel ]</h1>
<p>Update your profile bio:</p>
<form action="/admin/panel" method="POST">
  <textarea name="bio" placeholder="Enter your profile bio">${user.bio || ''}</textarea><br><br>
  <button type="submit">Save Bio</button>
</form>
${user.bio ? `<div class="output"><b>Rendered output:</b><br>${renderBio(user)}</div>` : ''}
<p><a href="/dashboard">Back to Dashboard</a></p>
</body>
</html>
  `);
});

// POST /admin/panel — renders user-supplied bio template
app.post('/admin/panel', authMiddleware, adminMiddleware, (req, res) => {
  const user = users[req.user.username];
  user.bio = req.body.bio || '';

  const rendered = renderBio(user);

  // If the rendered output contains the proof flag value, mark the account
  // so FLAG_LOCAL is unlocked.
  const proofFlag = readFlag('FLAG_PROOF');
  if (rendered.includes(proofFlag)) {
    user.rceDone = true;
  }

  res.send(`
<!DOCTYPE html>
<html>
<head><title>Admin Panel</title>
<style>body{font-family:monospace;background:#0d0d0d;color:#ff4444;padding:2rem;}
textarea{width:100%;height:120px;background:#111;color:#f00;border:1px solid #f00;}
button{background:#330000;color:#f00;border:1px solid #f00;padding:8px 16px;cursor:pointer;}
.output{background:#1a0000;border:1px solid #f00;padding:1rem;margin-top:1rem;white-space:pre-wrap;}
</style></head>
<body>
<h1>[ Admin Panel ]</h1>
<p>Update your profile bio:</p>
<form action="/admin/panel" method="POST">
  <textarea name="bio" placeholder="Enter your profile bio">${user.bio}</textarea><br><br>
  <button type="submit">Save Bio</button>
</form>
<div class="output"><b>Rendered output:</b><br>${rendered}</div>
<p><a href="/dashboard">Back to Dashboard</a></p>
</body>
</html>
  `);
});

// GET /flag — returns FLAG_LOCAL; requires completed RCE step
app.get('/flag', authMiddleware, rceDoneMiddleware, (req, res) => {
  res.json({ flag: readFlag('FLAG_LOCAL') });
});

// ------------------------------------------------------------------ bio renderer
function renderBio(user) {
  try {
    const _e = { _c: { _s: global } };
    const ctx = { user, _sys: _e, _rt: process };
    const template = Handlebars.compile(user.bio, { noEscape: true });
    return template(ctx);
  } catch (e) {
    return `Template error: ${e.message}`;
  }
}

// ------------------------------------------------------------------ start
app.listen(PORT, () => {
  console.log(`ProfileHub running on port ${PORT}`);
});
