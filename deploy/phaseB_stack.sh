#!/usr/bin/env bash
# Phase B — install + tune the Laravel production stack. Additive; idempotent-ish.
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export NEEDRESTART_SUSPEND=1

echo "===== PHASE B: Laravel production stack ====="

echo "--- B1: repositories (ondrej/php 8.4 + MariaDB 11.4) ---"
add-apt-repository -y ppa:ondrej/php
curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup -o /tmp/mariadb_repo_setup
bash /tmp/mariadb_repo_setup --mariadb-server-version="mariadb-11.4"
apt-get update -y

echo "--- B2: PHP 8.4 + extensions ---"
apt-get install -y php8.4-fpm php8.4-cli php8.4-common php8.4-bcmath php8.4-mbstring \
  php8.4-intl php8.4-mysql php8.4-redis php8.4-gd php8.4-curl php8.4-zip php8.4-xml \
  php8.4-gmp php8.4-opcache php8.4-readline

echo "--- B3: Nginx ---"
apt-get install -y nginx

echo "--- B4: MariaDB 11.4 ---"
apt-get install -y mariadb-server mariadb-client

echo "--- B5: Redis 7 ---"
apt-get install -y redis-server

echo "--- B6: Composer (latest) ---"
php -r "copy('https://getcomposer.org/installer','/tmp/composer-setup.php');"
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php

echo "--- B7: MariaDB tuning ---"
cat > /etc/mysql/mariadb.conf.d/99-salam.cnf <<'EOF'
[mysqld]
innodb_buffer_pool_size = 2560M
innodb_log_file_size    = 512M
innodb_flush_log_at_trx_commit = 1
innodb_flush_method     = O_DIRECT
max_connections         = 150
explicit_defaults_for_timestamp = 1
character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci
slow_query_log          = 1
slow_query_log_file     = /var/log/mysql/slow.log
long_query_time         = 1
EOF
mkdir -p /var/log/mysql && chown mysql:mysql /var/log/mysql
systemctl restart mariadb

echo "--- B8: Redis tuning (perf/persistence; auth password set in Phase C) ---"
if ! grep -q 'salam overrides' /etc/redis/redis.conf; then
cat >> /etc/redis/redis.conf <<'EOF'

# ---- salam overrides ----
maxmemory 512mb
maxmemory-policy noeviction
appendonly yes
EOF
fi
systemctl enable redis-server
systemctl restart redis-server

echo "--- B9: PHP 8.4 opcache + FPM pool tuning ---"
cat > /etc/php/8.4/fpm/conf.d/99-salam.ini <<'EOF'
memory_limit = 256M
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 192
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
realpath_cache_size = 4096k
realpath_cache_ttl = 600
expose_php = Off
EOF
P=/etc/php/8.4/fpm/pool.d/www.conf
sed -i 's/^pm = .*/pm = dynamic/' "$P"
sed -i 's/^pm.max_children = .*/pm.max_children = 20/' "$P"
sed -i 's/^pm.start_servers = .*/pm.start_servers = 4/' "$P"
sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 3/' "$P"
sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 8/' "$P"
sed -i 's/^;pm.max_requests = .*/pm.max_requests = 500/' "$P"
systemctl enable php8.4-fpm
systemctl restart php8.4-fpm

echo "--- B10: enable + start services ---"
systemctl enable nginx mariadb
systemctl restart nginx

echo "===== VERIFICATION ====="
echo "[php]";      php -v | head -1
echo "  sodium=$(php -m | grep -ic '^sodium') pcntl=$(php -m | grep -ic '^pcntl') posix=$(php -m | grep -ic '^posix') redis=$(php -m | grep -ic '^redis') intl=$(php -m | grep -ic '^intl') gmp=$(php -m | grep -ic '^gmp')"
echo "[nginx]";    nginx -v 2>&1; echo "  active=$(systemctl is-active nginx)"
echo "[mariadb]";  mariadb --version; echo "  active=$(systemctl is-active mariadb)"
echo "[redis]";    redis-server --version | awk '{print $1,$2,$3}'; echo "  active=$(systemctl is-active redis-server) ping=$(redis-cli ping)"
echo "[composer]"; composer --version
echo "[mem]";      free -h | grep -E 'Mem|Swap'
echo "===== PHASE B DONE ====="
