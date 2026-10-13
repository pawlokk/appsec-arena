# L5 Lab Deepening Specification
## Builder-facing only — do not expose to learner

---

## LAB 63 — STRATUM (Flask · PostgreSQL)

### 1. Business story additions
Stratum is an internal analytics SaaS used by multiple business units. It supports scheduled reports, team invites, notification preferences, and an admin audit log. Each team has a workspace; reports are generated on demand or on a schedule. Notification emails are rendered server-side from stored templates.

### 2. New files / routes / components to add

```
services/
  token_service.py          # invite and reset token generation
  notification_service.py   # renders notification messages
workers/
  report_worker.py          # background report generation, runs on schedule
routes/
  reset.py                  # password reset request + confirmation
  invites.py                # team invite create/accept
  notifications.py          # notification preferences + history
migrations/
  001_initial.sql
  002_add_notifications.sql  # stale — references old template_string column
config/
  settings.py               # production config
  settings_dev.py           # dev config (debug endpoints enabled, not loaded in prod)
```

New routes to add alongside existing ones:
- `GET/POST /reset/request` — submit email for reset
- `GET/POST /reset/confirm` — confirm token, set new password
- `POST /invite` — create invite token for email
- `GET /invite/accept/<token>` — accept invite
- `GET/POST /notifications/preferences` — stored notification settings
- `GET /admin/audit` — audit log viewer (admin only)
- `GET /api/reports/scheduled` — list scheduled reports
- `POST /api/reports/schedule` — create schedule (stores template reference)

### 3. New audit mechanisms

- **Token generation**: `token_service.py` uses `hashlib.md5(username.encode() + str(int(time.time() / 60)).encode()).hexdigest()` — time-bucketed, predictable within a 60-second window; no expiry enforced on confirm endpoint.
- **Notification rendering**: `notification_service.py` calls `render_template_string(stored_message_template, **context)` for notification body generation; message template is stored from a preferences field.
- **CORS per-blueprint**: public stats blueprint has `supports_credentials=False`; main API blueprint has the broken reflect-any-origin middleware. Both present; middleware chain order matters.
- **CORS regex bypass**: origin validator uses `re.search(r'analytics\.internal$', origin)` — suffix match, not `^https://analytics\.internal$`; also unescaped `.` in pattern.
- **Blind SQLi in ORDER BY**: `/api/reports` sort parameter injected into `ORDER BY {sort_val}` without parameterization; WHERE-style blocks applied only to search/filter params.
- **Second-order rendering**: `report_worker.py` fetches the stored `report_template_name` from DB and calls `render_template_string(load_template(name), **report_data)` where `load_template` reads from a user-writable templates table.

### 4. Second-order behavior
`report_worker.py` processes the `scheduled_reports` queue. It reads `template_ref` from the `scheduled_reports` table (set by the user at schedule-creation time), fetches the template body from the `report_templates` table, and calls `render_template_string`. Trigger: POST to `/api/reports/schedule`, then GET `/api/reports/run/<id>` (admin action or time-based poll visible in status endpoint).

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | Keyword blocklist on search/filter params | Rejects `UNION`, `--`, `/*` as literal substrings |
| Bypassable | CORS origin suffix check | `re.search(r'analytics\.internal$', origin)` — attackeranalytics.internal passes |
| Irrelevant | Jinja2 autoescape on `/profile` view | Profile display_name escaped via `{{ name \| e }}` — safe, but unrelated to SSTI path |

### 6. Rabbit holes and dead code

- `settings_dev.py` defines `/debug/console` route — container sets `APP_ENV=production`, so `settings_dev.py` is never imported.
- `/api/users/search` uses SQLAlchemy `.filter(User.username.ilike(f'%{q}%'))` — parameterized ORM.
- `/api/public/stats` blueprint has proper CORS allowlist with `supports_credentials=False` — visible at top of app initialization, looks like it covers all CORS.
- Direct profile name template rendering: `render_template('profile.html', name=escape(display_name))` — fixed template, escaped context.
- `migrations/002_add_notifications.sql` contains a `template_string TEXT` column on `users` — stale; current schema stores templates in a separate `report_templates` table.

### 7. Required source-review insights

- Trace which CORS middleware applies to main API vs public blueprint.
- Find CORS regex pattern and identify the canonicalization flaw.
- Locate `report_worker.py` and trace the `template_ref` → `load_template` → `render_template_string` chain.
- Understand that `ORDER BY` cannot be parameterized; trace how `sort` differs from `filter` handling.
- Identify that reset token is time-bucketed, not cryptographically random.

### 8. Setup / reset / seed

- Seed: two teams, one admin user, three report templates (two fixed, one user-editable), five scheduled reports, ten notification preference records.
- Reset script: `flask seed reset` — drops and recreates all tables, re-seeds.
- `report_worker.py` runs via `flask run-worker` or can be triggered via `/api/admin/worker/run` (admin route).

### 9. Learner-facing objective

> Stratum Analytics Platform — v2.4.1
>
> Stratum is an internal analytics platform used by multiple business teams. You have been given access to the source code and a running instance. Your objectives are:
>
> **FLAG_LOCAL**: Gain authenticated admin access to the platform.
> **FLAG_PROOF**: Achieve remote code execution on the server and read `/flag_proof.txt`.
>
> You are permitted to review all source files, interact with the running application, and write scripts. You may not modify the source after deployment.

### 10. Non-spoiler validation criteria

- FLAG_LOCAL is readable at `/admin/audit` after gaining admin session.
- FLAG_PROOF is readable only from the filesystem via OS-level command execution.
- Both objectives must be demonstrated with a written source-to-sink trace.

### 11. Patch themes (no exact fixes)

- Token generation: replace time-bucketed hash with `secrets.token_urlsafe(32)`, store hash in DB with expiry.
- CORS: use an exact-match allowlist, escape regex metacharacters, pin to scheme+host+port.
- SQLi: move ORDER BY to a server-side allowlist with no user input reaching SQL string.
- Template rendering: replace `render_template_string` with `render_template` and fixed-name templates.

---

## LAB 64 — VEIN (Flask · PostgreSQL)

### 1. Business story additions
Vein is a financial document ingestion platform. Documents are submitted for processing, go through a fast preview stage (synchronous), and a full processing stage (asynchronous worker). Import status and errors are visible in a job status table. An internal report bot generates PDF summaries by visiting internal report URLs. A config service on an internal port holds API tokens.

### 2. New files / routes / components to add

```
workers/
  import_worker.py    # async document processor — unsafe parser
  report_bot.py       # internal headless-style bot, visits report URLs
services/
  import_service.py   # queues documents, synchronous preview
  status_service.py   # tracks import job status and errors
routes/
  status.py           # GET /api/import/status/<job_id> — leaks error messages
  preview.py          # POST /api/documents/preview — safe parser
config/
  internal_config.py  # loaded only by import_worker; contains internal API token
migrations/
  001_initial.sql
  002_add_jobs.sql
  003_drop_legacy_xml.sql   # misleading — suggests XML was removed
```

### 3. New audit mechanisms

