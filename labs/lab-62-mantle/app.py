"""
Mantle — Project Collaboration Platform
"""
from flask import Flask, render_template_string, request, session, redirect, abort, jsonify
import psycopg2, psycopg2.extras
import os, base64, json, time, uuid
from html import escape as h
from functools import wraps
from werkzeug.security import generate_password_hash, check_password_hash
from werkzeug.utils import secure_filename

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'mantle-dev-fallback')
DATABASE_URL = os.environ.get('DATABASE_URL', 'dbname=mantle user=mantle password=mantlepass host=localhost')

ALLOWED_EXT   = {'pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg'}
ALLOWED_MIME  = {
    'application/pdf', 'text/plain', 'image/png', 'image/jpeg',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
}
UPLOAD_DIR = '/tmp/mantle_uploads'
os.makedirs(UPLOAD_DIR, exist_ok=True)

# ── DB ────────────────────────────────────────────────────────────────────────

def db():
    conn = psycopg2.connect(DATABASE_URL)
    conn.autocommit = True
    return conn


# ── Auth decorators ───────────────────────────────────────────────────────────

def login_required(f):
    @wraps(f)
    def g(*a, **kw):
        if 'uid' not in session:
            return redirect('/login')
        return f(*a, **kw)
    return g


def admin_required(f):
    @wraps(f)
    def g(*a, **kw):
        if 'uid' not in session:
            return redirect('/login')
        if not session.get('admin'):
            abort(403)
        return f(*a, **kw)
    return g


# ── Invite tokens ─────────────────────────────────────────────────────────────
# Tokens are base64url-encoded JSON payloads. No signature.

def make_token(email, role='user'):
    payload = {'email': email, 'role': role, 'exp': int(time.time()) + 86400}
    return base64.urlsafe_b64encode(json.dumps(payload).encode()).decode().rstrip('=')


def read_token(tok):
    try:
        padded = tok + '=' * (-len(tok) % 4)
        data = json.loads(base64.urlsafe_b64decode(padded))
        return data if data.get('exp', 0) > time.time() else None
    except Exception:
        return None


# ── Base template ─────────────────────────────────────────────────────────────

_BASE = """<!DOCTYPE html>
<html><head><meta charset=UTF-8>
<title>{{ title }} — Mantle</title>
<style>
body{font-family:sans-serif;max-width:960px;margin:40px auto;padding:0 20px;background:#f4f4f4}
nav{background:#1c2438;padding:12px 20px;border-radius:4px;margin-bottom:20px}
nav a{color:#aac4ff;margin-right:16px;text-decoration:none;font-size:.9rem}
.card{background:#fff;padding:20px;border-radius:4px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.flag{background:#27ae60;color:#fff;padding:10px 14px;border-radius:4px;font-family:monospace;margin-bottom:14px;word-break:break-all}
input,textarea{width:100%;padding:8px;margin:4px 0 10px;box-sizing:border-box;border:1px solid #ccc;border-radius:3px}
button,input[type=submit]{background:#2c6fad;color:#fff;border:none;padding:8px 18px;border-radius:3px;cursor:pointer}
table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:6px 10px;text-align:left}
.err{color:#c0392b}.ok{color:#27ae60}small{color:#777}
</style></head><body>
<nav>
  <a href="/">Mantle</a>
  <a href="/projects">Projects</a>
  {% if session.user %}<a href="/dashboard">Dashboard</a>{% endif %}
  {% if session.admin %}<a href="/admin">Admin</a>{% endif %}
  {% if session.user %}
    <a href="/logout">Logout ({{ session.user }})</a>
  {% else %}
    <a href="/login">Login</a>&nbsp;<a href="/register">Register</a>
  {% endif %}
</nav>
{{ body | safe }}
</body></html>"""


def page(title, body):
    return render_template_string(_BASE, title=title, body=body, session=session)


# ── Public routes ─────────────────────────────────────────────────────────────

@app.route('/')
def index():
    conn = db(); cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("""SELECT p.id, p.name, p.description, u.username owner
                   FROM projects p JOIN users u ON u.id = p.owner_id
                   ORDER BY p.created_at DESC LIMIT 8""")
    rows = cur.fetchall(); conn.close()
    items = ''.join(
        f"<p><a href='/project/{r['id']}'>{h(r['name'])}</a> <small>by {h(r['owner'])}</small></p>"
        for r in rows
    )
    return page('Home', f"<div class='card'><h2>Recent Projects</h2>{items}</div>")


