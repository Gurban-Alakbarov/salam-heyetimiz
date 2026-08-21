#!/usr/bin/env bash
# Phase C2b + F1 — fix stale JWT_* env vars, install Nginx vhost (HTTP/80 origin), verify health.
set -euo pipefail
APP=/var/www/salam

echo "===== PHASE C2b + F1: env JWT fix + Nginx vhost ====="

echo "--- fix stale JWT_* env vars (config reads AUTH_JWT_*) ---"
sed -i '/^JWT_/d' "$APP/.env"
if ! grep -q '^AUTH_JWT_ISSUER=' "$APP/.env"; then
cat >> "$APP/.env" <<'EOF'

# ---- JWT (RS256) — keys on disk at storage/keys/jwt_{user,admin}_*.pem ----
AUTH_JWT_ISSUER=salam-hayetimiz
AUTH_JWT_USER_KID=user-2026-q2
AUTH_JWT_ADMIN_KID=admin-2026-q2
EOF
fi
chown www-data:www-data "$APP/.env"; chmod 640 "$APP/.env"

echo "--- nginx vhost (HTTP origin; HTTPS added in SSL phase) ---"
cat > /etc/nginx/sites-available/salam <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name salamheyetimiz.com www.salamheyetimiz.com 185.208.206.174;

    root /var/www/salam/public;
    index index.php;
    client_max_body_size 20m;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
EOF
ln -sf /etc/nginx/sites-available/salam /etc/nginx/sites-enabled/salam
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo "--- re-cache config after env change ---"
cd "$APP"
php artisan config:clear >/dev/null
php artisan config:cache
chown -R www-data:www-data "$APP/bootstrap/cache"

echo "===== VERIFICATION ====="
echo "[nginx] active=$(systemctl is-active nginx)"
echo "[health/live]";  curl -s -w "  (http=%{http_code})\n" -H 'Host: salamheyetimiz.com' -H 'Accept: application/json' http://127.0.0.1/v1/health/live
echo "[health/ready]"; curl -s -w "  (http=%{http_code})\n" -H 'Host: salamheyetimiz.com' -H 'Accept: application/json' http://127.0.0.1/v1/health/ready
echo "===== DONE ====="
