'use strict';
const mysql  = require('mysql2/promise');
const http   = require('http');
const { JSDOM, ResourceLoader, CookieJar, toughCookie } = require('jsdom');

const DB        = { host: '127.0.0.1', user: 'mailuser', password: 'mailpass', database: 'maildb' };
const BASE_PORT = 5000;
const BASE_URL  = `http://127.0.0.1:${BASE_PORT}`;

let pool;
let adminMailSession = null;
let adminSession     = null;

async function init() {
  pool = await mysql.createPool({ ...DB, waitForConnections: true, connectionLimit: 5 });
}

async function ensureAdminSessions() {
  const [rows] = await pool.query('SELECT id FROM accounts WHERE is_admin=1 LIMIT 1');
  if (!rows.length) return;
  const adminId = rows[0].id;
  const tok  = require('crypto').randomBytes(32).toString('hex');
  const atok = require('crypto').randomBytes(32).toString('hex');
  await pool.query('DELETE FROM sessions WHERE account_id=?',       [adminId]);
  await pool.query('DELETE FROM admin_sessions WHERE account_id=?', [adminId]);
  await pool.query('INSERT INTO sessions (token, account_id) VALUES (?,?)',       [tok,  adminId]);
  await pool.query('INSERT INTO admin_sessions (token, account_id) VALUES (?,?)', [atok, adminId]);
  adminMailSession = tok;
  adminSession     = atok;
}

// Raw HTTP helper — routes all bot requests back to the app
function httpReq(method, rawPath, body, cookieStr, contentType) {
  return new Promise((resolve) => {
    let path = rawPath;
    if (path.startsWith('http')) {
      try { const u = new URL(path); path = u.pathname + (u.search || ''); } catch {}
    }
    const bodyBuf = body ? Buffer.from(body, 'binary') : null;
    const opts = {
      hostname: '127.0.0.1',
      port:     BASE_PORT,
      path,
      method:   method.toUpperCase(),
      headers: {
        'Cookie':             cookieStr,
        'Content-Type':       contentType || 'application/x-www-form-urlencoded',
        'X-Requested-With':   'XMLHttpRequest',
        ...(bodyBuf ? { 'Content-Length': bodyBuf.length } : {}),
      },
    };
    const req = http.request(opts, (res) => {
      const chunks = [];
      res.on('data', c => chunks.push(c));
      res.on('end', () => resolve({ status: res.statusCode, body: Buffer.concat(chunks).toString() }));
    });
    req.on('error', () => resolve({ status: 0, body: '' }));
    if (bodyBuf) req.write(bodyBuf);
    req.end();
  });
}

