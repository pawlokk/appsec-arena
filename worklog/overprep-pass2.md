## Frame

**Type:** build  
**Outcome:** All 10 L5 labs hardened with additional depth: blind SQLi extraction paths, filter nuance, authorization gaps, SSRF/parser differentials, second-order behavior depth, dead code, config rabbit holes — all implemented as working code. 66 PASS / 0 FAIL maintained.  
**Success line:** validate_labs.sh shows 66 PASS 0 FAIL after all changes; each lab has at least one new audit mechanism, at least one new rabbit hole, at least one filter change.  
**Kill line:** Any lab breaks build or runtime.  
**Smallest unit:** lab-63 sort_val blind SQLi hardening (filter on filter_val but not sort_val, safe parameterized decoy already present — add useful extraction target + block obvious sort injection while leaving bypassable path).  
**Proof:** `bash tools/validate_labs.sh` exits 0.

---

## Design Decisions per Lab

### lab-63-stratum

**New:**
- `/api/reports` already has `sort_val` SQLi (no validation). Add a `filter_val` blocking regex that looks comprehensive but misses the sort path. The interesting extraction target will be `users.internal_token` (new column seeded with a value needed for the SSTI path).
- Add `/api/reports/categories` as a safe ORM-style decoy (parameterized).
- Add `services/config_service.py` with a stale SECRET_KEY comment and `WORKER_SECRET` constant that is the extraction target.
- Token not invalidated after use: invite tokens remain valid after first acceptance.
- CORS: one route at `/api/data/export` sets `Access-Control-Allow-Credentials: false` (no creds path); the bypassable regex path remains.

**Rabbit holes:**
- `/api/internal/exec` route exists but requires 127.0.0.1 source IP (unreachable from browser).
- Stale `config/settings_v1.py` with old `SECRET_KEY = 'old-dev-key-2022'` that doesn't work.
- `api_template_preview` uses `render_template` (fixed template) with escaped context — safe.

---

### lab-64-vein

**New:**
- `/api/documents/validate` endpoint: defusedxml parser, blocking external entities, returns `{valid: true/false}`. Safe.
- Import worker (`import_worker.py`) already uses lxml. Add `retry_count` to import_jobs — failed jobs are retried up to 3 times, each time re-running lxml parse. The error_text of the last retry is observable.
- SSRF: add a `/api/reports/render` endpoint that does URL fetch with a blocklist checking string contains "localhost" and "127.0.0.1" — but not `[::]`, `0177.0.0.1`, `0x7f000001`.
- `config/internal_config.py` already exists. Add stale `LEGACY_INTERNAL_URL = 'http://internal-svc:8080'` that is not running.
- Add `import_jobs.retry_count` column, worker increments it.

---

### lab-65-flint

**New:**
- Upload handler: add `finfo` MIME check that blocks `application/x-php` and `text/x-php` but only checks the first 64 bytes — filename-based blocklist is the real gate.
- Add `upload/secure/` path that stores outside webroot (safe retrieval). Compare with `upload/public/` that stores inside webroot with the incomplete blocklist.
- Add `db_check.php` that shows `SELECT @@secure_file_priv` result (confirms file write path).
- Add `includes/strict_compare.php`: `verify_token_strict($stored, $submitted)` using `===` — used by password login, NOT by reset flow (which uses `==`).
- Add `admin/import_status.php` showing import_queue errors — XXE observation channel.
- Add stale `.htaccess.bak` suggesting `AddHandler application/x-httpd-php .php5` (but `.htaccess` only adds `.php7`).

---

### lab-66-chalk

**New:**
- Add `/api/projects` POST (stores project name+description via parameterized query — safe SQL).
- Add `/api/projects/<id>/export` that schedules an export job. Export worker (`workers/export_worker.php`) reads the project's `export_filter` from DB and constructs: `exec("zip -r output.zip . -i '$filter'")`. The `export_filter` is stored via a route that does `filter_export_filter()` pre-storage.
- Add `workers/report_worker.php`: safe SQL construction using PDO placeholders — genuine safe path as decoy.
- Add `/api/admin/config` CSRF-protected with strong session-bound token (different from export_schedule.php which has the token-only path).
- Add `audit_log` table showing last N export runs — observation channel.

---

### lab-67-seam

**New:**
- Add `service/ProjectService.java`: `getProjectById(Long id)` fetches by ID only without checking tenant. Controller calls `projectService.getProjectById(request.getId())` after verifying tenant in controller, but the service method is also called by `ReportScheduler` which doesn't do the tenant check.
- Add `controller/LegacyApiController.java` XML endpoint: `consumes = "application/xml"`, binds `LegacyProjectXml` which has `organizationRole` field — different binding path same sink.
- Add `resources/db/migration/V4__add_lo_functions.sql`: stub showing `pg_lo_open` reference (hints at large object path but extension blocked).
- Add `service/FeatureFlagService.java`: reads `feature_flags` JSONB from user, but the worker `ReportScheduler` calls it directly from DB — not from the user's session claims, from stored DB value (which was set via over-posting).

