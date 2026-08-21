#!/usr/bin/env bash
# Phase E2c — generate Traccar API token (form-encoded + expiration), wire into app .env safely,
# confirm Wialon port 5011 listening inside the container.
set -euo pipefail
SECRETS=/root/salam_secrets.env
APP=/var/www/salam
T=http://127.0.0.1:8082
ADMIN_EMAIL="admin@salamheyetimiz.com"
. "$SECRETS"

echo "===== PHASE E2c: Traccar API token ====="

echo "--- login ---"
curl -s -c /tmp/tc -o /dev/null -X POST "$T/api/session" \
  --data-urlencode "email=${ADMIN_EMAIL}" --data-urlencode "password=${TRACCAR_ADMIN_PASSWORD}"

echo "--- generate token (form-encoded, expiration 2035) ---"
TOKEN=$(curl -s -b /tmp/tc -X POST "$T/api/session/token" --data-urlencode "expiration=2035-12-31T00:00:00.000Z")
rm -f /tmp/tc
echo "  token length=${#TOKEN}"

if [ ${#TOKEN} -ge 16 ] && [ ${#TOKEN} -le 512 ] && ! printf '%s' "$TOKEN" | grep -q ' '; then
  echo "  token looks valid -> wiring"
  # secrets (safe: filter + append, no sed substitution)
  grep -v '^TRACCAR_API_TOKEN=' "$SECRETS" > "$SECRETS.tmp"; echo "TRACCAR_API_TOKEN=${TOKEN}" >> "$SECRETS.tmp"; mv "$SECRETS.tmp" "$SECRETS"; chmod 600 "$SECRETS"
  # app .env (safe)
  grep -v '^TRACCAR_API_TOKEN=' "$APP/.env" > "$APP/.env.tmp"; echo "TRACCAR_API_TOKEN=${TOKEN}" >> "$APP/.env.tmp"; mv "$APP/.env.tmp" "$APP/.env"; chown www-data:www-data "$APP/.env"; chmod 640 "$APP/.env"
  echo "--- token verify (GET /api/devices) ---"
  echo "  devices http=$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer ${TOKEN}" "$T/api/devices")"
  echo "--- re-cache app config ---"
  cd "$APP"; php artisan config:clear >/dev/null; php artisan config:cache >/dev/null; chown -R www-data:www-data "$APP/bootstrap/cache"
else
  echo "  WARN: token shape unexpected (len=${#TOKEN}); not wiring. First 100: $(printf '%s' "$TOKEN" | head -c 100)"
fi

echo "===== VERIFICATION ====="
echo "[wialon 5011 listening inside container]"
HEX=$(docker exec traccar sh -c 'cat /proc/net/tcp /proc/net/tcp6 2>/dev/null' | awk '{print $2}' | grep -iE ':1393$' | head -1)
[ -n "$HEX" ] && echo "  YES — container listens on :5011 (hex $HEX) for Wialon" || echo "  NOT FOUND on 5011"
echo "[app config token length]"; sudo -u www-data php "$APP/artisan" tinker --execute='echo "len=".strlen((string)config("integrations.traccar.api_token"));' 2>/dev/null | tail -1 || echo "  (set in .env)"
echo "[app health/ready]"; curl -s -H 'Host: salamheyetimiz.com' -H 'Accept: application/json' http://127.0.0.1/v1/health/ready; echo
echo "===== PHASE E2c DONE ====="
