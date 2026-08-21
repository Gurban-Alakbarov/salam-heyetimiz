#!/usr/bin/env bash
# Phase E2 — confirm Wialon port, verify container->backend path, create Traccar admin + API token,
# wire TRACCAR_API_TOKEN into the app .env.
set -euo pipefail
. /root/salam_secrets.env
APP=/var/www/salam
ADMIN_EMAIL="admin@salamheyetimiz.com"
T=http://127.0.0.1:8082

echo "===== PHASE E2: Traccar wiring ====="

echo "[wialon port in default.xml]"
docker exec traccar sh -c "grep -iE 'wialon.port|wialon</' /opt/traccar/conf/default.xml" | sed 's/^/  /' || echo "  (grep failed)"

echo "[container -> backend webhook path]"
docker exec traccar sh -c "curl -s -o /dev/null -w 'host.docker.internal http=%{http_code}\n' http://host.docker.internal/v1/health/live" 2>/dev/null | sed 's/^/  /' || echo "  (curl in container unavailable)"

echo "--- create first Traccar admin user (first user = admin) ---"
CREATE=$(curl -s -w '\n%{http_code}' -X POST "$T/api/users" -H 'Content-Type: application/json' \
  -d "{\"name\":\"Salam Admin\",\"email\":\"${ADMIN_EMAIL}\",\"password\":\"${TRACCAR_ADMIN_PASSWORD}\"}")
echo "  create-user http=$(echo "$CREATE" | tail -1)"

echo "--- login + generate API token ---"
curl -s -c /tmp/tcookie -o /dev/null -X POST "$T/api/session" \
  --data-urlencode "email=${ADMIN_EMAIL}" --data-urlencode "password=${TRACCAR_ADMIN_PASSWORD}"
TOKEN=$(curl -s -b /tmp/tcookie -X POST "$T/api/session/token")
rm -f /tmp/tcookie

if [ -n "$TOKEN" ] && [ ${#TOKEN} -gt 10 ]; then
  echo "  token generated (len=${#TOKEN})"
  grep -q '^TRACCAR_API_TOKEN=' /root/salam_secrets.env && sed -i "s|^TRACCAR_API_TOKEN=.*|TRACCAR_API_TOKEN=${TOKEN}|" /root/salam_secrets.env || echo "TRACCAR_API_TOKEN=${TOKEN}" >> /root/salam_secrets.env
  sed -i "s|^TRACCAR_API_TOKEN=.*|TRACCAR_API_TOKEN=${TOKEN}|" "$APP/.env"
  echo "--- token verify (GET /api/devices) ---"
  echo "  devices http=$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer ${TOKEN}" "$T/api/devices")"
  echo "--- re-cache app config ---"
  cd "$APP"; php artisan config:clear >/dev/null; php artisan config:cache >/dev/null; chown -R www-data:www-data "$APP/bootstrap/cache"
  echo "  app TRACCAR token wired: $(sudo -u www-data php artisan tinker --execute='echo strlen((string)config("integrations.traccar.api_token"));' 2>/dev/null || echo 'set')"
else
  echo "  WARN: token not generated (will set TRACCAR_API_TOKEN manually). create-user response:"; echo "$CREATE" | head -1 | sed 's/^/    /'
fi

echo "===== VERIFICATION ====="
echo "[app health/ready]"; curl -s -H 'Host: salamheyetimiz.com' -H 'Accept: application/json' http://127.0.0.1/v1/health/ready; echo
echo "[traccar containers]"; docker ps --format '  {{.Names}} {{.Status}}'
echo "===== PHASE E2 DONE ====="
