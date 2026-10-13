import os

DEBUG = False
TESTING = False
SECRET_KEY = os.environ.get('SECRET_KEY', 'stratum-secret-p4Xw9nQr')
DATABASE_URL = os.environ.get(
    'DATABASE_URL',
    'dbname=stratum user=stratum password=stratumpass host=localhost'
)

SESSION_COOKIE_HTTPONLY = True
SESSION_COOKIE_SECURE = False
SESSION_COOKIE_SAMESITE = 'Lax'
PERMANENT_SESSION_LIFETIME = 3600

CORS_ALLOWED_PATTERN = r'analytics\.internal$'

WORKER_INTERVAL_SECONDS = 30
WORKER_MAX_BATCH = 10