- **Parser differential**: `preview.py` uses `defusedxml.ElementTree.fromstring(data)` — safe. `import_worker.py` uses `lxml.etree.fromstring(raw_data)` with no safe parser argument — resolves external entities.
- **Indirect XXE evidence**: `import_worker.py` catches `lxml.etree.XMLSyntaxError` and stores the error message in `import_jobs.error_text`. Error messages from failed entity resolution (e.g., file not found, connection refused) appear in `/api/import/status/<job_id>`. This is the observation channel.
- **SSRF URL validation**: `url_validator.py` checks `urlparse(url).hostname not in BLOCKED_HOSTS` — BLOCKED_HOSTS contains string values; IPv6-mapped IPv4 addresses (`::ffff:127.0.0.1`) or decimal-encoded IPs are not in the set.
- **Report bot**: `report_bot.py` uses `requests.get(report_url, cookies={"session": ADMIN_SESSION})` with a hardcoded admin session. URLs are stored in the `report_queue` table by authenticated users. Bot visits the URL and stores the rendered HTML length in `report_results`.
- **Config service**: `internal_config.py` is imported only by `import_worker.py` and contains `INTERNAL_API_TOKEN`. This value is not in environment variables and not accessible via normal routes.

### 4. Second-order behavior

Two second-order chains:
1. POST document to `/api/documents/import` → job queued → `import_worker.py` processes with unsafe parser → error message (including entity-resolution output) stored in `import_jobs.error_text` → readable at `/api/import/status/<job_id>`.
2. POST URL to `/api/reports/queue` → `report_bot.py` visits URL with admin session → result stored in `report_results` table → readable at `/api/reports/result/<id>`.

Trigger: worker runs every 10 seconds; status endpoint returns `processing`/`done`/`error` with error text.

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | `defusedxml` on preview endpoint | Raises `DefusedXmlException` on any DOCTYPE — cannot be bypassed |
| Bypassable | SSRF hostname blocklist | String set comparison; IPv6-encoded addresses not blocked |
| Irrelevant | Content-Type check on upload endpoint | Returns 415 if not `application/xml` — no relationship to XXE exploitability |

### 6. Rabbit holes and dead code

- `preview.py` uses `defusedxml` — clearly documented in code comments as "XXE-safe preview."
- Direct SSRF to `127.0.0.1:9222` blocked by hostname check.
- `migrations/003_drop_legacy_xml.sql` contains `DROP TABLE legacy_xml_imports` — suggests XML processing was removed, but `import_worker.py` still processes XML from the jobs queue.
- Document search route uses `cur.execute("SELECT * FROM documents WHERE title = %s", (title,))` — fully parameterized, not injectable.
- `report_bot.py` sanitizes `<script>` tags from HTML before storing — direct script injection not useful.

### 7. Required source-review insights

- Find that preview uses safe parser but worker uses unsafe parser.
- Trace that XXE results surface only via error messages in status endpoint, not in direct response.
- Identify the SSRF URL validator class and find the address encoding gap.
- Understand that report_bot visits user-controlled URLs with admin credentials.
- Locate `internal_config.py` and understand it is only reachable via the worker or SSRF chain.

### 8. Setup / reset / seed

- Seed: five queued import jobs (mixed states), three report queue entries, two users (one admin).
- `import_worker.py` and `report_bot.py` run as background threads started at app startup.
- Reset: `flask seed reset`.

### 9. Learner-facing objective

> Vein Document Pipeline — v1.9.0
>
> Vein ingests financial documents for compliance archival. You have full source access and a running instance.
>
> **FLAG_LOCAL**: Gain admin-level access to the application.
> **FLAG_PROOF**: Achieve remote code execution and read `/flag_proof.txt`.

### 10. Non-spoiler validation criteria

- FLAG_LOCAL readable at `/admin` after admin session established.
- FLAG_PROOF readable only via OS command execution.
- Learner must document which parser processes which input and how evidence surfaces.

### 11. Patch themes

- XML processing: use `defusedxml` or `lxml.etree.XMLParser(resolve_entities=False, no_network=True)` in worker.
- SSRF: resolve hostname to IP before comparison; block all private address ranges by IP, not by string.
- Report bot: do not allow user-controlled URLs to be visited with privileged session; use an allowlist.

---

## LAB 65 — FLINT (PHP · MySQL)

### 1. Business story additions
Flint CRM manages customer territories with tenant separation. It supports bulk contact import via XML (preview and a privileged processing path), file attachment uploads for customer records, and a password reset flow. Multiple legacy migration files are present. Apache configuration includes handler rules for legacy file types.

### 2. New files / routes / components to add

```
includes/
  token_helper.php        # reset token generation and comparison
  upload_handler.php      # file upload with extension filter
  request_helper.php      # parameter extraction wrapper (uses $_REQUEST internally)
process_import.php        # privileged XML processing path (admin context)
preview_import.php        # safe XML preview (public/low-priv)
search_api.php            # parameterized API search (rabbit hole)
migrations/
  001_initial.sql
  002_add_tenants.sql     # stale — references old token_hash column type
  003_add_upload_types.sql
.htaccess                 # AddHandler entries for legacy extensions
upload/                   # upload directory outside webroot (rabbit hole)
```

### 3. New audit mechanisms

- **$_REQUEST precedence**: `request_helper.php` contains `get_param($name)` which reads `$_REQUEST[$name]`. On the token confirmation path, `get_param('token')` is used — `$_REQUEST` prefers `$_COOKIE` over `$_POST` in some PHP configurations. A `token` cookie can shadow the POST body value.
- **Array parameter confusion**: in one form submission, `token[]` (array) passed to the `==` comparison — PHP type-juggling behavior when comparing array to string.
- **Two XML paths**: `preview_import.php` uses `DOMDocument` with `libxml_disable_entity_loader(true)` and `LIBXML_NOENT` cleared. `process_import.php` uses `simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_DTDLOAD)` — privileged path, not rate-limited.
- **secure_file_priv discovery**: A SQLi-reachable path allows reading `@@secure_file_priv` and `@@datadir` from MySQL — these values confirm write path availability.
- **Upload extension filter**: `upload_handler.php` blocks `.php`, `.phtml`, `.php3`, `.php4`, `.php5`, `.pht`. The `.htaccess` in the uploads dir contains `AddHandler application/x-httpd-php .php7` — `.php7` not in blocklist.
- **Unserialize rabbit hole**: `session_restore.php` calls `unserialize($cookie_data)` but `$cookie_data` is MAC-verified with HMAC-SHA256 before deserialization — signature check is correct.

### 4. Second-order behavior
`process_import.php` is not called directly from the UI — it is invoked by a cron job or admin action (`/admin/process_queue.php`). Documents submitted via upload endpoint are queued; `process_import.php` processes the queue with elevated DB privileges. Trigger: POST to `/admin/process_queue.php` (requires admin, but admin access is one of the objectives).

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | Format check on reset token field | `preg_match('/^[a-f0-9]{32}$/', $token)` — blocks `0` and obvious non-hex input |
| Bypassable | `$_REQUEST` parameter reading | Cookie value shadows POST value; array-type submission alters comparison behavior |
| Irrelevant | `htmlspecialchars()` on display_name output | Safe output encoding — unrelated to SQLi input path |

### 6. Rabbit holes and dead code

- Direct magic hash (e.g., `0e...` string) blocked by hex format check — must understand the array bypass.
- Direct INTO OUTFILE to `/var/www/html/` initially fails due to `secure_file_priv` — but `secure_file_priv` is discoverable as empty via SQLi information leak, and correct path must be found.
- `preview_import.php` — clearly labeled in code as using disabled entity loading.
- `session_restore.php` — `unserialize()` present but behind correct HMAC check.
- `upload/` directory is outside webroot — uploaded files not directly served.

