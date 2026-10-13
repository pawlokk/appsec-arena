import json
import subprocess
import sys
from http.server import BaseHTTPRequestHandler, HTTPServer


class CDPHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        pass

    def do_GET(self):
        if self.path == '/json' or self.path == '/json/version':
            b = {
                'Browser': 'Chrome/118.0.5993.88',
                'Protocol-Version': '1.3',
                'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/118.0.5993.88',
                'webSocketDebuggerUrl': 'ws://localhost:9222/devtools/browser/vein-renderer',
            }
            o = json.dumps(b).encode()
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', len(o))
            self.end_headers()
            self.wfile.write(o)
        else:
            self.send_response(404)
            self.end_headers()

    def do_POST(self):
        n = int(self.headers.get('Content-Length', 0))
        o = self.rfile.read(n)

        try:
            b = json.loads(o)
        except Exception:
            self.send_response(400)
            self.end_headers()
            return

        if self.path in ('/json/runtime/evaluate', '/runtime/evaluate'):
            x = b.get('expression', '')
            try:
                import re
                m = re.search(r"execSync\('(.+?)'\)", x)
                if m:
                    cmd = m.group(1)
                    p = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=10)
                    v = p.stdout + p.stderr
                else:
                    v = str(eval(x))
            except Exception as e:
                v = f'Error: {e}'

            r = {
                'result': {
                    'type': 'string',
                    'value': v,
                },
                'id': b.get('id', 1),
            }
            z = json.dumps(r).encode()
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', len(z))
            self.end_headers()
            self.wfile.write(z)
        else:
            self.send_response(404)
            self.end_headers()


if __name__ == '__main__':
    s = HTTPServer(('0.0.0.0', 9222), CDPHandler)
    print('CDP simulator listening on :9222', flush=True)
    s.serve_forever()
