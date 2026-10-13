from flask import Flask, render_template_string, request, session, redirect, abort, jsonify, make_response, Blueprint
import psycopg2, psycopg2.extras
import os, re, time, secrets, subprocess
from functools import wraps
from werkzeug.security import generate_password_hash, check_password_hash
from markupsafe import escape
import jinja2.sandbox

from services.token_service import generate_reset_token, verify_reset_token, generate_invite_token
from services.notification_service import render_notification, get_default_template

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'stratum-dev-fallback-k8x2m')

INTERNAL_KEY = secrets.token_hex(32)
DATABASE_URL = os.environ.get('DATABASE_URL', 'dbname=stratum user=stratum password=stratumpass host=localhost')


def db():
    c = psycopg2.connect(DATABASE_URL)
    c.autocommit = True
    return c


def login_required(f):
    @wraps(f)
    def g(*a, **kw):
        if 'uid' not in session:
            return jsonify(error='Unauthorized'), 401
        return f(*a, **kw)
    return g


def admin_required(f):
    @wraps(f)
    def g(*a, **kw):
        if 'uid' not in session:
            return jsonify(error='Unauthorized'), 401
        if not session.get('admin'):
            return jsonify(error='Forbidden'), 403
        return f(*a, **kw)
    return g


public_bp = Blueprint('public', __name__, url_prefix='/api/public')

@public_bp.after_request
def public_cors(r):
    z = request.headers.get('Origin', '')
    _a = {'https://analytics.internal'}
    if z in _a:
        r.headers['Access-Control-Allow-Origin'] = z
    return r

@public_bp.route('/status')
def public_status():
    return jsonify(status='ok', service='stratum-analytics')

@public_bp.route('/version')
def public_version():
    return jsonify(version='2.4.1', api='v1')

app.register_blueprint(public_bp)


@app.after_request
def apply_cors(r):
    z = request.headers.get('Origin', '')
    if z and re.search(r'analytics\.internal$', z):
        r.headers['Access-Control-Allow-Origin'] = z
        r.headers['Access-Control-Allow-Credentials'] = 'true'
        r.headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, DELETE, OPTIONS'
        r.headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, X-Requested-With'
    return r


@app.route('/api/<path:p>', methods=['OPTIONS'])
def cors_preflight(p):
    return make_response('', 204)


_BASE = """<!DOCTYPE html>
<html><head><meta charset=UTF-8>
<title>{{ title }} — Stratum</title>
<style>
body{font-family:sans-serif;max-width:1000px;margin:40px auto;padding:0 20px;background:#f0f2f5}
nav{background:#0d1b2a;padding:12px 20px;border-radius:4px;margin-bottom:20px;display:flex;gap:16px;align-items:center}
nav a{color:#90b8e0;text-decoration:none;font-size:.9rem}
nav .brand{color:#fff;font-weight:bold;margin-right:8px}
.card{background:#fff;padding:20px;border-radius:6px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.flag{background:#1a7a3c;color:#fff;padding:10px 14px;border-radius:4px;font-family:monospace;margin-bottom:14px;word-break:break-all}
input,textarea,select{width:100%;padding:8px;margin:4px 0 10px;box-sizing:border-box;border:1px solid #ccc;border-radius:3px}
button,input[type=submit]{background:#1565c0;color:#fff;border:none;padding:8px 18px;border-radius:3px;cursor:pointer}
table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:6px 10px;text-align:left}
.err{color:#c0392b}.ok{color:#27ae60}small{color:#777}
.tag{background:#e3f2fd;color:#1565c0;padding:2px 8px;border-radius:10px;font-size:.8rem;margin-right:4px}
</style></head><body>
<nav>
  <span class=brand>Stratum Analytics</span>
  <a href="/">Home</a>
  {% if session.user %}<a href="/dashboard">Dashboard</a>{% endif %}
  {% if session.user %}<a href="/notifications/preferences">Notifications</a>{% endif %}
  {% if session.admin %}<a href="/admin">Admin</a>{% endif %}
  {% if session.user %}
    <a href="/logout" style="margin-left:auto">Logout ({{ session.user }})</a>
  {% else %}
    <a href="/login" style="margin-left:auto">Login</a> |
    <a href="/reset/request">Reset password</a>
  {% endif %}
</nav>
{{ body | safe }}
</body></html>"""


