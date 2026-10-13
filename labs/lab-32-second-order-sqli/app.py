import os
import pymysql
from flask import Flask, request, session, redirect, url_for, render_template_string, g

app = Flask(__name__)
app.secret_key = 'arena-secret-key-32'

DB_HOST = os.environ.get('DB_HOST', '127.0.0.1')
DB_USER = os.environ.get('DB_USER', 'arena')
DB_PASS = os.environ.get('DB_PASS', 'arena')
DB_NAME = os.environ.get('DB_NAME', 'arena')

def get_db():
    if 'db' not in g:
        g.db = pymysql.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASS,
            database=DB_NAME,
            cursorclass=pymysql.cursors.DictCursor
        )
    return g.db

@app.teardown_appcontext
def close_db(e=None):
    db = g.pop('db', None)
    if db is not None:
        db.close()

BASE_STYLE = """
<style>
body { font-family: monospace; background: #0d0d0d; color: #00ff88; padding: 20px; }
a { color: #00aaff; }
input { background: #1a1a1a; color: #00ff88; border: 1px solid #00ff88; padding: 5px; margin: 4px 0; display: block; width: 300px; }
button { background: #003322; color: #00ff88; border: 1px solid #00ff88; padding: 8px 16px; cursor: pointer; margin-top: 8px; }
table { border-collapse: collapse; margin-top: 10px; }
th, td { border: 1px solid #00ff88; padding: 8px 12px; }
.flag { background: #001a00; border: 1px solid #00ff88; padding: 10px; margin: 10px 0; }
.warn { color: #ffaa00; }
.err { color: #ff4444; }
nav { margin-bottom: 20px; }
nav a { margin-right: 15px; }
pre { background: #1a1a1a; padding: 10px; overflow-x: auto; }
</style>
"""

INDEX_HTML = BASE_STYLE + """
<h2>AppSec Arena — Lab 32</h2>
<nav>
  <a href="/register">Register</a>
  <a href="/login">Login</a>
</nav>
<p>A simple order-management portal. Register an account, place orders, and view reports.</p>
"""

REGISTER_HTML = BASE_STYLE + """
<h2>Register</h2>
<form method="post">
  <label>Username:</label>
  <input name="username" placeholder="e.g. alice">
  <label>Password:</label>
  <input name="password" type="password">
  <button type="submit">Register</button>
</form>
<p><a href="/login">Already have an account? Login</a></p>
{% if msg %}<p class="{{ cls }}">{{ msg }}</p>{% endif %}
"""

LOGIN_HTML = BASE_STYLE + """
<h2>Login</h2>
<form method="post">
  <label>Username:</label>
  <input name="username">
  <label>Password:</label>
  <input name="password" type="password">
  <button type="submit">Login</button>
</form>
<p><a href="/register">No account? Register</a></p>
{% if msg %}<p class="err">{{ msg }}</p>{% endif %}
"""

DASHBOARD_HTML = BASE_STYLE + """
<h2>Dashboard</h2>
<nav>
  <a href="/orders">My Orders</a>
  <a href="/admin/report">Reports</a>
  <a href="/logout">Logout</a>
</nav>
<p>Welcome, <b>{{ username }}</b></p>
<p>Your account is active. Use the navigation above to explore.</p>
"""

ORDERS_HTML = BASE_STYLE + """
<h2>My Orders</h2>
<nav>
  <a href="/dashboard">Dashboard</a>
  <a href="/admin/report">Reports</a>
  <a href="/logout">Logout</a>
</nav>
<form method="post" action="/orders/place">
  <label>Item:</label>
  <input name="item" placeholder="Widget, Gadget, etc.">
  <button type="submit">Place Order</button>
</form>
{% if orders %}
<table>
  <tr><th>ID</th><th>Username</th><th>Item</th><th>Status</th></tr>
  {% for o in orders %}
  <tr><td>{{ o.id }}</td><td>{{ o.username }}</td><td>{{ o.item }}</td><td>{{ o.status }}</td></tr>
  {% endfor %}
</table>
{% else %}
<p>No orders yet.</p>
{% endif %}
"""

REPORT_HTML = BASE_STYLE + """
<h2>Order Report</h2>
<nav>
  <a href="/dashboard">Dashboard</a>
  <a href="/orders">My Orders</a>
  <a href="/logout">Logout</a>
</nav>
<p>Generating report for user: <b>{{ username }}</b></p>
{% if rows %}
<table>
  <tr><th>id</th><th>username</th><th>item</th><th>status</th></tr>
  {% for r in rows %}
  <tr>
    <td>{{ r[0] }}</td>
    <td>{{ r[1] }}</td>
    <td>{{ r[2] }}</td>
    <td>{{ r[3] }}</td>
  </tr>
  {% endfor %}
</table>
{% else %}
<p>No rows returned.</p>
{% endif %}
"""

