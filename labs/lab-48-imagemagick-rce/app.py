import os
import uuid
import subprocess
from flask import Flask, request, jsonify, render_template_string
from markupsafe import Markup, escape

app = Flask(__name__)

HTML = """<!DOCTYPE html>
<html>
<head><title>Darkroom - Photo Processor</title>
<style>
body { background: #1a1a1a; color: #e0e0e0; font-family: monospace; max-width: 800px; margin: 50px auto; padding: 20px; }
h1 { color: #ff6b35; }
.subtitle { color: #888; margin-bottom: 30px; }
form { background: #2a2a2a; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
input[type=file] { margin: 10px 0; }
button { background: #ff6b35; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; }
button:hover { background: #e55a24; }
.output { background: #0d0d0d; padding: 15px; border-radius: 4px; white-space: pre-wrap; word-break: break-all; font-size: 12px; max-height: 400px; overflow-y: auto; border: 1px solid #333; }
.info { color: #888; font-size: 12px; margin-top: 10px; }
a { color: #ff6b35; }
</style>
</head>
<body>
<h1>Darkroom</h1>
<p class="subtitle">Upload your photos for processing. We use ImageMagick to convert your images.</p>
<form action="/upload" method="post" enctype="multipart/form-data">
  <label>Select image file:</label><br>
  <input type="file" name="file" required><br>
  <button type="submit">Process Image</button>
</form>
<p class="info">Supported formats: PNG, JPG, GIF, BMP, SVG and more.</p>
<p class="info"><a href="/result">View last result file</a></p>
</body>
</html>"""

RESULT_HTML = """<!DOCTYPE html>
<html>
<head><title>Darkroom - Result</title>
<style>
body { background: #1a1a1a; color: #e0e0e0; font-family: monospace; max-width: 800px; margin: 50px auto; padding: 20px; }
h1 { color: #ff6b35; }
.output { background: #0d0d0d; padding: 15px; border-radius: 4px; white-space: pre-wrap; word-break: break-all; font-size: 12px; border: 1px solid #333; }
a { color: #ff6b35; }
</style>
</head>
<body>
<h1>Result File</h1>
<div class="output">CONTENT_PLACEHOLDER</div>
<br><a href="/">Back</a>
</body>
</html>"""

UPLOAD_RESULT_HTML = """<!DOCTYPE html>
<html>
<head><title>Darkroom - Processing Result</title>
<style>
body { background: #1a1a1a; color: #e0e0e0; font-family: monospace; max-width: 800px; margin: 50px auto; padding: 20px; }
h1 { color: #ff6b35; }
.output { background: #0d0d0d; padding: 15px; border-radius: 4px; white-space: pre-wrap; word-break: break-all; font-size: 12px; border: 1px solid #333; }
a { color: #ff6b35; }
</style>
</head>
<body>
<h1>Processing Output</h1>
<div class="output">{{ output }}</div>
<br><a href="/">Back</a>
</body>
</html>"""


@app.route('/')
def index():
    return render_template_string(HTML)


@app.route('/upload', methods=['POST'])
def upload():
    if 'file' not in request.files:
        return jsonify({'error': 'No file provided'}), 400

    f = request.files['file']
    if f.filename == '':
        return jsonify({'error': 'No file selected'}), 400

    ext = ''
    if '.' in f.filename:
        ext = '.' + f.filename.rsplit('.', 1)[1]

    tmp_name = str(uuid.uuid4()) + ext
    filepath = '/tmp/' + tmp_name
    f.save(filepath)

    output = ''
    try:
        result = subprocess.check_output(
            ['convert', filepath, '/tmp/out.png'],
            stderr=subprocess.STDOUT
        )
        output = result.decode('utf-8', errors='replace')
    except subprocess.CalledProcessError as e:
        output = e.output.decode('utf-8', errors='replace')
    except Exception as e:
        output = str(e)

    return render_template_string(UPLOAD_RESULT_HTML, output=output)


@app.route('/result')
def result():
    content = ''
    try:
        with open('/tmp/pwned', 'r') as fh:
            content = fh.read()
    except Exception:
        content = 'No result file found yet.'
    html = RESULT_HTML.replace('CONTENT_PLACEHOLDER', str(Markup(escape(content))))
    return html


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
