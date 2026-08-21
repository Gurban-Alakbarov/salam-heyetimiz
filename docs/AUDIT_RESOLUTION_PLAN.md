# Salam Həyətimiz — Audit Resolution Plan

**Author:** Principal Software Architect
**Date:** 2026-06-09
**Responds to:** [AUDIT_REPORT.md](AUDIT_REPORT.md) v1.0
**Status:** For engineering / product / DevOps sign-off

This plan addresses every Critical (9) and High (18) finding from the audit. For each I state my position, propose the architectural change, list affected documents, sketch the exact edits needed, estimate impact, and classify the work.

**Position legend:**
- **Agree** — accept the finding as stated.
- **Partial** — accept the underlying concern; refine the remedy.
- **Disagree** — reject the finding (or its proposed remedy) with reasoning.

**Classification legend:**
- **Fix Now** — must be resolved or decided before Phase 1 starts (typically a doc edit, a decision, or a Phase 0 gate).
- **Phase 1** — designed and built during the first sprint window per `TECHNICAL_SPECIFICATION.md` §19.
- **Phase 2** — deferred to the next sprint window with a documented mitigation.
- **Accept Risk** — explicit choice not to act, with a recorded retrofit plan.

**Impact legend** (engineering days, not calendar):
- **XS** ≤ 1 d   **S** 2–3 d   **M** 1 wk   **L** 2–4 wk   **XL** > 1 mo

---

## 1. Summary Table — All 27 Findings

| ID | Title | Position | Class | Impact | Affected docs |
|---|---|---|---|---|---|
| CRIT-01 | CLIP carrier rewriting risk | Agree | Fix Now (Phase 0 gate) | M | Tech spec, Backend arch |
| CRIT-02 | Per-(user, device) sub edge cases | Agree | Fix Now (product) + Phase 1 (UX/policy) | M | Tech spec, UI/UX, OpenAPI |
| CRIT-03 | GSM gateway SPOF + undersized | Partial | Phase 1 (HA), Fix Now (NFR) | L | Tech spec, Backend arch |
| CRIT-04 | Key-rotation runbooks undefined | Agree | Fix Now (design), Phase 1 (impl) | M | Tech spec, Backend arch |
| CRIT-05 | Single Redis SPOF | Agree | Phase 1 | M | Tech spec, Backend arch |
| CRIT-06 | "Opened" status fictional for CLIP | Agree | Fix Now (UX copy), Phase 1 (feedback loop) | S | UI/UX, OpenAPI, Tech spec |
| CRIT-07 | Bank callback foot-guns | Agree | Phase 1 | S | Tech spec, Backend arch |
| CRIT-08 | Cert-pinning rotation undefined | Agree | Phase 1 | S | Tech spec |
| CRIT-09 | TOTP-only admin 2FA, no recovery | Agree | Phase 1 (pull from P2) | S | Tech spec, DB arch, UI/UX, OpenAPI |
| HIGH-01 | Whitelist drain serialised, no cap | Agree | Phase 1 | S | DB arch, Backend arch, UI/UX |
| HIGH-02 | No whitelist drift detection | Agree | Phase 2 | S | DB arch, Backend arch |
| HIGH-03 | Driver fallback policy undefined | Agree | Phase 1 | S | DB arch, Backend arch, Tech spec |
| HIGH-04 | No SIM credit / lifecycle monitoring | Partial | Phase 2 (post-pilot) | S | DB arch, Backend arch |
| HIGH-05 | Whitelist capacity enforcement | Agree | Phase 1 | XS | DB arch, Backend arch, OpenAPI |
| HIGH-06 | Open-permission cache contradicts primary-only | Agree | Fix Now (decision) + Phase 1 | XS | Backend arch |
| HIGH-07 | `device_users` STORED unique-active | Partial | Phase 1 (test then mirror table if needed) | S | DB arch |
| HIGH-08 | Refund pro-rata math undefined | Agree | Phase 1 (algorithm) + Phase 2 (partial refunds) | S | Tech spec, Backend arch |
| HIGH-09 | Device-sale order ↔ device link | Agree | Phase 1 | S | DB arch, Tech spec, UI/UX |
| HIGH-10 | Owner self-delete successor policy | Agree | Phase 1 | XS | Tech spec, UI/UX, Backend arch |
| HIGH-11 | Reverb single node undersized | Agree | Phase 1 | S | Tech spec, Backend arch |
| HIGH-12 | Phone uniqueness vs soft-delete ambiguous | Agree | Fix Now (decision) | XS | DB arch |
| HIGH-13 | Audit-log grants vague | Agree | Phase 1 | XS | DB arch, Backend arch |
| HIGH-14 | `expected_completion_ms` misleading | Agree | Phase 1 | XS | OpenAPI, Backend arch |
| HIGH-15 | `payment_logs` redaction fragile | Agree | Phase 1 | S | DB arch, Backend arch |
| HIGH-16 | App return URL trust | Partial | Phase 1 (verify) | XS | Backend arch, UI/UX |
| HIGH-17 | No `tenant_id` for future multi-tenant | **Disagree** | **Accept Risk** (with retrofit plan) | n/a now; L if invoked | n/a |
| HIGH-18 | `subscriptions.latest_order_id` denormalises | Agree | Phase 1 | XS | DB arch, Backend arch |

**Aggregate effort for non-accepted items: ~10 weeks of engineering across 26 items, spread over Phases 1 and 2.** None individually crosses 4 weeks; the bulk is XS/S items consolidated into the existing Phase 1 plan.

---

## 2. Detailed Resolutions — Critical Findings

---

### CRIT-01 — CLIP carrier rewriting risk

**Position:** Agree.

**Why:** This is the single biggest unknown. No software architecture can compensate for carrier-level CLI rewriting. The only honest answer is to validate empirically before committing.

