'use strict';

const config = {
  trustedOriginPattern: 'https://slag.internal',
};

function vo(o) {
  if (!o) return false;
  const re = new RegExp(config.trustedOriginPattern);
  return re.test(o);
}

function corsValidator(req, res, next) {
  const o = req.headers.origin;
  if (o && vo(o)) {
    res.setHeader('Access-Control-Allow-Origin', o);
    res.setHeader('Access-Control-Allow-Credentials', 'true');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  }
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
}

module.exports = { corsValidator, validateOrigin: vo };
