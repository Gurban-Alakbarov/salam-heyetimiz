# Salam Həyətimiz — Pre-Development Engineering Audit

**Auditor:** Principal Software Architect
**Date:** 2026-06-09
**Documents reviewed:**
- [TECHNICAL_SPECIFICATION.md](TECHNICAL_SPECIFICATION.md) v1.0
- [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md) v1.0
- [BACKEND_ARCHITECTURE.md](BACKEND_ARCHITECTURE.md) v1.0
- [UI_UX_SPECIFICATION.md](UI_UX_SPECIFICATION.md) v1.0
- [openapi/v1.yaml](openapi/v1.yaml) v1.0.0

**Verdict:** **Conditional go.** The plan is internally consistent and production-grade in shape, but contains **9 Critical** and **18 High** items that should be resolved or actively mitigated before Phase 1 starts. The Critical items cluster around the GSM communication layer, the subscription business model, and operational HA — exactly where the cost of being wrong rises fastest.

---

## 0. Executive Summary

### 0.1 The five things I would change first

1. **Prove CLIP works on AZ carriers in Phase 0 before committing any production code.** Caller-ID rewriting by carriers can silently break the whole "open" path. (`CRIT-01`)
2. **Re-examine the per-(user, device) subscription model with product.** It is self-consistent but produces several user-hostile edge cases (owner can't open while invitee can; owner pays twice; refund of owner sub doesn't affect invitee subs). At minimum, document the *intended* behaviour for support; ideally, simplify. (`CRIT-02`)
3. **Treat the GSM gateway as a separately-deployed component with HA, not a library inside the API.** A single modem rack is a single point of failure for the whole product's headline feature. (`CRIT-03`)
4. **Define key-rotation runbooks** for: JWT signing keys, mobile certificate pins, Kapital HMAC secret, application encryption key. Each is a foot-gun that only fires once. (`CRIT-04`)
5. **Make Redis highly available** (Sentinel or managed cluster) before relying on it for queue + cache + locks + idempotency. The current single-node plan turns Redis into a system-wide SPOF. (`CRIT-05`)

### 0.2 Cross-cutting themes

| Theme | What I'm seeing |
|---|---|
| **Hardware optimism** | Several design choices assume the GSM controllers behave better than they typically do: that CLIP works reliably end-to-end; that opens have a confirmation signal; that the whitelist survives power loss; that the SIM has data/credit. Each needs vendor + carrier validation, not assumption. |
| **Business model complexity leaks into UX** | The per-(user, device) sub model is a clean data model but produces multi-payer, multi-state situations the UX hasn't fully reconciled. Several screen specs describe "the happy path" without acknowledging mixed-state cases. |
| **Single-points-of-failure understated** | Modem cluster, Redis, voice gateway, Reverb WebSocket node — each is described in singular form. The doc set says "horizontally scalable" but the topology in §18 has no HA path for any of these. |
| **Operational concerns deferred** | SIM-credit alerting, firmware updates, capacity enforcement, refund pro-rata math, TOTP recovery, JWT key rotation — all listed but unowned. Many will surface as P0 incidents in month 2 if not designed in. |
| **Expensive-to-change choices made implicitly** | Single tenant, AZN-only, phone-only identity, custom JWT pipeline, Reverb. None are wrong; each is a hard-to-undo bet. They deserve an explicit decision record, not a default. |

### 0.3 What's *right* and shouldn't be re-litigated

- Modular monolith with per-domain `ModuleServiceProvider` — appropriate for team size and stage.
- Hosted-page Kapital integration — keeps PCI scope minimal.
- Per-event audit via single generic listener with `auditAction()`/`auditPayload()` on events — clean and easily extended.
- Two-tier idempotency (Redis + DB durable) with explicit replay/mismatch semantics — better than what most projects ship.
- Partitioning of `open_commands` / `audit_log` / `payment_logs` from day 1 — saves a migration emergency later.
- `payment_callbacks` durable idempotency table — defends against an at-least-once webhook delivery.
- Driver abstraction in DeviceComm — the bet on pluggability has the right shape.

---

## 1. Findings — Critical

These items are blockers or near-blockers. Don't start Phase 1 without a written decision on each.

---

### CRIT-01 — CLIP Caller-ID may not survive AZ carriers; the open path is unproven

- **Category:** GSM communication
- **Severity:** **Critical**
- **Where:** `TECHNICAL_SPECIFICATION.md` §12.6, `BACKEND_ARCHITECTURE.md` §14.6
- **Explanation:** The plan supports CLIP (caller-ID-based open) as a first-class driver. CLIP only works if the *originating CLI* presented to the device's SIM matches a number the device has whitelisted. On real Azerbaijani carrier interconnects, **Caller-ID is routinely rewritten, prefixed, or substituted** when calls traverse VoIP/PSTN boundaries. Without empirical proof for each of {Azercell, Bakcell, Nar}, the entire CLIP path can fail silently in production while sandbox tests pass.
- **Recommendation:**
  1. Phase 0 acceptance gate: a single Laravel command that places a CLIP call to one real device per operator and observes the gate move. Run it on the actual telephony stack you will deploy (SIP trunk + termination route, or a real GSM modem). Repeat across all three operators.
  2. Document the CLI-presentation behaviour per operator + termination provider as a versioned artefact.
  3. If any operator strips CLI, default to GSM-modem-cluster termination for that operator (real SIM-to-SIM call) and abandon SIP termination for that route.
  4. If CLI cannot be guaranteed at all, drop CLIP from MVP and ship SMS-only, accepting the latency hit.
- **Impact if ignored:** Opens silently fail for some users; support burden explodes; root cause is invisible from the application logs because the device just sees a "wrong number" and hangs up. This kind of failure can take weeks to diagnose post-launch.

---

### CRIT-02 — Per-(user, device) subscription model produces user-hostile edge cases

