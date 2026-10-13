import os
import sqlite3
import secrets
import time
from flask import (
    Flask, request, session, redirect, url_for,
    render_template_string, jsonify, g
)
from werkzeug.security import generate_password_hash, check_password_hash

app = Flask(__name__)
app.secret_key = secrets.token_hex(32)

DB_PATH = "/app/nexus.db"

FLAG_LOCAL = open("/flag_local.txt").read().strip() if os.path.exists("/flag_local.txt") else os.environ.get("FLAG_LOCAL", "ARENA{LOCAL_placeholder}")
FLAG_PROOF = open("/flag_proof.txt").read().strip() if os.path.exists("/flag_proof.txt") else os.environ.get("FLAG_PROOF", "ARENA{PROOF_placeholder}")


def get_db():
    if "db" not in g:
        g.db = sqlite3.connect(DB_PATH)
        g.db.row_factory = sqlite3.Row
    return g.db


@app.teardown_appcontext
def close_db(e=None):
    db = g.pop("db", None)
    if db is not None:
        db.close()


def get_user_by_email(email):
    db = get_db()
    return db.execute("SELECT * FROM users WHERE email = ?", (email,)).fetchone()


def get_user_by_id(uid):
    db = get_db()
    return db.execute("SELECT * FROM users WHERE id = ?", (uid,)).fetchone()


BASE_STYLE = """
<style>
  body { font-family: monospace; background: #0d0d0d; color: #00ff88; margin: 0; }
  .container { max-width: 700px; margin: 60px auto; padding: 20px; border: 1px solid #00ff88; }
  h1 { color: #00ffcc; }
  input, textarea { background: #111; color: #00ff88; border: 1px solid #00ff88; padding: 8px; width: 100%; margin: 6px 0; box-sizing: border-box; }
  button, .btn { background: #003322; color: #00ff88; border: 1px solid #00ff88; padding: 8px 18px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 8px; }
  .err { color: #ff4444; }
  .ok  { color: #00ff88; }
  .warn { color: #ffaa00; }
  nav a { color: #00ffcc; margin-right: 14px; text-decoration: none; }
  pre { background: #111; padding: 10px; border: 1px solid #333; overflow-x: auto; }
</style>
"""

NAV_AUTH = '<nav><a href="/">Home</a><a href="/dashboard">Dashboard</a><a href="/logout">Logout</a></nav>'
NAV_GUEST = '<nav><a href="/">Home</a><a href="/login">Login</a><a href="/register">Register</a></nav>'


@app.route("/")
def index():
    nav = NAV_AUTH if "user_id" in session else NAV_GUEST
    return render_template_string(BASE_STYLE + nav + """
    <div class="container">
      <h1>NexusLab Portal</h1>
      <p>Internal research portal. Authorized personnel only.</p>
      <p><a class="btn" href="/login">Login</a> <a class="btn" href="/register">Register</a></p>
    </div>
    """)


@app.route("/register", methods=["GET", "POST"])
def register():
    error = ""
    if request.method == "POST":
        email = request.form.get("email", "").strip()
        password = request.form.get("password", "")
        if not email or not password:
            error = "Email and password required."
        elif get_user_by_email(email):
            error = "Email already registered."
        else:
            db = get_db()
            db.execute(
                "INSERT INTO users (email, password_hash, is_admin) VALUES (?, ?, 0)",
                (email, generate_password_hash(password))
            )
            db.commit()
            return redirect(url_for("login"))
    return render_template_string(BASE_STYLE + NAV_GUEST + """
    <div class="container">
      <h1>Register</h1>
      {% if error %}<p class="err">{{ error }}</p>{% endif %}
      <form method="post">
        <input name="email" type="email" placeholder="Email" required>
        <input name="password" type="password" placeholder="Password" required>
        <button type="submit">Register</button>
      </form>
      <p><a href="/login">Already have an account?</a></p>
    </div>
    """, error=error)


@app.route("/login", methods=["GET", "POST"])
def login():
    error = ""
    if request.method == "POST":
        email = request.form.get("email", "").strip()
        password = request.form.get("password", "")
        user = get_user_by_email(email)
        if user and check_password_hash(user["password_hash"], password):
            db = get_db()
            # Check for a pending one-time post-reset marker for this account
            marker = db.execute(
                "SELECT token FROM reset_tokens WHERE email = ? AND used = 1",
                (email,)
            ).fetchone()
            session["user_id"] = user["id"]
            session["email"] = user["email"]
            session["is_admin"] = bool(user["is_admin"])
            if marker:
                session["via_reset_chain"] = True
                db.execute(
                    "DELETE FROM reset_tokens WHERE email = ? AND used = 1",
                    (email,)
                )
                db.commit()
            return redirect(url_for("dashboard"))
        error = "Invalid credentials."
    return render_template_string(BASE_STYLE + NAV_GUEST + """
    <div class="container">
      <h1>Login</h1>
      {% if error %}<p class="err">{{ error }}</p>{% endif %}
      <form method="post">
        <input name="email" type="email" placeholder="Email" required>
        <input name="password" type="password" placeholder="Password" required>
        <button type="submit">Login</button>
      </form>
      <p><a href="/reset/request">Forgot password?</a></p>
    </div>
    """, error=error)


@app.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("index"))


