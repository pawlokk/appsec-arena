/* AppSec Arena — Lab log streaming */

/*
 * State per lab:
 *   es            — app log EventSource
 *   dbEs          — DB log EventSource
 *   lineCount     — app log lines rendered
 *   dbLineCount   — DB log lines rendered
 *   open          — drawer open?
 *   tab           — active tab: 'app' | 'db'
 *   dbAvailable   — DB container detected (null = not yet checked)
 *   _shownOffline / _dbShownOffline — dedup noise
 */
const _logState = {};

// ── Public API ────────────────────────────────────────────────────────────────

function toggleLogs(labId) {
  const drawer = document.getElementById(`log-drawer-${labId}`);
  const icon   = document.getElementById(`log-icon-${labId}`);
  if (!drawer) return;

  if (drawer.classList.contains('open')) {
    drawer.classList.remove('open');
    icon.classList.remove('open');
    _setState(labId, { open: false });
    _stopLogStream(labId);
    _stopDbLogStream(labId);
  } else {
    _openDrawer(labId, drawer, icon);
    _checkDbAndStart(labId);
  }
}

/* Called by platform.js when a build starts — open drawer without stream */
function openLogDrawer(labId) {
  const drawer = document.getElementById(`log-drawer-${labId}`);
  const icon   = document.getElementById(`log-icon-${labId}`);
  if (!drawer) return;
  _openDrawer(labId, drawer, icon);
}

/* Append a single line to the App tab (build output / arena messages) */
function _appendLogLine(labId, text) {
  _appendLine(labId, 'app', text);
}

/* Connect app log SSE stream. Called by platform.js after build_done. */
function _startLogStream(labId) {
  _stopLogStream(labId);
  const state = _getState(labId);
  state.open = true;
  state._shownOffline = false;

  if (state.lineCount > 0) _appendSep(labId, 'app');

  const es = new EventSource(`/api/lab/${labId}/logs/stream?tail=200`);
  state.es = es;

  es.onmessage = (e) => {
    const text = e.data;
    if (text.includes('[container not running]') || text.includes('[container stopped]')) {
      if (state._shownOffline) return;
      state._shownOffline = true;
    } else {
      state._shownOffline = false;
    }
    _appendLine(labId, 'app', text);
  };

  es.onerror = () => {
    es.close(); state.es = null;
    if (!state.open) return;
    setTimeout(async () => {
      if (!state.open) return;
      try {
        const r = await fetch(`/api/lab/${labId}/status`);
        const d = await r.json();
        if (d.status === 'running') _startLogStream(labId);
      } catch (_) {}
    }, 3000);
  };
}

function _stopLogStream(labId) {
  const state = _logState[labId];
  if (state?.es) { state.es.close(); state.es = null; }
}

/* Switch between App and DB tabs */
function switchLogTab(labId, tab) {
  const state = _getState(labId);
  if (state.tab === tab) return;
  state.tab = tab;

  // Update tab buttons
  const appBtn = document.getElementById(`log-tab-app-${labId}`);
  const dbBtn  = document.getElementById(`log-tab-db-${labId}`);
  if (appBtn) appBtn.classList.toggle('active', tab === 'app');
  if (dbBtn)  dbBtn.classList.toggle('active', tab === 'db');

  // Swap output panes
  const appOut = document.getElementById(`log-output-${labId}`);
  const dbOut  = document.getElementById(`log-output-db-${labId}`);
  if (appOut) appOut.style.display = tab === 'app' ? '' : 'none';
  if (dbOut)  dbOut.style.display  = tab === 'db'  ? '' : 'none';

  // Start DB stream on first switch to DB tab
  if (tab === 'db' && !state.dbEs) _startDbLogStream(labId);
}

// ── Toolbar actions ───────────────────────────────────────────────────────────

function clearLogs(labId) {
  const state = _getState(labId);
  const tab   = state.tab || 'app';
  const outId = tab === 'db' ? `log-output-db-${labId}` : `log-output-${labId}`;
  const output = document.getElementById(outId);
  if (output) output.innerHTML = '';
  if (tab === 'db') state.dbLineCount = 0;
  else { state.lineCount = 0; _updateBadge(labId); }
}

function scrollToBottom(labId) {
  const state = _getState(labId);
  const tab   = state.tab || 'app';
  const outId = tab === 'db' ? `log-output-db-${labId}` : `log-output-${labId}`;
  const output = document.getElementById(outId);
  if (output) output.scrollTop = output.scrollHeight;
}

// ── DB log stream ─────────────────────────────────────────────────────────────

async function _checkDbAndStart(labId) {
  // Always start the app stream
  _startLogStream(labId);

  const state = _getState(labId);
  if (state.dbAvailable !== null) {
    // Already checked — show/hide tab accordingly
    _setDbTabVisible(labId, state.dbAvailable);
    return;
  }

  try {
    const r = await fetch(`/api/lab/${labId}/dblogs/available`);
    const d = await r.json();
    state.dbAvailable = d.available;
    _setDbTabVisible(labId, d.available);
    // Don't auto-connect DB stream — only on tab switch
  } catch (_) {
    state.dbAvailable = false;
  }
}

