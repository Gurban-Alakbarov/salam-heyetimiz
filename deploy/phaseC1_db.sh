#!/usr/bin/env bash
# Phase C1 — databases, users, secrets, Redis auth. Idempotent (reuses existing secrets).
set -euo pipefail
SECRETS=/root/salam_secrets.env
gen(){ openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | cut -c1-24; }

echo "===== PHASE C1: databases + secrets ====="

# --- generate/reuse secrets ---
[ -f "$SECRETS" ] && . "$SECRETS"
DB_PASSWORD="${DB_PASSWORD:-$(gen)}"
TRACCAR_DB_PASSWORD="${TRACCAR_DB_PASSWORD:-$(gen)}"
REDIS_PASSWORD="${REDIS_PASSWORD:-$(gen)}"
TRACCAR_FORWARD_TOKEN="${TRACCAR_FORWARD_TOKEN:-$(openssl rand -hex 24)}"
TRACCAR_ADMIN_PASSWORD="${TRACCAR_ADMIN_PASSWORD:-$(gen)}"

cat > "$SECRETS" <<EOF
DB_PASSWORD=$DB_PASSWORD
TRACCAR_DB_PASSWORD=$TRACCAR_DB_PASSWORD
REDIS_PASSWORD=$REDIS_PASSWORD
TRACCAR_FORWARD_TOKEN=$TRACCAR_FORWARD_TOKEN
TRACCAR_ADMIN_PASSWORD=$TRACCAR_ADMIN_PASSWORD
EOF
chmod 600 "$SECRETS"

# --- MariaDB: secure + databases/users (root via unix_socket) ---
mariadb <<SQL
DELETE FROM mysql.global_priv WHERE User='';
DROP DATABASE IF EXISTS test;
CREATE DATABASE IF NOT EXISTS salam   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS traccar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'salam_app'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'salam_app'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON salam.* TO 'salam_app'@'127.0.0.1';
GRANT ALL PRIVILEGES ON salam.* TO 'salam_app'@'localhost';
CREATE USER IF NOT EXISTS 'traccar'@'127.0.0.1' IDENTIFIED BY '${TRACCAR_DB_PASSWORD}';
CREATE USER IF NOT EXISTS 'traccar'@'localhost' IDENTIFIED BY '${TRACCAR_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON traccar.* TO 'traccar'@'127.0.0.1';
GRANT ALL PRIVILEGES ON traccar.* TO 'traccar'@'localhost';
FLUSH PRIVILEGES;
SQL

# --- Redis auth ---
if grep -q '^requirepass ' /etc/redis/redis.conf; then
  sed -i "s|^requirepass .*|requirepass ${REDIS_PASSWORD}|" /etc/redis/redis.conf
else
  echo "requirepass ${REDIS_PASSWORD}" >> /etc/redis/redis.conf
fi
systemctl restart redis-server

echo "===== VERIFICATION ====="
echo "[bind 3306]"; ss -tlnH 'sport = :3306' | awk '{print $4}'
echo "[databases]"; mariadb -e "SHOW DATABASES;" | tr '\n' ' '; echo
echo "[app-user login]"; mariadb -u salam_app -p"${DB_PASSWORD}" -h 127.0.0.1 -e "SELECT 'salam_app OK' AS status;" salam
echo "[traccar-user login]"; mariadb -u traccar -p"${TRACCAR_DB_PASSWORD}" -h 127.0.0.1 -e "SELECT 'traccar OK' AS status;" traccar
echo "[redis no-auth blocked]"; redis-cli ping 2>&1 | head -1
echo "[redis auth ok]"; redis-cli -a "${REDIS_PASSWORD}" ping 2>/dev/null
echo "[secrets]"; ls -l "$SECRETS"
echo "===== PHASE C1 DONE ====="
