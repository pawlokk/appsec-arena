#!/usr/bin/env python3
import os
import sys
import time

sys.path.insert(0, '/app')

import psycopg2
import psycopg2.extras

DATABASE_URL = os.environ.get(
    'DATABASE_URL',
    'dbname=vein user=vein password=veinpass host=localhost'
)


def gd():
    c = psycopg2.connect(DATABASE_URL)
    c.autocommit = True
    return c


def _ef(r):
    t = ''
    o = ''
    if r.find('title') is not None:
        t = r.find('title').text or ''
    if r.find('content') is not None:
        o = r.find('content').text or ''
    elif r.text:
        o = r.text
    return t, o


def _pj(i, b):
    from lxml import etree
    r = etree.fromstring(b if isinstance(b, bytes) else b.encode())
    return _ef(r)


def pp():
    c = gd()
    cur = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("""
        SELECT id, raw_xml, filename
        FROM import_jobs
        WHERE status = 'pending'
        ORDER BY created_at ASC
        LIMIT 5
    """)
    jobs = cur.fetchall()

    for j in jobs:
        cur.execute("UPDATE import_jobs SET status='processing' WHERE id=%s", (j['id'],))
        try:
            t, o = _pj(j['id'], j['raw_xml'])
            cur.execute(
                "INSERT INTO documents(title, content, raw_xml) VALUES(%s,%s,%s) RETURNING id",
                (t, o, j['raw_xml'])
            )
            i = cur.fetchone()[0]
            cur.execute(
                "UPDATE import_jobs SET status='done', result=%s WHERE id=%s",
                (f'document_id:{i}', j['id'])
            )
        except Exception as e:
            nr = (j.get('retry_count') or 0) + 1
            ns = 'pending' if nr < 3 else 'error'
            cur.execute(
                "UPDATE import_jobs SET status=%s, retry_count=%s, status_detail=%s WHERE id=%s",
                (ns, nr, str(e), j['id'])
            )

    c.close()
    return len(jobs)


if __name__ == '__main__':
    print('[import-worker] starting', flush=True)
    while True:
        try:
            n = pp()
            if n:
                print(f'[import-worker] processed {n} job(s)', flush=True)
        except Exception as e:
            print(f'[import-worker] error: {e}', flush=True)
        time.sleep(10)