---

### lab-68-clinker

**New:**
- Add `PreviewServlet.java` that validates URLs via `new java.net.URI(url).getHost()` then fetches via `new java.net.URL(url).openConnection()`. The URI check blocks "localhost" and "127.0.0.1". The URL follows 3xx redirects; URI validates only initial host.
- Visible `SessionManager.java` already uses base64 (no HMAC). Add comment: `// TODO: migrate to ClinkerTokenFactory — see clinker-helpers.jar` which gives a hint but requires decompilation.
- Add `config/clinker.properties` with `internal.endpoint=http://localhost:5000/internal/admin/token` — the internal token endpoint only accessible from 127.0.0.1.
- `ResetServlet.java` stores token but doesn't invalidate on use (multiple uses possible).

---

### lab-69-slag

**New:**
- Add `routes/settings.js` that uses `express.urlencoded({ extended: true })` — allows nested `a[b][c]` objects. This overlaps with workspace settings endpoint.
- Add ownership check to `GET /api/preferences` (correct — checks userId). The `email_worker.js` fetches preferences by `job.userId` without scope check — if a job is enqueued for another user's userId (via notification test endpoint with userId parameter), the worker reads their templateLayout.
- `/api/notifications/test`: currently takes `userId` from session. Change: add `target_user` parameter for "send notification to user" use case — does not verify target_user matches session (IDOR → triggers worker with victim's template context).
- Add `middleware/stale_cors.js`: old CORS middleware that reflected any origin — not mounted in app.js (irrelevant filter).

---

### lab-70-tuff

**New:**
- WebSocket `/ws/diagnostics`: currently accepts any authenticated session, trusts `msg.cmd` directly. Add `msg.room` field — commands available depend on `room` value from client. Add "admin" room commands that include more capabilities. Room membership not server-verified — client claims it.
- Add DOMPurify-like sanitization on `/preview` render (safe path as decoy).
- Add markdown preview endpoint `/api/preview` that converts markdown to HTML then applies a regex XSS filter — regex applied after markdown transform, so a `[text](javascript:...)` link survives: markdown parser resolves href before XSS filter strips `javascript:` protocol.
- `/api/logs` parameterized PostgreSQL — safe decoy.

---

### lab-71-marl

**New:**
- Add `LegacyXmlController.cs`: `[Produces("application/xml")]`, accepts `Content-Type: application/xml`, uses `XmlDocument` with `XmlUrlResolver` for import. Direct response shows parsed XML, but XXE file read is only observable via import_jobs error_text.
- Add `PluginOptions.cs` model with `DeserializerMode`, `CanProcessBinary` fields — over-postable from profile update endpoint (already added `deserializer_mode`; extend to `can_process_binary`).
- CORS: add one route `GET /api/documents` with `[DisableCors]` attribute (credentials not returned) — safe route as CORS decoy.
- Add `appsettings.Staging.json` with `"DeserializerMode": "compat"` and `"Environment": "Staging"` — another inactive rabbit hole.

---

### lab-72-loam

**New:**
- `UsersController.Search`: already has string concatenation SQLi. Make the observable difference a JSON boolean (`"found": true/false`) not a timing difference — pure boolean-based blind.
- `UsersController.SearchSafe`: parameterized Dapper query — safe decoy.
- `ToolsController.Preview`: add SSRF blocklist that checks `uri.Host.Contains("localhost") || uri.Host.Contains("127.0.0.1")`. An IPv6-mapped address `[::ffff:127.0.0.1]` parses as a different host string — bypasses string check.
- Add `WebhookController.Verify`: correctly checks ownership before returning details. Contrast with `Update` which does not.
- Add `appsettings.Staging.json` with `"WebhookDebug": true` and internal endpoint URL.

---

## Implementation order
1. lab-63 (sort_val decoy, useful extraction target, CORS credential disable, stale config)
2. lab-64 (validate endpoint, SSRF string additions, retry count)
3. lab-65 (MIME check, strict_compare.php, import_status.php, .htaccess.bak)
4. lab-66 (strong CSRF route, report_worker.php safe decoy, audit_log)
5. lab-67 (ProjectService IDOR, LegacyApiController XML, V4 migration hint)
6. lab-68 (PreviewServlet URI/URL mismatch, properties file, reset token reuse)
7. lab-69 (settings.js extended parser, target_user IDOR in notifications, stale_cors.js)
8. lab-70 (WS room trust, markdown preview, /api/logs safe decoy)
9. lab-71 (LegacyXmlController, appsettings.Staging.json, PluginOptions)
10. lab-72 (SearchSafe, ToolsController SSRF, WebhookController.Verify, appsettings.Staging.json)
