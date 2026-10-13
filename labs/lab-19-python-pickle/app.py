"""
FluxBoard - Developer Team Dashboard
Internal tooling for tracking deployments, infra health, and team activity.
"""

import os
import logging
import datetime
import hashlib
import hmac

import pickle
import base64
from flask import (
    Flask,
    request,
    redirect,
    url_for,
    make_response,
    render_template_string,
    abort,
)

app = Flask(__name__)
app.secret_key = os.urandom(32)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("fluxboard")

# ---------------------------------------------------------------------------
# User store
# ---------------------------------------------------------------------------

def _pbkdf2(password: str, salt: str) -> str:
    return hashlib.pbkdf2_hmac(
        "sha256", password.encode(), salt.encode(), 260000
    ).hex()

USERS = {
    "alice": {
        "hash": _pbkdf2("devpass1", "fluxsalt_alice"),
        "salt": "fluxsalt_alice",
        "role": "developer",
        "display": "Alice Mercer",
    },
    "bob": {
        "hash": _pbkdf2("devpass2", "fluxsalt_bob"),
        "salt": "fluxsalt_bob",
        "role": "developer",
        "display": "Bob Tanaka",
    },
}


def _check_password(user_record: dict, password: str) -> bool:
    expected = _pbkdf2(password, user_record["salt"])
    return hmac.compare_digest(expected, user_record["hash"])


# ---------------------------------------------------------------------------
# Fake deployment history shown on the dashboard
# ---------------------------------------------------------------------------
DEPLOYMENTS = [
    {"id": "d-0041", "service": "api-gateway",    "env": "prod",    "status": "success", "ts": "2026-06-09 22:14"},
    {"id": "d-0040", "service": "worker-queue",   "env": "staging", "status": "success", "ts": "2026-06-09 18:03"},
    {"id": "d-0039", "service": "frontend",       "env": "prod",    "status": "failed",  "ts": "2026-06-09 11:47"},
    {"id": "d-0038", "service": "auth-service",   "env": "prod",    "status": "success", "ts": "2026-06-08 09:22"},
    {"id": "d-0037", "service": "metrics-export", "env": "staging", "status": "success", "ts": "2026-06-07 16:55"},
]

# ---------------------------------------------------------------------------
# Session helpers
# ---------------------------------------------------------------------------

class Session:
    """Lightweight session container stored client-side."""

    def __init__(self, username: str, role: str, display: str):
        self.username = username
        self.role = role
        self.display = display


def encode_session(sess: Session) -> str:
    return base64.b64encode(pickle.dumps(sess)).decode()


def decode_session(cookie_val: str) -> Session | None:
    try:
        raw = base64.b64decode(cookie_val)
        obj = pickle.loads(raw)
        return obj
    except Exception as exc:
        logger.debug("Session decode failed (%s): %s", type(exc).__name__, exc)
        return None


def get_session() -> Session | None:
    cookie = request.cookies.get("flux_session")
    if not cookie:
        return None
    return decode_session(cookie)


def require_login():
    sess = get_session()
    if not sess:
        return redirect(url_for("login")), None
    return None, sess


def require_admin():
    sess = get_session()
    if not sess:
        return redirect(url_for("login")), None
    if sess.role != "admin":
        abort(403)
    return None, sess


# ---------------------------------------------------------------------------
# Templates
# ---------------------------------------------------------------------------

