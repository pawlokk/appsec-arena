const express = require('express');
const axios = require('axios');
const busboy = require('busboy');
const state = require('../state');
const { resolveUrl } = require('../network');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, '../public')));

app.use((req, res, next) => {
  res.setHeader('Server', 'kong/2.2.1');
  next();
});

async function proxyTo(targetUrl, req, res) {
  const t0 = Date.now();
  try {
    const headers = { ...req.headers };
    delete headers['host'];
    const r = await axios({
      method: req.method,
      url: targetUrl,
      headers,
      data: req.body && Object.keys(req.body).length ? req.body : undefined,
      validateStatus: () => true,
      timeout: 15000,
      responseType: 'arraybuffer',
    });
    const latency = Date.now() - t0;
    res.setHeader('X-Kong-Proxy-Latency', '0');
    res.setHeader('X-Kong-Upstream-Latency', String(latency));
    res.setHeader('Via', 'kong/2.2.1');
    Object.entries(r.headers).forEach(([k, v]) => {
      if (!['transfer-encoding', 'connection'].includes(k.toLowerCase())) res.setHeader(k, v);
    });
    res.status(r.status).send(Buffer.from(r.data));
  } catch (e) {
    res.status(502).json({ message: 'Bad gateway', detail: e.message });
  }
}

app.post('/cdn/files', (req, res) => {
  const bb = busboy({ headers: req.headers });
  bb.on('file', (fieldname, stream, info) => {
    const chunks = [];
    stream.on('data', (d) => chunks.push(d));
    stream.on('end', () => {
      state.uploadedAssets.set(info.filename, { data: Buffer.concat(chunks), mime: info.mimeType });
    });
  });
  bb.on('close', () => res.json({ ok: true }));
  req.pipe(bb);
});

app.get('/cdn/files/:name', (req, res) => {
  const entry = state.uploadedAssets.get(req.params.name);
  if (!entry) return res.status(404).json({ message: 'Not found' });
  res.setHeader('Content-Type', entry.mime || 'application/octet-stream');
  res.send(entry.data);
});

app.get('/beacon/collect', (req, res) => {
  const data = req.query.data || '';
  state.eventLog.push(data);
  res.json({ ok: true });
});

app.post('/beacon/collect', (req, res) => {
  const data = (req.body && req.body.data) || '';
  state.eventLog.push(data);
  res.json({ ok: true });
});

app.get('/api/beacon/results', (req, res) => {
  res.json(state.eventLog);
});

app.options('*', (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, apikey');
  res.status(204).send();
});

app.all(['/files/import', '/files*', '/users*'], (req, res) => {
  proxyTo(`http://127.0.0.1:8055${req.path}`, req, res);
});

app.get('/render*', async (req, res) => {
  const apikey = req.query.apikey || req.headers['apikey'];
  if (!apikey) {
    res.setHeader('WWW-Authenticate', 'Key realm="kong"');
    res.setHeader('X-Kong-Response-Latency', '0');
    return res.status(401).json({ message: 'No API key found in request' });
  }
  if (apikey !== state.RENDER_API_KEY) {
    res.setHeader('WWW-Authenticate', 'Key realm="kong"');
    res.setHeader('X-Kong-Response-Latency', '0');
    return res.status(401).json({ message: 'Invalid authentication credentials' });
  }
  const forwardQuery = new URLSearchParams(req.query);
  forwardQuery.delete('apikey');
  const qs = forwardQuery.toString();
  proxyTo(`http://127.0.0.1:9000/api/render${qs ? '?' + qs : ''}`, req, res);
});

app.use(async (req, res, next) => {
  const svcName = state.routeToService.get(req.path);
  if (!svcName) return next();

  const plugins = state.servicePlugins.get(svcName) || [];
  const preFn = plugins.find((p) => p.name === 'pre-function');

  if (preFn && preFn.config && Array.isArray(preFn.config.access) && preFn.config.access.length > 0) {
    const luaCode = preFn.config.access[0];
    const tmpFile = `/tmp/kong_${Date.now()}.lua`;
    try {
      fs.writeFileSync(tmpFile, luaCode);
      const output = execSync(`lua5.3 ${tmpFile}`, { timeout: 5000 }).toString();
      try { fs.unlinkSync(tmpFile); } catch (_) {}

      if (state.httpLogEndpoint.url) {
        axios.post(state.httpLogEndpoint.url, { method: req.method, path: req.path, headers: req.headers, body: req.body }).catch(() => {});
      }

      res.setHeader('Content-Type', 'text/plain');
      return res.status(200).send(output);
    } catch (e) {
      try { fs.unlinkSync(tmpFile); } catch (_) {}
      return res.status(500).json({ message: e.message });
    }
  }

  const svc = state.services.get(svcName);
  if (svc) {
    return proxyTo(svc.url + req.path, req, res);
  }
  next();
});

app.use((req, res) => {
  res.setHeader('X-Kong-Response-Latency', '0');
  res.status(404).json({ message: 'no Route matched with those values' });
});

app.use((req, res, next) => {
  if (state.httpLogEndpoint.url) {
    axios.post(state.httpLogEndpoint.url, { method: req.method, path: req.path, headers: req.headers }).catch(() => {});
  }
  next();
});

module.exports = app;
