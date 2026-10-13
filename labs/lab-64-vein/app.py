from flask import Flask, render_template_string, request, session, redirect, abort, jsonify
import psycopg2, psycopg2.extras
import os, urllib.request, urllib.error
from lxml import etree
from html import escape as h
from functools import wraps
from werkzeug.security import check_password_hash, generate_password_hash
from urllib.parse import urlparse
import defusedxml.ElementTree as defused_et

from services.import_service import enqueue_document, get_job_status
from services.status_service import list_jobs

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'vein-dev-fallback-x7k2m')
DATABASE_URL = os.environ.get('DATABASE_URL', 'dbname=vein user=vein password=veinpass host=localhost')
ADMIN_PASSWORD = os.environ.get('ADMIN_PASSWORD', 'admin123')

_REPORT_BLOCKED_HOSTS = {
    'localhost', '127.0.0.1', '::1', '0.0.0.0',
    '169.254.169.254', 'metadata.google.internal',
}


def db():
    c = psycopg2.connect(DATABASE_URL)
    c.autocommit = True
    return c


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


_BASE = """<!DOCTYPE html>
<html><head><meta charset=UTF-8>
<title>{{ title }} — Vein</title>
<style>
body{font-family:sans-serif;max-width:960px;margin:40px auto;padding:0 20px;background:#f4f4f4}
nav{background:#1a1f2e;padding:12px 20px;border-radius:4px;margin-bottom:20px}
nav a{color:#a8c4e8;margin-right:16px;text-decoration:none;font-size:.9rem}
nav .brand{color:#fff;font-weight:bold;margin-right:12px}
.card{background:#fff;padding:20px;border-radius:4px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.flag{background:#27ae60;color:#fff;padding:10px 14px;border-radius:4px;font-family:monospace;margin-bottom:14px;word-break:break-all}
input,textarea{width:100%;padding:8px;margin:4px 0 10px;box-sizing:border-box;border:1px solid #ccc;border-radius:3px}
button,input[type=submit]{background:#2c6fad;color:#fff;border:none;padding:8px 18px;border-radius:3px;cursor:pointer}
table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:6px 10px;text-align:left}
.err{color:#c0392b}.ok{color:#27ae60}small{color:#777}
pre{background:#f5f5f5;padding:12px;border-radius:4px;overflow:auto;font-size:.85rem}
</style></head><body>
<nav>
  <span class=brand>Vein</span>
  <a href="/">Home</a>
  <a href="/upload">Upload XML</a>
  <a href="/api/fetch">URL Fetcher</a>
  {% if session.admin %}<a href="/admin">Admin</a>{% endif %}
  {% if session.user %}
    <a href="/logout" style="margin-left:8px">Logout ({{ session.user }})</a>
  {% else %}
    <a href="/login">Login</a>
  {% endif %}
</nav>
{{ body | safe }}
</body></html>"""


def pg(t, o):
    return render_template_string(_BASE, title=t, body=o, session=session)


@app.route('/')
def index():
    return pg('Home', """
        <div class='card'>
          <h2>Document Processing Service</h2>
          <p>Vein provides XML document ingestion, storage, and reporting for enterprise data pipelines.</p>
          <ul>
            <li><a href='/upload'>Upload XML Document</a> — parse and store structured data</li>
            <li><a href='/documents'>View Documents</a> — browse stored records</li>
            <li><a href='/api/fetch?url='>URL Fetcher</a> — import remote XML content</li>
          </ul>
          <p><small>Admin login required for DBA console and reporting features.</small></p>
        </div>
    """)


@app.route('/login', methods=['GET', 'POST'])
def login():
    e = ''
    if request.method == 'POST':
        u = request.form.get('username', '')
        p = request.form.get('password', '')
        if u == 'admin' and p == ADMIN_PASSWORD:
            session.update(uid=1, user='admin', admin=True)
            return redirect('/admin')
        c = db(); cur = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
        cur.execute("SELECT * FROM users WHERE username = %s", (u,))
        r = cur.fetchone(); c.close()
        if r and check_password_hash(r['password_hash'], p):
            session.update(uid=r['id'], user=r['username'], admin=False)
            return redirect('/documents')
        e = 'Invalid credentials.'
    f = (f"<p class=err>{h(e)}</p>" if e else '') + """
        <form method=post>
          <label>Username</label><input name=username required>
          <label>Password</label><input name=password type=password required>
          <button>Login</button>
        </form>"""
    return pg('Login', f"<div class='card'><h2>Login</h2>{f}</div>")


