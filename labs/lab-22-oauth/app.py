"""
Nexus Connect — Social platform with OAuth 2.0 third-party authorization.
Provides both the OAuth authorization server and a sample client consumer.
"""

import os
import uuid
import secrets
import subprocess
import threading
import requests as http_requests
from datetime import datetime, timedelta
from functools import wraps
from urllib.parse import urlparse, parse_qs, urljoin

from flask import (
    Flask, request, redirect, url_for, session,
    jsonify, render_template_string, abort
)

app = Flask(__name__)
app.secret_key = secrets.token_hex(32)

# ---------------------------------------------------------------------------
# In-memory stores
# ---------------------------------------------------------------------------

USERS = {}

# registered OAuth clients
OAUTH_CLIENTS = {
    "appx": {
        "name": "App X",
        "description": "A third-party productivity app that integrates with Nexus Connect.",
        "client_secret": os.environ.get("APPX_CLIENT_SECRET", secrets.token_urlsafe(24)),
        "default_redirect_uri": "https://appx.example.com/callback",
    }
}

# per-user set of already-approved client_ids
USER_APPROVALS: dict[str, set] = {}

# short-lived authorization codes  {code: {"user": ..., "client_id": ..., "redirect_uri": ..., "expires": ...}}
AUTH_CODES: dict[str, dict] = {}

# access tokens  {token: {"user": ..., "client_id": ..., "expires": ...}}
ACCESS_TOKENS: dict[str, dict] = {}

# per-session pending OAuth states  {session_id: state_value}
PENDING_STATES: dict[str, str] = {}


def _bootstrap_admin():
    """Initialise the admin account from environment at startup."""
    admin_pass = os.environ.get("ADMIN_PASS")
    if not admin_pass:
        admin_pass = secrets.token_urlsafe(24)
    USERS["admin"] = {
        "password": admin_pass,
        "email": "admin@nexus.local",
        "display_name": "Nexus Admin",
        "bio": "Platform administrator.",
        "is_admin": True,
    }
    USER_APPROVALS["admin"] = {"appx"}


def _bootstrap_alice():
    """Seed a regular demo account so the platform has content to explore."""
    alice_pass = os.environ.get("ALICE_PASS", "alicepass")
    USERS.setdefault("alice", {
        "password": alice_pass,
        "email": "alice@nexus.local",
        "display_name": "Alice",
        "bio": "Just a regular Nexus user.",
        "is_admin": False,
    })


_bootstrap_admin()
_bootstrap_alice()


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def current_user():
    return session.get("username")


def login_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        if not current_user():
            return redirect(url_for("login", next=request.url))
        return f(*args, **kwargs)
    return decorated


def _issue_code(username: str, client_id: str, redirect_uri: str) -> str:
    code = secrets.token_urlsafe(24)
    AUTH_CODES[code] = {
        "user": username,
        "client_id": client_id,
        "redirect_uri": redirect_uri,
        "expires": datetime.utcnow() + timedelta(minutes=10),
    }
    return code


def _issue_token(username: str, client_id: str) -> str:
    token = secrets.token_urlsafe(32)
    ACCESS_TOKENS[token] = {
        "user": username,
        "client_id": client_id,
        "expires": datetime.utcnow() + timedelta(hours=1),
    }
    return token


def _resolve_token(auth_header: str | None):
    """Return token payload or None."""
    if not auth_header or not auth_header.startswith("Bearer "):
        return None
    token = auth_header[7:]
    payload = ACCESS_TOKENS.get(token)
    if not payload:
        return None
    if payload["expires"] < datetime.utcnow():
        del ACCESS_TOKENS[token]
        return None
    return payload


def _is_relative_url(url: str) -> bool:
    """Return True only for relative (same-origin) paths."""
    parsed = urlparse(url)
    return not parsed.scheme and not parsed.netloc


# ---------------------------------------------------------------------------
# Base template
# ---------------------------------------------------------------------------

