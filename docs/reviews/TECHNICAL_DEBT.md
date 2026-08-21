# TECHNICAL DEBT — PRIORITISED REGISTER

> Pre-Flutter audit. Every item: **Severity · Why it's a problem · Risk · When to fix.** Calibrated against the source (agent over-ratings corrected). **No code was changed.**

Legend for "When": **B = before Flutter** (blocks or misleads client work) · **G = before GA / launch traffic** · **P = post-Flutter / backlog**.

---

## CRITICAL
**None.** No data-loss, security, or correctness blocker was confirmed. (Two agent-flagged "criticals" were verified false: `whitelist_changes.max_attempts` HAS a DB default of 5; `invitations.linked_order_id` missing-FK is on a dormant, unimplemented feature.)

---

## HIGH

| ID | Item | Severity | Why it's a problem | Risk | When |
|---|---|---|---|---|---|
| TD-1 | **OpenAPI spec carries ~45 unimplemented design endpoints** (notifications, invitations, consents, privacy, admin users/metrics/lookups/reports/feature-flags/notification-templates, settings/{key}). | High | Swagger/ReDoc/Postman/Bruno present endpoints that 404. A Flutter dev will build against phantom endpoints. | Wasted client effort, broken integrations, eroded trust in the contract. | **B** |
| TD-2 | **OTP/registration email + phone-SMS sent synchronously** on the request path. | High | A worker blocks on SMTP/SMS per register/login; transient outage fails the user; serialises under load. | Slow auth UX; concurrency exhaustion at scale; mild DoS amplification. | **G** |
| TD-3 | **N+1 on admin device list (`whitelist_used`) + detail (subscription per roster member)**. | High | ~101 queries per device page; N+ per detail view. | Admin panel slows as fleet grows (admin-only, not mobile). | **G** |

---

## MEDIUM

| ID | Item | Severity | Why | Risk | When |
|---|---|---|---|---|---|
| TD-4 | **Response-shape multiplicity** (unified envelope vs legacy bare auth vs list vs 204 vs domain). | Medium | Mobile client must handle 5 shapes; legacy ≠ new 202. | Client complexity; inconsistency. (New mobile surface IS unified — mitigated.) | G/P |
| TD-5 | **restrictOnDelete on operational chains** (user/device soft-delete + decommission with children). | Medium | DB throws if roster/subscriptions exist and the lifecycle service doesn't revoke/null first. | Failed soft-delete/decommission; stuck records. | G |
| TD-6 | **Orphan cleanup for partitioned `open_commands` on device decommission** (no FK, app-enforced) — *audit-flagged, verify a cleanup path exists.* | Medium | Decommission may leave dead commands in the partitioned table. | Query bloat; partition churn. | G |
| TD-7 | **Whitelist sync resilience for extended device offline** — *audit-flagged:* a change may exhaust its 5 attempts (minute-scale backoff) while a device is offline and not be re-queued when it returns. | Medium | Whitelist (physical access) can drift from the DB roster after long downtime. | Resident loses access until manual `adminResyncWhitelist`. **Verify the retry/rescan behavior** before relying on it. | G |
| TD-8 | **Open-command stale-expiry hardcoded 120s** — *audit-flagged.* | Medium | Under queue backlog/timeout a command may expire while still dispatching (gate could open after the client gave up). | Double-open / lost confirmation. Verify + make configurable. | G |
| TD-9 | **Documented error codes (402/409/503) not emitted** by orders/open/subscription controllers. | Medium | Spec promises errors the backend doesn't produce. | Client builds dead error paths. | G |
| TD-10 | **Inline Traccar telemetry ingestion** (synchronous webhook). | Medium | Serialises webhook workers at fleet scale. | Telemetry lag / dropped webhooks under load. | G |
| TD-11 | **Bootstrap + `/me` uncached** (settings + 2 subscription queries per launch). | Medium | Hot on every app open. | Avoidable DB load at peak. | P |
| TD-12 | **card_tokens unique on encrypted column** → key-rotation breaks uniqueness. | Medium (conditional) | Only when save-card ships (`save_card_enabled=false` now). | Future key rotation corrupts card uniqueness. | P (before enabling cards) |
| TD-13 | **SMS / BLE drivers not production-complete** (SMS provider Phase-0; BLE reserved, no graceful error). | Medium | Fallback open via SMS unproven; a `ble` driver_type would fail opaquely. | Fallback reliability gap; only matters when Traccar is down. | P |

---

## LOW

| ID | Item | Severity | Why | When |
|---|---|---|---|---|
| TD-14 | Cursor-pagination logic duplicated in 4 Query classes. | Low | Drift risk; extract a trait. | P |
| TD-15 | `devices.whitelist_capacity_used` DEPRECATED column still in schema. | Low | Dead column; drop after confirming no reads. | P |
| TD-16 | `invitations.linked_order_id` has no FK (dormant feature). | Low | Add FK if/when invitations ship. | P |
| TD-17 | `settings(_versions).*_admin_id` no FK (informational). | Low | Add nullOnDelete FK or document. | P |
| TD-18 | Admin test/ops endpoints not throttled. | Low | Compromised admin could spam test emails / sandbox payments. | G |
| TD-19 | `complex_manager` device query loads before scope filter. | Low | Existence inference; outcome already 404-safe. Pre-filter by complex_id. | G |
| TD-20 | `$guarded=['id']` (allow-all-but-id) on sensitive models. | Low (latent) | Future `create($request->all())` could set a sensitive field. Prefer `$fillable`. | P |
| TD-21 | `sms_login` feature flag hardcoded false; legacy phone-OTP coexists. | Low | Remove/clarify retired paths in spec. | P |
| TD-22 | Reserved/placeholder columns (`users.password`, `devices.sim_*`, `device_users.access_window_*`). | Low (intentional) | Forward-compat; keep, just track. | — |
| TD-23 | Monthly partition-roll cron is a critical ops dependency (payment_logs/open_commands/device_diagnostics). | Low (ops) | If it fails, inserts pile into MAXVALUE → slow. | G (monitor) |

---

## Debt posture

- **Code debt is unusually low** — 0 TODO/FIXME, no dead code, consistent architecture. Most "debt" here is (a) **doc/spec drift** (TD-1, the one true pre-Flutter blocker), (b) **scale-hardening** (queue I/O, N+1, cache), and (c) **DeviceComm resilience** items to **verify** before relying on whitelist/open under adverse conditions.
- **The single highest-value pre-Flutter action is TD-1** (reconcile the spec) — it's doc-only, no backend code, and directly protects the Flutter build.
- **Before launch traffic:** TD-2 (queue email/SMS), TD-3 (admin N+1), TD-5–TD-10 (lifecycle + DeviceComm resilience, after verification), TD-18/19 (cheap security tidy).