@app.route('/projects')
def projects():
    q = request.args.get('q', '').strip()
    conn = db(); cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    if q:
        # Build LIKE pattern in Python, pass as query parameter
        like = f'%{q}%'
        cur.execute("""SELECT p.id, p.name, p.description, u.username owner
                       FROM projects p JOIN users u ON u.id = p.owner_id
                       WHERE p.name LIKE %s OR p.description LIKE %s
                       ORDER BY p.created_at DESC""", (like, like))
    else:
        cur.execute("""SELECT p.id, p.name, p.description, u.username owner
                       FROM projects p JOIN users u ON u.id = p.owner_id
                       ORDER BY p.created_at DESC""")
    rows = cur.fetchall(); conn.close()
    form = (f"<form method=get><input name=q value='{h(q)}' placeholder='Search projects…'>"
            "<button>Search</button></form>")
    items = ''.join(
        f"<p><a href='/project/{r['id']}'>{h(r['name'])}</a> — <small>{h(r['description'][:90])}</small></p>"
        for r in rows
    )
    return page('Projects', f"<div class='card'><h2>Projects</h2>{form}{items}</div>")


@app.route('/register', methods=['GET', 'POST'])
def register():
    err = ''
    if request.method == 'POST':
        tok   = request.form.get('token', '').strip()
        uname = request.form.get('username', '').strip()
        pwd   = request.form.get('password', '').strip()
        data  = read_token(tok)
        if not data:
            err = 'Invalid or expired invite token.'
        elif not uname or not pwd:
            err = 'All fields required.'
        else:
            conn = db(); cur = conn.cursor()
            try:
                cur.execute(
                    "INSERT INTO users(username, email, password_hash, role) VALUES(%s,%s,%s,%s)",
                    (uname, data['email'], generate_password_hash(pwd), data.get('role', 'user'))
                )
                return redirect('/login?reg=1')
            except psycopg2.errors.UniqueViolation:
                err = 'Username already taken.'
            finally:
                conn.close()
    form = (f"<p class=err>{h(err)}</p>" if err else '') + """
        <form method=post>
          <label>Invite Token</label><input name=token required>
          <label>Username</label><input name=username required>
          <label>Password</label><input name=password type=password required>
          <button>Register</button>
        </form>"""
    return page('Register', f"<div class='card'><h2>Create Account</h2>{form}</div>")


@app.route('/login', methods=['GET', 'POST'])
def login():
    err = ''
    if request.method == 'POST':
        uname = request.form.get('username', '')
        pwd   = request.form.get('password', '')
        conn = db(); cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
        cur.execute("SELECT * FROM users WHERE username = %s", (uname,))
        u = cur.fetchone(); conn.close()
        if u and check_password_hash(u['password_hash'], pwd):
            session.update(uid=u['id'], user=u['username'], admin=(u['role'] == 'admin'))
            return redirect('/admin' if u['role'] == 'admin' else '/dashboard')
        err = 'Invalid credentials.'
    notice = '<p class=ok>Registered. Please log in.</p>' if request.args.get('reg') else ''
    form = notice + (f"<p class=err>{h(err)}</p>" if err else '') + """
        <form method=post>
          <label>Username</label><input name=username required>
          <label>Password</label><input name=password type=password required>
          <button>Login</button>
        </form>"""
    return page('Login', f"<div class='card'><h2>Login</h2>{form}</div>")


@app.route('/logout')
def logout():
    session.clear()
    return redirect('/login')


# ── Authenticated routes ──────────────────────────────────────────────────────

@app.route('/dashboard')
@login_required
def dashboard():
    conn = db(); cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("SELECT * FROM projects WHERE owner_id = %s ORDER BY created_at DESC", (session['uid'],))
    rows = cur.fetchall(); conn.close()
    items = ''.join(f"<p><a href='/project/{r['id']}'>{h(r['name'])}</a></p>" for r in rows)
    return page('Dashboard',
        f"<div class='card'><h2>My Projects</h2>"
        f"<p><a href='/project/new'>+ New Project</a></p>{items}</div>")


@app.route('/project/new', methods=['GET', 'POST'])
@login_required
def new_project():
    err = ''
    if request.method == 'POST':
        name = request.form.get('name', '').strip()
        desc = request.form.get('description', '').strip()
        tmpl = request.form.get('report_template', '').strip()
        if not name:
            err = 'Project name required.'
        else:
            conn = db(); cur = conn.cursor()
            cur.execute(
                "INSERT INTO projects(name, description, report_template, owner_id)"
                " VALUES(%s,%s,%s,%s) RETURNING id",
                (name, desc, tmpl, session['uid'])
            )
            pid = cur.fetchone()[0]; conn.close()
            return redirect(f'/project/{pid}')
    form = (f"<p class=err>{h(err)}</p>" if err else '') + """
        <form method=post>
          <label>Project Name</label><input name=name required>
          <label>Description</label><textarea name=description rows=3></textarea>
          <label>Report Template <small>(Jinja2 — rendered by admin report generator)</small></label>
          <textarea name=report_template rows=5
            placeholder='&lt;h2&gt;{{ project_name }}&lt;/h2&gt;&lt;p&gt;{{ description }}&lt;/p&gt;'
          ></textarea>
          <button>Create Project</button>
        </form>"""
    return page('New Project', f"<div class='card'><h2>New Project</h2>{form}</div>")