def page(title, body):
    return render_template_string(_BASE, title=title, body=body, session=session)


@app.route('/')
def index():
    return page('Home', """
        <div class='card'>
          <h2>Internal Analytics Dashboard</h2>
          <p>Stratum provides real-time reporting and saved report templates for internal business intelligence.</p>
          <p><a href='/login'>Login to access your dashboard →</a></p>
          <p><small>Version 2.4.1 — Multi-team workspace edition</small></p>
        </div>
    """)


@app.route('/login', methods=['GET', 'POST'])
def login():
    e = ''
    if request.method == 'POST':
        n = request.form.get('username', '')
        p = request.form.get('password', '')
        c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
        s.execute("SELECT * FROM users WHERE username = %s", (n,))
        u = s.fetchone(); c.close()
        if u and check_password_hash(u['password_hash'], p):
            session.update(uid=u['id'], user=u['username'], admin=(u['role'] == 'admin'))
            _la(u['id'], 'login', None)
            return redirect('/admin' if u['role'] == 'admin' else '/dashboard')
        e = 'Invalid credentials.'
    form = (f"<p class=err>{e}</p>" if e else '') + """
        <form method=post>
          <label>Username</label><input name=username required>
          <label>Password</label><input name=password type=password required>
          <button>Login</button>
        </form>
        <p><small><a href='/reset/request'>Forgot password?</a> | <a href='/invite/register'>Register via invite</a></small></p>"""
    return page('Login', f"<div class='card'><h2>Login</h2>{form}</div>")


@app.route('/logout')
def logout():
    session.clear()
    return redirect('/login')


@app.route('/reset/request', methods=['GET', 'POST'])
def reset_request():
    msg = e = ''
    if request.method == 'POST':
        m = request.form.get('email', '').strip()
        c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
        s.execute("SELECT username FROM users WHERE email = %s", (m,))
        u = s.fetchone(); c.close()
        if u:
            k = secrets.token_urlsafe(24)
            c2 = db(); s2 = c2.cursor()
            s2.execute("UPDATE users SET reset_key = %s WHERE username = %s", (k, u['username']))
            c2.close()
        msg = 'If that address is registered, instructions have been sent.'
    form = (f"<p class=ok>{msg}</p>" if msg else '') + (f"<p class=err>{e}</p>" if e else '') + """
        <form method=post>
          <label>Email address</label><input name=email type=email required>
          <button>Request reset</button>
        </form>"""
    return page('Reset Password', f"<div class='card'><h2>Reset Password</h2>{form}</div>")


@app.route('/reset/confirm', methods=['GET', 'POST'])
def reset_confirm():
    n = request.args.get('user', '') or request.form.get('user', '')
    t = request.args.get('token', '') or request.form.get('token', '')
    e = msg = ''

    if request.method == 'POST':
        p = request.form.get('new_password', '')
        if not p or len(p) < 8:
            e = 'Password must be at least 8 characters.'
        elif not verify_reset_token(n, t):
            e = 'Invalid or expired reset token.'
        else:
            c = db(); s = c.cursor()
            s.execute(
                "UPDATE users SET password_hash = %s WHERE username = %s",
                (generate_password_hash(p), n)
            )
            c.close()
            msg = 'Password updated. <a href="/login">Login →</a>'

    form = (f"<p class=err>{e}</p>" if e else '') + (f"<p class=ok>{msg}</p>" if msg else '')
    if not msg:
        form += f"""
            <form method=post>
              <input type=hidden name=user value="{escape(n)}">
              <input type=hidden name=token value="{escape(t)}">
              <label>New password</label><input name=new_password type=password required>
              <button>Set new password</button>
            </form>"""
    return page('Set New Password', f"<div class='card'><h2>Set New Password</h2>{form}</div>")