BASE_STYLE = """
<style>
  body { font-family: 'Segoe UI', sans-serif; margin: 0; background: #0f1117; color: #c9d1d9; }
  nav { background: #161b22; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #30363d; }
  nav .brand { font-size: 1.3rem; font-weight: 700; color: #58a6ff; letter-spacing: 1px; }
  nav .user-info { font-size: 0.85rem; color: #8b949e; }
  .container { max-width: 960px; margin: 40px auto; padding: 0 20px; }
  h2 { color: #58a6ff; border-bottom: 1px solid #30363d; padding-bottom: 8px; }
  .card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; padding: 8px 12px; background: #21262d; color: #8b949e; font-size: 0.8rem; text-transform: uppercase; }
  td { padding: 8px 12px; border-top: 1px solid #21262d; font-size: 0.9rem; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
  .badge-success { background: #1a4731; color: #3fb950; }
  .badge-failed  { background: #4d1a1a; color: #f85149; }
  .badge-admin   { background: #1f2d4d; color: #58a6ff; }
  .form-group { margin-bottom: 16px; }
  label { display: block; margin-bottom: 4px; font-size: 0.85rem; color: #8b949e; }
  input { width: 100%; padding: 8px 12px; background: #0d1117; border: 1px solid #30363d; border-radius: 6px; color: #c9d1d9; font-size: 0.95rem; box-sizing: border-box; }
  .btn { padding: 9px 20px; background: #238636; border: none; border-radius: 6px; color: #fff; font-size: 0.95rem; cursor: pointer; }
  .btn:hover { background: #2ea043; }
  .alert { padding: 10px 16px; border-radius: 6px; margin-bottom: 16px; }
  .alert-error { background: #4d1a1a; border: 1px solid #6e2a2a; color: #f85149; }
  .diag-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #21262d; font-size: 0.9rem; }
  .diag-item:last-child { border-bottom: none; }
  .flag-box { font-family: monospace; background: #0d1117; border: 1px solid #30363d; border-radius: 6px; padding: 14px 18px; word-break: break-all; color: #3fb950; margin-top: 10px; }
</style>
"""

LOGIN_TEMPLATE = BASE_STYLE + """
<nav><span class="brand">FluxBoard</span></nav>
<div class="container" style="max-width:400px">
  <div class="card" style="margin-top:80px">
    <h2 style="margin-top:0">Sign in</h2>
    {% if error %}<div class="alert alert-error">{{ error }}</div>{% endif %}
    <form method="post">
      <div class="form-group">
        <label>Username</label>
        <input name="username" autocomplete="username" />
      </div>
      <div class="form-group">
        <label>Password</label>
        <input name="password" type="password" autocomplete="current-password" />
      </div>
      <button class="btn" type="submit" style="width:100%">Sign in</button>
    </form>
  </div>
</div>
"""

DASHBOARD_TEMPLATE = BASE_STYLE + """
<nav>
  <span class="brand">FluxBoard</span>
  <span class="user-info">
    {{ sess.display }}
    {% if sess.role == 'admin' %}<span class="badge badge-admin" style="margin-left:8px">admin</span>{% endif %}
    &nbsp;·&nbsp; <a href="/logout" style="color:#8b949e">logout</a>
  </span>
</nav>
<div class="container">
  <h2>Recent Deployments</h2>
  <div class="card" style="padding:0">
    <table>
      <thead><tr><th>ID</th><th>Service</th><th>Env</th><th>Status</th><th>Time</th></tr></thead>
      <tbody>
        {% for d in deployments %}
        <tr>
          <td style="font-family:monospace;color:#8b949e">{{ d.id }}</td>
          <td>{{ d.service }}</td>
          <td>{{ d.env }}</td>
          <td><span class="badge badge-{{ d.status }}">{{ d.status }}</span></td>
          <td style="color:#8b949e">{{ d.ts }}</td>
        </tr>
        {% endfor %}
      </tbody>
    </table>
  </div>

  {% if sess.role == 'admin' %}
  <h2>System Diagnostics</h2>
  <div class="card">
    <div class="diag-item"><span>Uptime</span><span>{{ uptime }}</span></div>
    <div class="diag-item"><span>Flask version</span><span>{{ flask_ver }}</span></div>
    <div class="diag-item"><span>Python version</span><span>{{ py_ver }}</span></div>
    <div class="diag-item"><span>Hostname</span><span>{{ hostname }}</span></div>
    <div class="diag-item">
      <span>Config endpoint</span>
      <span><a href="/admin/config" style="color:#58a6ff">/admin/config</a></span>
    </div>
    <div class="diag-item">
      <span>Deployment token</span>
      <span><a href="/admin/token" style="color:#58a6ff">/admin/token</a></span>
    </div>
  </div>
  {% endif %}
</div>
"""