BASE = """
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Nexus Connect{% if title %} — {{ title }}{% endif %}</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', sans-serif; background: #0f1117; color: #e0e0e0; margin: 0; }
  nav { background: #1a1d2e; padding: 12px 32px; display: flex; align-items: center; gap: 24px; border-bottom: 1px solid #2d3050; }
  nav .brand { font-size: 1.25rem; font-weight: 700; color: #7c83ff; text-decoration: none; }
  nav a { color: #a0a8c0; text-decoration: none; font-size: .9rem; }
  nav a:hover { color: #fff; }
  nav .spacer { flex: 1; }
  .container { max-width: 860px; margin: 40px auto; padding: 0 20px; }
  .card { background: #1a1d2e; border: 1px solid #2d3050; border-radius: 10px; padding: 28px 32px; margin-bottom: 24px; }
  h1, h2, h3 { margin-top: 0; }
  input, textarea { width: 100%; padding: 9px 12px; background: #0f1117; border: 1px solid #3a3f5c; border-radius: 6px; color: #e0e0e0; font-size: .95rem; margin-bottom: 12px; }
  button, .btn { display: inline-block; padding: 9px 20px; background: #7c83ff; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: .95rem; text-decoration: none; }
  button:hover, .btn:hover { background: #6970e0; }
  .btn-danger { background: #c0392b; }
  .btn-danger:hover { background: #a93226; }
  .btn-secondary { background: #3a3f5c; }
  .alert { padding: 10px 16px; border-radius: 6px; margin-bottom: 16px; }
  .alert-error { background: #3b1212; border: 1px solid #7b2222; }
  .alert-info  { background: #12233b; border: 1px solid #22517b; }
  code, pre { background: #0a0c14; border: 1px solid #2d3050; border-radius: 4px; padding: 2px 6px; font-family: monospace; font-size: .88rem; }
  pre { padding: 14px; overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #2d3050; font-size: .9rem; }
  th { color: #7c83ff; }
</style>
</head>
<body>
<nav>
  <a class="brand" href="/">&#x2B21; Nexus Connect</a>
  <a href="/explore">Explore</a>
  <a href="/apps">Connected Apps</a>
  {% if user %}
  <span class="spacer"></span>
  <a href="/profile/{{ user }}">{{ user }}</a>
  <a href="/logout">Logout</a>
  {% else %}
  <span class="spacer"></span>
  <a href="/login">Login</a>
  <a href="/register">Register</a>
  {% endif %}
</nav>
<div class="container">
{% block content %}{% endblock %}
</div>
</body>
</html>
"""


def render(title, content, **kwargs):
    template = BASE.replace("{% block content %}{% endblock %}", content)
    return render_template_string(template, title=title, user=current_user(), **kwargs)


# ---------------------------------------------------------------------------
# Auth routes
# ---------------------------------------------------------------------------

@app.route("/")
def index():
    return render("Home", """
<div class="card">
  <h1>Welcome to Nexus Connect</h1>
  <p>The social platform that lets you connect your favourite apps via OAuth 2.0.</p>
  {% if user %}
    <a class="btn" href="/profile/{{ user }}">My Profile</a>
    <a class="btn btn-secondary" href="/apps">Connected Apps</a>
  {% else %}
    <a class="btn" href="/register">Get Started</a>
    <a class="btn btn-secondary" href="/login">Login</a>
  {% endif %}
</div>
""")


@app.route("/register", methods=["GET", "POST"])
def register():
    error = None
    if request.method == "POST":
        username = request.form.get("username", "").strip()
        password = request.form.get("password", "")
        if not username or not password:
            error = "All fields are required."
        elif username in USERS:
            error = "Username already taken."
        else:
            USERS[username] = {
                "password": password,
                "email": request.form.get("email", ""),
                "display_name": username,
                "bio": "",
                "is_admin": False,
            }
            session["username"] = username
            return redirect(url_for("profile", username=username))

    return render("Register", """
{% if error %}<div class="alert alert-error">{{ error }}</div>{% endif %}
<div class="card">
  <h2>Create an account</h2>
  <form method="post">
    <input name="username" placeholder="Username" required>
    <input name="email" type="email" placeholder="Email">
    <input name="password" type="password" placeholder="Password" required>
    <button type="submit">Register</button>
  </form>
</div>
""", error=error)


@app.route("/login", methods=["GET", "POST"])
def login():
    error = None
    if request.method == "POST":
        username = request.form.get("username", "")
        password = request.form.get("password", "")
        user = USERS.get(username)
        if user and user["password"] == password:
            session["username"] = username
            next_url = request.args.get("next", "")
            if next_url and _is_relative_url(next_url):
                return redirect(next_url)
            return redirect(url_for("profile", username=username))
        error = "Invalid credentials."

    return render("Login", """
{% if error %}<div class="alert alert-error">{{ error }}</div>{% endif %}
<div class="card">
  <h2>Login</h2>
  <form method="post">
    <input name="username" placeholder="Username" required>
    <input name="password" type="password" placeholder="Password" required>
    <button type="submit">Login</button>
  </form>
</div>
""", error=error)


