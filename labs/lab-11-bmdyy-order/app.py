#!/usr/bin/python3
import os
from flask import Flask, render_template, request
import psycopg2

app = Flask(__name__)

FLAG_LOCAL = os.environ.get('FLAG_LOCAL', 'ARENA{LOCAL_dev}')

def _read_flag_local():
    try:
        return open('/flag_local.txt').read().strip()
    except Exception:
        return FLAG_LOCAL

def db_conn():
    return psycopg2.connect(database="order", user="postgres",
                            password="postgres", host="127.0.0.1", port="5432")

@app.route('/', methods=['GET', 'POST'])
def index():
    if request.method == 'GET':
        return render_template('index.html', success=None, flag=None)
    elif request.method == 'POST':
        conn = db_conn()
        cur = conn.cursor()
        cur.execute("SELECT * FROM users WHERE username=%(u)s AND password=%(p)s",
                    {'u': 'admin', 'p': request.form['password']})
        rows = cur.fetchall()
        conn.close()
        if len(rows) > 0:
            return render_template('index.html', success=1, flag=_read_flag_local())
        return render_template('index.html', success=-1, flag=None)

@app.route('/horses')
def horses():
    order_by = request.args.get('order')
    if order_by is None:
        order_by = '2'
    conn = db_conn()
    cur = conn.cursor()
    # Injection point: ORDER BY clause
    cur.execute("SELECT * FROM horses ORDER BY %s" % (order_by,))
    rows = cur.fetchall()
    conn.close()
    return render_template('horses.html', rows=rows, order=str(order_by))

if __name__ == '__main__':
    app.run(host='0.0.0.0')