ADMIN_PANEL_HTML = BASE_STYLE + """
<h2>Admin Panel</h2>
<nav>
  <a href="/dashboard">Dashboard</a>
  <a href="/logout">Logout</a>
</nav>
<p>Welcome to the admin panel, <b>{{ username }}</b>.</p>
{% if flag %}
<div class="flag"><b>FLAG:</b> {{ flag }}</div>
{% endif %}
"""

@app.route('/')
def index():
    return render_template_string(INDEX_HTML)

@app.route('/register', methods=['GET', 'POST'])
def register():
    msg = ''
    cls = 'err'
    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        password = request.form.get('password', '').strip()
        if not username or not password:
            msg = 'Username and password required.'
        else:
            db = get_db()
            with db.cursor() as cur:
                cur.execute(
                    "INSERT INTO users (username, password) VALUES (%s, %s)",
                    (username, password)
                )
            db.commit()
            msg = 'Registered successfully. Go login.'
            cls = 'warn'
    return render_template_string(REGISTER_HTML, msg=msg, cls=cls)

@app.route('/login', methods=['GET', 'POST'])
def login():
    msg = ''
    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        password = request.form.get('password', '').strip()
        db = get_db()
        with db.cursor() as cur:
            cur.execute(
                "SELECT * FROM users WHERE username = %s AND password = %s",
                (username, password)
            )
            user = cur.fetchone()
        if user:
            session['user_id'] = user['id']
            session['username'] = user['username']
            session['role'] = user.get('role', 'user')
            return redirect(url_for('dashboard'))
        else:
            msg = 'Invalid credentials.'
    return render_template_string(LOGIN_HTML, msg=msg)

@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('index'))

@app.route('/dashboard')
def dashboard():
    if 'user_id' not in session:
        return redirect(url_for('login'))
    return render_template_string(DASHBOARD_HTML, username=session['username'])

@app.route('/orders')
def orders():
    if 'user_id' not in session:
        return redirect(url_for('login'))
    db = get_db()
    with db.cursor() as cur:
        cur.execute("SELECT * FROM orders WHERE username = %s", (session['username'],))
        rows = cur.fetchall()
    return render_template_string(ORDERS_HTML, orders=rows)

@app.route('/orders/place', methods=['POST'])
def place_order():
    if 'user_id' not in session:
        return redirect(url_for('login'))
    item = request.form.get('item', '').strip()
    if item:
        db = get_db()
        with db.cursor() as cur:
            cur.execute(
                "INSERT INTO orders (username, item, status) VALUES (%s, %s, 'pending')",
                (session['username'], item)
            )
        db.commit()
    return redirect(url_for('orders'))

@app.route('/admin/report')
def admin_report():
    if 'user_id' not in session:
        return redirect(url_for('login'))

    db = get_db()
    with db.cursor() as cur:
        cur.execute("SELECT username FROM users WHERE id = %s", (session['user_id'],))
        user = cur.fetchone()

    stored_username = user['username']

    rows = []
    try:
        query = "SELECT id, username, item, status FROM orders WHERE username = '" + stored_username + "'"
        with db.cursor() as cur:
            cur.execute(query)
            raw = cur.fetchall()
            normalized = []
            for r in raw:
                if isinstance(r, dict):
                    normalized.append((r.get('id',''), r.get('username',''), r.get('item',''), r.get('status','')))
                else:
                    normalized.append(tuple(r))
            rows = normalized
    except Exception as e:
        rows = [('ERROR', str(e), '', '')]

    return render_template_string(
        REPORT_HTML,
        username=stored_username,
        rows=rows
    )

@app.route('/admin/panel')
def admin_panel():
    if 'user_id' not in session:
        return redirect(url_for('login'))

    db = get_db()
    with db.cursor() as cur:
        cur.execute("SELECT role FROM users WHERE id = %s", (session['user_id'],))
        user = cur.fetchone()

    if not user or user.get('role') != 'admin':
        return render_template_string(BASE_STYLE + '<h2>Access Denied</h2><p>Administrators only.</p><a href="/dashboard">Back</a>'), 403

    flag = None
    try:
        with open('/flag_proof.txt', 'r') as f:
            flag = f.read().strip()
    except Exception:
        flag = None

    return render_template_string(ADMIN_PANEL_HTML, username=session['username'], flag=flag)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