// Fetch the rendered view page as admin then let jsdom execute it
async function processMessage(msgId) {
  const cookies = `hexmail6=${adminMailSession}; hexmail6_admin=${adminSession}`;

  // Fetch the real rendered page — this is what the victim browser would see
  const page = await httpReq('GET', `/mail/view.php?id=${msgId}`, null, cookies, null);
  const fs = require('fs');
  const scriptMatch = page.body.match(/<script[\s>]/i);
  fs.appendFileSync('/tmp/bot.log', `msg=${msgId} status=${page.status} hasScript=${!!scriptMatch}\n${page.body.slice(-300)}\n---\n`);
  if (page.status !== 200) return;

  // Resource loader that routes all sub-requests (scripts, XHR, fetch) back to the app
  class BotResourceLoader extends ResourceLoader {
    fetch(url, options) {
      let path = url;
      try { const u = new URL(url); path = u.pathname + (u.search || ''); } catch {}
      return new Promise((resolve, reject) => {
        const opts = {
          hostname: '127.0.0.1',
          port:     BASE_PORT,
          path,
          method:   'GET',
          headers:  { 'Cookie': cookies },
        };
        const req = http.request(opts, (res) => {
          const chunks = [];
          res.on('data', c => chunks.push(c));
          res.on('end', () => resolve(Buffer.concat(chunks)));
        });
        req.on('error', reject);
        req.end();
      });
    }
  }

  // Populate a cookie jar so jsdom XHRs automatically carry admin sessions
  const cookieJar = new CookieJar();
  const origin = `http://127.0.0.1:${BASE_PORT}`;
  await cookieJar.setCookie(`hexmail6=${adminMailSession}; Path=/`, origin);
  await cookieJar.setCookie(`hexmail6_admin=${adminSession}; Path=/`, origin);

  const vc = new (require('jsdom').VirtualConsole)();
  const fslog = require('fs');
  vc.on('jsdomError', e => fslog.appendFileSync('/tmp/bot.log', `[jsdomError] ${e.message}\n`));
  vc.on('error', e => fslog.appendFileSync('/tmp/bot.log', `[vcError] ${e}\n`));

  const dom = new JSDOM(page.body, {
    url:             `${BASE_URL}/mail/view.php?id=${msgId}`,
    runScripts:      'dangerously',
    resources:       new BotResourceLoader(),
    pretendToBeVisual: true,
    virtualConsole:  vc,
    cookieJar,
    beforeParse(window) {
      const fs2 = require('fs');
      window.onerror = (msg, src, line, col, err) => fs2.appendFileSync('/tmp/bot.log', `[onerror] ${msg} at ${src}:${line}\n`);

      // Replace XHR entirely with a Node.js http-backed implementation
      // jsdom forbids setting Cookie via setRequestHeader, so we bypass its XHR completely
      window.XMLHttpRequest = class NodeXHR {
        constructor() {
          this.readyState = 0;
          this.status = 0;
          this.responseText = '';
          this._method = 'GET';
          this._url = '';
          this._headers = { Cookie: cookies };
        }
        open(method, url) {
          let path = url;
          if (url && url.startsWith('http')) {
            try { const u = new URL(url); path = u.pathname + (u.search || ''); } catch {}
          }
          this._method = method.toUpperCase();
          this._url = path;
          this.readyState = 1;
        }
        setRequestHeader(k, v) {
          if (k.toLowerCase() !== 'cookie') this._headers[k] = v;
        }
        send(body) {
          const self = this;
          const bodyBuf = body ? Buffer.from(body, 'binary') : null;
          const opts = {
            hostname: '127.0.0.1', port: BASE_PORT,
            path: self._url, method: self._method,
            headers: { ...self._headers, ...(bodyBuf ? { 'Content-Length': bodyBuf.length } : {}) },
          };
          const req = http.request(opts, (res) => {
            const chunks = [];
            res.on('data', c => chunks.push(c));
            res.on('end', () => {
              self.status = res.statusCode;
              self.responseText = Buffer.concat(chunks).toString();
              self.readyState = 4;
              if (self.onreadystatechange) self.onreadystatechange();
            });
          });
          req.on('error', () => { self.readyState = 4; if (self.onreadystatechange) self.onreadystatechange(); });
          if (bodyBuf) req.write(bodyBuf);
          req.end();
        }
      };

      // Patch fetch to also carry cookies and route to app
      window.fetch = (url, opts = {}) => {
        let path = url;
        if (typeof url === 'string' && url.startsWith('http')) {
          try { const u = new URL(url); path = u.pathname + (u.search || ''); } catch {}
        }
        opts.headers = { ...(opts.headers || {}), Cookie: cookies };
        return new Promise((resolve) => {
          const bodyBuf = opts.body ? Buffer.from(opts.body, 'binary') : null;
          const reqOpts = {
            hostname: '127.0.0.1', port: BASE_PORT, path,
            method: (opts.method || 'GET').toUpperCase(),
            headers: { ...opts.headers, ...(bodyBuf ? { 'Content-Length': bodyBuf.length } : {}) },
          };
          const req = http.request(reqOpts, (res) => {
            const chunks = [];
            res.on('data', c => chunks.push(c));
            res.on('end', () => resolve({ ok: res.statusCode < 400, status: res.statusCode, text: () => Promise.resolve(Buffer.concat(chunks).toString()) }));
          });
          req.on('error', () => resolve({ ok: false, status: 0 }));
          if (bodyBuf) req.write(bodyBuf);
          req.end();
        });
      };
    },
  });

  // Give scripts time to execute and async XHRs to complete
  await new Promise(r => setTimeout(r, 3000));
  dom.window.close();
}

async function tick() {
  try {
    if (!adminMailSession) await ensureAdminSessions();
    const [msgs] = await pool.query(
      "SELECT id FROM messages WHERE recipient_email='admin@hexmail.local' AND read_at IS NULL ORDER BY received_at ASC LIMIT 20"
    );
    for (const msg of msgs) {
      await processMessage(msg.id);
      await pool.query('UPDATE messages SET read_at=NOW() WHERE id=?', [msg.id]);
    }
  } catch (e) {
    adminMailSession = null;
    setTimeout(ensureAdminSessions, 5000);
  }
}

init().then(() => {
  ensureAdminSessions();
  setTimeout(tick, 3000);
  setInterval(tick, 8000);
}).catch(() => process.exit(1));
