# Notifications — Database Plan

**Version:** 0.1 (DRAFT — pending documentation-set sign-off)
**Status:** DRAFT change-plan. It **references** the canonical schema and specifies the **delta** to be folded into `DATABASE_ARCHITECTURE.md §7` **after** sign-off. **No migration is written and no canonical doc is edited by this file.**
**Date:** 2026-08-11
**Depends on:** [NOTIFICATIONS_RECONCILIATION.md](NOTIFICATIONS_RECONCILIATION.md) (B1/B2/E1/E2) · DB Arch §7 (§7.1–7.4), §1.3 `user_devices` · migration batch **`11_notifications`** (the §11 plan's `08_notifications` slot is occupied by the deployed `08_rbac`; the notifications bundle is a new batch after `10_access`).

---

## 1. Canonical tables — KEPT AS-IS (referenced, not restated)

The following exist in `DATABASE_ARCHITECTURE.md §7` and are **adopted unchanged**. This plan does **not** re-specify their columns; see the canonical sections.

| Table | Canonical | Role | Change |
|---|---|---|---|
| `notification_templates` | §7.1 | Admin-editable template catalogue; `default_channels_mask`, `category`, `is_user_mutable` | **none** |
| `notification_template_locales` | §7.2 | Per-locale (az/ru/en) subject + body | **none** |
| `notifications` | §7.3 | **Per-channel** per-user record; drives inbox + delivery outcome; **monthly partitioned**; retention 12mo inapp / 90d others; `uq_notifications_dedupe (user_id, dedupe_key, channel)` | **+1 nullable column** (§3) |
| `user_notification_settings` | §7.4 | Per-user mutable-category channel prefs | **none** (UI deferred, schema preserved — E6) |
| `user_devices` | §1.3 | Holds `push_token`, `push_token_updated_at`, `push_invalid` (per install) | **none** — reused as the token store |

**Locked invariants** (see [INVARIANTS](NOTIFICATIONS_INVARIANTS.md)): per-channel rows (R-NOT-01), dedupe `(user_id, dedupe_key, channel)` (R-NOT-05), monthly partitioning (R-NOT-15), retention (R-NOT-16), no new push-token table (R-NOT-09).

## 2. NEW table — `notification_campaigns` *(proposed DB Arch §7.5)*

**Purpose.** One row per admin-initiated notification (a "send"). Records the compose + audience + dispatch stats. Automatic business notifications do **not** use this table (their `campaign_id` is NULL).

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `created_by_admin_id` | BIGINT UNSIGNED | no | — | FK → `admin_users.id` |
| `type` | VARCHAR(40) | no | — | MVP: `system` |
| `title` | VARCHAR(160) | no | — | rendered as-is (free-text) |
| `body` | TEXT | no | — | rendered as-is (free-text) |
| `language` | ENUM('az','ru','en') | no | — | single language per campaign (D7) |
| `audience_scope` | ENUM('all_users','user_ids','filter','complex') | no | — | (D6) |
| `audience_filter` | JSON | yes | NULL | explicit `user_ids[]`, or `{q,complex_id,role,subscription_status}`, or `{complex_id}` |
| `status` | ENUM('draft','queued','sending','sent','failed') | no | `draft` | lifecycle |
| `total_recipients` | INT UNSIGNED | yes | NULL | server-resolved distinct `user_id` at send |
| `sent_count` | INT UNSIGNED | no | 0 | successful deliveries (rollup) |
| `failed_count` | INT UNSIGNED | no | 0 | failed deliveries (rollup) |
| `confirmed_at` | TIMESTAMP | yes | NULL | admin confirmation timestamp (D8) |
| `sent_at` | TIMESTAMP | yes | NULL | dispatch start |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

- **Indexes:** `idx_campaigns_created (created_by_admin_id, created_at)`, `idx_campaigns_status (status)`.
- **FK:** `created_by_admin_id` → `admin_users.id` ON DELETE RESTRICT.
- **Soft delete:** No. **Partitioning:** No (low volume).
- **Audit:** the send is also emitted as an `AuditableEvent` (audit module) — the campaign row is the operational record, not the audit log.

## 3. `notifications` — the single additive change *(proposed edit to DB Arch §7.3)*

Add exactly **one** nullable column; everything else in §7.3 is unchanged.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `campaign_id` | BIGINT UNSIGNED | **yes** | NULL | FK → `notification_campaigns.id`; NULL for automatic business notifications |

- **FK:** `campaign_id` → `notification_campaigns.id` ON DELETE SET NULL.
- **Index:** `idx_notifications_campaign (campaign_id)` — campaign delivery rollup.
- **`template_key` stays NOT NULL** (E2/B2). Admin-campaign rows use the **reserved constant** `template_key = 'system.admin_campaign'`; the free-text title/body live in `payload` (R-NOT-03). Business rows use their real template key.

> **`notifications.payload` content** (R-NOT-03/17; [ARCHITECTURE §5.1](NOTIFICATIONS_ARCHITECTURE.md)): rendered `title`, `body`, `type`, deep-link entity ids. It drives the inbox **and** is the source for the FCM `notification{title,body}` + `data: { type, notification_id, ids }` message. No secrets/tokens; PII-minimal. A campaign carries **no** `channels_mask` — admin campaigns fan out to the fixed **push + inapp** channels (D1/R-NOT-04).

## 4. How a row is produced (mapping to the model)

| Source | `template_key` | `campaign_id` | `channel` rows | `dedupe_key` |
|---|---|---|---|---|
| Business (e.g. visitor opened) | real key (e.g. `device.opened`) | NULL | inapp (+push if template mask) | `visitor_opened:{open_command_id}` |
| Subscription expiring | `subscription.expiring_7d` … | NULL | per template mask | `sub_expiring:{subscription_id}:{cycle}` |
| Admin campaign | `system.admin_campaign` | campaign id | inapp + push (fixed MVP channels; no campaign mask) | `campaign:{campaign_id}:{user_id}` |

Per-channel = one row per selected channel (R-NOT-01). Recipient = distinct `user_id` (R-NOT-02); multi-device fan-out happens inside push delivery (R-NOT-08), not as extra rows.

## 5. Migrations (roadmap only — NOT written here)

- All notification tables are created in a **new `11_notifications` batch** (nothing is deployed yet): `notification_templates`, `notification_template_locales`, `notification_campaigns`, `notifications` (partitioned), `user_notification_settings`. The `notifications` row already carries `campaign_id` at creation (no separate alter). The §11 plan's `08_notifications` slot is taken by the deployed `08_rbac`, so this batch is appended after `10_access`.
- **Seeds (post-approval):** `NotificationTemplatesSeeder` rows for the initial live business types + az/ru/en locale rows (visitor-opened, `subscription.*`). Admin campaigns need no template seed.
- **No `device_tokens`/`push_tokens` table** — R-NOT-09.
- **No UMKa tables/columns** — R-NOT-21.

## 6. Retention & partitioning (unchanged, restated for the implementer)

- `notifications`: monthly RANGE partition on `partition_key`; retention **12mo inapp / 90d non-inapp** (DB Arch §7.3; R-NOT-15/16). The retention sweep is an existing-pattern scheduled job scope (do not remove).
- `notification_campaigns`: unpartitioned; retained indefinitely (operational/audit record).

## 7. Canonical edits this plan will require (post-approval)
`DATABASE_ARCHITECTURE.md`: **new §7.5 `notification_campaigns`**; **§7.3** add `campaign_id` (+ reserved `template_key` note). Nothing else in §7 changes. (Tracked in [RECONCILIATION §C](NOTIFICATIONS_RECONCILIATION.md).)
