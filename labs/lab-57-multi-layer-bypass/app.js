'use strict';

const express = require('express');
const rateLimit = require('express-rate-limit');
const jwt = require('jsonwebtoken');
const bodyParser = require('body-parser');
const crypto = require('crypto');
const { execSync, exec } = require('child_process');
const fs = require('fs');

const app = express();
app.use(bodyParser.json());

// Generate RS256 key pair at startup
const { privateKey, publicKey } = crypto.generateKeyPairSync('rsa', {
  modulusLength: 2048,
  publicKeyEncoding: { type: 'spki', format: 'pem' },
  privateKeyEncoding: { type: 'pkcs8', format: 'pem' }
});

// Derive JWK from public key for JWKS endpoint
const pubKeyObj = crypto.createPublicKey(publicKey);
const jwkObj = pubKeyObj.export({ format: 'jwk' });

// Global settings object used for diagnostics
const settings = {};

// Rate limiter
const limiter = rateLimit({
  windowMs: 60 * 1000,
  max: 5,
  keyGenerator: (req) => {
    return req.headers['x-forwarded-for'] || req.connection.remoteAddress;
  },
  standardHeaders: true,
  legacyHeaders: false
});

app.use(limiter);

// JWKS endpoint
app.get('/.well-known/jwks.json', (req, res) => {
  res.json({
    keys: [
      Object.assign({ kty: jwkObj.kty, use: 'sig', alg: 'RS256', kid: '1' }, jwkObj)
    ]
  });
});

// Auth middleware
function requireAuth(req, res, next) {
  const authHeader = req.headers['authorization'];
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'Missing token' });
  }
  const token = authHeader.slice(7);
  try {
    const decoded = jwt.verify(token, publicKey);
    req.user = decoded;
    next();
  } catch (err) {
    return res.status(401).json({ error: 'Invalid token' });
  }
}

function requireAdmin(req, res, next) {
  if (!req.user || req.user.role !== 'admin') {
    return res.status(403).json({ error: 'Forbidden' });
  }
  next();
}

// Login endpoint - issues RS256 token for valid credentials
app.post('/api/login', (req, res) => {
  const { username, password } = req.body || {};
  if (username === 'user' && password === process.env.USER_PASSWORD) {
    const token = jwt.sign({ sub: 'user', role: 'user' }, privateKey, { algorithm: 'RS256', expiresIn: '1h' });
    return res.json({ token });
  }
  return res.status(401).json({ error: 'Invalid credentials' });
});

// Profile endpoint
app.get('/api/profile', requireAuth, requireAdmin, (req, res) => {
  const flagLocal = fs.existsSync('/flag_local.txt') ? fs.readFileSync('/flag_local.txt', 'utf8').trim() : process.env.FLAG_LOCAL || 'FLAG_LOCAL{placeholder}';
  res.json({
    user: req.user,
    flag: flagLocal
  });
});

// Config merge helper
function merge(target, source) {
  for (let k in source) {
    if (typeof source[k] === 'object' && source[k] !== null) {
      target[k] = {};
      merge(target[k], source[k]);
    } else {
      target[k] = source[k];
    }
  }
}

// Admin merge endpoint
app.post('/admin/merge', requireAuth, requireAdmin, (req, res) => {
  const config = req.body && req.body.config;
  if (!config || typeof config !== 'object') {
    return res.status(400).json({ error: 'config object required' });
  }
  merge(settings, config);
  res.json({ status: 'merged' });
});

// Admin diagnostic endpoint
app.get('/admin/diagnostic', requireAuth, requireAdmin, (req, res) => {
  const cmd = settings.diagnosticCommand || 'uptime';
  exec(cmd, { timeout: 5000 }, (err, stdout, stderr) => {
    res.json({ output: stdout || stderr || (err && err.message) || '' });
  });
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});
