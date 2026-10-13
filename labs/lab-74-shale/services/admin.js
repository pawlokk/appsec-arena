const express = require('express');
const { v4: uuid } = require('uuid');
const state = require('../state');

const app = express();
app.use(express.json());

app.get('/', (req, res) => {
  res.json({
    plugins: {
      enabled_in_cluster: ['key-auth'],
      available_on_server: {
        'pre-function': true,
        'http-log': true,
        'key-auth': true,
        'cors': true,
        'rate-limiting': true,
        'grpc-web': true,
        'correlation-id': true,
      },
    },
  });
});

app.get('/config/runtime', (req, res) => {
  res.json({ data: process.env.FLAG_LOCAL || 'ARENA{placeholder}' });
});

app.get('/key-auths', (req, res) => {
  res.json({
    next: null,
    data: [{
      created_at: state.RENDER_CREATED_AT,
      id: state.RENDER_KEY_ID,
      tags: null,
      ttl: null,
      key: state.RENDER_API_KEY,
      consumer: { id: state.RENDER_CONSUMER_ID },
    }],
  });
});

app.post('/services', (req, res) => {
  const { name, url } = req.body;
  const id = uuid();
  const svc = { id, name, url, created_at: Date.now() };
  state.services.set(name, svc);
  res.status(201).json(svc);
});

app.post('/services/:name/routes', (req, res) => {
  const { name } = req.params;
  const { paths } = req.body;
  const id = uuid();
  const route = { id, paths, service: { name }, created_at: Date.now() };
  state.serviceRoutes.set(name, route);
  if (Array.isArray(paths)) {
    paths.forEach((p) => state.routeToService.set(p, name));
  }
  res.status(201).json(route);
});

app.post('/services/:name/plugins', (req, res) => {
  const { name } = req.params;
  const { name: pluginName, config } = req.body;
  const id = uuid();
  const plugin = { id, name: pluginName, config, service: { name }, created_at: Date.now() };
  if (!state.servicePlugins.has(name)) state.servicePlugins.set(name, []);
  state.servicePlugins.get(name).push(plugin);
  if (pluginName === 'http-log' && config && config.http_endpoint) {
    state.httpLogEndpoint.url = config.http_endpoint;
  }
  res.status(201).json(plugin);
});

app.get('/plugins', (req, res) => {
  const all = [];
  for (const list of state.servicePlugins.values()) all.push(...list);
  res.json({ data: all });
});

app.delete('/plugins/:id', (req, res) => {
  const { id } = req.params;
  for (const [svcName, list] of state.servicePlugins.entries()) {
    const idx = list.findIndex((p) => p.id === id);
    if (idx !== -1) {
      const removed = list[idx];
      list.splice(idx, 1);
      if (removed.name === 'http-log') state.httpLogEndpoint.url = null;
      return res.status(204).send();
    }
  }
  res.status(404).json({ message: 'Not found' });
});

module.exports = app;