@app.route("/dashboard")
def dashboard():
    if "user_id" not in session:
        return redirect(url_for("login"))
    user = get_user_by_id(session["user_id"])
    is_admin = bool(user["is_admin"])
    flag_section = ""
    if is_admin:
        if session.get("via_reset_chain"):
            flag_section = f'<p class="ok"><b>FLAG:</b> <code>{FLAG_LOCAL}</code></p>'
        else:
            flag_section = '<p class="warn">Elevated access requires completing the account recovery workflow.</p>'
    admin_link = '<p><a class="btn" href="/admin/eval">Admin Eval Panel</a></p>' if is_admin else ""
    return render_template_string(BASE_STYLE + NAV_AUTH + """
    <div class="container">
      <h1>Dashboard</h1>
      <p>Welcome, {{ email }}</p>
      {% if is_admin %}<p class="ok">[ADMIN]</p>{% endif %}
      {{ flag_section | safe }}
      {{ admin_link | safe }}
    </div>
    """, email=user["email"], is_admin=is_admin, flag_section=flag_section, admin_link=admin_link)


@app.route("/reset/request", methods=["GET", "POST"])
def reset_request():
    message = ""
    if request.method == "POST":
        email = request.form.get("email", "").strip()
        user = get_user_by_email(email)
        if user:
            token = secrets.token_urlsafe(32)
            db = get_db()
            db.execute(
                "INSERT OR REPLACE INTO reset_tokens (token, email, created_at, used) VALUES (?, ?, ?, 0)",
                (token, email, int(time.time()))
            )
            db.commit()

            # Construct notification link
            host = request.headers.get("Host", "localhost:5000")
            reset_url = f"http://{host}/reset/{token}"

            # Dispatch notification — logged to stdout (visible in docker logs)
            print(f"[MAIL] To: {email} | Subject: Account recovery | Body: Use the following link to regain access: {reset_url}", flush=True)

            message = "If that email exists, a reset link has been dispatched."
        else:
            message = "If that email exists, a reset link has been dispatched."
    return render_template_string(BASE_STYLE + NAV_GUEST + """
    <div class="container">
      <h1>Account Recovery</h1>
      {% if message %}<p class="ok">{{ message }}</p>{% endif %}
      <form method="post">
        <input name="email" type="email" placeholder="Email address" required>
        <button type="submit">Send Recovery Link</button>
      </form>
    </div>
    """, message=message)


@app.route("/reset/<token>", methods=["GET", "POST"])
def reset_token(token):
    db = get_db()
    row = db.execute(
        "SELECT * FROM reset_tokens WHERE token = ? AND used = 0", (token,)
    ).fetchone()

    if not row:
        return render_template_string(BASE_STYLE + NAV_GUEST + """
        <div class="container"><h1>Invalid or expired token.</h1></div>
        """)

    # Token expires after 1 hour
    if int(time.time()) - row["created_at"] > 3600:
        db.execute("DELETE FROM reset_tokens WHERE token = ?", (token,))
        db.commit()
        return render_template_string(BASE_STYLE + NAV_GUEST + """
        <div class="container"><h1>Token expired.</h1></div>
        """)

    error = ""
    if request.method == "POST":
        new_password = request.form.get("password", "")
        if not new_password:
            error = "Password required."
        else:
            db.execute(
                "UPDATE users SET password_hash = ? WHERE email = ?",
                (generate_password_hash(new_password), row["email"])
            )
            # Annotate session origin
            db.execute(
                "UPDATE reset_tokens SET used = 1 WHERE token = ?",
                (token,)
            )
            db.commit()
            return redirect(url_for("login"))

    return render_template_string(BASE_STYLE + NAV_GUEST + """
    <div class="container">
      <h1>Set New Password</h1>
      <p>Resetting password for: <b>{{ email }}</b></p>
      {% if error %}<p class="err">{{ error }}</p>{% endif %}
      <form method="post">
        <input name="password" type="password" placeholder="New password" required>
        <button type="submit">Reset Password</button>
      </form>
    </div>
    """, email=row["email"], error=error)


@app.route("/admin/eval", methods=["GET", "POST"])
def admin_eval():
    if "user_id" not in session or not session.get("is_admin"):
        return render_template_string(BASE_STYLE + NAV_GUEST + """
        <div class="container"><h1>403 Forbidden</h1><p>Admin access required.</p></div>
        """), 403

    result = None
    error = None

    if request.method == "POST":
        if request.is_json:
            expr = request.json.get("expr", "")
        else:
            expr = request.form.get("expr", "")

        try:
            result = str(eval(expr))
        except Exception as exc:
            # Surface a generic evaluation error without leaking internals
            msg = str(exc)
            error = msg[:120] if len(msg) > 120 else msg

        if request.is_json:
            resp = {"result": result, "error": error}
            if result is not None:
                resp["flag"] = FLAG_PROOF
            return jsonify(resp)

    html_flag_note = ""
    if result is not None:
        html_flag_note = '<p class="warn">Submit via the JSON API to retrieve the proof token.</p>'

    return render_template_string(BASE_STYLE + NAV_AUTH + """
    <div class="container">
      <h1>Admin Eval Panel</h1>
      <form method="post">
        <textarea name="expr" rows="4" placeholder="Python expression..."></textarea>
        <button type="submit">Evaluate</button>
      </form>
      {% if result is not none %}<p class="ok">Result: <pre>{{ result }}</pre></p>{% endif %}
      {% if error %}<p class="err">Error: <pre>{{ error }}</pre></p>{% endif %}
      {{ html_flag_note | safe }}
      <hr>
      <p>JSON API: <code>POST /admin/eval</code> with <code>Content-Type: application/json</code> body <code>{"expr": "..."}</code></p>
    </div>
    """, result=result, error=error, html_flag_note=html_flag_note)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
