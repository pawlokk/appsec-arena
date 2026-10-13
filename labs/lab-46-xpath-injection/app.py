import os
import subprocess
from flask import Flask, request, session, redirect, url_for, render_template_string
from lxml import etree

app = Flask(__name__)
app.secret_key = 'folio-secret-key-not-secure'

FLAG_LOCAL = os.environ.get('FLAG_LOCAL', 'ARENA{LOCAL_placeholder}')
FLAG_PROOF = os.environ.get('FLAG_PROOF', 'ARENA{PROOF_placeholder}')

XML_PATH = os.path.join(os.path.dirname(__file__), 'users.xml')

LOGIN_PAGE = """
<!DOCTYPE html>
<html>
<head>
  <title>Folio - Document Portal</title>
  <style>
    body { font-family: Georgia, serif; background: #1a1a2e; color: #e0e0e0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
    .container { background: #16213e; padding: 40px; border-radius: 8px; width: 360px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
    h2 { text-align: center; color: #e94560; margin-bottom: 30px; }
    input { width: 100%; padding: 10px; margin: 8px 0 16px 0; box-sizing: border-box; background: #0f3460; border: 1px solid #e94560; color: #e0e0e0; border-radius: 4px; }
    button { width: 100%; padding: 12px; background: #e94560; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
    button:hover { background: #c73652; }
    .error { color: #e94560; text-align: center; margin-bottom: 10px; }
    .subtitle { text-align: center; color: #aaa; font-size: 13px; margin-bottom: 20px; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Folio</h2>
    <p class="subtitle">Document Management Portal</p>
    {% if error %}<p class="error">{{ error }}</p>{% endif %}
    <form method="POST" action="/login">
      <label>Username</label>
      <input type="text" name="username" autocomplete="off" />
      <label>Password</label>
      <input type="password" name="password" />
      <button type="submit">Sign In</button>
    </form>
  </div>
</body>
</html>
"""

DASHBOARD_PAGE = """
<!DOCTYPE html>
<html>
<head>
  <title>Folio - Dashboard</title>
  <style>
    body { font-family: Georgia, serif; background: #1a1a2e; color: #e0e0e0; margin: 0; padding: 0; }
    .header { background: #16213e; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e94560; }
    .header h1 { color: #e94560; margin: 0; font-size: 24px; }
    .header a { color: #aaa; text-decoration: none; }
    .content { padding: 40px; max-width: 900px; margin: 0 auto; }
    .card { background: #16213e; border-radius: 8px; padding: 24px; margin-bottom: 20px; border: 1px solid #0f3460; }
    .card h3 { color: #e94560; margin-top: 0; }
    .flag { font-family: monospace; background: #0f3460; padding: 10px 16px; border-radius: 4px; color: #4ecca3; font-size: 15px; display: inline-block; }
    .role-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    .admin-badge { background: #e94560; color: white; }
    .user-badge { background: #0f3460; color: #aaa; }
    .doc-list { list-style: none; padding: 0; }
    .doc-list li { padding: 10px; border-bottom: 1px solid #0f3460; }
    .doc-list li:last-child { border-bottom: none; }
  </style>
</head>
<body>
  <div class="header">
    <h1>Folio</h1>
    <div>
      Logged in as <strong>{{ username }}</strong>
      <span class="role-badge {% if role == 'admin' %}admin-badge{% else %}user-badge{% endif %}">{{ role }}</span>
      &nbsp;&nbsp;<a href="/logout">Logout</a>
    </div>
  </div>
  <div class="content">
    {% if role == 'admin' %}
    <div class="card">
      <h3>Administrator Access Confirmed</h3>
      <p>Full administrative privileges are active for this session.</p>
      <span class="flag">{{ flag_local }}</span>
    </div>
    {% endif %}
    <div class="card">
      <h3>Document Library</h3>
      <ul class="doc-list">
        <li>Q1 Financial Report 2026.pdf</li>
        <li>Employee Handbook v3.2.docx</li>
        <li>Security Policy Draft.docx</li>
        <li>Project Roadmap H2.pptx</li>
        <li>Audit Log Archive.zip</li>
      </ul>
    </div>
    {% if role == 'admin' %}
    <div class="card">
      <h3>Admin Panel - Command Execution</h3>
      <p>Execute system commands (admin only):</p>
      <form method="POST" action="/admin/exec">
        <input type="text" name="cmd" placeholder="Enter command..." style="width:70%; padding:8px; background:#0f3460; border:1px solid #e94560; color:#e0e0e0; border-radius:4px;" />
        <button type="submit" style="padding:8px 16px; background:#e94560; color:white; border:none; border-radius:4px; cursor:pointer; margin-left:8px;">Run</button>
      </form>
      {% if cmd_output is not none %}
      <pre style="background:#0a0a1a; padding:12px; border-radius:4px; margin-top:12px; color:#4ecca3; overflow-x:auto;">{{ cmd_output }}</pre>
      {% endif %}
    </div>
    {% endif %}
  </div>
</body>
</html>
"""


@app.route('/')
def index():
    if 'username' in session:
        return redirect(url_for('dashboard'))
    return redirect(url_for('login_page'))


@app.route('/login', methods=['GET', 'POST'])
def login_page():
    if request.method == 'GET':
        return render_template_string(LOGIN_PAGE, error=None)

    username = request.form.get('username', '')
    password = request.form.get('password', '')

    try:
        tree = etree.parse(XML_PATH)
        query = "//user[username/text()='" + username + "' and password/text()='" + password + "']"
        results = tree.xpath(query)
    except etree.XPathEvalError:
        return render_template_string(LOGIN_PAGE, error='Invalid query.')
    except Exception:
        return render_template_string(LOGIN_PAGE, error='Authentication error.')

    if results:
        user_node = results[0]
        uname = user_node.findtext('username') or username
        role = user_node.findtext('role') or 'user'
        session['username'] = uname
        session['role'] = role
        return redirect(url_for('dashboard'))
    else:
        return render_template_string(LOGIN_PAGE, error='Invalid credentials.')


@app.route('/dashboard')
def dashboard():
    if 'username' not in session:
        return redirect(url_for('login_page'))
    return render_template_string(
        DASHBOARD_PAGE,
        username=session['username'],
        role=session['role'],
        flag_local=FLAG_LOCAL,
        cmd_output=None
    )


@app.route('/admin/exec', methods=['POST'])
def admin_exec():
    if 'username' not in session:
        return redirect(url_for('login_page'))
    if session.get('role') != 'admin':
        return 'Access denied: admin only.', 403

    cmd = request.form.get('cmd', '')
    try:
        result = subprocess.run(
            cmd, shell=True, capture_output=True, text=True, timeout=10
        )
        output = result.stdout + result.stderr
    except subprocess.TimeoutExpired:
        output = 'Command timed out.'
    except Exception as e:
        output = str(e)

    return render_template_string(
        DASHBOARD_PAGE,
        username=session['username'],
        role=session['role'],
        flag_local=FLAG_LOCAL,
        cmd_output=output
    )


@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('login_page'))


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