function _setDbTabVisible(labId, visible) {
  const btn = document.getElementById(`log-tab-db-${labId}`);
  if (btn) btn.style.display = visible ? '' : 'none';
}

function _startDbLogStream(labId) {
  _stopDbLogStream(labId);
  const state = _getState(labId);
  state._dbShownOffline = false;

  if (state.dbLineCount > 0) _appendSep(labId, 'db');

  const es = new EventSource(`/api/lab/${labId}/dblogs/stream?tail=200`);
  state.dbEs = es;

  es.onmessage = (e) => {
    const text = e.data;
    if (text.includes('[db container') ) {
      if (state._dbShownOffline) return;
      state._dbShownOffline = true;
    } else {
      state._dbShownOffline = false;
    }
    _appendLine(labId, 'db', text);
  };

  es.onerror = () => {
    es.close(); state.dbEs = null;
    if (!state.open || state.tab !== 'db') return;
    setTimeout(async () => {
      if (!state.open || state.tab !== 'db') return;
      try {
        const r = await fetch(`/api/lab/${labId}/dblogs/available`);
        const d = await r.json();
        if (d.available) _startDbLogStream(labId);
      } catch (_) {}
    }, 3000);
  };
}

function _stopDbLogStream(labId) {
  const state = _logState[labId];
  if (state?.dbEs) { state.dbEs.close(); state.dbEs = null; }
}

// ── Internal helpers ──────────────────────────────────────────────────────────

function _openDrawer(labId, drawer, icon) {
  drawer.classList.add('open');
  icon.classList.add('open');
  _setState(labId, { open: true });
}

function _getState(labId) {
  if (!_logState[labId]) {
    _logState[labId] = {
      es: null, dbEs: null,
      lineCount: 0, dbLineCount: 0,
      open: false, tab: 'app',
      dbAvailable: null,
      _shownOffline: false, _dbShownOffline: false,
    };
  }
  return _logState[labId];
}

function _setState(labId, patch) {
  Object.assign(_getState(labId), patch);
}

function _appendLine(labId, tab, text) {
  const outId = tab === 'db' ? `log-output-db-${labId}` : `log-output-${labId}`;
  const output   = document.getElementById(outId);
  const checkbox = document.getElementById(`log-autoscroll-${labId}`);
  if (!output) return;

  const span = document.createElement('span');
  span.className = _classifyLine(text, tab);
  span.textContent = text + '\n';
  output.appendChild(span);

  while (output.children.length > 2000) output.removeChild(output.firstChild);

  const state = _getState(labId);
  if (tab === 'db') {
    state.dbLineCount++;
  } else {
    state.lineCount++;
    _updateBadge(labId);
  }

  if (!checkbox || checkbox.checked) output.scrollTop = output.scrollHeight;
}

function _appendSep(labId, tab) {
  const outId = tab === 'db' ? `log-output-db-${labId}` : `log-output-${labId}`;
  const output = document.getElementById(outId);
  if (!output) return;
  const span = document.createElement('span');
  span.className = 'log-line log-line-sep';
  span.textContent = '─'.repeat(40) + '\n';
  output.appendChild(span);
}

function _updateBadge(labId) {
  const badge = document.getElementById(`log-badge-${labId}`);
  if (badge) badge.textContent = _getState(labId).lineCount;
}

function _classifyLine(text, tab) {
  const t = text.toLowerCase();
  if (/error|exception|traceback|fatal|critical/.test(t)) return 'log-line log-line-err';
  if (/warn|warning/.test(t))                             return 'log-line log-line-warn';
  if (/"(get|post|put|delete|patch) /.test(t) || /\b(200|201|302|400|401|403|404|500)\b/.test(t))
                                                          return 'log-line log-line-http';
  if (/\b(select|insert|update|delete)\b.*\bfrom\b|\bwhere\b/.test(t))
                                                          return 'log-line log-line-sql';
  if (/arena\{/.test(t))                                 return 'log-line log-line-flag';
  if (/^\─+$/.test(text))                                return 'log-line log-line-sep';
  if (/^\[arena\]/.test(t))                              return 'log-line log-line-arena';
  // DB-specific classifiers
  if (tab === 'db') {
    if (/query|execute|statement/.test(t))               return 'log-line log-line-sql';
    if (/connect|accept|listen|ready/.test(t))           return 'log-line log-line-http';
  }
  return 'log-line';
}

// ── Poll — reconnect streams if lab came back up ──────────────────────────────
setInterval(async () => {
  for (const [labId, state] of Object.entries(_logState)) {
    if (!state.open) continue;
    try {
      const r = await fetch(`/api/lab/${labId}/status`);
      const d = await r.json();
      if (d.status === 'running') {
        if (!state.es) _startLogStream(labId);
        if (state.tab === 'db' && !state.dbEs && state.dbAvailable) _startDbLogStream(labId);
      }
    } catch (_) {}
  }
}, 10_000);
