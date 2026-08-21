# RELEASE READINESS

> Pre-Flutter assessment. **Read-only.** Answers: is the backend ready for v1.0? Is it safe to start Flutter? What must happen before vs after Flutter?

---

## 1. Bottom line

- **Backend v1.0 readiness: YES, conditionally.** The implemented surface is functionally complete, secure, tested (344 passing tests / 1461 assertions), deployed, and prod-verified end-to-end. It is ready for a **v1.0 pilot/launch** once the go-live ops + the queue/N+1 hardening are in place.
- **Safe to start Flutter: YES — now.** The mobile contract Flutter will consume (auth/registration, bootstrap, current-user, orders, devices, subscriptions, open/commands) is **stable, unified, documented, and prod-verified**. There is exactly **one doc-fix prerequisite** (reconcile the OpenAPI spec, TD-1) so the client builds against real endpoints — not a backend code change.

---

## 2. What is production-ready (implemented + tested + prod-verified)

| Area | State |
|---|---|
| **Auth — registration (email-OTP)** | ✅ register / verify-email / resend-otp / login, unified envelope, atomic verify+issue, rate-limited, audited, prod E2E (10 scenarios) |
| **Auth — JWT + refresh** | ✅ RS256, rotation + reuse-detection, force-logout cutoff |
| **Auth — phone-OTP (legacy)** | ✅ frozen, additive coexistence |
| **Auth — admin login + 2FA** | ✅ password + TOTP/recovery, lockout |
| **Bootstrap** | ✅ guest `/v1/bootstrap` + authed `/v1/me` (extensible), prod-verified |
| **Payments (Kapital/BirPay)** | ✅ create → redirect → webhook (HMAC, dedup) → status-sync → refunds (full/partial) → idempotency → audit → retry → payment logs → stats; sandbox-verified |
| **Subscriptions** | ✅ list/show/renew/auto-renew, reminders |
| **Devices / Roster** | ✅ CRUD, ownership, transfer, decommission, roster add/remove, active-uniqueness |
| **Whitelist sync** | ✅ outbox + drain job (resilience items to verify — see §4) |
| **Barrier open** | ✅ command queue → driver (Traccar/SMS) → ACK/feedback state machine, rate-limit + cooldown, idempotency |
| **RBAC (admin)** | ✅ dynamic roles/permissions/overrides, complex scoping |
| **Settings v2** | ✅ grouped, encrypted secrets, config bridge, versions, import/export, live test actions |
| **Audit** | ✅ every state mutation auto-audited |
| **API docs** | ✅ OpenAPI 3.1 + Swagger + ReDoc + Postman + Bruno, admin-gated |
| **SMTP** | ✅ production-ready (Brevo, SPF/DKIM, verified delivery) |
| **Tests** | ✅ 344 passing |

---

## 3. MUST fix BEFORE Flutter (small, mostly doc)

| # | Action | Effort | Why before Flutter |
|---|---|---|---|
| 1 | **Reconcile the OpenAPI spec with the real route list** (TD-1) — prune or clearly mark the ~45 unimplemented design endpoints (filter `build-openapi.mjs` by `route:list`, or trim `v1.yaml`). | Small (doc/build only) | Flutter codes against Swagger; phantom endpoints cause wasted/broken work. |
| 2 | **Publish the mobile contract surface** as the authoritative list (it already exists in `docs/registration/FLUTTER_FLOW.md` + `API_REVIEW.md §1`). | Tiny | Gives the Flutter team the exact, stable endpoint set. |

> Note: these are **not backend code changes** — they make the documentation tell the truth. Flutter can start in parallel with #1.

---

## 4. SHOULD fix before GA / launch traffic (backend, can overlap Flutter)

1. **Queue OTP/registration email + SMS** (TD-2) — removes request-path blocking + outage fragility.
2. **Verify + harden DeviceComm resilience** (TD-6/7/8): orphan-command cleanup on decommission, failed-whitelist rescan for long offline, configurable open-command expiry. **These need code-level verification first** (the audit flagged them; one related "critical" was already disproven).
3. **Fix admin N+1** (TD-3) — admin panel scale.
4. **Lifecycle on restrictOnDelete chains** (TD-5) — user/device delete with children.
5. **Cheap security tidy** (TD-18 throttle test endpoints, TD-19 scope pre-filter).
6. **Align documented error codes** (TD-9) with controller behavior.
7. **Ops:** prod `QUEUE=redis` + Horizon healthy, Redis HA, partition-roll cron monitored, `APP_DEBUG=false`, secrets set, docs password rotated.

---

## 5. CAN wait until after Flutter (backend backlog)

- Cache bootstrap/`/me`/stats (TD-11), refund blob columns (TD-3/PERF-3).
- Drop deprecated column (TD-15), extract pagination trait (TD-14), add dormant-feature FKs (TD-16/17).
- Build the **unimplemented design features** as the product needs them: notifications (+ push), invitations, privacy/consents, admin user-management, reports, lookups, notification-templates. `/v1/me` was deliberately built **extensible** to absorb these without a contract change.
- SMS/BLE driver completion (TD-13) — only matters for the Traccar-down fallback.
- Card tokenisation key-rotation plan (TD-12) — before enabling save-card.

---

## 6. Risk register for go-live

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Flutter builds against phantom endpoints | High if spec not reconciled | Medium | Do TD-1 before Flutter (this doc's #1) |
| Auth slow / fails under load (inline SMTP/SMS) | Medium | Medium | Queue (TD-2) before launch traffic |
| Whitelist drift after long device offline | Medium | High (access) | **Verify** TD-7 retry/rescan; document `adminResyncWhitelist` runbook |
| Partition-roll cron failure | Low | High (writes) | Monitor + alert (TD-23) |
| Admin panel slow at scale | Medium | Low | Fix N+1 (TD-3) |

---

## 7. Recommendation

**Start Flutter now**, against the implemented mobile surface, after the one-step spec reconciliation (TD-1). Run the GA-hardening track (queue I/O, DeviceComm verification, N+1, ops) **in parallel** — none of it changes the mobile contract the app binds to. The backend is in strong shape; the remaining work is scale-hardening and DeviceComm resilience verification, not foundational rework.
