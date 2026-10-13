'use strict';

const express = require('express');
const crypto = require('crypto');
const http = require('http');
const net = require('net');
const path = require('path');
const fs = require('fs');
const WebSocket = require('ws');
const GuacamoleLite = require('guacamole-lite');

const PORT = parseInt(process.env.PORT || '5000', 10);
const TEMPLATING_ENGINE = process.env.TEMPLATING_ENGINE || 'hbs';
const TOKEN_SECRET = process.env.TOKEN_SECRET || 'Ch1ps_S3cur3_T0k3n_K3y_32Bytes!!';

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

const hbs = require('express-handlebars');
app.engine('hbs', hbs.engine({ extname: '.hbs', defaultLayout: false }));
app.engine('ejs', require('ejs').renderFile);
app.engine('pug', require('pug').__express);

if (TEMPLATING_ENGINE === 'ejs') {
  app.set('view engine', 'ejs');
} else if (TEMPLATING_ENGINE === 'pug') {
  app.set('view engine', 'pug');
} else {
  app.set('view engine', 'hbs');
}
app.set('views', path.join(__dirname, 'views'));
app.use(express.static(path.join(__dirname, 'public')));

const tokenKey = Buffer.from(TOKEN_SECRET, 'utf8').slice(0, 32);

app.post('/token', (req, res) => {
  const connection = req.body;
  if (!connection || !connection.connection) {
    return res.status(400).json({ error: 'connection object required' });
  }
  const iv = crypto.randomBytes(16);
  const cipher = crypto.createCipheriv('aes-256-cbc', tokenKey, iv);
  let encrypted = cipher.update(JSON.stringify(connection), 'ascii', 'binary');
  encrypted += cipher.final('binary');
  const tokenObj = {
    iv: iv.toString('base64'),
    value: Buffer.from(encrypted, 'binary').toString('base64')
  };
  const token = Buffer.from(JSON.stringify(tokenObj)).toString('base64');
  res.json({ token });
});

app.get('/rdp', (req, res) => {
  const token = req.query.token || '';
  res.render('rdp', { token });
});

app.get('/rdp-ejs', (req, res) => {
  const token = req.query.token || '';
  res.render('rdp.ejs', { token });
});

app.get('/rdp-hbs', (req, res) => {
  const token = req.query.token || '';
  res.render('rdp.hbs', { token });
});

app.get('/rdp-pug', (req, res) => {
  const token = req.query.token || '';
  res.render('rdp.pug', { token });
});

const FILES_BASE = path.join(__dirname, 'files');

app.get('/files', (req, res) => {
  const filename = req.query.filename;
  if (!filename) {
    return res.status(400).json({ error: 'filename parameter required' });
  }
  const filePath = path.join(FILES_BASE, filename);
  if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
    return res.status(404).json({ error: 'not found' });
  }
  res.sendFile(filePath);
});

const collectedData = [];

app.options('/beacon/*', (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  res.status(204).send();
});

app.get('/beacon/collect', (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  const data = req.query.data || '';
  collectedData.push(data);
  res.json({ ok: true });
});

app.post('/beacon/collect', (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  const data = (req.body && req.body.data) || '';
  collectedData.push(data);
  res.json({ ok: true });
});

app.get('/api/beacon/results', (req, res) => {
  res.json(collectedData);
});

const submitQueue = [];

app.post('/submit', (req, res) => {
  const { token } = req.body;
  if (!token) return res.status(400).json({ error: 'token required' });
  submitQueue.push(token);
  res.json({ ok: true, queued: submitQueue.length });
});

app.get('/api/status', (req, res) => {
  res.json({ status: 'ok', engine: TEMPLATING_ENGINE });
});

const server = http.createServer(app);

const guacdListener = net.createServer((socket) => {
  socket.on('error', () => {});
});
guacdListener.listen(4822, '127.0.0.1');

const guacServer = new GuacamoleLite(
  { server, path: '/guaclite' },
  { host: '127.0.0.1', port: 4822 },
  {
    crypt: {
      cypher: 'AES-256-CBC',
      key: TOKEN_SECRET.slice(0, 32)
    },
    log: { level: 0 }
  }
);

guacServer.on('error', () => {});

setInterval(() => {
  if (submitQueue.length === 0) return;
  const token = submitQueue.shift();
  const wsUrl = `ws://127.0.0.1:${PORT}/guaclite?token=${encodeURIComponent(token)}`;
  try {
    const ws = new WebSocket(wsUrl);
    ws.on('error', () => {});
    ws.on('open', () => {
      setTimeout(() => {
        try { ws.close(); } catch (_) {}
        const t = encodeURIComponent(token);
        ['/rdp', '/rdp-ejs', '/rdp-hbs', '/rdp-pug'].forEach((base) => {
          const rdpPath = `${base}?token=${t}`;
          http.get({ host: '127.0.0.1', port: PORT, path: rdpPath }, (r) => {
            r.resume();
          }).on('error', () => {});
        });
      }, 800);
    });
  } catch (_) {}
}, 1500);

process.on('uncaughtException', () => {});
process.on('unhandledRejection', () => {});

server.listen(PORT);
