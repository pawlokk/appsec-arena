const express = require('express');
const axios = require('axios');
const vm = require('vm');
const { resolveUrl } = require('../network');

const app = express();
app.use(express.json());

async function executeHtmlJs(htmlContent) {
  const scripts = [];
  const re = /<script[^>]*>([\s\S]*?)<\/script>/gi;
  let m;
  while ((m = re.exec(htmlContent)) !== null) scripts.push(m[1].trim());

  const onloadMatch = htmlContent.match(/<body[^>]+onload=['"]([^'"]+)['"]/i);
  const onloadExpr = onloadMatch ? onloadMatch[1] : null;

  const combinedScript = scripts.join('\n');

  const fetchPolyfill = async function(url, options = {}) {
    const { resolveUrl: rv } = require('../network');
    const resolved = rv(url);
    if (!resolved.reachable) throw new Error(`connect ${resolved.code} ${resolved.address}:${resolved.port || ''}`);
    const method = (options.method || 'GET').toUpperCase();
    const headers = options.headers || {};
    let data = options.body;
    const r = await require('axios')({
      method, url: resolved.url,
      headers: { 'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/79.0.3945.0 Safari/537.36', ...headers },
      data, validateStatus: () => true, timeout: 8000,
    });
    const text = typeof r.data === 'string' ? r.data : JSON.stringify(r.data);
    return {
      ok: r.status >= 200 && r.status < 300,
      status: r.status,
      text: () => Promise.resolve(text),
      json: () => Promise.resolve(typeof r.data === 'object' ? r.data : JSON.parse(text)),
    };
  };

  const sandbox = {
    Promise,
    fetch: fetchPolyfill,
    encodeURIComponent, decodeURIComponent, JSON,
    console: { log: () => {}, error: () => {}, warn: () => {} },
    setTimeout: (fn) => Promise.resolve().then(() => fn()),
    clearTimeout: () => {},
    window: {}, document: {}, location: {},
  };
  vm.createContext(sandbox);

  let onloadCall = '';
  if (onloadExpr) {
    const fnName = onloadExpr.replace(/\(.*\)$/, '').trim();
    onloadCall = `\nif (typeof ${fnName} === 'function') { await ${fnName}(); }`;
  }
  onloadCall += `\nif (typeof window.onload === 'function') { await window.onload(); }`;

  const fullScript = `(async function() { ${combinedScript}${onloadCall} })()`;
  try {
    const p = vm.runInContext(fullScript, sandbox);
    await Promise.race([p, new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 8000))]);
  } catch (_) {}
}

app.get('/api/render', async (req, res) => {
  const url = req.query.url;
  if (!url) {
    return res.status(400).json({ status: 400, statusText: 'Bad Request', errors: [{ field: ['url'], messages: ['"url" must be provided'] }] });
  }

  const resolved = resolveUrl(url);
  if (!resolved.reachable) {
    return res.status(500).json({ error: `connect ${resolved.code} ${resolved.address}:${resolved.port}` });
  }

  try {
    const r = await axios({
      method: 'GET',
      url: resolved.url,
      headers: { 'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/79.0.3945.0 Safari/537.36' },
      validateStatus: () => true,
      timeout: 12000,
    });

    const htmlContent = typeof r.data === 'string' ? r.data : JSON.stringify(r.data);
    await executeHtmlJs(htmlContent);
    return res.status(200).json({ ok: true });
  } catch (e) {
    return res.status(500).json({ error: e.message });
  }
});

module.exports = app;
