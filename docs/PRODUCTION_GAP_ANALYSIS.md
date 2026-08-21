# Production Gap Analysis — Salam Həyətimiz

**Date:** 2026-06-21
**Method:** read-only audit of the live server (`185.208.206.174`) + the deployed codebase. No code changed.
**Scope:** 10 areas — Backend, Admin UI, Traccar, Database, Security, Backups, Logging, Monitoring, Device workflow, Payment workflow.
**Verdict:** Infrastructure is **production-grade and healthy**; the platform is **NOT go-live ready** — the three revenue/usage-critical integrations (SMS, Kapital, on-device Traccar open) are unproven/disabled, there is no end-user mobile app, and observability + off-site backups are missing. See `GO_LIVE_BLOCKERS.md` for the prioritized list and `MVP_CHECKLIST.md` for the launch checklist.

Legend: ✅ ready · ⚠️ partial / risk · ❌ missing / blocking

---

## 1. Backend

| Item | State | Evidence |
|---|---|---|
| Laravel 12 app (PHP 8.4.22) | ✅ live | `health/ready` = `{database:true, redis:true}`; `APP_ENV=production`, `APP_DEBUG=false` |
| Queue (Horizon, 7 queues) | ✅ running | `salam-horizon` active, "Horizon is running" |
| Scheduler | ✅ running | `salam-scheduler.timer` active (every minute) |
| Implemented domains (batches 00–09B) | ✅ | Catalog/lookups, Auth (OTP + admin 2FA + JWT/JWKS), Users (minimal), Devices (CRUD + lifecycle), DeviceComm (open-command core + Traccar/SMS transport), Subscriptions, Payments (orders/refunds/Kapital client). **225 automated tests passing** (local). |
| **Not-built domains (planned)** | ❌ | **Roster** (sub-user invite/add/remove/list, invitations), **Notifications** (FCM push + templates), **Privacy** (GDPR export/delete/consent flows), **Reporting**, **Admin extras** (dashboard stats, customer/admin mgmt, audit-log viewer, settings, feature flags, lookups CRUD), full **Profile** (`GET/PATCH /me`). |
| Real-time push (Reverb/WebSocket) | ⚠️ deferred | `BROADCAST_CONNECTION=log`; mobile relies on `GET /commands/{id}` polling (R-ARCH-12 fallback) |
| Mail | ⚠️ | `MAIL_MAILER=log` (no transactional email; product is SMS-centric, low impact) |

**Gap:** core control-plane is solid and tested; the customer-facing roster/notifications/privacy modules and most admin operational endpoints are not yet built.

## 2. Admin UI

| Item | State | Evidence |
|---|---|---|
| SPA deployed | ✅ | `https://admin.salamheyetimiz.com` → 200, React app, SSL valid, nested-route refresh works |
| Same-origin API + backend split | ✅ | `/admin/v1` → Laravel; `api.` stays the API |
| Super-admin account | ✅ | `admin_users` = 1 (super_admin, 2FA on); login flow verified end-to-end |
| Coverage | ⚠️ | Devices, Subscriptions (list), Orders/Refunds, Auth/Account implemented. **User Management, dashboard counters, subscription detail, admin-user management** not built (no backend endpoints) |
| Admin provisioning | ⚠️ | Only via `deploy/create_super_admin.php` (no in-UI admin management) |

## 3. Traccar integration

| Item | State | Evidence |
|---|---|---|
| Traccar 6.14.5 + dedicated DB (Docker) | ✅ healthy | both containers Up; UI on `traccar.salamheyetimiz.com` |
| Wialon device port 5011 | ✅ listening | confirmed in-container; UFW open |
| REST API + token wired to app | ✅ | `TRACCAR_DRIVER=http`, token set, `GET /api/devices` = 200 |
| Event-forward webhook | ✅ wired | container → `host.docker.internal/v1/traccar/forward` (token-gated) |
| **Registered devices** | ❌ **0** | `/api/devices` = `[]`; `traccar_devices` map = 0 |
| **HB1 — Traccar `custom`→`OUTPUT0=1` on a real UMKa 310** | ❌ **UNPROVEN** | no hardware ever connected; the core open-gate command path is untested end-to-end |
| Actuation read-back (T3 — OUT0 bit → `opened`) | ⚠️ unconfirmed | depends on real telemetry attribute key |
| Whitelist sync | ⚠️ by-design no-op | server-authorised model → `whitelistAdd/Remove` complete immediately (audit-only) |

**Gap:** the single most important product flow (remote/in-person gate open) has never executed against real hardware.

## 4. Database

| Item | State | Evidence |
|---|---|---|
| MariaDB 11.4.12 (app) | ✅ | bound `127.0.0.1`, tuned buffer pool, `explicit_defaults_for_timestamp=1` |
| Schema | ✅ | 33 migrations applied; partitioned `open_commands`/`device_diagnostics`/`payment_logs` |
| Lookup seed | ✅ | 3 SIM operators, 1 device model (UMKa 310 v2L), 9 regions |
| Traccar DB (separate) | ✅ | own container/db (51 tables) |
| **Business data** | — | 0 users / 0 orders / 0 subscriptions / 0 devices (pre-launch, expected) |
| Read replica | ❌ | single instance (acceptable at pilot scale; scale path documented) |
| Restore tested | ❌ | nightly dumps exist but a restore has never been verified |