**Architectural change:**
- Add **Phase 0 gate G1** to `TECHNICAL_SPECIFICATION.md` §19 as a hard prerequisite to Phase 1 kickoff.
- Build a one-off Laravel artisan command (`gsm:test-clip --operator=azercell|bakcell|nar`) under `app/Console/Commands/Diagnostics/`. This command lives outside the production driver layer; it's a black-box prover.
- Capture results in a versioned artefact `docs/phase0/clip-validation-{operator}.md` (one per operator) with video, logs, and a pass/fail verdict.
- If any operator fails: the device driver for that operator's SIMs is forced to SMS at config level (`config/domain/device_comm.php` operator override map).

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` — §0 open items #2 closed by this; §12.6 add explicit per-operator fallback table; §19 Phase 0 expand.
- `BACKEND_ARCHITECTURE.md` — §14.6 add `OperatorFallbackPolicy` resolved at `DriverResolver::for()`.

**Required document modifications (sketch):**

> `TECHNICAL_SPECIFICATION.md` §12.6, append:
>
> "### 12.6.1 Per-Operator CLI Validation
>
> Before Phase 1 kickoff, each AZ operator's CLI behaviour MUST be empirically validated. The validation produces three artefacts (one per operator) recording whether end-to-end CLI is preserved when calling from our voice gateway. The result determines the *default* driver per `sim_operator`:
>
> | Operator | If CLI preserved | If CLI rewritten |
> |---|---|---|
> | Azercell | `clip_sms` | `sms` |
> | Bakcell | `clip_sms` | `sms` |
> | Nar | `clip_sms` | `sms` |
>
> The mapping lives in `config/domain/device_comm.php` and is overridable per device in `devices.driver_type`."

**Implementation impact:** **M** (1 week to design, run, and document — single engineer; gates everything downstream).

**Classification:** **Fix Now** (Phase 0 gate).

---

### CRIT-02 — Per-(user, device) subscription edge cases

**Position:** Agree.

**Why:** The audit correctly identifies four genuine edge cases. I would not push to *simplify* the model — the project description explicitly mandates main + additional user pricing, and changing that is a product call, not architecture's. But the audit is right that the UX has not been designed for these states; that gap is fixable without rewriting the model.

**Architectural change:**
- **No change to the data model.** The per-(user, device) sub stays.
- Add four named UX states to S-09 / S-11 reflecting the edge cases:
  - `device_active_for_others` — others have active subs, you don't. Renew CTA prominent.
  - `device_suspended_owner_only` — owner expired; invitees active. Different message to owner vs invitees.
  - `partial_refund_active` — sub shortened but still active.
  - `successor_required` — owner trying to self-delete with active invitee subs.
- Add the policy `Owner-cannot-self-delete-while-any-invitee-has-active-sub` (see also HIGH-10).
- Add invoice/receipt line items explicitly distinguishing "Main user" from "Additional user" so the customer can reconcile the multi-payer state.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §13 — add explicit subsection §13.9 "Edge-case behaviours" enumerating the four states.
- `UI_UX_SPECIFICATION.md` §7.3 (S-09, S-11) — add the four states to the per-screen state table.
- `openapi/v1.yaml` — `Device.suspension_reason` enum extended with `owner_sub_expired_others_active`.

**Required document modifications (sketch):**

> `TECHNICAL_SPECIFICATION.md` §13, append §13.9:
>
> "### 13.9 Edge-case Behaviours
>
> | Situation | Owner experience | Invitee experience | API rule |
> |---|---|---|---|
> | Owner sub expired, ≥1 invitee active | Device shows `suspended_owner_only`; can renew or manage; cannot open | Device active; can open | `DeviceAccessQuery` per-user; `device.status` derived field varies by caller |
> | Owner has active sub, all invitees expired | Device active for owner; invitees suspended | Renew CTA; cannot open | as above |
> | All subs expired | Device-wide suspended | Same | `device.status = suspended` |
> | Refund of owner main sub | Owner sub → `refunded`; others unaffected | Unchanged | Settlement note in §14.5 |
> | Owner attempts self-delete with active invitee | Blocked `409 successor_required` | Unchanged | See HIGH-10 |"

**Implementation impact:** **M** (UX design + API field + tests; spread across mobile and backend).

**Classification:** **Fix Now** (product sign-off on §13.9 wording), then **Phase 1** for UX states and the policy.

---

### CRIT-03 — GSM gateway SPOF + undersized for stated NFR

**Position:** Partial — agree on HA, push back on the throughput numbers.

**Why:** The audit calculates 50 RPS × 4 s = 200 ports needed against the 64-port deployment. The arithmetic is correct but the input (50 RPS sustained) is unrealistic for a residential access-control system. Even at 100,000 active devices, 50 RPS sustained = 4.3 M opens/day = ~43 opens per device per day. Sustained, never sleeping. That is not a residential workload. The realistic shape is **bursty**: morning departure peak (~07:00–09:00) and evening arrival peak (~17:00–20:00), with low background traffic elsewhere.

So: keep two gateways for HA, but **revise the NFR** to a shape the deployment can actually serve.

**Architectural change:**
1. **Revise NFR §3.1**: from "50 RPS sustained, 200 RPS burst" to **"5 RPS sustained, 50 RPS burst sustained for 60 s, 200 RPS peak for 10 s."** Size against the burst figure: 50 RPS × 4 s = 200 port-seconds, which means 200 / 60 s window ≈ 3.3 concurrent port-occupancies on average; bursts up to ~33 concurrent ports. Two 32-port gateways = 64 ports. Headroom: ~2×.
2. **Deploy two GSM gateways from day 1**, in separate facilities, SIMs from at least two operators distributed across both.
3. **Make the voice gateway a network-addressable service** (HTTP from Laravel to a thin gateway daemon), not a library inside the API.
4. **Add `VoiceGatewaySelector` to `Domain/DeviceComm`** with health-aware round-robin and a per-gateway circuit breaker (Open / Half-open / Closed states).
5. **Document a degraded-mode**: when both gateways are unhealthy, opens accept with `state=failed` immediately and `failure_reason=gateway_unavailable`, not queued indefinitely.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §3.1, §12.6, §18 — NFR table, voice topology, two-gateway diagram.
- `BACKEND_ARCHITECTURE.md` §14.6 — `VoiceGatewaySelector` added to the module catalogue.

**Required document modifications (sketch):**

> `TECHNICAL_SPECIFICATION.md` §3.1, replace the Concurrent-open-commands row:
>
> | Metric | Target |
> |---|---|
> | Concurrent open commands (sustained) | **≥ 5 RPS** |
> | Concurrent open commands (1-min burst) | **≥ 50 RPS** |
> | Concurrent open commands (10-s peak) | **≥ 200 RPS** |
>
> The deployment must sustain the 1-min burst figure with **at least one** GSM gateway healthy; the 10-s peak requires both. Above the peak, opens degrade to `failed` with `failure_reason=gateway_capacity`.

**Implementation impact:** **L** (procurement + dual-DC config + daemonisation; longest poll is hardware/SIM logistics).

**Classification:** **Fix Now** (NFR revision in spec) + **Phase 1** (deployment).

---

### CRIT-04 — Key-rotation runbooks undefined

**Position:** Agree.

**Why:** Every long-lived key is a one-shot foot-gun. Documenting rotation before the first rotation is needed is roughly 1000× cheaper than during.

**Architectural change:**
1. **JWTs gain a `kid` claim**; signing keys live as a small file set rotated by ID. Two keys valid simultaneously during overlap (≥ `refresh_token_ttl` to ensure no forced logouts).
2. **JWKS endpoint** at `https://api.salamhayetimiz.az/.well-known/jwks.json` exposes admin public keys only. Mobile pins so does not consume JWKS, but documenting the structure means we *could* deliver future mobile keys via this channel if pinning is ever relaxed.
3. **Document four rotation runbooks** as Phase 1 deliverables in a new file `docs/runbooks/key-rotation/`:
   - `jwt-mobile.md`
   - `jwt-admin.md`
   - `app-encryption-key.md` (with re-encryption migration)
   - `kapital-hmac.md` (with bank coordination steps)
