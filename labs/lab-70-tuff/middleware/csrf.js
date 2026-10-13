'use strict';

const crypto = require('crypto');

function generateToken(req) {
  if (!req.session.csrfToken) {
    req.session.csrfToken = crypto.randomBytes(24).toString('hex');
  }
  return req.session.csrfToken;
}

function validateToken(req, res, next) {
  const t = req.body._csrf || req.headers['x-csrf-token'];
  if (!t || t !== req.session.csrfToken) {
    return res.status(403).json({ error: 'invalid CSRF token' });
  }
  next();
}

module.exports = { generateToken, validateToken };
