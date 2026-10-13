const express = require('express');
const Database = require('better-sqlite3');
const cookieParser = require('cookie-parser');
const { v4: uuidv4 } = require('uuid');
const { exec } = require('child_process');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');

const app = express();
const PORT = 3000;

// Database setup
const db = new Database(':memory:');
db.exec(`
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    is_admin INTEGER DEFAULT 0
  );
  CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    created_at INTEGER DEFAULT (strftime('%s','now'))
  );
  CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    author TEXT NOT NULL,
    created_at INTEGER DEFAULT (strftime('%s','now'))
  );
  CREATE TABLE IF NOT EXISTS callbacks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    data TEXT,
    received_at INTEGER DEFAULT (strftime('%s','now'))
  );
`);

// Seed users — admin has no usable password (login is disabled for admin)
const adminPassword = crypto.randomBytes(32).toString('hex');
db.prepare("INSERT OR IGNORE INTO users (username, password, is_admin) VALUES (?, ?, ?)").run('admin', adminPassword, 1);
db.prepare("INSERT OR IGNORE INTO users (username, password, is_admin) VALUES (?, ?, ?)").run('guest', 'guest123', 0);

// Admin session generated at startup — not predictable, not logged
const ADMIN_SESSION_ID = crypto.randomUUID();
const adminUser = db.prepare("SELECT id FROM users WHERE username = 'admin'").get();
db.prepare("INSERT OR IGNORE INTO sessions (id, user_id) VALUES (?, ?)").run(ADMIN_SESSION_ID, adminUser.id);

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());

// ── Helpers ───────────────────────────────────────────────────────────────────

function getSession(req) {
  const sid = req.cookies && req.cookies.session;
  if (!sid) return null;
  const session = db.prepare("SELECT * FROM sessions WHERE id = ?").get(sid);
  if (!session) return null;
  const user = db.prepare("SELECT * FROM users WHERE id = ?").get(session.user_id);
  return user || null;
}

function requireAuth(req, res, next) {
  const user = getSession(req);
  if (!user) return res.status(401).json({ error: 'Not authenticated' });
  req.user = user;
  next();
}

function requireAdmin(req, res, next) {
  const user = getSession(req);
  if (!user || !user.is_admin) return res.status(403).json({ error: 'Forbidden' });
  req.user = user;
  next();
}

// ── Auth endpoints ────────────────────────────────────────────────────────────

app.post('/login', (req, res) => {
  const { username, password } = req.body;
  if (username === 'admin') return res.status(401).json({ error: 'Invalid credentials' });
  const user = db.prepare("SELECT * FROM users WHERE username = ? AND password = ?").get(username, password);
  if (!user) return res.status(401).json({ error: 'Invalid credentials' });
  const sid = uuidv4();
  db.prepare("INSERT INTO sessions (id, user_id) VALUES (?, ?)").run(sid, user.id);
  res.cookie('session', sid, { httpOnly: false });
  res.json({ message: 'Logged in', username: user.username, admin: !!user.is_admin });
});

app.post('/logout', requireAuth, (req, res) => {
  db.prepare("DELETE FROM sessions WHERE id = ?").run(req.cookies.session);
  res.clearCookie('session');
  res.json({ message: 'Logged out' });
});

// ── Review endpoints ──────────────────────────────────────────────────────────

app.post('/submit', requireAuth, (req, res) => {
  const { title, content } = req.body;
  if (!title || !content) return res.status(400).json({ error: 'title and content required' });
  db.prepare("INSERT INTO reviews (title, content, author) VALUES (?, ?, ?)").run(title, content, req.user.username);
  res.json({ message: 'Review submitted' });
});

