# Salam Həyətimiz — Document Changelog

All versioned changes to the project documents live here. Every change links to the originating finding ID in [AUDIT_REPORT.md](AUDIT_REPORT.md) and the resolution decision in [AUDIT_RESOLUTION_PLAN.md](AUDIT_RESOLUTION_PLAN.md).

---

## v1.3 (proposed) — 2026-08-11 — Notifications Reconciliation Batch

**Purpose:** Fold the approved Notification module design (`docs/notifications/`) into the canonical corpus per [NOTIFICATIONS_RECONCILIATION.md](notifications/NOTIFICATIONS_RECONCILIATION.md) §C. **No new architecture decisions** — MVP-scoping + reconciliation of the pre-existing notification spec (Tech Spec §17 / DB Arch §7 / Backend Arch §14.9). UMKa remains **out of scope** for notifications.

### Documents Affected (this batch)

| File | Change |
|---|---|
| `DATABASE_ARCHITECTURE.md` | new §7.5 `notification_campaigns`; §7.3 `+campaign_id` + index/FK + payload note |
| `TECHNICAL_SPECIFICATION.md` | §17.1 MVP channel scope; §17.2 hybrid note; §17.3 dedupe `(user_id,dedupe_key,channel)` + payload model; §17.5 soft-invalidate; **new §17.6** Admin-Initiated Notifications |
| `BACKEND_ARCHITECTURE.md` | §14.9 admin campaign path + `NotificationCampaignPolicy` + visitor-opened consumer |
| `PROJECT_CONSTITUTION.md` | new §3.5 (R-NOT-01…22 adopted) |
| `docs/flutter/SCREEN_FLOW.md` | §9 planned MVP inbox + deep-link |

### Locked decisions carried in
push+inapp MVP (sms/email future) · per-channel rows · dedupe `(user_id,dedupe_key,channel)` · hybrid templates · `data:{type,notification_id,ids}` payload · admin campaigns (no channel mask) · user_id dedupe · soft `push_invalid` · VL110C/Traccar/OpenCommand only · UMKa out of scope.

### Follow-up reconciliation — resolved 2026-08-11 (decisions D1–D5)
The items deferred above were closed by the locked decisions D1–D5 (see [decisions/notifications-reconciliation.md](decisions/notifications-reconciliation.md)): **D1** RBAC "Variant B" grants applied to `RBAC_PERMISSION_MATRIX` / `ADMIN_PERMISSION_MATRIX` (super + Operator + Complex Manager send; Support view); **D2** canonical naming (`NotificationDispatcher` / `PushClient` / `FcmPushClient` / `FakePushClient` / `SendPushNotificationJob`) — module docs aligned, **no code renamed**; **D3** `PUT /v1/notifications/push-token`; **D4** `DELETE /v1/notifications/push-token`; **D5** admin campaign endpoints + schemas added to `openapi/v1.yaml`. `LOCALIZATION_SPECIFICATION` needs no change (§4.6/§6.8).

---

## v1.2 — 2026-06-14 — Transport Amendment (UMKa 310 / Traccar / BLE)

**Purpose:** Apply the owner-approved device-communication transport pivot — see
[FINAL_TRANSPORT_DECISION.md](FINAL_TRANSPORT_DECISION.md),
[DOCUMENT_CHANGE_PLAN.md](DOCUMENT_CHANGE_PLAN.md), and the dedicated
[TRANSPORT_MIGRATION_CHANGELOG.md](TRANSPORT_MIGRATION_CHANGELOG.md). Confirmed hardware is the
GLONASSSoft **UMKa 310 v2L** telematics tracker (not a CLIP GSM relay). Device communication becomes a
hybrid: **BLE** (local), **Traccar** (remote + telemetry + command), **SMS** (fallback). The CLIP
driver, voice gateway, `VoiceGatewaySelector`, per-operator CLI validation, and the on-device caller-ID
whitelist are retired. **CRIT-01** and **CRIT-03** are retired; **CRIT-06** is resolved.

### Documents Affected

| File | v1.1 → v1.2 | Footprint |
|---|---|---|
| [PROJECT_CONSTITUTION.md](PROJECT_CONSTITUTION.md) | header + §6 (R-GSM-01..13), R-DOM-05, R-SEC-04, principle 4, R-WF-07, Fix-Now | Driver taxonomy; CRIT-01/03 retired; CRIT-06 resolved; BLE exception |
| [TECHNICAL_SPECIFICATION.md](TECHNICAL_SPECIFICATION.md) | header + §12 supersede banner + glossary + assumptions + §3.1 + FR-DEV/OPEN/OWN/ADM | §12 superseded; CLIP/voice retired |
| [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md) | header + §2.2/§6.1/§6.1.1/§6.2/§6.3 notes | Notes only — **no DDL change**; enum values reinterpreted |
| [BACKEND_ARCHITECTURE.md](BACKEND_ARCHITECTURE.md) | header + §13 integrations + §14.6 + queues | VoiceGateway/ClipDriver → TraccarClient/TraccarDriver/Ble/Sms |
| [openapi/v1.yaml](openapi/v1.yaml) | `1.1.0 → 1.2.0`; driver enum ×5; 2 descriptions | `clip/sms/clip_sms/mqtt` → `traccar/ble/sms`; validator green |

