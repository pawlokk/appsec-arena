'use strict';

const express = require('express');
const bodyParser = require('body-parser');
const ldap = require('ldapjs');
const jwt = require('jsonwebtoken');
const fs = require('fs');
const { exec } = require('child_process');

const JWT_SECRET = process.env.JWT_SECRET || (() => { throw new Error('JWT_SECRET env var is required'); })();
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || (() => { throw new Error('ADMIN_PASSWORD env var is required'); })();
const LDAP_PORT = 1389;
const APP_PORT = 3000;

// ── In-process LDAP server ─────────────────────────────────────────────────
const ldapServer = ldap.createServer();

const entries = {
  'uid=admin,dc=lattice,dc=io': {
    dn: 'uid=admin,dc=lattice,dc=io',
    attributes: {
      uid: 'admin',
      userPassword: ADMIN_PASSWORD,
      role: 'admin',
      objectClass: ['top', 'person']
    }
  },
  'uid=user,dc=lattice,dc=io': {
    dn: 'uid=user,dc=lattice,dc=io',
    attributes: {
      uid: 'user',
      userPassword: process.env.USER_PASSWORD || (() => { throw new Error('USER_PASSWORD env var is required'); })(),
      role: 'user',
      objectClass: ['top', 'person']
    }
  }
};

ldapServer.bind('dc=lattice,dc=io', (req, res, next) => {
  res.end();
  return next();
});

ldapServer.search('dc=lattice,dc=io', (req, res, next) => {
  Object.values(entries).forEach((entry) => {
    if (req.filter.matches(entry.attributes)) {
      res.send({
        dn: entry.dn,
        attributes: entry.attributes
      });
    }
  });
  res.end();
  return next();
});

ldapServer.listen(LDAP_PORT, '127.0.0.1', () => {
  console.log(`LDAP server listening on ldap://127.0.0.1:${LDAP_PORT}`);
});

// ── Express app ───────────────────────────────────────────────────────────
const app = express();
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

function ldapSearch(filter) {
  return new Promise((resolve, reject) => {
    const client = ldap.createClient({ url: `ldap://127.0.0.1:${LDAP_PORT}` });
    const results = [];

    client.bind('', '', (err) => {
      if (err) {
        client.destroy();
        return reject(err);
      }
      client.search('dc=lattice,dc=io', { filter, scope: 'sub' }, (err, searchRes) => {
        if (err) {
          client.destroy();
          return reject(err);
        }
        searchRes.on('searchEntry', (entry) => {
          results.push(entry.object);
        });
        searchRes.on('error', (e) => {
          client.destroy();
          reject(e);
        });
        searchRes.on('end', () => {
          client.destroy();
          resolve(results);
        });
      });
    });
  });
}

function requireAuth(req, res, next) {
  const auth = req.headers['authorization'] || '';
  const token = auth.replace(/^Bearer\s+/i, '');
  if (!token) return res.status(401).json({ error: 'No token' });
  try {
    req.user = jwt.verify(token, JWT_SECRET);
    next();
  } catch (e) {
    return res.status(401).json({ error: 'Invalid token' });
  }
}

function requireAdmin(req, res, next) {
  requireAuth(req, res, () => {
    if (req.user.role !== 'admin') return res.status(403).json({ error: 'Admin only' });
    next();
  });
}

// ── Routes ────────────────────────────────────────────────────────────────

app.get('/', (req, res) => {
  res.send(`<!DOCTYPE html>
<html>
<head><title>Lattice HR Portal</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 400px; margin: 80px auto; background: #f4f4f4; }
  h2 { color: #333; }
  input { display:block; width:100%; margin:8px 0; padding:8px; box-sizing:border-box; }
  button { padding:10px 20px; background:#2c6fad; color:#fff; border:none; cursor:pointer; width:100%; }
  .msg { color: red; margin-top: 10px; }
</style>
</head>
<body>
  <h2>Lattice HR Portal</h2>
  <p>Employee authentication system</p>
  <input id="user" placeholder="Username" />
  <input id="pass" type="password" placeholder="Password" />
  <button onclick="doLogin()">Login</button>
  <div class="msg" id="msg"></div>
  <script>
    async function doLogin() {
      const username = document.getElementById('user').value;
      const password = document.getElementById('pass').value;
      const r = await fetch('/login', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({username, password})
      });
      const d = await r.json();
      if (d.token) {
        document.getElementById('msg').style.color = 'green';
        document.getElementById('msg').innerText = 'Logged in as ' + d.role + '. Token: ' + d.token;
      } else {
        document.getElementById('msg').innerText = d.error || 'Login failed';
      }
    }
  </script>
</body>
</html>`);
});

