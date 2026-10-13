const express = require('express');
const axios = require('axios');
const { resolveUrl } = require('../network');

const app = express();
app.use(express.json());

const DHEADERS = {
  'X-Powered-By': 'Directus',
  'Vary': 'Origin',
  'Access-Control-Allow-Credentials': 'true',
  'Access-Control-Expose-Headers': 'Content-Range',
};

app.use((req, res, next) => {
  Object.entries(DHEADERS).forEach(([k, v]) => res.setHeader(k, v));
  next();
});

app.get('/files*', (req, res) => {
  res.status(403).json({ errors: [{ message: 'You don\'t have permission to access the "directus_files" collection.', extensions: { code: 'FORBIDDEN' } }] });
});

app.get('/users*', (req, res) => {
  res.status(403).json({ errors: [{ message: 'You don\'t have permission to access the "directus_users" collection.', extensions: { code: 'FORBIDDEN' } }] });
});

app.get('*', (req, res) => {
  res.status(403).json({ errors: [{ message: 'You don\'t have permission to access this.', extensions: { code: 'FORBIDDEN' } }] });
});

app.post('/users/invite', (req, res) => {
  res.status(400).json({ errors: [{ message: '"email" is required', extensions: { code: 'INVALID_PAYLOAD' } }] });
});

app.post('/files/import', async (req, res) => {
  const body = req.body;
  if (!body || !body.url) {
    return res.status(400).json({ errors: [{ message: '"url" is required', extensions: { code: 'INVALID_PAYLOAD' } }] });
  }

  const resolved = resolveUrl(body.url);

  if (!resolved.reachable) {
    if (resolved.code === 'ECONNREFUSED') {
      return res.status(500).json({ errors: [{ message: `connect ECONNREFUSED ${resolved.address}:${resolved.port}`, extensions: { code: 'INTERNAL_SERVER_ERROR' } }] });
    }
    if (resolved.code === 'EHOSTUNREACH') {
      return res.status(500).json({ errors: [{ message: `connect EHOSTUNREACH ${resolved.address}:${resolved.port}`, extensions: { code: 'INTERNAL_SERVER_ERROR' } }] });
    }
  }

  try {
    const r = await axios({
      method: 'GET',
      url: resolved.url,
      validateStatus: () => true,
      timeout: 12000,
      responseType: 'text',
    });

    return res.status(403).json({ errors: [{ message: 'You don\'t have permission to access this.', extensions: { code: 'FORBIDDEN' } }] });
  } catch (e) {
    if (e.code === 'ECONNRESET' || (e.message && e.message.includes('socket hang up'))) {
      return res.status(500).json({ errors: [{ message: 'socket hang up', extensions: { code: 'INTERNAL_SERVER_ERROR' } }] });
    }
    if (e.code === 'ERR_HTTP_INVALID_STATUS_LINE' || (e.message && e.message.includes('Parse Error'))) {
      return res.status(500).json({ errors: [{ message: 'Parse Error: Expected HTTP/', extensions: { code: 'INTERNAL_SERVER_ERROR' } }] });
    }
    if (e.code === 'ETIMEDOUT' || e.code === 'ECONNABORTED') {
      return res.status(500).json({ errors: [{ message: 'Request timeout', extensions: { code: 'INTERNAL_SERVER_ERROR' } }] });
    }
    return res.status(500).json({ errors: [{ message: e.message || 'Internal Server Error', extensions: { code: 'INTERNAL_SERVER_ERROR' } }] });
  }
});

module.exports = app;
