"""
Titan Commerce Platform - Internal Admin Dashboard
Version: 2.4.1
"""

import os
import psycopg2
import psycopg2.extras
from flask import (
    Flask, request, session, redirect, url_for,
    render_template_string, jsonify, g
)
from werkzeug.security import generate_password_hash, check_password_hash

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "titan-secret-do-not-share-xK9m")

DB_DSN = os.environ.get("DATABASE_URL", "dbname=titan user=titan password=titan host=localhost")

FLAG_LOCAL = os.environ.get("FLAG_LOCAL", "FLAG_LOCAL{placeholder}")
FLAG_PROOF = os.environ.get("FLAG_PROOF", "FLAG_PROOF{placeholder}")

# ---------------------------------------------------------------------------
# DB helpers
# ---------------------------------------------------------------------------

def get_db():
    if "db" not in g:
        g.db = psycopg2.connect(DB_DSN)
        g.db.autocommit = True
    return g.db

@app.teardown_appcontext
def close_db(exc):
    db = g.pop("db", None)
    if db is not None:
        db.close()

def query(sql, params=None, fetchone=False, fetchall=False):
    cur = get_db().cursor(cursor_factory=psycopg2.extras.RealDictCursor)
    cur.execute(sql, params)
    if fetchone:
        return cur.fetchone()
    if fetchall:
        return cur.fetchall()
    return cur

# ---------------------------------------------------------------------------
# Templates (inline for single-file simplicity)
# ---------------------------------------------------------------------------

BASE = """<!DOCTYPE html>
<html>
<head><title>Titan Commerce</title>
<style>
  body{font-family:Arial,sans-serif;margin:0;background:#f4f6f8;}
  nav{background:#1a1a2e;color:#fff;padding:12px 24px;display:flex;gap:20px;align-items:center;}
  nav a{color:#e0e0e0;text-decoration:none;font-size:14px;}
  nav a:hover{color:#fff;}
  .container{max-width:900px;margin:30px auto;padding:0 20px;}
  .card{background:#fff;border-radius:6px;padding:24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.1);}
  .btn{display:inline-block;padding:8px 18px;background:#1a1a2e;color:#fff;border:none;border-radius:4px;cursor:pointer;text-decoration:none;font-size:14px;}
  .btn-danger{background:#c0392b;}
  table{width:100%;border-collapse:collapse;}
  th,td{text-align:left;padding:10px 12px;border-bottom:1px solid #eee;}
  th{background:#f0f0f0;}
  .alert{padding:12px;border-radius:4px;margin-bottom:16px;}
  .alert-error{background:#fdecea;color:#b71c1c;}
  .alert-success{background:#e8f5e9;color:#1b5e20;}
  input,select{width:100%;padding:8px;margin:6px 0 14px;box-sizing:border-box;border:1px solid #ccc;border-radius:4px;}
  label{font-size:13px;color:#555;}
  .badge{padding:3px 8px;border-radius:12px;font-size:12px;background:#e8eaf6;color:#3949ab;}
  .badge-admin{background:#fce4ec;color:#880e4f;}
</style>
</head>
<body>
<nav>
  <span style="font-weight:bold;font-size:16px;">⬡ Titan</span>
  <a href="/">Home</a>
  <a href="/products">Products</a>
  {% if session.get('user_id') %}
    <a href="/profile">Profile</a>
    {% if session.get('role') == 'admin' %}
      <a href="/admin/report">Reports</a>
    {% endif %}
    <a href="/logout">Logout</a>
  {% else %}
    <a href="/login">Login</a>
    <a href="/register">Register</a>
  {% endif %}
</nav>
<div class="container">
{% with messages = get_flashed_messages(with_categories=true) %}{% endwith %}
{{ content }}
</div>
</body></html>"""

def render(content, **kwargs):
    from flask import get_flashed_messages
    return render_template_string(BASE, content=content, **kwargs)

# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------

@app.route("/")
def index():
    products = query("SELECT * FROM products LIMIT 5", fetchall=True)
    rows = "".join(
        f"<tr><td>{p['name']}</td><td>${p['price']}</td><td>{p['stock']}</td></tr>"
        for p in products
    )
    content = f"""
    <div class="card">
      <h2>Welcome to Titan Commerce</h2>
      <p>Your trusted B2B platform for technology products and services.</p>
    </div>
    <div class="card">
      <h3>Featured Products</h3>
      <table><tr><th>Product</th><th>Price</th><th>Stock</th></tr>{rows}</table>
      <br><a class="btn" href="/products">View All Products</a>
    </div>
    """
    return render(content)


