import os
import psycopg2
from flask import Flask, request, jsonify, render_template_string, session, redirect, url_for

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'arena-session-key')

DB_CONFIG = {
    'host': '/var/run/postgresql',
    'dbname': 'analytics',
    'user': 'arena'
}

def get_db():
    return psycopg2.connect(**DB_CONFIG)

LOGIN_PAGE = """
<!DOCTYPE html>
<html>
<head><title>Analytics Dashboard - Login</title>
<style>
body { font-family: Arial, sans-serif; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
.box { background: #16213e; padding: 40px; border-radius: 8px; width: 320px; }
h2 { text-align: center; color: #e94560; }
input { width: 100%; padding: 10px; margin: 8px 0; background: #0f3460; border: 1px solid #e94560; color: #eee; border-radius: 4px; box-sizing: border-box; }
button { width: 100%; padding: 10px; background: #e94560; border: none; color: #fff; border-radius: 4px; cursor: pointer; font-size: 16px; }
.error { color: #ff6b6b; margin-top: 10px; }
</style>
</head>
<body>
<div class="box">
  <h2>Analytics Portal</h2>
  <form method="POST" action="/login">
    <input name="username" placeholder="Username" /><br/>
    <input name="password" type="password" placeholder="Password" /><br/>
    <button type="submit">Login</button>
  </form>
  {% if error %}<p class="error">{{ error }}</p>{% endif %}
</div>
</body>
</html>
"""

DASHBOARD_PAGE = """
<!DOCTYPE html>
<html>
<head><title>Analytics Dashboard</title>
<style>
body { font-family: Arial, sans-serif; background: #1a1a2e; color: #eee; margin: 0; padding: 20px; }
h1 { color: #e94560; }
.filter-section { margin: 20px 0; }
input[type=text] { padding: 8px; background: #0f3460; border: 1px solid #444; color: #eee; border-radius: 4px; width: 300px; }
button { padding: 8px 20px; background: #e94560; border: none; color: #fff; border-radius: 4px; cursor: pointer; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { border: 1px solid #444; padding: 8px 12px; text-align: left; }
th { background: #16213e; color: #e94560; }
tr:nth-child(even) { background: #16213e; }
pre { background: #0f3460; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 13px; }
</style>
</head>
<body>
<h1>Analytics Dashboard</h1>

<div class="filter-section">
  <h3>Report Filter</h3>
  <form id="reportForm">
    <input type="text" id="filterInput" placeholder="Category filter (e.g. web, api, mobile)" value="{{ filter_val }}" />
    <button type="button" onclick="fetchReport()">Run Report</button>
  </form>
</div>

<div id="results"></div>

<script>
function fetchReport() {
  const filter = document.getElementById('filterInput').value;
  fetch('/api/report', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({filter: filter})
  })
  .then(r => r.json())
  .then(data => {
    let html = '';
    if (data.error) {
      html = '<pre style="color:#ff6b6b">' + JSON.stringify(data, null, 2) + '</pre>';
    } else if (data.rows && data.rows.length > 0) {
      html = '<table><tr>';
      data.columns.forEach(c => html += '<th>' + c + '</th>');
      html += '</tr>';
      data.rows.forEach(row => {
        html += '<tr>';
        row.forEach(cell => html += '<td>' + (cell !== null ? cell : '<em>NULL</em>') + '</td>');
        html += '</tr>';
      });
      html += '</table>';
    } else {
      html = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    }
    document.getElementById('results').innerHTML = html;
  });
}
window.onload = function() {
  if ('{{ filter_val }}') fetchReport();
}
</script>
</body>
</html>
"""

@app.route('/')
def index():
    if 'user' not in session:
        return redirect(url_for('login'))
    return redirect(url_for('dashboard'))

@app.route('/login', methods=['GET', 'POST'])
def login():
    error = None
    if request.method == 'POST':
        username = request.form.get('username', '')
        password = request.form.get('password', '')
        try:
            conn = get_db()
            cur = conn.cursor()
            cur.execute(f"SELECT username FROM users WHERE username='{username}' AND password='{password}'")
            row = cur.fetchone()
            conn.close()
            if row:
                session['user'] = row[0]
                return redirect(url_for('dashboard'))
        except Exception:
            pass
        error = 'Invalid credentials'
    return render_template_string(LOGIN_PAGE, error=error)

@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('login'))

@app.route('/dashboard')
def dashboard():
    if 'user' not in session:
        return redirect(url_for('login'))
    return render_template_string(DASHBOARD_PAGE, filter_val='web')

@app.route('/api/report', methods=['POST'])
def api_report():
    if 'user' not in session:
        return jsonify({'error': 'Unauthorized'}), 401

    data = request.get_json(silent=True) or {}
    filter_val = data.get('filter', 'web')

    # execute query
    query = f"SELECT * FROM analytics WHERE category = '{filter_val}' ORDER BY date DESC"

    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute(query)
        rows = cur.fetchall()
        columns = [desc[0] for desc in cur.description] if cur.description else []
        conn.commit()
        cur.close()
        conn.close()
        return jsonify({'columns': columns, 'rows': [list(r) for r in rows]})
    except Exception as e:
        try:
            conn.rollback()
            conn.close()
        except Exception:
            pass
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