CONFIG_TEMPLATE = BASE_STYLE + """
<nav>
  <span class="brand">FluxBoard</span>
  <span class="user-info">
    <a href="/dashboard" style="color:#8b949e">&larr; dashboard</a>
    &nbsp;·&nbsp; <a href="/logout" style="color:#8b949e">logout</a>
  </span>
</nav>
<div class="container">
  <h2>Runtime Configuration</h2>
  <div class="card">
    <pre style="margin:0;white-space:pre-wrap;word-break:break-all;color:#3fb950">{{ config_dump }}</pre>
  </div>
</div>
"""

TOKEN_TEMPLATE = BASE_STYLE + """
<nav>
  <span class="brand">FluxBoard</span>
  <span class="user-info">
    <a href="/dashboard" style="color:#8b949e">&larr; dashboard</a>
    &nbsp;·&nbsp; <a href="/logout" style="color:#8b949e">logout</a>
  </span>
</nav>
<div class="container">
  <h2>Deployment Token</h2>
  <div class="card">
    <p style="color:#8b949e;margin-top:0">
      This token authorises automated deployment pipelines to push releases.
      Rotate it after any suspected credential exposure.
    </p>
    <div class="flag-box">{{ token }}</div>
  </div>
</div>
"""

# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------

@app.route("/")
def index():
    sess = get_session()
    if sess:
        return redirect(url_for("dashboard"))
    return redirect(url_for("login"))


@app.route("/login", methods=["GET", "POST"])
def login():
    error = None
    if request.method == "POST":
        username = request.form.get("username", "").strip()
        password = request.form.get("password", "")
        user = USERS.get(username)
        if user and _check_password(user, password):
            sess = Session(username=username, role=user["role"], display=user["display"])
            resp = make_response(redirect(url_for("dashboard")))
            resp.set_cookie("flux_session", encode_session(sess), httponly=True)
            return resp
        error = "Invalid username or password."
    return render_template_string(LOGIN_TEMPLATE, error=error)


@app.route("/logout")
def logout():
    resp = make_response(redirect(url_for("login")))
    resp.delete_cookie("flux_session")
    return resp


@app.route("/dashboard")
def dashboard():
    sess = get_session()
    if not sess:
        return redirect(url_for("login"))

    import flask
    import sys
    import socket

    diag = {
        "uptime": str(datetime.datetime.utcnow().strftime("%Y-%m-%d %H:%M UTC")),
        "flask_ver": flask.__version__,
        "py_ver": sys.version.split()[0],
        "hostname": socket.gethostname(),
    }
    return render_template_string(
        DASHBOARD_TEMPLATE,
        sess=sess,
        deployments=DEPLOYMENTS,
        **diag,
    )


@app.route("/admin/config")
def admin_config():
    err, sess = require_admin()
    if err:
        return err

    config_lines = []
    config_lines.append(f"# FluxBoard runtime config — {datetime.datetime.utcnow().isoformat()}Z")
    config_lines.append(f"# Hostname : {__import__('socket').gethostname()}")
    config_lines.append("")

    for key in ("PYTHONPATH", "PYTHONDONTWRITEBYTECODE", "FLASK_ENV", "HOSTNAME"):
        val = os.environ.get(key, "<unset>")
        config_lines.append(f"{key}={val}")

    return render_template_string(CONFIG_TEMPLATE, config_dump="\n".join(config_lines))


@app.route("/admin/token")
def admin_token():
    """Deployment token — only accessible to admin-role sessions."""
    err, sess = require_admin()
    if err:
        return err

    flag_path = "/flag_local.txt"
    try:
        with open(flag_path, "r") as fh:
            token = fh.read().strip()
    except FileNotFoundError:
        token = "<token not provisioned>"

    return render_template_string(TOKEN_TEMPLATE, token=token)


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