### 7. Required source-review insights

- Understand PHP `$_REQUEST` superglobal precedence order for the specific PHP version/config.
- Trace `process_import.php` as the unsafe XML processing path (not the UI-facing import).
- Identify `secure_file_priv` discovery through SQLi, then find writable path.
- Read `.htaccess` to understand which file extensions are executable by Apache.

### 8. Setup / reset / seed

- MySQL `secure_file_priv` set to empty string in config — discoverable via SQL query.
- Upload directory outside webroot; uploads subdir under webroot created with `chmod 777` for INTO OUTFILE.
- Seed: three tenants, two admin users, ten customers, five queued XML documents.
- Reset: `php reset.php` — truncates job tables, re-seeds.

### 9. Learner-facing objective

> Flint CRM — v3.1.2
>
> Flint is a multi-tenant CRM for territory management and bulk contact import.
>
> **FLAG_LOCAL**: Gain admin-level authentication.
> **FLAG_PROOF**: Achieve remote code execution and read `/flag_proof.txt`.

### 10. Non-spoiler validation criteria

- Admin access demonstrated by reading FLAG_LOCAL from `/admin`.
- RCE demonstrated by reading FLAG_PROOF from filesystem.
- Learner documents the specific PHP request-handling behavior that enables the bypass.

### 11. Patch themes

- Token comparison: use `hash_equals()` with `random_bytes(32)` tokens; enforce type checking before comparison.
- XML processing: use consistent safe parser across all paths; never apply `LIBXML_DTDLOAD` in any path.
- File uploads: use `finfo` MIME check plus strict extension allowlist; store uploads outside webroot with a non-guessable name.

---

## LAB 66 — CHALK (PHP · MySQL)

### 1. Business story additions
Chalk is an internal knowledge base with content versioning and an approval workflow. Content is submitted, reviewed by admin, and published. Scheduled export jobs generate archives of approved content. File attachments can be submitted with articles. Content rendering uses a templating helper that processes stored data.

### 2. New files / routes / components to add

```
api/
  content.php           # JSON API with correct CSRF token validation
  export_trigger.php    # triggers export job (admin only, CSRF present)
admin/
  export_schedule.php   # schedule export — CSRF token present but not session-bound
  review_queue.php      # admin content review
workers/
  export_worker.php     # CLI-invoked export processor
includes/
  content_filter.php    # filter applied before storage
  safe_output.php       # output escaping helpers
  cors_middleware.php   # CORS allowlist — never require()d
migrations/
  001_initial.sql
  002_add_exports.sql   # references old `export_command` column — misleading
  003_remove_export_command.sql  # drops that column — but export_worker still builds commands
uploads/               # file attachments stored here (outside webroot)
```

### 3. New audit mechanisms

- **CSRF token not session-bound**: `export_schedule.php` generates a CSRF token and stores it in a static file-based location keyed only by token value, not by session. Token from one session can be used in another session's form submission.
- **SameSite not set**: session cookie in `session_start()` options has no `SameSite` attribute — cross-origin form POST carries the cookie.
- **Second-order SQLi trigger**: `export_worker.php` runs `SELECT display_name FROM users WHERE id = ?` (parameterized) then uses the result in a shell command via string interpolation. The stored display_name from the article author field is used in the archive filename construction.
- **Double-decoding in export_worker**: export_worker URL-decodes the stored `export_filter` value before passing to a shell function — filter applied before storage, decode after.
- **Upload outside webroot**: attachments stored in `/var/uploads/chalk/` — not web-accessible.
- **Low-impact SQLi**: public article search has a raw `LIKE` concatenation injectable — but MySQL user has no FILE privilege and `secure_file_priv` is non-empty.

### 4. Second-order behavior
`export_worker.php` is invoked by admin clicking "Run Export" in `export_schedule.php` (POST to `/admin/export_trigger.php`). The worker reads the export configuration from DB including the `export_filter` field, URL-decodes it, and passes it to a `zip` shell command. The filter was stored (and partially validated) at schedule-creation time by a regular user.

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | Command separator filter on `export_filename` input | Strips `;`, `&&`, `\|\|`, `\|`, `` $() `` — filename input is genuinely safe |
| Bypassable | Validation on `export_filter` before storage | Applied before URL-encoding; worker URL-decodes before shell use — double-decode gap |
| Irrelevant | XSS filter on article body | `htmlspecialchars()` applied on output — safe, unrelated to SQLi or CMDi paths |

### 6. Rabbit holes and dead code

- `uploads/` directory outside webroot — cannot serve uploaded PHP files.
- `api/content.php` has correct CSRF token (`hash_equals($_SESSION['csrf'], $token)`) — strong.
- Public article search SQLi cannot complete the objective due to MySQL privilege constraints.
- `migrations/002_add_exports.sql` shows an `export_command TEXT` column — suggests direct command injection; `003_remove_export_command.sql` drops it — but export_worker still builds commands from other fields.
- `cors_middleware.php` contains a correct allowlist implementation — never `require()`d anywhere.

### 7. Required source-review insights

- Trace `export_worker.php` input from user-supplied `export_filter` field through DB storage to shell command.
- Understand decode-order: validation pre-storage vs decode post-retrieval.
- Identify that CSRF token on `export_schedule.php` is not session-bound (different from `api/content.php`).
- Confirm uploads outside webroot make file-based RCE impossible.

### 8. Setup / reset / seed

- Seed: ten articles (five pending, three approved, two published), three export schedules, two users, one admin.
- Reset: `php reset.php`.
- `export_worker.php` is triggered via `/admin/export_trigger.php`; output written to `/tmp/chalk_exports/`.

### 9. Learner-facing objective

> Chalk Knowledge Base — v2.0.5
>
> Chalk is an internal knowledge base with an editorial workflow.
>
> **FLAG_LOCAL**: Gain admin access.
> **FLAG_PROOF**: Achieve remote code execution and read `/flag_proof.txt`.

### 10. Non-spoiler validation criteria

- Admin access demonstrated by reading FLAG_LOCAL from `/admin`.
- RCE demonstrated by reading FLAG_PROOF via OS command.
- Learner documents the second-order data flow from input to worker execution.

### 11. Patch themes

- CSRF: bind token to session server-side; do not allow cross-session token reuse.
- CMDi: use `escapeshellarg()` on all user-derived values before shell use; apply escaping after any decode step, not before.
- SQLi: convert article search to prepared statement.

---

## LAB 67 — SEAM (Spring Boot · PostgreSQL)

### 1. Business story additions
Seam is a multi-tenant project management SaaS. Teams belong to organizations (tenants). Reports are generated by a background scheduler with an elevated DB connection. A legacy XML-format API is supported for enterprise clients. A DTO mapper layer sits between the API and entity layer.

### 2. New files / routes / components to add

