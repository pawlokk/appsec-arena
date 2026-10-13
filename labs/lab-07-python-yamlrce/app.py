import os
import sqlite3
import secrets
import time
import yaml
from flask import Flask, request, session, redirect, url_for, render_template_string, g

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'vantage-dev-key-change-in-prod')

DATABASE = '/tmp/vantage.db'

TOKEN_TTL = 900  # seconds

def get_db():
    db = getattr(g, '_database', None)
    if db is None:
        db = g._database = sqlite3.connect(DATABASE)
        db.row_factory = sqlite3.Row
    return db

@app.teardown_appcontext
def close_connection(exception):
    db = getattr(g, '_database', None)
    if db is not None:
        db.close()

def init_db():
    conn = sqlite3.connect(DATABASE)
    c = conn.cursor()
    c.execute('''CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL
    )''')
    c.execute('''CREATE TABLE IF NOT EXISTS reset_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        token TEXT NOT NULL,
        expires_at INTEGER NOT NULL
    )''')
    c.execute('''CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        recipient TEXT NOT NULL,
        subject TEXT NOT NULL,
        body TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        read INTEGER NOT NULL DEFAULT 0
    )''')
    admin_pass = os.environ.get('ADMIN_PASS', secrets.token_urlsafe(24))
    alice_pass = os.environ.get('ALICE_PASS', 'alice2024')
    bob_pass = os.environ.get('BOB_PASS', 'bob2024')
    users = [
        ('admin', admin_pass),
        ('alice', alice_pass),
        ('bob', bob_pass),
    ]
    for username, password in users:
        c.execute('INSERT OR IGNORE INTO users (username, password) VALUES (?, ?)', (username, password))
    conn.commit()
    conn.close()

def generate_reset_token(username):
    token = secrets.token_hex(32)
    expires_at = int(time.time()) + TOKEN_TTL
    db = get_db()
    db.execute('DELETE FROM reset_tokens WHERE username = ?', (username,))
    db.execute('INSERT INTO reset_tokens (username, token, expires_at) VALUES (?, ?, ?)',
               (username, token, expires_at))
    db.execute(
        'INSERT INTO notifications (recipient, subject, body, created_at) VALUES (?, ?, ?, ?)',
        (
            username,
            'Password reset requested',
            'A password reset was initiated for your account. Reset token: {}  '
            'This token expires in 15 minutes. If you did not request this, '
            'contact your system administrator.'.format(token),
            int(time.time()),
        )
    )
    db.commit()
    return token

DARK_BASE = """
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Vantage — {{ title }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #0d0f14; color: #c9d1e0; font-family: 'Segoe UI', sans-serif; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .card { background: #151820; border: 1px solid #252a35; border-radius: 8px; padding: 36px 40px; min-width: 360px; max-width: 520px; width: 100%; }
  h1 { color: #5c8aff; margin-bottom: 8px; font-size: 1.6rem; }
  h2 { color: #5c8aff; margin-bottom: 20px; font-size: 1.2rem; }
  p { margin-bottom: 12px; color: #8a93a8; font-size: 0.92rem; }
  a { color: #5c8aff; text-decoration: none; }
  a:hover { text-decoration: underline; }
  input[type=text], input[type=password], textarea {
    width: 100%; padding: 10px 12px; background: #0d0f14; border: 1px solid #2e3545;
    border-radius: 4px; color: #c9d1e0; font-size: 0.95rem; margin-bottom: 14px;
  }
  textarea { height: 160px; font-family: monospace; resize: vertical; }
  button, input[type=submit] {
    background: #5c8aff; color: #0d0f14; border: none; padding: 10px 22px;
    border-radius: 4px; font-size: 0.95rem; font-weight: 600; cursor: pointer; width: 100%;
  }
  button:hover, input[type=submit]:hover { background: #7aa3ff; }
  .alert { background: #1e2535; border-left: 3px solid #5c8aff; padding: 10px 14px; margin-bottom: 16px; border-radius: 3px; font-size: 0.9rem; }
  .alert.error { border-left-color: #ff5c5c; color: #ff9090; }
  .alert.success { border-left-color: #4caf7d; color: #82d9a8; }
  .output-box { background: #0a0c10; border: 1px solid #252a35; border-radius: 4px; padding: 12px 14px; font-family: monospace; font-size: 0.85rem; color: #b2ffa8; white-space: pre-wrap; word-break: break-all; margin-top: 14px; max-height: 260px; overflow-y: auto; }
  nav { display: flex; gap: 18px; margin-top: 20px; font-size: 0.9rem; }
  .label { color: #5c8aff; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.88rem; }
  td, th { padding: 8px 10px; border-bottom: 1px solid #1e2535; text-align: left; }
  th { color: #5c8aff; font-weight: 600; }
</style>
</head>
<body>
{% block body %}{% endblock %}
</body>
</html>
"""

