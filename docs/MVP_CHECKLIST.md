# MVP Checklist — Salam Həyətimiz

**Date:** 2026-06-21
**Companion:** `PRODUCTION_GAP_ANALYSIS.md` (findings) · `GO_LIVE_BLOCKERS.md` (severity + hours).
Legend: ✅ done · ⏳ pending · ⛔ blocked (external dependency) · IDs reference `GO_LIVE_BLOCKERS.md`.

---

## A. Infrastructure & platform
- [x] ✅ Ubuntu 24.04 VPS provisioned, hardened (swap, sysctl, timezone)
- [x] ✅ Nginx + PHP 8.4 FPM + MariaDB 11.4 + Redis 7 (native)
- [x] ✅ Horizon (7 queues) + scheduler via systemd
- [x] ✅ Cloudflare Full (Strict) TLS, Origin cert (valid to 2041)
- [x] ✅ UFW firewall (80/443 → Cloudflare only), fail2ban active
- [ ] ⏳ SSH hardening — non-root user + keys, disable root/password **(H5)**
- [ ] ⏳ HSTS enabled at Cloudflare **(L1)**
- [ ] ⏳ Cloudflare Access / IP allowlist for admin + Traccar panels **(M8)**

## B. Backend
- [x] ✅ App deployed, `APP_ENV=production`, `APP_DEBUG=false`, health green (db+redis)
- [x] ✅ JWT RS256 keys + JWKS; admin 2FA; 225 tests passing
- [x] ✅ Domains 00–09B (auth, devices, devicecomm, subscriptions, payments-code)
- [ ] ⏳ Roster module (sub-user invite/add/remove/list) **(H4)**
- [ ] ⏳ Notifications module (FCM push + templates) **(M1)**
- [ ] ⏳ Privacy module (GDPR export/delete/consent) **(M2)**
- [ ] ⏳ Admin operational endpoints (dashboard stats, users, admins, audit, settings) **(M3)**
- [ ] ⏳ Reverb real-time push (optional; polling fallback works) **(M5)**

## C. Admin UI
- [x] ✅ React SPA deployed at `admin.salamheyetimiz.com`, SSL, SPA refresh works
- [x] ✅ Super-admin account created; login (password + 2FA) verified
- [x] ✅ Devices (CRUD/lifecycle/diagnostics/commands/whitelist), Subscriptions, Orders/Refunds
- [ ] ⏳ In-UI admin management + dashboard counters + user management (await backend M3)

## D. SMS / OTP  🔴
- [ ] ⛔ Real AZ SMS provider account + creds **(external)**
- [ ] ⏳ Set `SMS_PROVIDER` (real), `SMS_BASE_URL`, `SMS_API_KEY`; swap off `fake` **(C1)**
- [ ] ⏳ Verify OTP request/verify delivers a real SMS (p95 target)
- [ ] ⏳ Verify SMS-fallback open path (`OUTPUT0=1` to device SIM)

## E. Payments (Kapital)  🔴
- [ ] ⛔ Kapital sandbox → production credentials **(external)**
- [ ] ⏳ Fill `KAPITAL_BASE_URL/MERCHANT_ID/HMAC_SECRET/IP_ALLOWLIST` **(C2)**
- [ ] ⏳ G3: full purchase + full refund + callback-storm dedupe against sandbox
- [ ] ⏳ Order → subscription activation verified
- [ ] ⏳ (fast-follow) Auto-renew card tokenization **(M4)**

## F. Device / open-gate workflow  🔴
- [ ] ⛔ 1× real UMKa 310 v2L + SIM, routed to Traccar (Wialon IPS :5011) **(external)**
- [ ] ⏳ HB1: Traccar `custom` → `OUTPUT0=1` fires the relay on real hardware **(C3)**
- [ ] ⏳ T3: actuation read-back (OUT0 bit → `opened`) confirmed
- [ ] ⏳ Register a real device (admin) → assign owner → open succeeds end-to-end
- [ ] ⏳ Decision recorded: Traccar-primary confirmed, or pivot (GLONASSSoft API / SMS-primary)

## G. Mobile app (Flutter)  🔴
- [ ] ⏳ Flutter app MVP: OTP login, device list, **open-gate (poll)**, subscriptions, payments **(C4)**
- [ ] ⏳ Build against deployed backend; launch gated on D+E+F being real
- [ ] ⏳ App store / staged rollout plan

## H. Backups & DR
- [x] ✅ Nightly DB + config backup (14-day local retention)
- [ ] ⏳ Off-site copy (object storage / restic / rclone) **(H1)**
- [ ] ⏳ Restore drill performed and documented **(H1/M7)**

## I. Logging & observability
- [x] ✅ Laravel/Nginx/Traccar logs + logrotate; in-DB audit log
- [ ] ⏳ Error tracking (Sentry) backend + frontend **(H3)**
- [ ] ⏳ Monitoring agent + dashboards (CPU/RAM/disk, MariaDB, Redis, Horizon depth, Traccar sessions) **(H2)**
- [ ] ⏳ Uptime check on `/v1/health/ready` + alerts (5xx, queue backlog, disk >80%, device mass-offline) **(H2)**

## J. Operations
- [x] ✅ Deployment scripts (`deploy/phase*.sh`) + runbook + access docs
- [ ] ⏳ CI/CD pipeline **(L2)**
- [ ] ⏳ Pre-launch smoke test run (OTP → pay → activate → register device → open → backup off-site)

---

## Go / No-Go gate

**Launchable when all 🔴 (D, E, F, G) pass plus H1 + H2 + H5.** Everything else (Roster, Notifications,
Privacy, Admin ops, Reverb, CI/CD) is fast-follow after a controlled soft launch.

**Current status: NO-GO** — 4 critical workstreams open (SMS, Kapital, HB1 hardware, mobile app), no
off-site backups, no monitoring. Infrastructure and the admin/back-office plane are ready; the
revenue + core-usage paths are not yet proven or built.
