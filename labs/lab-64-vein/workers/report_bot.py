#!/usr/bin/env python3
import os
import sys
import time

sys.path.insert(0, '/app')

import psycopg2
import psycopg2.extras
import requests
import re

DATABASE_URL = os.environ.get(
    'DATABASE_URL',
    'dbname=vein user=vein password=veinpass host=localhost'
)

BOT_TOKEN = os.environ.get('BOT_SESSION', '')


def gd():
    c = psycopg2.connect(DATABASE_URL)
    c.autocommit = True
    return c


def _ss(o):
    return re.sub(r'<script[^>]*>.*?</script>', '', o, flags=re.DOTALL | re.IGNORECASE)


def pq():
    c = gd()
    cur = c.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute("""
        SELECT id, report_url, requested_by
        FROM report_queue
        WHERE status = 'pending'
        ORDER BY created_at ASC
        LIMIT 3
    """)
    jobs = cur.fetchall()

    for j in jobs:
        cur.execute("UPDATE report_queue SET status='processing' WHERE id=%s", (j['id'],))
        try:
            r = requests.get(
                j['report_url'],
                cookies={'session': BOT_TOKEN},
                timeout=8,
                allow_redirects=True,
            )
            o = _ss(r.text)
            cur.execute(
                "INSERT INTO report_results(queue_id, status_code, content_length, content_snippet)"
                " VALUES(%s,%s,%s,%s)",
                (j['id'], r.status_code, len(o), o[:2048])
            )
            cur.execute("UPDATE report_queue SET status='done' WHERE id=%s", (j['id'],))
        except Exception as e:
            cur.execute(
                "UPDATE report_queue SET status='error', status_detail=%s WHERE id=%s",
                (str(e), j['id'])
            )

    c.close()
    return len(jobs)


if __name__ == '__main__':
    print('[report-bot] starting', flush=True)
    while True:
        try:
            n = pq()
            if n:
                print(f'[report-bot] processed {n} report(s)', flush=True)
        except Exception as e:
            print(f'[report-bot] error: {e}', flush=True)
        time.sleep(15)
