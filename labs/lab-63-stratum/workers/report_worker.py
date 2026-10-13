#!/usr/bin/env python3
import os
import sys
import time

sys.path.insert(0, '/app')

import psycopg2
import psycopg2.extras

DATABASE_URL = os.environ.get(
    'DATABASE_URL',
    'dbname=stratum user=stratum password=stratumpass host=localhost'
)


def gd():
    c = psycopg2.connect(DATABASE_URL)
    c.autocommit = True
    return c


def process_pending_reports():
    c = gd()
    s = c.cursor(cursor_factory=psycopg2.extras.DictCursor)

    s.execute("""
        SELECT sr.id, sr.template_ref, sr.report_data, u.username
        FROM scheduled_reports sr
        JOIN users u ON u.id = sr.owner_id
        WHERE sr.status = 'pending'
        ORDER BY sr.created_at ASC
        LIMIT 10
    """)
    jobs = s.fetchall()

    for job in jobs:
        try:
            s.execute(
                "SELECT template_body FROM report_templates WHERE id = %s",
                (job['template_ref'],)
            )
            tr = s.fetchone()
            if not tr:
                s.execute(
                    "UPDATE scheduled_reports SET status = 'error',"
                    " status_detail = 'Template not found' WHERE id = %s",
                    (job['id'],)
                )
                continue

            rd = job['report_data'] or {}

            from flask import Flask as _Flask
            from flask import render_template_string
            _a = _Flask(__name__)
            with _a.app_context():
                r = render_template_string(tr['template_body'], **rd)

            s.execute(
                "UPDATE scheduled_reports SET status = 'done', result = %s WHERE id = %s",
                (r, job['id'])
            )
        except Exception as ex:
            s.execute(
                "UPDATE scheduled_reports SET status = 'error', status_detail = %s WHERE id = %s",
                (str(ex), job['id'])
            )

    c.close()
    return len(jobs)


if __name__ == '__main__':
    print('[worker] Stratum report worker starting', flush=True)
    while True:
        try:
            n = process_pending_reports()
            if n:
                print(f'[worker] processed {n} report(s)', flush=True)
        except Exception as ex:
            print(f'[worker] error: {ex}', flush=True)
        time.sleep(30)
