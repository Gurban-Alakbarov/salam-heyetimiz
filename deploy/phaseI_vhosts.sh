#!/usr/bin/env bash
# Phase I — multi-host Nginx: Laravel app (apex/www/api/admin) + Traccar reverse-proxy (traccar.*).
# Fixes: traccar.* now serves the Traccar web panel (was falling through to the Laravel app);
# HTTP->HTTPS redirect now preserves the original host.
set -euo pipefail
echo "===== PHASE I: multi-host vhosts ====="

# WebSocket upgrade map (http context) for the Traccar UI live socket
cat > /etc/nginx/conf.d/ws_upgrade.conf <<'EOF'
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}
EOF

cat > /etc/nginx/sites-available/salam <<'EOF'
# ---- HTTP :80 — redirect to HTTPS (preserve host); internal Traccar webhook stays HTTP (bridge only) ----
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
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
    location / { return 301 https://$host$request_uri; }
}

# ---- HTTPS :443 — Laravel app (apex + www + api + admin) ----
server {
    listen 443 ssl http2 default_server;
    listen [::]:443 ssl http2 default_server;
    server_name salamheyetimiz.com www.salamheyetimiz.com api.salamheyetimiz.com admin.salamheyetimiz.com;
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

# ---- HTTPS :443 — Traccar web panel (reverse proxy -> local 8082) ----
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name traccar.salamheyetimiz.com;

    ssl_certificate     /etc/ssl/cloudflare/salam-origin.pem;
    ssl_certificate_key /etc/ssl/cloudflare/salam-origin.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    client_max_body_size 25m;

    location / {
        proxy_pass http://127.0.0.1:8082;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
}
EOF

nginx -t
systemctl reload nginx
echo "[reloaded]"

echo "===== ORIGIN SNI VERIFICATION (bypassing Cloudflare via --resolve to 127.0.0.1) ====="
for h in salamheyetimiz.com www.salamheyetimiz.com api.salamheyetimiz.com admin.salamheyetimiz.com traccar.salamheyetimiz.com; do
  health=$(curl -sk -o /dev/null -w '%{http_code}' --resolve "$h:443:127.0.0.1" "https://$h/v1/health/live")
  title=$(curl -sk --resolve "$h:443:127.0.0.1" "https://$h/" | grep -oiE '<title>[^<]*</title>' | head -1)
  body=$(curl -sk --resolve "$h:443:127.0.0.1" "https://$h/v1/health/live" | head -c 40)
  printf "  %-32s health=%s  root=%s  body=%s\n" "$h" "$health" "${title:-<json/none>}" "$body"
done
echo "===== PHASE I DONE ====="