INDEX_TMPL = DARK_BASE.replace('{% block body %}{% endblock %}', """
<div class="card">
  <h1>Vantage</h1>
  <p>Operational intelligence platform. Authorized personnel only.</p>
  <nav>
    <a href="/login">Sign In</a>
    <a href="/forgot">Reset Password</a>
  </nav>
</div>
""")

LOGIN_TMPL = DARK_BASE.replace('{% block body %}{% endblock %}', """
<div class="card">
  <h1>Vantage</h1>
  <h2>Sign In</h2>
  {% if error %}<div class="alert error">{{ error }}</div>{% endif %}
  <form method="POST" action="/login">
    <div class="label">Username</div>
    <input type="text" name="username" autocomplete="off" required>
    <div class="label">Password</div>
    <input type="password" name="password" required>
    <input type="submit" value="Sign In">
  </form>
  <nav><a href="/forgot">Forgot password?</a></nav>
</div>
""")

FORGOT_TMPL = DARK_BASE.replace('{% block body %}{% endblock %}', """
<div class="card">
  <h1>Vantage</h1>
  <h2>Password Reset</h2>
  {% if message %}<div class="alert">{{ message }}</div>{% endif %}
  {% if error %}<div class="alert error">{{ error }}</div>{% endif %}
  <form method="POST" action="/forgot">
    <div class="label">Username</div>
    <input type="text" name="username" autocomplete="off" required>
    <input type="submit" value="Send Reset Token">
  </form>
  <nav><a href="/login">Back to sign in</a></nav>
</div>
""")

RESET_FORM_TMPL = DARK_BASE.replace('{% block body %}{% endblock %}', """
<div class="card">
  <h1>Vantage</h1>
  <h2>Set New Password</h2>
  {% if error %}<div class="alert error">{{ error }}</div>{% endif %}
  <form method="POST" action="/reset">
    <input type="hidden" name="token" value="{{ token }}">
    <input type="hidden" name="username" value="{{ username }}">
    <div class="label">New Password</div>
    <input type="password" name="password" required>
    <input type="submit" value="Update Password">
  </form>
</div>
""")

DASHBOARD_TMPL = DARK_BASE.replace('{% block body %}{% endblock %}', """
<div class="card">
  <h1>Vantage</h1>
  <h2>Dashboard</h2>
  <p>Welcome, <strong>{{ username }}</strong>. Your session is active.</p>
  {% if is_admin %}
  <nav><a href="/admin">Admin Panel</a> &nbsp;|&nbsp; <a href="/logout">Sign Out</a></nav>
  {% else %}
  <nav><a href="/api/notifications">Notifications</a> &nbsp;|&nbsp; <a href="/logout">Sign Out</a></nav>
  {% endif %}
</div>
""")

ADMIN_TMPL = DARK_BASE.replace('{% block body %}{% endblock %}', """
<div class="card">
  <h1>Vantage</h1>
  <h2>Admin Panel</h2>
  <p>System configuration management. Use the import tool to apply configuration documents.</p>

  <hr style="border-color:#1e2535; margin: 24px 0;">

  <h2>Configuration Import</h2>
  <p>Paste a YAML document to import configuration records into the system.</p>
  <form method="POST" action="/admin/import">
    <textarea name="data" placeholder="key: value&#10;nested:&#10;  field: data"></textarea>
    <button type="submit">Import</button>
  </form>
  {% if output is not none %}
  <div class="label" style="margin-top:12px;">Import Result</div>
  <div class="output-box">{{ output }}</div>
  {% endif %}

  <nav style="margin-top:24px;"><a href="/dashboard">Dashboard</a> &nbsp;|&nbsp; <a href="/logout">Sign Out</a></nav>
</div>
""")