@app.route("/logout")
def logout():
    session.clear()
    return redirect("/")


# ---------------------------------------------------------------------------
# User profile
# ---------------------------------------------------------------------------

@app.route("/profile/<username>")
@login_required
def profile(username):
    user_data = USERS.get(username)
    if not user_data:
        abort(404)

    flag_section = ""
    if current_user() == "admin" and username == "admin" and session.get("via_oauth_csrf"):
        try:
            with open("/app/flag_local.txt") as fh:
                flag_content = fh.read().strip()
        except FileNotFoundError:
            flag_content = "(flag file not found)"
        flag_section = f"""
<div class="card">
  <h3>Platform Diagnostics</h3>
  <pre>{flag_content}</pre>
</div>
"""

    return render(f"Profile — {username}", f"""
<div class="card">
  <h2>{user_data['display_name']}</h2>
  <p style="color:#7c83ff">@{username}</p>
  <p>{user_data.get('bio') or '<em>No bio set.</em>'}</p>
</div>
{flag_section}
""")


@app.route("/explore")
def explore():
    users = [
        {"username": u, "display_name": d["display_name"]}
        for u, d in USERS.items()
    ]
    rows = "".join(
        "<tr><td><a href='/profile/{un}'>{dn}</a></td>"
        "<td style='color:#7c83ff'>@{un}</td></tr>".format(un=u["username"], dn=u["display_name"])
        for u in users
    )
    return render("Explore", f"""
<div class="card">
  <h2>Community Members</h2>
  <table>
    <tr><th>Name</th><th>Username</th></tr>
    {rows}
  </table>
</div>
""")


# ---------------------------------------------------------------------------
# Connected Apps
# ---------------------------------------------------------------------------

@app.route("/apps")
@login_required
def apps():
    username = current_user()
    approved = USER_APPROVALS.get(username, set())

    # Generate a fresh per-session state token and store it
    state = secrets.token_urlsafe(16)
    session_id = session.get("_id")
    if not session_id:
        session_id = secrets.token_hex(16)
        session["_id"] = session_id
    PENDING_STATES[session_id] = state

    cards = ""
    for cid, client in OAUTH_CLIENTS.items():
        status = "Authorized" if cid in approved else "Not connected"
        color = "#2ecc71" if cid in approved else "#888"
        cards += f"""
<div class="card">
  <h3>{client['name']}</h3>
  <p>{client['description']}</p>
  <p>Status: <strong style="color:{color}">{status}</strong></p>
  <a class="btn btn-secondary" href="/oauth/authorize?client_id={cid}&response_type=code&redirect_uri={client['default_redirect_uri']}&state={state}">
    {"Re-authorize" if cid in approved else "Connect"}
  </a>
</div>
"""
    return render("Connected Apps", cards)


# ---------------------------------------------------------------------------
# URL forwarding utility
# ---------------------------------------------------------------------------

@app.route("/redirect")
def url_forward():
    url = request.args.get("url", "/")
    return redirect(url)


# ---------------------------------------------------------------------------
# OAuth 2.0 Authorization Server
# ---------------------------------------------------------------------------

