# AppSec Arena — Developer Guide for Claude

Read this before touching any file. It maps the architecture, file ownership rules, and the traps that have already burned us.

---

## Project layout

```
appsec-arena/
├── platform/
│   ├── app.py                   ← Flask backend — lab registry, routes, flag logic
│   ├── port_registry.json       ← stable host-port assignments (5100-5599), never hand-edit
│   ├── templates/
│   │   ├── index.html           ← main UI — lab cards, filter bar, running tray, inline JS
│   │   └── directory.html       ← static directory view
│   └── static/
│       ├── css/platform.css     ← ALL styles live here, never inline
│       ├── js/platform.js       ← lab lifecycle (start/stop/reset/flag/hint/download)
│       └── js/logs.js           ← log drawer streaming (SSE), separate concern
├── labs/
│   └── lab-NN-shortname/        ← one dir per lab, Dockerfile + app source
├── hints/
│   └── lab-NN.json              ← {"tier1": "...", "tier2": "..."} — zero-padded NN
├── docs/
│   └── adding-a-lab.md          ← Dockerfile templates, known failure patterns
├── tools/
│   ├── validate_labs.sh         ← run after ANY Dockerfile or registry change
│   └/smoke_test.sh              ← build + HTTP check for one lab
├── worklog/                     ← session work logs, committed with changes
└── OSWE_TARGET_LIST.md          ← study plan, do not reformat
```

---

## Data flow — know this before editing anything UI-related

```
app.py LABS list
  → _assign_ports() reads port_registry.json → populates lab["port"]
  → index() / directory() build categories list → pass to render_template
  → index.html Jinja2 renders lab-card divs with data-* attributes
  → inline JS in index.html: _filters object + _applyFilters() handles filtering
  → platform.js: labAction / submitFlag / downloadSource / revealHint / pollStatuses
  → logs.js: toggleLogs / _startLogStream / switchLogTab (completely separate)
```

**Key split:** filtering and the running tray live in **inline `<script>` in index.html**. Everything else (start/stop/build/flag/hint/source download) lives in **platform.js**. Never move filtering logic to platform.js or vice versa.

---

## File ownership rules — one concern per file

| What you're changing | File to edit |
|---|---|
| New LABS entry | `platform/app.py` LABS list |
| Port assignment | Don't touch `port_registry.json` by hand — auto-assigned on first start |
| Lab card HTML structure | `platform/templates/index.html` |
| Filter bar pills | `platform/templates/index.html` (Lang/Level hardcoded; Category is Jinja2 dynamic) |
| Filter logic (lang/level/category) | Inline `<script>` in `index.html` — `_filters` + `_applyFilters()` |
| Running tray (button + dropdown) | Inline `<script>` in `index.html` — `_updateRunningTray()` + `_toggleRunningDropdown()` |
| Lab lifecycle (start/stop/reset/flag) | `platform/static/js/platform.js` |
| Log streaming | `platform/static/js/logs.js` |
| **Any CSS** | `platform/static/css/platform.css` — **never inline styles in HTML** |
| Hint content | `hints/lab-NN.json` |
| Dockerfile templates / failure patterns | `docs/adding-a-lab.md` |

---

## lab-card data attributes (index.html)

Every `.lab-card` div carries these — JS reads them for filtering and actions:

```
data-lab-id      lab["id"]                        e.g. "lab-30-jwt-kid-sqli"
data-port        lab["port"]                      e.g. 5130
data-lang        lab["lang"]                      e.g. "Node.js"
data-level       lab["level"]                     e.g. 3
data-category    lab["category"] | default        e.g. "oswe-prep"
data-desc        lab["description"]
data-tags        lab["tags"] joined with ","
data-name        lab["name"]
```

**If you add a new filter dimension:** add it to the `data-*` attribute on the card, add it to `_filters = { ..., newdim: '' }`, add it to `_applyFilters()`, and add the pill group to the filter bar.

---

## app.py LABS entry — required fields

