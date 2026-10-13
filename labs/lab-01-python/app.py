"""
Serpentine — internal developer portal
"""

import os, sqlite3, json, base64, hmac, hashlib, re
from flask import Flask, request, jsonify, render_template_string, g
from werkzeug.security import generate_password_hash, check_password_hash

app = Flask(__name__)

JWT_SECRET = os.environ.get("JWT_SECRET", "")
if not JWT_SECRET:
    raise RuntimeError("JWT_SECRET environment variable must be set")

FLAG_LOCAL = os.environ.get("FLAG_LOCAL", "")

DB_PATH = "/tmp/serpentine.db"

# ── DB ─────────────────────────────────────────────────────────────────────────
def get_db():
    db = getattr(g, '_db', None)
    if db is None:
        db = g._db = sqlite3.connect(DB_PATH)
        db.row_factory = sqlite3.Row
    return db

@app.teardown_appcontext
def close_db(exc):
    db = getattr(g, '_db', None)
    if db: db.close()

def init_db():
    db = sqlite3.connect(DB_PATH)
    admin_pass = os.environ.get("ADMIN_PASS", "")
    if not admin_pass:
        raise RuntimeError("ADMIN_PASS environment variable must be set")
    alice_pass = os.environ.get("ALICE_PASS", "")
    bob_pass   = os.environ.get("BOB_PASS", "")
    if not alice_pass or not bob_pass:
        raise RuntimeError("ALICE_PASS and BOB_PASS environment variables must be set")
    db.execute("""
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user'
        )
    """)
    for username, password, role in [
        ("admin", admin_pass, "admin"),
        ("alice", alice_pass, "user"),
        ("bob",   bob_pass,   "user"),
    ]:
        existing = db.execute("SELECT id FROM users WHERE username=?", (username,)).fetchone()
        if not existing:
            db.execute(
                "INSERT INTO users (username, password, role) VALUES (?, ?, ?)",
                (username, generate_password_hash(password), role)
            )
    db.commit()
    db.close()

init_db()

# ── JWT ────────────────────────────────────────────────────────────────────────
def _b64url(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).rstrip(b'=').decode()

def _b64url_decode(s: str) -> bytes:
    pad = 4 - len(s) % 4
    return base64.urlsafe_b64decode(s + '=' * (pad % 4))

def make_token(payload: dict) -> str:
    header = _b64url(json.dumps({"alg": "HS256", "typ": "JWT"}).encode())
    body   = _b64url(json.dumps(payload).encode())
    sig    = _b64url(hmac.new(JWT_SECRET.encode(), f"{header}.{body}".encode(),
                               hashlib.sha256).digest())
    return f"{header}.{body}.{sig}"

def verify_token(token: str) -> dict | None:
    try:
        parts = token.split('.')
        if len(parts) != 3:
            return None
        header_b, body_b, sig_b = parts
        header_data = json.loads(_b64url_decode(header_b))
        alg = header_data.get("alg", "")
        if alg == "none":
            if sig_b == "":
                return json.loads(_b64url_decode(body_b))
            return None
        expected_sig = _b64url(hmac.new(JWT_SECRET.encode(),
                                         f"{header_b}.{body_b}".encode(),
                                         hashlib.sha256).digest())
        if not hmac.compare_digest(expected_sig, sig_b):
            return None
        return json.loads(_b64url_decode(body_b))
    except Exception:
        return None

def current_user():
    token = request.headers.get("Authorization", "").removeprefix("Bearer ").strip()
    return verify_token(token) if token else None

# ── Routes ─────────────────────────────────────────────────────────────────────

INDEX_HTML = """<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Serpentine</title>
  <style>
    body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
    h1{color:#5c8aff}a{color:#5c8aff}code{background:#1a1e2a;padding:2px 6px;border-radius:4px}
    pre{background:#1a1e2a;padding:1rem;border-radius:8px;overflow:auto}
    .section{margin-top:1.5rem}
  </style>
</head>
<body>
  <h1>Serpentine</h1>
  <p>Internal developer portal. JWT-authenticated API.</p>
  <div class="section">
    <h3>Endpoints</h3>
    <pre>POST /api/login          { username, password }
GET  /api/profile         Authorization: Bearer &lt;token&gt;</pre>
  </div>
</body>
</html>"""

@app.route("/")
def index():
    return INDEX_HTML

@app.route("/api/login", methods=["POST"])
def login():
    data     = request.get_json(force=True) or {}
    username = data.get("username", "")
    password = data.get("password", "")
    db       = get_db()
    row      = db.execute("SELECT * FROM users WHERE username=?", (username,)).fetchone()
    if not row or not check_password_hash(row["password"], password):
        return jsonify({"error": "Invalid credentials"}), 401
    token = make_token({"sub": row["username"], "role": row["role"]})
    return jsonify({"token": token})

@app.route("/api/profile")
def profile():
    user = current_user()
    if not user:
        return jsonify({"error": "Unauthorised"}), 401
    return jsonify({"username": user.get("sub"), "role": user.get("role")})

@app.route("/api/dashboard")
def dashboard():
    user = current_user()
    if not user:
        return jsonify({"error": "Unauthorised"}), 401
    if user.get("role") != "admin":
        return jsonify({"error": "Forbidden"}), 403
    return jsonify({"flag": FLAG_LOCAL, "message": "Welcome, administrator."})

@app.route("/render", methods=["POST"])
def render_endpoint():
    user = current_user()
    if not user or user.get("role") != "admin":
        return jsonify({"error": "Forbidden"}), 403
    data     = request.get_json(force=True) or {}
    template = data.get("template", "")
    try:
        result = render_template_string(template)
        return jsonify({"output": result})
    except Exception:
        return jsonify({"error": "Render error"}), 500

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