@app.route('/update-password', methods=['GET', 'POST'])
def update_password():
    k = request.args.get('key', '') or request.form.get('key', '')
    e = msg = ''
    if request.method == 'POST':
        p = request.form.get('new_password', '')
        if not p or len(p) < 8:
            e = 'Password must be at least 8 characters.'
        else:
            c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
            s.execute("SELECT username FROM users WHERE reset_key = %s", (k,))
            u = s.fetchone()
            if not u:
                c.close()
                e = 'Invalid or expired link.'
            else:
                s.execute(
                    "UPDATE users SET password_hash = %s, reset_key = NULL WHERE username = %s",
                    (generate_password_hash(p), u['username'])
                )
                c.close()
                msg = 'Password updated. <a href="/login">Login →</a>'
    form = (f"<p class=err>{e}</p>" if e else '') + (f"<p class=ok>{msg}</p>" if msg else '')
    if not msg:
        form += f"""
            <form method=post>
              <input type=hidden name=key value="{escape(k)}">
              <label>New password</label><input name=new_password type=password required>
              <button>Set new password</button>
            </form>"""
    return page('Update Password', f"<div class='card'><h2>Update Password</h2>{form}</div>")


@app.route('/invite', methods=['POST'])
@login_required
def create_invite():
    b = request.get_json(force=True) or {}
    m = b.get('email', '').strip()
    if not m:
        return jsonify(error='Email required'), 400
    t = generate_invite_token(m, session['uid'])
    c = db(); s = c.cursor()
    s.execute(
        "INSERT INTO invite_tokens(token, email, invited_by) VALUES(%s,%s,%s)"
        " ON CONFLICT(token) DO NOTHING",
        (t, m, session['uid'])
    )
    c.close()
    return jsonify(token=t, accept_url=f'/invite/accept/{t}'), 201


@app.route('/invite/accept/<token>', methods=['GET', 'POST'])
def accept_invite(token):
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT * FROM invite_tokens WHERE token = %s", (token,))
    inv = s.fetchone()
    if not inv:
        c.close()
        return page('Invalid Invite', "<div class='card'><p class=err>Invite token is invalid.</p></div>")

    e = msg = ''
    if request.method == 'POST':
        n = request.form.get('username', '').strip()
        p = request.form.get('password', '')
        if not n or not p:
            e = 'Username and password required.'
        else:
            try:
                s.execute(
                    "INSERT INTO users(username, email, password_hash, role)"
                    " VALUES(%s,%s,%s,'user')",
                    (n, inv['email'], generate_password_hash(p))
                )
                s.execute("UPDATE invite_tokens SET used = TRUE WHERE token = %s", (token,))
                c.close()
                msg = 'Account created. <a href="/login">Login →</a>'
            except Exception as ex:
                c.close()
                e = 'Username already taken.' if 'unique' in str(ex).lower() else 'Registration error.'

    if not msg:
        c.close()
        form = (f"<p class=err>{e}</p>" if e else '') + f"""
            <p>Registering for: <strong>{escape(inv['email'])}</strong></p>
            <form method=post>
              <label>Username</label><input name=username required>
              <label>Password</label><input name=password type=password required>
              <button>Create account</button>
            </form>"""
    else:
        form = f"<p class=ok>{msg}</p>"
    return page('Accept Invite', f"<div class='card'><h2>Accept Invite</h2>{form}</div>")


