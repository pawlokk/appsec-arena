/* AppSec Arena — Platform JS */

// Active build EventSource per lab — so we can cancel it
const _buildStreams = {};

async function labAction(labId, action) {
  const card    = document.querySelector(`[data-lab-id="${labId}"]`);
  const dot     = document.getElementById(`dot-${labId}`);
  const openBtn = card.querySelector('.btn-open');

  if (action === 'start') {
    await _startLab(labId, card, dot, openBtn);
    return;
  }

  if (action === 'stop' || action === 'reset' || action === 'rebuild') {
    _setTransientState(labId, card, dot, action);

    _cancelBuildStream(labId);
    await fetch(`/api/lab/${labId}/build/cancel`, { method: 'POST' }).catch(() => {});
    const res  = await fetch(`/api/lab/${labId}/${action}`, { method: 'POST' });
    const data = await res.json();
    _restoreStopLabel(card);
    if (action === 'reset' || action === 'rebuild') {
      if (data.status === 'needs_build') {
        await _startLab(labId, card, dot, openBtn);
      } else {
        _applyStatus(labId, card, dot, openBtn, data);
      }
    } else {
      _applyStatus(labId, card, dot, openBtn, data);
    }
    return;
  }

  try {
    const res  = await fetch(`/api/lab/${labId}/${action}`, { method: 'POST' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    _applyStatus(labId, card, dot, openBtn, data);
  } catch (err) {
    console.error(`labAction ${action} failed for ${labId}:`, err);
  }
}

function _cancelBuildStream(labId) {
  const es = _buildStreams[labId];
  if (es) {
    es.close();
    delete _buildStreams[labId];
  }
}


async function _startLab(labId, card, dot, openBtn) {
  const res  = await fetch(`/api/lab/${labId}/start`, { method: 'POST' });
  const data = await res.json();

  if (data.status === 'running') {
    _applyStatus(labId, card, dot, openBtn, data);
    return;
  }

  if (data.status === 'needs_build') {
    _setCardState(card, dot, 'building');
    _disableButtons(card, true);
    if (typeof openLogDrawer === 'function') openLogDrawer(labId);

    try {
      await _streamBuild(labId);
      _applyStatus(labId, card, dot, openBtn, { status: 'running', port: card.dataset.port });
    } catch (err) {
      _applyStatus(labId, card, dot, openBtn, { status: 'stopped', port: card.dataset.port });
      if (typeof _appendLogLine === 'function') _appendLogLine(labId, `[arena] ${err.message}`);
    }
    _disableButtons(card, false);
    return;
  }

  console.error('Unexpected start response:', data);
}


function _streamBuild(labId) {
  return new Promise((resolve, reject) => {
    const es = new EventSource(`/api/lab/${labId}/build/stream`);
    _buildStreams[labId] = es;

    es.onmessage = (e) => {
      if (typeof _appendLogLine === 'function') _appendLogLine(labId, e.data);
    };

    es.addEventListener('build_done', () => {
      es.close();
      delete _buildStreams[labId];
      if (typeof _startLogStream === 'function') _startLogStream(labId);
      resolve();
    });

    es.addEventListener('build_failed', () => {
      es.close();
      delete _buildStreams[labId];
      reject(new Error('Build failed — check logs'));
    });

    es.onerror = () => {
      es.close();
      delete _buildStreams[labId];
      reject(new Error('Build stream error'));
    };
  });
}


function _applyStatus(labId, card, dot, openBtn, data) {
  const running = data.status === 'running';

  dot.className = 'status-dot ' + (running ? 'dot-running' : 'dot-off');

  card.classList.remove('running', 'stopped', 'absent', 'building');
  card.classList.add(running ? 'running' : data.status === 'absent' ? 'absent' : 'stopped');

  const ob = document.getElementById(`open-${labId}`);
  if (ob) {
    if (running) {
      ob.href = `http://${window.ARENA_HOST || location.hostname}:${data.port}`;
      ob.classList.remove('disabled');
    } else {
      ob.removeAttribute('href');
      ob.classList.add('disabled');
    }
  }

  _disableButtons(card, false);
  const startBtn = card.querySelector('.btn-start');
  const stopBtn  = card.querySelector('.btn-stop');
  if (startBtn) startBtn.disabled = running;
  if (stopBtn)  stopBtn.disabled  = !running;
}


function _setCardState(card, dot, state) {
  card.classList.remove('running', 'stopped', 'absent', 'building');
  card.classList.add(state);
  dot.className = 'status-dot dot-building';
}

function _setTransientState(labId, card, dot, action) {
  dot.className = 'status-dot dot-stopping';
  card.classList.remove('running', 'stopped', 'absent', 'building');
  card.classList.add('stopping');

  const startBtn = card.querySelector('.btn-start');
  const stopBtn  = card.querySelector('.btn-stop');
  if (startBtn) startBtn.disabled = true;
  if (stopBtn)  { stopBtn.disabled = true; stopBtn.classList.add('btn-busy'); }
}


function _disableButtons(card, disabled) {
  card.querySelectorAll('.btn-start, .btn-stop').forEach(b => { b.disabled = disabled; });
  const resetBtn = card.querySelector('.btn-reset');
  if (resetBtn) {
    resetBtn.textContent = disabled ? 'Cancel' : 'Reset';
  }
}

function _restoreStopLabel(card) {
  const stopBtn = card.querySelector('.btn-stop');
  if (stopBtn) stopBtn.classList.remove('btn-busy');
}


async function submitFlag(labId) {
  const input  = document.getElementById(`flag-${labId}`);
  const result = document.getElementById(`flag-result-${labId}`);
  const flag   = input.value.trim();
  if (!flag) return;

  try {
    const res  = await fetch(`/api/lab/${labId}/flag/verify`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ flag }),
    });
    const data = await res.json();
    if (data.valid) {
      result.textContent = `✓ ${data.type}`;
      result.className   = 'flag-result ok';
      // Mark completed when proof flag is verified (RCE confirmed)
      if (data.type === 'proof') {
        _markCompleted(labId);
      }
    } else {
      result.textContent = '✗ wrong';
      result.className   = 'flag-result fail';
    }
  } catch (err) {
    result.textContent = 'err';
    result.className   = 'flag-result fail';
  }
}

