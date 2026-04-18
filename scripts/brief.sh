#!/usr/bin/env bash
# brief.sh — Lance le serveur du brief et ouvre le formulaire.
# Ctrl+C pour stopper proprement.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PORT="${BRIEF_PORT:-9000}"
URL="http://localhost:${PORT}/brief.html"

[ -d _starter ] || { echo "✗ _starter/ introuvable à la racine"; exit 1; }

if lsof -i ":${PORT}" -sTCP:LISTEN >/dev/null 2>&1; then
    echo "✗ Port ${PORT} déjà utilisé — change avec BRIEF_PORT=9001 ./scripts/brief.sh"
    exit 1
fi

echo "▶︎ Brief server sur ${URL}"
echo "  Ctrl+C pour stopper"
echo ""

php -S "localhost:${PORT}" -t _starter/ >/dev/null 2>&1 &
PID=$!
trap 'kill $PID 2>/dev/null || true; echo ""; echo "✓ Serveur stoppé"; exit 0' INT TERM

sleep 1
open "$URL" 2>/dev/null || echo "  (ouvre manuellement : ${URL})"

wait $PID
