#!/usr/bin/env bash
# Phase C2 — deploy the Laravel app: code, .env, composer, keys, migrate, seed, caches, permissions.
set -euo pipefail
export COMPOSER_ALLOW_SUPERUSER=1
APP=/var/www/salam
. /root/salam_secrets.env

echo "===== PHASE C2: app deploy ====="

echo "--- extract code ---"
mkdir -p "$APP"
tar xzf /root/salam_src.tgz -C "$APP"

echo "--- storage + bootstrap/cache skeleton ---"
mkdir -p "$APP"/storage/app/public
mkdir -p "$APP"/storage/framework/cache/data
mkdir -p "$APP"/storage/framework/sessions
mkdir -p "$APP"/storage/framework/views
mkdir -p "$APP"/storage/logs
mkdir -p "$APP"/storage/keys
mkdir -p "$APP"/bootstrap/cache

echo "--- .env ---"
cat > "$APP/.env" <<EOF
APP_NAME="Salam Həyətimiz"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://salamheyetimiz.com
APP_TIMEZONE=UTC
APP_LOCALE=az
APP_FALLBACK_LOCALE=az
APP_FAKER_LOCALE=az_AZ
APP_SCHEDULE_TIMEZONE=Asia/Baku
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=salam
DB_USERNAME=salam_app
DB_PASSWORD=${DB_PASSWORD}
DB_READ_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379
REDIS_DB_CACHE=0
REDIS_DB_QUEUE=1
REDIS_DB_LOCKS=2
REDIS_DB_BROADCASTING=3
REDIS_DB_IDEMPOTENCY=4

CACHE_STORE=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=log
SESSION_DRIVER=database
SESSION_LIFETIME=120

JWT_MOBILE_KID=mobile-2026-q3
JWT_ADMIN_KID=admin-2026-q3
JWT_MOBILE_PRIVATE_KEY_PATH=storage/keys/jwt-mobile.pem
JWT_ADMIN_PRIVATE_KEY_PATH=storage/keys/jwt-admin.pem
JWT_ACCESS_TTL_MINUTES=15
JWT_ADMIN_ACCESS_TTL_MINUTES=30
JWT_REFRESH_TTL_DAYS=60

TRACCAR_DRIVER=http
TRACCAR_BASE_URL=http://127.0.0.1:8082
TRACCAR_API_TOKEN=
TRACCAR_FORWARD_TOKEN=${TRACCAR_FORWARD_TOKEN}
TRACCAR_OUTPUT_ATTRIBUTE=output
TRACCAR_ACTUATION_WINDOW=30
TRACCAR_TIMEOUT=10

KAPITAL_BASE_URL=
KAPITAL_MERCHANT_ID=
KAPITAL_HMAC_SECRET=
KAPITAL_IP_ALLOWLIST=

SMS_PROVIDER=fake
SMS_BASE_URL=
SMS_API_KEY=

FCM_PROJECT_ID=
FCM_CREDENTIALS_PATH=

MAIL_MAILER=log
EOF
chmod 640 "$APP/.env"

echo "--- composer install (no-dev) ---"
cd "$APP"
composer install --no-dev --optimize-autoloader --no-interaction

echo "--- app key + JWT RS256 keys ---"
php artisan key:generate --force
php artisan auth:generate-keys

echo "--- migrate + seed ---"
php artisan migrate --force
php artisan db:seed --force

echo "--- caches ---"
php artisan config:cache || echo "WARN: config:cache failed"
php artisan route:cache  || echo "WARN: route:cache failed"
php artisan event:cache  || true

echo "--- permissions (www-data) ---"
chown -R www-data:www-data "$APP"
find "$APP/storage" "$APP/bootstrap/cache" -type d -exec chmod 775 {} \;
find "$APP/storage" "$APP/bootstrap/cache" -type f -exec chmod 664 {} \;
chmod 700 "$APP/storage/keys"
chmod 600 "$APP"/storage/keys/*.pem 2>/dev/null || true

echo "===== VERIFICATION ====="
echo "[migrate status tail]"; sudo -u www-data php artisan migrate:status 2>/dev/null | tail -15
echo "[routes count]"; sudo -u www-data php artisan route:list 2>/dev/null | grep -cE '(GET|POST|PATCH|PUT|DELETE)' || echo 0
echo "[seed check]"; mariadb -u salam_app -p"${DB_PASSWORD}" -h 127.0.0.1 -N -e "SELECT CONCAT('sim_operators=',(SELECT COUNT(*) FROM sim_operators),' device_models=',(SELECT COUNT(*) FROM device_models),' regions=',(SELECT COUNT(*) FROM regions));" salam
echo "[jwt keys]"; ls -l "$APP"/storage/keys/
echo "[app env]"; sudo -u www-data php artisan tinker --execute="echo app()->environment().' debug='.(config('app.debug')?'1':'0');" 2>/dev/null || true
echo "===== PHASE C2 DONE ====="