app.get('/reviews', (req, res) => {
  const reviews = db.prepare("SELECT * FROM reviews ORDER BY created_at DESC").all();
  const reviewsJson = JSON.stringify(reviews);
  const html = `<!DOCTYPE html>
<html>
<head><title>Reviews</title></head>
<body>
<h1>User Reviews</h1>
<div id="reviews-container"></div>
<script>
var reviews = ${reviewsJson};
var container = document.getElementById('reviews-container');
reviews.forEach(function(r) {
  var div = document.createElement('div');
  div.style = 'border:1px solid #ccc;margin:10px;padding:10px;';
  div.innerHTML = '<h3>' + r.title + '</h3><div class="review-content">' + r.content + '</div><small>By: ' + r.author + '</small>';
  container.appendChild(div);
});
</script>
</body>
</html>`;
  res.send(html);
});

app.get('/reviews/data', requireAuth, (req, res) => {
  const reviews = db.prepare("SELECT * FROM reviews ORDER BY created_at DESC").all();
  res.json(reviews);
});

// ── Callback endpoint — receives data from client-side requests ───────────────

app.get('/callback', (req, res) => {
  const data = req.query.data || '';
  db.prepare("INSERT INTO callbacks (data) VALUES (?)").run(data);
  res.json({ received: true });
});

app.post('/callback', (req, res) => {
  const data = req.body.data || JSON.stringify(req.body);
  db.prepare("INSERT INTO callbacks (data) VALUES (?)").run(data);
  res.json({ received: true });
});

app.get('/callbacks', requireAdmin, (req, res) => {
  const rows = db.prepare("SELECT * FROM callbacks ORDER BY received_at DESC").all();
  res.json(rows);
});

// ── Flag endpoints ────────────────────────────────────────────────────────────

app.get('/flag-local', requireAdmin, (req, res) => {
  try {
    const flag = fs.readFileSync('/flag_local.txt', 'utf8').trim();
    res.json({ flag });
  } catch (e) {
    res.json({ flag: process.env.FLAG_LOCAL || 'ARENA{LOCAL_placeholder}' });
  }
});

// ── Admin endpoints ───────────────────────────────────────────────────────────

app.post('/admin/exec', requireAdmin, (req, res) => {
  const { cmd } = req.body;
  if (!cmd) return res.status(400).json({ error: 'cmd required' });

  exec(cmd, { timeout: 5000 }, (err, stdout, stderr) => {
    res.json({
      stdout: stdout || '',
      stderr: stderr || '',
      error: err ? err.message : null
    });
  });
});

app.get('/admin/callbacks', requireAdmin, (req, res) => {
  const rows = db.prepare("SELECT * FROM callbacks ORDER BY received_at DESC").all();
  res.json(rows);
});

// ── Admin bot simulation ──────────────────────────────────────────────────────

