# Server Access — Salam Həyətimiz Production

> ⚠️ **Secrets policy:** This file documents *where* access lives, never the secret values.
> Passwords, DB credentials, and tokens are **not** stored in the repo. The owner holds the root
> password; all generated secrets live in `/root/salam_secrets.env` (mode `600`, root-only).

## 1. Server

| | |
|---|---|
| IP | `185.208.206.174` |
| Domain | `salamheyetimiz.com` (Cloudflare, proxied) |
| OS | Ubuntu 24.04.4 LTS |
| Provider | VPS (6 vCPU / 11 GiB / 96 GB) |

## 2. SSH

- **User:** `root` · **Auth:** password (held by the owner — **not in repo**).
- **Port:** 22 (fail2ban active; 5 fails → 1 h ban).
- **Host key (verify on first connect):**
  - ED25519 `SHA256:nLFofEE+RAtoOCttXx6qmy2wYAuI8hW9w7uSG7dQoCU`
  - RSA `SHA256:ZomS1PSn2OnCJD8sgujMP5TXgiA2KJ/vB8OKsW/dxmY`
  - ECDSA `SHA256:g8N2V7MP9KRBfMwcz2pr4Wzf+ibbWPcx5RrUsyDvGFM`

```bash
ssh root@185.208.206.174
# Windows (PuTTY): plink -ssh -hostkey "SHA256:nLFofEE+..." root@185.208.206.174
#                  pscp -hostkey "SHA256:nLFofEE+..." <file> root@185.208.206.174:<path>
```

> 🔒 **Recommended hardening (deferred by owner decision 2026-06-21):** create a non-root `deployer`
> sudo user, install an SSH key, then set `PasswordAuthentication no` + `PermitRootLogin prohibit-password`.
> Until then, root password auth is the only credential — rotate it periodically.

## 3. Filesystem map

| Path | Contents |
|---|---|
| `/var/www/salam` | Laravel app (owner `www-data`); `.env` (640), `storage/keys/jwt_*` (600) |
| `/opt/salam-traccar` | `docker-compose.yml`, `traccar.xml` (600) |
| `/root/salam_secrets.env` | DB / Redis / Traccar secrets (600) |
| `/etc/ssl/cloudflare/` | `salam-origin.pem` (644), `salam-origin.key` (600) |
| `/var/backups/salam` | nightly DB + config backups (700) |
| `/etc/nginx/sites-available/salam` | vhost (HTTP redirect + HTTPS) |
| `/root/phase*.sh` | uploaded deploy scripts (also versioned in repo `deploy/`) |

## 4. Services

| Service | Manage |
|---|---|
| Nginx | `systemctl {status,reload,restart} nginx` |
| PHP-FPM | `systemctl restart php8.4-fpm` |
| MariaDB (app) | `systemctl status mariadb` · client: `mariadb` (root via unix_socket) |
| Redis | `systemctl status redis-server` · client: `redis-cli -a <REDIS_PASSWORD>` |
| Horizon | `systemctl {status,restart} salam-horizon` |
| Scheduler | `systemctl list-timers salam-scheduler.timer` |
| Backups | `systemctl list-timers salam-backup.timer` · `/usr/local/bin/salam-backup.sh` |
| Traccar | `cd /opt/salam-traccar && docker compose {ps,logs,restart}` |
| fail2ban | `fail2ban-client status sshd` |
| UFW | `ufw status verbose` |

## 5. Application accounts / credentials (locations, not values)

| Secret | Where |
|---|---|
| App DB (`salam_app`) password | `/root/salam_secrets.env` → `DB_PASSWORD` (also in app `.env`) |
| Redis password | `/root/salam_secrets.env` → `REDIS_PASSWORD` |
| Traccar DB password | `/root/salam_secrets.env` → `TRACCAR_DB_PASSWORD` |
| Traccar admin (UI `admin@salamheyetimiz.com`) | `/root/salam_secrets.env` → `TRACCAR_ADMIN_PASSWORD` |
| Traccar API token | `/root/salam_secrets.env` + app `.env` → `TRACCAR_API_TOKEN` |
| Traccar forward webhook token | `/root/salam_secrets.env` → `TRACCAR_FORWARD_TOKEN` |
| JWT signing keys (RS256) | `/var/www/salam/storage/keys/jwt_{user,admin}_{private,public}.pem` |
| Laravel `APP_KEY` | app `.env` |

## 6. Cloudflare

- Account: owner-managed. Domain `salamheyetimiz.com` proxied.
- Origin certificate: CF dashboard → SSL/TLS → Origin Server (expires **2041-06-17**; renew before then).
- DNS, WAF, cache rules: see `DEPLOYMENT.md` §5.

## 7. Public endpoints

- App: `https://salamheyetimiz.com/v1/...`, admin `https://salamheyetimiz.com/admin/v1/...`
- Health: `https://salamheyetimiz.com/v1/health/{live,ready}`
- Traccar UI: **localhost only** (`http://127.0.0.1:8082`) — reach via SSH tunnel:
  `ssh -L 8082:127.0.0.1:8082 root@185.208.206.174` → `http://localhost:8082`
- Devices (Wialon IPS): `185.208.206.174:5011` (or `gps.salamheyetimiz.com:5011`, DNS-only).
