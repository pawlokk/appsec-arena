import psycopg2
import os

DATABASE_URL = os.environ.get(
    'DATABASE_URL',
    'dbname=vein user=vein password=veinpass host=localhost'
)


def enqueue_document(raw_xml, filename, submitted_by_uid):
    conn = psycopg2.connect(DATABASE_URL)
    conn.autocommit = True
    cur = conn.cursor()
    cur.execute(
        "INSERT INTO import_jobs(raw_xml, filename, submitted_by, status)"
        " VALUES(%s,%s,%s,'pending') RETURNING id",
        (raw_xml, filename or 'upload', submitted_by_uid)
    )
    job_id = cur.fetchone()[0]
    conn.close()
    return job_id


def get_job_status(job_id):
    conn = psycopg2.connect(DATABASE_URL)
    conn.autocommit = True
    cur = conn.cursor()
    cur.execute(
        "SELECT id, status, status_detail, result, created_at FROM import_jobs WHERE id = %s",
        (job_id,)
    )
    row = cur.fetchone()
    conn.close()
    if not row:
        return None
    return {
        'id': row[0],
        'status': row[1],
        'status_detail': row[2],
        'result': row[3],
        'created_at': str(row[4]),
    }
