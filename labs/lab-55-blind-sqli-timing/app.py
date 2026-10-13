import os
import secrets
import psycopg2
from flask import Flask, request, jsonify, session, render_template_string

app = Flask(__name__)
app.secret_key = secrets.token_hex(32)

DB_HOST = os.environ.get("DB_HOST", "localhost")
DB_NAME = os.environ.get("DB_NAME", "arena_db")
DB_USER = os.environ.get("DB_USER", "arena_user")
DB_PASS = os.environ.get("DB_PASS", "arena_pass")

FLAG_LOCAL = os.environ.get("FLAG_LOCAL", "ARENA{LOCAL_placeholder}")
FLAG_PROOF = os.environ.get("FLAG_PROOF", "ARENA{PROOF_placeholder}")


def get_db():
    return psycopg2.connect(
        host=DB_HOST, dbname=DB_NAME, user=DB_USER, password=DB_PASS
    )


INDEX_HTML = """<!DOCTYPE html>
<html>
<head><title>Ripple Search</title></head>
<body>
<h1>Ripple Item Search</h1>
<form action="/search" method="get">
  <input name="q" placeholder="Search items..." />
  <button type="submit">Search</button>
</form>
</body>
</html>"""


@app.route("/")
def index():
    return render_template_string(INDEX_HTML)


@app.route("/search")
def search():
    q = request.args.get("q", "")
    try:
        conn = get_db()
        cur = conn.cursor()
        query = f"SELECT * FROM items WHERE name LIKE '%' || '{q}' || '%'"
        cur.execute(query)
        cur.fetchall()
        cur.close()
        conn.close()
    except Exception:
        pass
    return jsonify({"results": []})


@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "GET":
        return render_template_string("""<!DOCTYPE html>
<html><head><title>Login</title></head>
<body>
<h1>Login</h1>
<form method="post">
  <input name="username" placeholder="Username" /><br/>
  <input name="password" type="password" placeholder="Password" /><br/>
  <button type="submit">Login</button>
</form>
</body></html>""")

    data = request.get_json(silent=True) or {}
    username = data.get("username") or request.form.get("username", "")
    password = data.get("password") or request.form.get("password", "")

    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute(
            "SELECT id, username FROM users WHERE username=%s AND password=%s",
            (username, password),
        )
        row = cur.fetchone()
        cur.close()
        conn.close()
    except Exception:
        return jsonify({"error": "Internal server error"}), 500

    if row:
        session["user_id"] = row[0]
        session["username"] = row[1]
        session["is_admin"] = username == "admin"
        return jsonify({"status": "ok"})
    return jsonify({"status": "fail", "message": "Invalid credentials"}), 401


@app.route("/dashboard")
def dashboard():
    if not session.get("user_id"):
        return jsonify({"error": "Unauthorized"}), 403

    if session.get("is_admin"):
        return render_template_string("""<!DOCTYPE html>
<html><head><title>Admin Dashboard</title></head>
<body>
<h1>Admin Dashboard</h1>
<p>Welcome, {{ username }}.</p>
<p>Flag: {{ flag }}</p>
</body></html>""", username=session.get("username"), flag=FLAG_LOCAL)

    return render_template_string("""<!DOCTYPE html>
<html><head><title>Dashboard</title></head>
<body>
<h1>Dashboard</h1>
<p>Welcome, {{ username }}.</p>
</body></html>""", username=session.get("username"))


@app.route("/admin/report", methods=["POST"])
def admin_report():
    if not session.get("is_admin"):
        return jsonify({"error": "Unauthorized"}), 403

    data = request.get_json(silent=True) or {}
    filter_val = data.get("filter", "")

    try:
        conn = get_db()
        cur = conn.cursor()
        query = f"SELECT name, description FROM items WHERE category='{filter_val}'"
        cur.execute(query)
        rows = cur.fetchall()
        result_text = str(rows)
        cur.close()

        cur2 = conn.cursor()
        cur2.execute("DELETE FROM report_log")
        cur2.execute("INSERT INTO report_log (result) VALUES (%s)", (result_text,))
        conn.commit()
        cur2.close()
        conn.close()
    except Exception:
        try:
            conn2 = get_db()
            cur3 = conn2.cursor()
            cur3.execute("DELETE FROM report_log")
            try:
                cur3.execute("SELECT o FROM out LIMIT 1")
                out_row = cur3.fetchone()
                if out_row:
                    cur3.execute(
                        "INSERT INTO report_log (result) VALUES (%s)", (out_row[0],)
                    )
                    conn2.commit()
            except Exception:
                pass
            cur3.close()
            conn2.close()
        except Exception:
            pass
        return jsonify({"status": "executed"}), 200

    return jsonify({"status": "ok", "rows": len(rows)})


@app.route("/admin/last-report", methods=["GET"])
def last_report():
    if not session.get("is_admin"):
        return jsonify({"error": "Unauthorized"}), 403

    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute("SELECT result FROM report_log ORDER BY id DESC LIMIT 1")
        row = cur.fetchone()
        cur.close()
        conn.close()
        if row:
            return jsonify({"result": row[0]})
        return jsonify({"result": None})
    except Exception:
        return jsonify({"error": "Internal server error"}), 500


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
