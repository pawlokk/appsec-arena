import os

SECRET_KEY = os.environ.get('SECRET_KEY', 'vein-dev-fallback-x7k2m')
DATABASE_URL = os.environ.get('DATABASE_URL', 'dbname=vein user=vein password=veinpass host=localhost')

ADMIN_PASSWORD = os.environ.get('ADMIN_PASSWORD', 'admin123')

DEBUG = False
