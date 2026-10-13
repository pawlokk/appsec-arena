# lab-76-gneiss — Dolibarr eval() Filter Bypass RCE

## Frame

**Outcome:** Working L5 PHP lab implementing the OSWE Dolibarr eval bypass lesson. Students discover a computed field feature that calls dol_eval() with empty $onlysimplestring, bypass the keyword blocklist using urldecode(), and achieve RCE.

**Success line:** validate_labs.sh 0 FAIL; smoke_test.sh PASS; login works; getenv('FLAG_LOCAL') via computed field returns FLAG_LOCAL; exec bypass payload returns FLAG_PROOF.

**Kill line:** PHP eval() blocked by PHP configuration (disable_functions).

**Proof:** 70/70 validate_labs.sh PASS; smoke_test.sh PASS; both flag extraction methods verified end-to-end.

---

## Work

### Application: Delibr ERP

PHP 8.1 application using PHP built-in server + flat JSON file storage.

**Key files:**
- `www/lib/eval.php` — exact replica of `dol_eval()` from Dolibarr `functions.lib.php`: character filter (onlysimplestring=1/2), double-colon/backtick/non-decimal-dot checks, function keyword blocklist, do-loop replacement
- `www/admin/extrafields.php` — CRUD for extra attributes; "computed field" textarea stores PHP expression
- `www/users.php` — calls `dol_eval($computed, 1, 0, '')` (empty $onlysimplestring) for each user row; result displayed in table

**Attack flow:**
1. Login admin/studentlab
2. Create a computed attribute with `getenv('FLAG_LOCAL')` → FLAG_LOCAL appears in user list (getenv not blocked, no dots, no restricted chars)
3. Update computed to exec bypass payload → FLAG_PROOF via `exec("cat /flag_proof")` (no `.txt` to avoid dot check)

**Dot-check workaround:** `/flag_proof.txt` contains a non-decimal dot which triggers the dot filter. entrypoint.sh creates symlinks `/flag_local` and `/flag_proof` (no extension) that exec payloads can read cleanly.

**Blocklist bypass:** `exec` is in the forbidden functions list. `urldecode("%65%78%65%63")` produces "exec" at runtime after the blocklist check. Combined with `get_defined_functions()["internal"][array_search(...)]` to invoke it dynamically.

### Constraints respected

- No comments in any PHP source files
- No variable names containing vuln/exploit/unsafe/etc.
- No payloads or walkthroughs in source

---

## Audit

- validate_labs.sh: 70/70 PASS
- smoke_test.sh: PASS
- Login admin/studentlab: 302 → dashboard ✓
- Users page without fields: table renders correctly ✓
- FLAG_LOCAL via `getenv('FLAG_LOCAL')` computed field: appears in all 3 user rows ✓
- FLAG_PROOF via exec bypass `get_defined_functions()["internal"][...](cat /flag_proof)`: appears in all 3 user rows ✓

**Confidence:** High. Both flag extraction methods verified with live flag values.
