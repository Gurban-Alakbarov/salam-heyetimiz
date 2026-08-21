#!/usr/bin/env bash
# Phase H (SSL) — install Cloudflare Origin cert, configure Nginx HTTPS (Full Strict), HTTP->HTTPS redirect.
# The internal Traccar event-forward webhook stays on HTTP from the docker bridge (no redirect).
set -euo pipefail
CRT=/etc/ssl/cloudflare/salam-origin.pem
KEY=/etc/ssl/cloudflare/salam-origin.key

echo "===== PHASE H (SSL): Cloudflare Origin cert + Nginx HTTPS ====="

sed -i 's/\r$//' "$CRT" "$KEY"
chmod 644 "$CRT"; chmod 600 "$KEY"; chown root:root "$CRT" "$KEY"

CM=$(openssl x509 -noout -modulus -in "$CRT" | openssl md5 | awk '{print $NF}')
KM=$(openssl rsa  -noout -modulus -in "$KEY" 2>/dev/null | openssl md5 | awk '{print $NF}')
echo "[cert/key modulus] cert=$CM key=$KM"
[ "$CM" = "$KM" ] && [ -n "$CM" ] || { echo "FATAL: cert/key mismatch"; exit 1; }
echo "[cert]"; openssl x509 -noout -subject -enddate -in "$CRT" | sed 's/^/  /'
echo "[SAN]"; openssl x509 -noout -ext subjectAltName -in "$CRT" | tail -1 | sed 's/^/  /'

cat > /etc/nginx/sites-available/salam <<'EOF'
# ---- HTTP :80 — redirect to HTTPS, EXCEPT the internal Traccar webhook (docker bridge only) ----
server {
    listen 80;
    listen [::]:80;
    server_name salamheyetimiz.com www.salamheyetimiz.com gps.salamheyetimiz.com 185.208.206.174;
    root /var/www/salam/public;

    location = /v1/traccar/forward {
        allow 172.16.0.0/12;
        deny all;
        try_files $uri /index.php?$query_string;
    }
    location ~ \.php$ {
        allow 172.16.0.0/12;
        deny all;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS off;
        include fastcgi_params;
    }
    location / { return 301 https://salamheyetimiz.com$request_uri; }
}

# ---- HTTPS :443 — Cloudflare Origin certificate (SSL/TLS mode: Full (Strict)) ----
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name salamheyetimiz.com www.salamheyetimiz.com;
    root /var/www/salam/public;
    index index.php;
    client_max_body_size 20m;
    charset utf-8;

    ssl_certificate     /etc/ssl/cloudflare/salam-origin.pem;
    ssl_certificate_key /etc/ssl/cloudflare/salam-origin.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
EOF

nginx -t
systemctl reload nginx

echo "===== VERIFICATION (origin, -k = self-trust bypass; CF edge does the real validation) ====="
echo "[listening 443]"; ss -tlnH 'sport = :443' | awk '{print "  "$4}'
echo "[https/live]";  curl -sk -H 'Host: salamheyetimiz.com' -w "  (http=%{http_code})\n" https://127.0.0.1/v1/health/live
echo "[https/ready]"; curl -sk -H 'Host: salamheyetimiz.com' -w "  (http=%{http_code})\n" https://127.0.0.1/v1/health/ready
echo "[http->https]"; curl -s -o /dev/null -w "  code=%{http_code} -> %{redirect_url}\n" -H 'Host: salamheyetimiz.com' http://127.0.0.1/v1/devices/1
echo "[webhook on :80 from non-bridge is denied]"; curl -s -o /dev/null -w "  forward(loopback)=%{http_code}\n" -H 'Host: salamheyetimiz.com' "http://127.0.0.1/v1/traccar/forward"
echo "===== SSL DONE (origin) ====="