@app.route('/logout')
def logout():
    session.clear()
    return redirect('/login')


@app.route('/upload', methods=['GET', 'POST'])
def upload():
    msg = e = ''
    if request.method == 'POST':
        b = request.data or request.files.get('file', b'')
        if hasattr(b, 'read'):
            b = b.read()
        x = request.content_type or ''

        if not b:
            e = 'No data received.'
        else:
            try:
                r = _pxd(b, x)
                c = db(); cur = c.cursor()
                cur.execute(
                    "INSERT INTO documents(title, content, raw_xml) VALUES(%s,%s,%s) RETURNING id",
                    (r.get('title', 'Untitled'), r.get('content', ''), b.decode('utf-8', errors='replace'))
                )
                i = cur.fetchone()[0]; c.close()
                msg = f'Document stored with ID {i}. Title: {h(r.get("title", ""))}'
            except Exception as ex:
                e = f'Parse error: {h(str(ex))}'

    f = (f"<p class=ok>{msg}</p>" if msg else '') + (f"<p class=err>{e}</p>" if e else '') + """
        <form method=post enctype=multipart/form-data>
          <label>Upload XML File</label>
          <input type=file name=file accept='.xml,text/xml,application/xml'>
          <button>Upload</button>
        </form>
        <p><small>Or POST raw XML body with Content-Type: application/xml<br>
        Partner format accepted via Content-Type: application/xml+partner</small></p>"""
    return pg('Upload XML', f"<div class='card'><h2>XML Document Upload</h2>{f}</div>")


def _pxd(b, x):
    p = etree.XMLParser(resolve_entities=False, no_network=True)

    if x == 'application/xml+partner' or b'<!DOCTYPE' in b:
        r = etree.fromstring(b)
    else:
        r = etree.fromstring(b, p)

    t = ''
    o = ''
    if r.find('title') is not None:
        t = r.find('title').text or ''
    if r.find('content') is not None:
        o = r.find('content') is not None and r.find('content').text or ''
    elif r.text:
        o = r.text

    return {'title': t, 'content': o}


@app.route('/documents')
@login_required
def documents():
    c = db(); cur = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("SELECT id, title, created_at FROM documents ORDER BY created_at DESC LIMIT 50")
    rows = cur.fetchall(); c.close()
    items = ''.join(
        f"<tr><td>{r['id']}</td><td><a href='/document/{r['id']}'>{h(r['title'])}</a></td>"
        f"<td><small>{r['created_at']}</small></td></tr>"
        for r in rows
    )
    o = f"""
        <div class='card'>
          <h2>Stored Documents</h2>
          <table><tr><th>ID</th><th>Title</th><th>Created</th></tr>
          {items}
          </table>
        </div>"""
    return pg('Documents', o)


@app.route('/document/<int:doc_id>')
@login_required
def view_document(doc_id):
    c = db(); cur = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("SELECT * FROM documents WHERE id = %s", (doc_id,))
    d = cur.fetchone(); c.close()
    if not d: abort(404)
    o = f"""
        <div class='card'>
          <h2>{h(d['title'])}</h2>
          <p>{h(d['content'] or '')}</p>
          <h3>Raw XML</h3>
          <pre>{h(d['raw_xml'] or '')}</pre>
        </div>"""
    return pg(d['title'], o)


@app.route('/api/fetch')
def api_fetch():
    u = request.args.get('url', '')
    if not u:
        return pg('URL Fetcher', """
            <div class='card'>
              <h2>URL Fetcher</h2>
              <p>Import remote XML content via URL.</p>
              <form method=get>
                <label>Remote URL</label>
                <input name=url placeholder='https://example.com/data.xml' required>
                <button>Fetch</button>
              </form>
            </div>""")

    bl = ['localhost', '127.0.0.1', '169.254', '::1']
    if any(b in u for b in bl):
        return jsonify(error='URL not allowed: internal addresses are blocked'), 403

    try:
        q = urllib.request.Request(u, headers={'User-Agent': 'Vein-Fetcher/1.0'})
        with urllib.request.urlopen(q, timeout=10) as r:
            o = r.read(65536).decode('utf-8', errors='replace')
        return pg('Fetch Result', f"""
            <div class='card'>
              <h2>Fetched Content</h2>
              <p><small>URL: {h(u)}</small></p>
              <pre>{h(o[:4096])}</pre>
            </div>""")
    except Exception as e:
        return jsonify(error=f'Fetch failed: {str(e)}'), 500