4. **Add `audit_log` entries** `secret.rotated` with `payload = {kind, kid_old, kid_new, actor}`.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §15.5 — expand to enumerate every secret with rotation cadence and runbook reference.
- `BACKEND_ARCHITECTURE.md` §7.1 — JWT structure now includes `kid`; describe JWKS.
- `openapi/v1.yaml` — add `/.well-known/jwks.json` (admin host) as an unauthenticated GET.

**Required document modifications (sketch):**

> `TECHNICAL_SPECIFICATION.md` §15.5, append table:
>
> | Secret | Cadence | Runbook | Overlap window |
> |---|---|---|---|
> | JWT mobile signing (RS256) | 12 months | `runbooks/key-rotation/jwt-mobile.md` | 60 d (= refresh TTL) |
> | JWT admin signing (RS256) | 6 months | `jwt-admin.md` | 24 h |
> | App encryption key | On compromise only | `app-encryption-key.md` | re-encrypt in batches |
> | Kapital HMAC | 12 months | `kapital-hmac.md` | bank-coordinated |
> | SMS / voice provider keys | 12 months | `provider-keys.md` | provider-supports both |
> | TLS / cert-pin keys | per HIGH-08 | `cert-pin.md` | 2-pin overlap |

**Implementation impact:** **M** (runbook writing + JWKS endpoint + key-id wiring + tests).

**Classification:** **Fix Now** (design + JWKS contract) + **Phase 1** (implementation).

---

### CRIT-05 — Single Redis SPOF

**Position:** Agree.

**Why:** Open commands, payments, idempotency, and scheduler all sit on Redis. Outage of any single Redis instance currently means total platform outage.

**Architectural change:**
1. **Redis with Sentinel** (one primary + two replicas + three sentinels) **OR** managed Redis (e.g. AWS ElastiCache, Azure Cache) with HA failover.
2. **Separate logical databases**:
   - `db=0` cache (eviction policy `allkeys-lru`)
   - `db=1` queue (`noeviction`)
   - `db=2` locks (`noeviction`)
   - `db=3` broadcasting (Reverb pub/sub)
   - `db=4` idempotency hot tier (`allkeys-lru`)
3. **Connection-level circuit breaker** in `Cache::store('redis')` and `Queue::connection('redis')`; surface as `503` for opens when Redis is unhealthy (do not hang).
4. **`maxmemory` budget**: 60 % of node RAM, leaving headroom for replication buffers.
5. **Monitoring** on `used_memory`, `evicted_keys` (≥ 0 means cache pressure), `connected_slaves`, and Sentinel quorum.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §18.2, §18.3 — replace "1× Redis" with the HA topology.
- `BACKEND_ARCHITECTURE.md` §11.1 — document logical-DB split and `maxmemory-policy` per DB.

**Required document modifications (sketch):**

> `TECHNICAL_SPECIFICATION.md` §18.3, replace the Redis row:
>
> | Node | vCPU | RAM | Disk |
> |---|---|---|---|
> | Redis primary | 2 | 8 GB | 40 GB |
> | Redis replica × 2 | 2 | 8 GB | 40 GB |
> | Sentinels (co-located) | — | — | — |

**Implementation impact:** **M** (deployment + monitoring; logical-DB split is cheap once decided).

**Classification:** **Phase 1**.

---

### CRIT-06 — "Opened" status is fiction for CLIP

**Position:** Agree.

**Why:** The mismatch between UI claim and physical reality is the kind of bug that erodes trust the fastest. Even if the gate worked 99 % of the time, the 1 % "I tapped but nothing happened" cases will be unaccountable from logs.

**Architectural change:**
- **Rename the terminal state for CLIP-only devices** in mobile copy: "Sent" / "Göndərildi" with a softer green, not "Opened" / "Açıldı". The OpenCommand state `opened` in DB stays for hybrid/MQTT (which have confirmation).
- **For `clip_sms` devices**, attempt a delivery-report query (one of the controller models supports this) — if it confirms relay actuation, state is `opened`; otherwise `dispatched`.
- **Add an in-app feedback prompt** "Did the gate open? Yes / No" shown after the action, persisted to a new lightweight table `open_command_feedback (open_command_id, user_id, gate_moved boolean, comment, created_at)`. Surface per-device reliability metrics to admin.
- The OpenAPI `OpenCommand.state` enum stays as-is in spec, but the **mobile UX layer maps `dispatched` to "Sent" copy** for CLIP devices.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §11.5 / §12 — note the semantic distinction.
- `UI_UX_SPECIFICATION.md` S-12 — change state labels for CLIP-only devices.
- `openapi/v1.yaml` — extend `OpenCommand` with `driver_confirms_actuation: boolean` so mobile can render correct copy without device-model knowledge.
- `DATABASE_ARCHITECTURE.md` — add `open_command_feedback` table.

**Required document modifications (sketch):**

> `UI_UX_SPECIFICATION.md` S-12, replace "Success State":
>
> "**Success State** — Behaviour depends on the driver's actuation confirmation capability:
> - If `OpenCommand.driver_confirms_actuation = true`: arc green, check, success haptic, toast "Açıldı".
> - If `false` (CLIP-only): arc green, paper-plane icon, soft haptic, toast "Göndərildi". Optional inline prompt "Did the gate open? Yes / No"."

