# verify-extra-miles

## Frame

**Type:** build (correctness check — must reproduce)

**Outcome:** All 5 designated OSWE-course labs have their extra-mile primitives correctly wired at the source level and will compile/run without errors.

**Labs in scope:**
- lab-67-seam (ManageEngine) — Java deserialization at /api/v1/partner/state-restore
- lab-68-clinker (openCRX) — security_alerts table + AlertServlet + reset insertion
- lab-70-tuff (openITCockpit) — all primitives (no changes this session, verify existing)
- lab-71-marl (DotNetNuke) — XmlSerializer cookie deserialization (no changes, verify existing)
- lab-63-stratum (ERPNext) — SQLi + Jinja2 sandbox bypass (no changes, verify existing)

**Success line:** Every source-level check passes:
- New Java files are syntactically valid and reference only available symbols
- New routes are registered and permitted correctly
- DB schema additions are syntactically correct SQL
- No broken imports or missing class references
- validate_labs.sh exits 0 (all LABS entries valid against Dockerfile/registry)

**Kill line:** A file has an unresolvable compile error (missing import, wrong method signature, missing class) that can't be fixed within the existing dep set.

**Smallest unit:** Compile-check each new Java file by tracing imports and method signatures against the classpath, then run validate_labs.sh.

**Proof:** Paste grep/read output showing each check passes; paste validate_labs.sh exit code.

---

## Receipt

### validate_labs.sh
```
[PASS] lab-63-stratum
[PASS] lab-67-seam
[PASS] lab-68-clinker
[PASS] lab-70-tuff
[PASS] lab-71-marl
Summary: 75 PASS, 0 WARN, 0 FAIL
```

### lab-67-seam (ManageEngine) — Java deserialization
- `SyncRestoreController.java`: imports only `org.springframework.http.*`, `org.springframework.web.bind.annotation.*`, `java.io.*`, `java.util.Map` — all on classpath via spring-boot-starter-web ✓
- `@RequestBody byte[]` — handled by Spring's `ByteArrayHttpMessageConverter` (supports `*/*`) ✓
- `consumes = "application/x-java-serialized-object"` — Spring matches this to ByteArrayHttpMessageConverter ✓
- `commons-collections:3.1` added to pom.xml — Maven fetches from Central at build time (no --network=none in Dockerfile) ✓
- `/api/v1/partner/state-restore` added to `permitAll` in SecurityConfig ✓
- Two `@RestController`s sharing `/api/v1/partner` base path — Spring merges them, no method overlap ✓

### lab-68-clinker (openCRX) — security_alerts + AlertServlet
- `security_alerts` DDL in `Database.java` — valid H2 SQL, matches mode=PostgreSQL ✓
- `ResetServlet.java` uses `java.sql.*` (import already present); new alert INSERT uses existing `Connection` in same try-with-resources block ✓
- `AlertServlet.java` — null-checks `s == null` before `s.isAdmin()` (no NPE risk) ✓
- `getPathInfo()` on `"/api/alerts/*"` mapping: returns `null` or `"/"` for clear-all, `"/N"` for delete-by-id; `parseInt` failure → 400 ✓
- `AlertServlet` registered at `/api/alerts/*` in `ClinkerApp.java` ✓

### lab-70-tuff (openITCockpit) — existing primitives
- 10 references to key primitives: `sudo_server`, `execute_nagios_command`, `su tuffmon`, `MONITOR_KEY`, `tuff-core`, `document.write`, `build=` ✓
- 12 references to extra-mile primitives: `check_http`/`check_ping` in plugins, `/app/config/tuff.conf`, `/team/promote`, `monitor_commands`, `/monitor/run` ✓

### lab-71-marl (DotNetNuke) — existing deserialization
- `marl_pref` cookie → `LoadXml` → `Type.GetType` → `XmlSerializer` → `Deserialize` all present ✓

### lab-63-stratum (ERPNext) — existing SQLi + SSTI
- `/api/content/search` — unauthenticated (no `@login_required`), f-string `LIMIT {lm} OFFSET {st}` confirmed ✓
- `jinja2.sandbox.SandboxedEnvironment` at `api/template/preview` confirmed ✓

**All checks passed. No fixes required.**

---

## Verdict

**APPROVE** — independent auditor confirmed all receipt claims. No blocking issues.

Auditor notes (non-blocking):
- `ByteArrayHttpMessageConverter` supports `*/*` → `application/x-java-serialized-object` matched correctly, no custom converter needed
- Jetty dispatches `DELETE` to `doDelete()` per Servlet spec — works as written
- ResetServlet UPDATE + INSERT share same Connection under JDBC auto-commit; if INSERT fails, UPDATE already committed. Minor consistency gap, consistent with rest of codebase. Not a blocker.

Commit: aa9d58f