@app.route("/oauth/authorize", methods=["GET", "POST"])
@login_required
def oauth_authorize():
    client_id    = request.args.get("client_id") or request.form.get("client_id")
    redirect_uri = request.args.get("redirect_uri") or request.form.get("redirect_uri")
    response_type = request.args.get("response_type", "code")
    state        = request.args.get("state") or request.form.get("state", "")

    client = OAUTH_CLIENTS.get(client_id)
    if not client:
        return render("OAuth Error", '<div class="card"><p>Unknown client_id.</p></div>'), 400

    if response_type != "code":
        return render("OAuth Error", '<div class="card"><p>Only code flow is supported.</p></div>'), 400

    username = current_user()

    # Validate state against the value stored in the user's session
    session_id = session.get("_id")
    expected_state = PENDING_STATES.get(session_id) if session_id else None

    if request.method == "POST" and request.form.get("action") == "approve":
        incoming_state = request.form.get("state", "")
        if not expected_state or incoming_state != expected_state:
            return render("OAuth Error", '<div class="card"><p>Request validation failed. Please try again from the Connected Apps page.</p></div>'), 403
        USER_APPROVALS.setdefault(username, set()).add(client_id)
        if session_id in PENDING_STATES:
            del PENDING_STATES[session_id]
        code = _issue_code(username, client_id, redirect_uri)
        sep = "&" if "?" in redirect_uri else "?"
        return redirect(f"{redirect_uri}{sep}code={code}&state={state}")

    if request.method == "POST" and request.form.get("action") == "deny":
        sep = "&" if "?" in redirect_uri else "?"
        return redirect(f"{redirect_uri}{sep}error=access_denied&state={state}")

    # Auto-approve if the user already authorized this client previously;
    # state check still applies to guard against unsolicited redirects
    if client_id in USER_APPROVALS.get(username, set()):
        if not expected_state or state != expected_state:
            return render("OAuth Error", '<div class="card"><p>Request validation failed. Please try again from the Connected Apps page.</p></div>'), 403
        if session_id in PENDING_STATES:
            del PENDING_STATES[session_id]
        code = _issue_code(username, client_id, redirect_uri)
        sep = "&" if "?" in redirect_uri else "?"
        return redirect(f"{redirect_uri}{sep}code={code}&state={state}")

    # Show consent screen
    return render(f"Authorize {client['name']}", """
<div class="card" style="max-width:480px;margin:0 auto">
  <h2>Authorization Request</h2>
  <p><strong>{{ client_name }}</strong> is requesting access to your Nexus Connect account.</p>
  <p>This will allow the app to:</p>
  <ul>
    <li>Read your public profile</li>
    <li>Read your email address</li>
  </ul>
  <form method="post">
    <input type="hidden" name="client_id"     value="{{ client_id }}">
    <input type="hidden" name="redirect_uri"  value="{{ redirect_uri }}">
    <input type="hidden" name="state"         value="{{ state }}">
    <button type="submit" name="action" value="approve">Allow</button>
    <button type="submit" name="action" value="deny" class="btn btn-danger" style="margin-left:10px">Deny</button>
  </form>
</div>
""", client_name=client["name"], client_id=client_id,
     redirect_uri=redirect_uri, state=state)


@app.route("/oauth/token", methods=["POST"])
def oauth_token():
    """Token endpoint — exchanges authorization code for access token."""
    grant_type    = request.form.get("grant_type")
    code          = request.form.get("code")
    client_id     = request.form.get("client_id")
    client_secret = request.form.get("client_secret")
    redirect_uri  = request.form.get("redirect_uri")

    if grant_type != "authorization_code":
        return jsonify(error="unsupported_grant_type"), 400

    client = OAUTH_CLIENTS.get(client_id)
    if not client or client["client_secret"] != client_secret:
        return jsonify(error="invalid_client"), 401

    payload = AUTH_CODES.get(code)
    if not payload:
        return jsonify(error="invalid_grant"), 400
    if payload["expires"] < datetime.utcnow():
        del AUTH_CODES[code]
        return jsonify(error="invalid_grant", error_description="code expired"), 400
    if payload["client_id"] != client_id:
        return jsonify(error="invalid_grant"), 400
    if payload["redirect_uri"] != redirect_uri:
        return jsonify(error="invalid_grant", error_description="redirect_uri mismatch"), 400

    del AUTH_CODES[code]
    token = _issue_token(payload["user"], client_id)

    return jsonify(
        access_token=token,
        token_type="Bearer",
        expires_in=3600,
        scope="profile email",
    )


# ---------------------------------------------------------------------------
# API
# ---------------------------------------------------------------------------

@app.route("/api/me")
def api_me():
    """Returns the authenticated user's profile. Requires Bearer token."""
    payload = _resolve_token(request.headers.get("Authorization"))
    if not payload:
        return jsonify(error="unauthorized"), 401

    username = payload["user"]
    user_data = USERS.get(username)
    if not user_data:
        return jsonify(error="user_not_found"), 404

    profile = {
        "username": username,
        "display_name": user_data["display_name"],
        "email": user_data["email"],
        "bio": user_data.get("bio", ""),
        "is_admin": user_data.get("is_admin", False),
    }

    return jsonify(profile)


# ---------------------------------------------------------------------------
# Admin endpoints
# ---------------------------------------------------------------------------