@app.route("/register", methods=["GET", "POST"])
def register():
    error = ""
    if request.method == "POST":
        username = request.form.get("username", "").strip()
        password = request.form.get("password", "")
        email = request.form.get("email", "").strip()

        if not username or not password:
            error = "Username and password are required."
        elif len(username) > 128:
            error = "Username too long."
        else:
            pw_hash = generate_password_hash(password)
            try:
                # Safely store all user-supplied data with parameterized query
                query(
                    "INSERT INTO users (username, password, email) VALUES (%s, %s, %s)",
                    (username, pw_hash, email)
                )
                return redirect(url_for("login"))
            except psycopg2.errors.UniqueViolation:
                error = "Username already taken."
            except Exception as e:
                error = "Registration failed. Please try again."

    form = f"""
    <div class="card" style="max-width:440px;margin:auto;">
      <h2>Create Account</h2>
      {'<div class="alert alert-error">'+error+'</div>' if error else ''}
      <form method="POST">
        <label>Username</label>
        <input name="username" maxlength="128" required>
        <label>Email</label>
        <input name="email" type="email">
        <label>Password</label>
        <input name="password" type="password" required>
        <button class="btn" type="submit">Register</button>
      </form>
      <p style="margin-top:14px;font-size:13px;">Already have an account? <a href="/login">Log in</a></p>
    </div>
    """
    return render(form)


@app.route("/login", methods=["GET", "POST"])
def login():
    error = ""
    if request.method == "POST":
        username = request.form.get("username", "").strip()
        password = request.form.get("password", "")
        user = query(
            "SELECT * FROM users WHERE username = %s",
            (username,), fetchone=True
        )
        if user and check_password_hash(user["password"], password):
            session.clear()
            session["user_id"] = user["id"]
            session["username"] = user["username"]
            session["role"] = user["role"]
            if user["role"] == "admin":
                return redirect(url_for("admin_report"))
            return redirect(url_for("profile"))
        error = "Invalid credentials."

    form = f"""
    <div class="card" style="max-width:440px;margin:auto;">
      <h2>Sign In</h2>
      {'<div class="alert alert-error">'+error+'</div>' if error else ''}
      <form method="POST">
        <label>Username</label>
        <input name="username" required>
        <label>Password</label>
        <input name="password" type="password" required>
        <button class="btn" type="submit">Login</button>
      </form>
      <p style="margin-top:14px;font-size:13px;">No account? <a href="/register">Register</a></p>
    </div>
    """
    return render(form)


@app.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("index"))


@app.route("/products")
def products():
    # Full-text product search with parameterized query to prevent injection
    search = request.args.get("search", "").strip()
    if search:
        rows = query(
            "SELECT * FROM products WHERE name ILIKE %s ORDER BY name",
            ("%" + search + "%",), fetchall=True
        )
    else:
        rows = query("SELECT * FROM products ORDER BY name", fetchall=True)

    product_rows = "".join(
        f"<tr><td>{p['id']}</td><td>{p['name']}</td><td>${p['price']}</td>"
        f"<td>{p['stock']}</td></tr>"
        for p in rows
    )
    content = f"""
    <div class="card">
      <h2>Product Catalog</h2>
      <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;">
        <input name="search" placeholder="Search products..." value="{search}" style="margin:0;">
        <button class="btn" type="submit">Search</button>
      </form>
      <table>
        <tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th></tr>
        {product_rows if product_rows else '<tr><td colspan="4">No products found.</td></tr>'}
      </table>
    </div>
    """
    return render(content)