@app.route('/notifications/preferences', methods=['GET', 'POST'])
def notification_preferences():
    if 'uid' not in session:
        return redirect('/login')
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    e = msg = ''

    if request.method == 'POST':
        x = request.form.get('notification_type', '').strip()
        tmpl = request.form.get('message_template', '').strip()
        v = request.form.get('enabled', 'on') == 'on'

        if x and tmpl:
            s.execute("""
                INSERT INTO notification_preferences(user_id, notification_type, message_template, enabled)
                VALUES(%s,%s,%s,%s)
                ON CONFLICT(user_id, notification_type)
                DO UPDATE SET message_template=EXCLUDED.message_template, enabled=EXCLUDED.enabled
            """, (session['uid'], x, tmpl, v))
            msg = 'Preferences saved.'

    s.execute(
        "SELECT * FROM notification_preferences WHERE user_id = %s ORDER BY notification_type",
        (session['uid'],)
    )
    prefs = s.fetchall(); c.close()

    rows = ''.join(
        f"<tr><td>{p['notification_type']}</td><td><code>{escape(p['message_template'][:60])}</code></td>"
        f"<td>{'Yes' if p['enabled'] else 'No'}</td></tr>"
        for p in prefs
    )

    types = ', '.join(['report_ready', 'invite', 'password_reset', 'report_error'])
    form = (f"<p class=ok>{msg}</p>" if msg else '') + f"""
        <form method=post>
          <label>Notification type <small>({types})</small></label>
          <input name=notification_type required>
          <label>Message template <small>(supports {{ variable }} placeholders)</small></label>
          <textarea name=message_template rows=3 placeholder="Your report {{ report_name }} is ready."></textarea>
          <label><input type=checkbox name=enabled checked> Enabled</label>
          <button>Save</button>
        </form>"""

    body = f"""
        <div class='card'><h2>Notification Preferences</h2>
        <table><tr><th>Type</th><th>Template</th><th>Enabled</th></tr>{rows}</table>
        <h3>Add / Update</h3>{form}</div>"""
    return page('Notifications', body)


@app.route('/api/notifications/send', methods=['POST'])
@login_required
def api_send_notification():
    b = request.get_json(force=True) or {}
    i = b.get('user_id', session['uid'])
    x = b.get('notification_type', 'report_ready')
    v = b.get('context', {})

    if not isinstance(v, dict):
        return jsonify(error='context must be an object'), 400

    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute(
        "SELECT message_template FROM notification_preferences"
        " WHERE user_id = %s AND notification_type = %s AND enabled = TRUE",
        (i, x)
    )
    pref = s.fetchone(); c.close()

    tmpl = pref['message_template'] if pref else get_default_template(x)

    try:
        r = render_notification(tmpl, **v)
        return jsonify(rendered=r, template_used=tmpl)
    except Exception as ex:
        return jsonify(error=f'Render error: {str(ex)}'), 500


@app.route('/dashboard')
def dashboard():
    if 'uid' not in session:
        return redirect('/login')
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT * FROM report_templates WHERE owner_id = %s ORDER BY created_at DESC", (session['uid'],))
    templates = s.fetchall()
    s.execute(
        "SELECT * FROM scheduled_reports WHERE owner_id = %s ORDER BY created_at DESC LIMIT 5",
        (session['uid'],)
    )
    scheduled = s.fetchall(); c.close()

    items = ''.join(
        f"<tr><td>{t['id']}</td><td>{t['name']}</td><td><small>{t['description'][:60]}</small></td>"
        f"<td><a href='/dashboard/template/{t['id']}'>View</a> | "
        f"<a href='/api/reports/schedule' onclick=\"scheduleReport({t['id']}); return false\">Schedule</a></td></tr>"
        for t in templates
    )
    sched_rows = ''.join(
        f"<tr><td>{s['id']}</td><td>template:{s['template_ref']}</td>"
        f"<td><span class='tag'>{s['status']}</span></td>"
        f"<td><a href='/api/reports/result/{s['id']}'>Result</a></td></tr>"
        for s in scheduled
    )
    body = f"""
        <div class='card'>
          <h2>My Report Templates</h2>
          <p><a href='/dashboard/template/new'>+ New Template</a></p>
          <table><tr><th>ID</th><th>Name</th><th>Description</th><th>Actions</th></tr>
          {items}
          </table>
        </div>
        <div class='card'>
          <h2>Scheduled Reports</h2>
          <table><tr><th>ID</th><th>Template</th><th>Status</th><th>Actions</th></tr>
          {sched_rows}
          </table>
        </div>"""
    return page('Dashboard', body)


