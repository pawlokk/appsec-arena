# Final Audit Pass — AppSec Arena labs 63–72

## Frame

**Outcome:** Suite at peak OSWE-overprep quality — no spoiler comments, all mechanics reachable, 66 PASS.

**Success line:** `validate_labs.sh` exits 0 with 66 PASS; zero spoiler terms in lab source across labs 63–72.

**Type:** build

---

## Goal 1 — Reachability Audit

| Lab | DB | Workers | Routes | Observation channel | Status |
|-----|----|---------|---------|--------------------|--------|
| 63-stratum | PostgreSQL (service start in CMD) | `report_worker.py` started with `&` | All mounted in `app.py` | `import_jobs.error_text` via `/api/import/status/<id>` | PASS |
| 64-vein | PostgreSQL | `cdp_sim.py`, `import_worker.py`, `report_bot.py` all started in CMD | All mounted | `import_jobs.error_text`, `report_results` | PASS |
| 65-flint | MariaDB (service start in CMD) | None needed (sync trigger) | All mounted in Apache | `admin/import_status.php` shows import_queue errors | PASS |
| 66-chalk | MariaDB | `export_worker.php` invoked synchronously via `api/export_trigger.php` | All mounted | `admin/audit.php` shows export activity | PASS |
| 67-seam | PostgreSQL | `ReportScheduler` runs as Spring `@Scheduled` bean within JAR | All mounted via `@RestController` | Admin `/api/admin/export` returns query errors | PASS |
| 68-clinker | H2 in-memory | None needed (request-driven) | All registered in `ClinkerApp.java` | `ImportServlet` returns error type in response | PASS |
| 69-slag | MongoDB | `email_worker.js` started in CMD | All mounted in `app.js` | Notification queue / email worker log | PASS |
| 70-tuff | PostgreSQL | `review_bot.js` started in CMD | All mounted in `app.js` | `review_results` table polled via `/api/reviews/results` | PASS |
| 71-marl | SQLite (init in CMD) | None needed (request-driven, `import_jobs` table) | All mapped in `Program.cs` | `import_jobs.error_text` polled via `/api/imports/status/<id>` | PASS |
| 72-loam | SQLite | `TaskProcessor` registered as `IHostedService` | All mapped in `Program.cs` | Task queue results observable via admin panel | PASS |

All reachability checks: **PASS**

---

## Goal 2 — Anti-Spoiler Scrub

Files modified in this pass (spoiler comments/names removed):

**lab-63-stratum:**
- `app.py`: "SandboxedEnvironment prevents SSTI" → neutral docstring

**lab-64-vein:**
- `app.py`: Removed "The real XXE sink is /upload", "The vulnerable SSRF endpoint is /api/fetch", "SSRF protection" comment, "prevents XXE" in docstring
- Section headers scrubbed of attack-class names

**lab-65-flint:**
- `import.php`: Full XXE walkthrough block comment → 1-line description; removed "Deceptive: Content-Type check looks like a security gate but is unrelated to XXE"; removed "VULN: LIBXML_NOENT resolves external entities — XXE"
- `search.php`: Full SQLi walkthrough block comment → 1-line description; removed "Deceptive filter" comment; removed "VULN:" inline; removed "Raw query — SQLi sink"
- `admin.php`: Removed "VULN: raw interpolation — SQLi sink (INTO OUTFILE → webshell)"
- `session_restore.php`: Removed "NOT exploitable via deserialization without the HMAC key"; removed "safe to deserialize"
- `xml_viewer.php`: Removed "NOTE: The real import endpoint ... Students who find this safe viewer..."; removed "XXE protected" from note div and HTML

**lab-66-chalk:**
- `api/change_password.php`: Removed "The legacy /admin/settings.php has no token — that is the vulnerable path"

**lab-67-seam:**
- `ReportController.java`: Removed "NOTE: @Valid + CategoryRequest DTO exists but is NOT used here. The raw @RequestParam bypasses all DTO validation entirely"; removed "Vulnerable: category is interpolated directly"; removed "Parameterized title search — safe from SQLi. The vulnerable endpoint is GET /api/reports?category="
- `RegisterDto.java`: Removed "The vulnerable POST /api/register uses @RequestBody User directly — no DTO"
- `ReportScheduleController.java`: Removed "Allowlist enforced at controller level — not at repository/scheduler level"; removed "Allowlist check here — but ReportScheduler calls repository directly, bypassing this"
- `AdminController.java`: Removed "weak blocklist: blocks DROP/DELETE but not CREATE FUNCTION or COPY TO PROGRAM"; removed "Weak blocklist — looks like protection but allows CREATE FUNCTION, COPY TO PROGRAM"; removed "useful for blind exploitation feedback"

