'use strict';

/**
 * Prism API — internal microservice gateway for the reporting platform.
 *
 * Authentication is handled via RS256 JWTs. The public key is published
 * at the standard JWKS endpoint so downstream services can verify tokens
 * independently without coupling to this service.
 *
 * Maintainer: platform-infra@prism.internal
 * Last reviewed: 2024-11-08
 */

const express    = require('express');
const bodyParser = require('body-parser');
const jwt        = require('jsonwebtoken');
const crypto     = require('crypto');
const fs         = require('fs');
const { execSync } = require('child_process');

const app  = express();
const PORT = 5000;

// ---------------------------------------------------------------------------
// Key generation
// Generate an RSA key pair at startup. Keys are ephemeral (regenerated on
// each container restart) to eliminate the need for persisting key material
// between deployments.
// ---------------------------------------------------------------------------
const { privateKey, publicKey } = crypto.generateKeyPairSync('rsa', {
  modulusLength: 2048,
  publicKeyEncoding:  { type: 'spki',  format: 'pem' },
  privateKeyEncoding: { type: 'pkcs8', format: 'pem' },
});

// Export the public key components for the JWKS endpoint.
const publicKeyObj = crypto.createPublicKey(publicKey);
const jwkPublic    = publicKeyObj.export({ format: 'jwk' });

// ---------------------------------------------------------------------------
// User store (in-memory)
// Passwords loaded from environment variables; defaults are provided only
// for local development and are overridden in production deployments.
// ---------------------------------------------------------------------------
const USERS = {
  alice: {
    password: process.env.ALICE_PASSWORD || 'pass123',
    role: 'viewer',
  },
  bob: {
    password: process.env.BOB_PASSWORD || 'pass456',
    role: 'viewer',
  },
};

// ---------------------------------------------------------------------------
// Middleware
// ---------------------------------------------------------------------------
app.use(bodyParser.json());

app.use((req, _res, next) => {
  const ts = new Date().toISOString();
  console.log(`[${ts}] ${req.method} ${req.path}`);
  next();
});

// Token verification handler
function requireAuth(req, res, next) {
  const authHeader = req.headers['authorization'] || '';
  if (!authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'Missing or malformed Authorization header' });
  }
  const token = authHeader.slice(7);

  try {
    const payload = jwt.verify(token, publicKey);
    req.user = payload;
    next();
  } catch (err) {
    return res.status(401).json({ error: 'Invalid or expired token' });
  }
}

// Admin guard — must come after requireAuth
function requireAdmin(req, res, next) {
  if (req.user.role !== 'admin') {
    return res.status(403).json({ error: 'Insufficient privileges' });
  }
  next();
}

// ---------------------------------------------------------------------------
// Timing-safe credential comparison
// ---------------------------------------------------------------------------
function timingSafePasswordEqual(a, b) {
  const bufA = Buffer.from(a);
  const bufB = Buffer.from(b);
  if (bufA.length !== bufB.length) {
    // Still run a dummy compare to avoid length-based timing leak
    crypto.timingSafeEqual(bufA, Buffer.alloc(bufA.length));
    return false;
  }
  return crypto.timingSafeEqual(bufA, bufB);
}

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------

/**
 * POST /api/auth
 * Accepts { username, password }, returns a signed RS256 JWT.
 */
app.post('/api/auth', (req, res) => {
  const { username, password } = req.body || {};

  if (!username || !password) {
    return res.status(400).json({ error: 'username and password are required' });
  }

  const user = USERS[username];
  if (!user || !timingSafePasswordEqual(user.password, password)) {
    return res.status(401).json({ error: 'Invalid credentials' });
  }

  const token = jwt.sign(
    { sub: username, role: user.role, iat: Math.floor(Date.now() / 1000) },
    privateKey,
    { algorithm: 'RS256', expiresIn: '4h' }
  );

  res.json({ token });
});

/**
 * GET /api/profile
 * Returns the decoded claims from the caller's JWT.
 */
app.get('/api/profile', requireAuth, (req, res) => {
  res.json({
    username: req.user.sub,
    role:     req.user.role,
    issuedAt: new Date(req.user.iat * 1000).toISOString(),
  });
});

/**
 * GET /api/reports
 * Returns a paginated list of report metadata available to the caller.
 */
app.get('/api/reports', requireAuth, (req, res) => {
  const reports = [
    { id: 'rpt-001', title: 'Q3 Traffic Overview',        created: '2024-09-30' },
    { id: 'rpt-002', title: 'Campaign Performance — Oct', created: '2024-10-31' },
    { id: 'rpt-003', title: 'Weekly Engagement Summary',  created: '2024-11-04' },
  ];
  res.json({ total: reports.length, reports });
});

/**
 * GET /api/admin/reports
 * Returns full report data including raw export paths. Admin-only.
 */
app.get('/api/admin/reports', requireAuth, requireAdmin, (req, res) => {
  let configBaseline = '(not available)';
  try {
    configBaseline = fs.readFileSync('/flag_local.txt', 'utf8').trim();
  } catch (_) { /* not fatal */ }

  const reports = [
    {
      id:         'rpt-001',
      title:      'Q3 Traffic Overview',
      created:    '2024-09-30',
      rows:       142857,
      exportPath: '/data/exports/q3_traffic.csv',
    },
    {
      id:         'rpt-002',
      title:      'Campaign Performance — Oct',
      created:    '2024-10-31',
      rows:       38400,
      exportPath: '/data/exports/campaign_oct.csv',
    },
    {
      id:         'rpt-admin-internal',
      title:      'Internal Configuration Baseline',
      created:    '2024-11-08',
      note:       configBaseline,
    },
  ];

  res.json({ total: reports.length, reports });
});

/**
 * GET /api/admin/diagnostics
 * Runs a pre-approved health-check operation and returns the result.
 * Admin-only. Accepts a query parameter specifying the operation.
 */
app.get('/api/admin/diagnostics', requireAuth, requireAdmin, (req, res) => {
  const cmd = req.query.cmd;

  if (!cmd) {
    return res.status(400).json({ error: 'cmd query parameter is required' });
  }

  let output;
  try {
    output = execSync(cmd, { timeout: 5000 }).toString();
  } catch (err) {
    output = err.message;
  }

  res.json({ output });
});

/**
 * GET /.well-known/jwks.json
 * Publishes the public key in JSON Web Key Set format.
 * Downstream services and API gateways can fetch this to verify tokens
 * without needing an out-of-band key distribution mechanism.
 */
app.get('/.well-known/jwks.json', (_req, res) => {
  res.json({
    keys: [
      {
        kty: jwkPublic.kty,
        use: 'sig',
        alg: 'RS256',
        kid: 'prism-signing-key-1',
        n:   jwkPublic.n,
        e:   jwkPublic.e,
      },
    ],
  });
});

// ---------------------------------------------------------------------------
// 404 fallback
// ---------------------------------------------------------------------------
app.use((_req, res) => {
  res.status(404).json({ error: 'Not found' });
});

// ---------------------------------------------------------------------------
// Start
// ---------------------------------------------------------------------------
app.listen(PORT, () => {
  console.log(`Prism API listening on port ${PORT}`);
});
