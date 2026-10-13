#!/usr/bin/env bash
# Build a lab image and verify the container starts and serves HTTP.
# Usage:
#   ./tools/smoke_test.sh lab-00-testr    # test one lab
#   ./tools/smoke_test.sh                 # test all labs

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_PY="$ROOT/platform/app.py"
LABS_DIR="$ROOT/labs"

SMOKE_PORT_BASE=19000
SMOKE_PORT_MAX=19099

passed=0
failed=0
port_offset=0

smoke_one() {
  local lab_id="$1"
  local lab_dir="$LABS_DIR/$lab_id"
  local image="appsec-arena-$lab_id"
  local container="arena-smoke-$lab_id"
  local port=$((SMOKE_PORT_BASE + port_offset))
  ((port_offset++)) || true

  if [[ $port -gt $SMOKE_PORT_MAX ]]; then
    echo "[ERROR] smoke port pool exhausted (19000-19099)"
    exit 1
  fi

  if [[ ! -d "$lab_dir" ]]; then
    echo "[FAIL] $lab_id — directory not found"
    ((failed++)) || true
    return
  fi

  # Build
  echo -n "  building $lab_id... "
  if ! docker build -t "$image" "$lab_dir/" -q > /tmp/smoke_build_"$lab_id".log 2>&1; then
    echo "FAIL (build error)"
    echo "    $(tail -5 /tmp/smoke_build_"$lab_id".log | sed 's/^/    /')"
    echo "[FAIL] $lab_id — build error"
    ((failed++)) || true
    return
  fi
  echo "built"

  # Start
  docker run -d \
    --name "$container" \
    -p "${port}:5000" \
    -e FLAG_LOCAL=ARENA{smoke} \
    -e FLAG_PROOF=ARENA{smoke} \
    -e ADMIN_PASS=smoketest \
    -e ADMIN_PASSWORD=smoketest \
    -e USER_PASSWORD=smoketest \
    -e INTERNAL_API_KEY=smoketest \
    "$image" > /dev/null 2>&1

  # Poll
  local ok=0
  for i in $(seq 1 8); do
    sleep 2
    if curl -sf -o /dev/null -w "%{http_code}" "http://localhost:${port}/" 2>/dev/null | grep -qE '^[23]'; then
      ok=1
      break
    fi
  done

  # Cleanup
  docker stop "$container" > /dev/null 2>&1 || true
  docker rm "$container" > /dev/null 2>&1 || true

  if [[ $ok -eq 1 ]]; then
    echo "[PASS] $lab_id (port $port)"
    ((passed++)) || true
  else
    echo "[FAIL] $lab_id — did not respond on port $port within 16s"
    ((failed++)) || true
  fi
}

if [[ $# -ge 1 ]]; then
  smoke_one "$1"
else
  LAB_IDS=$(grep '"id": "lab-' "$APP_PY" | sed 's/.*"id": "\(lab-[^"]*\)".*/\1/')
  if [[ -z "$LAB_IDS" ]]; then
    echo "[ERROR] Could not parse lab IDs from $APP_PY"
    exit 1
  fi
  for lab_id in $LAB_IDS; do
    smoke_one "$lab_id"
  done
fi

echo ""
echo "Passed: $passed  Failed: $failed"
