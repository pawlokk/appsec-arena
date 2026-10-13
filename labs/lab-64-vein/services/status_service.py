import psycopg2
import os

DATABASE_URL = os.environ.get(
    'DATABASE_URL',
    'dbname=vein user=vein password=veinpass host=localhost'
)


def update_job(i, s, result=None, status_detail=None):
    c = psycopg2.connect(DATABASE_URL)
    c.autocommit = True
    cur = c.cursor()
    cur.execute(
        "UPDATE import_jobs SET status=%s, result=%s, status_detail=%s WHERE id=%s",
        (s, result, status_detail, i)
    )
    c.close()


def list_jobs(n=20):
    c = psycopg2.connect(DATABASE_URL)
    c.autocommit = True
    cur = c.cursor()
    cur.execute(
        "SELECT id, filename, status, status_detail, created_at FROM import_jobs"
        " ORDER BY created_at DESC LIMIT %s",
        (n,)
    )
    rows = cur.fetchall()
    c.close()
    return [
        {'id': r[0], 'filename': r[1], 'status': r[2], 'status_detail': r[3], 'created_at': str(r[4])}
        for r in rows
    ]