@app.route('/api/documents/validate', methods=['POST'])
def api_documents_validate():
    b = request.data
    if not b:
        return jsonify(error='Empty body'), 400
    try:
        r = defused_et.fromstring(b)
        f = {child.tag: (child.text or '') for child in r}
        return jsonify(valid=True, fields=f)
    except defused_et.ParseError as e:
        return jsonify(valid=False, error=f'XML parse error: {str(e)}'), 422
    except Exception as e:
        return jsonify(valid=False, error=str(e)), 400


_LINK_ALLOWED_HOSTS = {'docs.vein.internal', 'api.vein.internal', 'cdn.vein.internal'}

@app.route('/api/links/check', methods=['POST'])
def api_links_check():
    b = request.get_json(force=True) or {}
    u = b.get('url', '').strip()
    if not u:
        return jsonify(error='url required'), 400

    p = urlparse(u)
    n = p.hostname
    if n not in _LINK_ALLOWED_HOSTS:
        return jsonify(error='Host not allowed', allowed=sorted(_LINK_ALLOWED_HOSTS)), 400

    try:
        q = urllib.request.Request(u, headers={'User-Agent': 'Vein-LinkChecker/1.0'})
        with urllib.request.urlopen(q, timeout=5) as r:
            s = r.getcode()
        return jsonify(url=u, reachable=True, status=s)
    except Exception as e:
        return jsonify(url=u, reachable=False, error=str(e))


@app.route('/admin')
@admin_required
def admin():
    f = ''
    if os.path.exists('/flag_local.txt'):
        f = open('/flag_local.txt').read().strip()

    c = db(); cur = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("SELECT id, title, created_at FROM documents ORDER BY created_at DESC LIMIT 20")
    docs = cur.fetchall(); c.close()

    items = ''.join(
        f"<tr><td>{d['id']}</td><td>{h(d['title'])}</td><td><small>{d['created_at']}</small></td></tr>"
        for d in docs
    )
    o = (f"<div class='flag'>{f}</div>" if f else '') + f"""
        <div class='card'>
          <h2>Admin Panel</h2>
          <p><a href='/admin/query'>DBA Console</a> — run SQL reports against the database</p>
          <h3>Recent Documents</h3>
          <table><tr><th>ID</th><th>Title</th><th>Created</th></tr>
          {items}
          </table>
        </div>"""
    return pg('Admin', o)


@app.route('/admin/query', methods=['GET', 'POST'])
@admin_required
def admin_query():
    r = e = q = ''
    if request.method == 'POST':
        q = request.form.get('query', '').strip()
        bl = ['DROP TABLE', 'DELETE FROM', 'TRUNCATE TABLE', 'ALTER TABLE']
        if any(x in q.upper() for x in bl):
            e = 'Query blocked: destructive operations are not permitted in the DBA console.'
        else:
            try:
                c = db(); cur = c.cursor()
                cur.execute(q)
                if cur.description:
                    cols = [d[0] for d in cur.description]
                    rows = cur.fetchall()
                    hz = '<tr>' + ''.join(f'<th>{h(col)}</th>' for col in cols) + '</tr>'
                    br = ''.join(
                        '<tr>' + ''.join(f'<td>{h(str(v))}</td>' for v in row) + '</tr>'
                        for row in rows
                    )
                    r = f"<table>{hz}{br}</table>"
                else:
                    r = '<p class=ok>Query executed successfully.</p>'
                c.close()
            except Exception as ex:
                e = f'Query error: {h(str(ex))}'

    f = (f"<p class=err>{e}</p>" if e else '') + (r if r else '') + f"""
        <form method=post>
          <label>SQL Query</label>
          <textarea name=query rows=6 placeholder='SELECT * FROM documents LIMIT 10'>{h(q)}</textarea>
          <button>Execute</button>
        </form>
        <p><small>Read-only reporting queries only. DROP TABLE and DELETE FROM are blocked.</small></p>"""
    return pg('DBA Console', f"<div class='card'><h2>DBA Console</h2>{f}</div>")


