# Production Deployment — Salam Həyətimiz

**Date:** 2026-06-21
**Server:** `185.208.206.174` (Ubuntu 24.04.4 LTS, 6 vCPU / 11 GiB RAM / 96 GB SSD, AMD EPYC)
**Domain:** `salamheyetimiz.com` (Cloudflare, proxied)
**Status:** ✅ Live over HTTPS (Cloudflare Full Strict). Application + Traccar operational.
**Scope tier:** pilot / ≤ 500 stationary devices (single-box co-location per `SERVER_SIZING_GUIDE.md` Tier 1–2).

---

## 1. Architecture

```
                          ┌─────────────────── Cloudflare (proxy) ───────────────────┐
   Mobile app / Admin ───►│  Full(Strict) TLS · Always HTTPS · WAF · rate-limit       │
                          └───────────────────────────┬──────────────────────────────┘
                                                       │ 443 (UFW: Cloudflare IPs only)
   UMKa 310 devices ──(Wialon IPS, raw TCP :5011)──────┐  (gps.salamheyetimiz.com grey-cloud / direct IP)
                                                       │  │
   ┌──────────────────── VPS 185.208.206.174 (Ubuntu 24.04) ─────────────────────────────────────────────┐
   │   Nginx :80→:443  ──FastCGI──►  PHP 8.4 FPM (Laravel /var/www/salam/public)                          │
   │        │                              │                                                              │
   │   Cloudflare Origin cert         Horizon (systemd, 7 queues) ── Redis 7 :6379 (auth, localhost)      │
   │   (/etc/ssl/cloudflare)          Scheduler (systemd timer, 1/min)                                    │
   │                                       │                                                              │
   │   Traccar 6.14.5 (Docker) ──REST──────┘    MariaDB 11.4 :3306 (localhost) — DB: salam                │
   │     :8082 UI (localhost)                                                                             │
   │     :5011 Wialon (public)   ── position forward → http://host.docker.internal/v1/traccar/forward     │
   │     traccar-db (Docker MariaDB) — DB: traccar (separate)                                             │
   │                                                                                                      │
   │   SMS provider ◄──(fallback open)── Laravel   ·   fail2ban · UFW · swap 4G · nightly backup          │
   └──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

## 2. Components & versions

| Component | Version | How it runs | Config |
|---|---|---|---|
| OS | Ubuntu 24.04.4 LTS | — | tz Asia/Baku, swap 4G |
| Nginx | 1.24.0 | systemd `nginx` | `/etc/nginx/sites-available/salam` |
| PHP-FPM | 8.4.22 | systemd `php8.4-fpm` | `/etc/php/8.4/fpm/` (pool www, `99-salam.ini`) |
| Laravel app | (this repo) | PHP-FPM | `/var/www/salam`, `.env` |
| MariaDB (app) | 11.4.12 | systemd `mariadb` | `/etc/mysql/mariadb.conf.d/99-salam.cnf` (bind 127.0.0.1) |
| Redis | 7.0.15 | systemd `redis-server` | `/etc/redis/redis.conf` (requirepass, noeviction, AOF) |
| Horizon | laravel/horizon | systemd `salam-horizon` | `config/horizon.php` (env `production`) |
| Scheduler | `schedule:run` | systemd `salam-scheduler.timer` | `routes/console.php` |
| Traccar | 6.14.5 | Docker compose | `/opt/salam-traccar/docker-compose.yml` + `traccar.xml` |
| Traccar DB | mariadb 11.4 | Docker (`traccar-db`) | separate DB `traccar` |
| Docker | CE 29.6 | systemd `docker` | log caps `/etc/docker/daemon.json` |
| fail2ban | 1.0.2 | systemd `fail2ban` | `sshd` jail |

## 3. Resource allocation (single box, 11 GiB)

| Component | RAM cap | Key setting |
|---|---|---|
| MariaDB (app) | ~3.0 GiB | `innodb_buffer_pool_size=2560M`, `flush_log_at_trx_commit=1` |
| PHP-FPM | ~1.2 GiB | `pm=dynamic`, `pm.max_children=20` |
| Horizon | ~0.7 GiB idle (peak more) | `balance=auto` (scales only under load) |
| Redis | ~0.5 GiB | `maxmemory 512mb`, `noeviction`, AOF |
| Traccar JVM | ~1.0 GiB | `_JAVA_OPTIONS=-Xms512m -Xmx1024m`, `mem_limit 1536m` |
| traccar-db | ~0.4 GiB | `innodb-buffer-pool-size=256M` |
| OS + Nginx | ~1.0 GiB | swap 4G (swappiness 10) safety net |
| **Peak** | **~8 GiB / 11 GiB** | ~3 GiB headroom |

## 4. Network / security posture (UFW)

| Port | Exposure | Purpose |
|---|---|---|
| 22/tcp | public (fail2ban) | SSH (root + password — hardening deferred) |
| 80/tcp | Cloudflare IPs + Docker bridge | HTTP→HTTPS redirect; internal Traccar webhook (172.16/12 only) |
| 443/tcp | Cloudflare IPs only | HTTPS origin (Cloudflare Origin cert) |
| 5011/tcp | public | Traccar Wialon IPS (devices) |
| 8082, 3306, 6379 | localhost only | Traccar UI, MariaDB, Redis |

`UFW: default deny incoming / allow outgoing`, `DEFAULT_FORWARD_POLICY=ACCEPT` (Docker). Cloudflare IP ranges fetched from `cloudflare.com/ips-v4|v6` (22 rules).

## 5. Cloudflare configuration

- **DNS:** A `salamheyetimiz.com` → `185.208.206.174` (proxied); CNAME `www` → apex (proxied); A `gps` → IP (DNS-only, devices).
- **SSL/TLS:** Full (Strict); Origin cert (CF Origin CA, SAN `salamheyetimiz.com` + `*`, expires 2041-06-17) at `/etc/ssl/cloudflare/salam-origin.{pem,key}`.
- **Always Use HTTPS:** On · **Automatic HTTPS Rewrites:** On · **Min TLS 1.2**.
- **Caching:** `/v1/*` and `/admin/*` → Bypass (dynamic API).
- **Security:** Bot Fight Mode, WAF Managed Ruleset, auth-endpoint rate limit; origin reachable only via Cloudflare (UFW).

## 6. Deploy automation

All steps are reproducible scripts in [`deploy/`](../deploy) (uploaded via `pscp`, CRLF-stripped, run via `plink`):

| Script | Phase |
|---|---|
| `phaseA_base.sh` | apt, swap, timezone, sysctl, fail2ban |
| `phaseB_stack.sh` | PHP 8.4, Nginx, MariaDB 11.4, Redis 7, Composer + tuning |
| `phaseC1_db.sh` | databases, users, secrets, Redis auth |
| `phaseC2_app.sh` | code, .env, composer, keys, migrate, seed, caches |
| `phaseF1_web.sh` | env JWT fix, Nginx vhost, health |
| `phaseD_horizon.sh` | Horizon + scheduler systemd |
| `phaseE_traccar.sh` / `phaseE2c_token.sh` | Traccar Docker + API token + Wialon port |
| `phaseG_backup_logrotate.sh` | backups + logrotate + docker log caps |
| `phaseH_ufw.sh` | UFW firewall |
| `phaseH_ssl.sh` | Cloudflare Origin cert + Nginx HTTPS |

## 7. Verification (2026-06-21)

- `https://salamheyetimiz.com/v1/health/live` → 200 `{"status":"ok"}` (Server: cloudflare, CF-RAY).
- `https://salamheyetimiz.com/v1/health/ready` → 200 `{"database":true,"redis":true}`.
- HTTP→HTTPS 301; www→apex 200; Full(Strict) validated (no 526).
- Migrations + seed applied (3 SIM operators, 1 device model = UMKa 310 v2L, 9 regions).
- Horizon running (7 supervisors); scheduler firing every minute; nightly backup verified.
- Traccar 6.14.5 + traccar-db healthy; Wialon 5011 listening; API token wired (`GET /api/devices` → 200).

## 8. Pending (not infrastructure — see `PRODUCTION_CHECKLIST.md`)

SMS provider creds (currently `fake`), Kapital creds (empty), off-site backup target, HB1 on-device Traccar→`OUTPUT0` test, SSH key hardening, monitoring/alerting, HSTS.
