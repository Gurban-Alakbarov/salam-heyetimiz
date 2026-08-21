#!/usr/bin/env bash
# Phase H — UFW firewall. SSH(22) kept open FIRST. 80/443 limited to Cloudflare IPs.
# 5011 open (Wialon devices). Docker bridge -> host:80 allowed (Traccar webhook).
# DEFAULT_FORWARD_POLICY=ACCEPT so Docker container networking is not broken.
set -euo pipefail

echo "===== PHASE H: UFW firewall ====="

# keep Docker container forwarding working under UFW
sed -i 's/^DEFAULT_FORWARD_POLICY=.*/DEFAULT_FORWARD_POLICY="ACCEPT"/' /etc/default/ufw

ufw --force reset >/dev/null 2>&1 || true
ufw default deny incoming
ufw default allow outgoing

# 1) SSH FIRST — never lock ourselves out (root+password auth retained per decision)
ufw allow 22/tcp comment 'SSH'

# 2) Traccar Wialon IPS device port (devices connect from mobile-operator IPs)
ufw allow 5011/tcp comment 'Traccar Wialon (devices)'

# 2b) Traccar GT06 device port (Jimi VL110C — online-only onboarding, 2026-07)
ufw allow 5023/tcp comment 'Traccar GT06 (VL110C)'

# 3) HTTP/HTTPS origin reachable ONLY via Cloudflare edge IPs
echo "--- fetching Cloudflare IP ranges ---"
CF4=$(curl -s https://www.cloudflare.com/ips-v4)
CF6=$(curl -s https://www.cloudflare.com/ips-v6)
n=0
for cidr in $CF4 $CF6; do
  ufw allow proto tcp from "$cidr" to any port 80,443 comment 'Cloudflare' >/dev/null && n=$((n+1))
done
echo "  added $n Cloudflare allow rules (80,443)"

# 4) Docker bridge -> host:80 for the Traccar event-forward webhook to Laravel
ufw allow proto tcp from 172.16.0.0/12 to any port 80 comment 'docker->webhook' >/dev/null

ufw --force enable

echo "===== VERIFICATION ====="
echo "[forward policy]"; grep DEFAULT_FORWARD_POLICY /etc/default/ufw | sed 's/^/  /'
echo "[ufw status]"; ufw status verbose | sed -n '1,12p' | sed 's/^/  /'
echo "[ssh rule present?]"; ufw status | grep -E '22/tcp' | head -1 | sed 's/^/  /'
echo "[cloudflare rules]"; echo "  count=$(ufw status | grep -c Cloudflare)"
echo "[containers healthy after ufw?]"; docker ps --format '  {{.Names}} {{.Status}}'
echo "[traccar-db reachable from traccar net]"; docker exec traccar-db mariadb -utraccar -p"$(grep '^TRACCAR_DB_PASSWORD=' /root/salam_secrets.env | cut -d= -f2)" -N traccar -e "SELECT 'db-ok';" 2>/dev/null | sed 's/^/  /'
echo "[app health via localhost]"; curl -s -o /dev/null -w "  health=%{http_code}\n" -H 'Host: salamheyetimiz.com' http://127.0.0.1/v1/health/live
echo "===== PHASE H DONE ====="
