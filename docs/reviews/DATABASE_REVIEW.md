# DATABASE REVIEW

> Pre-Flutter audit. **Read-only — no migration was created or run.** Findings calibrated against the source (some agent "critical" claims were verified and corrected — noted inline).

---

## 0. Verdict

**Database grade: STRONG (8.5/10).** ~42 migrations, ~40 tables, clean modular layout, no duplicate/overlapping tables, enums match the app, indexes cover the hot paths, the right unique constraints exist. The real items are (a) delete-lifecycle behavior on operational chains, (b) the partitioned-table operational dependency, and (c) a handful of reserved/deprecated columns to tidy. None block v1.0.

---

## 1. Schema inventory (by module)

System (cache, jobs, job_batches, failed_jobs, sessions, password_reset_tokens) · Lookups (sim_operators, device_models, regions) · Identity (admin_users, users) · Auth (user_devices, refresh_tokens, otps, auth_attempts, user_consents) · Devices (devices, device_users, device_user_history, invitations) · Payments (card_tokens, orders, order_items, payments, payment_logs, payment_callbacks, refunds) · Subscriptions (subscriptions, subscription_periods) · Device-ops (open_commands, open_command_attempts, open_command_feedback, whitelist_changes, device_diagnostics, traccar_devices) · RBAC (complexes, permissions, role_permissions, audit_logs, user_permissions) · Settings (settings, settings_versions).

**No duplicate or overlapping tables.** Each entity has one canonical table. `UserDevice` (mobile install / push token) and `DeviceUser` (roster membership) are correctly distinct concepts.

---

## 2. Foreign keys

**Well-modelled overall.** Cascades are used for ephemeral children (user_devices, refresh_tokens, order_items, traccar_devices, user_permissions); restrict/null for records that must survive.

### Items to address

| # | Finding | Severity | Notes |
|---|---|---|---|
| DB-1 | **restrictOnDelete on operational chains** — `users → device_users`, `devices → device_users`, `device_users → subscriptions`, `users → devices(owner)`. A user soft-delete or device decommission **fails at the DB** if children exist. | **Medium** | Partly intentional (don't silently drop roster/subscriptions). But the lifecycle services must explicitly revoke/null children first, or soft-delete throws. Confirm `DeviceStatusService::decommission` + user-deletion handle this. |
| DB-2 | **Partitioned tables have NO foreign keys** — `payment_logs`, `open_commands`, `device_diagnostics` (RANGE-partitioned; InnoDB forbids FKs). Referential integrity + orphan cleanup are app-enforced. | **Medium** | Documented, intentional trade-off. The real risk is operational (see §6). Orphan cleanup on device decommission for `open_commands` is **audit-flagged — verify a cleanup path exists**. |
| DB-3 | `invitations.linked_order_id` has **no FK** (migration comment: "FK added in batch 11", which never landed). | **Low** | The invitations feature is **dormant/unimplemented** (no controllers/actions — see `API_REVIEW`). Column is never written. Low impact; add the FK if/when invitations ship. *(Corrected from an agent "Critical" — it is not, because nothing uses the table.)* |
| DB-4 | `settings.updated_by_admin_id`, `settings_versions.created_by_admin_id` have **no FK** to admin_users. | **Low** | Informational columns; admins are never hard-deleted (offboarded status). Document as informational or add nullOnDelete FK. |
| DB-5 | `device_user_history.actor_id` has **no FK** (actor may be user or admin). | **Low** | Intentional — an immutable history/audit row must survive actor changes. Document. |

---

## 3. Indexes — GOOD

Hot-path coverage verified: `orders(status,created_at | payer,created_at | purpose,created_at)`, `otps(phone,purpose | email,purpose | expires_at)`, `device_users(device,status | user,status | role)`, `subscriptions(status,ends_at | ends_at | auto_renew,ends_at)`, `whitelist_changes(device,status,priority,seq | status,next_attempt_at)`, unique on `payments.bank_transaction_id`, `payment_callbacks.payload_hash`, `user_devices(user,install_uuid)`, `refresh_tokens.token_hash`. **No critical index gaps.**

One to watch (Low): `SubscriptionQuery::hasActiveForUser` filters subscriptions by `status,ends_at` AND `whereHas(deviceUser → user_id)` — a cross-table join; the subscriptions-side index covers it, but if this becomes hot (called on every authed `/v1/me`), consider denormalising "has_active" or caching (see `PERFORMANCE_REVIEW`).

---

## 4. Unique constraints — GOOD

`users.phone` (UNIQUE, soft-delete anonymises so a real number is reusable), `users.email` (UNIQUE nullable — multiple NULLs allowed, fine for the registration model), `orders(payer,idempotency_key)`, `payment_callbacks.payload_hash` (webhook dedup), `device_users(device,user,is_active)` (generated-column active-uniqueness), `invitations.token`. 

- **DB-6 (Medium, conditional):** `card_tokens` unique on `(user_id, bank_token_encrypted)` (encrypted VARBINARY). If the encryption key is ever rotated, the same bank token re-encrypts differently → uniqueness silently breaks. Only relevant once save-card ships (currently `save_card_enabled=false`). Plan a key-rotation procedure before enabling card tokens.
- **DB-7 (Low):** `device_users(...is_active)` active-uniqueness depends on a STORED generated column. Works on the target MariaDB; just note it's app+DB-coupled.

---

## 5. Nullable / reserved / deprecated columns

| Column | State | Action |
|---|---|---|
| `users.password` | **Reserved** (future Security feature; hidden; unused) | Keep — intentional forward-compat |
| `otps.channel`, `otps.email` | **Active** (email-OTP — Registration P1) | — (the agent's "no OtpChannel enum" note is a non-issue: `channel` is a plain string column set by `OtpService`, not enum-cast — verified) |
| `devices.whitelist_capacity_used` | **DEPRECATED** (compute-on-read per R-DOM-14) | **Drop** in a future migration after confirming no reads — small cleanup |
| `devices.sim_credit_minor / sim_credit_checked_at / sim_status` | **Phase-0 placeholders** (SIM lifecycle deferred) | Keep or drop; currently not driven by core code |
| `device_users.access_window_start/end/days_mask` | **P2 placeholders** (scheduled access) | Keep — reserved for a planned feature |

---

## 6. Operational dependency (Medium — track for ops, not code)

The 3 RANGE-partitioned tables (`payment_logs`, `open_commands`, `device_diagnostics`) need a **monthly partition-roll cron**. If it fails, inserts fall into MAXVALUE and queries slow; long-term it can block inserts. **This is a release-ops checklist item** (monitor the cron + alert), not a schema defect.

---

## 7. Migration hygiene — GOOD

All migrations are additive + forward-compatible. The recent `add_email_channel_to_otps` and the two BirPay column migrations could be squashed into their base tables for a pre-release "clean schema", but this is cosmetic (Low) and risky to do now (they're already applied in prod). **Leave as-is.**

---

## 8. Summary

| Severity | Count | Items |
|---|---|---|
| Critical | 0 | — |
| High | 0 | — |
| Medium | 3 | DB-1 (delete lifecycle), DB-2 (partition orphan cleanup — verify), DB-6 (card-token key rotation, conditional) + the partition-roll ops dependency |
| Low | 4 | DB-3 (invitations FK, dormant), DB-4 (settings FK), DB-5 (history FK), deprecated `whitelist_capacity_used` |

**Database is release-ready.** Address DB-1 (lifecycle) + verify DB-2 (orphan cleanup) as part of go-live ops; the rest are post-Flutter cleanups.