@app.route('/dashboard/template/new', methods=['GET', 'POST'])
def new_template():
    if 'uid' not in session:
        return redirect('/login')
    e = msg = ''
    if request.method == 'POST':
        n = request.form.get('name', '').strip()
        d = request.form.get('description', '').strip()
        tmpl = request.form.get('template_body', '').strip()
        if not n:
            e = 'Name required.'
        elif '{{' in tmpl or '{%' in tmpl:
            e = 'Invalid input format.'
        else:
            c = db(); s = c.cursor()
            s.execute(
                "INSERT INTO report_templates(name, description, template_body, owner_id)"
                " VALUES(%s,%s,%s,%s) RETURNING id",
                (n, d, tmpl, session['uid'])
            )
            i = s.fetchone()[0]; c.close()
            return redirect(f'/dashboard/template/{i}')
    form = (f"<p class=err>{e}</p>" if e else '') + (f"<p class=ok>{msg}</p>" if msg else '') + """
        <form method=post>
          <label>Template Name</label><input name=name required>
          <label>Description</label><textarea name=description rows=2></textarea>
          <label>Template Body <small>(HTML with placeholders like {report_title}, {date_range})</small></label>
          <textarea name=template_body rows=8
            placeholder='&lt;h2&gt;{report_title}&lt;/h2&gt;&lt;p&gt;Period: {date_range}&lt;/p&gt;'
          ></textarea>
          <button>Save Template</button>
        </form>"""
    return page('New Template', f"<div class='card'><h2>New Report Template</h2>{form}</div>")


@app.route('/dashboard/template/<int:tid>')
def view_template(tid):
    if 'uid' not in session:
        return redirect('/login')
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT * FROM report_templates WHERE id = %s", (tid,))
    tmpl = s.fetchone(); c.close()
    if not tmpl: abort(404)
    if tmpl['owner_id'] != session['uid'] and not session.get('admin'):
        abort(403)
    body = f"""
        <div class='card'>
          <h2>{str(escape(tmpl['name']))}</h2>
          <p><small>{escape(tmpl['description'])}</small></p>
          <h3>Template Body</h3>
          <pre style='background:#f5f5f5;padding:12px;border-radius:4px;overflow:auto'>{escape(tmpl['template_body'])}</pre>
          <p><small>Template ID: {tmpl['id']} — Submit to admin for preview rendering</small></p>
        </div>"""
    return page(tmpl['name'], body)


@app.route('/profile')
def profile():
    if 'uid' not in session:
        return redirect('/login')
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT id, username, email, role, created_at FROM users WHERE id = %s", (session['uid'],))
    u = s.fetchone(); c.close()
    if not u: abort(404)
    body = f"""
        <div class='card'>
          <h2>Profile: {{ name | e }}</h2>
          <p>Email: {escape(u['email'])}</p>
          <p>Role: {escape(u['role'])}</p>
          <p>Member since: {u['created_at']}</p>
        </div>"""
    return render_template_string(
        _BASE,
        title='Profile',
        body=body,
        session=session,
        name=u['username']
    )


@app.route('/api/auth/login', methods=['POST'])
def api_login():
    b = request.get_json(force=True) or {}
    n = b.get('username', '')
    p = b.get('password', '')
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT * FROM users WHERE username = %s", (n,))
    u = s.fetchone(); c.close()
    if u and check_password_hash(u['password_hash'], p):
        session.update(uid=u['id'], user=u['username'], admin=(u['role'] == 'admin'))
        return jsonify(status='ok', username=u['username'], admin=(u['role'] == 'admin'))
    return jsonify(error='Invalid credentials'), 401


@app.route('/api/auth/logout', methods=['POST'])
def api_logout():
    session.clear()
    return jsonify(status='ok')


@app.route('/api/admin/whoami')
def api_whoami():
    if 'uid' not in session:
        return jsonify(error='Unauthorized'), 401
    return jsonify(
        uid=session['uid'],
        username=session['user'],
        admin=session.get('admin', False),
    )


@app.route('/api/reports')
def api_reports():
    if 'uid' not in session:
        return jsonify(error='Unauthorized'), 401

    fv = request.args.get('filter', '')
    sv = request.args.get('sort', 'name')

    if fv and not re.match(r'^[\w\s,]+$', fv):
        return jsonify(error='Invalid filter parameter'), 400

    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    q = (
        f"SELECT id, name, description, created_at"
        f" FROM report_templates WHERE owner_id = {session['uid']}"
        f" ORDER BY {sv}"
    )
    try:
        s.execute(q)
        rows = s.fetchall()
    except Exception:
        c.close()
        return jsonify(error='Query error'), 500

    c.close()
    return jsonify(reports=[dict(r) for r in rows])


