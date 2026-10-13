'use strict';

const express = require('express');
const cors = require('cors');
const session = require('express-session');
const cookieParser = require('cookie-parser');
const yaml = require('js-yaml');
const fs = require('fs');

const app = express();

app.use(cors({ origin: true, credentials: true }));

app.use(cookieParser());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(session({
  secret: 'workflow-secret-2024',
  resave: false,
  saveUninitialized: false,
  cookie: { httpOnly: false, sameSite: false }
}));

const users = {};

function getFlag(name) {
  try {
    return fs.readFileSync(`/flag_${name}.txt`, 'utf8').trim();
  } catch (e) {
    return process.env[`FLAG_${name.toUpperCase()}`] || `ARENA{${name.toUpperCase()}_placeholder}`;
  }
}

// Seed admin from environment at startup
(function seedAdmin() {
  const adminPass = process.env.ADMIN_PASSWORD;
  if (adminPass) {
    users['admin'] = { password: adminPass, role: 'admin' };
  }
})();

app.get('/', (req, res) => {
  res.send(`<!DOCTYPE html>
<html>
<head><title>WorkflowForge - Automation Platform</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
  h1 { color: #333; }
  .card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
  input, textarea { width: 100%; padding: 8px; margin: 5px 0; box-sizing: border-box; }
  button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
  button:hover { background: #0056b3; }
  .status { padding: 10px; border-radius: 4px; margin: 10px 0; }
  .success { background: #d4edda; color: #155724; }
  .error { background: #f8d7da; color: #721c24; }
  pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>
</head>
<body>
<h1>WorkflowForge</h1>
<p>Automate your deployment workflows with YAML-based pipeline definitions.</p>

<div class="card">
  <h2>Register</h2>
  <input id="reg-user" placeholder="Username" />
  <input id="reg-pass" type="password" placeholder="Password" />
  <button onclick="register()">Register</button>
  <div id="reg-status"></div>
</div>

<div class="card">
  <h2>Login</h2>
  <input id="login-user" placeholder="Username" />
  <input id="login-pass" type="password" placeholder="Password" />
  <button onclick="login()">Login</button>
  <div id="login-status"></div>
</div>

<div class="card">
  <h2>Run Workflow (Admin Only)</h2>
  <textarea id="workflow-yaml" rows="10" placeholder="Paste YAML workflow definition here...">name: my-workflow
steps:
  - type: echo
    message: Hello World
</textarea>
  <button onclick="runWorkflow()">Run Workflow</button>
  <div id="workflow-status"></div>
  <pre id="workflow-result"></pre>
</div>

<div class="card">
  <h2>Who Am I</h2>
  <button onclick="whoami()">Check Session</button>
  <pre id="whoami-result"></pre>
</div>

<script>
async function register() {
  const u = document.getElementById('reg-user').value;
  const p = document.getElementById('reg-pass').value;
  const r = await fetch('/api/register', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    credentials: 'include',
    body: JSON.stringify({username: u, password: p})
  });
  const d = await r.json();
  document.getElementById('reg-status').innerHTML = '<div class="status ' + (r.ok ? 'success' : 'error') + '">' + JSON.stringify(d) + '</div>';
}

async function login() {
  const u = document.getElementById('login-user').value;
  const p = document.getElementById('login-pass').value;
  const r = await fetch('/api/login', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    credentials: 'include',
    body: JSON.stringify({username: u, password: p})
  });
  const d = await r.json();
  document.getElementById('login-status').innerHTML = '<div class="status ' + (r.ok ? 'success' : 'error') + '">' + JSON.stringify(d) + '</div>';
}

async function runWorkflow() {
  const yamlDef = document.getElementById('workflow-yaml').value;
  const r = await fetch('/api/workflow/run', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    credentials: 'include',
    body: JSON.stringify({workflow: yamlDef})
  });
  const d = await r.json();
  document.getElementById('workflow-result').textContent = JSON.stringify(d, null, 2);
}

async function whoami() {
  const r = await fetch('/api/whoami', {credentials: 'include'});
  const d = await r.json();
  document.getElementById('whoami-result').textContent = JSON.stringify(d, null, 2);
}
</script>
</body>
</html>`);
});