function _markCompleted(labId) {
  try {
    const completed = new Set(JSON.parse(localStorage.getItem('arena_completed') || '[]'));
    completed.add(labId);
    localStorage.setItem('arena_completed', JSON.stringify([...completed]));
    // Re-apply filters in case hide-completed is active
    if (typeof _applyFilters === 'function') _applyFilters();
  } catch(_) {}
}


/* Source download — fetch as blob so the page never navigates */
async function downloadSource(e, labId) {
  e.preventDefault();
  const btn = e.currentTarget;
  btn.textContent = '...';
  btn.disabled = true;
  try {
    const r    = await fetch(`/api/lab/${labId}/source`);
    const blob = await r.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `${labId}-source.zip`;
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 5000);
  } finally {
    btn.textContent = 'Source';
    btn.disabled = false;
  }
}

/* Copy lab URL to clipboard.
 * Uses Clipboard API when available (HTTPS/localhost), falls back to the
 * legacy execCommand trick which works silently on plain HTTP. */
async function copyLink(labId) {
  const card = document.querySelector(`[data-lab-id="${labId}"]`);
  const port = card ? card.dataset.port : null;
  const btn  = document.getElementById(`copy-${labId}`);
  if (!port) return;
  const url = `http://${location.hostname}:${port}`;

  let ok = false;
  if (navigator.clipboard && location.protocol === 'https:') {
    try { await navigator.clipboard.writeText(url); ok = true; } catch (_) {}
  }
  if (!ok) {
    // HTTP fallback — silent textarea + execCommand
    const ta = document.createElement('textarea');
    ta.value = url;
    ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try { ok = document.execCommand('copy'); } catch (_) {}
    document.body.removeChild(ta);
  }

  if (btn) {
    btn.textContent = ok ? 'Copied!' : 'failed';
    setTimeout(() => { btn.textContent = 'Copy link'; }, 1500);
  }
}