## 5. Security

| Item | State | Evidence |
|---|---|---|
| UFW firewall | ✅ active | 27 rules; 80/443 → Cloudflare IPs only; 22 + 5011 open; DB/Redis/8082 localhost |
| Cloudflare Full (Strict) + Origin cert | ✅ | cert valid to 2041; `ssl_verify=0` |
| fail2ban | ✅ working | sshd jail; **5 IPs currently banned** (active brute-force attempts being blocked) |
| Secrets hygiene | ✅ | `/root/salam_secrets.env` 600 root; `.env` 640 www-data; JWT keys 600 |
| `APP_DEBUG=false` | ✅ | no debug leakage |
| **SSH hardening** | ⚠️ | `PermitRootLogin yes`, `PasswordAuthentication yes` — root+password still enabled (deferred by owner) |
| HSTS | ❌ | not enabled at Cloudflare |
| Admin/Traccar panel exposure | ⚠️ | publicly reachable (password/2FA-protected); no Cloudflare Access / IP allowlist |
| WAF / rate limiting | ✅ | Cloudflare managed rules + app-level limiters |
| Backend `auth:admin` on missing token | ⚠️ | returns 500 instead of 401 (edge case; SPA never triggers it) |

## 6. Backups

| Item | State | Evidence |
|---|---|---|
| Nightly DB + config backup | ✅ | `salam-backup.timer` active; app+traccar+config archives present |
| Retention | ✅ | 14 days local |
| **Off-site copy** | ❌ **NONE** | no rclone/restic/object-storage target — a server loss = total data loss |
| Restore drill | ❌ | never performed |
| Secrets/keys backup | ⚠️ | included in local config tarball only (not off-site) |

## 7. Logging

| Item | State | Evidence |
|---|---|---|
| Laravel logs + logrotate | ✅ | `storage/logs/laravel.log` (65K), daily rotate 14 |
| Nginx logs | ✅ | error.log small |
| Traccar logs | ✅ | Docker json-file capped (10m×3) |
| Audit log (in-DB) | ✅ by design | `audit_log` per spec |
| **Centralised / shipped logs** | ❌ | no log aggregation; logs live only on the box |
| **Error tracking (Sentry/Bugsnag)** | ❌ | no exception tracking for backend or frontend |

## 8. Monitoring

| Item | State | Evidence |
|---|---|---|
| Metrics agent (Prometheus/Netdata/etc.) | ❌ **NONE** | no monitoring service running |
| Uptime monitoring | ❌ | no external check on `/v1/health/ready` |
| Alerting | ❌ | nothing alerts on 5xx / queue backlog / disk / Traccar session drop / device mass-offline |
| Resource headroom | ✅ | disk 9% (8.6/96 GB), mem 1.9/11 GiB, swap 4 GB — comfortable |

**Gap:** the platform is fully blind in production — failures would go unnoticed until a user reports them.

## 9. Device workflow (end-to-end)

| Step | State |
|---|---|
| Admin registers device (`adminCreateDevice` / `techRegisterDevice`) | ✅ endpoint ready |
| Device ↔ Traccar mapping + registration | ✅ code ready (`TraccarDeviceMapper`) |
| Owner assignment on purchase (`OrderPaid` → assign) | ✅ code ready |
| **User opens gate (app → backend → Traccar → UMKa relay)** | ❌ **blocked** — no mobile app + HB1 unproven |
| SMS fallback open | ❌ blocked — `SMS_PROVIDER=fake` |
| Actuation confirmation (`dispatched`→`opened`) | ⚠️ unproven (needs real telemetry) |
| Sub-user (roster) access | ❌ Roster module not built |

## 10. Payment workflow (end-to-end)

| Step | State |
|---|---|
| Create order (`createOrder`) | ✅ endpoint ready |
| **Kapital redirect + charge** | ❌ **blocked** — `KAPITAL_BASE_URL/MERCHANT_ID/HMAC_SECRET` all EMPTY; gateway unproven (G3 gate) |
| Callback dedupe + verify (HMAC) | ✅ code ready (untested vs real bank) |
| Order → subscription activation | ✅ code ready |
| Refund (admin) | ✅ code ready (untested vs real bank) |
| Auto-renew (card tokenization) | ⚠️ P2-gated/disabled (depends on Kapital tokenization) |
| **OTP to even reach checkout** | ❌ blocked — `SMS_PROVIDER=fake` (no real OTP → no login → no purchase) |

---

## Cross-cutting summary

| Area | Ready | Risk / Missing |
|---|---|---|
| Infra (server, Nginx, SSL, UFW, services) | ✅ | SSH hardening pending |
| Backend control plane (auth, devices, subs, payments-code) | ✅ tested | feature modules (roster/notif/privacy/admin-ops) not built |
| Admin UI | ✅ deployed | partial coverage (backend-limited) |
| **SMS (OTP + fallback)** | ❌ | fake — **blocks all user login & SMS open** |
| **Kapital payments** | ❌ | no creds — **blocks all purchases** |
| **On-device Traccar open (HB1)** | ❌ | unproven — **blocks the core product** |
| **Mobile app (Flutter)** | ❌ | not started — **no end-user product** |
| Off-site backups | ❌ | local only |
| Monitoring / alerting / error tracking | ❌ | none |

Hour estimates and severity are in `GO_LIVE_BLOCKERS.md`.
