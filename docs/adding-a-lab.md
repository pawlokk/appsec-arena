# Adding a Lab

## Quick Checklist

Before opening a PR, verify:

- [ ] Directory: `labs/lab-NN-shortname/` with a `Dockerfile`
- [ ] Registered in `LABS` list in `platform/app.py` with all required fields
- [ ] Hint file: `hints/lab-NN.json` with `tier1` and `tier2` keys
- [ ] Dockerfile: internal port 5000, CMD writes both flags before starting app
- [ ] Flag format: `ARENA{...}` only — never `FLAG_LOCAL{...}`, `FLAG_PROOF{...}`, `FLAG{...}`
- [ ] No silent download fallback: `touch *.jar` or `touch *.zip` after wget/curl
- [ ] No root TCP DB connection: use a dedicated `appuser`, not `root`
- [ ] `tools/validate_labs.sh` exits 0 after adding the lab
- [ ] `tools/smoke_test.sh lab-NN-shortname` reports PASS

---

## Directory and Naming

**Convention:** `lab-NN-shortname/` where NN is the next available two-digit number.

To find the next free number:
```bash
ls labs/ | grep '^lab-' | sort -t- -k2 -n | tail -5
```

Register the lab in `LABS` in `platform/app.py`. All fields are required:

```python
{
    "id": "lab-62-mylab",
    "name": "MyLab",          # short display name
    "level": 2,               # 0-4, see level guide below
    "lang": "Python",         # Python | PHP | Java | Node.js | .NET
    "stack": "Flask · SQLite",
    "tags": ["SQLi", "RCE"],
    "description": "One-sentence spoiler-free description of what the lab teaches.",
    "image": "appsec-arena-lab-62-mylab",
    "category": "oswe-prep",  # oswe-prep | ctf | foss-audit | osed-prep | bug-bounty
    "type": "docker",         # docker | external
},
```

**Level guide:**
- L0 — intro / demo (no real exploitation required)
- L1 — single vulnerability, single step
- L2 — two-step chain (auth bypass → RCE)
- L3 — complex chain, bypass methodology, or realistic noise
- L4 — exam simulation (multi-route source review, red herrings)

---

## Dockerfile Requirements

- Expose **port 5000** only
- CMD must write both flags from env before starting the app:
  ```sh
  CMD ["sh", "-c", "echo \"$FLAG_LOCAL\" > /flag_local.txt && echo \"$FLAG_PROOF\" > /flag_proof.txt && <start app>"]
  ```
- Flag format: `ARENA{...}` — the platform injects real values at runtime; placeholders in ENV must also use `ARENA{placeholder}`
- Do not hardcode flag values — read from `$FLAG_LOCAL` and `$FLAG_PROOF` env vars

---

## Per-Stack Dockerfile Templates

### a. PHP + Apache

```dockerfile
FROM php:8.1-apache
RUN mkdir -p /app
WORKDIR /var/www/html
COPY index.php .
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /app/flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  sed -i 's/80/5000/g' /etc/apache2/sites-enabled/000-default.conf /etc/apache2/ports.conf && \
  apache2-foreground"]
```

### b. PHP + Apache + MariaDB

```dockerfile
FROM php:8.1-apache
RUN apt-get update && apt-get install -y mariadb-server default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*
RUN mkdir -p /app
WORKDIR /var/www/html
COPY . .
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /app/flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  service mariadb start && sleep 2 && \
  mysql -u root < /var/www/html/init.sql && \
  sed -i 's/80/5000/g' /etc/apache2/sites-enabled/000-default.conf /etc/apache2/ports.conf && \
  apache2-foreground"]
```

**init.sql must include:**
```sql
CREATE USER IF NOT EXISTS 'appuser'@'localhost' IDENTIFIED BY 'apppass';
GRANT ALL PRIVILEGES ON mydb.* TO 'appuser'@'localhost';
FLUSH PRIVILEGES;
```

Never connect as `root` via TCP (`host='127.0.0.1'`). The `unix_socket` auth plugin blocks it.

### c. Flask + PostgreSQL

```dockerfile
FROM python:3.11-slim
RUN apt-get update && apt-get install -y postgresql postgresql-contrib libpq-dev \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY app.py init.sql .
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
ENV ADMIN_PASSWORD=""
EXPOSE 5000
CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  service postgresql start && sleep 4 && \
  su postgres -c \"psql -c \\\"CREATE ROLE appuser WITH LOGIN PASSWORD 'apppass';\\\" || true\" && \
  su postgres -c \"createdb -O appuser mydb || true\" && \
  HASH=$(python3 -c \"from werkzeug.security import generate_password_hash; print(generate_password_hash('$ADMIN_PASSWORD'))\") && \
  sed \"s|PLACEHOLDER_HASH|$HASH|g\" /app/init.sql | su postgres -c \"psql -d mydb\" && \
  python3 /app/app.py"]
```

**Note:** Use `|` as sed delimiter when substituting values that may contain `/` (e.g. bcrypt hashes).

Debian ships two `pg_hba.conf` files. The one the service actually reads is:
`/etc/postgresql/15/main/pg_hba.conf` (not `/etc/postgresql/pg_hba.conf`).

### d. Node.js + Express (no DB, app on port 3000)

```dockerfile
FROM node:18-slim
WORKDIR /app
COPY package.json .
RUN npm install
COPY app.js .
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  node /app/app.js & \
  sleep 1 && socat TCP-LISTEN:5000,fork TCP:127.0.0.1:3000"]
```