```
dto/
  ProjectCreateRequest.java     # explicit DTO, used on /api/projects
  UserUpdateRequest.java        # explicit DTO — one field over-included
  LegacyProjectXml.java         # XML model binding class
mapper/
  UserMapper.java               # maps UserUpdateRequest → User entity (over-posts one field)
service/
  ReportScheduler.java          # @Scheduled background job, elevated datasource
  TenantService.java            # tenant boundary enforcement
controller/
  LegacyApiController.java      # accepts application/xml
  ReportController.java         # report triggers
resources/
  application-dev.properties    # debug endpoints, actuator — inactive in prod
  application-prod.properties   # production config
  db/
    migration/
      V1__initial.sql
      V2__add_tenant_id.sql
      V3__feature_flags.sql     # adds `feature_flags JSONB` column to users
```

### 3. New audit mechanisms

- **DTO mapper over-posting**: `UserMapper.java` maps `UserUpdateRequest` to `User`. The mapper copies `organizationRole` from DTO to entity. `UserUpdateRequest` has `organizationRole` listed — not explicitly blocked. A user can elevate their organization role.
- **Content-type differential**: `LegacyApiController.java` registers `consumes = "application/xml"` and uses a different `ObjectMapper` bean (XML flavor) that does not enforce `@JsonIgnore` annotations from the JSON mapper — model binding surprises in XML path.
- **Blind SQLi in sort**: `ReportController` calls `reportRepository.findAllSorted(tenantId, sortField)` — `ReportRepository` implementation builds the ORDER BY from `sortField` using string format. The allowlist check `VALID_SORT_FIELDS.contains(sortField)` is in the controller; the repository method is also called directly from `ReportScheduler` which passes an internal value derived from user data stored at schedule creation time.
- **Elevated background job**: `ReportScheduler` uses a `SuperuserDataSource` bean (separate from the app datasource) to run report queries — necessary for cross-tenant aggregation. This datasource has superuser privileges.
- **Object-level authorization bug**: `DELETE /api/projects/{id}` in `ProjectController` checks `project.getOwner().getId().equals(currentUser.getId())` but the service method `projectService.archiveProject(id)` uses only the project ID without re-checking ownership.

### 4. Second-order behavior
`ReportScheduler.java` runs every 30 seconds. It reads the `report_schedule` table, fetches the `sort_preference` column (stored at schedule creation time by user), and calls `reportRepository.findAllSorted(tenantId, sortPreference)` using the superuser datasource. Trigger: POST to `/api/reports/schedules` to create a schedule with a specific `sort_preference`, then wait for scheduler or POST to `/api/admin/reports/trigger` (admin).

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | CREATE EXTENSION blocked in export endpoint | `BLOCKED_OPS.stream().anyMatch(b -> query.toLowerCase().contains(b))` includes `create extension` |
| Bypassable | Sort field allowlist | Allowlist checked in controller; repository called from scheduler bypasses controller |
| Irrelevant | XML DisallowDoctypeDecl on `/api/documents/parse` | Correct parser setting on an endpoint unrelated to the SQLi/UDF paths |

### 6. Rabbit holes and dead code

- `application-dev.properties` enables `/actuator/**` and a `/debug/sql` endpoint — container uses `spring.profiles.active=prod`.
- `reportRepository.findAll()` uses `@Query("SELECT r FROM Report r WHERE r.tenant = :tenant")` — JPA parameterized, safe.
- CREATE EXTENSION blocked by export endpoint blocklist.
- `LegacyApiController` looks like a deserialization/binding risk — but only accepts known DTO types; no polymorphic type fields.
- `V3__feature_flags.sql` adds `feature_flags JSONB` to user table — suggests feature flag exploitation, but flag values are not reflected in any privilege check in the current version.

### 7. Required source-review insights

- Trace the sort parameter from user input → controller allowlist → repository → SQL string construction.
- Find that `ReportScheduler` calls repository directly, bypassing controller allowlist.
- Identify that `SuperuserDataSource` is used by the scheduler.
- Trace `UserMapper.java` to find the over-posted field.
- Understand that the service layer `archiveProject` does not re-check authorization.

### 8. Setup / reset / seed

- Seed: three organizations, two tenants, five users (one admin, two org managers), ten projects, three report schedules.
- Reset: `./mvnw spring-boot:run -Dspring-boot.run.arguments=--seed`.
- Scheduler poll interval configurable via `APP_SCHEDULER_INTERVAL_MS` env var (default: 30000).

### 9. Learner-facing objective

> Seam Project Management — v4.2.0
>
> Seam is a multi-tenant project management platform for enterprise teams.
>
> **FLAG_LOCAL**: Gain elevated (admin or superuser-equivalent) access.
> **FLAG_PROOF**: Achieve remote code execution and read `/flag_proof.txt`.

### 10. Non-spoiler validation criteria

- Elevated access demonstrated by reading FLAG_LOCAL from admin endpoint.
- RCE demonstrated by reading FLAG_PROOF.
- Learner documents the trust boundary failure and second-order data flow.

### 11. Patch themes

- DTO mapping: explicitly enumerate mapped fields in mapper; do not copy role/privilege fields from user-controlled input.
- Blind SQLi: move sort field to a strict allowlist enforced at the repository layer, not only at the controller layer; never pass sort values through to scheduler from user-controlled storage.
- PostgreSQL UDF: constrain the report datasource to a non-superuser role; use `SECURITY DEFINER` only for specific functions.

---

## LAB 68 — CLINKER (Java · Jetty)

### 1. Business story additions
Clinker is a legacy enterprise document archival portal. Core servlets are available as source. A companion helper JAR (`clinker-helpers.jar`) is deployed in `WEB-INF/lib/` and must be decompiled for full analysis. A password reset flow shares the same PRNG class as session tokens. An internal document processor handles serialized job objects submitted by authenticated users.

### 2. New files / routes / components to add

```
src/
  SessionManager.java        # visible source — references ClinkerTokenFactory
  ResetServlet.java          # password reset token generation (same factory)
  DocumentServlet.java       # submit document processing job
  V2LoginServlet.java        # issues HMAC-signed tokens (new path, labeled secure)
  SafePreviewServlet.java    # URL preview with allowlist (rabbit hole)
  SafeImportServlet.java     # deserialization with ObjectInputFilter (rabbit hole)
WEB-INF/
  lib/
    clinker-helpers.jar      # compiled; contains ClinkerTokenFactory, SerializationHelper
  web.xml                    # servlet registration
build/
  pom.xml                    # commons-io listed as dependency but scope: provided
```

`ClinkerTokenFactory` inside the JAR:
- Visible source references it but shows only the interface.
- Decompilation reveals: uses `new java.util.Random(System.currentTimeMillis() / 1000)` — second-resolution seed, shared across session and reset token generation.
- `SerializationHelper` inside the JAR: wraps `ObjectInputStream.readObject()` with base64+gzip decode; no `ObjectInputFilter`; has a `readResolve`-like hook that logs deserialization events — side-effect visible in app logs.

### 3. New audit mechanisms

- **Compiled helper requirement**: `ClinkerTokenFactory.class` is only in the JAR. Session token prediction requires decompiling the JAR and reading the seed generation logic.
- **URI/URL parser mismatch**: SSRF validator uses `new java.net.URI(url).getHost()` for validation; HTTP fetch uses `new java.net.URL(url).openConnection()`. `URL` follows 3xx redirects; `URI` validates only the initial host. A redirect from a non-blocked host to `127.0.0.1` bypasses the check.
- **Serialization wrapper**: `SerializationHelper.deserialize(data)` takes a base64-gzip-encoded byte stream, decodes it, then calls `ObjectInputStream.readObject()`. Content-type `application/x-clinker-job` required on import endpoint.
- **Dead deserialization route**: `LegacyImportServlet` class exists in source but is not registered in `web.xml`.