@app.route("/profile")
def profile():
    if "user_id" not in session:
        return redirect(url_for("login"))

    user = query(
        "SELECT id, username, email, role, created_at FROM users WHERE id = %s",
        (session["user_id"],), fetchone=True
    )
    uname = user["username"]
    cur = get_db().cursor(cursor_factory=psycopg2.extras.RealDictCursor)
    cur.execute(
        "SELECT o.id, o.qty, o.status, o.created_at, p.name "
        "FROM orders o JOIN users u ON o.user_id = u.id "
        "JOIN products p ON o.product_id = p.id "
        "WHERE u.username = '" + uname + "' ORDER BY o.created_at DESC"
    )
    orders = cur.fetchall()
    order_rows = "".join(
        f"<tr><td>{o['id']}</td><td>{o['name']}</td><td>{o['qty']}</td>"
        f"<td>{o['status']}</td><td>{o['created_at']}</td></tr>"
        for o in orders
    ) or "<tr><td colspan='5'>No orders yet.</td></tr>"

    role_badge = f'<span class="badge{"-admin" if user["role"]=="admin" else ""} badge">{user["role"]}</span>'
    content = f"""
    <div class="card">
      <h2>My Profile</h2>
      <table style="width:auto;">
        <tr><td style="padding-right:40px;"><strong>Username</strong></td><td>{user['username']}</td></tr>
        <tr><td><strong>Email</strong></td><td>{user['email'] or '—'}</td></tr>
        <tr><td><strong>Role</strong></td><td>{role_badge}</td></tr>
        <tr><td><strong>Member since</strong></td><td>{user['created_at']}</td></tr>
      </table>
    </div>
    <div class="card">
      <h3>Your Token</h3>
      <p style="font-family:monospace;background:#f0f0f0;padding:10px;border-radius:4px;">{FLAG_LOCAL}</p>
    </div>
    <div class="card">
      <h3>Order History</h3>
      <table><tr><th>#</th><th>Product</th><th>Qty</th><th>Status</th><th>Date</th></tr>
      {order_rows}</table>
    </div>
    """
    return render(content)


@app.route("/admin/report")
def admin_report():
    if session.get("role") != "admin":
        return render('<div class="card"><p>Access denied.</p></div>'), 403

    target = request.args.get("user", "").strip()
    report_html = ""

    if target:
        # Look up user record first
        user_row = query(
            "SELECT * FROM users WHERE username = %s",
            (target,), fetchone=True
        )
        if user_row:
            uname = user_row["username"]
            cur = get_db().cursor(cursor_factory=psycopg2.extras.RealDictCursor)
            cur.execute(
                "SELECT o.id, o.qty, o.status, o.created_at, p.name as product "
                "FROM orders o JOIN users u ON o.user_id = u.id "
                "JOIN products p ON o.product_id = p.id "
                "WHERE u.username = '" + uname + "'"
            )
            order_data = cur.fetchall()
            rows = "".join(
                f"<tr><td>{r['id']}</td><td>{r['product']}</td><td>{r['qty']}</td>"
                f"<td>{r['status']}</td><td>{r['created_at']}</td></tr>"
                for r in order_data
            ) or "<tr><td colspan='5'>No orders found.</td></tr>"

            report_html = f"""
            <div class="card">
              <h3>Activity Report: {user_row['username']}</h3>
              <p>Role: {user_row['role']} &nbsp;|&nbsp; Email: {user_row['email'] or '—'} &nbsp;|&nbsp; Joined: {user_row['created_at']}</p>
              <table><tr><th>#</th><th>Product</th><th>Qty</th><th>Status</th><th>Date</th></tr>
              {rows}</table>
            </div>
            """
        else:
            report_html = '<div class="card"><p>User not found.</p></div>'

    all_users = query(
        "SELECT id, username, role, created_at FROM users ORDER BY id",
        fetchall=True
    )
    user_options = "".join(
        f'<option value="{u["username"]}">{u["username"]} ({u["role"]})</option>'
        for u in all_users
    )

    content = f"""
    <div class="card">
      <h2>Admin — User Activity Reports</h2>
      <div style="margin-bottom:16px;padding:10px;background:#fce4ec;border-radius:4px;">
        <strong>Admin Flag:</strong> <span style="font-family:monospace;">{FLAG_LOCAL}</span>
      </div>
      <form method="GET">
        <label>Select user to generate report:</label>
        <select name="user" style="margin:8px 0 14px;">
          <option value="">— choose —</option>
          {user_options}
        </select>
        <button class="btn" type="submit">Generate Report</button>
      </form>
    </div>
    {report_html}
    """
    return render(content)


# ---------------------------------------------------------------------------
# Bootstrap flag endpoint (only accessible from localhost in real deploy)
# ---------------------------------------------------------------------------

@app.route("/healthz")
def healthz():
    return jsonify({"status": "ok", "version": "2.4.1"})


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