Add `socat` to the `apt-get install` if the app listens on a non-5000 port.

### e. Node.js + MongoDB

```dockerfile
FROM node:18-slim
RUN apt-get update && apt-get install -y mongodb && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY package.json .
RUN npm install
COPY app.js init.js .
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  mongod --fork --logpath /var/log/mongod.log && sleep 2 && \
  node /app/init.js && \
  node /app/app.js"]
```

### f. Java + Maven + Jetty (multi-stage)

```dockerfile
FROM maven:3.9-eclipse-temurin-17 AS build
WORKDIR /build
COPY pom.xml .
COPY src ./src
RUN mvn package -q -DskipTests

FROM eclipse-temurin:17-jre-slim
WORKDIR /app
COPY --from=build /build/target/mylab-1.0.0.jar app.jar
ENV FLAG_LOCAL="ARENA{placeholder}"
ENV FLAG_PROOF="ARENA{placeholder}"
EXPOSE 5000
CMD ["sh", "-c", "\
  echo \"$FLAG_LOCAL\" > /flag_local.txt && \
  echo \"$FLAG_PROOF\" > /flag_proof.txt && \
  java -jar /app/app.jar"]
```

---

## Known Failure Patterns

| Pattern | Symptom | Fix |
|---|---|---|
| MariaDB unix_socket root-TCP block | `Access denied for user 'root'@'localhost'` on TCP connection | Create dedicated `appuser` in init.sql; connect as `appuser` in app code |
| PostgreSQL dual pg_hba.conf path | `FATAL: no pg_hba.conf entry` despite editing the file | Edit `/etc/postgresql/15/main/pg_hba.conf`, not `/etc/postgresql/pg_hba.conf` |
| pg + pg-promise version lock | `pg.connect is not a function` or type errors | Use `pg@8` + `pg-promise@10` — older pg-promise bundles pg@5 |
| sed `/` delimiter collision | `sed: 1: "s/PLACEHOLDER/...`: unterminated substitute | Use `\|` as delimiter: `sed "s\|PLACEHOLDER\|$HASH\|g"` |
| ysoserial `/releases/latest/` dead URL | Zero-byte JAR, ClassNotFound at runtime | Pin to explicit version: `https://github.com/frohoff/ysoserial/releases/download/v0.0.6/ysoserial-all.jar`; use `\|\| exit 1` fallback |
| wkhtmltopdf silent skip | PDF generation returns blank page or 500 | Add `&& which wkhtmltopdf` after install block to fail fast |
| Missing `/app` in Apache images | `cannot create /app/flag_local.txt: Directory nonexistent` | Add `RUN mkdir -p /app` before writing to `/app/` |
| Wrong flag format | validate_labs.sh FAIL on flag format check | ENV and CMD must use `ARENA{placeholder}`, never `FLAG_LOCAL{...}` |

---

## Hints File Format

Create `hints/lab-NN.json` (NN = zero-padded lab number):

```json
{
  "tier1": "Vulnerability class only — one phrase, no file paths, no technique",
  "tier2": "Specific file + function or line where the sink lives. The exact technique to exploit it. Enough to unstick someone on the how, not a full solution."
}
```

**Examples:**

```json
{
  "tier1": "SQL injection",
  "tier2": "app.py:47 login() — username parameter interpolated directly into query string. Bypass with ' OR 1=1-- to extract admin hash, then crack or reuse."
}
```

```json
{
  "tier1": "JWT algorithm confusion",
  "tier2": "app.js:23 verifyToken() — jwt.verify() called without algorithms option. Fetch public key from /.well-known/jwks.json, re-sign with HS256 using the PEM as HMAC secret."
}
```

---

## Local Test Commands

```bash
# Build
docker build -t appsec-arena-lab-NN-mylab labs/lab-NN-mylab/

# Run with test flags
docker run --rm -p 5000:5000 \
  -e FLAG_LOCAL=ARENA{test_local} \
  -e FLAG_PROOF=ARENA{test_proof} \
  -e ADMIN_PASSWORD=testpass \
  appsec-arena-lab-NN-mylab

# Verify it responds
curl -s -o /dev/null -w "%{http_code}" http://localhost:5000/
# → 200 or 302

# Run validator
./tools/validate_labs.sh

# Smoke test
./tools/smoke_test.sh lab-NN-mylab
```

---

## Extensibility Conventions

### Category values

The category filter in the UI is data-driven — adding a new `"category"` value to a LABS entry automatically creates a new filter pill with no other code change.

Current values: `oswe-prep`, `ctf`, `foss-audit`, `osed-prep`, `bug-bounty`

### Type: docker vs external

- `"type": "docker"` — standard lab with a Dockerfile and a running container
- `"type": "external"` — no container; entry has a `"url"` field pointing to a GitHub repo or external resource. The UI shows an "Open" link only. (Data model only — UI for external type not yet implemented.)

```python
# External entry example (no container, no image field):
{
    "id": "lab-99-foss-audit",
    "name": "MyProject",
    "level": 3,
    "lang": "Python",
    "stack": "Django · PostgreSQL",
    "tags": ["SQLi", "SSTI"],
    "description": "Real production FOSS target — audit for injection sinks.",
    "url": "https://github.com/owner/myproject",
    "category": "foss-audit",
    "type": "external",
},
```