@app.route('/project/<int:pid>')
@login_required
def view_project(pid):
    conn = db(); cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("""SELECT p.*, u.username owner
                   FROM projects p JOIN users u ON u.id = p.owner_id
                   WHERE p.id = %s""", (pid,))
    proj = cur.fetchone(); conn.close()
    if not proj: abort(404)
    body = (f"<div class='card'><h2>{h(proj['name'])}</h2>"
            f"<p>{h(proj['description'])}</p>"
            f"<p><small>Owner: {h(proj['owner'])}</small></p>"
            f"<p><a href='/project/{pid}/attachments'>Attachments</a></p></div>")
    return page(proj['name'], body)


@app.route('/project/<int:pid>/attachments', methods=['GET', 'POST'])
@login_required
def attachments(pid):
    err = msg = ''
    if request.method == 'POST':
        f = request.files.get('file')
        if not f or not f.filename:
            err = 'No file selected.'
        else:
            fname = secure_filename(f.filename)
            ext = fname.rsplit('.', 1)[-1].lower() if '.' in fname else ''
            if ext not in ALLOWED_EXT:
                err = f'Extension .{ext} not permitted. Allowed: {", ".join(sorted(ALLOWED_EXT))}'
            else:
                chunk = f.read(4096)
                import magic
                mime = magic.from_buffer(chunk, mime=True)
                if mime not in ALLOWED_MIME:
                    err = f'Content type {mime!r} not permitted.'
                else:
                    dest = os.path.join(UPLOAD_DIR, f'{uuid.uuid4()}_{fname}')
                    with open(dest, 'wb') as out:
                        out.write(chunk)
                        out.write(f.read())
                    msg = f'Uploaded: {h(fname)}'
    form = (f"<p class=err>{err}</p>" if err else '') + (f"<p class=ok>{msg}</p>" if msg else '') + """
        <form method=post enctype=multipart/form-data>
          <input type=file name=file>
          <button>Upload</button>
        </form>"""
    return page('Attachments', f"<div class='card'><h2>Attachments — Project {pid}</h2>{form}</div>")


# ── Admin routes ──────────────────────────────────────────────────────────────

@app.route('/admin')
@admin_required
def admin():
    conn = db(); cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("SELECT id, username, email, role, created_at FROM users ORDER BY id")
    users = cur.fetchall()
    cur.execute("""SELECT p.id, p.name, u.username owner
                   FROM projects p JOIN users u ON u.id = p.owner_id
                   ORDER BY p.created_at DESC""")
    projs = cur.fetchall(); conn.close()

    flag = ''
    if os.path.exists('/flag_local.txt'):
        flag = open('/flag_local.txt').read().strip()

    body = (f"<div class='flag'>{flag}</div>" if flag else '') + "<div class='card'><h2>Admin Panel</h2>"
    body += "<h3>Users</h3><table><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>"
    body += ''.join(
        f"<tr><td>{u['id']}</td><td>{h(u['username'])}</td>"
        f"<td>{h(u['email'])}</td><td>{h(u['role'])}</td></tr>"
        for u in users
    )
    body += "</table><h3>Projects</h3><ul>"
    body += ''.join(
        f"<li><a href='/project/{p['id']}'>{h(p['name'])}</a> "
        f"<small>(by {h(p['owner'])})</small> — "
        f"<a href='/admin/report/{p['id']}'>Generate Report</a></li>"
        for p in projs
    )
    body += "</ul></div>"
    return page('Admin', body)


@app.route('/admin/report/<int:pid>')
@admin_required
def admin_report(pid):
    conn = db(); cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("""SELECT p.*, u.username owner
                   FROM projects p JOIN users u ON u.id = p.owner_id
                   WHERE p.id = %s""", (pid,))
    proj = cur.fetchone(); conn.close()
    if not proj: abort(404)

    tmpl = proj['report_template'] or '<h2>{{ project_name }}</h2><p>{{ description }}</p>'
    rendered = render_template_string(
        tmpl,
        project_name=proj['name'],
        description=proj['description'],
        owner=proj['owner'],
        created=str(proj['created_at']),
    )
    return f'<html><body style="font-family:sans-serif;max-width:800px;margin:40px auto">{rendered}</body></html>'


@app.route('/api/invite', methods=['POST'])
@admin_required
def api_invite():
    data = request.get_json(force=True) or {}
    email = data.get('email', '')
    role  = data.get('role', 'user')
    if role not in ('user', 'admin'):
        return jsonify(error='invalid role'), 400
    return jsonify(token=make_token(email, role))


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