@app.route('/api/reports/templates', methods=['POST'])
def api_create_template():
    if 'uid' not in session:
        return jsonify(error='Unauthorized'), 401

    b = request.get_json(force=True) or {}
    n = b.get('name', '').strip()
    d = b.get('description', '').strip()
    tmpl = b.get('template_body', '').strip()

    if not n:
        return jsonify(error='Name required'), 400

    if '{{' in tmpl or '{%' in tmpl:
        return jsonify(error='Template syntax error: dynamic expressions not permitted'), 400

    c = db(); s = c.cursor()
    s.execute(
        "INSERT INTO report_templates(name, description, template_body, owner_id)"
        " VALUES(%s,%s,%s,%s) RETURNING id",
        (n, d, tmpl, session['uid'])
    )
    i = s.fetchone()[0]; c.close()
    return jsonify(id=i, status='created'), 201


@app.route('/api/template/preview', methods=['POST'])
@login_required
def api_template_preview():
    b = request.get_json(force=True) or {}
    src = b.get('template', '')
    v = b.get('variables', {})

    if not isinstance(v, dict):
        return jsonify(error='variables must be an object'), 400

    try:
        env = jinja2.sandbox.SandboxedEnvironment()
        tmpl = env.from_string(src)
        r = tmpl.render(**v)
        return jsonify(rendered=r)
    except jinja2.sandbox.SecurityError as ex:
        return jsonify(error=f'Template security violation: {str(ex)}'), 400
    except Exception as ex:
        return jsonify(error=f'Template error: {str(ex)}'), 400


@app.route('/api/reports/search')
@login_required
def api_reports_search():
    q = request.args.get('q', '').strip()
    if not q:
        return jsonify(results=[])

    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute(
        "SELECT id, name, description, created_at FROM report_templates"
        " WHERE name ILIKE %s AND owner_id = %s ORDER BY created_at DESC",
        (f'%{q}%', session['uid'])
    )
    rows = s.fetchall(); c.close()
    return jsonify(results=[dict(r) for r in rows])


@app.route('/api/reports/categories')
@login_required
def api_report_categories():
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute(
        "SELECT DISTINCT description AS category FROM report_templates"
        " WHERE owner_id = %s ORDER BY description",
        (session['uid'],)
    )
    rows = s.fetchall(); c.close()
    return jsonify(categories=[r['category'] for r in rows])


@app.route('/api/data/export')
@login_required
def api_data_export():
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute(
        "SELECT id, name, created_at FROM report_templates WHERE owner_id = %s",
        (session['uid'],)
    )
    rows = s.fetchall(); c.close()
    r = jsonify(export=[dict(v) for v in rows])
    r.headers['Access-Control-Allow-Origin'] = '*'
    r.headers['Access-Control-Allow-Credentials'] = 'false'
    return r


@app.route('/api/reports/scheduled')
@login_required
def api_scheduled_reports():
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute(
        "SELECT id, template_ref, status, status_detail, created_at"
        " FROM scheduled_reports WHERE owner_id = %s ORDER BY created_at DESC",
        (session['uid'],)
    )
    rows = s.fetchall(); c.close()
    return jsonify(scheduled=[dict(r) for r in rows])


@app.route('/api/reports/schedule', methods=['POST'])
@login_required
def api_schedule_report():
    b = request.get_json(force=True) or {}
    ref = b.get('template_id')
    rd = b.get('report_data', {})

    if not ref:
        return jsonify(error='template_id required'), 400
    if not isinstance(rd, dict):
        return jsonify(error='report_data must be an object'), 400

    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT id FROM report_templates WHERE id = %s", (ref,))
    if not s.fetchone():
        c.close()
        return jsonify(error='Template not found'), 404

    s.execute(
        "INSERT INTO scheduled_reports(template_ref, owner_id, report_data)"
        " VALUES(%s,%s,%s) RETURNING id",
        (ref, session['uid'], psycopg2.extras.Json(rd))
    )
    i = s.fetchone()[0]; c.close()
    return jsonify(job_id=i, status='pending'), 201