app.post('/login', async (req, res) => {
  const username = req.body.username || '';
  const password = req.body.password || '';

  const filter = '(&(uid=' + username + ')(userPassword=' + password + '))';

  try {
    const results = await ldapSearch(filter);
    if (results && results.length > 0) {
      const user = results[0];
      const token = jwt.sign(
        { uid: user.uid, role: user.role || 'user' },
        JWT_SECRET,
        { expiresIn: '1h' }
      );
      return res.json({ success: true, token, role: user.role || 'user' });
    } else {
      return res.status(401).json({ error: 'Invalid credentials' });
    }
  } catch (e) {
    return res.status(500).json({ error: 'LDAP error', detail: e.message });
  }
});

app.get('/api/me', requireAuth, (req, res) => {
  res.json({ user: req.user });
});

app.get('/admin', requireAdmin, (req, res) => {
  let flag = '';
  try { flag = fs.readFileSync('/flag_local.txt', 'utf8').trim(); } catch (e) { flag = process.env.FLAG_LOCAL || ''; }
  res.json({ message: 'Welcome to the Lattice admin panel.', flag });
});

// ── Admin exec endpoint (L2 challenge — FLAG_PROOF gate) ──────────────────
//
// Intended exploit chain:
//   1. LDAP injection on /login to obtain an admin JWT.
//   2. Read /etc/maintenance_token via command injection in the `cmd` parameter
//      (whitelisted prefix check is bypassable with shell metacharacters, e.g.
//       cmd = "uptime; cat /etc/maintenance_token").
//   3. POST /api/admin/run with the recovered token to receive FLAG_PROOF.
//
// The whitelist prefix check ("uptime", "df", "free") is intentionally weak —
// it only checks that `cmd` *starts with* one of the allowed strings, so a
// payload like "uptime; cat /flag_proof.txt" bypasses it.

const ALLOWED_CMD_PREFIXES = ['uptime', 'df', 'free'];

app.post('/api/admin/run', requireAdmin, (req, res) => {
  const { cmd, token } = req.body || {};

  if (!cmd) return res.status(400).json({ error: 'cmd is required' });

  // Validate maintenance token — player must supply it (obtained via cmd injection)
  let expectedToken = '';
  try { expectedToken = fs.readFileSync('/etc/maintenance_token', 'utf8').trim(); } catch (e) { /* ignore */ }
  if (!token || token !== expectedToken) {
    return res.status(403).json({ error: 'Invalid or missing maintenance token' });
  }

  // Intentionally vulnerable whitelist: only the *prefix* is checked
  const allowed = ALLOWED_CMD_PREFIXES.some((p) => cmd.startsWith(p));
  if (!allowed) {
    return res.status(400).json({ error: `Command not whitelisted. Allowed prefixes: ${ALLOWED_CMD_PREFIXES.join(', ')}` });
  }

  // Vulnerable: cmd is passed directly to the shell
  exec(cmd, { timeout: 5000 }, (err, stdout, stderr) => {
    if (err) return res.status(500).json({ error: 'Execution failed', detail: err.message });

    // If the player managed to read /flag_proof.txt via injection, include flag
    let flagProof = null;
    if (stdout.includes('ARENA{') || stdout.includes(process.env.FLAG_PROOF || '__NOFLAG__')) {
      try { flagProof = fs.readFileSync('/flag_proof.txt', 'utf8').trim(); } catch (e) { flagProof = process.env.FLAG_PROOF || ''; }
    }

    res.json({ output: stdout, stderr, flag_proof: flagProof });
  });
});

app.listen(APP_PORT, () => {
  console.log(`Lattice HR Portal listening on port ${APP_PORT}`);
});
