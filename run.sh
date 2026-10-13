#!/usr/bin/env bash
# AppSec Arena — start everything
#
# Usage:
#   ./run.sh                          # binds to all interfaces (0.0.0.0)
#   ./run.sh 192.168.18.110           # bind + generate links for this IP
#   ARENA_HOST=192.168.18.110 ./run.sh  # same via env var
#
set -e
cd "$(dirname "$0")"

# Optional positional arg overrides ARENA_HOST
if [ -n "$1" ]; then
  export ARENA_HOST="$1"
fi

# If ARENA_HOST is set and ARENA_BIND_HOST isn't, bind to that specific IP
# so containers are only reachable on the intended interface.
if [ -n "$ARENA_HOST" ] && [ -z "$ARENA_BIND_HOST" ]; then
  export ARENA_BIND_HOST="$ARENA_HOST"
fi

VENV="platform/.venv"

if [ ! -d "$VENV" ]; then
  python3 -m venv "$VENV"
fi

"$VENV/bin/pip" install -q --upgrade pip
"$VENV/bin/pip" install -q -r platform/requirements.txt

if [ -n "$ARENA_HOST" ]; then
  echo "[arena] Binding to ${ARENA_BIND_HOST}  •  Lab links → http://${ARENA_HOST}:51XX"
else
  echo "[arena] Binding to 0.0.0.0  •  Lab links derived from request host"
fi

exec "$VENV/bin/python" platform/app.py
