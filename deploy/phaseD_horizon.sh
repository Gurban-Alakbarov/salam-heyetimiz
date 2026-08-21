#!/usr/bin/env bash
# Phase D — Horizon worker (systemd) + Laravel scheduler (systemd timer).
set -euo pipefail
APP=/var/www/salam
PHP=/usr/bin/php8.4

echo "===== PHASE D: Horizon + scheduler ====="

echo "--- horizon systemd unit ---"
cat > /etc/systemd/system/salam-horizon.service <<EOF
[Unit]
Description=Laravel Horizon (salam)
After=network.target redis-server.service mariadb.service
Requires=redis-server.service

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=3
WorkingDirectory=$APP
ExecStart=$PHP $APP/artisan horizon
ExecStop=$PHP $APP/artisan horizon:terminate

[Install]
WantedBy=multi-user.target
EOF

echo "--- scheduler systemd service + timer (every minute) ---"
cat > /etc/systemd/system/salam-scheduler.service <<EOF
[Unit]
Description=Salam Laravel scheduler (schedule:run)
After=network.target

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=$APP
ExecStart=$PHP $APP/artisan schedule:run
EOF

cat > /etc/systemd/system/salam-scheduler.timer <<EOF
[Unit]
Description=Run Salam scheduler every minute

[Timer]
OnCalendar=*:0/1
AccuracySec=5s
Persistent=false

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now salam-horizon.service
systemctl enable --now salam-scheduler.timer
sleep 5

echo "===== VERIFICATION ====="
echo "[horizon service] active=$(systemctl is-active salam-horizon.service)"
echo "[horizon status]"; sudo -u www-data $PHP $APP/artisan horizon:status 2>/dev/null | sed 's/^/  /' || echo "  (no status)"
echo "[scheduler timer] active=$(systemctl is-active salam-scheduler.timer)"
systemctl list-timers salam-scheduler.timer --no-pager 2>/dev/null | sed -n '1,2p' | sed 's/^/  /'
echo "[horizon worker procs] count=$(pgrep -fc 'artisan horizon')"
echo "===== PHASE D DONE ====="