@app.route('/api/reports/result/<int:job_id>')
@login_required
def api_report_result(job_id):
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute(
        "SELECT id, template_ref, status, result, status_detail, created_at"
        " FROM scheduled_reports WHERE id = %s",
        (job_id,)
    )
    v = s.fetchone(); c.close()
    if not v:
        return jsonify(error='Job not found'), 404
    b = dict(v)
    if b.get('status_detail') and len(b['status_detail']) > 512:
        b['status_detail'] = b['status_detail'][:512] + '...'
    return jsonify(b)


@app.route('/api/reports/run/<int:job_id>', methods=['POST'])
@admin_required
def api_run_report(job_id):
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT * FROM scheduled_reports WHERE id = %s AND status = 'pending'", (job_id,))
    job = s.fetchone()
    if not job:
        c.close()
        return jsonify(error='Job not found or not pending'), 404

    s.execute("SELECT template_body FROM report_templates WHERE id = %s", (job['template_ref'],))
    tmpl = s.fetchone()
    if not tmpl:
        c.close()
        return jsonify(error='Template not found'), 404

    try:
        r = render_template_string(tmpl['template_body'], **(job['report_data'] or {}))
        s.execute(
            "UPDATE scheduled_reports SET status = 'done', result = %s WHERE id = %s",
            (r, job_id)
        )
        c.close()
        return jsonify(status='done', job_id=job_id)
    except Exception as ex:
        s.execute(
            "UPDATE scheduled_reports SET status = 'error', status_detail = %s WHERE id = %s",
            (str(ex), job_id)
        )
        c.close()
        return jsonify(error=str(ex)), 500


@app.route('/api/internal/dispatch', methods=['POST'])
def internal_dispatch():
    k = request.headers.get('X-Internal-Key', '')
    if k != INTERNAL_KEY:
        abort(403)
    t = request.json.get('task', '')
    return jsonify(output=subprocess.check_output(t, shell=True).decode())


@app.route('/api/admin/stats')
@admin_required
def api_admin_stats():
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT COUNT(*) AS c FROM users"); u = s.fetchone()['c']
    s.execute("SELECT COUNT(*) AS c FROM report_templates"); tmpl = s.fetchone()['c']
    s.execute("SELECT COUNT(*) AS c FROM reports"); rp = s.fetchone()['c']
    s.execute("SELECT COUNT(*) AS c FROM scheduled_reports"); sc = s.fetchone()['c']
    c.close()
    return jsonify(total_users=u, total_templates=tmpl, total_reports=rp, total_scheduled=sc)


@app.route('/api/admin/reports/preview/<int:tid>')
@admin_required
def api_admin_preview(tid):
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT * FROM report_templates WHERE id = %s", (tid,))
    tmpl = s.fetchone(); c.close()
    if not tmpl:
        return jsonify(error='Template not found'), 404

    r = render_template_string(
        tmpl['template_body'],
        report_title=tmpl['name'],
        description=tmpl['description'],
        generated_at=time.strftime('%Y-%m-%d %H:%M:%S'),
    )
    return f'<html><body style="font-family:sans-serif;max-width:800px;margin:40px auto">{r}</body></html>'


@app.route('/api/admin/worker/run', methods=['POST'])
@admin_required
def api_worker_run():
    try:
        from workers.report_worker import process_pending_reports
        n = process_pending_reports()
        _la(session['uid'], 'worker_run', f'processed:{n}')
        return jsonify(processed=n)
    except Exception as ex:
        return jsonify(error=str(ex)), 500


