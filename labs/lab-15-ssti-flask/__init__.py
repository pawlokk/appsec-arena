import os
from flask import Flask, render_template_string, request

app = Flask(__name__, static_url_path='/static')

FLAG_LOCAL = os.environ.get('FLAG_LOCAL', 'ARENA{LOCAL_dev}')

def _read_flag_local():
    try:
        return open('/flag_local.txt').read().strip()
    except Exception:
        return FLAG_LOCAL

@app.route("/")
def home():
    user = request.args.get('user') or None
    flag_local = _read_flag_local()

    template = '''
    <html><head><title>SSTI Playground</title><style>
    body {margin: 90px; background-image: url('{{url_for('static', filename='bg.jpg')}}');}
    .token-box {background:rgba(0,0,0,.6);color:#a6e3a1;font-family:monospace;padding:12px 16px;border-radius:6px;display:inline-block;margin-top:8px;font-size:13px;}
    </style></head><body>
    '''

    footer = '''
    <br><p style="margin-top: 30px; color:#999; font-size:12px;">
    SSTI Playground &mdash; identify the injection, gain code execution, read <code>/flag_proof.txt</code>.
    </p>
    '''

    if user is None:
        template = template + '''
        <h1 style="color:#eee;">SSTI Playground</h1>
        <p style="color:#ccc;">Enter a username to personalise your greeting.</p>
        <form>
        <input name="user" style="border: 2px solid #C21010; padding: 10px; border-radius: 10px; margin-bottom: 25px;" value="Username"><br>
        <input type="submit" value="Log In" style="border: 0px; padding: 5px 20px; color: #C21010;">
        </form>
        <div style="margin-top:30px;">
          <p style="color:#888;font-size:13px;">Access token:</p>
          <div class="token-box">''' + flag_local + '''</div>
        </div>
        ''' + footer
    else:
        template = template + '''
        <h1 style="color:#eee;">Hi {}</h1>
        <p style="color:#ccc;">Welcome to the vulnerable app.<br>Have fun with Server-Side Template Injection (SSTI).</p>
        '''.format(user) + footer

    return render_template_string(template)

if __name__ == "__main__":
    app.run(debug=False, host='0.0.0.0', port=8089)