@app.route('/admin/query/filtered', methods=['GET', 'POST'])
@admin_required
def admin_query_filtered():
    r = e = q = ''
    if request.method == 'POST':
        q = request.form.get('query', '').strip()
        bl = [
            'copy', 'pg_read_file', 'pg_ls_dir', 'lo_', 'dblink',
            'create function', 'create extension', 'pg_exec',
        ]
        if any(b in q.lower() for b in bl):
            e = 'Query contains blocked operation. Blocked keywords: ' + ', '.join(bl)
        else:
            try:
                c = db(); cur = c.cursor()
                cur.execute(q)
                if cur.description:
                    cols = [d[0] for d in cur.description]
                    rows = cur.fetchall()
                    hz = '<tr>' + ''.join(f'<th>{h(col)}</th>' for col in cols) + '</tr>'
                    br = ''.join(
                        '<tr>' + ''.join(f'<td>{h(str(v))}</td>' for v in row) + '</tr>'
                        for row in rows
                    )
                    r = f"<table>{hz}{br}</table>"
                else:
                    r = '<p class=ok>Query executed successfully.</p>'
                c.close()
            except Exception as ex:
                e = f'Query error: {h(str(ex))}'

    f = (f"<p class=err>{e}</p>" if e else '') + (r if r else '') + f"""
        <form method=post>
          <label>SQL Query (hardened console)</label>
          <textarea name=query rows=6 placeholder='SELECT * FROM documents LIMIT 10'>{h(q)}</textarea>
          <button>Execute</button>
        </form>
        <p><small>Comprehensive blocklist active. For legacy console: <a href='/admin/query'>/admin/query</a></small></p>"""
    return pg('DBA Console (Filtered)', f"<div class='card'><h2>DBA Console (Hardened)</h2>{f}</div>")


@app.route('/api/documents/import', methods=['POST'])
def api_documents_import():
    b = request.data
    if not b:
        return jsonify(error='Empty body'), 400

    x = request.content_type or ''
    if 'xml' not in x.lower():
        return jsonify(error='Content-Type must be application/xml'), 415

    n = request.headers.get('X-Filename', 'upload.xml')
    u = session.get('uid')
    i = enqueue_document(b.decode('utf-8', errors='replace'), n, u)
    return jsonify(job_id=i, status='pending'), 202


@app.route('/api/import/status/<int:job_id>')
def api_import_status(job_id):
    r = get_job_status(job_id)
    if not r:
        return jsonify(error='Job not found'), 404
    return jsonify(r)


@app.route('/api/import/jobs')
@admin_required
def api_import_jobs():
    r = list_jobs(50)
    return jsonify(jobs=r)


@app.route('/api/reports/queue', methods=['POST'])
@login_required
def api_report_queue():
    b = request.get_json(force=True) or {}
    u = b.get('url', '').strip()
    if not u:
        return jsonify(error='url required'), 400

    p = urlparse(u)
    n = p.hostname or ''
    if n in _REPORT_BLOCKED_HOSTS:
        return jsonify(error='URL not allowed: internal addresses are blocked'), 403

    c = db(); cur = c.cursor()
    cur.execute(
        "INSERT INTO report_queue(report_url, requested_by) VALUES(%s,%s) RETURNING id",
        (u, session['uid'])
    )
    i = cur.fetchone()[0]; c.close()
    return jsonify(queue_id=i, status='pending'), 202


@app.route('/api/reports/result/<int:queue_id>')
@login_required
def api_report_result(queue_id):
    c = db(); cur = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("""
        SELECT rq.id, rq.report_url, rq.status, rq.status_detail,
               rr.status_code, rr.content_length, rr.content_snippet
        FROM report_queue rq
        LEFT JOIN report_results rr ON rr.queue_id = rq.id
        WHERE rq.id = %s
    """, (queue_id,))
    r = cur.fetchone(); c.close()
    if not r:
        return jsonify(error='Not found'), 404
    return jsonify(dict(r))


@app.route('/internal/status')
def internal_status():
    if request.remote_addr not in ('127.0.0.1', '::1'):
        abort(403)

    from config.internal_config import INTERNAL_API_TOKEN
    f = ''
    if os.path.exists('/flag_local.txt'):
        f = open('/flag_local.txt').read().strip()

    return jsonify(token=INTERNAL_API_TOKEN, flag=f)


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
