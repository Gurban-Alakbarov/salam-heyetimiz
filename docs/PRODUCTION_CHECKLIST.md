# Production Checklist — Salam Həyətimiz

**Server:** `185.208.206.174` · **Domain:** `salamheyetimiz.com` · **Updated:** 2026-06-21

Legend: ✅ done · ⏳ pending · 🔒 security · ⚠️ go-live blocker

---

## A. Infrastructure (✅ complete)

- [x] ✅ Ubuntu 24.04, timezone Asia/Baku, 4 GB swap (swappiness 10), sysctl tuning
- [x] ✅ Nginx 1.24 + PHP 8.4.22 FPM (opcache, tuned pool)
- [x] ✅ MariaDB 11.4.12 (bound 127.0.0.1, `innodb_buffer_pool=2560M`, `explicit_defaults_for_timestamp=1`)
- [x] ✅ Redis 7.0.15 (requirepass, `noeviction`, AOF)
- [x] ✅ App deployed `/var/www/salam`, `composer install --no-dev`, migrations + seed
- [x] ✅ JWT RS256 keys generated (`storage/keys/jwt_*`)
- [x] ✅ `health/live`=200, `health/ready`={database:true, redis:true}
- [x] ✅ Horizon (7 queues) + scheduler via systemd
- [x] ✅ Traccar 6.14.5 (Docker) + dedicated `traccar-db`; Wialon 5011; API token wired
- [x] ✅ Nightly backups (app + traccar DB + config), logrotate, docker log caps

## B. Network & TLS (✅ complete)

- [x] ✅ UFW: deny-default; 22 + 5011 open; 80/443 Cloudflare-only; localhost-only DB/Redis/Traccar-UI
- [x] ✅ Cloudflare DNS proxied (A apex, www CNAME, gps DNS-only)
- [x] ✅ Cloudflare Origin cert installed; Nginx HTTPS; HTTP→HTTPS redirect
- [x] ✅ SSL/TLS **Full (Strict)** verified (200 via edge, not 526)
- [x] ✅ Always Use HTTPS, Automatic HTTPS Rewrites, Min TLS 1.2
- [x] ✅ Cache bypass for `/v1/*` `/admin/*`; WAF managed ruleset; Bot Fight Mode

## C. Security hardening

- [x] ✅ fail2ban (sshd jail)
- [x] ✅ Secrets `600` root-only (`/root/salam_secrets.env`); `.env` 640; keys 600
- [x] ✅ Origin not directly reachable (UFW → Cloudflare IPs only on 443)
- [ ] 🔒⏳ **SSH key auth** — create `deployer`, install key, disable password + root login *(deferred by owner)*
- [ ] 🔒⏳ Rotate the initial root password
- [ ] 🔒⏳ Enable **HSTS** at Cloudflare (after a stable HTTPS period)
- [ ] 🔒⏳ Cloudflare **Authenticated Origin Pulls** (mTLS edge→origin) — optional hardening
- [ ] 🔒⏳ Restrict `/admin/*` and `/horizon` (Cloudflare Access / IP allowlist)

## D. Go-live blockers (⚠️ not infrastructure — require real credentials / hardware)

- [ ] ⚠️ **SMS provider** — `SMS_PROVIDER=fake`; real OTP/SMS not sent. Set `SMS_BASE_URL` + `SMS_API_KEY`, switch to real provider.
- [ ] ⚠️ **Kapital Bank** — `KAPITAL_*` empty; payments inert. Fill `KAPITAL_BASE_URL/MERCHANT_ID/HMAC_SECRET/IP_ALLOWLIST` (sandbox → prod).
- [ ] ⚠️ **Off-site backups** — local only today; add object storage / `restic`/`rclone` copy + monthly restore test.
- [ ] ⚠️ **HB1 hardware test** — prove Traccar `custom`→`OUTPUT0=1` actually fires the relay on a real UMKa 310 v2L (see `FINAL_PHASE0_VERDICT.md`). Until then the open command path is unvalidated on hardware.
- [ ] ⏳ **Reverb (WebSocket)** — deferred; `BROADCAST_CONNECTION=log`. Mobile uses polling fallback (R-ARCH-12). Add when real-time push is needed.

## E. Observability (⏳ recommended before scale)

- [ ] ⏳ External uptime monitor on `/v1/health/ready`
- [ ] ⏳ Metrics/alerting (CPU/RAM/disk, MariaDB, Redis, Horizon queue depth, Traccar sessions) — `INFRASTRUCTURE_REQUIREMENTS.md` §4
- [ ] ⏳ Alert on 5xx spike, queue backlog, disk >80%, device mass-offline

## F. Scale-out triggers (when device count grows — `SERVER_SIZING_GUIDE.md`)

- [ ] ≥ ~500 devices: move Traccar + `traccar-db` to a **dedicated host** (compose is portable)
- [ ] ≥ 1,000 devices: MariaDB read replica; Redis Sentinel HA (G5)
- [ ] ≥ 5,000 devices: app nodes ×2 behind LB; MariaDB bare-metal/managed

## G. Pre-launch smoke test (run before announcing)

1. `curl https://salamheyetimiz.com/v1/health/ready` → `{database:true,redis:true}`
2. OTP request/verify against the **real** SMS provider (after D-1)
3. A real Kapital sandbox order → paid → subscription activates (after D-2)
4. Register a real UMKa in Traccar → open command → relay fires (after D-4 / HB1)
5. Confirm a nightly backup landed off-site (after D-3)
6. Trigger a 4xx/5xx and confirm it surfaces in monitoring (after E)
