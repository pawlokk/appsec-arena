#!/usr/bin/env bash
# Validate all labs in the LABS registry without building images.
# Exit 0 if no FAILs; exit 1 if any FAIL.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_PY="$ROOT/platform/app.py"
LABS_DIR="$ROOT/labs"
HINTS_DIR="$ROOT/hints"

pass=0
warn=0
fail=0

fail_msg() { echo "[FAIL] $1"; ((fail++)) || true; }
warn_msg() { echo "[WARN] $1"; ((warn++)) || true; }
pass_msg() { echo "[PASS] $1"; ((pass++)) || true; }

# Parse lab IDs from LABS list in app.py
LAB_IDS=$(grep '"id": "lab-' "$APP_PY" | sed 's/.*"id": "\(lab-[^"]*\)".*/\1/')

if [[ -z "$LAB_IDS" ]]; then
  echo "[ERROR] Could not parse lab IDs from $APP_PY"
  exit 1
fi

for lab_id in $LAB_IDS; do
  lab_dir="$LABS_DIR/$lab_id"
  dockerfile="$lab_dir/Dockerfile"

  # Extract zero-padded number for hint file: lab-20-jwt-algconf → 20
  num=$(echo "$lab_id" | sed 's/lab-\([0-9]*\)-.*/\1/')
  hint_file="$HINTS_DIR/lab-${num}.json"

  failed_this=0

  # 1. Lab directory exists
  if [[ ! -d "$lab_dir" ]]; then
    fail_msg "$lab_id: directory $lab_dir does not exist"
    ((failed_this++)) || true
    continue
  fi

  # 2. Dockerfile exists
  if [[ ! -f "$dockerfile" ]]; then
    fail_msg "$lab_id: Dockerfile not found"
    ((failed_this++)) || true
    continue
  fi

  # 3. CMD references FLAG_LOCAL
  if ! grep -q 'FLAG_LOCAL' "$dockerfile"; then
    fail_msg "$lab_id: Dockerfile CMD does not reference FLAG_LOCAL"
    ((failed_this++)) || true
  fi

  # 4. CMD references FLAG_PROOF
  if ! grep -q 'FLAG_PROOF' "$dockerfile"; then
    fail_msg "$lab_id: Dockerfile CMD does not reference FLAG_PROOF"
    ((failed_this++)) || true
  fi

  # 5. Wrong flag format (FLAG_LOCAL{ or FLAG_PROOF{ or FLAG{ instead of ARENA{)
  if grep -qE 'FLAG_LOCAL\{|FLAG_PROOF\{|FLAG\{' "$dockerfile"; then
    fail_msg "$lab_id: wrong flag format (must be ARENA{...}, not FLAG_LOCAL{}/FLAG_PROOF{}/FLAG{})"
    ((failed_this++)) || true
  fi

  # 6. Silent download fallback: touch on a .jar or .zip after wget/curl
  if grep -qE 'touch.*\.(jar|zip)' "$dockerfile"; then
    fail_msg "$lab_id: silent download fallback — 'touch *.jar/zip' creates empty files on download failure"
    ((failed_this++)) || true
  fi

  # 7. Root TCP DB connection in any .py file
  for pyfile in "$lab_dir"/*.py; do
    [[ -f "$pyfile" ]] || continue
    if grep -qE "user=['\"]root['\"]" "$pyfile" && grep -qE "host=['\"]127\.0\.0\.1['\"]" "$pyfile"; then
      fail_msg "$lab_id: $(basename "$pyfile") connects to DB as root via TCP (use a dedicated appuser)"
      ((failed_this++)) || true
    fi
  done

  # 8. Hint file exists
  if [[ ! -f "$hint_file" ]]; then
    fail_msg "$lab_id: hints/lab-${num}.json does not exist"
    ((failed_this++)) || true
  fi

  # WARN: sed / delimiter around password/hash values
  if grep -qE "sed.*s/.*PASS|sed.*s/.*HASH" "$dockerfile"; then
    warn_msg "$lab_id: sed uses / delimiter around a value that may contain / — consider using | instead"
  fi

  # WARN: /releases/latest/ download URL
  if grep -q '/releases/latest/' "$dockerfile"; then
    warn_msg "$lab_id: /releases/latest/ URL — consider pinning to a versioned release"
  fi

  # WARN: build artefacts in lab directory
  if [[ -d "$lab_dir/node_modules" ]]; then
    warn_msg "$lab_id: node_modules/ present — add to .dockerignore"
  fi
  if [[ -d "$lab_dir/__pycache__" ]]; then
    warn_msg "$lab_id: __pycache__/ present — add to .dockerignore"
  fi

  if [[ $failed_this -eq 0 ]]; then
    pass_msg "$lab_id"
  fi
done

echo ""
echo "Summary: $pass PASS, $warn WARN, $fail FAIL"

[[ $fail -eq 0 ]]