### 4. Second-order behavior
`DocumentServlet` accepts POST to `/api/documents/process` with a base64-gzip-encoded job payload. It queues the payload in `document_jobs` table. A background thread (`JobProcessor`) calls `SerializationHelper.deserialize(payload)` every 15 seconds. Status visible at `/api/documents/status/<job_id>`. Log output from `SerializationHelper` visible at `/admin/logs`.

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | Localhost string check in SSRF validator | `uri.getHost().equals("localhost") \|\| uri.getHost().equals("127.0.0.1")` |
| Bypassable | URI used for validation, URL used for fetch | Redirect from allowed host to internal bypasses URI-level check |
| Irrelevant | Content-type check on import endpoint | `application/x-clinker-job` required — does not restrict payload content |

### 6. Rabbit holes and dead code

- `commons-io` in `pom.xml` as `<scope>provided</scope>` — not in the final WAR; any gadget chain requiring it will fail at runtime.
- `LegacyImportServlet.java` is in source but not in `web.xml` — never reachable.
- Visible `SessionManager.java` comments describe "migrated to ClinkerTokenFactory" — the old code shown uses `UUID.randomUUID()` (secure) but the JAR uses `Random`.
- `SafePreviewServlet` at `/safe-preview` — genuine allowlist, no bypass.
- `SafeImportServlet` at `/api/safe-import` — `ObjectInputFilter` with allowlist; receives no job payloads from normal workflow.
- Direct `127.0.0.1` SSRF blocked; `localhost` blocked — but redirect-based bypass remains.

### 7. Required source-review insights

- Decompile `clinker-helpers.jar` to read `ClinkerTokenFactory` seed generation.
- Identify URI vs URL handling difference in SSRF validator.
- Trace document job submission to `JobProcessor` to `SerializationHelper.deserialize`.
- Understand that `commons-io` is absent at runtime despite appearing in build file.

### 8. Setup / reset / seed

- Seed: five users, ten documents, three queued jobs.
- `clinker-helpers.jar` built from source at Docker build time (source not included in the image).
- Job processor interval configurable via `CLINKER_JOB_INTERVAL_MS` env var.
- Reset: POST to `/admin/reset` with admin session.

### 9. Learner-facing objective

> Clinker Document Archive — v2.7.3
>
> Clinker is a legacy enterprise document archival portal. Partial source is available; some components are compiled-only.
>
> **FLAG_LOCAL**: Gain admin-level access to the portal.
> **FLAG_PROOF**: Achieve remote code execution and read `/flag_proof.txt`.

### 10. Non-spoiler validation criteria

- Admin access via FLAG_LOCAL at `/admin`.
- RCE via FLAG_PROOF from filesystem.
- Learner must include: decompilation finding, redirect chain description, serialization wrapper analysis.

### 11. Patch themes

- Session tokens: replace `java.util.Random` with `SecureRandom`; separate session and reset token generation.
- SSRF: validate the final resolved IP after following redirects; block all RFC1918 ranges by IP, not by hostname string.
- Deserialization: add `ObjectInputFilter` with strict allowlist to `SerializationHelper`; reject unknown types.

---

## LAB 69 — SLAG (Express · MongoDB)

### 1. Business story additions
Slag is a team workspace platform with notification emails. Users configure workspace settings and notification preferences. A background email worker sends notifications by rendering Handlebars templates stored in user preferences. Workspace invites are token-based. A public workspace directory uses a separate Express router.

### 2. New files / routes / components to add

```
workers/
  email_worker.js       # renders notifications using stored preferences
middleware/
  cors_validator.js     # regex-based CORS origin check
routes/
  preferences.js        # GET/POST notification preferences (stores templateLayout)
  invites.js            # workspace invite token management
  public.js             # public workspace directory (separate CORS policy)
models/
  UserPreference.js     # Mongoose schema — includes templateLayout, notificationFrequency
  Workspace.js          # workspace model
migrations/
  001_seed.js
  002_add_preferences.js
```

### 3. New audit mechanisms

- **CORS regex**: `cors_validator.js` builds: `new RegExp(config.trustedOriginPattern)` where `trustedOriginPattern` is `"https://slag.internal"` (no anchors, no escape of `.`) — dot matches any character; `https://slagXinternal` would match.
- **Second body parser**: one route group uses `express.urlencoded({ extended: true })` — allows nested `a[b][c]` objects. This route group overlaps with the preferences endpoint that feeds the merge function.
- **Prototype pollution → authorization**: `merge()` is called on workspace settings; the middleware `authMiddleware.js` reads `req.user.isAdmin` from the request context object. Prototype pollution of `isAdmin` on `Object.prototype` before the auth check affects all subsequent requests in the same process.
- **Handlebars in worker only**: `email_worker.js` calls `Handlebars.compile(preference.templateLayout)(context)` using the 4.0.13 instance. Direct template endpoint (UI) uses `Handlebars.compile` with a fixed template string — safe because the template source is not user-controlled there.
- **Sanitizer on direct markers**: stored `templateLayout` field: filter strips `{{` and `}}` before storage. Handlebars `{{>partial}}` and `{{#with}}` syntax also stripped. However, the `layoutName` field (which selects a layout file by name) is not filtered — layout files can include partials with code execution potential.

### 4. Second-order behavior
`email_worker.js` polls `notification_queue` every 20 seconds. For each notification, it reads `user.preferences.templateLayout` (stored via `/api/preferences`) and calls `Handlebars.compile(templateLayout)(notificationContext)`. Trigger: POST to `/api/preferences` to store a layout value, then POST to `/api/notifications/test` to enqueue a test notification.

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | Direct `{{` / `}}` stripped from templateLayout field | Cannot store direct Handlebars expressions in templateLayout |
| Bypassable | CORS regex without anchors or escaped metacharacters | `new RegExp(pattern)` — dot unescaped, no anchors |
| Irrelevant | MongoDB exact-match on invite token lookup | `Invite.findOne({ token: req.params.token })` — not injectable via operator injection |

### 6. Rabbit holes and dead code

- Profile name in UI rendered with `{{escape name}}` Handlebars built-in — safe.
- `/api/public/workspaces` route has `cors({ origin: false, credentials: false })` — reflected origin disabled.
- NoSQLi on workspace search allows listing workspaces matching a regex, but ownership is enforced by a separate query — cannot bypass authorization.
- `merge()` constructor protection: `if (k === 'constructor') continue` — one path blocked; `__proto__` path open.
- Handlebars comment in source: `// using handlebars@4.7.8 — patched` — comment is wrong; `package.json` specifies `4.0.13`.

### 7. Required source-review insights

- Find `package.json` version and understand the comment is misleading.
- Trace `preferences.js` → MongoDB storage → `email_worker.js` rendering chain.
- Identify that direct template markers are blocked but layout/partial selection is not.
- Understand prototype pollution path from `merge()` to `authMiddleware` context.
- Find CORS regex pattern and test anchor/escape behavior.

### 8. Setup / reset / seed

- Seed: three workspaces, five users, ten notification preferences, ten queued notifications.
- Reset: `npm run seed:reset`.
- `email_worker.js` started as a child process at app startup; output logged to `/tmp/slag_email.log`.

