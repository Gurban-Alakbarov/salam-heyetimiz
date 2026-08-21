#!/usr/bin/env bash
# Phase J — serve the admin-ui React SPA at admin.salamheyetimiz.com.
# admin. now serves the SPA static build; /admin/v1 on that host is handed to the Laravel front
# controller (same-origin, no CORS). apex/www/api keep serving the Laravel app; traccar unchanged.
set -euo pipefail
echo "===== PHASE J: admin SPA vhost ====="

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

# ---- HTTPS :443 — Laravel app (apex + www + api) ----
server {
    listen 443 ssl http2 default_server;
    listen [::]:443 ssl http2 default_server;
    server_name salamheyetimiz.com www.salamheyetimiz.com api.salamheyetimiz.com;
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

# ---- HTTPS :443 — Admin SPA (React) at admin.salamheyetimiz.com ----
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name admin.salamheyetimiz.com;

    ssl_certificate     /etc/ssl/cloudflare/salam-origin.pem;
    ssl_certificate_key /etc/ssl/cloudflare/salam-origin.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    client_max_body_size 20m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    root /var/www/salam-admin/dist;
    index index.html;

    # API → Laravel front controller (same-origin; no CORS). All /admin/v1/* go to index.php.
    location ^~ /admin/v1 {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/salam/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/salam/public;
        fastcgi_param HTTPS on;
    }

    # Hashed, immutable build assets
    location /assets/ {
        access_log off;
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }

    # SPA fallback — React Router client routes resolve to index.html
    location / {
        try_files $uri $uri/ /index.html;
    }
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

echo "===== ORIGIN VERIFICATION (--resolve to 127.0.0.1, bypassing Cloudflare) ====="
echo "[admin SPA root]";   curl -sk --resolve admin.salamheyetimiz.com:443:127.0.0.1 https://admin.salamheyetimiz.com/ | grep -oiE '<title>[^<]*</title>|id="root"' | head -2 | sed 's/^/  /'
echo "[admin asset]";      curl -sk -o /dev/null -w '  /assets js http=%{http_code} ct=%{content_type}\n' --resolve admin.salamheyetimiz.com:443:127.0.0.1 https://admin.salamheyetimiz.com/assets/index-B8uSqbc_.js
echo "[admin API same-origin]"; curl -sk --resolve admin.salamheyetimiz.com:443:127.0.0.1 -X POST -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{}' -w '\n  (http=%{http_code})\n' https://admin.salamheyetimiz.com/admin/v1/auth/login | head -c 200
echo; echo "[nested route refresh -> index.html]"; curl -sk -o /dev/null -w '  /devices http=%{http_code}\n' --resolve admin.salamheyetimiz.com:443:127.0.0.1 https://admin.salamheyetimiz.com/devices
echo "[api host still Laravel]"; curl -sk --resolve api.salamheyetimiz.com:443:127.0.0.1 https://api.salamheyetimiz.com/v1/health/live -w ' (http=%{http_code})\n'
echo "===== PHASE J DONE ====="