**Implementation impact:** **S** (UX + small API field + new tiny table).

**Classification:** **Fix Now** (UX copy and OpenAPI field) + **Phase 1** (feedback table + admin metrics).

---

### CRIT-07 — Bank callback foot-guns

**Position:** Agree.

**Why:** All three sub-issues are real production class. They are also small to fix correctly if done before launch.

**Architectural change:**
1. **`CaptureRawBody` middleware** runs *before* JSON parsing on the callback route, stashes `php://input` raw bytes into the request attributes. `VerifyKapitalSignature` reads from this raw stash for HMAC validation.
2. **Nginx config** for the callback path must include `proxy_request_buffering on;` (or be removed from any rewriting proxy). Document in `runbooks/deploy/nginx.md`.
3. **IP allowlist monitor**: separate metric on `signature_invalid_count` partitioned by `ip_in_allowlist={true,false}`. Failed-and-not-in-allowlist = attack; failed-but-in-allowlist = signature drift (probable bank change). Alert each separately.
4. **PENDING handling**: callback handler for `bank_status=PENDING` writes the `payment_callbacks` row but **does not** mutate `orders`. It enqueues a `RecheckOrderStatusJob` with backoff.
5. **`getOrderStatus` as authority**: the `ProcessPaymentCallbackJob` action MUST always call `getOrderStatus` before mutating, even when signature passes and status is APPROVED. Document as a non-negotiable in the action's docblock.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §14.3 — explicitly enumerate the three defences.
- `BACKEND_ARCHITECTURE.md` §6.5 — add `CaptureRawBody` middleware.
- `BACKEND_ARCHITECTURE.md` §14.8 — `PaymentCallbackService` flow updated for PENDING.

**Required document modifications (sketch):**

> `BACKEND_ARCHITECTURE.md` §6.5, add row to middleware table:
>
> | `CaptureRawBody` | webhooks | Stashes raw `php://input` bytes on the request before any JSON parsing, so HMAC verifiers can hash the exact wire bytes. Runs first in the webhook middleware stack. |

**Implementation impact:** **S** (three small focused changes).

**Classification:** **Phase 1**.

---

### CRIT-08 — Cert-pinning rotation undefined

**Position:** Agree.

**Why:** Pinning failures are silent on the server and catastrophic on the client.

**Architectural change:**
1. **Pin public keys (SPKI hash), not full certificates.** Survives cert renewal as long as the CSR is reused.
2. **Pin two SPKI hashes** (primary + backup). Backup is generated and kept offline; primary is in use.
3. **Rotation runbook**: ship app version *N+1* pinning the new-primary + a new-backup, wait until ≥ 95 % of installs are on *N+1* (or *N* is force-update-required), then rotate the cert.
4. **Backend monitor**: 4xx-spike-by-app-version dashboard; pinning failures produce TLS handshake failures that won't appear in app logs but will appear in CDN logs.
5. **Emergency escape hatch**: a non-pinned hostname `api-recovery.salamhayetimiz.az` permanently exists; the mobile app falls back to it *only* when activated by a remote-flag served from a known-good third-party config (e.g. a single static JSON in S3). Used only in cert-rotation emergencies.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §15.4, §15.8 — document SPKI pinning, dual-pin policy, runbook reference, and the escape hatch.

**Required document modifications (sketch):**

> `TECHNICAL_SPECIFICATION.md` §15.8, replace "Cert pinning" line:
>
> "- **SPKI pinning** of two public-key hashes (primary + backup) generated from a long-lived CSR. The pinned set rotates by app release, not by cert renewal. Runbook: `runbooks/key-rotation/cert-pin.md`. Backend monitors handshake failures by `app_version` via CDN logs; spikes page on-call."

**Implementation impact:** **S** (mobile pin set + runbook + escape-hatch wiring).

**Classification:** **Phase 1**.

---

### CRIT-09 — TOTP-only admin 2FA, no recovery codes

**Position:** Agree.

**Why:** The cost is one screen and one column; the benefit is not locking out your operators. Deferring to P2 was the wrong call.

**Architectural change:**
1. **At TOTP enrollment**, the system generates 8 recovery codes (each 10 hex chars), shows them once, persists their bcrypt hashes in a new column `admin_users.recovery_codes_hashes JSON` (array of 8 hashes).
2. **Login Step 2** accepts either TOTP or a recovery code (one-time use; consumed entry replaced with `null` in the array).
3. **Admin profile screen** A-99 adds "Regenerate recovery codes" action (invalidates old; shows new once).
4. **Two-super-admin invariant**: a periodic check warns if only one super admin is `active`. Documented in operations runbook.
5. **Recovery-by-peer**: if all 8 codes are also lost, another active super admin can initiate a 2FA reset for a colleague via a documented two-person workflow audited in `audit_log`.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §15.2 — recovery codes now MVP, not P2.
- `DATABASE_ARCHITECTURE.md` §1.2 — add `recovery_codes_hashes` column to `admin_users`.
- `UI_UX_SPECIFICATION.md` A-02, A-99 — recovery code entry on login; regenerate on profile.
- `openapi/v1.yaml` — `adminVerify2fa` accepts either `totp` or `recovery_code`; add `regenerateRecoveryCodes` endpoint.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §1.2 `admin_users` table, add column:
>
> | `recovery_codes_hashes` | JSON | yes | NULL | Array of 8 bcrypt hashes; nulls mark consumed codes; full array replaced on regenerate. |

**Implementation impact:** **S** (column + endpoint + UX form + tests).

**Classification:** **Phase 1** (pulled from P2).

---

## 3. Detailed Resolutions — High Findings

---

### HIGH-01 — Whitelist drain serialised per device, no upper bound

**Position:** Agree.

**Why:** A small UX cap and a small visible "Provisioning…" state fix this completely without invasive changes.

**Architectural change:**
- **API guard:** `InvitationService::create` and `RosterService::addUser` reject if `whitelist_changes(device_id, status='pending')` count ≥ 5 (configurable). HTTP `409 too_many_pending_changes`.
- **UI state**: invitation rows show a `provisioning` chip until their corresponding `whitelist_change` reaches `synced`.
- **Optional priority field** on `whitelist_changes.priority TINYINT DEFAULT 50`; admin-forced resyncs use 10 (drain first).

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §6.3 — add `priority` column.
- `BACKEND_ARCHITECTURE.md` §14.6 — `WhitelistOutboxService` drain order updated.
- `UI_UX_SPECIFICATION.md` S-15 — Pending chip on roster rows; helper copy.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §6.3, add column:
>
> | `priority` | TINYINT UNSIGNED | no | 50 | Lower drains first. |

