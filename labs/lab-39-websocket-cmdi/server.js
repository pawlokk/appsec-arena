'use strict';

const express = require('express');
const http = require('http');
const WebSocket = require('ws');
const { exec } = require('child_process');
const Database = require('better-sqlite3');
const { v4: uuidv4 } = require('uuid');

const app = express();
const server = http.createServer(app);
const wss = new WebSocket.Server({ server, path: '/ws' });

// In-memory SQLite
const db = new Database(':memory:');
db.exec(`
  CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    akey TEXT NOT NULL
  );
`);

// Seed admin from environment — never a literal password
const adminPassword = process.env.ADMIN_PASSWORD || uuidv4();
const defaultAkey = uuidv4();
db.prepare("INSERT INTO users (username, password, akey) VALUES (?, ?, ?)").run('admin', adminPassword, defaultAkey);

app.use(express.json());
app.use(express.urlencoded({ extended: false }));

// Cookie-based session (manual, no library)
const sessions = {}; // sessionId -> { userId, username, akey }

function getSession(req) {
  const raw = req.headers.cookie || '';
  const match = raw.match(/session=([^;]+)/);
  if (!match) return null;
  return sessions[match[1]] || null;
}

// GET /api/info — unauthenticated, returns basic service info
app.get('/api/info', (req, res) => {
  res.json({ service: 'WebSocket Monitoring Dashboard' });
});

// POST /api/login
app.post('/api/login', (req, res) => {
  const { username, password } = req.body;
  if (!username || !password) return res.status(400).json({ error: 'Missing fields' });
  const user = db.prepare("SELECT * FROM users WHERE username=? AND password=?").get(username, password);
  if (!user) return res.status(401).json({ error: 'Invalid credentials' });
  const sid = uuidv4();
  sessions[sid] = { userId: user.id, username: user.username, akey: user.akey };
  res.setHeader('Set-Cookie', `session=${sid}; Path=/; HttpOnly`);
  res.json({ ok: true, username: user.username, akey: user.akey });
});

// POST /api/logout
app.post('/api/logout', (req, res) => {
  const raw = req.headers.cookie || '';
  const match = raw.match(/session=([^;]+)/);
  if (match) delete sessions[match[1]];
  res.setHeader('Set-Cookie', 'session=; Path=/; Max-Age=0');
  res.json({ ok: true });
});

// GET /callback?d=... — exfil receiver
app.get('/callback', (req, res) => {
  const d = req.query.d || '';
  console.log(`[EXFIL] Received data: ${d}`);
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.status(200).send('ok');
});

// GET /dashboard — requires valid session
app.get('/dashboard', (req, res) => {
  const sess = getSession(req);
  if (!sess) return res.redirect('/login');
  res.setHeader('Content-Type', 'text/html');
  res.send(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Monitoring Dashboard</title>
  <style>
    body { font-family: monospace; background: #0d0d0d; color: #00ff41; margin: 0; padding: 20px; }
    h1 { color: #00ff41; border-bottom: 1px solid #00ff41; }
    input, button { background: #111; color: #00ff41; border: 1px solid #00ff41; padding: 6px 12px; font-family: monospace; }
    button { cursor: pointer; }
    #results { margin-top: 20px; padding: 10px; border: 1px solid #333; min-height: 40px; }
    .info { color: #888; font-size: 0.85em; margin-top: 10px; }
    #wslog { margin-top: 10px; padding: 10px; border: 1px solid #333; min-height: 60px; max-height: 200px; overflow-y: auto; }
    #wsstatus { color: #ff4444; }
  </style>
</head>
<body>
  <h1>[ Monitoring Dashboard ]</h1>
  <p class="info">FLAG: ${process.env.FLAG_LOCAL || ''}</p>

  <div id="mainSection">
    <h2>WebSocket Diagnostics</h2>
    <p class="info">Commands: <code>status</code> | <code>ping &lt;target&gt;</code></p>
    <input type="text" id="wsCmd" placeholder="cmd (e.g. ping 8.8.8.8)" style="width:300px">
    <button onclick="sendWsCmd()">Send</button>
    <span id="wsstatus"> disconnected</span>
    <div id="wslog"></div>
  </div>

  <script>
    let ws = null;

    connectWs();

    function connectWs() {
      const proto = location.protocol === 'https:' ? 'wss' : 'ws';
      ws = new WebSocket(proto + '://' + location.host + '/ws');
      ws.onopen = function() {
        document.getElementById('wsstatus').style.color = '#00ff41';
        document.getElementById('wsstatus').textContent = ' connected';
      };
      ws.onmessage = function(e) {
        const log = document.getElementById('wslog');
        log.textContent += e.data + '\\n';
        log.scrollTop = log.scrollHeight;
      };
      ws.onclose = function() {
        document.getElementById('wsstatus').style.color = '#ff4444';
        document.getElementById('wsstatus').textContent = ' disconnected';
      };
    }

    function sendWsCmd() {
      if (!ws || ws.readyState !== WebSocket.OPEN) { alert('Not connected'); return; }
      const raw = document.getElementById('wsCmd').value.trim();
      const parts = raw.split(' ');
      const cmd = parts[0];
      const target = parts.slice(1).join(' ');
      if (cmd === 'ping') {
        ws.send(JSON.stringify({cmd: 'ping', target: target}));
      } else if (cmd === 'status') {
        ws.send(JSON.stringify({cmd: 'status'}));
      } else {
        ws.send(JSON.stringify({cmd: raw}));
      }
    }
  </script>
</body>
</html>`);
});

// Root redirect
app.get('/', (req, res) => res.redirect('/dashboard'));

// WebSocket message handler
wss.on('connection', (ws, req) => {
  // Auth check: require a valid session cookie or ?token query param
  let sess = null;
  const raw = req.headers.cookie || '';
  const cookieMatch = raw.match(/session=([^;]+)/);
  if (cookieMatch) {
    sess = sessions[cookieMatch[1]] || null;
  }
  if (!sess) {
    const urlParams = new URL(req.url, 'http://localhost').searchParams;
    const token = urlParams.get('token');
    if (token) sess = sessions[token] || null;
  }
  if (!sess) {
    ws.send('ERROR: authentication required');
    ws.close(1008, 'Unauthorized');
    return;
  }

  ws.send('CONNECTED WebSocket diagnostics ready. Commands: status, ping <target>');

  ws.on('message', (raw) => {
    let msg;
    try { msg = JSON.parse(raw); } catch(e) { ws.send('ERROR: invalid JSON'); return; }

    const cmd = msg.cmd || '';

    if (cmd === 'status') {
      ws.send(`STATUS uptime=${process.uptime().toFixed(1)}s pid=${process.pid}`);
      return;
    }

    if (cmd === 'ping') {
      const target = msg.target || '127.0.0.1';
      const shellCmd = `/bin/sh -c "su -c 'ping -c1 ${target}' monitor"`;
      ws.send(`EXEC running: ping -c1 ${target}`);
      exec(shellCmd, { timeout: 5000 }, (err, stdout, stderr) => {
        const out = (stdout + stderr).trim();
        ws.send(`OUTPUT\n${out || '(no output)'}`);
      });
      return;
    }

    ws.send(`UNKNOWN command: ${cmd}. Try: status, ping`);
  });

  ws.on('close', () => {});
});

const PORT = 3000;
server.listen(PORT, () => {
  console.log(`Lab-39 listening on port ${PORT}`);
});