app.get('/api/config', (req, res) => {
  if (!req.session.user) {
    return res.status(401).json({ error: 'Authentication required' });
  }
  res.json({
    apiVersion: '1.0',
    staticKey: process.env.DEPLOY_KEY,
    debug: false
  });
});

app.post('/api/register', (req, res) => {
  const { username, password } = req.body;
  if (!username || !password) return res.status(400).json({ error: 'Username and password required' });
  if (users[username]) return res.status(409).json({ error: 'User already exists' });
  users[username] = { password, role: 'user' };
  req.session.user = username;
  req.session.role = 'user';
  res.json({ success: true, username, role: 'user' });
});

app.post('/api/login', (req, res) => {
  const { username, password } = req.body;
  if (!username || !password) return res.status(400).json({ error: 'Username and password required' });
  const user = users[username];
  if (!user || user.password !== password) return res.status(401).json({ error: 'Invalid credentials' });
  req.session.user = username;
  req.session.role = user.role;
  res.json({ success: true, username, role: user.role });
});

app.post('/admin/setup', (req, res) => {
  const { key } = req.body;
  const deployKey = process.env.DEPLOY_KEY;
  if (!deployKey) return res.status(503).json({ error: 'Service unavailable' });
  if (key !== deployKey) {
    return res.status(403).json({ error: 'Invalid key' });
  }
  if (!users['admin']) {
    users['admin'] = { password: process.env.ADMIN_PASSWORD || 'admin-' + Math.random().toString(36).slice(2), role: 'admin' };
  } else {
    users['admin'].role = 'admin';
  }
  req.session.user = 'admin';
  req.session.role = 'admin';
  res.json({ success: true, message: 'Admin session established', username: 'admin' });
});

app.get('/api/whoami', (req, res) => {
  if (!req.session.user) {
    return res.status(401).json({ error: 'Not authenticated' });
  }
  const response = {
    username: req.session.user,
    role: req.session.role
  };
  if (req.session.role === 'admin') {
    response.flag = getFlag('local');
  }
  res.json(response);
});

app.post('/api/workflow/run', (req, res) => {
  if (!req.session.user) {
    return res.status(401).json({ error: 'Authentication required' });
  }
  if (req.session.role !== 'admin') {
    return res.status(403).json({ error: 'Admin access required' });
  }

  const { workflow } = req.body;
  if (!workflow) return res.status(400).json({ error: 'Workflow definition required' });

  let workflowDef;
  try {
    workflowDef = yaml.load(workflow);
  } catch (e) {
    return res.status(400).json({ error: 'Invalid YAML', details: e.message });
  }

  if (!workflowDef || !workflowDef.steps || !Array.isArray(workflowDef.steps)) {
    return res.status(400).json({ error: 'Workflow must have a steps array' });
  }

  const results = [];
  const context = { env: process.env, outputs: {} };

  for (const step of workflowDef.steps) {
    try {
      if (step.type === 'echo') {
        results.push({ type: 'echo', output: step.message || '' });
      } else if (step.type === 'script') {
        const fn = new Function('context', step.code);
        const output = fn(context);
        const scriptResult = { type: 'script', output: output };
        if (output !== undefined && output !== null) {
          scriptResult.flag_proof = getFlag('proof');
        }
        results.push(scriptResult);
      } else if (step.type === 'set') {
        context.outputs[step.key] = step.value;
        results.push({ type: 'set', key: step.key, value: step.value });
      } else {
        results.push({ type: step.type, output: `Unknown step type: ${step.type}` });
      }
    } catch (e) {
      results.push({ type: step.type, error: e.message });
    }
  }

  res.json({
    success: true,
    workflow: workflowDef.name || 'unnamed',
    results
  });
});

app.post('/api/logout', (req, res) => {
  req.session.destroy();
  res.json({ success: true });
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`WorkflowForge running on port ${PORT}`);
});