**Implementation impact:** **S**.

**Classification:** **Phase 1**.

---

### HIGH-02 — No whitelist drift detection

**Position:** Agree.

**Why:** Drift is inevitable (power loss, manual SMS programming by support, SIM swaps). Without detection, the platform's view diverges from reality unboundedly.

**Architectural change:**
- New scheduled command `devices:audit-whitelist` runs weekly per active device. Sends a "list whitelist" SMS query to the device; parses response.
- Diff against `device_users(status=active).phone` set; insert `whitelist_changes` rows to converge.
- Alerts on drift > 10 % of expected entries on any device (likely a deeper problem).

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §6.3 — no schema change.
- `BACKEND_ARCHITECTURE.md` §14.6 + §10 — add command and schedule.
- `TECHNICAL_SPECIFICATION.md` §12.5 — diagnostics extended.

**Required document modifications (sketch):**

> `BACKEND_ARCHITECTURE.md` §10 schedule table, add row:
>
> | weekly Sun 04:00 | `devices:audit-whitelist` | DeviceComm |

**Implementation impact:** **S** (one command + parser).

**Classification:** **Phase 2** (not launch-blocking; pilot first to confirm drift rate is non-trivial).

---

### HIGH-03 — Driver fallback (CLIP → SMS) policy undefined

**Position:** Agree.

**Why:** The most common real-world case ("CLIP didn't get through, try SMS") is currently unhandled.

**Architectural change:**
- Add `device_models.fallback_open_driver` (nullable enum).
- `DispatchOpenCommand` action: on transient failure of primary driver (`busy`, `no_answer`, `network_temporary`), retry with fallback driver. Cap at one fallback attempt. Both attempts recorded in a new `open_command_attempts` table (see MED-13).

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §2.2 (device_models), new §6.1.1 (open_command_attempts).
- `BACKEND_ARCHITECTURE.md` §14.6.
- `TECHNICAL_SPECIFICATION.md` §12.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §2.2, add column:
>
> | `fallback_open_driver` | ENUM('clip','sms','clip_sms','mqtt') | yes | NULL | If primary `open` fails transiently, dispatcher retries via this driver once. |

**Implementation impact:** **S**.

**Classification:** **Phase 1**.

---

### HIGH-04 — No SIM credit / lifecycle monitoring

**Position:** Partial.

**Why:** I agree this is necessary at scale, but pre-launch is not when to design it. Most prepaid balance-check methods (USSD, balance-SMS) are operator-specific and brittle; implementing three of them before knowing which operators we even ship with is YAGNI. Pilot reveals which mechanism actually works.

**Architectural change:**
- **Add a column placeholder now** so we don't need a schema migration later: `devices.sim_credit_minor INT NULL`, `devices.sim_credit_checked_at TIMESTAMP NULL`, `devices.sim_status ENUM('active','low_credit','suspended','unknown') DEFAULT 'unknown'`.
- **Defer the actual collection logic** to Phase 2, after the SIM provider per operator is confirmed.
- **Phase 2 design** will add `app/Console/Commands/Devices/CheckSimCreditCommand.php` and operator-specific balance-parser adapters.

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §3.1 — add three columns to `devices`.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §3.1 `devices` table, add columns:
>
> | `sim_credit_minor` | INT | yes | NULL | Operator-reported balance in qəpik; updated by Phase 2 scheduler. |
> | `sim_credit_checked_at` | TIMESTAMP | yes | NULL | |
> | `sim_status` | ENUM('active','low_credit','suspended','unknown') | no | `unknown` | Derived from balance + connectivity. |

**Implementation impact:** **XS** now (columns); **S** later (collectors).

**Classification:** **Phase 2** (collectors); column-placeholder is Phase 1 to avoid a future migration.

---

### HIGH-05 — Whitelist capacity enforcement implicit

**Position:** Agree.

**Why:** Capacity has to be enforced server-side; making `devices.whitelist_capacity_used` derived removes a class of drift bug entirely.

**Architectural change:**
- **Make `whitelist_capacity_used` computed on read**, not maintained on write. Either a database VIEW or a service computation. Cost: one COUNT(*) per device — negligible.
- **`InvitationService::create` and `RosterService::addUser`** check capacity authoritatively against `device_models.whitelist_capacity` before adding.
- **Make `device_models.whitelist_capacity` NOT NULL** with a sensible default (e.g. 100).

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §3.1 — `whitelist_capacity_used` marked as "derived (do not write)" or removed entirely; §2.2 — `whitelist_capacity` not-null with default.
- `BACKEND_ARCHITECTURE.md` §14.5 — `RosterService` capacity check.
- `openapi/v1.yaml` — `RosterCapacityException` (409) added to `createInvitation` and `revokeRosterUser` responses.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §3.1, mark column:
>
> | `whitelist_capacity_used` | SMALLINT UNSIGNED | no | 0 | **DEPRECATED** — compute on read from `device_users(status='active')`. Do not write. To be dropped in Phase 2 after migration. |

**Implementation impact:** **XS**.

**Classification:** **Phase 1**.

---

### HIGH-06 — Open-permission cache contradicts primary-only rule

**Position:** Agree.

**Why:** The doc-set contradicts itself. The right answer is to commit to a position.

**Architectural change:**
- **Drop the cache for the open-permission check.** The query is single-index lookup on `device_users` + `subscriptions`; it's <2 ms on a hot DB. The cache saves 2 ms at the cost of correctness; not worth it.
- For OTHER hot reads (Device list, Subscription list), caching against the *replica* with explicit invalidation is fine.

**Affected documents:**
- `BACKEND_ARCHITECTURE.md` §11.1 — remove "Open-permission check" cache row.

**Required document modifications (sketch):**

> `BACKEND_ARCHITECTURE.md` §11.1, remove the row:
>
> | Open-permission check | `device_access:{deviceId}:{userId}` | 30 s | Roster events + subscription events |
>
> Add note:
>
> "Open-permission is intentionally **not cached**. The check is a single SQL on `device_users` + `subscriptions` via `DeviceAccessQuery`; latency budget allows 2 ms. Caching here trades correctness (expired sub could open) for negligible gain."

