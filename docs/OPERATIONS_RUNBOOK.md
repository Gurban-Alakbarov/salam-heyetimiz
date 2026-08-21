# Operations Runbook — Salam Həyətimiz Production

Day-2 operations for `185.208.206.174` / `salamheyetimiz.com`. See `SERVER_ACCESS.md` for paths/services.

---

## 1. Service control

```bash
# Web / app
systemctl reload nginx              # after vhost change (nginx -t first)
systemctl restart php8.4-fpm        # after PHP ini / opcache flush
systemctl restart salam-horizon     # restart queue workers (prefer horizon:terminate, see §3)

# Data
systemctl status mariadb redis-server
redis-cli -a "$(grep '^REDIS_PASSWORD=' /root/salam_secrets.env | cut -d= -f2)" ping

# Traccar
cd /opt/salam-traccar && docker compose ps
docker compose logs -f --tail=100 traccar
```

## 2. Deploy an application update

```bash
cd /var/www/salam
php artisan down --render="errors::503"        # optional maintenance mode
# --- upload new code (rsync from CI or pscp a tarball; preserve .env + storage/) ---
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan event:cache
chown -R www-data:www-data /var/www/salam
systemctl reload php8.4-fpm
php artisan horizon:terminate                  # Horizon restarts via systemd with new code
php artisan up
```

> Never overwrite `.env`, `storage/keys/`, or `storage/logs/` on deploy. `route:cache` requires all
> routes be controller-based (no closures). After `config:cache`, edits to `.env` need a re-cache.

## 3. Horizon (queues)

```bash
sudo -u www-data php /var/www/salam/artisan horizon:status     # "Horizon is running."
php /var/www/salam/artisan horizon:terminate                   # graceful restart (systemd respawns)
# Dashboard (auth-gated /horizon) — tunnel: ssh -L 8080:127.0.0.1:80 ... then https://salamheyetimiz.com/horizon
```
Queues: `high, default, device-comm, notifications, payments, reports, privacy`. `balance=auto` scales
workers with backlog. Failed jobs: Horizon dashboard → Failed, or `php artisan queue:retry all`.

## 4. Backups & restore

```bash
# Manual backup now
/usr/local/bin/salam-backup.sh
ls -lh /var/backups/salam            # salam_*.sql.gz, traccar_*.sql.gz, config_*.tgz (14-day retention)

# Restore app DB
gunzip -c /var/backups/salam/salam_<TS>.sql.gz | mariadb salam

# Restore Traccar DB
gunzip -c /var/backups/salam/traccar_<TS>.sql.gz | \
  docker exec -i traccar-db mariadb -utraccar -p"$(grep '^TRACCAR_DB_PASSWORD=' /root/salam_secrets.env | cut -d= -f2)" traccar
```
Nightly via `salam-backup.timer` at 03:00 Asia/Baku. **TODO (go-live):** copy `/var/backups/salam`
off-site (object storage / `restic` / `rclone`) — local-only today.

## 5. Traccar operations

```bash
cd /opt/salam-traccar
docker compose restart traccar            # restart server (keeps DB)
docker compose pull && docker compose up -d   # upgrade image (bump tag in compose first)
docker compose logs --tail=200 traccar    # diagnostics

# Register a device manually (UMKa uniqueId = IMEI):
TOKEN=$(grep '^TRACCAR_API_TOKEN=' /root/salam_secrets.env | cut -d= -f2)
curl -s -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -X POST http://127.0.0.1:8082/api/devices -d '{"name":"<serial>","uniqueId":"<IMEI>"}'
```
Device config: point the UMKa at `185.208.206.174:5011`, protocol **Wialon IPS**, `uniqueId=IMEI`.
The backend's `TraccarDeviceMapper` also registers devices via the API during provisioning.

## 6. Logs

| Source | Location |
|---|---|
| Laravel | `/var/www/salam/storage/logs/laravel.log` (logrotate daily, 14) |
| Nginx | `/var/log/nginx/{access,error}.log` |
| PHP-FPM | `journalctl -u php8.4-fpm` |
| Horizon | `journalctl -u salam-horizon` |
| MariaDB slow | `/var/log/mysql/slow.log` (>1 s) |
| Traccar | `docker compose logs traccar` |
| fail2ban | `fail2ban-client status sshd` |
| Auth/SSH | `journalctl -u ssh` |

## 7. Troubleshooting

| Symptom | Check |
|---|---|
| `502 Bad Gateway` | `systemctl status php8.4-fpm`; socket `/run/php/php8.4-fpm.sock`; `journalctl -u php8.4-fpm` |
| `health/ready` `database:false` | `systemctl status mariadb`; creds in `.env`; `mariadb -u salam_app -p... salam` |
| `health/ready` `redis:false` | `redis-cli -a <pw> ping`; `requirepass` in `/etc/redis/redis.conf` matches `.env` |
| Cloudflare **521/522** | origin down/unreachable — `systemctl status nginx`; UFW allows CF on 443 |
| Cloudflare **526** | origin cert invalid for Full(Strict) — check `/etc/ssl/cloudflare/*`, SAN, expiry |
| Jobs not processing | `systemctl status salam-horizon`; `redis-cli -a <pw> -n 1 llen queues:default` |
| Scheduler not firing | `systemctl list-timers salam-scheduler.timer`; `journalctl -u salam-scheduler` |
| Traccar forward not arriving | `docker compose logs traccar`; UFW allows `172.16.0.0/12`→80; token matches `.env` |
| Locked out by UFW | console access → `ufw disable` (port 22 is explicitly allowed, should not happen) |

## 8. Cloudflare cache / TLS

```bash
# Purge: CF dashboard → Caching → Purge Everything (API is dynamic/bypassed, rarely needed)
# Origin cert renewal (before 2041): regenerate in CF dashboard, replace /etc/ssl/cloudflare/*, nginx -t && reload
```

## 9. Health monitoring (set up — currently manual)

```bash
watch -n5 'curl -s https://salamheyetimiz.com/v1/health/ready'
free -h; df -h /; docker stats --no-stream
```
**TODO (go-live):** external uptime monitor on `/v1/health/ready`, alerting on 5xx / queue backlog /
disk >80% / Traccar session drop (see `INFRASTRUCTURE_REQUIREMENTS.md` §4).