**Not edited (deferred to batch 09-B implementation):** PHP `DriverType` enum, `config/domain/device_comm.php`,
`config/integrations/voice.php` (removal), `database/seeders/Lookups/DeviceModelSeeder.php` (RTU5024 → UMKa).
The `DriverType` enum change is breaking and coordinated with OpenAPI + a data migration at the start of 09-B.

---

## v1.1 — 2026-06-09 — Post-Audit Baseline

**Purpose:** Apply the 26 accepted resolutions from the audit (24 in full, 2 partial, 1 disagreement deferred to a filed retrofit plan). This is the pre-Phase-1 baseline. All five documents move from v1.0 to v1.1; the OpenAPI moves from `1.0.0` to `1.1.0`. No existing content was removed in the doc body unless explicitly superseded by a `~~strikethrough~~` deprecation note pointing to the v1.1 replacement.

### Documents Affected

| File | v1.0 → v1.1 | New content footprint |
|---|---|---|
| [TECHNICAL_SPECIFICATION.md](TECHNICAL_SPECIFICATION.md) | header + 14 sections | Largest edit surface |
| [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md) | header + 11 sections | 2 new tables, 7 new columns, 1 deprecation |
| [BACKEND_ARCHITECTURE.md](BACKEND_ARCHITECTURE.md) | header + 7 sections | 1 new CI check group, 1 new middleware, 1 new selector |
| [UI_UX_SPECIFICATION.md](UI_UX_SPECIFICATION.md) | header + 7 screens | New states on `S-11 / S-12 / S-15 / S-34 / S-56 / A-02 / A-99` |
| [openapi/v1.yaml](openapi/v1.yaml) | header + 7 paths/schemas | 3 new operations, 1 new schema, 2 enum extensions |
| [futures/multi-tenancy-retrofit.md](futures/multi-tenancy-retrofit.md) | **NEW** | One-page retrofit plan, not active work |

### Critical Resolutions

#### CRIT-01 — CLIP Caller-ID validation as a Phase-0 gate
- `TECHNICAL_SPECIFICATION.md` §0 (Items Resolved at v1.1), §12.6.1 (new per-operator validation table + override map), §19 (Phase 0 expanded with eight gates G1–G8).
- `BACKEND_ARCHITECTURE.md` §14.6: `DriverResolver::for(Device)` consults `OperatorFallbackPolicy` reading `config/domain/device_comm.php`.

#### CRIT-02 — Subscription edge cases enumerated
- `TECHNICAL_SPECIFICATION.md` §0 + §3.7 + new §13.9 ("Edge-case Behaviours") with a table of 8 mixed-state situations.
- `UI_UX_SPECIFICATION.md` S-11 gained per-caller `suspension_reason` copy and CTA.
- `openapi/v1.yaml` `Device.suspension_reason` enum extended with `owner_sub_expired_others_active` and `device_suspended`.

#### CRIT-03 — Realistic GSM throughput NFR + dual gateway
- `TECHNICAL_SPECIFICATION.md` §3.1 (revised throughput table — 5 RPS sustained / 50 RPS burst / 200 RPS peak), §12.6.2 (new voice-gateway topology section), §12.10 (gateway-unavailable behaviour), §18.2.1 + §18.3 (dual gateway in topology and sizing).
- `BACKEND_ARCHITECTURE.md` §14.6: new `VoiceGatewaySelector` adapter.

#### CRIT-04 — Key rotation runbooks and JWKS
- `TECHNICAL_SPECIFICATION.md` §15.2 (`kid` on JWTs), new §15.5.1 (rotation cadence table referencing per-secret runbooks).
- `BACKEND_ARCHITECTURE.md` new §7.1.1 (Key identification & JWKS).
- `openapi/v1.yaml` new `GET /.well-known/jwks.json` (admin host); new `Jwks` schema.

#### CRIT-05 — HA Redis with logical-DB split
- `TECHNICAL_SPECIFICATION.md` §18.2.1, §18.3 (Sentinel topology in sizing table).
- `BACKEND_ARCHITECTURE.md` §11.1.1 (new section: logical-DB split + `maxmemory-policy` per DB + circuit breaker).

