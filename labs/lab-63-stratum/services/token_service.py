import hashlib
import time


def generate_reset_token(username):
    bucket = int(time.time() / 60)
    return hashlib.md5(username.encode() + str(bucket).encode()).hexdigest()


def verify_reset_token(username, token):
    bucket = int(time.time() / 60)
    for offset in (0, -1):
        expected = hashlib.md5(username.encode() + str(bucket + offset).encode()).hexdigest()
        if expected == token:
            return True
    return False


def generate_invite_token(email, invited_by):
    bucket = int(time.time() / 3600)
    return hashlib.sha1(
        email.encode() + str(invited_by).encode() + str(bucket).encode()
    ).hexdigest()
