import os

INTERNAL_API_TOKEN = os.environ.get('INTERNAL_API_TOKEN', 'vein-internal-svc-k9Pw3mXqR7')

INTERNAL_PROCESSOR_URL = 'http://127.0.0.1:5000/internal'

WORKER_POLL_INTERVAL = 10
WORKER_MAX_BATCH = 5

REPORT_RENDER_ENDPOINT = 'http://127.0.0.1:5000/internal/render'