- **Category:** Business logic
- **Severity:** **Critical**
- **Where:** `TECHNICAL_SPECIFICATION.md` §13, `DATABASE_ARCHITECTURE.md` §4
- **Explanation:** The subscription is associated with `(user, device)` via `device_user_id`. This is a clean data model but it produces specific situations that the UX has not designed for:
  1. **Mixed-state device**: Owner sub is expired; one invitee sub is active. The device is *open-capable* for the invitee but the owner — the *paying customer of record* — cannot open it. Owner sees "Suspended" on Home while invitee sees "Active."
  2. **Owner pays twice** for self-and-additional-user scenarios: 12 AZN for own main sub plus 6 AZN for each invitee. There is no "household plan" semantics. This is a deliberate product decision but the UX (§7.5 S-16) does not call it out clearly enough.
  3. **Refund of owner's main sub leaves the device open for additional users.** This is correct by the model but may surprise the support team and the refunded customer ("I refunded but the device still opens").
  4. **Hand-off on owner self-deletion**: if owner soft-deletes while invitees have active subs, there is no defined transfer of ownership. Anonymisation in 30 days erases the owner-of-record without a successor.
- **Recommendation:**
  - **Document all four cases** in `TECHNICAL_SPECIFICATION.md` §13 with explicit expected behaviour and corresponding UX.
  - **Add a UX state** to `S-09 Device Detail` for "device active for others but not for me — your subscription is expired" (this is different from device-wide suspended).
  - **Add a policy** for owner-self-delete-while-others-active: either block the self-delete (with a clear "transfer ownership first" CTA) or auto-promote the earliest invitee.
  - **Strongly consider** simplifying to **per-device sub paid by owner** for MVP, with additional users free up to capacity (matching how garages, intercoms, and gated-community products typically work in the region). This would simplify billing, UX, support, and the database. Validate with product before locking in.
- **Impact if ignored:** Predictable customer-support cases that don't have good answers. Refund disputes. Support burden. UX confusion turning into churn.

---

### CRIT-03 — GSM gateway is a single point of failure for the headline feature

- **Category:** Scalability / availability
- **Severity:** **Critical**
- **Where:** `TECHNICAL_SPECIFICATION.md` §12.6, §18.2
- **Explanation:** The deployment topology shows "1× GSM-modem gateway (on-prem or DC)" with no redundancy. This single appliance handles *every* open command. If the modem rack reboots, loses uplink, or its SIMs are deprovisioned by the operator, **all opens stop platform-wide**. A 32-port modem also caps throughput at ~32 concurrent calls regardless of how much Laravel scaling you do — and the NFR §3.1 calls for 50 RPS sustained opens.
- **Recommendation:**
  1. **Deploy two modem gateways from day 1**, ideally in different facilities, with SIMs from at least two operators across them. The driver bus must support per-call gateway selection (round-robin with health-aware exclusion).
  2. **Make the gateway addressable as a network service** (gRPC or HTTP between Laravel and gateway), not a library in-process. This lets you replace, restart, and scale the gateway independently of the API.
  3. **Capacity-plan against the throughput requirement**: 50 RPS sustained × 4 s avg call time = 200 concurrent ports needed. Two 32-port appliances give 64 ports. **The current sizing meets ~25% of the stated NFR.** Either revise the NFR or add capacity.
  4. **Define a circuit-breaker** at the driver layer so a gateway outage degrades gracefully (e.g. switch all opens to SMS) instead of queuing forever.
- **Impact if ignored:** Single hardware fault = total open outage. Customer-facing SLA broken on day one of any real incident.

---

### CRIT-04 — Key rotation procedures are undefined for several critical secrets

- **Category:** Security / operations
- **Severity:** **Critical**
- **Where:** `BACKEND_ARCHITECTURE.md` §7.1, `TECHNICAL_SPECIFICATION.md` §15.5
- **Explanation:** Several long-lived secrets are introduced without a documented rotation path:
  - **JWT signing keys** (RS256, separate for mobile and admin) — no JWKS endpoint, no key-id (`kid`) in tokens, no grace-period overlap mechanism documented. Rotating today means logging out everyone.
  - **Mobile certificate pins** — pinned in the app binary. If the cert rotates before an app release ships with the new pin, **every installed app stops working on every endpoint**.
  - **Application encryption key** (`app.key`) — used for app-layer encryption of `card_tokens.bank_token`, `admin_users.totp_secret`, etc. Rotation needs a re-encryption migration.
  - **Kapital HMAC secret** — rotation requires coordination with the bank.
  - **SMS / Voice provider API keys** — no documented rotation cadence.
- **Recommendation:**
  - **Add `kid` to JWTs** and serve a small JWKS document at `/.well-known/jwks.json` (admin domain only — mobile pins). Run two valid keys during rotation; expire the old one after 1 × max(refresh_ttl).
  - **Pin two certificates** in the mobile app (primary + backup). Rotate by deploying a new app version pinning the *new primary + a new backup*; old primary remains valid until the previous app version is forced to update.
  - **Document the `app.key` rotation runbook** as a Phase 1 deliverable. Use Laravel's `APP_PREVIOUS_KEYS` mechanism with a one-off migration job re-encrypting affected columns.
  - **Establish quarterly rotation** for HMAC / API keys; capture each rotation in `audit_log` as `secret.rotated`.
- **Impact if ignored:** A rotation that should be routine becomes an emergency with user-visible downtime, or — worse — a leaked key cannot be revoked without breaking everyone.

---

### CRIT-05 — Redis is a single-instance SPOF for queue + cache + locks + idempotency