**Implementation impact:** **XS**.

**Classification:** **Fix Now** (decision) + **Phase 1** (implementation reflects).

---

### HIGH-07 — `device_users` STORED unique-active

**Position:** Partial.

**Why:** STORED generated columns with UNIQUE work on MariaDB 11.x in our testing matrix; the concern is real but probabilistic, not certain. The right move is to test it first; mirror table is a fallback if it bites.

**Architectural change:**
- **Plan A (default):** keep the STORED + UNIQUE approach. Add a dedicated test in CI that hammers the constraint with concurrent inserts (100 threads, same `(device_id, user_id)`, expect exactly 1 success). Pass = ship.
- **Plan B (if Plan A fails):** introduce `device_users_active` mirror table `(device_id, user_id) PRIMARY KEY` updated by triggers (or by the `RosterService` inside the same transaction). Schema-level uniqueness without the generated column.
- Commit to Plan A unless concurrency test fails on the deployed MariaDB version.

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §3.2 — add note about fallback strategy.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §3.2, append note after the UNIQUE row:
>
> "**Fallback plan**: If concurrency tests against the deployed MariaDB version reveal STORED-column UNIQUE issues, replace this constraint with a separate mirror table `device_users_active (device_id, user_id) PRIMARY KEY` maintained by `RosterService` within the same transaction. Decision deferred to Phase 1 testing."

**Implementation impact:** **S** (concurrency test); **S** more if fallback needed.

**Classification:** **Phase 1**.

---

### HIGH-08 — Refund pro-rata math undefined

**Position:** Agree.

**Why:** Without an algorithm, the first partial refund becomes a one-off manual operation.

**Architectural change:**
- Define the algorithm explicitly in `TECHNICAL_SPECIFICATION.md` §14.5:
  - **Full refund (refund_amount == order.amount)**: subscription → `refunded`, `ends_at = now`. Whitelist removal for that (user, device).
  - **Partial refund (refund_amount < order.amount)**: days_to_remove = floor(refund_amount / price_per_day_minor) where price_per_day_minor = price_minor / term_days. New `subscriptions.ends_at = max(now, current_ends_at - days_to_remove)`. New `subscription_periods` row with `kind='refund'`, `amount_minor=-refund_amount`, `period_start = original.period_start`, `period_end = original.period_end - days_to_remove`.
  - **Edge**: if computed new `ends_at <= now`, treat as full refund.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §14.5.
- `BACKEND_ARCHITECTURE.md` §14.8 — `RefundService::executeApprovedRefund` references this algorithm.

**Required document modifications (sketch):**

> `TECHNICAL_SPECIFICATION.md` §14.5, replace the refund-impact paragraph with the algorithm above. Then add an example:
>
> "**Example**: 12 AZN main sub, 365 day term, paid 2026-01-01 → `ends_at = 2027-01-01`. Refund of 4 AZN on 2026-04-01. price_per_day_minor = 1200 / 365 ≈ 3.29. days_to_remove = 400 / 3.29 ≈ 121. New `ends_at = max(2026-04-01, 2027-01-01 - 121d) = 2026-09-02`. Audit + notification."

**Implementation impact:** **S**.

**Classification:** **Phase 1** (algorithm); **Phase 2** for the UI exposing partial refunds (full refunds work without it).

---

### HIGH-09 — Device-sale order doesn't link to device

**Position:** Agree.

**Why:** A sale that doesn't link to its delivered asset is a reconciliation problem in 30 days.

**Architectural change:**
- **Require unassigned device to exist before a `device_sale` order.** Workflow: technical user provisions device → device in `unassigned` → customer pays sale order referencing that device → sale completion triggers assign-to-owner.
- This avoids a new table and keeps the data model lean. The "intent" the audit suggested is just the order itself.
- `order_items.referenced_id` for `item_type='device'` is now a hard FK reference enforced at app layer.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §13 + §14 — note the sequencing.
- `UI_UX_SPECIFICATION.md` Technical Mode — `T-03` Device Register precedes sale (already so).
- `DATABASE_ARCHITECTURE.md` §5.2 — note on `referenced_id` semantics.

**Required document modifications (sketch):**

> `TECHNICAL_SPECIFICATION.md` §14.1 (new subsection), add:
>
> "Orders with `purpose=device_sale` MUST reference an existing `devices` row in `unassigned` state. The `OrderService::create` action validates this; a sale cannot exist before its asset."

**Implementation impact:** **S**.

**Classification:** **Phase 1**.

---

### HIGH-10 — Owner self-deletion successor policy

**Position:** Agree.

**Why:** Without a policy, anonymisation breaks the schema invariant (`devices.owner_user_id` non-null required).

**Architectural change:**
- **Block owner self-deletion** if any owned device has *any* active sub (own or invitee). Surface as `409 successor_required` with a list of devices the user must transfer or wind down first.
- **UX**: `S-56` adds an explicit "Transfer ownership" step that links to a transfer flow (admin-only initially, expanded later if a self-service transfer is added).
- For now, transfer is admin-only via `adminTransferDevice`; the owner contacts support.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §13.9 (the new §13.9 from CRIT-02).
- `UI_UX_SPECIFICATION.md` S-56 — successor-required state.
- `BACKEND_ARCHITECTURE.md` §14.2 — `UserProfileService::selfDelete` rule.
- `openapi/v1.yaml` — `deleteMe` already returns `409`; add `error.code=successor_required`.

**Required document modifications (sketch):**

> `openapi/v1.yaml` `deleteMe` response 409 example:
>
> ```yaml
> '409':
>   description: Devices with active subscriptions block self-delete
>   content:
>     application/json:
>       schema: { $ref: '#/components/schemas/Error' }
>       examples:
>         successor_required:
>           value:
>             error:
>               code: successor_required
>               message_key: errors.successor_required
>               message: "Cihazınızın yeni sahibi təyin edilməlidir."
>               details: { devices: [{id: 42, label: "Yard gate"}] }
> ```

**Implementation impact:** **XS**.

**Classification:** **Phase 1**.

---

### HIGH-11 — Reverb single node undersized

**Position:** Agree.

**Why:** Single-node WS is a latent SPOF; capacity is also worth scaling early because connection counts grow non-linearly during peak (morning departure).

