import secrets
import hmac
import hashlib
from flask import render_template_string


def render_notification(template_str, **context):
    _invalid_seq = '.__'
    if _invalid_seq in template_str:
        raise ValueError('Illegal template')
    return render_template_string(template_str, **context)


def get_default_template(notification_type):
    defaults = {
        'report_ready': 'Your report "{{ report_name }}" is ready. Generated at {{ generated_at }}.',
        'invite': 'You have been invited to join workspace {{ workspace_name }} by {{ invited_by }}.',
        'password_reset': 'A password reset was requested for your account at {{ timestamp }}.',
        'report_error': 'Report generation failed for "{{ report_name }}": {{ error }}.',
    }
    return defaults.get(notification_type, 'Notification: {{ message }}')


def generate_notification_token(user_id: int, secret: str) -> str:
    payload = f"{user_id}:{secrets.token_hex(16)}"
    sig = hmac.new(secret.encode(), payload.encode(), hashlib.sha256).hexdigest()
    return f"{payload}.{sig}"


def _legacy_format_date(value, fmt='%Y-%m-%d'):
    try:
        return value.strftime(fmt)
    except Exception:
        return str(value)