- **Category:** Scalability / availability
- **Severity:** **Critical**
- **Where:** `TECHNICAL_SPECIFICATION.md` §18.3, `BACKEND_ARCHITECTURE.md` §11
- **Explanation:** The deployment topology lists "1× Redis" carrying Horizon queues, application cache, locks (cooldowns, whitelist drain serialisation, scheduler one-server guards), and the hot-cache half of idempotency. If Redis goes down or OOMs, the entire platform stops accepting new commands. There is no replica, no Sentinel, no plan documented.
- **Recommendation:**
  - **Run Redis with Sentinel** (one primary + two replicas + three sentinels), or use a managed Redis with built-in HA.
  - **Separate concerns onto separate logical databases** at minimum: `0=cache`, `1=queue`, `2=locks`, `3=broadcasting`. Easier to reason about memory pressure and to migrate individual concerns later.
  - **Add a connection-level circuit breaker**: if Redis is unhealthy, the API should reject opens cleanly (`503`) instead of hanging.
  - **Document Redis memory budget**: cache TTLs are explicit but no `maxmemory-policy` is set. Use `allkeys-lru` for the cache DB only; never `noeviction` on a DB that holds queue or locks.
- **Impact if ignored:** Single Redis fault = total open outage + cooldown bypass + idempotency loss (double payments possible) + scheduler clashes.

---

### CRIT-06 — "Opened" status is fiction for CLIP without a device feedback channel

- **Category:** GSM communication / UX
- **Severity:** **Critical**
- **Where:** `UI_UX_SPECIFICATION.md` S-12, `TECHNICAL_SPECIFICATION.md` §12
- **Explanation:** The UX promises a terminal state "Opened" with a green check, success haptic, and toast "Açıldı". For **CLIP**, the backend only knows that it *placed a call* and the device *rejected* (or accepted) it — it has no observable signal that the gate physically moved. The system will report "Opened" when in fact the gate may not have moved (relay failure, mechanical jam, no power). Users will lose trust quickly because the app's claim disagrees with reality.
- **Recommendation:**
  - **Either** rename the terminal state to "Dispatched" / "Göndərildi" for CLIP (honest about what we know), with a softer visual treatment.
  - **Or** require devices that have a feedback signal (e.g. a status SMS after open, or a dry-contact sensor reading) before claiming "Opened." If using GSM controllers with delivery-report capability over SMS, use **only the hybrid driver** for opens-with-confirmation; demote CLIP to "best effort."
  - **Add a "Did it open?" inline feedback prompt** after the action (Yes / No) for the first month of usage to build a per-device reliability metric, and surface that to support.
- **Impact if ignored:** Support tickets that engineering cannot resolve from logs alone. Eroded trust. Possible regulatory complaint if access control fails in safety-relevant situations (e.g. emergency egress).

---

### CRIT-07 — Bank callback verification has at least three production foot-guns

- **Category:** Payment processing / security
- **Severity:** **Critical**
- **Where:** `TECHNICAL_SPECIFICATION.md` §14.3, `openapi/v1.yaml` `/payments/callback`
- **Explanation:** The callback flow does three things right (HMAC signature + IP allowlist + `getOrderStatus` cross-check) and has three things that bite in practice:
  1. **HMAC over canonical body.** Any proxy hop (Cloudflare, Nginx) that re-encodes, gzips, or normalises the body invalidates the signature. The spec doesn't designate which layer captures the raw bytes.
  2. **IP allowlist** — banks rotate egress IPs without notice. If the allowlist isn't owned by someone (with monitoring), callbacks start silently 401-ing on a Wednesday.
  3. **`status=PENDING` callbacks** — the schema enumerates PENDING but our flow assumes terminal. If we treat PENDING as APPROVED or DECLINED we corrupt state.
- **Recommendation:**
  - **Capture raw request body** in custom middleware (`VerifyKapitalSignature` reads from a stashed `php://input` snapshot, not the parsed JSON). Document that Nginx must `proxy_request_buffering on;` and not modify the body.
  - **Monitor IP allowlist drift**: log `signature_valid=0` callbacks separately; alert if the source IP is not in the allowlist *or* if it's in the allowlist but signature fails (different root causes).
  - **Treat PENDING explicitly**: do not change order status; trigger a `RecheckOrderStatusJob` with backoff.
  - **Use `getOrderStatus` as authority** — even if signature passes, never change order status without `getOrderStatus` returning the same status independently. This is in the spec but ensure the implementation enforces it.
- **Impact if ignored:** Either revenue loss (genuine callbacks rejected as invalid) or — worse — accepting forged callbacks because the signature was bypassed by a middleware bug. Both are silent until discovered by reconciliation.

---

### CRIT-08 — Mobile cert-pinning rotation is undefined; one bad rotation locks out the field

- **Category:** Security / operations
- **Severity:** **Critical**
- **Where:** `TECHNICAL_SPECIFICATION.md` §15.8
- **Explanation:** Mobile cert-pinning is an excellent security control but, in practice, the failure mode is brutal: when the pinned cert expires and the deployed app version doesn't have the new pin, **the app cannot reach the backend** and the user has no path to fix it. This becomes a "force-update from the store" event under time pressure — and Apple review can take 24 h. Even with backup pins, mismanagement is the most common cert-pinning incident.
- **Recommendation:**
  - **Pin to public key, not certificate.** Public key survives cert renewal as long as the same CSR is used. Reduces rotation cadence from 1 × cert_validity to roughly never.
  - **Pin two keys** (primary + backup). Rotate by deploying an app with new-primary + new-backup; only after that release reaches > 95 % of installs do you actually rotate the cert.
  - **Backend monitors connection metrics by app version**. A spike of pinning failures must page on-call.
  - **Have an unpinned escape hatch** wired through a separate hostname that is *only* enabled by a remote flag, used as a last resort to recover stranded installs.
- **Impact if ignored:** A predictable annual cert renewal turns into an all-hands incident with users locked out. Reputation damage compounds.

---

### CRIT-09 — TOTP-only admin 2FA with no recovery code = predictable admin lockout

