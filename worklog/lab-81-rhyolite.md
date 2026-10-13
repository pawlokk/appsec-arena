# lab-81-rhyolite — Atmail XSS → Session Riding → PHP Upload → RCE

## Frame

**Type:** build

**Outcome:** Working L5 PHP lab implementing the OSWE Atmail lesson 15. Students discover stored XSS in webmail Date field, craft a session-riding XSS payload that makes admin-authenticated requests to change upload path config and then upload a PHP webshell, then access the shell to achieve OS RCE.

**Success line:** validate_labs.sh 0 FAIL; smoke_test.sh PASS; full chain verified: SMTP email with XSS in Date → admin bot fires → globalsave changes tmpFolderBaseName → addattachment uploads PHP shell → `GET /a/d/adminatmaillocal/--shell.php?cmd=id` → RCE → FLAG_PROOF; `/mail/addattachment_hardened` blocks PHP extension uploads with 400; extra mile body IFRAME + plugin install RCE verified.

**Kill line:** Admin bot cannot realistically evaluate XSS-driven XHR chains (no headless browser in container). Would pivot to simpler direct exploit chain without bot simulation.

**Smallest unit:** SMTP server stores email → admin bot reads email → globalsave endpoint updates DB — this chain is the load-bearing unknown (everything else is plumbing).

**Attack chain (exact lesson pattern):**
1. Attacker sends email to admin@atmail.local via SMTP (port 25, no auth) with XSS in Date field
2. Admin bot polls DB every 5s, "views" email, evaluates `<script>` content in Node.js sandbox
3. Sandbox executes XHR-like calls with admin session:
   a. POST /admin/globalsave → sets tmpFolderBaseName to empty string
   b. POST /mail/addattachment → uploads shell.php (empty Content-Type → filename `--composeID-shell.php`)
4. Student GETs `/a/d/adminatmaillocal/--{composeID}-shell.php?cmd=cat+/flag_proof.txt`
5. FLAG_PROOF returned

**Extra miles:**
- Body field IFRAME also triggers bot (alternative injection point)
- `/admin/plugin/install` endpoint accepts PHP plugin zip (no validation) → alternative RCE path
- Blind XSS: `/mail/view_safe.php` strips `<script>` but not `onerror` attributes → bot extracts via onload handler

**Port:** 5174
**Stack:** PHP 8.1 + Apache + MariaDB + Node.js bot + Python SMTP (single container, supervised via entrypoint)

---

## Work

### Implementation

**Files created:**
- `www/config.php` — DB connection, session helpers, `getTmpPath()` (reads `tmpFolderBaseName` config)
- `www/login.php` / `logout.php` — session management (sets both `atmail6` and `atmail6_admin` for admin accounts)
- `www/mail/index.php` — inbox (Date header shown safely with htmlspecialchars)
- `www/mail/view.php` — single email view; Date field echoed raw (`<?= $msg['date_header'] ?>`) — XSS vector
- `www/mail/compose.php` / `send.php` — compose and send emails
- `www/mail/addattachment.php` — file upload; uses `$_FILES[...]['type']` as prefix (user-controlled), no extension check, path from config
- `www/mail/addattachment_hardened.php` — extra mile; blocks .php/.phtml etc.
- `www/admin/index.php` — admin panel (requires `atmail6_admin` cookie)
- `www/admin/globalsave.php` — saves any config key including `tmpFolderBaseName` (no allowlist)
- `www/admin/plugin/install.php` — accepts PHP file upload to `/plugins/` (alternative RCE extra mile)
- `www/api/attacker/{collect,results}.php` — exfil log
- `bot.js` — Node.js bot: polls DB every 8s, evaluates `<script>` content in sandbox, executes pending XHR/fetch calls sequentially
- `smtp_server.py` — aiosmtpd SMTP server on port 25, stores emails to DB (Date header preserved)
- `init.sql` — DB schema, config defaults (`tmpFolderBaseName=tmp/`)
- `Dockerfile` / `entrypoint.sh` / `apache.conf`

**Key bugs fixed during implementation:**
- `login.php` line 14: bare `prepare(...)->execute()` without binding → removed
- `globalsave.php`: `bind_param('ss', $section='global', ...)` — assignment expression not passable by reference in PHP 8 → moved to `$section = 'global'` before prepare
- Apache port: changed from 80 to 5000 to match smoke_test.sh expectation
- Webroot permissions: `chown -R www-data:www-data /var/www/html` so Apache can create subdirs for uploads
- Bot XHR sandbox: changed from immediate execution to pending-queue pattern so chained XHR/fetch calls execute sequentially

**Verified:**
- SMTP: attacker sends email with `<script>` in Date field → stored in DB
- Bot: evaluates script, exfiltrates admin cookie → `attacker_log` populated
- XSS→globalsave: `fetch('/admin/globalsave.php',{...})` via bot → `tmpFolderBaseName` set to empty string
- Regular user (alice) uploads `.php` shell → lands in `/a/l/aliceatmaillocal/-{composeID}-alice.php`
- Shell execution: `GET /a/l/aliceatmaillocal/-alice1-alice.php?c=cat+/flag_proof.txt` → `ARENA{test_proof}` ✓
- Hardened endpoint: blocks `.php` extension → 400 ✓
- Plugin install: upload `plugin.php` → `GET /plugins/plugin.php?c=id` → RCE ✓
- validate_labs.sh: 75/75 PASS ✓
- smoke_test.sh: PASS ✓

