#!/usr/bin/env bash
# Phase E2b — repair secrets file, then diagnose Traccar wialon port + token API.
set -uo pipefail
SECRETS=/root/salam_secrets.env
T=http://127.0.0.1:8082
ADMIN_EMAIL="admin@salamheyetimiz.com"

echo "===== repair secrets (keep first 5 canonical lines) ====="
head -5 "$SECRETS" > /tmp/s.$$ && mv /tmp/s.$$ "$SECRETS" && chmod 600 "$SECRETS"
echo "  secrets keys now: $(cut -d= -f1 "$SECRETS" | tr '\n' ' ')"
. "$SECRETS"

echo "===== diagnose ====="
echo "[default.xml location]"; docker exec traccar find /opt/traccar -maxdepth 3 -name 'default.xml' 2>/dev/null | sed 's/^/  /' || echo "  none"
echo "[wialon/5011 in startup logs]"; docker logs traccar 2>&1 | grep -iE 'wialon|:5011|port.*5011' | head -5 | sed 's/^/  /' || echo "  (none in logs)"

echo "[login response]"
curl -s -i -c /tmp/tc.$$ -X POST "$T/api/session" \
  --data-urlencode "email=${ADMIN_EMAIL}" --data-urlencode "password=${TRACCAR_ADMIN_PASSWORD}" \
  | grep -iE '^HTTP/|^set-cookie' | head | sed 's/^/  /'

echo "[token POST]"
R=$(curl -s -b /tmp/tc.$$ -w '\n__META__ http=%{http_code} ct=%{content_type} size=%{size_download}' -X POST "$T/api/session/token")
echo "$R" | grep '__META__' | sed 's/^/  /'
echo "  first 120 chars of body: $(echo "$R" | grep -v '__META__' | head -c 120)"
rm -f /tmp/tc.$$
echo "===== DIAG DONE ====="