/* Delete lab — stops container + removes image, resets card to unbuilt */
async function deleteLab(labId) {
  const card = document.querySelector(`[data-lab-id="${labId}"]`);
  const dot  = document.getElementById(`dot-${labId}`);
  const btn  = card ? card.querySelector('.btn-delete') : null;
  if (!card) return;

  if (!confirm(`Delete ${labId}?\nThis removes the container and built image. You will need to rebuild.`)) return;

  if (btn) { btn.textContent = '...'; btn.disabled = true; }
  _setTransientState(labId, card, dot, 'stop');

  try {
    const res  = await fetch(`/api/lab/${labId}/delete`, { method: 'POST' });
    const data = await res.json();
    const ob   = document.getElementById(`open-${labId}`);
    const openBtn = ob || card.querySelector('.btn-open');
    _applyStatus(labId, card, dot, openBtn, { status: 'absent', port: card.dataset.port });
    // Reset build badge to "not built"
    const badge = document.getElementById(`build-badge-${labId}`);
    if (badge) { badge.textContent = 'not built'; badge.className = 'build-badge unbuilt'; }
    _restoreStopLabel(card);
  } catch (err) {
    console.error('Delete failed:', err);
    _restoreStopLabel(card);
  } finally {
    if (btn) { btn.textContent = 'Delete'; btn.disabled = false; }
  }
}

/* Poll status every 10s to keep dots fresh */
async function pollStatuses() {
  try {
    const res  = await fetch('/api/labs');
    const labs = await res.json();
    for (const lab of labs) {
      const dot  = document.getElementById(`dot-${lab.id}`);
      const card = document.querySelector(`[data-lab-id="${lab.id}"]`);
      if (!dot || !card) continue;
      if (card.classList.contains('building') || card.classList.contains('stopping')) continue;

      const running = lab.status === 'running';

      dot.className = 'status-dot ' + (running ? 'dot-running' : 'dot-off');
      card.classList.remove('running', 'stopped', 'absent');
      card.classList.add(running ? 'running' : lab.status === 'absent' ? 'absent' : 'stopped');

      const ob = document.getElementById(`open-${lab.id}`);
      if (ob) {
        if (running) {
          ob.href = `http://${window.ARENA_HOST || location.hostname}:${lab.port}`;
          ob.classList.remove('disabled');
        } else {
          ob.removeAttribute('href');
          ob.classList.add('disabled');
        }
      }

      const startBtn = card.querySelector('.btn-start');
      const stopBtn  = card.querySelector('.btn-stop');
      if (startBtn && !startBtn.classList.contains('btn-building')) startBtn.disabled = running;
      if (stopBtn)  stopBtn.disabled  = !running;

      const buildBadge = document.getElementById(`build-badge-${lab.id}`);
      if (buildBadge) {
        buildBadge.textContent = lab.image_built ? 'built' : 'not built';
        buildBadge.className   = 'build-badge ' + (lab.image_built ? 'built' : 'unbuilt');
      }
    }
  } catch (_) {}
}

setInterval(pollStatuses, 10_000);

function _openModal(label, tier, text, tags) {
  const labelEl = document.getElementById('hint-modal-label');
  const textEl  = document.getElementById('hint-modal-text');
  const tagsEl  = document.getElementById('hint-modal-tags');
  labelEl.textContent = label;
  labelEl.dataset.tier = tier;
  textEl.textContent = text;
  tagsEl.innerHTML = '';
  if (tags && tags.length) {
    tags.forEach(t => {
      const s = document.createElement('span');
      s.className = 'tag';
      s.textContent = t.trim();
      tagsEl.appendChild(s);
    });
  }
  document.getElementById('hint-modal').style.display = 'flex';
}

function showDesc(labId) {
  const card = document.querySelector(`[data-lab-id="${labId}"]`);
  const name = card.dataset.name || labId;
  const desc = card.dataset.desc || '';
  const tags = (card.dataset.tags || '').split(',').filter(Boolean);
  _openModal(name, 'desc', desc, tags);
}

async function revealHint(labId, tier) {
  const labName = document.querySelector(`[data-lab-id="${labId}"]`)?.dataset.name || labId;
  _openModal(`${labName}  —  hint ${tier}`, tier, '…', []);
  try {
    const r = await fetch(`/api/lab/${labId}/hint/${tier}`, { method: 'POST' });
    const d = await r.json();
    document.getElementById('hint-modal-text').textContent =
      r.ok ? d.text : (d.error || 'no hint available');
  } catch (_) {
    document.getElementById('hint-modal-text').textContent = 'error fetching hint';
  }
}

function closeHintModal() {
  document.getElementById('hint-modal').style.display = 'none';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeHintModal(); });

function toggleExpand(labId) {
  const tray = document.getElementById(`expand-tray-${labId}`);
  const btn  = document.getElementById(`expand-${labId}`);
  const open = tray.classList.toggle('open');
  btn.classList.toggle('active', open);
}