**Architectural change:**
- **Two Reverb nodes** behind a sticky LB (cookie / hash on user_id). State shared via Redis pub/sub (Reverb supports this natively).
- **Capacity envelope**: each 2 vCPU / 4 GB node holds ≥ 5,000 concurrent WebSocket connections.
- **Fallback verified**: the mobile app's 1 s polling fallback is a launch acceptance criterion; tested by killing Reverb during a synthetic open command and observing recovery.

**Affected documents:**
- `TECHNICAL_SPECIFICATION.md` §18.2, §18.3.
- `BACKEND_ARCHITECTURE.md` §6.4.

**Required document modifications (sketch):**

> `TECHNICAL_SPECIFICATION.md` §18.3, replace Reverb row:
>
> | Reverb × 2 (sticky LB) | 2 | 4 GB | 40 GB |

**Implementation impact:** **S**.

**Classification:** **Phase 1**.

---

### HIGH-12 — Phone uniqueness vs soft-delete ambiguous

**Position:** Agree.

**Why:** This needs to be one decision because it affects every signup.

**Architectural change:**
- **Decision: phone is reusable.** Soft-delete triggers *immediate* anonymisation of `users.phone` to `deleted:<sha256(phone)>` (same hash) and `users.email` to `deleted:<sha256(email)>` (when present). New signup with the same real phone creates a new `users` row with a new `user_id`. Historical references to the old `user_id` stay intact.
- The 30 d window protects reactivation rights; reactivation is a support workflow that finds the soft-deleted ghost by `phone_hash` and undoes anonymisation if it matches.
- Simplifies `users.phone UNIQUE` to a plain UNIQUE (deleted rows hold tokens, not real phones).

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §0.3 (soft-delete policy) + §1.1.
- `TECHNICAL_SPECIFICATION.md` §3.7.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §1.1, simplify uniqueness:
>
> "**Unique Constraints**
> - `uq_users_phone` on `phone` (plain UNIQUE). Soft-deleted rows carry a `phone` anonymised to `deleted:<sha256(original_phone)>` so the real number is reusable."

**Implementation impact:** **XS**.

**Classification:** **Fix Now** (decision).

---

### HIGH-13 — Audit-log grants vague

**Position:** Agree.

**Why:** "DB grants enforce it" is a promise; without explicit grants it's not enforced.

**Architectural change:**
1. **Explicit GRANT statements** committed to `database/grants/runtime.sql` and `database/grants/migrator.sql`:
   - runtime: `INSERT, SELECT` on `audit_log`; same on `payment_logs`; full on everything else.
   - migrator: full on `audit_log` and `payment_logs`.
2. **CI integrity check** queries `information_schema.user_privileges` for the runtime user; fails the build if `UPDATE` or `DELETE` is granted on those tables.
3. **Daily monitor** in production runs the same check; alerts on drift.

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §8.1 — explicit GRANT documentation.
- `BACKEND_ARCHITECTURE.md` §15 — CI check listed.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §8.1, replace "DB grants permit..." with:
>
> "**Database-level immutability** is enforced by explicit GRANT statements committed to `database/grants/runtime.sql`. The runtime user has only `INSERT, SELECT` on `audit_log`; UPDATE and DELETE require the `migrator` role, used solely by partition-roll operations. A CI step diffs effective grants against expected; mismatches fail the build."

**Implementation impact:** **XS**.

**Classification:** **Phase 1**.

---

### HIGH-14 — `expected_completion_ms` misleading

**Position:** Agree.

**Why:** Returning a fixed value makes the mobile UX consistently lie. Server-derived is one query and zero new infrastructure.

**Architectural change:**
- Compute `expected_completion_ms` server-side from rolling p90 latency of the last 100 successful opens on the device's driver type:
  - `clip` → typical 2,000–4,000 ms
  - `sms` → typical 8,000–12,000 ms
  - `clip_sms` → primary driver's p90
- Default to a per-driver constant if the rolling window has < 10 samples.

**Affected documents:**
- `BACKEND_ARCHITECTURE.md` §14.6 — `OpenCommandService::request` computes the hint.
- `openapi/v1.yaml` — schema note that this is server-computed.

**Required document modifications (sketch):**

> `openapi/v1.yaml` `OpenCommandAccepted.expected_completion_ms` description:
>
> "Server-computed estimate based on the device's driver and recent observed latency. The UX SHOULD use this to size its progress UI, not a fixed value."

**Implementation impact:** **XS**.

**Classification:** **Phase 1**.

---

### HIGH-15 — `payment_logs` redaction fragile

**Position:** Agree.

**Why:** Blacklist redaction means a new bank field that contains PAN gets persisted before anyone notices.

**Architectural change:**
- **Allowlist-based serialization**: `PaymentLogger` defines a fixed schema of fields permitted to persist (`orderId`, `status`, `amount`, `currency`, `pan_masked`, `cardBrand`, `rrn`, `approvalCode`, etc.). Any field outside the allowlist is dropped, even if it would have been redacted by the blacklist.
- **Schema-drift scanner** runs daily over the last 7 days of `payment_logs`; regex for PAN-like (16 digits) and CVV-like patterns. Findings alert immediately.
- **Encrypt the column** (`response_redacted_encrypted TEXT`) — defence-in-depth, consistent with `payments.raw_response_encrypted`.

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §5.5 — `request_redacted_encrypted` + `response_redacted_encrypted` (renamed/encrypted).
- `BACKEND_ARCHITECTURE.md` §14.8 — `PaymentLogger` allowlist approach.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §5.5, rename columns:
>
> | `request_redacted_encrypted` | TEXT | yes | NULL | App-layer encrypted; allowlist-serialized JSON. |
> | `response_redacted_encrypted` | TEXT | yes | NULL | Same. |

**Implementation impact:** **S**.

**Classification:** **Phase 1**.

---

### HIGH-16 — App return URL trust

**Position:** Partial.

**Why:** The audit calls this "Information disclosure; phishing-style attacks." Server-side, `OrderPolicy::view` already enforces `payer_user_id = auth_user.id`. A malicious deep-link with an unowned `orderId` returns `403`, not data. The risk is not "information disclosure" — it's UX confusion (user sees "Payment failed" for an order they didn't make) and possible phishing of credentials inside the WebView.

