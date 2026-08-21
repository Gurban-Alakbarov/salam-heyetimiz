#!/usr/bin/env bash
# Phase G — logrotate (Laravel), nightly DB+config backups (systemd timer), docker log caps.
# Non-disruptive: does NOT restart docker (daemon.json applies on next container recreate).
set -euo pipefail

echo "===== PHASE G: logrotate + backups ====="

echo "--- logrotate for Laravel logs ---"
cat > /etc/logrotate.d/salam <<'EOF'
/var/www/salam/storage/logs/*.log {
    daily
    rotate 14
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
    su www-data www-data
}
EOF

echo "--- docker log caps (applies to containers on next recreate) ---"
mkdir -p /etc/docker
cat > /etc/docker/daemon.json <<'EOF'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" }
}
EOF

echo "--- backup script ---"
mkdir -p /var/backups/salam && chmod 700 /var/backups/salam
cat > /usr/local/bin/salam-backup.sh <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
. /root/salam_secrets.env
TS=$(date +%F_%H%M)
DST=/var/backups/salam
mkdir -p "$DST"
mariadb-dump --single-transaction --routines --events salam | gzip > "$DST/salam_${TS}.sql.gz"
docker exec traccar-db mariadb-dump --single-transaction -utraccar -p"${TRACCAR_DB_PASSWORD}" traccar 2>/dev/null | gzip > "$DST/traccar_${TS}.sql.gz"
tar czf "$DST/config_${TS}.tgz" -C / root/salam_secrets.env var/www/salam/.env var/www/salam/storage/keys 2>/dev/null || true
find "$DST" -type f -mtime +14 -delete
EOF
chmod 700 /usr/local/bin/salam-backup.sh

echo "--- backup systemd timer (daily 03:00 Asia/Baku) ---"
cat > /etc/systemd/system/salam-backup.service <<'EOF'
[Unit]
Description=Salam nightly backup (app + traccar DB + config)
[Service]
Type=oneshot
ExecStart=/usr/local/bin/salam-backup.sh
EOF
cat > /etc/systemd/system/salam-backup.timer <<'EOF'
[Unit]
Description=Run Salam backup daily at 03:00
[Timer]
OnCalendar=*-*-* 03:00:00
Persistent=true
[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now salam-backup.timer

echo "--- run one backup now (verify) ---"
/usr/local/bin/salam-backup.sh

echo "===== VERIFICATION ====="
echo "[backups]"; ls -lh /var/backups/salam/ | sed 's/^/  /'
echo "[backup timer] active=$(systemctl is-active salam-backup.timer)"
systemctl list-timers salam-backup.timer --no-pager 2>/dev/null | sed -n '2p' | sed 's/^/  /'
echo "[logrotate validate]"; logrotate -d /etc/logrotate.d/salam 2>&1 | grep -iE 'rotating pattern|log needs|considering' | head -2 | sed 's/^/  /' || echo "  (config syntax ok)"
echo "===== PHASE G DONE ====="
