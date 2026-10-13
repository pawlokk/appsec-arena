# Platform Improvements — AppSec Arena

## Frame

**type:** build

**Outcome:** AppSec Arena platform is hardened, extensible, and self-validating — port registry is clean, all labs carry category+type metadata, UI has a data-driven category filter, tooling validates and smoke-tests labs, documentation covers lab authoring, and all L3/L4 hints reference real source locations.

**Success line:** `tools/validate_labs.sh` exits 0 with 0 FAILs on all 55 labs; category filter renders in the browser with at least one pill ("oswe-prep") built from Jinja2 `categories` variable; all 19 L3/L4 hint files have tier2 text containing a file name and function/line reference; `docs/adding-a-lab.md` and both `tools/` scripts exist and are `chmod +x`.

**Kill line:** Any task requires modifying a lab's Dockerfile or app source beyond what tasks 1–3 spec (only platform/ files and hint JSONs are in scope for edits).

**Smallest unit:** Task 1 — remove 7 stale entries (labs 11–17) from `platform/port_registry.json`. Zero-risk, confirms edit pipeline.

**Proof:**
```bash
bash tools/validate_labs.sh
# → Summary: 55 PASS, N WARN, 0 FAIL
# → exit code 0

# UI: start platform, open browser, confirm category filter group visible
python platform/app.py &
curl -s http://localhost:8080/ | grep -i "oswe-prep"

# Hints: spot-check
cat hints/lab-20.json | python3 -m json.tool
# → tier2 should contain a filename like "app.js:NN" or similar

ls -la tools/validate_labs.sh tools/smoke_test.sh docs/adding-a-lab.md
# → all present, executable
```

---

## Tasks

- [ ] 1. Port registry cleanup (platform/port_registry.json)
- [ ] 2. Add category + type to all LABS entries + update routes (platform/app.py)
- [ ] 3. Category filter UI (platform/templates/index.html)
- [ ] 4. Lab validator script (tools/validate_labs.sh)
- [ ] 5. Smoke test script (tools/smoke_test.sh)
- [ ] 6. Lab development guide (docs/adding-a-lab.md)
- [ ] 7. README rewrite (README.md)
- [ ] 8. L3/L4 hint audit (hints/lab-NN.json × 19)

---

## Work log