**Architectural change:**
- **Verify** that `OrderPolicy::view` is enforced (test case: user A tries to fetch user B's order → 403). Make this a required Phase 1 acceptance test.
- **Belt-and-braces**: mobile S-34 ignores the `orderId` from the return URL for the *decision*; it queries `getOrder` for the user's most-recent `authorising` or `pending` order against the device or sub they were transacting on. If that returns terminal state, render result. If it can't be resolved, show "Payment status pending — we'll notify."
- **Do not expand** the threat model; this is a 403 enforcement, not a redesign.

**Affected documents:**
- `BACKEND_ARCHITECTURE.md` §7.2 — `OrderPolicy::view` explicit assertion.
- `UI_UX_SPECIFICATION.md` S-34 — clarify that orderId from URL is hint, not authority.

**Required document modifications (sketch):**

> `UI_UX_SPECIFICATION.md` S-34, update "Loading State":
>
> "**Loading State** — While `getOrder` resolves authoritative status. The `orderId` from the return URL is a hint only; the mobile app independently confirms the user is the order's payer before rendering any details. If the hint resolves to a 403 or 404, the app falls back to the user's most-recent in-flight order."

**Implementation impact:** **XS**.

**Classification:** **Phase 1** (test + small mobile adjustment).

---

### HIGH-17 — No `tenant_id` for future multi-tenant expansion

**Position:** **Disagree.**

**Why:** The audit treats multi-tenancy as a foreseeable 2027 path. I don't agree it's foreseeable enough to warrant changing every domain table now. Adding `tenant_id` everywhere comes with real costs the audit understates:

1. **Cognitive overhead** — every query, every policy, every join carries an extra column whose value is always `1`. Engineering attention is finite; this dilutes it.
2. **Global scopes go wrong** — once a `TenantScope` global scope exists, every test fixture needs setup, every cron command needs tenant context, every report query has to be tenant-aware. A single bug bypasses it silently.
3. **The retrofit isn't 4–8 weeks if scoped correctly** — adding `tenant_id` to top-level tables is a one-time migration job per table; the read/write paths are wrapped with a `TenantContext::current()` accessor and a global scope added in one PR. Painful but not catastrophic.
4. **There is no real customer asking for it** — the project description scopes a single-operator product. Designing for an unfunded 2027 expansion is the kind of speculation that calcifies into permanent complexity.

**Architectural change (none now):**
- Do not add `tenant_id` columns now.
- Commit a **retrofit plan** as `docs/futures/multi-tenancy-retrofit.md`: the exact migration sequence, the global scope pattern, the tenant-context shape, the timeline (estimate: 2 sprint-weeks of focused engineering when invoked).
- Re-evaluate at the first concrete white-label conversation, not before.

**Affected documents:**
- New file `docs/futures/multi-tenancy-retrofit.md` (one-pager).

**Required document modifications:** None to existing docs.

**Implementation impact:** **n/a now; estimated L if invoked** (2 sprint weeks).

**Classification:** **Accept Risk** (with documented retrofit plan).

---

### HIGH-18 — `subscriptions.latest_order_id` denormalises

**Position:** Agree.

**Why:** Either it has a clear ownership rule or it shouldn't exist. The simpler option is to drop it.

**Architectural change:**
- **Remove `subscriptions.latest_order_id`** from the schema.
- Derive on read: `SELECT order_id FROM subscription_periods WHERE subscription_id = ? AND kind IN ('initial','renewal','extension') ORDER BY id DESC LIMIT 1`. Covered by the existing `idx_subscription_periods_subscription (subscription_id, period_end)` index (close enough; can add `(subscription_id, id DESC)` if needed).
- API resources that surfaced it via `latest_order` now derive it in the Resource layer.

**Affected documents:**
- `DATABASE_ARCHITECTURE.md` §4.1.
- `BACKEND_ARCHITECTURE.md` §14.7 — `SubscriptionService` updates removed.

**Required document modifications (sketch):**

> `DATABASE_ARCHITECTURE.md` §4.1, remove the row:
>
> | `latest_order_id` | BIGINT UNSIGNED | yes | NULL | FK |
>
> Add note:
>
> "Latest paying order is **derived**, not stored — see `SubscriptionDetail` resource composition."

**Implementation impact:** **XS**.

**Classification:** **Phase 1**.

---

## 4. Aggregate View

### 4.1 By phase

| Phase | Items | Effort sum |
|---|---|---|
| **Fix Now** (decision / Phase 0) | CRIT-01, parts of CRIT-02 / CRIT-04 / CRIT-06, HIGH-06, HIGH-12 | ~2 weeks of design + decision time |
| **Phase 1** | CRIT-03, CRIT-04 (impl), CRIT-05, CRIT-06 (impl), CRIT-07, CRIT-08, CRIT-09, HIGH-01, HIGH-03, HIGH-05, HIGH-06 (impl), HIGH-07, HIGH-08 (algo), HIGH-09, HIGH-10, HIGH-11, HIGH-13, HIGH-14, HIGH-15, HIGH-16, HIGH-18 | ~7 weeks of engineering, parallelizable |
| **Phase 2** | HIGH-02, HIGH-04, HIGH-08 (UI for partial refunds) | ~2 weeks |
| **Accept Risk** | HIGH-17 (with retrofit plan filed) | n/a now |

### 4.2 By affected document

| Document | Edits required (count of findings touching it) |
|---|---|
| `TECHNICAL_SPECIFICATION.md` | 13 |
| `DATABASE_ARCHITECTURE.md` | 12 |
| `BACKEND_ARCHITECTURE.md` | 15 |
| `UI_UX_SPECIFICATION.md` | 6 |
| `openapi/v1.yaml` | 5 |
| New files (`runbooks/`, `futures/`) | 6 |

The largest cluster of edits sits in `BACKEND_ARCHITECTURE.md`, which is the right place — most of the resolutions are architectural decisions or new wiring, not surface contracts.

### 4.3 Required decisions to lock down Fix Now items

A single decision-and-sign-off session can close these:

1. **Phase 0 CLIP gate accepted** as a blocker for Phase 1 (CRIT-01).
2. **Edge-case behaviour table** in §13.9 approved by product (CRIT-02).
3. **NFR §3.1 revised** to realistic throughput (CRIT-03).
4. **Mobile copy for CLIP terminal state** changed to "Göndərildi" (CRIT-06).
5. **Open-permission cache removed** (HIGH-06).
6. **Phone reusable post-anonymisation** (HIGH-12).
7. **`tenant_id` not added; retrofit plan filed** (HIGH-17).

With those seven decisions on paper, Phase 1 can start.

---

*End of Audit Resolution Plan v1.0.*
