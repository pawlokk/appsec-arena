'use strict';

const express = require('express');

const PORT = parseInt(process.env.PORT || '5000', 10);

const app = express();
app.use(express.json());

app.get('/', (req, res) => res.json({ status: 'ok' }));

// Application routes
app.get('/profile', (req, res) => {
  res.json({ id: 'fa0dbda9b1b', name: 'John Doe' });
});

app.get('/item', (req, res) => {
  res.json({ id: '55cf687663', name: 'Active Item' });
});

app.get('/item/:id', (req, res) => {
  res.json({ id: req.params.id, name: 'Item' });
});

// Auxiliary endpoints
const eventLog = [];

app.options('/hook/*', (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  res.status(204).send();
});

app.get('/hook/collect', (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  eventLog.push(req.query.data || '');
  res.json({ ok: true });
});

app.post('/hook/collect', (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  eventLog.push((req.body && req.body.data) || '');
  res.json({ ok: true });
});

app.get('/api/hook/results', (req, res) => {
  res.json(eventLog);
});

// -----------------------------------------------------------------------
// Batch processing — based on the bassmaster plugin batch.js logic
// -----------------------------------------------------------------------

const requestRegex = /(?:\/)(?:\$(\d)+\.)?([^\/\$]*)/g;

function internalsDispatch(reqObj, callback) {
  const path = reqObj.path || '';

  // /profile
  if (path === '/profile') {
    return callback({ statusCode: 200, result: { id: 'fa0dbda9b1b', name: 'John Doe' }, headers: {} });
  }
  // /item
  if (path === '/item') {
    return callback({ statusCode: 200, result: { id: '55cf687663', name: 'Active Item' }, headers: {} });
  }
  // /item/:id  (path will be something like /item/55cf687663 after ref resolution)
  const itemMatch = path.match(/^\/item\/(.+)$/);
  if (itemMatch) {
    return callback({ statusCode: 200, result: { id: itemMatch[1], name: 'Item' }, headers: {} });
  }

  callback({ statusCode: 404, result: { error: 'Not Found' }, headers: {} });
}

function internalsBatch(batchRequests, pos, parts, resultsData, callback) {
  let path  = '';
  let error = null;

  for (let i = 0; i < parts.length; i++) {
    path += '/';

    if (parts[i].type === 'ref') {
      const ref = resultsData.resultsMap[parts[i].index];

      if (ref) {
        let value = null;

        try {
          eval('value = ref.' + parts[i].value + ';');   // eslint-disable-line no-eval
        } catch (e) {
          error = new Error(e.message);
        }

        if (value) {
          if (value.match && value.match(/^[\w:]+$/)) {
            path += value;
          } else {
            error = new Error('Reference value includes illegal characters');
            break;
          }
        } else {
          error = error || new Error('Reference not found');
          break;
        }
      } else {
        error = new Error('Missing reference response');
        break;
      }
    } else {
      path += parts[i].value;
    }
  }

  if (error === null) {
    batchRequests[pos].path = path;
    internalsDispatch(batchRequests[pos], (data) => {
      if (('' + data.statusCode).indexOf('3') === 0) {
        batchRequests[pos].path = data.headers.location;
        internalsDispatch(batchRequests[pos], (data2) => {
          resultsData.results[pos]    = data2.result;
          resultsData.resultsMap[pos] = data2.result;
          callback(null, data2.result);
        });
        return;
      }
      resultsData.results[pos]    = data.result;
      resultsData.resultsMap[pos] = data.result;
      callback(null, data.result);
    });
  } else {
    resultsData.results[pos] = { statusCode: 400, error: error.message };
    callback(error);
  }
}

function hasRefPart(parts) {
  return parts.some((p) => p.type === 'ref');
}

function runSerial(fns, callback) {
  if (fns.length === 0) return callback(null);
  fns[0]((err) => {
    if (err) return callback(err);
    runSerial(fns.slice(1), callback);
  });
}

function runParallel(fns, callback) {
  if (fns.length === 0) return callback(null);
  let done = 0;
  let failed = false;
  fns.forEach((fn) => {
    fn((err) => {
      if (failed) return;
      if (err) { failed = true; return callback(err); }
      if (++done === fns.length) callback(null);
    });
  });
}

function internalsProcess(batchRequests, resultsData, reply) {
  const fnsParallel = [];
  const fnsSerial   = [];

  const callBatch = (pos, parts) => (callback) => {
    internalsBatch(batchRequests, pos, parts, resultsData, callback);
  };

  for (let i = 0; i < batchRequests.length; i++) {
    const parts = batchRequests[i]._parts;
    if (hasRefPart(parts)) {
      fnsSerial.push(callBatch(i, parts));
    } else {
      fnsParallel.push(callBatch(i, parts));
    }
  }

  runSerial([
    (cb) => runParallel(fnsParallel, cb),
    (cb) => runSerial(fnsSerial, cb),
  ], (err) => {
    if (err) {
      reply(400, { statusCode: 400, error: 'Bad Request', message: err.message });
    } else {
      reply(200, resultsData.results);
    }
  });
}

