import os
import uuid
import subprocess
import threading
import json

from flask import Flask, request, jsonify, Response

FLAG_LOCAL = os.environ.get("FLAG_LOCAL", "ARENA{LOCAL_placeholder}")
FLAG_PROOF = os.environ.get("FLAG_PROOF", "ARENA{PROOF_placeholder}")

admin_app = Flask("admin")

@admin_app.route("/admin/info", methods=["GET"])
def admin_info():
    import socket
    return jsonify({"flag_local": FLAG_LOCAL, "host": socket.gethostname()})

@admin_app.route("/admin/exec", methods=["POST"])
def admin_exec():
    data = request.get_json(force=True, silent=True) or {}
    cmd = data.get("cmd", "")
    if not cmd:
        return jsonify({"error": "no cmd"}), 400
    result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=10)
    return jsonify({"stdout": result.stdout, "stderr": result.stderr, "rc": result.returncode})

def run_admin():
    admin_app.run(host="127.0.0.1", port=8888, debug=False, use_reloader=False)

app = Flask(__name__)

@app.route("/", methods=["GET"])
def index():
    return """<!DOCTYPE html>
<html>
<head><title>Render – Invoice Generator</title>
<style>
body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:0 20px;background:#f5f5f5}
h1{color:#333}textarea{width:100%;height:200px;font-family:monospace;font-size:13px}
button{padding:10px 20px;background:#0066cc;color:#fff;border:none;cursor:pointer;border-radius:4px}
pre{background:#fff;border:1px solid #ddd;padding:12px;border-radius:4px;white-space:pre-wrap;word-break:break-all}
.result{margin-top:20px}
</style>
</head>
<body>
<h1>Render – Invoice Generator</h1>
<p>Paste your invoice HTML below. The server will render it and return the processed document.</p>
<form id="f">
<textarea id="html" placeholder="<h1>Invoice #1234</h1><p>Amount due: $100</p>"></textarea><br><br>
<button type="submit">Generate Invoice</button>
</form>
<div id="out" class="result"></div>
<script>
document.getElementById('f').onsubmit = async function(e){
  e.preventDefault();
  var html = document.getElementById('html').value;
  var r = await fetch('/invoice/generate',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({html:html})});
  var data = await r.json();
  var outDiv = document.getElementById('out');
  if(data.id){
    var r2 = await fetch('/invoice/result?id=' + encodeURIComponent(data.id));
    var text = await r2.text();
    outDiv.innerHTML = '<h3>Rendered output:</h3><pre>' + text.replace(/</g,'&lt;') + '</pre>';
  } else {
    outDiv.innerHTML = '<pre>' + JSON.stringify(data,null,2).replace(/</g,'&lt;') + '</pre>';
  }
};
</script>
</body>
</html>"""

@app.route("/invoice/generate", methods=["POST"])
def generate_invoice():
    data = request.get_json(force=True, silent=True) or {}
    html_content = data.get("html", "")
    if not html_content:
        return jsonify({"error": "html field required"}), 400

    job_id = str(uuid.uuid4())
    html_path = f"/tmp/invoice_{job_id}.html"
    out_path = f"/tmp/out_{job_id}.pdf"

    with open(html_path, "w") as f:
        f.write(html_content)

    try:
        cmd = [
            "xvfb-run", "--auto-servernum",
            "wkhtmltopdf",
            "--enable-javascript",
            "--javascript-delay", "3000",
            "--no-sandbox",
            "--quiet",
            html_path,
            out_path
        ]
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        return jsonify({"id": job_id, "rc": proc.returncode})
    except subprocess.TimeoutExpired:
        return jsonify({"error": "render timeout"}), 504
    except Exception as ex:
        return jsonify({"error": str(ex)}), 500
    finally:
        try:
            os.unlink(html_path)
        except Exception:
            pass

@app.route("/invoice/result", methods=["GET"])
def invoice_result():
    job_id = request.args.get("id", "")
    if not job_id or "/" in job_id or ".." in job_id:
        return jsonify({"error": "invalid id"}), 400
    out_path = f"/tmp/out_{job_id}.pdf"
    if not os.path.exists(out_path):
        return jsonify({"error": "not found"}), 404
    with open(out_path, "rb") as f:
        data = f.read()
    try:
        os.unlink(out_path)
    except Exception:
        pass
    return Response(data, mimetype="application/pdf")

if __name__ == "__main__":
    t = threading.Thread(target=run_admin, daemon=True)
    t.start()
    app.run(host="0.0.0.0", port=5000, debug=False)