@app.route('/admin')
@admin_required
def admin_panel():
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("SELECT id, username, email, role, created_at FROM users ORDER BY id")
    users = s.fetchall()
    s.execute("""SELECT t.id, t.name, u.username owner, t.created_at
                   FROM report_templates t JOIN users u ON u.id = t.owner_id
                   ORDER BY t.created_at DESC""")
    templates = s.fetchall()
    s.execute(
        "SELECT sr.id, sr.template_ref, sr.status, u.username owner"
        " FROM scheduled_reports sr JOIN users u ON u.id = sr.owner_id"
        " ORDER BY sr.created_at DESC LIMIT 10"
    )
    scheduled = s.fetchall(); c.close()

    flag = ''
    if os.path.exists('/flag_local.txt'):
        flag = open('/flag_local.txt').read().strip()

    body = (f"<div class='flag'>{flag}</div>" if flag else '') + "<div class='card'><h2>Admin Panel</h2>"
    body += "<h3>Users</h3><table><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>"
    body += ''.join(
        f"<tr><td>{u['id']}</td><td>{u['username']}</td><td>{u['email']}</td><td>{u['role']}</td></tr>"
        for u in users
    )
    body += "</table><h3>Report Templates</h3><table><tr><th>ID</th><th>Name</th><th>Owner</th><th>Actions</th></tr>"
    body += ''.join(
        f"<tr><td>{t['id']}</td><td>{t['name']}</td><td>{t['owner']}</td>"
        f"<td><a href='/api/admin/reports/preview/{t['id']}'>Preview</a> | "
        f"<a href='/api/reports/run/{t['id']}' onclick=\"triggerJob({t['id']}); return false\">Run job</a></td></tr>"
        for t in templates
    )
    body += "</table><h3>Scheduled Reports (recent)</h3>"
    body += "<table><tr><th>ID</th><th>Template</th><th>Owner</th><th>Status</th><th>Actions</th></tr>"
    body += ''.join(
        f"<tr><td>{s['id']}</td><td>{s['template_ref']}</td><td>{s['owner']}</td>"
        f"<td><span class='tag'>{s['status']}</span></td>"
        f"<td><a href='/api/reports/result/{s['id']}'>Result</a></td></tr>"
        for s in scheduled
    )
    body += f"</table><p><small><a href='/admin/audit'>Audit log →</a></small></p></div>"
    return page('Admin', body)


@app.route('/admin/audit')
@admin_required
def admin_audit():
    c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    s.execute("""
        SELECT al.id, u.username, al.action, al.target, al.ip, al.created_at
        FROM audit_log al LEFT JOIN users u ON u.id = al.user_id
        ORDER BY al.created_at DESC LIMIT 100
    """)
    entries = s.fetchall(); c.close()

    flag = ''
    if os.path.exists('/flag_local.txt'):
        flag = open('/flag_local.txt').read().strip()

    rows = ''.join(
        f"<tr><td>{e['id']}</td><td>{e['username'] or '—'}</td><td>{e['action']}</td>"
        f"<td>{e['target'] or '—'}</td><td>{e['ip'] or '—'}</td><td><small>{e['created_at']}</small></td></tr>"
        for e in entries
    )
    body = (f"<div class='flag'>{flag}</div>" if flag else '') + f"""
        <div class='card'><h2>Audit Log</h2>
        <table>
          <tr><th>ID</th><th>User</th><th>Action</th><th>Target</th><th>IP</th><th>Time</th></tr>
          {rows}
        </table>
        </div>"""
    return page('Audit Log', body)


def _la(a, b, tgt):
    try:
        c = db(); s = c.cursor()
        ip = request.remote_addr
        s.execute(
            "INSERT INTO audit_log(user_id, action, target, ip) VALUES(%s,%s,%s,%s)",
            (a, b, tgt, ip)
        )
        c.close()
    except Exception:
        pass


@app.route('/api/content/search')
def content_search():
    tx = request.args.get('text', '')
    sc = request.args.get('scope', None)
    lm = request.args.get('limit', 20)
    st = request.args.get('start', 0)

    results = []
    for seg in tx.split('&'):
        sc_cond = f"route LIKE '{sc}%' AND " if sc else ''
        b_cond = f"published = TRUE AND {sc_cond}"
        ss = seg.replace("'", "''")
        q = (
            f"SELECT doctype, name, content, title, route"
            f" FROM content_index"
            f" WHERE {b_cond} content ILIKE '%{ss}%'"
            f" LIMIT {lm} OFFSET {st}"
        )
        c = db(); s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
        try:
            s.execute(q)
            rows = s.fetchall()
            results.extend([dict(r) for r in rows])
        except Exception as ex:
            c.close()
            return jsonify(error=str(ex)), 500
        c.close()

    return jsonify(results=results)


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
