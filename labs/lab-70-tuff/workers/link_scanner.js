'use strict';

const http = require('http');
const https = require('https');
const { Pool } = require('pg');
const fs = require('fs');

const pool = new Pool({
  connectionString: process.env.DATABASE_URL || 'postgresql://tuff:tuffpass@localhost/tuff'
});

const ADMIN_SESSION = process.env.SCANNER_SESSION || '';
const POLL_MS = parseInt(process.env.LINK_SCANNER_INTERVAL_MS || '15000', 10);
const LOG_FILE = '/tmp/tuff_link_scanner.log';

function lg(msg) {
  const ln = `[${new Date().toISOString()}] ${msg}\n`;
  process.stdout.write(ln);
  try { fs.appendFileSync(LOG_FILE, ln); } catch (e) {}
}

function fu(url, sc, mt, fb) {
  return new Promise((resolve) => {
    const p = new URL(url);
    const lib = p.protocol === 'https:' ? https : http;
    const z = { 'User-Agent': 'TuffLinkScanner/1.0' };
    if (sc) z['Cookie'] = `connect.sid=${sc}`;
    if (mt) z['X-Monitor-Token'] = mt;
    const opts = {
      hostname: p.hostname,
      port:     p.port || (p.protocol === 'https:' ? 443 : 80),
      path:     p.pathname + (p.search || ''),
      method:   'GET',
      headers:  z,
      timeout:  8000,
    };
    const rq = lib.request(opts, (rs) => {
      let o = '';
      rs.on('data', (chunk) => { o += chunk; });
      rs.on('end', () => resolve({ status: rs.statusCode, body: fb ? o : o.substring(0, 200) }));
    });
    rq.on('error', (e) => resolve({ status: 0, body: e.message }));
    rq.on('timeout', () => { rq.destroy(); resolve({ status: 0, body: 'timeout' }); });
    rq.end();
  });
}

async function pb(ru, st) {
  if (!st) return;
  let pu;
  try { pu = new URL(ru); } catch (e) { return; }

  const bp = pu.searchParams.get('build');
  if (!bp) return;

  let bs = '';
  try {
    const r = await fu(bp, '', '', true);
    bs = r.body;
  } catch (e) { return; }

  if (!bs.includes('/beacon/collect')) return;

  const mu = pu.protocol + '//' + pu.host + '/monitoring';
  let mb = '';
  try {
    const r = await fu(mu, '', st, true);
    mb = r.body;
  } catch (e) { return; }

  const km = mb.match(/id="monitor-key">([^<]+)</) ||
             mb.match(/monitor-key[^>]*>([^<]+)</i);
  if (!km) return;
  const ex = km[1].trim();

  const cu = pu.protocol + '//' + pu.host +
    '/beacon/collect?data=' + encodeURIComponent(ex);
  try { await fu(cu, '', '', false); } catch (e) {}
  lg('xss-sim: exfiltrated monitor key via ' + ru);
}

async function pq() {
  let rows;
  try {
    const r = await pool.query(
      "SELECT id, url FROM review_queue WHERE status = 'pending' ORDER BY created_at LIMIT 3"
    );
    rows = r.rows;
  } catch (e) {
    lg('db error: ' + e.message);
    return;
  }

  for (const v of rows) {
    lg(`visiting: ${v.url}`);
    let st = 0;
    let sn = '';
    try {
      const r = await fu(v.url, ADMIN_SESSION, ADMIN_SESSION, false);
      st = r.status;
      sn = r.body;
    } catch (e) {
      sn = e.message;
    }
    pb(v.url, ADMIN_SESSION).catch(() => {});

    try {
      await pool.query(
        "UPDATE review_queue SET status = 'processed', processed_at = NOW() WHERE id = $1",
        [v.id]
      );
      await pool.query(
        'INSERT INTO review_results (queue_id, http_status, response_snippet, reviewed_at) VALUES ($1, $2, $3, NOW())',
        [v.id, st, sn]
      );
    } catch (e) {
      lg('update error: ' + e.message);
    }
  }
}

async function wd() {
  for (let i = 0; i < 20; i++) {
    try {
      await pool.query('SELECT 1');
      return;
    } catch (e) {
      await new Promise(r => setTimeout(r, 2000));
    }
  }
  throw new Error('DB not available');
}

async function main() {
  lg('link scanner starting');
  await wd();
  lg('connected to DB');
  setInterval(() => {
    pq().catch(e => lg('poll error: ' + e.message));
  }, POLL_MS);
}

main().catch(e => {
  lg('fatal: ' + e.message);
  process.exit(1);
});
