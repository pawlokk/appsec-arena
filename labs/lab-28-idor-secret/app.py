import os
import sqlite3
import subprocess
from flask import Flask, request, jsonify, session

app = Flask(__name__)
app.secret_key = os.environ["FLASK_SECRET_KEY"]

DB_PATH = "/tmp/lab28.db"


def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def init_db():
    conn = get_db()
    c = conn.cursor()
    c.execute("""
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            debug_key TEXT
        )
    """)
    admin_password = os.environ.get("ADMIN_PASSWORD", os.urandom(16).hex())
    flask_secret = os.environ["FLASK_SECRET_KEY"]
    c.execute("""
        INSERT OR IGNORE INTO users (id, username, password, role, debug_key)
        VALUES (1, 'admin', ?, 'admin', ?)
    """, (admin_password, flask_secret))
    conn.commit()
    conn.close()


@app.route("/")
def index():
    return jsonify({
        "app": "ProfileHub v1.0",
        "endpoints": [
            "POST /register",
            "POST /login",
            "GET /api/users/<id>",
            "POST /admin/run"
        ]
    })


@app.route("/register", methods=["POST"])
def register():
    data = request.get_json(force=True)
    username = data.get("username", "").strip()
    password = data.get("password", "").strip()
    if not username or not password:
        return jsonify({"error": "username and password required"}), 400
    conn = get_db()
    try:
        conn.execute(
            "INSERT INTO users (username, password, role) VALUES (?, ?, 'user')",
            (username, password)
        )
        conn.commit()
    except sqlite3.IntegrityError:
        conn.close()
        return jsonify({"error": "username taken"}), 409
    conn.close()
    return jsonify({"message": "registered", "username": username}), 201


@app.route("/login", methods=["POST"])
def login():
    data = request.get_json(force=True)
    username = data.get("username", "").strip()
    password = data.get("password", "").strip()
    conn = get_db()
    row = conn.execute(
        "SELECT * FROM users WHERE username=? AND password=?", (username, password)
    ).fetchone()
    conn.close()
    if not row:
        return jsonify({"error": "invalid credentials"}), 401
    session["user_id"] = row["id"]
    session["username"] = row["username"]
    session["role"] = row["role"]
    return jsonify({"message": "logged in", "user_id": row["id"], "role": row["role"]})


@app.route("/logout", methods=["POST"])
def logout():
    session.clear()
    return jsonify({"message": "logged out"})


@app.route("/api/users/<int:user_id>", methods=["GET"])
def get_user(user_id):
    if "user_id" not in session:
        return jsonify({"error": "authentication required"}), 401

    conn = get_db()
    row = conn.execute("SELECT * FROM users WHERE id=?", (user_id,)).fetchone()
    conn.close()
    if not row:
        return jsonify({"error": "user not found"}), 404

    return jsonify({
        "id": row["id"],
        "username": row["username"],
        "role": row["role"],
        "debug_key": row["debug_key"]
    })


@app.route("/admin/run", methods=["POST"])
def admin_run():
    if "user_id" not in session:
        return jsonify({"error": "authentication required"}), 401

    provided_key = request.headers.get("X-Debug-Key", "")
    flask_secret = os.environ["FLASK_SECRET_KEY"]
    if provided_key != flask_secret:
        return jsonify({"error": "forbidden"}), 403

    data = request.get_json(force=True)
    cmd = data.get("cmd", "")
    if not cmd:
        return jsonify({"error": "cmd required"}), 400

    try:
        result = subprocess.run(
            cmd, shell=True, capture_output=True, text=True, timeout=10
        )
        output = result.stdout + result.stderr
    except subprocess.TimeoutExpired:
        output = "timeout"

    return jsonify({
        "cmd": cmd,
        "output": output
    })


init_db()

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001, debug=False)