- **Category:** Security / operations
- **Severity:** **Critical**
- **Where:** `TECHNICAL_SPECIFICATION.md` §15.2; `UI_UX_SPECIFICATION.md` A-02; `BACKEND_ARCHITECTURE.md` §7
- **Explanation:** Admin 2FA via TOTP is required for super admin. Recovery codes are explicitly deferred to P2 (`A-03`). When (not if) a super admin loses or replaces their phone, there is no documented recovery path. This is the same class of foot-gun as cert-pinning: low-frequency, high-consequence.
- **Recommendation:**
  - **Ship recovery codes in MVP**, not P2. Eight one-time codes generated at TOTP enrollment, shown once, hashed in DB.
  - **Document a multi-step admin recovery workflow** that requires another super admin to verify a recovery request out-of-band.
  - **Require ≥ 2 super admins** in production at all times (verified by a periodic health check).
- **Impact if ignored:** Single super admin locks themselves out → manual DB intervention to disable 2FA → audit trail compromised. Or worse: panic-decision to weaken 2FA platform-wide.

---

## 2. Findings — High

These should be resolved in Phase 1 or have a documented mitigation by go-live.

---

### HIGH-01 — Whitelist drain serialised per-device, no upper bound on backlog

- **Category:** Scalability / business logic
- **Where:** `DATABASE_ARCHITECTURE.md` §6.3, `BACKEND_ARCHITECTURE.md` §14.6
- **Explanation:** Whitelist changes go through a per-device outbox drained in order via `WithoutOverlapping` per device. Each SMS-programming command can take 5–30 s. If an owner bulk-imports 10 users (UX doesn't block this), the queue is 10 × ~15 s = 2.5 min. During that window: new opens by new users fail; the UI says "added" but the device disagrees.
- **Recommendation:** **Cap the burst** at the API layer (max 3 pending whitelist changes per device); **show pending whitelist state in the UX** (avatar with sync icon, "Provisioning…"); **emit `WhitelistChangeRequested` with priority**, drain priority-first inside the outbox.
- **Impact if ignored:** Predictable "I added them but they can't get in" complaints.

---

### HIGH-02 — Device confirmation of whitelist write is not modelled

- **Category:** GSM / data integrity
- **Where:** `DATABASE_ARCHITECTURE.md` §6.3
- **Explanation:** `whitelist_changes.status` includes `synced` but the SMS-programming command on most controllers returns an ACK SMS only sometimes, and some devices send no ACK at all. The system has no way to verify the whitelist matches reality, leading to drift over time (especially after device power loss, which on many models clears the in-memory whitelist).
- **Recommendation:** Add a **periodic whitelist audit** — send a query SMS, parse the returned whitelist, diff against the platform's view, repair drift. Run weekly per device.
- **Impact if ignored:** Slow-burn divergence; users lose access months later for reasons no one can explain.

---

### HIGH-03 — Driver fallback policy (CLIP → SMS) is undefined

- **Category:** GSM / business logic
- **Where:** `TECHNICAL_SPECIFICATION.md` §12.2
- **Explanation:** The driver layer enumerates `clip`, `sms`, `clip_sms`, `mqtt`. The `clip_sms` (hybrid) is described as "CLIP for open + SMS for whitelist/diagnostics." There is no documented behaviour for **CLIP attempted, failed (e.g. busy, no answer, network reject), fall back to SMS for open?** This is the most common real-world scenario.
- **Recommendation:** Specify per-device fallback policy on `device_models` (e.g. `fallback_open_driver`). At dispatch time, if primary fails with a transient code, try fallback once. Audit both attempts.
- **Impact if ignored:** Avoidable open failures; users retry manually creating duplicate audit rows.

---

### HIGH-04 — No SIM lifecycle / credit monitoring

- **Category:** GSM / operations
- **Where:** `TECHNICAL_SPECIFICATION.md` §12; nothing in DB schema
- **Explanation:** Devices have SIM cards with finite credit and provisioning state. Prepaid SIMs without auto-recharge run out; postpaid SIMs can be suspended. The platform has no representation of SIM credit balance, expiry, or provisioning state.
- **Recommendation:** Add a periodic credit query (most operators support a USSD or balance-check SMS); store on `devices.sim_credit_minor` + `devices.sim_credit_checked_at`. Alert on low balance per operator threshold.
- **Impact if ignored:** Devices go dark with no warning. Support burden compounds because the platform doesn't know *why* they're dark.

---

### HIGH-05 — Whitelist capacity enforcement is implicit and divergent across layers

- **Category:** Data integrity / UX
- **Where:** `DATABASE_ARCHITECTURE.md` §3.1 (`devices.whitelist_capacity_used`); `UI_UX_SPECIFICATION.md` S-15
- **Explanation:** `whitelist_capacity_used` is "maintained by the app." Capacity is on `device_models.whitelist_capacity`. Two failure modes: (a) the app's accounting drifts from physical reality (`HIGH-02`); (b) the UX disables Invite at capacity but the server should also enforce it (API doesn't currently reject explicitly).
- **Recommendation:** Make capacity a **server-side authoritative check** in `InvitationService::create`. Recompute `whitelist_capacity_used` from `device_users(status=active)` on every roster mutation (it's cheap) instead of incrementing/decrementing. Make `device_models.whitelist_capacity` mandatory.
- **Impact if ignored:** Inviting more users than the device can hold → silent partial sync → users believe they have access but don't.

---

### HIGH-06 — Open-permission check on primary DB on every tap is risky at scale

- **Category:** Scalability
- **Where:** `BACKEND_ARCHITECTURE.md` §11.1, §14.4 (`DeviceAccessQuery`)
- **Explanation:** §11.1 sets a 30 s Redis cache TTL on `device_access:{deviceId}:{userId}` — but §11 also says "Open-permission check: **Primary only** — replica lag could allow expired-sub user to open." These are contradictory: if we cache for 30 s, we already accept up to 30 s of staleness. A replica with sub-second lag would be safer than a 30 s cache.
- **Recommendation:** **Either** drop the cache and serve from primary (consistent, but every tap is a DB round trip — fine for now, watch p95) **or** keep the cache but make it shorter (5 s) and add explicit invalidation on `SubscriptionExpired` / `DeviceUserRevoked`. Pick one position and reflect both docs consistently.
- **Impact if ignored:** Either a security gap (cache lets expired user open) or a performance footgun (primary saturation under burst).

---

### HIGH-07 — `device_users` unique-active constraint uses a STORED generated column trick

- **Category:** Database
- **Where:** `DATABASE_ARCHITECTURE.md` §3.2
- **Explanation:** MariaDB has known quirks with STORED generated columns participating in unique indexes — including version-specific bugs around inserts that fail to compute the value in time. The "STORED" requirement also makes the column a write-path cost.
- **Recommendation:** Either (a) **use application-layer enforcement** with a transaction-bounded check; or (b) **use a `WHERE` clause UNIQUE** via a deferrable index (MariaDB doesn't support partial unique). The cleanest approach: a separate table `active_device_users (device_id PK, user_id)` mirrored from `device_users` for active rows only, with strict app-layer maintenance.
- **Impact if ignored:** Subtle data corruption (duplicate active roster rows) discovered weeks later.

---

### HIGH-08 — Refund pro-rata against `subscription_periods` is undefined

- **Category:** Payment processing / business logic
- **Where:** `TECHNICAL_SPECIFICATION.md` §14.5
- **Explanation:** Refunds can be partial. The spec says "subscription is either revoked (full refund) or shortened pro-rata (partial — Phase 2)." There is no algorithm: which period gets shortened? What if the refund spans multiple periods? Does `ends_at` move? Does a negative `subscription_periods` row mean money returned or time removed?
- **Recommendation:** Define refund-to-time math explicitly. Suggested: refund_minor / price_minor × term_days = days to subtract from `ends_at`, rounded down; new negative `subscription_periods` row records the negative amount + the days subtracted. If `ends_at` goes ≤ today, status → expired (or refunded if 100 %).
- **Impact if ignored:** Inconsistent behaviour across cases, manual SQL surgery to fix, audit headaches.

---

### HIGH-09 — Order for `purpose=device_sale` does not link to a device

- **Category:** Business logic
- **Where:** `DATABASE_ARCHITECTURE.md` §5.1–5.2
- **Explanation:** A device sale order can be paid before the device is provisioned (the technical user assigns it later). `order_items.referenced_id` is "device_id" by convention but during checkout there is no device row yet — it doesn't exist until the technical user provisions it. The order is then orphaned from the device.
- **Recommendation:** Introduce a **`device_purchase_intents`** table holding the buyer, order, and post-fulfilment device link; fulfilment closes the intent and binds the device. Or: require devices to exist (unassigned) before sale orders, and the order references that pre-existing unassigned device.
- **Impact if ignored:** Reconciliation gaps; cannot answer "which order paid for this device."

---

### HIGH-10 — Owner self-deletion has no successor policy

- **Category:** Business logic / privacy
- **Where:** `TECHNICAL_SPECIFICATION.md` §3.7, `UI_UX_SPECIFICATION.md` S-56
- **Explanation:** Owner can self-delete (blocked if owned devices have active subs — `409`). But what about expired subs with invitees still on the roster? Spec says owner gets soft-deleted then anonymised in 30 days, but who owns the device after? The schema requires a non-null `owner_user_id` (or it becomes orphaned).
- **Recommendation:** Define the policy: (a) block delete until ownership transferred (preferred); (b) auto-promote earliest invitee with an active sub. Whichever, surface it in the deletion UX as an explicit step.
- **Impact if ignored:** Anonymisation breaks FK invariants; devices end up orphaned and unreachable.

---

### HIGH-11 — Reverb WebSocket on a single 2 vCPU node is undersized and untested

- **Category:** Scalability
- **Where:** `TECHNICAL_SPECIFICATION.md` §18.3
- **Explanation:** Reverb is single-node by default; the topology lists 2 vCPU / 4 GB. Each connected mobile install holds a persistent connection while the app is foregrounded. At 5,000 simultaneous foregrounded apps on a peak evening, the node's file-descriptor + memory footprint and the lack of HA become real concerns.
- **Recommendation:**
  - **Confirm the actual concurrency target** (≤ N concurrent WS) before committing.
  - **Plan for 2 Reverb nodes** behind a sticky LB from day 1; share state via Redis pub/sub.
  - **Make WS strictly an optimisation**: the mobile app must poll `/v1/commands/{id}` if WS is unhealthy. The doc says this but ensure it's tested.
- **Impact if ignored:** Open-flow UX falls back to polling silently; in the worst case, all mobile clients reconnect at once and DDoS the node.

---

### HIGH-12 — `users.phone` uniqueness vs soft delete is ambiguous

- **Category:** Database / privacy
- **Where:** `DATABASE_ARCHITECTURE.md` §1.1
- **Explanation:** The spec says "enforced over non-soft-deleted rows via composite `(phone, deleted_at)` if phone reuse is desired; otherwise plain unique" — two opposite policies presented as a choice. This needs to be one decision because it changes every signup path.
- **Recommendation:** Decide now. Recommended: **phone is reusable after anonymisation**. After 30 d of soft delete, anonymisation replaces `phone` with a deterministic token (e.g. `deleted:{hash}`), freeing the original phone for a fresh signup with a new `user_id`. This avoids resurrection-of-ghost-account semantics.
- **Impact if ignored:** Signup behaviour diverges from documentation; user complaints when a previously-used phone won't register.

---

### HIGH-13 — Audit log table grants are vague ("DB role enforces")

- **Category:** Security / data integrity
- **Where:** `DATABASE_ARCHITECTURE.md` §8.1
- **Explanation:** "DB grants permit INSERT and SELECT only on this table for the app's runtime role. UPDATE and DELETE require an elevated migration role." This is the right idea but no implementation detail is given. A misconfigured deploy that runs as the migration role permanently breaks the immutability guarantee.
- **Recommendation:** Document the exact `GRANT` statements; produce a CI integrity check that fails the build if `UPDATE` or `DELETE` privileges on `audit_log` are granted to the runtime role; add a daily monitor.
- **Impact if ignored:** Audit log is no longer trustworthy; legal/regulatory implications.

---

### HIGH-14 — Open command "expected_completion_ms" is misleading

- **Category:** UX / GSM
- **Where:** `openapi/v1.yaml` `openDevice` → `OpenCommandAccepted.expected_completion_ms: 5000`
- **Explanation:** Promising a fixed `5000` ms misleads the UX layer into a 5 s loading state, which then hits the empirical CLIP/SMS latency reality (often 5–15 s for SMS) and the user sees "Açılmadı" before the gate has even rung. The mobile UX explicitly relies on this hint (S-12).
- **Recommendation:** Compute the hint server-side from `device.driver_type` and historical latency (rolling p90). Stop returning a fixed value.
- **Impact if ignored:** False negatives in the UX; users hammer Open thinking it failed, generating real conflicts and audit noise.

---

### HIGH-15 — `payment_logs.request_redacted`/`response_redacted` redaction is application-side and fragile

- **Category:** Security / compliance
- **Where:** `DATABASE_ARCHITECTURE.md` §5.5
- **Explanation:** Redaction is performed by app code (`Redactor.php` in `Support/`). A new field added by the bank that contains PAN is a one-line code change away from a PCI incident — and the field gets stored before anyone notices.
- **Recommendation:**
  - **Whitelist, not blacklist**. The persisted JSON contains only fields explicitly on an allowlist; everything else is stripped.
  - **Run a periodic scanner** (cron job) over `payment_logs` looking for patterns that look like PAN / CVV; fire alert.
  - **Encrypt the column** (`response_redacted_encrypted`) as a defence-in-depth, consistent with `payments.raw_response_encrypted`.
- **Impact if ignored:** PCI scope expansion; risk of breach if logs leak.

---

### HIGH-16 — App return URL accepts client-supplied `orderId` without server-side trust gate

- **Category:** Security
- **Where:** `TECHNICAL_SPECIFICATION.md` §14, `UI_UX_SPECIFICATION.md` S-33/S-34
- **Explanation:** The bank redirects back to `salam://payment/return?orderId=...`. The app then calls `GET /orders/{orderId}`. If a malicious app intercepts the deep link (or a phishing link is crafted), the user is shown a result for an arbitrary order they don't own. The order policy must prevent reading another user's order — which it does — but only if the policy is enforced server-side.
- **Recommendation:** Confirm `OrderPolicy::view` enforces `payer_user_id === auth()->user()->id`. Independently, **don't trust the return URL** for the success/failure decision — re-query `getOrder` and let the server speak.
- **Impact if ignored:** Information disclosure; phishing-style attacks confusing legitimate users.

---

### HIGH-17 — No multi-tenant `tenant_id` column anywhere is locking out a likely 2027 expansion

- **Category:** Expensive-to-change
- **Where:** All schemas
- **Explanation:** The product as scoped is single-tenant (one Salam Həyətimiz operator). White-label resale to other property managers / cooperatives is a foreseeable path. Retrofitting `tenant_id` later is essentially a rewrite.
- **Recommendation:** Add `tenant_id` (default 1) to top-level entities (`users`, `admin_users`, `devices`, `orders`, `subscriptions`, `audit_log`) as a nullable column with a default and an index. Wire it through the auth context but enforce nothing for MVP. Cost now: low. Cost later: very high.
- **Impact if ignored:** If white-label is pursued in 2027, expect 4–8 weeks of migration work and a data audit.

---

### HIGH-18 — `subscriptions.latest_order_id` denormalises a relationship that can drift

- **Category:** Database normalization
- **Where:** `DATABASE_ARCHITECTURE.md` §4.1
- **Explanation:** `latest_order_id` is a denormalised pointer. It's "most recent paying order," but is it the most recent in time? The most recent paid? Updated where? Risk of staleness.
- **Recommendation:** Either drop the column and derive from `subscription_periods` (the source of truth for paid extensions), or document the precise update rule and have one service own it (`SubscriptionService::activate()`).
- **Impact if ignored:** UI / receipts may surface the wrong order for a sub. Small but recurring.

---

## 3. Findings — Medium

Worth addressing before launch but won't block it.

---

### MED-01 — Two-phase admin login leaves a `challenge_token` window with no rate limit specified

- **Category:** Security
- **Where:** `openapi/v1.yaml` `/admin/auth/login` → `/admin/auth/2fa/verify`
- **Explanation:** Step 1 returns a challenge with no documented rate limit; an attacker who has the password but not TOTP can hammer Step 2.
- **Recommendation:** Rate-limit Step 2 per `challenge_token` (e.g. 5 attempts) and per email (e.g. 10 attempts / hour). Invalidate the challenge after first wrong TOTP submission.

---

### MED-02 — `Idempotency-Key` semantics across `/orders` create double-charge risk on retry

- **Category:** API / payments
- **Where:** `openapi/v1.yaml`, `BACKEND_ARCHITECTURE.md` §11.3
- **Explanation:** The idempotency layer caches the response body. If a client retries `POST /orders` with a new key after a timeout, a duplicate order can be created against the same subscription — leading to two simultaneous `authorising` orders, one of which will eventually expire but the user may pay twice in the meantime.
- **Recommendation:** Add a server-side guard: at most one `authorising` order per `subscription_id` at a time. Reject the second with `409 conflict`.

---

### MED-03 — Phone-number normalisation is documented but not enforced in OpenAPI

- **Category:** API consistency
- **Where:** All places phone appears
- **Explanation:** Spec mentions normalising to E.164 at the app layer but accepts strings matching `^\+994\d{9}$`. A user typing `+99450 123 45 67` or `0501234567` should be normalised, not rejected.
- **Recommendation:** Document a normalisation step server-side; accept several common formats and canonicalise. Surface the normalised form back to the client.

---

### MED-04 — No optimistic concurrency control on `devices` / `subscriptions`

- **Category:** Database
- **Explanation:** Two admins editing a device simultaneously: last write wins, no warning.
- **Recommendation:** Add a `version` integer column on `devices` and `subscriptions`; require `If-Match` header on PATCH endpoints; return `409` on mismatch.

---

### MED-05 — Soft-deleted users still hold `users.email` and `users.phone` as unique

- **Category:** Database / privacy
- **Explanation:** Until anonymisation runs (30 d after soft-delete), the email/phone is still considered taken. New signups by the same person can't proceed for 30 days.
- **Recommendation:** Anonymise PII *immediately* on soft-delete (replace phone/email with deterministic tokens) and keep the audit-linkable `user_id`. The 30 d window protects the *re-activation right*, not the data.

---

### MED-06 — `notifications.payload` JSON unbounded

- **Category:** Storage
- **Explanation:** No size limit. A buggy emit can store kilobytes of JSON per notification; over 12 months × N users this grows fast.
- **Recommendation:** Validate payload size ≤ 4 KB at dispatch time; persist a reference to a separate "rich payload" table if more is needed (unlikely).

---

### MED-07 — Open-permission cache key doesn't include device state version

- **Category:** Correctness
- **Where:** `BACKEND_ARCHITECTURE.md` §11.1
- **Explanation:** Cache key `device_access:{deviceId}:{userId}` doesn't change on device disable; we rely on explicit invalidation, which is fragile.
- **Recommendation:** Include a fast-changing fingerprint (e.g. `devices.updated_at` epoch) in the cache key. Updates naturally invalidate.

---

### MED-08 — No covering index for the open-permission check

- **Category:** Performance
- **Explanation:** `device_users(device_id, user_id, status)` is implied by separate indexes but not declared as a single composite. Query plan likely uses one index and looks up; covering composite + `subscription_id` is faster.
- **Recommendation:** Add `(device_id, user_id, status) INCLUDE (id)` covering index on `device_users`.

---

### MED-09 — `whitelist_changes` ordering relies on `created_at` not a monotonic seq

- **Category:** Data integrity
- **Explanation:** With burst inserts at the same millisecond, ordering by `created_at` is non-deterministic. The schema uses `TIMESTAMP` (second precision) on this table.
- **Recommendation:** Add an explicit `seq` BIGINT auto-increment column; drain by `(device_id, seq)`.

---

### MED-10 — Realtime channel naming `private-user.{userId}` is fine, but channel auth scope not specified

- **Category:** Security
- **Explanation:** Reverb channel auth has to ensure user can only subscribe to their own channel. The spec says so but doesn't show the auth-handler shape.
- **Recommendation:** Document the channel-auth callback contract; include a test asserting cross-user subscription fails.

---

### MED-11 — `KapitalCallback` payload is `additionalProperties: true`

- **Category:** API contract
- **Explanation:** Permissive on inbound is good for forward compatibility but means we'll silently accept new fields without thinking about them.
- **Recommendation:** Log unknown fields to a "schema-drift" metric; review weekly.

---

### MED-12 — Spec promises receipts only post-MVP; AZ tax law may require them sooner

- **Category:** Compliance
- **Explanation:** Cash/card payments in Azerbaijan have receipt-issuance requirements. The spec defers PDF invoices to P2.
- **Recommendation:** Validate with counsel whether a minimal HTML receipt visible in-app satisfies the requirement for MVP; if not, bring forward to MVP.

---

### MED-13 — Multi-driver retry policy interacts badly with `Idempotency-Key`

- **Category:** GSM / API
- **Explanation:** If CLIP fails and we fall back to SMS, the underlying `open_commands` row has the same `idempotency_key`. Two driver attempts but one persisted action. Replays after the first attempt may return "dispatched" but the second driver has not been tried.
- **Recommendation:** Track per-attempt records in a sub-table (`open_command_attempts`) so a replay can return a useful state independent of internal retries.

---

### MED-14 — Mobile login flow's "Edit phone" lets attacker burn through OTP attempts

- **Category:** Security
- **Explanation:** OTP rate limit is per phone — an attacker can rotate phones to evade. The doc has 30/IP/hour as a backstop, which is generous given an attacker controlling a single IP can do 30 tries.
- **Recommendation:** Combine phone + IP + install_uuid into a single rate-limit bucket; reduce per-IP to 10/hour.

---

### MED-15 — No mention of database backup *restore* exercise

- **Category:** Operations
- **Explanation:** Backups are specified (nightly + hourly). Restores aren't drilled.
- **Recommendation:** Quarterly restore exercise into a staging environment; document RTO measured, not assumed.

---

### MED-16 — `payment_callbacks` retention is 5 years, same as `payment_logs`

- **Category:** Storage
- **Explanation:** Five years × hundreds of duplicate callbacks per day = sizeable table.
- **Recommendation:** Reduce retention to 2 years for `payment_callbacks` (it's a dedupe ledger, not a primary financial record); keep `payment_logs` at 5.

---

### MED-17 — `open_commands` partitioning is monthly but volume forecast may need finer

- **Category:** Database
- **Explanation:** At 50 RPS × 86,400 s × 30 d ≈ 130 M rows/month at peak. Monthly partitions of that size make pruning and index maintenance slower.
- **Recommendation:** Re-evaluate at 30 d post-launch; switch to weekly partitions if write rates justify.

---

### MED-18 — `consents` UI doesn't expose revoke-history; data subject requests can't easily produce it

- **Category:** Privacy / UX
- **Explanation:** Append-only consent log is in the data model but `S-54 Privacy` only shows "latest per kind." A DSAR (data export) should include the full history.
- **Recommendation:** Add full consent history to data exports; expose a "consent history" subscreen in privacy for transparency.

---

## 4. Findings — Low

Backlog items. Document them; revisit in Phase 2/3.

| ID | Item | Note |
|---|---|---|
| LOW-01 | OpenAPI servers config muddles mobile vs admin prefixes | Style; doesn't break tooling, but tidy when convenient |
| LOW-02 | `notifications.payload` could move to a separate KV store | Premature optimisation; revisit if write contention appears |
| LOW-03 | No `OPTIONS`/CORS handling documented for admin Blade SPA | Add when admin SPA work begins |
| LOW-04 | OTP `purpose=recover` is enumerated but no flow exists | Either remove from enum or design the recovery flow |
| LOW-05 | Spec uses `Asia/Baku` for human-time crons but DB is UTC | Document the TZ rule once, with examples |
| LOW-06 | `device_users.access_window_*` (P2) has no server-side enforcement design yet | Don't ship the column with no design; defer |
| LOW-07 | `feature_flags.target_user_ids` as JSON array does not scale | Move to a join table if any flag's allowlist grows > 100 |
| LOW-08 | `error.message` is locale-baked at server; `message_key` is the locale-independent contract | Make sure clients parse on `message_key`, not `message` |
| LOW-09 | `notifications` retention 12 mo for inapp may be too long | Revisit if user inbox UX shows degradation |
| LOW-10 | Reverb is vendor lock-in to Laravel echo flavour | Acceptable for stage; document the swap path to Soketi |
| LOW-11 | No batched endpoints (bulk mark-read, bulk decline) | Add only if hot-path emerges |
| LOW-12 | `audit_log` partition retention 5 yrs vs 12 mo "hot" not codified | Decide policy explicitly in `config/audit.php` |
| LOW-13 | Test plan says "MariaDB required for feature tests" — slow CI risk | Acknowledged; document expected CI duration |
| LOW-14 | Privacy doc lacks PII inventory map (what columns hold what) | Produce as a one-page artefact for legal review |
| LOW-15 | `card_tokens` table exists for a feature that's P2 (auto-renew) | Acceptable; do not ship the UI until the column is exercised |
| LOW-16 | "Voice gateway → PSTN call cost" not in the cost model | Build a cost-per-open dashboard in Phase 1 |

---

## 5. Mandatory Phase 0 Validation Gates

Phase 1 should not start until each of these is empirically verified, not assumed.

| # | Gate | Owner | Evidence |
|---|---|---|---|
| G1 | CLIP open works end-to-end on all three AZ operators with the chosen termination route | Tech lead | Video + logs per operator |
| G2 | SMS open works end-to-end on all three AZ operators within ≤ 15 s p95 | Tech lead | Provider report |
| G3 | Kapital sandbox can complete one full purchase + one full refund + one callback storm dedup | Backend lead | Test report |
| G4 | Two GSM gateways can be operated as redundant peers with health-aware routing | DevOps | Failover demo |
| G5 | Redis with HA (Sentinel or managed) configured and primary failover demonstrated | DevOps | Failover demo |
| G6 | JWT key rotation tested end-to-end on a staging environment without logging anyone out | Backend lead | Runbook + log |
| G7 | Mobile cert-pin rotation tested with two pinned keys | Mobile lead | TestFlight + Play internal |
| G8 | Whitelist drift test: programme device, power-cycle, verify whitelist intact / detected as drift | Tech lead | Test report |

A Phase 1 kickoff blocked on missing gates is cheaper than a Phase 3 launch delayed by them.

---

## 6. Sequencing Recommendations

Based on the above, two re-prioritisations to the development phases:

1. **Bring HA Redis and dual GSM gateway into Phase 1 (was: Phase 4 / scaling).** These are not optimisations; they are foundational reliability for the headline feature. Adding them later means re-architecting the deployment pipeline mid-stream.
2. **Bring TOTP recovery codes into Phase 1 (was: Phase 2 / P2).** Cost: one screen + one DB column + 8 hashed strings. Benefit: not locking out your operators. The cost/benefit is overwhelming.

Other items can hold their phase but their **decisions should be made now** (not their implementation):
- Subscription model simplification or explicit edge-case acceptance (`CRIT-02`).
- Refund pro-rata algorithm (`HIGH-08`).
- Owner self-deletion successor policy (`HIGH-10`).
- Phone reuse policy post anonymisation (`HIGH-12` / `MED-05`).

---

## 7. Sign-off Conditions

Before Phase 1 begins, the following written decisions are required:

1. **Resolution of all Critical items.** Each needs either "fix in Phase 0/1" or "explicit risk acceptance with mitigation."
2. **Phase 0 validation gates G1–G8 either passed or explicitly waived with named owner.**
3. **Product sign-off on the subscription model edge cases** (CRIT-02) — choose simplification or accept the cases.
4. **DevOps owner identified for the GSM gateway and Redis HA workstreams**, with budget.
5. **Compliance / legal sign-off** on the receipt obligation (MED-12) and the personal-data anonymisation procedure (HIGH-10 / MED-05).
6. **Acceptance of the High findings**: each marked either "Phase 1 fix" or "Phase 2 with documented mitigation."

The plan is good. With these resolutions, it's launchable.

---

*End of Pre-Development Audit v1.0.*
