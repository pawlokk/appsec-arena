# AppSec Arena

Local Docker-based security lab range for web application security and whitebox exploitation practice. 55 intentionally vulnerable web apps across Python, PHP, Java, Node.js, and .NET — each isolated in its own container, each with two flags. Fully extensible: add a Dockerfile and a registry entry, the platform handles the rest.

## Quick Start

```bash
pip install -r platform/requirements.txt
python platform/app.py
# → http://localhost:8080
```

Requires Docker running locally.

## Architecture

```
Browser
  │
  ▼
Flask platform (port 8080)
  │  - starts / stops / resets containers
  │  - streams build logs via SSE
  │  - verifies flags, serves hints
  │
  ▼
Docker daemon
  │
  ├── lab-00-testr  → host:5100  (container:5000)
  ├── lab-01-python → host:5101  (container:5000)
  ├── lab-02-php    → host:5102  (container:5000)
  └── ...           → host:5100-5599
```

Port assignments are stable — stored in `platform/port_registry.json` and never reassigned.

## Lab Levels

| Level | Description |
|---|---|
| L0 | Intro / demo — no real exploitation required |
| L1 | Single vulnerability, single step |
| L2 | Two-step chain (auth bypass → RCE) |
| L3 | Complex chain, bypass methodology, or realistic noise |
| L4 | Exam simulation — multi-route source review, red herrings |

## Adding Labs

See [docs/adding-a-lab.md](docs/adding-a-lab.md) for the checklist, Dockerfile templates, and the known-failure-patterns table.

## Flags

Every lab has two flags injected at runtime as environment variables:

- `FLAG_LOCAL` — captured at an auth milestone (e.g. session as admin)
- `FLAG_PROOF` — captured at RCE (e.g. `cat /flag_proof.txt` from a shell)

Format: `ARENA{...}`. Flags are deterministic per lab and per `ARENA_SECRET` — rotating the secret rotates all flags.

## Hints

Two-tier per lab, stored in `hints/lab-NN.json`:

- **Tier 1** — vulnerability class only (e.g. "JWT algorithm confusion")
- **Tier 2** — specific file + function/line + technique — enough to unstick, not a full solution

Hint requests are logged to `progress/hints.jsonl`.

## Validation

Before adding a lab or after any Dockerfile change:

```bash
./tools/validate_labs.sh          # check all 55 labs — exits 1 if any FAIL
./tools/smoke_test.sh lab-NN-name # build + start + HTTP check one lab
```

## Port Range

Host ports: **5100–5599**. Internal container port: **5000** (all labs).

Managed by `platform/port_registry.json`. Auto-assigns new labs on first start.

## Network Exposure

By default the platform binds to `0.0.0.0`. To restrict to a specific interface or set the hostname used in lab links:

```bash
ARENA_HOST=192.168.18.110 python platform/app.py
```
