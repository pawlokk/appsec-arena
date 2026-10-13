import os
from flask import Flask, request, jsonify, session, render_template_string, redirect

app = Flask(__name__)
app.secret_key = os.urandom(24)

FLAG_LOCAL = os.environ.get('FLAG_LOCAL', 'ARENA{LOCAL_placeholder}')
FLAG_PROOF = os.environ.get('FLAG_PROOF', 'ARENA{PROOF_placeholder}')

USERS = {
    'user': 'password'
}

SAVED_TEMPLATES = [
    {
        'id': 1,
        'name': 'Welcome Email',
        'template': 'Hello {{ user.name }}, welcome to {{ company.name }}!'
    },
    {
        'id': 2,
        'name': 'Password Reset',
        'template': 'Hi {{ user.name }}, click here to reset your password.'
    }
]

BLOCKED_PATTERNS = ['__', 'class', 'mro', 'subclasses', 'builtins', 'import', 'eval', 'exec', 'os', 'sys', 'open']

LOGIN_PAGE = '''<!DOCTYPE html>
<html>
<head><title>MailCraft - Email Template Engine</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
.login-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 320px; }
h2 { margin-top: 0; color: #333; }
input { width: 100%; padding: 10px; margin: 8px 0; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
button { width: 100%; padding: 10px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
button:hover { background: #45a049; }
.error { color: red; font-size: 14px; }
</style>
</head>
<body>
<div class="login-box">
  <h2>MailCraft Login</h2>
  {% if error %}<p class="error">{{ error }}</p>{% endif %}
  <form method="POST" action="/login">
    <input type="text" name="username" placeholder="Username" required />
    <input type="password" name="password" placeholder="Password" required />
    <button type="submit">Login</button>
  </form>
</div>
</body>
</html>'''

DASHBOARD_PAGE = '''<!DOCTYPE html>
<html>
<head><title>MailCraft - Dashboard</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; }
.header { background: #333; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
.container { max-width: 900px; margin: 30px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h2 { color: #333; margin-top: 0; }
textarea { width: 100%; height: 150px; padding: 10px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; }
button { padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
button:hover { background: #45a049; }
.result { margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; }
.error { color: red; }
a { color: #4CAF50; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="header">
  <span>MailCraft Email Template Engine</span>
  <span>Logged in as {{ username }} | <a href="/logout" style="color:#aaa;">Logout</a></span>
</div>
<div class="container">
  <h2>Template Preview</h2>
  <p>Preview your email templates before sending.</p>
  <form id="previewForm">
    <textarea id="templateInput" placeholder="Enter your Jinja2 template here...">Hello {{ user.name }}, welcome to {{ company.name }}!</textarea>
    <br><br>
    <button type="button" onclick="previewTemplate()">Preview Template</button>
  </form>
  <div id="result" class="result" style="display:none;"></div>
  <hr>
  <p><a href="/templates">View Saved Templates</a></p>
</div>
<script>
async function previewTemplate() {
  const template = document.getElementById('templateInput').value;
  const result = document.getElementById('result');
  result.style.display = 'block';
  try {
    const resp = await fetch('/preview', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({template: template})
    });
    const data = await resp.json();
    if (resp.ok) {
      result.innerHTML = '<strong>Rendered output:</strong><br>' + data.output;
      result.className = 'result';
    } else {
      result.innerHTML = '<span class="error">Error: ' + data.error + '</span>';
      result.className = 'result error';
    }
  } catch(e) {
    result.innerHTML = '<span class="error">Request failed: ' + e.message + '</span>';
  }
}
</script>
</body>
</html>'''

TEMPLATES_PAGE = '''<!DOCTYPE html>
<html>
<head><title>MailCraft - Saved Templates</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; }
.header { background: #333; color: white; padding: 15px 30px; }
.container { max-width: 900px; margin: 30px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h2 { color: #333; margin-top: 0; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #f2f2f2; }
a { color: #4CAF50; }
</style>
</head>
<body>
<div class="header">MailCraft - Saved Templates</div>
<div class="container">
  <h2>Saved Templates</h2>
  <table>
    <tr><th>ID</th><th>Name</th><th>Template</th></tr>
    {% for t in templates %}
    <tr>
      <td>{{ t.id }}</td>
      <td>{{ t.name }}</td>
      <td><code>{{ t.template }}</code></td>
    </tr>
    {% endfor %}
  </table>
  <br><a href="/">Back to Dashboard</a>
</div>
</body>
</html>'''


@app.route('/', methods=['GET'])
def index():
    if 'username' not in session:
        return render_template_string(LOGIN_PAGE, error=None)
    return render_template_string(DASHBOARD_PAGE, username=session['username'])


@app.route('/login', methods=['POST'])
def login():
    username = request.form.get('username', '')
    password = request.form.get('password', '')
    if username in USERS and USERS[username] == password:
        session['username'] = username
        return redirect('/')
    return render_template_string(LOGIN_PAGE, error='Invalid credentials')


@app.route('/logout')
def logout():
    session.clear()
    return redirect('/')


@app.route('/templates', methods=['GET'])
def list_templates():
    if 'username' not in session:
        return jsonify({'error': 'Unauthorized'}), 401
    return render_template_string(TEMPLATES_PAGE, templates=SAVED_TEMPLATES)


@app.route('/preview', methods=['POST'])
def preview():
    if 'username' not in session:
        return jsonify({'error': 'Unauthorized'}), 401

    data = request.get_json()
    if not data or 'template' not in data:
        return jsonify({'error': 'Missing template field'}), 400

    template_str = data['template']

    for pattern in BLOCKED_PATTERNS:
        if pattern in template_str:
            return jsonify({'error': f'Blocked pattern detected: {pattern}'}), 400

    class Obj:
        pass

    user = Obj()
    user.name = 'John Doe'
    user.email = 'john@example.com'

    company = Obj()
    company.name = 'Acme Corp'

    try:
        rendered = render_template_string(template_str, user=user, company=company)
        return jsonify({'output': rendered})
    except Exception as e:
        return jsonify({'error': str(e)}), 500


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