```python
{
    "id": "lab-NN-shortname",   # must match labs/ directory name exactly
    "name": "DisplayName",      # shown in UI + used as source zip filename
    "level": 0-4,
    "lang": "Python|PHP|Java|Node.js|.NET",
    "stack": "Human-readable stack string",
    "tags": ["Tag1", "Tag2"],
    "description": "One sentence, no spoilers.",
    "image": "appsec-arena-lab-NN-shortname",
    "category": "oswe-prep",    # filter pill is auto-generated from this
    "type": "docker",
}
```

After adding: `bash tools/validate_labs.sh` must exit 0.

---

## Category filter is data-driven — never hardcode

The Category pills in the filter bar are rendered by Jinja2 from the `categories` variable:
```python
categories = sorted(set(l.get("category", "oswe-prep") for l in LABS))
```
Both `index()` and `directory()` routes must pass `categories=categories`. Adding a new category value to a LABS entry auto-creates the pill — no template edit needed.

---

## Flag system

- Format: `ARENA{...}` always. Never `FLAG_LOCAL{...}`, `FLAG_PROOF{...}`, `FLAG{...}`.
- Flags are **derived at runtime** by `_lab_flag(lab_id, flag_type)` using HMAC-SHA256 of `ARENA_SECRET:lab_id:flag_type`. Never hardcode flag values anywhere.
- Every lab Dockerfile CMD must write both flags before starting the app:
  ```sh
  echo "$FLAG_LOCAL" > /flag_local.txt && echo "$FLAG_PROOF" > /flag_proof.txt
  ```
- `FLAG_LOCAL` = auth milestone (readable after gaining access). `FLAG_PROOF` = RCE confirmed (readable from shell).

---

## Known Dockerfile traps

| Trap | Symptom | Fix |
|---|---|---|
| MariaDB root TCP | `Access denied for root@localhost` | Use `appuser` in init.sql + app code, never `root` with `host='127.0.0.1'` |
| sed `/` delimiter | `sed: unterminated substitute` on bcrypt/password values | Use `\|` as delimiter: `sed "s\|PLACEHOLDER\|$VALUE\|g"` |
| ysoserial dead URL | Zero-byte JAR | Use `https://github.com/frohoff/ysoserial/releases/download/v0.0.6/ysoserial-all.jar`; fallback `\|\| exit 1`, never `touch` |
| wkhtmltopdf silent skip | Blank PDF | Add `&& which wkhtmltopdf` after install block |
| Missing `/app` dir | `cannot create /app/flag_local.txt` in Apache images | Add `RUN mkdir -p /app` |
| Wrong flag format | validate_labs.sh FAIL | `ARENA{placeholder}` in ENV, `$FLAG_LOCAL`/`$FLAG_PROOF` in CMD |
| PostgreSQL pg_hba.conf | auth fails despite editing | Edit `/etc/postgresql/15/main/pg_hba.conf`, not the root one |
| pg + pg-promise version | `pg.connect is not a function` | `pg@8` + `pg-promise@10` — older pg-promise bundles pg@5 |

---

## Source download filename

`platform/app.py` `lab_source()` uses `download_name=f"{lab['name']}-source.zip"` — the display name, not the lab ID. Keep it this way.

---

## Running tray architecture

- **Button:** `#running-tray-btn` — shows `▶ running (N)`, toggles dropdown
- **Dropdown:** `#running-dropdown` — absolute-positioned below button, hidden by default
- **Chips:** inside `#running-chips` in the dropdown — one row per running lab
- **JS:** `_updateRunningTray()` rebuilds chips; `_toggleRunningDropdown()` toggles; click-outside handler closes
- **CSS:** `.running-tray` is `position: relative`; `.running-dropdown` is `position: absolute`

---

## Port range

- Host: 5100–5599. Internal container port: always 5000.
- Never manually assign a port that's already in `port_registry.json`.
- Smoke tests use 19000–19099 (ephemeral, not registered).

---

## Before committing any change

```bash
bash tools/validate_labs.sh   # must exit 0, 0 FAIL
```

If you edited a Dockerfile, also:
```bash
bash tools/smoke_test.sh lab-NN-shortname   # must PASS
```