**lab-68-clinker:**
- `SafeImportServlet.java`: Removed "TODO: migrate to SafeImportServlet (see ImportServlet — no filter, vulnerable)"; removed "The real /api/import has no ObjectInputFilter and is vulnerable to gadget chains"
- `SafePreviewServlet.java`: Removed "The vulnerable PreviewServlet at /preview uses a string-contains blocklist (localhost, 127.0.0.1) which is bypassable"; removed "Don't follow redirects to bypass allowlist"
- `InternalServlet.java`: Removed "Intended for service-to-service communication but reachable via SSRF"
- `SecureSessionManager.java`: Removed "The exploitable cookie forgery works against the legacy format"
- `V2LoginServlet.java`: Removed "The exploitable cookie forgery works against the unsigned legacy tokens"
- `ImportServlet.java`: Removed "Size check — deceptive: looks like security but ysoserial payloads are small"; removed "No ObjectInputFilter — vulnerable to gadget chains"; removed "Still return 500 even if RCE payload executed"

**lab-69-slag:**
- `app.js`: Removed "prototype pollution prevented" from safeMerge comment; removed "CORS middleware — reflects any origin with credentials (vulnerability #1)"; removed "vulnerable to prototype pollution via __proto__"; removed "Safe merge - prototype pollution prevented / Compare with ... which uses the vulnerable merge()"; removed "regex match doesn't bypass auth. Time sink for students"; removed "regex-based, bypassable"
- `package.json`: Scrubbed description from "CORS misconfiguration + Prototype Pollution + Handlebars SSTI" to "collaborative workspace platform"

**lab-70-tuff:**
- `app.js`: Removed "CSRF vulnerability — No CSRF token check"; removed "Vuln: any logged-in user can promote themselves"; removed "AST-based analysis: reject dangerous call expressions"; removed "'Safety' filter — blocks literal strings only; bracket notation bypasses this"; removed "Admin only. Safety check blocks naive keywords but not bracket-notation bypasses"; removed "The admin /api/eval endpoint has a weaker filter — compare carefully"

**lab-71-marl:**
- `Program.cs`: Removed vulnerability enumeration block comment (XXE, BinaryFormatter, CORS)
- `Models.cs`: Removed "no type metadata, no gadget surface" from ConfigImportDto comment

**lab-72-loam:**
- `AdminController.cs`: Removed "Uses TypeNameHandling.None — no $type support, no gadget chain possible / The vulnerable Import below uses TypeNameHandling.All"; removed "VULNERABLE: TypeNameHandling.All allows arbitrary type instantiation via $type"; removed "Deceptive check 1/2" labels; removed safe-deserialization explainer
- `ToolsController.cs`: Removed "No SSRF possible here / The vulnerable SSRF endpoint is Preview"
- `UsersController.cs`: Removed "VULNERABLE: direct string concatenation — blind time-based SQLi via randomblob()"

---

## Goal 3 — Realism / Rabbit-hole checklist

All 10 labs confirmed to have:
1. Unreachable dangerous function (dead code / commented-out)
2. Disabled/dev-only route or config file
3. Safe sink with constant/sanitized data
4. Stale config/migration pointing toward wrong conclusion
5. Low-impact real bug that doesn't complete objective
6. Safe implementation adjacent to unsafe implementation
7. Filter that correctly blocks obvious input
8. Filter bypassable via context/canonicalization/parser mismatch
9. Irrelevant distracting filter

---

## Goal 4 — Stability

`bash tools/validate_labs.sh` → **66 PASS, 0 WARN, 0 FAIL** (verified before and after changes)

---

## Goal 5 — Final validation result

**66 PASS, 0 FAIL** — suite is clean and stable.

Confidence: HIGH. All spoiler terms removed from labs 63–72 source; reachability verified; workers confirmed started; observation channels confirmed accessible; filter categories all present.

Pending human judgment: None — this pass was purely cosmetic (comment/naming scrub) with zero logic changes. All lab mechanics are unchanged from the prior session's 66 PASS baseline.
