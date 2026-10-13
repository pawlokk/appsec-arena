import os
import re
import subprocess
import pymysql
from flask import Flask, request, session, jsonify, abort, render_template_string

app = Flask(__name__)
app.secret_key = os.urandom(24)

FLAG_LOCAL = os.environ.get("FLAG_LOCAL", "FLAG_LOCAL_PLACEHOLDER")

def get_db():
    return pymysql.connect(
        host="127.0.0.1",
        user="appuser",
        password="apppass",
        database="arena",
        cursorclass=pymysql.cursors.DictCursor
    )

BLOCKED_PATTERNS = [' ', 'OR', 'UNION', 'SELECT', 'DROP', '--', 'SLEEP', 'BENCHMARK']

def waf_check(value):
    for pattern in BLOCKED_PATTERNS:
        if pattern.lower() in value.lower():
            abort(403)

LOGIN_PAGE = """<!DOCTYPE html>
<html>
<head><title>Collide - Login</title>
<style>
body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
.box { background: #16213e; padding: 40px; border: 1px solid #0f3460; border-radius: 4px; width: 320px; }
h2 { color: #e94560; margin-top: 0; }
input { width: 100%; padding: 8px; margin: 8px 0; background: #0f3460; border: 1px solid #e94560; color: #e0e0e0; box-sizing: border-box; }
button { width: 100%; padding: 10px; background: #e94560; border: none; color: white; cursor: pointer; font-size: 16px; }
.error { color: #e94560; margin-top: 10px; }
</style></head>
<body>
<div class="box">
<h2>Collide</h2>
<form method="POST" action="/login">
<input type="text" name="username" placeholder="Username" /><br>
<input type="password" name="password" placeholder="Password" /><br>
<button type="submit">Login</button>
</form>
{% if error %}<div class="error">{{ error }}</div>{% endif %}
</div>
</body></html>"""

DASHBOARD_PAGE = """<!DOCTYPE html>
<html>
<head><title>Collide - Dashboard</title>
<style>
body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; padding: 40px; }
h2 { color: #e94560; }
.flag { background: #0f3460; padding: 15px; border: 1px solid #e94560; margin: 20px 0; word-break: break-all; }
a { color: #e94560; }
</style></head>
<body>
<h2>Welcome, {{ username }}</h2>
{% if role == 'admin' %}
<div class="flag">{{ flag }}</div>
{% endif %}
<p><a href="/logout">Logout</a></p>
</body></html>"""

@app.route("/", methods=["GET"])
def index():
    return render_template_string(LOGIN_PAGE, error=None)

@app.route("/login", methods=["POST"])
def login():
    username = request.form.get("username", "")
    password = request.form.get("password", "")

    waf_check(username)
    waf_check(password)

    try:
        conn = get_db()
        cursor = conn.cursor()
        query = "SELECT * FROM users WHERE username='" + username + "' AND password_hash=MD5('" + password + "')"
        cursor.execute(query)
        user = cursor.fetchone()
        conn.close()
    except Exception:
        return render_template_string(LOGIN_PAGE, error="Database error"), 500

    if user:
        session["username"] = user["username"]
        session["role"] = user["role"]
        return render_template_string(DASHBOARD_PAGE, username=user["username"], role=user["role"], flag=FLAG_LOCAL)
    else:
        return render_template_string(LOGIN_PAGE, error="Invalid credentials"), 401

@app.route("/logout")
def logout():
    session.clear()
    return render_template_string(LOGIN_PAGE, error=None)

@app.route("/admin/exec", methods=["POST"])
def admin_exec():
    if session.get("role") != "admin":
        abort(403)
    data = request.get_json(force=True)
    cmd = data.get("cmd", "")
    if not cmd:
        return jsonify({"error": "no cmd"}), 400
    result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=10)
    return jsonify({"stdout": result.stdout, "stderr": result.stderr, "returncode": result.returncode})

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