#### CRIT-06 — "Opened" vs "Dispatched" honesty for CLIP
- `TECHNICAL_SPECIFICATION.md` new FR-OPEN-07.
- `UI_UX_SPECIFICATION.md` S-12 success-state rewritten to branch on `driver_confirms_actuation`; copy distinguishes "Açıldı" from "Göndərildi"; optional in-app prompt.
- `openapi/v1.yaml` `OpenCommandAccepted.driver_confirms_actuation` (new field); new `POST /commands/{id}/feedback` endpoint (`submitOpenFeedback`).
- `DATABASE_ARCHITECTURE.md` new §6.1.2 `open_command_feedback` table.

#### CRIT-07 — Bank-callback hardening (raw body + PENDING + IP-list monitor)
- `TECHNICAL_SPECIFICATION.md` §14.3 rewritten with three explicit defences and the `getOrderStatus` non-negotiable.
- `BACKEND_ARCHITECTURE.md` §6.5: new `CaptureRawBody` middleware; `VerifyKapitalSignature` row updated to emit partitioned metric.

#### CRIT-08 — SPKI cert pinning with dual pins + escape hatch
- `TECHNICAL_SPECIFICATION.md` §15.4 + §15.8 rewritten for public-key pinning, dual pins, app-release-driven rotation, CDN-side monitoring, emergency unpinned hostname.

#### CRIT-09 — TOTP recovery codes promoted from P2 to MVP
- `TECHNICAL_SPECIFICATION.md` new FR-AUTH-08, new §15.2.1 with the recovery-code lifecycle.
- `DATABASE_ARCHITECTURE.md` §1.2 added `recovery_codes_hashes JSON` and `recovery_codes_generated_at` to `admin_users`.
- `UI_UX_SPECIFICATION.md` A-02 rewritten (mode toggle TOTP / recovery), A-99 gained recovery-codes section.
- `openapi/v1.yaml` `adminVerify2fa` accepts `oneOf {totp, recovery_code}`; response carries `used_recovery_code` and `recovery_codes_remaining`; new `POST /admin/auth/recovery-codes` (`regenerateRecoveryCodes`).
- `BACKEND_ARCHITECTURE.md` new §7.1.2 (auth flow for recovery codes).

### High-Severity Resolutions

#### HIGH-01 — Whitelist drain burst guard + UX provisioning chip
- `DATABASE_ARCHITECTURE.md` §6.3 added `priority TINYINT` and `seq BIGINT` columns; updated drain-order index; added burst-guard note.
- `UI_UX_SPECIFICATION.md` S-15 added the burst-guard tooltip and the per-row "Provisioning…" chip.
- `openapi/v1.yaml` `createInvitation` 409 gained `too_many_pending_changes` example.

#### HIGH-02 — Weekly whitelist drift audit (Phase 2)
- `BACKEND_ARCHITECTURE.md` §10 added `devices:audit-whitelist` weekly schedule.
- `TECHNICAL_SPECIFICATION.md` §19 Phase 4 listed under "v1.1 Phase 2 additions."

#### HIGH-03 — Driver fallback policy
- `TECHNICAL_SPECIFICATION.md` new §12.7 "Driver Fallback Policy" + FR-OPEN-08; §12.10 failure-modes table extended.
- `DATABASE_ARCHITECTURE.md` §2.2 added `device_models.fallback_open_driver`; new §6.1.1 `open_command_attempts` table.
- `BACKEND_ARCHITECTURE.md` §14.6 driver-fallback section + new `OpenCommandAttempt` model in catalog.

#### HIGH-04 — SIM lifecycle columns (placeholders now, collectors Phase 2)
- `DATABASE_ARCHITECTURE.md` §3.1 added `sim_credit_minor`, `sim_credit_checked_at`, `sim_status` to `devices`.
- `TECHNICAL_SPECIFICATION.md` Phase 4 / "v1.1 Phase 2 additions" lists collectors.

#### HIGH-05 — Whitelist capacity enforced server-side
- `DATABASE_ARCHITECTURE.md` §2.2 `device_models.whitelist_capacity` is now NOT NULL with default 100; §3.1 `devices.whitelist_capacity_used` marked DEPRECATED (compute on read).
- `openapi/v1.yaml` `createInvitation` 409 gained `roster_capacity_exceeded` example.

#### HIGH-06 — Open-permission cache removed (contradiction resolved)
- `BACKEND_ARCHITECTURE.md` §11.1: the cache row was struck; an explicit note documents the decision and the latency budget that justifies it.

#### HIGH-07 — `device_users` STORED unique-active concurrency test, fallback plan
- `DATABASE_ARCHITECTURE.md` §3.2 appended fallback-plan note (Plan A = STORED generated unique; Plan B = mirror table) and named the CI concurrency test.
- `BACKEND_ARCHITECTURE.md` §15.1 lists `ci:device-users-uniqueness` as a build-failing invariant check.

