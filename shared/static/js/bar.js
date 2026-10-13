/* AppSec Arena — injected lab bar script
 * Reads LAB_META injected by nginx (or falls back to window.__ARENA_LAB__)
 * and builds the bar UI.
 */
(function () {
  const meta = window.__ARENA_LAB__ || {};
  const labId   = meta.id    || document.body.dataset.arenaLab    || '';
  const labName = meta.name  || document.body.dataset.arenaName   || 'Lab';
  const level   = meta.level !== undefined ? meta.level : (document.body.dataset.arenaLevel || '?');
  const tags    = meta.tags  || (document.body.dataset.arenaTags  || '').split(',').filter(Boolean);
  // Use the Referer header origin if available, fall back to env-injected or same host port 8080
  const platformOrigin = meta.platform
    || (document.referrer ? new URL(document.referrer).origin : null)
    || `http://${location.hostname}:8080`;

  const bar = document.getElementById('arena-bar');
  if (!bar) return;

  // Tags HTML
  const tagsHtml = tags.map(t => `<span class="arena-tag">${t}</span>`).join('');

  bar.innerHTML = `
    <div class="arena-bar-left">
      <a class="arena-logo" href="${platformOrigin}" target="_blank">[AppSec Arena]</a>
      <span class="arena-sep">/</span>
      <span class="arena-lab-name">${labName}</span>
      <span class="arena-level">L${level}</span>
      <div class="arena-tags">${tagsHtml}</div>
    </div>
    <div class="arena-bar-right">
      <div class="arena-flag-wrap">
        <input class="arena-flag-input" id="arena-flag-input"
               placeholder="ARENA{...}" autocomplete="off" spellcheck="false">
        <button class="arena-btn-flag" onclick="arenaVerifyFlag()">Verify</button>
        <span class="arena-flag-status" id="arena-flag-status"></span>
      </div>
      <button class="arena-btn arena-btn-reset" onclick="arenaReset()">⟳ Reset</button>
      <a class="arena-btn arena-btn-home" href="${platformOrigin}" target="_blank">← Arena</a>
    </div>
  `;

  window.arenaVerifyFlag = async function () {
    const flag   = document.getElementById('arena-flag-input').value.trim();
    const status = document.getElementById('arena-flag-status');
    if (!flag) return;
    try {
      const r = await fetch(`${platformOrigin}/api/lab/${labId}/flag/verify`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ flag }),
      });
      const d = await r.json();
      status.textContent = d.valid ? `✓ ${d.type}` : '✗';
      status.className   = 'arena-flag-status ' + (d.valid ? 'arena-flag-ok' : 'arena-flag-fail');
    } catch (_) {
      status.textContent = 'err';
      status.className   = 'arena-flag-status arena-flag-fail';
    }
  };

  window.arenaReset = async function () {
    if (!labId) return;
    await fetch(`${platformOrigin}/api/lab/${labId}/reset`, { method: 'POST' });
    setTimeout(() => location.reload(), 2000);
  };

  // Enter to verify flag
  document.getElementById('arena-flag-input')
    .addEventListener('keydown', e => { if (e.key === 'Enter') arenaVerifyFlag(); });
})();