### 9. Learner-facing objective

> Slag Workspace Platform — v3.0.1
>
> Slag is a team workspace and notification platform.
>
> **FLAG_LOCAL**: Gain admin-level access to the platform.
> **FLAG_PROOF**: Achieve remote code execution and read `/flag_proof.txt`.

### 10. Non-spoiler validation criteria

- Admin access demonstrated by FLAG_LOCAL at `/admin`.
- RCE demonstrated via FLAG_PROOF.
- Learner documents the second-order rendering chain and the CORS bypass mechanism.

### 11. Patch themes

- Prototype pollution: use `Object.create(null)` for merge target; block `__proto__`, `constructor`, `prototype` keys recursively.
- Handlebars: pin to patched version; sanitize all user-controlled template fields including layout/partial selection; use `Handlebars.SafeString` or compile-time escaping.
- CORS: use exact string comparison, not regex; anchor to scheme+host+port.

---

## LAB 70 — TUFF (Express · PostgreSQL)

### 1. Business story additions
Tuff is a DevOps collaboration platform with expression-based automation, real-time WebSocket channels, and a content review workflow. Flagged content is reviewed by an admin bot that visits URLs from a review queue. Expressions run in a sandbox with graduated privilege levels. WebSocket channels have room-based membership.

### 2. New files / routes / components to add

```
workers/
  review_bot.js         # admin context, visits flagged content URLs
middleware/
  csrf.js               # generates and validates CSRF tokens
  csp.js                # Content Security Policy headers
routes/
  expressions.js        # expression evaluator — two modes (sandbox, admin)
  review.js             # content review queue submission
websocket/
  channel_manager.js    # room-based WebSocket with client-trust bug
static/
  diagnostics.html      # DOM XSS sink in legacy filter UI
  profile.html          # DOMPurify-sanitized profile page
```

### 3. New audit mechanisms

- **WebSocket channel trust**: `channel_manager.js` assigns room membership from a client-supplied `room` field in the WebSocket handshake message. Room membership determines which diagnostic commands are available. Clients can claim membership in rooms they are not authorized for.
- **CSRF token on most routes, missing on one**: `csrf.js` generates and validates tokens for all `application/json` POST routes. One `application/x-www-form-urlencoded` POST route (`/team/promote`) generates but does not validate the token (validation code is commented out with `// TODO: re-enable`).
- **Expression sandbox**: `/api/expressions/evaluate` (public users) uses a restrictive regex blocklist. `/api/admin/scripts/run` uses `eval()` with a weaker check. Admin access required for the second endpoint.
- **Markdown sanitizer ordering**: link-preview route runs markdown-to-HTML conversion then applies XSS sanitizer. A specific markdown link syntax resolves to a `javascript:` URI during markdown parse but is rendered before the sanitizer strips `javascript:` from `href` values — order-dependent.
- **Admin bot**: `review_bot.js` fetches URLs from `review_queue` table using an admin session cookie. After visiting, it stores the HTTP response code and first 200 chars of response in the DB. Trigger: POST to `/api/content/report` to add URL to queue; result at `/api/review/result/<id>`.

### 4. Second-order behavior
`review_bot.js` polls `review_queue` every 15 seconds. It visits the stored URL using `axios.get(url, { headers: { Cookie: adminSessionCookie } })`. Response summary stored in `review_results`. Trigger: POST to `/api/content/report`, then GET `/api/review/status/<id>`.

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | Expression sandbox regex for public users | Blocks `require`, `process`, `exec`, `spawn`, `fork`, `child_process`, `__proto__`, `constructor` |
| Bypassable | Sanitizer applied after markdown transform | `javascript:` in markdown link survives markdown parse; sanitizer runs too late |
| Irrelevant | PostgreSQL parameterized query on activity log | `pg.query('SELECT * FROM activity WHERE user_id = $1', [userId])` — safe, unrelated |

### 6. Rabbit holes and dead code

- Direct WebSocket connection without HTTP Upgrade + session cookie rejected by channel manager.
- Profile page uses DOMPurify — safe.
- `GET /api/logs?q=` — parameterized PostgreSQL query, confirmed in source.
- Expression endpoint for non-admin users — regex blocks direct bypass; admin path requires admin access.
- CSP header present on most pages — blocks inline script in most contexts.
- Commented-out admin shell: `// app.post('/admin/shell', ...)` in `routes/admin.js`.

### 7. Required source-review insights

- Find the `/team/promote` CSRF validation gap (commented out).
- Understand admin bot behavior: what URLs it visits, what session it uses, what it stores.
- Trace markdown sanitizer order — when `javascript:` is resolved vs when it is stripped.
- Identify `channel_manager.js` room trust issue.
- Find admin expression endpoint and confirm it requires admin access first.

### 8. Setup / reset / seed

- Seed: five users, one admin, ten review queue entries, five WebSocket channels.
- Reset: `npm run seed:reset`.
- Review bot runs as background process; interval via `REVIEW_BOT_INTERVAL_MS`.

### 9. Learner-facing objective

> Tuff DevOps Platform — v1.5.0
>
> Tuff is a DevOps collaboration platform with expression automation and content review.
>
> **FLAG_LOCAL**: Gain admin-level access to the platform.
> **FLAG_PROOF**: Achieve remote code execution and read `/flag_proof.txt`.

### 10. Non-spoiler validation criteria

- Admin access demonstrated by FLAG_LOCAL at admin panel.
- RCE demonstrated by FLAG_PROOF.
- Learner documents the admin bot interaction and the CSRF validation gap.

### 11. Patch themes

- CSRF: enforce token validation on all state-changing routes regardless of content-type; bind token to session.
- Markdown/sanitizer: sanitize before rendering, or use an allowlist-based markdown renderer that never produces `javascript:` URIs.
- WebSocket authorization: validate room membership server-side using session data, never trust client-supplied room claims.

---

## LAB 71 — MARL (ASP.NET Core · SQLite)

### 1. Business story additions
Marl is an enterprise document gateway with a plugin architecture. A compiled plugin DLL (`MarlImportPlugin.dll`) handles document processing and is loaded at startup. Feature flags on user accounts control access to import and deserialization features. A legacy XML endpoint supports B2B integrations. Import status and error logs surface evidence of processing.

### 2. New files / routes / components to add

```
Plugins/
  MarlImportPlugin.dll          # compiled; contains deserialization wrapper and feature flag check
Controllers/
  LegacyXmlController.cs        # XML formatter, different model binding pipeline
  PublicController.cs           # public endpoints (safe CORS policy)
Models/
  UserClaims.cs                 # includes CanImport, DeserializerMode as claimable properties
Services/
  ImportStatusService.cs        # tracks import jobs, stores error messages
  PluginLoader.cs               # loads and invokes MarlImportPlugin
wwwroot/
  docs/
    integration-guide.html      # mentions legacy XML endpoint and feature flag names
appsettings.Production.json     # DeserializerMode default: "safe"
appsettings.Development.json    # DeserializerMode default: "compat" — not active in prod
```

`MarlImportPlugin.dll` (must be decompiled):
- `ImportProcessor.Process(byte[] data, string mode)` — if `mode == "compat"`, calls `BinaryFormatter.Deserialize`; if `mode == "safe"`, uses `XmlSerializer`.
- `mode` read from the current user's `DeserializerMode` claim.
- Default claim value in `UserClaims.cs` initialization code is `"safe"` — but model binding on user update endpoint does not block `DeserializerMode` from being set by the user.