function buildBatchRequests(rawRequests) {
  const out = [];
  let errorMessage = null;

  for (let i = 0; i < rawRequests.length; i++) {
    const parts = [];
    let localError = null;
    requestRegex.lastIndex = 0;

    const parseRequest = ($0, $1, $2) => {
      if ($1 !== undefined && $1 !== null) {
        if (parseInt($1) < i) {
          parts.push({ type: 'ref', index: parseInt($1), value: $2 });
          return '';
        } else {
          localError = 'Request reference is beyond array size: ' + i;
          return $0;
        }
      } else {
        parts.push({ type: 'text', value: $2 });
        return '';
      }
    };

    const result = rawRequests[i].path.replace(requestRegex, parseRequest);

    if (localError) { errorMessage = localError; break; }
    if (result !== '') { errorMessage = 'Invalid request format in item: ' + i; break; }

    rawRequests[i]._parts = parts;
    out.push(rawRequests[i]);
  }

  return { requests: out, error: errorMessage };
}

function batchHandler(req, res) {
  if (!req.body || !req.body.requests) {
    return res.status(400).json({ statusCode: 400, error: 'Bad Request', message: 'Request missing requests array' });
  }

  const { requests, error: parseError } = buildBatchRequests(req.body.requests);

  if (parseError !== null) {
    return res.status(400).json({ statusCode: 400, error: 'Bad Request', message: parseError });
  }

  const resultsData = { results: [], resultsMap: [] };

  internalsProcess(requests, resultsData, (status, body) => {
    res.status(status).json(body);
  });
}

app.post('/batch', batchHandler);

// -----------------------------------------------------------------------
// Extra mile — same logic with additional pre-eval input restriction
// -----------------------------------------------------------------------

function internalsBatchHardened(batchRequests, pos, parts, resultsData, callback) {
  let path  = '';
  let error = null;

  for (let i = 0; i < parts.length; i++) {
    path += '/';

    if (parts[i].type === 'ref') {
      const ref = resultsData.resultsMap[parts[i].index];

      if (ref) {
        if (/['"`]/.test(parts[i].value)) {
          error = new Error('Invalid characters in reference');
          break;
        }

        let value = null;

        try {
          eval('value = ref.' + parts[i].value + ';');   // eslint-disable-line no-eval
        } catch (e) {
          error = new Error(e.message);
        }

        if (value) {
          if (value.match && value.match(/^[\w:]+$/)) {
            path += value;
          } else {
            error = new Error('Reference value includes illegal characters');
            break;
          }
        } else {
          error = error || new Error('Reference not found');
          break;
        }
      } else {
        error = new Error('Missing reference response');
        break;
      }
    } else {
      path += parts[i].value;
    }
  }

  if (error === null) {
    batchRequests[pos].path = path;
    internalsDispatch(batchRequests[pos], (data) => {
      if (('' + data.statusCode).indexOf('3') === 0) {
        batchRequests[pos].path = data.headers.location;
        internalsDispatch(batchRequests[pos], (data2) => {
          resultsData.results[pos]    = data2.result;
          resultsData.resultsMap[pos] = data2.result;
          callback(null, data2.result);
        });
        return;
      }
      resultsData.results[pos]    = data.result;
      resultsData.resultsMap[pos] = data.result;
      callback(null, data.result);
    });
  } else {
    resultsData.results[pos] = { statusCode: 400, error: error.message };
    callback(error);
  }
}

function internalsProcessHardened(batchRequests, resultsData, reply) {
  const fnsParallel = [];
  const fnsSerial   = [];

  const callBatch = (pos, parts) => (callback) => {
    internalsBatchHardened(batchRequests, pos, parts, resultsData, callback);
  };

  for (let i = 0; i < batchRequests.length; i++) {
    const parts = batchRequests[i]._parts;
    if (hasRefPart(parts)) {
      fnsSerial.push(callBatch(i, parts));
    } else {
      fnsParallel.push(callBatch(i, parts));
    }
  }

  runSerial([
    (cb) => runParallel(fnsParallel, cb),
    (cb) => runSerial(fnsSerial, cb),
  ], (err) => {
    if (err) {
      reply(400, { statusCode: 400, error: 'Bad Request', message: err.message });
    } else {
      reply(200, resultsData.results);
    }
  });
}

function batchHandlerHardened(req, res) {
  if (!req.body || !req.body.requests) {
    return res.status(400).json({ statusCode: 400, error: 'Bad Request', message: 'Request missing requests array' });
  }

  const { requests, error: parseError } = buildBatchRequests(req.body.requests);

  if (parseError !== null) {
    return res.status(400).json({ statusCode: 400, error: 'Bad Request', message: parseError });
  }

  const resultsData = { results: [], resultsMap: [] };

  internalsProcessHardened(requests, resultsData, (status, body) => {
    res.status(status).json(body);
  });
}

app.post('/batch_hardened', batchHandlerHardened);

process.on('uncaughtException', () => {});
process.on('unhandledRejection', () => {});

app.listen(PORT);
