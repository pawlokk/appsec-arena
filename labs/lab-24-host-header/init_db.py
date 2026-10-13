import sqlite3
import os
from werkzeug.security import generate_password_hash

DB_PATH = "/app/nexus.db"

def init():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()

    c.execute("""
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0
        )
    """)

    c.execute("""
        CREATE TABLE IF NOT EXISTS reset_tokens (
            token TEXT PRIMARY KEY,
            email TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            used INTEGER NOT NULL DEFAULT 0
        )
    """)

    # Seed admin user — password sourced from runtime environment only
    admin_email = "admin@nexuslab.io"
    admin_password = os.environ.get("ADMIN_PASS")
    if not admin_password:
        raise RuntimeError("ADMIN_PASS environment variable must be set before initializing the database.")

    existing = c.execute("SELECT id FROM users WHERE email = ?", (admin_email,)).fetchone()
    if not existing:
        c.execute(
            "INSERT INTO users (email, password_hash, is_admin) VALUES (?, ?, 1)",
            (admin_email, generate_password_hash(admin_password))
        )

    conn.commit()
    conn.close()
    print("[init_db] Database initialized.")

if __name__ == "__main__":
    init()