### 3. New audit mechanisms

- **Model binding over-posting**: user profile update endpoint binds `UserUpdateModel` which maps to `UserClaims`. `DeserializerMode` field is present on the model — not excluded from binding.
- **CORS simple-request path**: CORS policy for API routes `AllowAnyOrigin()`. A `GET` request with no preflight (simple request) to a state-reading endpoint returns sensitive data without any preflight check. The Referer-based CSRF protection on `POST` endpoints only checks Referer presence, not value.
- **XXE through error log**: `LegacyXmlController` uses `XmlDocument` with `XmlUrlResolver`. XXE entity resolution errors (file not found, connection error) are written to `ImportStatusService`. Error messages visible at `/api/import/status/<id>`.
- **Plugin feature flag gate**: `MarlImportPlugin.Process()` checks `DeserializerMode` from user claims. If `"compat"`, `BinaryFormatter` path is used. The plugin is loaded via `PluginLoader.cs` which wraps the call.

### 4. Second-order behavior
POST document to `/api/documents/import` → job queued → `PluginLoader.InvokeAsync(jobId)` processes document on background thread using user's `DeserializerMode` claim at submission time → error/status at `/api/import/status/<id>`.

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | CORS preflight for non-simple methods | `OPTIONS` requests to dangerous endpoints return 403 |
| Bypassable | CORS origin suffix check | `origin.EndsWith(".marl.internal")` — not anchored to scheme+host |
| Irrelevant | JSON.NET endpoint uses `TypeNameHandling.None` | Safe configuration — unrelated to the plugin-based deserialization path |

### 6. Rabbit holes and dead code

- JSON.NET endpoint confirmed safe (`TypeNameHandling.None` in code).
- `appsettings.Development.json` sets `DeserializerMode: "compat"` by default — not active (container uses `ASPNETCORE_ENVIRONMENT=Production`).
- Direct XML response from normal JSON endpoint returns JSON (content negotiation selects JSON formatter).
- `PublicController` has correct named CORS policy with origin whitelist — different from default policy on API routes.
- `MarlImportPlugin.dll` also contains a `SafeImportProcessor` class that uses `XmlSerializer` — visible via decompilation, might look like the only code path.

### 7. Required source-review insights

- Decompile `MarlImportPlugin.dll` to find `DeserializerMode` check and `BinaryFormatter` path.
- Find `UserClaims.cs` `DeserializerMode` field and trace it through model binding on profile update endpoint.
- Understand that XXE surfaces only through error messages in import status, not direct response.
- Identify CORS suffix check pattern and find a valid bypass.

### 8. Setup / reset / seed

- Seed: three users, one admin, five documents, three queued import jobs.
- `MarlImportPlugin.dll` copied into `Plugins/` at Docker build time; source not included.
- Reset: `dotnet run --seed`.

### 9. Learner-facing objective

> Marl Document Gateway — v2.1.0
>
> Marl is an enterprise document gateway with plugin-based processing.
>
> **FLAG_LOCAL**: Gain admin-level access to the gateway.
> **FLAG_PROOF**: Achieve remote code execution and read `/flag_proof.txt`.

### 10. Non-spoiler validation criteria

- Admin access via FLAG_LOCAL at `/admin`.
- RCE via FLAG_PROOF.
- Learner must include decompilation finding and model binding trace.

### 11. Patch themes

- Model binding: explicitly exclude privilege-related fields from all user-facing update models; use separate admin-only DTOs for claim management.
- CORS: use exact-match origin allowlist anchored to full scheme+host+port.
- Deserialization: replace `BinaryFormatter` with a safe alternative; never use `BinaryFormatter` regardless of mode flag.

---

## LAB 72 — LOAM (ASP.NET MVC · SQLite)

### 1. Business story additions
Loam is an internal HR directory and workflow tool with tenant/department separation. Users register webhooks for external notifications. Background task processor runs deserialization on stored webhook payloads. A compiled helper DLL handles token generation. Old controller files are present but route-disabled.

### 2. New files / routes / components to add

```
BackgroundWorker/
  TaskProcessor.cs          # reads stored JSON payloads, deserializes with unsafe settings
Controllers/
  OldAdminController.cs     # route attributes commented out — class present, unreachable
  WebhookController.cs      # object ownership bug (tenant boundary)
Helpers/
  TokenHelper.dll           # compiled; partial source available showing old logic
Models/
  WebhookConfig.cs          # includes TenantId, OwnerId
  TaskPayload.cs            # stored JSON for background worker
Migrations/
  001_initial.sql
  002_add_webhooks.sql
  003_add_tasks.sql
  004_cleanup_old_admin.sql # drops old_admin_actions table — suggests old functionality removed
Views/
  Users/
    SearchSafe.cshtml        # parameterized search view
```

`TokenHelper.dll` (partial source + compiled):
- Partial source (`TokenHelper.cs.partial`) shows `Guid.NewGuid().ToString()` — looks secure.
- Compiled version uses `new Random(Environment.TickCount).Next().ToString("x")` — different from partial source, predictable.

`TaskProcessor.cs`:
- Reads rows from `task_queue` table — populated by `WebhookController` when a webhook is registered.
- Deserializes `payload_json` column using `JsonConvert.DeserializeObject<object>(payload, new JsonSerializerSettings { TypeNameHandling = TypeNameHandling.All })`.
- Controller-level binding uses `TypeNameHandling.None` — worker does not.

### 3. New audit mechanisms

- **Tenant boundary bug**: `WebhookController.UpdateWebhook(int id)` fetches webhook by `id` only — does not verify `webhook.OwnerId == currentUser.Id` or `webhook.TenantId == currentUser.TenantId`. Any authenticated user can modify another tenant's webhook config including the `payload_json` field.
- **SQLite blind SQLi**: `UsersController.Search` uses raw string concatenation. SQLite has no `SLEEP()` — but expensive operations via `randomblob(N)` produce measurable timing differences. Boolean-based blind extraction also possible via response length differences.
- **SSRF bypass**: `ToolsController.Preview` blocklist checks `uri.Host` contains `localhost` or `127.0.0.1`. `Uri` class in .NET does not normalize IPv6-mapped IPv4 addresses — `[::ffff:127.0.0.1]` parses as a different host string.
- **Worker JSON deserialization**: `TaskProcessor` uses unsafe `JsonSerializerSettings`; controller endpoint looks safe. Worker reads `payload_json` stored by the webhook update endpoint.
- **Partial source mismatch**: `TokenHelper.cs.partial` shows secure `Guid.NewGuid()`. Compiled DLL uses `Random(TickCount)`. The source comment says `// TokenHelper.dll compiled from this source` — incorrect.

### 4. Second-order behavior
`TaskProcessor` polls `task_queue` every 20 seconds. Rows inserted when webhook is registered or updated. `payload_json` column value deserialized with unsafe settings. Trigger: PUT to `/webhook/{id}` to update `payload_json`, then wait for worker or GET `/api/tasks/status` (shows queue depth and last processed ID).

### 5. Filtering behavior

