/**
 * AppSec Arena — lab-04-node: Axiom
 * Stack: Node.js · Express · EJS · MongoDB (Mongoose)
 *
 * Axiom — internal analytics dashboard
 * Vulnerability: NoSQL injection via MongoDB operator pass-through + EJS SSTI
 */

'use strict';
const express    = require('express');
const session    = require('express-session');
const bodyParser = require('body-parser');
const ejs        = require('ejs');
const mongoose   = require('mongoose');

const app = express();
const FLAG_LOCAL = process.env.FLAG_LOCAL || 'ARENA{LOCAL_dev}';
const FLAG_PROOF = process.env.FLAG_PROOF || 'ARENA{PROOF_dev}';

app.set('view engine', 'ejs');
app.set('views', __dirname + '/views');
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(session({ secret: 'axiom-dev', resave: false, saveUninitialized: false }));

// ── MongoDB schema ────────────────────────────────────────────────────────────
const userSchema = new mongoose.Schema({
  username: String,
  password: String,
  role:     String,
});
const User = mongoose.model('User', userSchema);

async function connectAndSeed() {
  await mongoose.connect('mongodb://127.0.0.1:27017/axiom');
  const count = await User.countDocuments();
  if (count === 0) {
    await User.insertMany([
      { username: 'admin', password: 'adm1nP@ss', role: 'admin' },
      { username: 'alice', password: 'alicepass', role: 'user'  },
      { username: 'bob',   password: 'bobpass',   role: 'user'  },
    ]);
  }
}

// ── Routes ────────────────────────────────────────────────────────────────────
app.get('/', (req, res) => {
  res.send(`<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Axiom</title>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}pre{background:#1a1e2a;padding:1rem;border-radius:8px;overflow:auto}
a{color:#5c8aff}
</style></head>
<body>
<h1>Axiom</h1>
<p>Internal analytics platform. Session-authenticated.</p>
<pre>POST /login                { username, password }
GET  /dashboard            session required
POST /render               admin — render EJS template string
GET  /logout</pre>
<p>Users: alice / alicepass &nbsp; bob / bobpass</p>
<a href="/login">→ Login</a>
</body></html>`);
});

app.post('/login', async (req, res) => {
  const { username, password } = req.body;
  try {
    // Query object passed directly to MongoDB — operator injection possible.
    // e.g. {"username": "admin", "password": {"$ne": ""}} logs in as admin.
    const user = await User.findOne({ username, password });
    if (!user) {
      return res.status(401).send('Invalid credentials. <a href="/login">Back</a>');
    }
    req.session.user = { username: user.username, role: user.role };
    res.redirect('/dashboard');
  } catch (err) {
    res.status(500).send('Error: ' + err.message);
  }
});

app.get('/login', (req, res) => {
  res.send(`<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Axiom — Login</title>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;display:flex;
     align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#13161e;border:1px solid #1f2535;border-radius:12px;padding:2rem;width:300px}
h2{color:#5c8aff;margin:0 0 1.5rem}
input{width:100%;background:#0a0c11;border:1px solid #1f2535;border-radius:5px;
      padding:8px 10px;color:#d4d8e8;font-family:monospace;margin-bottom:.75rem;box-sizing:border-box}
button{width:100%;background:#1a2440;color:#5c8aff;border:1px solid #233060;
       border-radius:5px;padding:9px;font-family:monospace;cursor:pointer}
</style></head>
<body><div class="box">
<h2>Axiom</h2>
<form method="POST" action="/login">
  <input name="username" placeholder="username" autocomplete="off">
  <input name="password" type="password" placeholder="password">
  <button>Login</button>
</form></div></body></html>`);
});

app.get('/dashboard', (req, res) => {
  if (!req.session.user) return res.redirect('/login');
  const { username, role } = req.session.user;
  const flagHtml = role === 'admin'
    ? `<div style="background:#1a2518;border:1px solid #1e3d2d;border-radius:8px;padding:.75rem 1rem;color:#3ecf8e;margin:1rem 0">${FLAG_LOCAL}</div>`
    : '';
  res.send(`<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Axiom</title>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}a{color:#5c8aff}
.card{background:#13161e;border:1px solid #1f2535;border-radius:8px;padding:1.5rem;margin:1rem 0}
textarea{width:100%;background:#0a0c11;border:1px solid #1f2535;border-radius:5px;
         padding:8px;color:#d4d8e8;font-family:monospace;height:100px;box-sizing:border-box}
button{background:#1a2440;color:#5c8aff;border:1px solid #233060;border-radius:5px;
       padding:7px 16px;font-family:monospace;cursor:pointer}
</style></head>
<body>
<h1>Dashboard</h1>
<p>Welcome <strong>${username}</strong> (${role})</p>
${flagHtml}
${role === 'admin' ? `<div class="card">
<h3>Template Renderer</h3>
<form method="POST" action="/render">
  <textarea name="template" placeholder="Enter EJS template..."></textarea><br><br>
  <button>Render</button>
</form></div>` : ''}
<a href="/logout">Logout</a>
</body></html>`);
});

app.post('/render', (req, res) => {
  if (!req.session.user || req.session.user.role !== 'admin') {
    return res.status(403).send('Admin only');
  }
  const template = req.body.template || '';
  try {
    const output = ejs.render(template, { flag_proof: FLAG_PROOF });
    res.send(`<pre style="background:#1a1e2a;padding:1rem;border-radius:8px;color:#d4d8e8">${output}</pre>
    <a href="/dashboard" style="color:#5c8aff">← Back</a>`);
  } catch (err) {
    res.status(500).send('Template error: ' + err.message);
  }
});

app.get('/logout', (req, res) => {
  req.session.destroy(() => res.redirect('/login'));
});

// ── Boot ──────────────────────────────────────────────────────────────────────
connectAndSeed()
  .then(() => {
    app.listen(5000, () => console.log('[axiom] running on :5000'));
  })
  .catch(err => {
    console.error('[axiom] DB connection failed:', err.message);
    process.exit(1);
  });