@app.route('/')
def index():
    return render_template_string(INDEX_TMPL, title='Home')

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        password = request.form.get('password', '')
        db = get_db()
        user = db.execute('SELECT * FROM users WHERE username = ? AND password = ?', (username, password)).fetchone()
        if user:
            session['username'] = username
            return redirect(url_for('dashboard'))
        return render_template_string(LOGIN_TMPL, title='Login', error='Invalid credentials.')
    return render_template_string(LOGIN_TMPL, title='Login', error=None)

@app.route('/forgot', methods=['GET', 'POST'])
def forgot():
    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        db = get_db()
        user = db.execute('SELECT * FROM users WHERE username = ?', (username,)).fetchone()
        if not user:
            return render_template_string(FORGOT_TMPL, title='Forgot Password',
                                          message=None, error='No account found with that username.')
        generate_reset_token(username)
        return render_template_string(FORGOT_TMPL, title='Forgot Password',
                                      message='Reset instructions have been dispatched to your registered contact method.',
                                      error=None)
    return render_template_string(FORGOT_TMPL, title='Forgot Password', message=None, error=None)

@app.route('/reset', methods=['GET', 'POST'])
def reset():
    if request.method == 'GET':
        token = request.args.get('token', '')
        username = request.args.get('username', '')
        return render_template_string(RESET_FORM_TMPL, title='Reset Password',
                                      token=token, username=username, error=None)
    token = request.form.get('token', '').strip()
    username = request.form.get('username', '').strip()
    password = request.form.get('password', '')
    db = get_db()
    now = int(time.time())
    row = db.execute(
        'SELECT * FROM reset_tokens WHERE username = ? AND token = ? AND expires_at > ?',
        (username, token, now)
    ).fetchone()
    if not row:
        return render_template_string(RESET_FORM_TMPL, title='Reset Password',
                                      token=token, username=username,
                                      error='Token validation failed. Please request a new reset.')
    db.execute('UPDATE users SET password = ? WHERE username = ?', (password, username))
    db.execute('DELETE FROM reset_tokens WHERE username = ?', (username,))
    db.commit()
    return redirect(url_for('login'))

@app.route('/dashboard')
def dashboard():
    if 'username' not in session:
        return redirect(url_for('login'))
    username = session['username']
    return render_template_string(DASHBOARD_TMPL, title='Dashboard',
                                  username=username, is_admin=(username == 'admin'))

@app.route('/api/notifications')
def api_notifications():
    if 'username' not in session:
        return {'error': 'Unauthorized'}, 401
    current_user = session['username']
    target = request.args.get('user', current_user)
    db = get_db()
    rows = db.execute(
        'SELECT id, recipient, subject, body, created_at FROM notifications WHERE recipient = ? ORDER BY created_at DESC',
        (target,)
    ).fetchall()
    result = [dict(r) for r in rows]
    return {'notifications': result}

@app.route('/admin', methods=['GET'])
def admin():
    if session.get('username') != 'admin':
        return redirect(url_for('login'))
    return render_template_string(ADMIN_TMPL, title='Admin', output=None)

@app.route('/admin/import', methods=['POST'])
def admin_import():
    if session.get('username') != 'admin':
        return redirect(url_for('login'))
    data = request.form.get('data', '')
    output = None
    try:
        result = yaml.load(data, Loader=yaml.Loader)  # configuration parser
        if result is not None:
            output = str(result)
        else:
            output = '(empty document)'
    except Exception:
        output = 'Configuration import failed.'
    return render_template_string(ADMIN_TMPL, title='Admin', output=output)

@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('index'))

if __name__ == '__main__':
    init_db()
    app.run(host='0.0.0.0', port=5000)