#### HIGH-08 — Refund pro-rata math
- `TECHNICAL_SPECIFICATION.md` new §14.5.1 with the algorithm and a worked example.
- `BACKEND_ARCHITECTURE.md` §14.8 `RefundService::executeApprovedRefund` references the algorithm.

#### HIGH-09 — Device-sale order ↔ device link
- `TECHNICAL_SPECIFICATION.md` new §14.1.1 stating "unassigned device must exist before sale order."

#### HIGH-10 — Owner self-delete successor policy
- `TECHNICAL_SPECIFICATION.md` §3.7 added the policy; §13.9 captures the case.
- `UI_UX_SPECIFICATION.md` S-56 added the `successor_required` state.
- `openapi/v1.yaml` `deleteMe` 409 gained `successor_required` example with blocking-device details.

#### HIGH-11 — Two Reverb nodes + sticky LB
- `TECHNICAL_SPECIFICATION.md` §18.2.1 + §18.3 (sizing table: 2× Reverb).

#### HIGH-12 — Phone reuse policy decided; immediate anonymisation
- `DATABASE_ARCHITECTURE.md` §0.3 (soft-delete policy) and §1.1 (users) clarified: phone is reusable; PII anonymised immediately on soft-delete.
- `TECHNICAL_SPECIFICATION.md` §3.7 reflects the same.
- `UI_UX_SPECIFICATION.md` S-56 success copy adjusted ("Hesabınız silindi … bərpa …").

#### HIGH-13 — Audit-log grants made explicit
- `DATABASE_ARCHITECTURE.md` §8.1 expanded with the GRANT statements, CI integrity check, and daily monitor.
- `BACKEND_ARCHITECTURE.md` §15.1 lists `ci:grants:audit-log-immutable` as a build-failing check.

#### HIGH-14 — `expected_completion_ms` server-computed
- `TECHNICAL_SPECIFICATION.md` new FR-OPEN-09.
- `openapi/v1.yaml` `OpenCommandAccepted.expected_completion_ms` description rewritten.

#### HIGH-15 — `payment_logs` allowlist + encryption
- `DATABASE_ARCHITECTURE.md` §5.5 renamed columns to `request_redacted_encrypted` / `response_redacted_encrypted`, described allowlist serialization + encryption.
- `BACKEND_ARCHITECTURE.md` §14.8 `PaymentLogger` rewritten; `PaymentLogsScannerJob` added.
- `BACKEND_ARCHITECTURE.md` §15.1 added `ci:payment-logs:no-pan` build-failing check.

#### HIGH-16 — Return URL is hint, not authority
- `UI_UX_SPECIFICATION.md` S-34 Loading-State rewritten — server-side `OrderPolicy::view` is authoritative; URL `orderId` is a hint; fallback to most-recent in-flight order.

#### HIGH-17 — Multi-tenancy: deferred with retrofit plan
- **No schema or doc changes** to v1.0 docs.
- New file [futures/multi-tenancy-retrofit.md](futures/multi-tenancy-retrofit.md) with trigger conditions, sequencing, and a "do not over-engineer" guard.

#### HIGH-18 — `subscriptions.latest_order_id` removed
- `DATABASE_ARCHITECTURE.md` §4.1 column struck through with deprecation note pointing to `subscription_periods`-derived computation in the Resource layer.

### New Operations in OpenAPI (counts vs v1.0)

| Surface | v1.0 | v1.1 | Delta |
|---|---|---|---|
| Paths | 84 | 87 | +3 |
| Operations | 97 | 100 | +3 (`getJwks`, `regenerateRecoveryCodes`, `submitOpenFeedback`) |
| Schemas | 80 | 81 | +1 (`Jwks`) |
| `$ref`s | 95 | 96 | all resolve |
| Tags | 26 | 26 | unchanged |

Validated via `php docs/openapi/validate.php` — all checks green.

### Notes for Implementers

1. **Phase 1 timeline is now 5 weeks (was 4)** — the extra week absorbs HA Redis/dual GSM gateway deployment, recovery codes, callback hardening, key rotation wiring, payment-log allowlist, and the CI grant-integrity check. None individually is large; the aggregate matters.
2. **Phase 2 carries:** weekly whitelist drift audit, SIM-credit collectors, partial-refund admin UI.
3. **Phase 0 acceptance gates G1–G8** are listed in `TECHNICAL_SPECIFICATION.md` §19 — Phase 1 should not start without an explicit decision on each.
4. **Seven Fix-Now decisions** are required (covered in `AUDIT_RESOLUTION_PLAN.md` §4.3) — all are now reflected in the v1.1 docs but require product/DevOps sign-off in writing.

---

## v1.0 — 2026-06-09 — Initial Baseline

Original document set produced as the pre-development specification. See individual document headers for authorship and review notes.