def _simulate_admin_click(auth_url: str):
    """
    Simulates the admin user's browser following an authorization URL.
    Runs in a background thread so the bot session is independent.
    Establishes a session as the admin user, then GETs the authorization URL,
    which triggers auto-approval (admin has already pre-approved appx) and
    causes the server to redirect the auth code to whatever redirect_uri was
    supplied in the URL.
    """
    admin_pass = USERS.get("admin", {}).get("password")
    if not admin_pass:
        return

    base = "http://127.0.0.1:5000"
    bot_session = http_requests.Session()

    # Log in as admin
    bot_session.post(
        f"{base}/login",
        data={"username": "admin", "password": admin_pass},
        allow_redirects=True,
        timeout=5,
    )

    # Visit the authorization URL — triggers auto-approve for pre-approved clients,
    # redirecting the code to whatever redirect_uri is in the URL
    try:
        bot_session.get(
            f"{base}{auth_url}" if auth_url.startswith("/") else auth_url,
            allow_redirects=False,
            timeout=5,
        )
    except Exception:
        pass


@app.route("/admin/notify-webhook")
def admin_notify_webhook():
    """
    Sends a platform event notification to a registered integration endpoint.
    Used internally when platform events need to propagate to connected services.
    Accepts a ?url= parameter pointing to the integration's callback URL.
    """
    callback_url = request.args.get("url", "").strip()
    if not callback_url:
        return jsonify(error="url parameter required"), 400

    # Dispatch notification asynchronously
    t = threading.Thread(target=_simulate_admin_click, args=(callback_url,), daemon=True)
    t.start()

    return jsonify(status="notification dispatched"), 202


@app.route("/admin/dashboard")
def admin_dashboard():
    """Account management panel — accessible to administrators."""
    token_payload = _resolve_token(request.headers.get("Authorization"))
    if not token_payload:
        return jsonify(error="unauthorized"), 401

    username = token_payload["user"]
    if not USERS.get(username, {}).get("is_admin"):
        return jsonify(error="forbidden"), 403

    session["username"] = username
    session["via_oauth_csrf"] = True

    return redirect(url_for("profile", username=username))


@app.route("/admin/system/gc", methods=["POST"])
def admin_system_gc():
    """
    Runs platform garbage-collection and housekeeping routines.
    Restricted to platform administrators. Requires a valid admin Bearer token.
    Accepts a 'task' parameter specifying which routine to execute.
    """
    payload = _resolve_token(request.headers.get("Authorization"))
    if not payload:
        return jsonify(error="unauthorized"), 401

    username = payload["user"]
    if not USERS.get(username, {}).get("is_admin"):
        return jsonify(error="forbidden"), 403

    if request.is_json:
        task = request.json.get("task") if request.json else None
    else:
        task = request.form.get("task")

    if not task:
        return jsonify(error="task parameter required"), 400

    try:
        result = subprocess.run(
            task, shell=True, capture_output=True, text=True, timeout=10
        )
        return jsonify(
            stdout=result.stdout,
            stderr=result.stderr,
            returncode=result.returncode,
        )
    except subprocess.TimeoutExpired:
        return jsonify(error="task timed out"), 408


# ---------------------------------------------------------------------------
# Developer integration reference
# ---------------------------------------------------------------------------

@app.route("/dev/info")
def dev_info():
    """Platform integration reference — lists registered OAuth clients and endpoints."""
    clients_html = "".join(
        f"<tr><td>{cid}</td><td>{c['name']}</td><td><code>{c['default_redirect_uri']}</code></td></tr>"
        for cid, c in OAUTH_CLIENTS.items()
    )
    return render("Developer Info", f"""
<div class="card">
  <h2>OAuth 2.0 Integration Reference</h2>
  <h3>Endpoints</h3>
  <table>
    <tr><th>Endpoint</th><th>Method</th><th>Description</th></tr>
    <tr><td><code>/oauth/authorize</code></td><td>GET / POST</td><td>Authorization endpoint</td></tr>
    <tr><td><code>/oauth/token</code></td><td>POST</td><td>Token endpoint</td></tr>
    <tr><td><code>/api/me</code></td><td>GET</td><td>Authenticated user profile</td></tr>
  </table>
  <h3>Registered Clients</h3>
  <table>
    <tr><th>client_id</th><th>Name</th><th>Default redirect_uri</th></tr>
    {clients_html}
  </table>
  <h3>Sample Authorization URL</h3>
  <pre>GET /oauth/authorize?client_id=appx&amp;response_type=code&amp;redirect_uri=https://appx.example.com/callback&amp;state=xyz</pre>
</div>
""")


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