| Role | Filter | Behavior |
|---|---|---|
| Blocking | SQLite stacked queries not supported | `; DROP TABLE` style attacks impossible — confirmed in SQL engine behavior |
| Bypassable | SSRF blocklist string comparison | `[::ffff:127.0.0.1]` not in blocklist — .NET `Uri` parses differently from string check |
| Irrelevant | Controller JSON binding uses `TypeNameHandling.None` | Safe at controller layer — worker layer uses different settings |

### 6. Rabbit holes and dead code

- `OldAdminController.cs` — class present, `[Route]` attributes commented out, never reachable.
- `Controllers/UsersController.cs` `SearchSafe` action — Dapper parameterized query, genuinely safe.
- `ToolsController.CheckUrl` — allowlist-based URL checker, genuinely safe.
- `AdminController.Config` action uses `TypeNameHandling.None` — safe, adjacent to vulnerable worker.
- `migrations/004_cleanup_old_admin.sql` — `DROP TABLE old_admin_actions` — suggests old admin functionality removed, misleading.
- `TokenHelper.cs.partial` source does not match compiled DLL behavior.

### 7. Required source-review insights

- Decompile `TokenHelper.dll` and compare with partial source to find the discrepancy.
- Trace webhook payload from `WebhookController.UpdateWebhook` → `task_queue.payload_json` → `TaskProcessor` deserialization.
- Find tenant ownership check missing in webhook update path.
- Identify SQLite timing primitive (`randomblob`) for blind extraction.
- Find IPv6-mapped address SSRF bypass by testing .NET `Uri` parsing behavior.

### 8. Setup / reset / seed

- Seed: three tenants, five users, three webhooks per tenant, ten task queue entries.
- `TaskProcessor` runs as `IHostedService` background task.
- Reset: `dotnet run --environment Development --seed`.

### 9. Learner-facing objective

> Loam HR Directory — v1.8.2
>
> Loam is an internal HR directory and workflow automation tool with webhook integrations.
>
> **FLAG_LOCAL**: Gain admin-level or cross-tenant access.
> **FLAG_PROOF**: Achieve remote code execution and read `/flag_proof.txt`.

### 10. Non-spoiler validation criteria

- Admin/cross-tenant access demonstrated by FLAG_LOCAL.
- RCE demonstrated by FLAG_PROOF.
- Learner documents tenant boundary failure and worker deserialization chain.

### 11. Patch themes

- Object ownership: always verify `OwnerId` and `TenantId` match the authenticated user on every mutating operation.
- JSON.NET: never use `TypeNameHandling.All` or `TypeNameHandling.Objects/Arrays` without a type binder allowlist.
- SSRF: resolve hostnames to IPs before comparison; block all RFC1918 and loopback ranges by IP, not by hostname string.
- Token generation: replace predictable `Random(TickCount)` with `RandomNumberGenerator`.

---

## REPORT_GUIDE.md Template (non-spoiler, for all labs)

Each lab directory should contain a `REPORT_GUIDE.md` with the following structure. **Do not include solutions.**

```markdown
# Lab Report Guide

## What to document

For each objective (FLAG_LOCAL and FLAG_PROOF), document the following sections:

### Entry Point
- Which endpoint, parameter, file, or feature was the starting point?
- How did you identify it required further analysis?

### Source-to-Sink Trace
- List the files and functions your input passes through, in order.
- Identify where the trust boundary is crossed.
- Note which layer (controller / service / repository / worker) each step occurs in.

### Relevant Files and Functions
- List every file that is part of the exploitable data flow.
- Include files that appeared relevant but turned out to be safe — explain why they were rejected.

### Trust Boundary Failure
- What assumption did the application make that was incorrect?
- Which component trusted data that it should not have?

### Filter Behavior
- Which filters did you encounter?
- For each: did it block, allow, or misdirect your analysis?
- If a filter was bypassable: what property of the framework or parser enabled the bypass?

### Second-Order Trigger
- What stored the data?
- What read and processed it later?
- How did you trigger the processing step?
- How did you confirm execution?

### Impact
- What could an attacker achieve with this finding?
- What data is accessible?
- Is the impact limited to this application or does it extend further?

### Minimal Patch Theme
- What is the root cause?
- What is the minimal category of fix that would prevent this class of issue?
- (Do not describe the exact fix — describe the principle.)

### Rabbit Holes Rejected
- List each dead end you investigated.
- For each: what made it look interesting, and what made you reject it?
- Cite the specific source evidence that confirmed it was a false path.

## Format
- Write in plain text or Markdown.
- Cite file paths and line numbers where applicable.
- Do not include request/response dumps in the report — describe the interaction in terms of parameters and fields.
```

---

## Coverage Matrix

| Capability | Stratum | Vein | Flint | Chalk | Seam | Clinker | Slag | Tuff | Marl | Loam |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Source-code auditing | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Auth/session/token weakness | ✓ | | ✓ | | | ✓ | | | | ✓ |
| Object authorization | | | | | ✓ | | | | | ✓ |
| Tenant boundary | | | ✓ | | ✓ | | | | ✓ | ✓ |
| CORS/CSRF | ✓ | | | ✓ | | | ✓ | ✓ | ✓ | |
| Blind SQLi | ✓ | | ✓ | ✓ | ✓ | | | | | ✓ |
| Data extraction | ✓ | ✓ | ✓ | | ✓ | | | | ✓ | ✓ |
| File upload/filter bypass | | | ✓ | ✓ | | | | | | |
| File read/write primitive | ✓ | ✓ | ✓ | | ✓ | | | | ✓ | |
| XXE | | ✓ | ✓ | | | | | | ✓ | |
| SSRF | | ✓ | | | | ✓ | | ✓ | | ✓ |
| SSTI | ✓ | | | | | | ✓ | | | |
| Prototype pollution | | | | | | | ✓ | | | |
| Deserialization | | | | | | ✓ | | | ✓ | ✓ |
| PostgreSQL UDF/large objects | | ✓ | | | ✓ | | | | | |
| Regex/character filter bypass | ✓ | | ✓ | ✓ | ✓ | | ✓ | ✓ | | |
| DOM/persistent XSS | | | | | | | | ✓ | | |
| WebSocket command path | | | | | | | | ✓ | | |
| Background job / second-order | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Parser differential | ✓ | ✓ | ✓ | | ✓ | | ✓ | ✓ | ✓ | ✓ |
| Decompilation/source recovery | | | | | | ✓ | | | ✓ | ✓ |
| Framework request-parsing edge case | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Report-writing practice | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

---

## Realism pass notes

All ten designs pass the realism check:

- No lab has an issue visible from a single obvious parameter name.
- No route name reveals the bug category.
- No lab can be completed by submitting a generic public payload without source review.
- Every lab requires tracing input across at least three layers.
- Every lab has at least three plausible false paths.
- Every lab has at least one framework-specific nuance (parser behavior, superglobal precedence, URI normalization, annotation scanning, model binding).
- Every lab has at least one second-order behavior with a deterministic trigger.

## Solvability pass notes

- All background jobs have configurable intervals and deterministic trigger mechanisms (admin endpoint or queue depth polling).
- All blind behaviors have a measurable signal (timing, response size, error message in status endpoint).
- All compiled components are built at Docker build time and present at runtime.
- All required filesystem permissions are set in Dockerfile.
- All internal services are on loopback (reachable via SSRF, not via direct external request).
- No objective depends on a race condition.
- All false paths are false for reasons verifiable in source code.