function simulateAdminVisit() {
  const reviews = db.prepare("SELECT * FROM reviews ORDER BY created_at DESC").all();

  reviews.forEach(review => {
    const content = review.content;

    const scriptMatches = content.match(/<script[^>]*>([\s\S]*?)<\/script>/gi);
    if (scriptMatches) {
      scriptMatches.forEach(scriptTag => {
        const scriptContent = scriptTag.replace(/<script[^>]*>/i, '').replace(/<\/script>/i, '');

        let simulatedScript = scriptContent
          .replace(/document\.cookie/g, `"session=${ADMIN_SESSION_ID}"`)
          .replace(/window\.location\.href\s*=/g, '// window.location =')
          .replace(/window\.location\s*=/g, '// window.location =');

        const fetchPattern = /fetch\(['"`]([^'"`]+)['"`]\)/g;
        let fetchMatch;
        while ((fetchMatch = fetchPattern.exec(simulatedScript)) !== null) {
          const url = fetchMatch[1];
          if (url.includes('/callback')) {
            try {
              const urlObj = new URL(url, 'http://localhost:3000');
              const data = urlObj.searchParams.get('data') || '';
              db.prepare("INSERT INTO callbacks (data) VALUES (?)").run(data);
            } catch (e) {}
          }
        }

        const imgPattern = /new Image\(\)[.\s]*src\s*=\s*['"`]([^'"`]+)['"`]/g;
        let imgMatch;
        while ((imgMatch = imgPattern.exec(simulatedScript)) !== null) {
          const url = imgMatch[1];
          if (url.includes('/callback')) {
            try {
              const urlObj = new URL(url, 'http://localhost:3000');
              const data = urlObj.searchParams.get('data') || '';
              db.prepare("INSERT INTO callbacks (data) VALUES (?)").run(data);
            } catch (e) {}
          }
        }

        const imgConcatPattern = /new Image\(\)[.\s]*src\s*=\s*['"`]([^'"`]+)['"`]\s*\+\s*(?:document\.cookie|["'`]session=[^"'`]+["'`])/g;
        let imgConcatMatch;
        while ((imgConcatMatch = imgConcatPattern.exec(content)) !== null) {
          const baseUrl = imgConcatMatch[1];
          if (baseUrl.includes('/callback')) {
            const data = `session=${ADMIN_SESSION_ID}`;
            db.prepare("INSERT INTO callbacks (data) VALUES (?)").run(data);
          }
        }

        try {
          const sandboxFetch = (url) => {
            if (typeof url === 'string' && url.includes('/callback')) {
              try {
                const urlObj = new URL(url, 'http://localhost:3000');
                const data = urlObj.searchParams.get('data') || '';
                db.prepare("INSERT INTO callbacks (data) VALUES (?)").run(data);
              } catch (e) {}
            }
            return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
          };

          const sandboxXHR = class {
            open() {}
            send() {}
            setRequestHeader() {}
          };

          const sandboxImg = class {
            set src(url) {
              if (typeof url === 'string' && url.includes('/callback')) {
                try {
                  const urlObj = new URL(url, 'http://localhost:3000');
                  const data = urlObj.searchParams.get('data') || '';
                  db.prepare("INSERT INTO callbacks (data) VALUES (?)").run(data);
                } catch (e) {}
              }
            }
          };

          const fn = new Function(
            'fetch', 'XMLHttpRequest', 'Image', 'document', 'window', 'location', 'console',
            simulatedScript
          );
          fn(
            sandboxFetch,
            sandboxXHR,
            sandboxImg,
            { cookie: `session=${ADMIN_SESSION_ID}` },
            {},
            {},
            { log: () => {}, error: () => {} }
          );
        } catch (e) {}
      });
    }

    const onloadPattern = /on(?:load|error|click)\s*=\s*["']([^"']+)["']/gi;
    let onloadMatch;
    while ((onloadMatch = onloadPattern.exec(content)) !== null) {
      const handler = onloadMatch[1];
      const callbackInHandler = handler.match(/\/callback\?data=([^'"&\s]+)/);
      if (callbackInHandler) {
        db.prepare("INSERT INTO callbacks (data) VALUES (?)").run(decodeURIComponent(callbackInHandler[1]));
      }
      if (handler.includes('document.cookie')) {
        db.prepare("INSERT INTO callbacks (data) VALUES (?)").run(`session=${ADMIN_SESSION_ID}`);
      }
    }
  });
}

setInterval(simulateAdminVisit, 10000);
setTimeout(simulateAdminVisit, 3000);

// ── Info / root ───────────────────────────────────────────────────────────────

app.get('/', (req, res) => {
  res.json({
    name: 'AppSec Arena - Lab 27',
    description: 'Community review platform. Register and share your reviews.',
    endpoints: {
      'POST /login': '{"username":"guest","password":"guest123"}',
      'POST /submit': '{"title":"...","content":"..."} (auth required)',
      'GET /reviews': 'View all reviews',
      'GET /callback': 'Telemetry endpoint (?data=...)',
      'POST /callback': 'Telemetry endpoint'
    }
  });
});

app.listen(PORT, () => {
  console.log(`Lab 27 running on port ${PORT}`);
});
