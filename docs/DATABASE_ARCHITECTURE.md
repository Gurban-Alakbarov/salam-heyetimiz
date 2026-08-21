# Salam Həyətimiz — Physical Database Architecture

**Version:** 1.2
**Status:** Active; v1.2 applies the transport pivot (notes only — no DDL change to existing tables)
**Source spec:** [TECHNICAL_SPECIFICATION.md](TECHNICAL_SPECIFICATION.md) v1.2
**Changelog:** see [CHANGELOG.md](CHANGELOG.md) and [TRANSPORT_MIGRATION_CHANGELOG.md](TRANSPORT_MIGRATION_CHANGELOG.md). v1.1 applied the audit resolutions; **v1.2 (2026-06-14)** applies [FINAL_TRANSPORT_DECISION.md](FINAL_TRANSPORT_DECISION.md). The device-comm tables (§6) are **structurally unchanged**; v1.2 reinterprets the `driver` enum values (`traccar/ble/sms`), marks `open_command_attempts.voice_gateway_id` vestigial, and re-scopes the `whitelist_changes` outbox to config/credential provisioning. Two new tables (BLE entitlements, Traccar device mapping) are specified for batch 09-B.
**DB Engine:** MariaDB 11.x, InnoDB, `utf8mb4_unicode_ci`
**Money:** Always stored in **minor units** (`qəpik`, 1 AZN = 100) as integers.
**Timestamps:** Stored UTC. Hot, event-heavy tables use `TIMESTAMP(3)` (millisecond precision). Standard tables use `TIMESTAMP`.

---

## 0. Conventions

These conventions apply globally; each table only restates fields that deviate.

### 0.1 Standard Column Conventions

| Concern | Rule |
|---|---|
| **Primary key** | `id` `BIGINT UNSIGNED AUTO_INCREMENT` (Laravel `$table->id()`) unless table is a junction with no entity identity. |
| **Timestamps** | `created_at`, `updated_at` `TIMESTAMP NULL` (Laravel `$table->timestamps()`). Append `created_at_ms`/equivalent via `TIMESTAMP(3)` only on hot event tables. |
| **Soft deletes** | Only on entity tables where historical references must remain valid (`users`, `devices`). Junction, log, outbox, and event tables use **status enums**, never soft-delete. |
| **Audit fields** | Mutable admin-owned rows carry `created_by_admin_id BIGINT UNSIGNED NULL` and `updated_by_admin_id BIGINT UNSIGNED NULL` (FK to `admin_users`). Mutable user-owned rows carry equivalent `*_by_user_id`. Read-only / append-only event tables omit them — actor identity lives in `audit_log`. |
| **Money** | `*_minor` `INT UNSIGNED` (or `BIGINT UNSIGNED` if > 21M AZN possible). Currency: `CHAR(3)` ISO 4217. |
| **Phone** | E.164 string, `VARCHAR(20)`. Normalised at app layer before storage. |
| **JSON** | Used for extension/metadata only; queryable columns are promoted out of JSON when accessed >once at scale. |
| **Enums** | Declared as MySQL `ENUM` for compactness + read-time validation. Renaming an enum value requires a migration. Where the set grows dynamically (e.g. device models), use a lookup table instead. |
| **Charset** | All text columns `utf8mb4_unicode_ci`. |
| **Encryption** | App-layer (`Crypt::encryptString`) for secrets and tokens — column type `VARBINARY(255)` or `TEXT` depending on size. |
| **Hashes** | Always store hex/base64 of SHA-256 (CHAR(64)) — never raw OTPs/refresh tokens. |
| **Naming** | Tables plural snake-case (`open_commands`); FK columns `<singular>_id`; index names `idx_<table>_<cols>`, uniques `uq_<table>_<cols>`, FKs `fk_<table>_<col>`. |

### 0.2 Cascade Rule Defaults

| Relationship class | ON UPDATE | ON DELETE |
|---|---|---|
| Configuration / lookup → entity | CASCADE | RESTRICT |
| Owner → entity (entity belongs to owner) | CASCADE | RESTRICT (block owner-delete; transfer first) |
| Entity → child event (event references entity) | CASCADE | RESTRICT (preserve history); use soft-delete on parent |
| Junction tables | CASCADE | CASCADE only if junction has no further dependants |
| Audit/log → entity | CASCADE | SET NULL (logs survive entity removal) |

Where the rule departs from default, the table block calls it out explicitly.

### 0.3 Soft Delete Policy

Soft delete is **not** the default. It is used **only** when:
1. The entity is referenced by long-lived rows (subs, audit, payments, orders) that must continue to display the entity's identity ("Deleted user #42").
2. The entity may be restored on a regulatory or business basis (account self-deletion under AZ personal data law).

Soft-deleted rows:
- Have `deleted_at TIMESTAMP NULL DEFAULT NULL`.
- Are filtered out by default via Eloquent global scope (`SoftDeletes`).
- *(v1.1, HIGH-12)* **Personal data is anonymised IMMEDIATELY on soft-delete**, not 30 days later. `users.phone` is replaced with `deleted:<sha256(original_phone)>`, `users.email` likewise (when present), `users.full_name` cleared. The same SHA-256 token allows support-assisted reactivation within 30 days (a worker finds the ghost by hash). The 30-day window protects the *reactivation right*, not the data itself. Phones are therefore reusable for a fresh signup with a new `user_id` immediately after soft-delete.

### 0.4 Audit Fields Policy

| Table class | Pattern |
|---|---|
| Reference / lookup | `created_by_admin_id`, `updated_by_admin_id` |
| Owner-mutable domain (devices, subscriptions) | `created_by_user_id` (or admin), `updated_by_user_id` |
| User self-mutable (profile) | None — actor is implied to be the row owner |
| Event / log / outbox | None — these ARE the audit |

For every state transition that matters to support/regulatory needs, a row is also written to `audit_log` — even if it's redundant with the audit fields. Audit columns are **for UI display**; `audit_log` is **for forensic search**.

### 0.5 Index Strategy Summary

- **Every FK gets an index.**
- **Composite indexes** designed by leading the most selective predicate that aligns with the query.
- **Covering indexes** added when explained query plan shows row-fetch dominating.
- **No** indexes added speculatively; every index is justified below by a documented query pattern.

### 0.6 Partitioning Strategy Summary

- Tables expected to exceed 50 M rows in year 1 will be `RANGE` partitioned by month on a `created_at`-derived `YYYYMM` integer (`partition_key`).
- Affected tables: `open_commands`, `audit_log`, `payment_logs`, `notifications`, `device_diagnostics`.
- Partitioning is applied **at first deploy** (not later) — initial create with 12 forward partitions; a monthly cron rolls one forward.

---

## 1. Identity & Authentication

### 1.1 `users`

**Purpose.** Mobile end-user identities (residents, additional users, device owners). Phone is the canonical identity. Linked to `device_users`, `orders`, `subscriptions`, `notifications`.

**Columns**

| # | Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|---|
| 1 | `id` | BIGINT UNSIGNED PK auto-inc | no | auto | |
| 2 | `phone` | VARCHAR(20) | no | — | E.164, e.g. `+994501234567` |
| 3 | `phone_country` | CHAR(2) | no | `AZ` | ISO 3166-1 alpha-2, for analytics |
| 4 | `full_name` | VARCHAR(120) | yes | NULL | Set on profile completion |
| 5 | `email` | VARCHAR(160) | yes | NULL | Optional, used for receipts/recovery |
| 6 | `email_verified_at` | TIMESTAMP | yes | NULL | |
| 7 | `preferred_language` | ENUM('az','ru','en') | no | `az` | |
| 8 | `status` | ENUM('active','blocked','self_deleted') | no | `active` | |
| 9 | `blocked_reason` | VARCHAR(255) | yes | NULL | Filled when admin blocks |
| 10 | `blocked_by_admin_id` | BIGINT UNSIGNED | yes | NULL | FK → admin_users.id |
| 11 | `last_login_at` | TIMESTAMP | yes | NULL | |
| 12 | `last_login_ip` | VARCHAR(45) | yes | NULL | v4 + v6 capable |
| 13 | `created_at` | TIMESTAMP | yes | NULL | |
| 14 | `updated_at` | TIMESTAMP | yes | NULL | |
| 15 | `deleted_at` | TIMESTAMP | yes | NULL | Soft delete |

**Unique Constraints**
- `uq_users_phone` on `phone` — plain `UNIQUE` *(v1.1, simplified per HIGH-12)*. Soft-deleted rows carry a `phone` anonymised to `deleted:<sha256(original_phone)>`, so the real number is reusable for a fresh signup with a new `user_id`. Reactivation within 30 days is a support workflow that finds the ghost by hash and undoes anonymisation.
- `uq_users_email` on `email` (partial-style: enforced where not NULL; in MariaDB use a regular unique with `NULL`-distinct semantics — multiple NULLs allowed by default).

**Indexes**
- `idx_users_status_created_at (status, created_at)` — admin user lists.
- `idx_users_last_login_at (last_login_at)` — DAU reports.

**Foreign Keys**
- `blocked_by_admin_id` → `admin_users.id` ON UPDATE CASCADE ON DELETE SET NULL.

**Cascade Behavior**
- Block delete of a user with active orders/subs at application layer; physical delete forbidden in prod.

**Soft Delete**
- Yes. On `self_deleted`/admin-soft-delete, PII anonymisation job runs at +30 days.

**Audit Fields**
- `blocked_by_admin_id` (admin actor for the most recent block). All blocks/unblocks duplicated to `audit_log` for full history.

---

### 1.2 `admin_users`

**Purpose.** Back-office identities for super admins and technical (installer) users. Disjoint identity space from `users`.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `email` | VARCHAR(160) | no | — | Login identity |
| `password` | VARCHAR(255) | no | — | bcrypt cost 12 |
| `name` | VARCHAR(120) | no | — | Display name |
| `role` | ENUM('super_admin','technical') | no | `technical` | |
| `phone` | VARCHAR(20) | yes | NULL | For contact, NOT login |
| `totp_secret` | VARBINARY(255) | yes | NULL | App-layer encrypted |
| `is_2fa_enabled` | TINYINT(1) | no | 0 | Required `true` for super_admin |
| `is_2fa_enforced_at` | TIMESTAMP | yes | NULL | When system forced enrollment |
| `recovery_codes_hashes` *(v1.1)* | JSON | yes | NULL | Array of 8 bcrypt hashes of one-time recovery codes; consumed entries set to `null`. Regenerate replaces whole array. See spec §15.2.1. |
| `recovery_codes_generated_at` *(v1.1)* | TIMESTAMP | yes | NULL | When current set was issued |
| `password_changed_at` | TIMESTAMP | yes | NULL | For rotation policy |
| `failed_login_count` | SMALLINT UNSIGNED | no | 0 | Reset on success |
| `locked_until` | TIMESTAMP | yes | NULL | Brute-force lockout |
| `status` | ENUM('active','suspended','offboarded') | no | `active` | |
| `last_login_at` | TIMESTAMP | yes | NULL | |
| `last_login_ip` | VARCHAR(45) | yes | NULL | |
| `remember_token` | VARCHAR(100) | yes | NULL | Laravel-style; admin panel only |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |
| `created_by_admin_id` | BIGINT UNSIGNED | yes | NULL | Who created |
| `updated_by_admin_id` | BIGINT UNSIGNED | yes | NULL | |

**Unique Constraints**
- `uq_admin_users_email` on `email`.

**Indexes**
- `idx_admin_users_status (status)`.
- `idx_admin_users_role (role)`.

**Foreign Keys**
- `created_by_admin_id`, `updated_by_admin_id` → `admin_users.id` ON UPDATE CASCADE ON DELETE SET NULL (self-reference).

**Cascade Behavior** — Admins never hard-deleted; only `offboarded`. Their FKs elsewhere stay intact.

**Soft Delete** — No (use `status = 'offboarded'`). Justification: audit log + creator-of-record references must remain intact.

**Audit Fields** — `created_by_admin_id`, `updated_by_admin_id`.

---

### 1.3 `user_devices`

**Purpose.** Tracks each install of the mobile app per user (one user → many phones / installs). Source of push tokens.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `user_id` | BIGINT UNSIGNED | no | — | FK |
| `install_uuid` | CHAR(36) | no | — | App-generated UUIDv4 on first run |
| `platform` | ENUM('ios','android') | no | — | |
| `os_version` | VARCHAR(40) | yes | NULL | |
| `app_version` | VARCHAR(20) | yes | NULL | semver |
| `device_model` | VARCHAR(80) | yes | NULL | Phone model (e.g. `iPhone15,2`) |
| `push_token` | VARCHAR(255) | yes | NULL | FCM token (Android & iOS via FCM) |
| `push_token_updated_at` | TIMESTAMP | yes | NULL | |
| `push_invalid` | TINYINT(1) | no | 0 | Set when FCM reports invalid |
| `biometric_enrolled` | TINYINT(1) | no | 0 | User opted in |
| `last_seen_at` | TIMESTAMP | yes | NULL | Updated on each authenticated request |
| `last_seen_ip` | VARCHAR(45) | yes | NULL | |
| `revoked_at` | TIMESTAMP | yes | NULL | If user logged out / forced logout |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_user_devices_user_install` on `(user_id, install_uuid)`.

**Indexes**
- `idx_user_devices_push_token (push_token)` for invalidation lookups.
- `idx_user_devices_user_revoked (user_id, revoked_at)` for active-install queries.

**Foreign Keys**
- `user_id` → `users.id` ON UPDATE CASCADE ON DELETE CASCADE (delete installs with user — but `users` is soft-deleted so this is a no-op until purge).

**Cascade Behavior** — Hard cascade only on physical purge of user.

**Soft Delete** — No. Use `revoked_at`.

**Audit Fields** — None (event-style table).

---

### 1.4 `refresh_tokens`

**Purpose.** Long-lived rotated refresh tokens, one per active mobile install. Hashed.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `user_id` | BIGINT UNSIGNED | no | — | FK |
| `user_device_id` | BIGINT UNSIGNED | no | — | FK |
| `token_hash` | CHAR(64) | no | — | SHA-256 hex of raw token |
| `issued_at` | TIMESTAMP | no | CURRENT_TIMESTAMP | |
| `last_used_at` | TIMESTAMP | yes | NULL | Updated on rotation |
| `expires_at` | TIMESTAMP | no | — | 60 days from issuance |
| `revoked_at` | TIMESTAMP | yes | NULL | On logout / rotation / security event |
| `revocation_reason` | ENUM('rotated','logout','password_change','admin','security','expired') | yes | NULL | |
| `replaced_by_id` | BIGINT UNSIGNED | yes | NULL | Self-reference for rotation chain |
| `ip` | VARCHAR(45) | yes | NULL | |
| `user_agent` | VARCHAR(255) | yes | NULL | |

**Unique Constraints**
- `uq_refresh_tokens_token_hash` on `token_hash`.

**Indexes**
- `idx_refresh_tokens_user_revoked (user_id, revoked_at)`.
- `idx_refresh_tokens_expires_at (expires_at)` for janitor sweep.

**Foreign Keys**
- `user_id` → `users.id` ON DELETE CASCADE.
- `user_device_id` → `user_devices.id` ON DELETE CASCADE.
- `replaced_by_id` → `refresh_tokens.id` ON DELETE SET NULL.

**Cascade Behavior** — Cascade with user/install on physical delete.

**Soft Delete** — No.

**Audit Fields** — None.

---

### 1.5 `otps`

**Purpose.** One-time codes for phone verification at signup/login.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `phone` | VARCHAR(20) | no | — | E.164 |
| `code_hash` | CHAR(64) | no | — | SHA-256 of code |
| `purpose` | ENUM('login','recover','email_verify') | no | `login` | |
| `attempts` | TINYINT UNSIGNED | no | 0 | Bumped on wrong submission |
| `max_attempts` | TINYINT UNSIGNED | no | 5 | Snapshot at issue time |
| `expires_at` | TIMESTAMP | no | — | TTL 120 s |
| `consumed_at` | TIMESTAMP | yes | NULL | On successful verify |
| `issued_ip` | VARCHAR(45) | yes | NULL | |
| `created_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints** — None (multiple OTPs per phone permitted; only the latest unconsumed/unexpired is honoured).

**Indexes**
- `idx_otps_phone_purpose (phone, purpose)` — verify lookup.
- `idx_otps_expires_at (expires_at)` — sweep.

**Foreign Keys** — None (phone is a string, not FK; user may not yet exist).

**Soft Delete** — No.

**Audit Fields** — None.

**Retention** — Rows hard-deleted after `expires_at + 7 days` by janitor.

---

### 1.6 `auth_attempts` *(new — flagged in §3 missing-tables review)*

**Purpose.** Brute-force / abuse detection. Captures both successful and failed authentication attempts across mobile (OTP) and admin (password) flows.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `actor_kind` | ENUM('user','admin') | no | — | |
| `identifier` | VARCHAR(160) | no | — | Phone (user) or email (admin) |
| `outcome` | ENUM('success','wrong_credential','locked','rate_limited','otp_expired','otp_max_attempts','2fa_failed') | no | — | |
| `ip` | VARCHAR(45) | no | — | |
| `user_agent` | VARCHAR(255) | yes | NULL | |
| `request_id` | CHAR(26) | yes | NULL | ULID/UUIDv7 for correlation |
| `created_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |

**Indexes**
- `idx_auth_attempts_identifier_time (identifier, created_at)` — rate-limit window queries.
- `idx_auth_attempts_ip_time (ip, created_at)`.
- `idx_auth_attempts_outcome_time (outcome, created_at)`.

**Soft Delete** — No.

**Retention** — Hard-archive monthly after 12 months. Append-only.

---

### 1.7 `user_consents` *(new — privacy / AZ Personal Data Law)*

**Purpose.** Stores explicit user consents (terms, privacy, optional marketing). One row per consent grant or revocation.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `user_id` | BIGINT UNSIGNED | no | — | FK |
| `consent_kind` | ENUM('terms','privacy','marketing_push','marketing_sms','data_processing') | no | — | |
| `document_version` | VARCHAR(20) | no | — | e.g. `terms-v3` |
| `granted` | TINYINT(1) | no | — | 1=granted, 0=revoked |
| `ip` | VARCHAR(45) | yes | NULL | |
| `user_agent` | VARCHAR(255) | yes | NULL | |
| `created_at` | TIMESTAMP | no | CURRENT_TIMESTAMP | |

**Indexes**
- `idx_user_consents_user_kind (user_id, consent_kind, created_at)`.

**Foreign Keys**
- `user_id` → `users.id` ON DELETE CASCADE.

**Soft Delete** — No (append-only; revocation is a new row).

---

## 2. Reference / Lookup

### 2.1 `sim_operators` *(new — §3 normalization fix)*

**Purpose.** Reference list of telecom operators in scope (Azercell, Bakcell, Nar, plus extensible future operators). Lets analytics group by operator without hardcoded strings.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | SMALLINT UNSIGNED PK auto-inc | no | auto | Small PK, cheap FK |
| `code` | VARCHAR(20) | no | — | Stable code, e.g. `azercell` |
| `name` | VARCHAR(60) | no | — | Display |
| `country_iso` | CHAR(2) | no | `AZ` | |
| `mcc_mnc` | VARCHAR(10) | yes | NULL | e.g. `400-01` |
| `is_active` | TINYINT(1) | no | 1 | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_sim_operators_code` on `code`.

**Indexes** — PK only.

**Soft Delete** — No (`is_active` flag).

**Audit Fields** — None (changes are rare; tracked in `audit_log` via settings module).

---

### 2.2 `device_models` *(new — §3 normalization fix)*

> *(v1.2)* `default_driver_type` / `fallback_open_driver` enum values become `traccar/ble/sms`. `supports_clip` / `supports_sms` / `supports_mqtt` are reinterpreted as transport-capability flags (CLIP no longer applies to the confirmed UMKa 310 hardware); they are vestigial for UMKa and may be generalised in a 09-B migration. The reference seed row changes from King Pigeon RTU5024 to **GLONASSSoft UMKa 310 v2L** (09-B).

**Purpose.** Catalogue of supported hardware (vendor, model code, default driver/transport capabilities). Lets ops manage supported devices without code change.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | SMALLINT UNSIGNED PK | no | auto | |
| `vendor` | VARCHAR(60) | no | — | |
| `model_code` | VARCHAR(60) | no | — | e.g. `RTU5024` |
| `supports_clip` | TINYINT(1) | no | 1 | |
| `supports_sms` | TINYINT(1) | no | 1 | |
| `supports_mqtt` | TINYINT(1) | no | 0 | |
| `default_driver_type` | ENUM('clip','sms','clip_sms','mqtt') | no | `clip_sms` | |
| `fallback_open_driver` *(v1.1)* | ENUM('clip','sms','clip_sms','mqtt') | yes | NULL | If primary open fails transiently, dispatcher retries via this driver once (see spec §12.7). |
| `whitelist_capacity` *(v1.1, NOT NULL)* | SMALLINT UNSIGNED | **no** | **100** | Max whitelist entries — required for capacity enforcement (HIGH-05). |
| `sms_open_command` | VARCHAR(40) | yes | NULL | e.g. `CC` |
| `notes` | VARCHAR(255) | yes | NULL | |
| `is_active` | TINYINT(1) | no | 1 | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_device_models_vendor_model` on `(vendor, model_code)`.

**Soft Delete** — No (use `is_active`).

**Audit Fields** — `audit_log` via settings module.

---

### 2.3 `regions` *(new — for reporting groupings)*

**Purpose.** Optional grouping of devices by city/district. Used for ops reporting and per-region revenue dashboards.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | SMALLINT UNSIGNED PK | no | auto | |
| `code` | VARCHAR(20) | no | — | e.g. `baku-yasamal` |
| `name` | VARCHAR(80) | no | — | |
| `parent_id` | SMALLINT UNSIGNED | yes | NULL | Self-reference: district → city |
| `is_active` | TINYINT(1) | no | 1 | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_regions_code` on `code`.

**Indexes**
- `idx_regions_parent (parent_id)`.

**Foreign Keys**
- `parent_id` → `regions.id` ON DELETE RESTRICT.

**Soft Delete** — No.

---

## 3. Device Domain

### 3.1 `devices`

**Purpose.** Physical GSM-controlled access controllers registered in the platform.

**Columns**

| # | Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|---|
| 1 | `id` | BIGINT UNSIGNED PK | no | auto | |
| 2 | `serial` | VARCHAR(64) | no | — | Manufacturer serial |
| 3 | `device_model_id` | SMALLINT UNSIGNED | no | — | FK → device_models |
| 4 | `firmware_version` | VARCHAR(40) | yes | NULL | Free text, validated against known list |
| 5 | `sim_phone` | VARCHAR(20) | no | — | E.164 |
| 6 | `sim_operator_id` | SMALLINT UNSIGNED | yes | NULL | FK → sim_operators |
| 7 | `sim_iccid` | VARCHAR(22) | yes | NULL | Optional |
| 8 | `driver_type` | ENUM('clip','sms','clip_sms','mqtt') | no | `clip_sms` | Initial from model default; overrideable |
| 9 | `status` | ENUM('unassigned','active','suspended','disabled','decommissioned') | no | `unassigned` | |
| 10 | `owner_user_id` | BIGINT UNSIGNED | yes | NULL | FK → users.id |
| 11 | `region_id` | SMALLINT UNSIGNED | yes | NULL | FK → regions.id |
| 12 | `location_label` | VARCHAR(160) | yes | NULL | Free text address |
| 13 | `latitude` | DECIMAL(10,7) | yes | NULL | |
| 14 | `longitude` | DECIMAL(10,7) | yes | NULL | |
| 15 | `last_online_at` | TIMESTAMP | yes | NULL | From diagnostics |
| 16 | `last_signal_strength` | TINYINT | yes | NULL | 3GPP 0–31 |
| 17 | `consecutive_offline_diagnostics` | SMALLINT UNSIGNED | no | 0 | Increments on missed ping |
| 18 | `whitelist_capacity_used` | SMALLINT UNSIGNED | no | 0 | **DEPRECATED in v1.1 (HIGH-05)** — compute on read from `device_users(status='active')` count. Column scheduled for drop in Phase 2 once readers migrate to the derived computation. Do not write. |
| 18a | `sim_credit_minor` *(v1.1, placeholder for HIGH-04)* | INT | yes | NULL | Operator-reported balance in qəpik; populated by Phase 2 collectors. |
| 18b | `sim_credit_checked_at` *(v1.1)* | TIMESTAMP | yes | NULL | Last successful balance query. |
| 18c | `sim_status` *(v1.1)* | ENUM('active','low_credit','suspended','unknown') | no | `unknown` | Derived from balance + connectivity. |
| 19 | `metadata` | JSON | yes | NULL | Driver-specific extension |
| 20 | `registered_by_admin_id` | BIGINT UNSIGNED | yes | NULL | FK → admin_users.id |
| 21 | `activated_at` | TIMESTAMP | yes | NULL | First time status = `active` |
| 22 | `decommissioned_at` | TIMESTAMP | yes | NULL | |
| 23 | `decommission_reason` | VARCHAR(255) | yes | NULL | |
| 24 | `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |
| 25 | `deleted_at` | TIMESTAMP | yes | NULL | Soft delete |
| 26 | `created_by_admin_id` | BIGINT UNSIGNED | yes | NULL | |
| 27 | `updated_by_admin_id` | BIGINT UNSIGNED | yes | NULL | |

**Unique Constraints**
- `uq_devices_serial` on `serial`.
- `uq_devices_sim_phone` on `sim_phone` (a SIM number is globally unique).

**Indexes**
- `idx_devices_owner (owner_user_id)`.
- `idx_devices_status (status)`.
- `idx_devices_status_last_online (status, last_online_at)` — admin health dashboard.
- `idx_devices_region_status (region_id, status)` — regional reports.
- `idx_devices_model (device_model_id)`.

**Foreign Keys**
- `device_model_id` → `device_models.id` ON DELETE RESTRICT.
- `sim_operator_id` → `sim_operators.id` ON DELETE RESTRICT.
- `owner_user_id` → `users.id` ON DELETE RESTRICT (block user delete while owner; transfer first).
- `region_id` → `regions.id` ON DELETE RESTRICT.
- `registered_by_admin_id`, `created_by_admin_id`, `updated_by_admin_id` → `admin_users.id` ON DELETE SET NULL.

**Cascade Behavior** — Soft delete only; references stay intact.

**Soft Delete** — Yes. Justification: historical orders/subs/open-commands must continue to render the device's serial/label.

**Audit Fields** — `registered_by_admin_id`, `created_by_admin_id`, `updated_by_admin_id`; full transitions in `audit_log`.

---

### 3.2 `device_users`

**Purpose.** Many-to-many roster of users who can open a device. One row per (device, user, role). Subscription belongs to this association.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `device_id` | BIGINT UNSIGNED | no | — | FK |
| `user_id` | BIGINT UNSIGNED | no | — | FK |
| `role` | ENUM('owner','user') | no | `user` | Owner has elevated permissions |
| `added_by_user_id` | BIGINT UNSIGNED | yes | NULL | Owner who added this user |
| `added_by_admin_id` | BIGINT UNSIGNED | yes | NULL | Admin override |
| `access_window_start` | TIME | yes | NULL | **[P2]** time-of-day gating |
| `access_window_end` | TIME | yes | NULL | **[P2]** |
| `access_days_mask` | TINYINT UNSIGNED | yes | NULL | **[P2]** bitmask Mon=1 … Sun=64 |
| `status` | ENUM('active','revoked') | no | `active` | |
| `last_open_at` | TIMESTAMP | yes | NULL | Updated on each successful open |
| `revoked_at` | TIMESTAMP | yes | NULL | |
| `revoked_by_user_id` | BIGINT UNSIGNED | yes | NULL | |
| `revoked_by_admin_id` | BIGINT UNSIGNED | yes | NULL | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_device_users_active` on `(device_id, user_id)` **partial** — to allow re-adding the same user after revocation, we instead enforce uniqueness on `(device_id, user_id, status)` where status='active'. MariaDB lacks partial-unique; we model this via a generated `is_active` virtual column `GENERATED ALWAYS AS (CASE WHEN status='active' THEN 1 ELSE NULL END) STORED` and unique on `(device_id, user_id, is_active)` — NULLs don't collide.
- *(v1.1, HIGH-07)* **Fallback plan**: A concurrency test in CI hammers this constraint with 100 simultaneous inserts of the same `(device_id, user_id)` and expects exactly one to succeed. If the test fails on the deployed MariaDB version, fall back to **Plan B**: a separate `device_users_active` mirror table `(device_id, user_id) PRIMARY KEY` maintained inside `RosterService` transactions. Decision is captured in Phase 1 testing and recorded in `docs/decisions/device-users-unique.md`.

**Indexes**
- `idx_device_users_user_status (user_id, status)` — user's device list.
- `idx_device_users_device_status (device_id, status)` — roster fetch.
- `idx_device_users_role (device_id, role)` — owner lookup.

**Foreign Keys**
- `device_id` → `devices.id` ON DELETE RESTRICT.
- `user_id` → `users.id` ON DELETE RESTRICT.
- `added_by_user_id`, `revoked_by_user_id` → `users.id` ON DELETE SET NULL.
- `added_by_admin_id`, `revoked_by_admin_id` → `admin_users.id` ON DELETE SET NULL.

**Cascade Behavior** — Both parents soft-delete; this row stays.

**Soft Delete** — No (use `status`).

**Audit Fields** — `added_by_*`, `revoked_by_*`. Each transition also in `audit_log`.

---

### 3.3 `device_user_history` *(new — §3 missing-tables)*

**Purpose.** Append-only event log of every roster change (add, role change, revoke, re-add). Powers "who had access on date X" queries that `device_users` cannot answer once revoked rows are overwritten.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `device_id` | BIGINT UNSIGNED | no | — | |
| `user_id` | BIGINT UNSIGNED | no | — | |
| `event` | ENUM('added','revoked','role_changed','re_added') | no | — | |
| `from_role` | ENUM('owner','user') | yes | NULL | |
| `to_role` | ENUM('owner','user') | yes | NULL | |
| `actor_kind` | ENUM('user','admin','system') | no | — | |
| `actor_id` | BIGINT UNSIGNED | yes | NULL | Actor PK in respective table |
| `created_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |

**Indexes**
- `idx_dev_user_hist_device_time (device_id, created_at)`.
- `idx_dev_user_hist_user_time (user_id, created_at)`.

**Foreign Keys**
- `device_id` → `devices.id` ON DELETE RESTRICT.
- `user_id` → `users.id` ON DELETE RESTRICT.

**Soft Delete** — No (append-only).

---

### 3.4 `invitations` *(new — §3 missing-tables)*

**Purpose.** Tracks pending invitations sent by an owner to an unregistered phone or an existing user who has not yet accepted. `device_users` is created only after acceptance (or auto-accept when the invitee already exists).

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `device_id` | BIGINT UNSIGNED | no | — | |
| `invited_by_user_id` | BIGINT UNSIGNED | no | — | Owner |
| `invitee_phone` | VARCHAR(20) | no | — | Target |
| `invitee_user_id` | BIGINT UNSIGNED | yes | NULL | Linked when phone matches an existing user |
| `role` | ENUM('user','owner') | no | `user` | |
| `payer` | ENUM('owner','invitee') | no | `owner` | Who pays the additional-user sub |
| `token` | CHAR(40) | no | — | Random URL-safe token |
| `status` | ENUM('pending','accepted','declined','expired','cancelled') | no | `pending` | |
| `expires_at` | TIMESTAMP | no | — | 7 days default |
| `accepted_at` | TIMESTAMP | yes | NULL | |
| `accepted_device_user_id` | BIGINT UNSIGNED | yes | NULL | FK → device_users.id |
| `linked_order_id` | BIGINT UNSIGNED | yes | NULL | FK → orders.id; the sub purchase |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_invitations_token` on `token`.
- `uq_invitations_active_target` partial — `(device_id, invitee_phone)` where `status='pending'`. Implemented via generated `is_pending` column trick (see §3.2 pattern).

**Indexes**
- `idx_invitations_status_expires (status, expires_at)`.
- `idx_invitations_invitee_phone (invitee_phone)`.

**Foreign Keys**
- `device_id` → `devices.id` ON DELETE RESTRICT.
- `invited_by_user_id` → `users.id` ON DELETE RESTRICT.
- `invitee_user_id` → `users.id` ON DELETE SET NULL.
- `accepted_device_user_id` → `device_users.id` ON DELETE SET NULL.
- `linked_order_id` → `orders.id` ON DELETE SET NULL.

**Soft Delete** — No.

**Audit Fields** — None (events on status transitions go to `audit_log`).

---

## 4. Subscriptions

### 4.1 `subscriptions`

**Purpose.** Entitlement record per (user, device). Drives the open-permission check.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `device_user_id` | BIGINT UNSIGNED | no | — | FK; one sub per device_user |
| `tier` | ENUM('main','additional') | no | — | |
| `price_minor` | INT UNSIGNED | no | — | Snapshot of price at purchase |
| `currency` | CHAR(3) | no | `AZN` | |
| `term_days` | SMALLINT UNSIGNED | no | 365 | Snapshot |
| `starts_at` | TIMESTAMP | no | — | |
| `ends_at` | TIMESTAMP | no | — | Drives expiry sweep |
| `status` | ENUM('pending_payment','active','expired','cancelled','refunded') | no | `pending_payment` | |
| `auto_renew` | TINYINT(1) | no | 0 | Future-ready |
| `card_token_id` | BIGINT UNSIGNED | yes | NULL | FK |
| ~~`latest_order_id`~~ | ~~BIGINT UNSIGNED~~ | — | — | **REMOVED in v1.1 (HIGH-18)** — derive on read from the most recent `subscription_periods` row whose `kind ∈ {'initial','renewal','extension'}`. Resource layer exposes as `latest_order` via composition. |
| `last_reminder_kind` | ENUM('d30','d15','d7','d1','expired') | yes | NULL | Idempotent reminder marker |
| `last_reminder_sent_at` | TIMESTAMP | yes | NULL | |
| `cancelled_at` | TIMESTAMP | yes | NULL | |
| `cancellation_reason` | VARCHAR(255) | yes | NULL | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_subscriptions_device_user` on `device_user_id` — one logical sub per device_user; renewals extend `ends_at` rather than insert. Historic terms are captured in `subscription_periods` (4.2).

**Indexes**
- `idx_subscriptions_status_ends (status, ends_at)` — daily expiry sweep.
- `idx_subscriptions_ends_at (ends_at)` — reminders.
- `idx_subscriptions_auto_renew (auto_renew, ends_at)` — future autorenew sweep.

**Foreign Keys**
- `device_user_id` → `device_users.id` ON DELETE RESTRICT.
- `card_token_id` → `card_tokens.id` ON DELETE SET NULL.
- ~~`latest_order_id` → `orders.id`~~ *removed v1.1; derived*.

**Soft Delete** — No (use `status='cancelled'`).

**Audit Fields** — None at column level; status changes captured in `subscription_periods` + `audit_log`.

---

### 4.2 `subscription_periods` *(new — §3 missing-tables, normalization win)*

**Purpose.** Append-only history of every paid term (or refund) for a subscription. Lets us answer "how many full years did this user actually pay" and supports pro-rata refunds.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `subscription_id` | BIGINT UNSIGNED | no | — | FK |
| `order_id` | BIGINT UNSIGNED | no | — | FK; the order that funded this term |
| `kind` | ENUM('initial','renewal','extension','refund') | no | — | |
| `period_start` | TIMESTAMP | no | — | |
| `period_end` | TIMESTAMP | no | — | |
| `amount_minor` | INT | no | — | Signed; refund is negative |
| `created_at` | TIMESTAMP | no | CURRENT_TIMESTAMP | |

**Indexes**
- `idx_subscription_periods_subscription (subscription_id, period_end)`.
- `idx_subscription_periods_order (order_id)`.

**Foreign Keys**
- `subscription_id` → `subscriptions.id` ON DELETE RESTRICT.
- `order_id` → `orders.id` ON DELETE RESTRICT.

**Soft Delete** — No.

---

## 5. Payments

### 5.1 `orders`

**Purpose.** A payment intent (one or more line items) that the user has been redirected to settle.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `reference` | VARCHAR(40) | no | — | Our human-friendly ref (e.g. `SH-202606-000123`) |
| `payer_user_id` | BIGINT UNSIGNED | no | — | FK |
| `purpose` | ENUM('device_sale','sub_main','sub_additional','sub_renewal','bundle') | no | — | |
| `amount_minor` | INT UNSIGNED | no | — | Sum of line items |
| `currency` | CHAR(3) | no | `AZN` | |
| `status` | ENUM('pending','authorising','paid','failed','cancelled','refunded','partially_refunded','expired') | no | `pending` | |
| `bank_order_id` | VARCHAR(80) | yes | NULL | Kapital order id |
| `bank_redirect_url` | TEXT | yes | NULL | |
| `idempotency_key` | VARCHAR(60) | yes | NULL | Client-supplied |
| `expires_at` | TIMESTAMP | yes | NULL | Pending-state ttl |
| `paid_at` | TIMESTAMP | yes | NULL | |
| `failed_at` | TIMESTAMP | yes | NULL | |
| `failed_reason` | VARCHAR(200) | yes | NULL | |
| `return_url` | TEXT | yes | NULL | Mobile deep link |
| `metadata` | JSON | yes | NULL | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_orders_reference` on `reference`.
- `uq_orders_bank_order` on `bank_order_id` (nullable; multiple NULLs allowed).
- `uq_orders_idempotency` on `(payer_user_id, idempotency_key)` where idempotency_key NOT NULL.

**Indexes**
- `idx_orders_status_created (status, created_at)`.
- `idx_orders_payer_created (payer_user_id, created_at)`.
- `idx_orders_purpose_created (purpose, created_at)`.

**Foreign Keys**
- `payer_user_id` → `users.id` ON DELETE RESTRICT.

**Soft Delete** — No (financial records). Status enum covers terminal states.

**Audit Fields** — None at column level; state changes in `audit_log`.

---

### 5.2 `order_items`

**Purpose.** Decomposes an order into priced lines. One order can fund a device sale + a main subscription in a single checkout.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `order_id` | BIGINT UNSIGNED | no | — | FK |
| `item_type` | ENUM('device','sub_main','sub_additional','sub_renewal') | no | — | |
| `referenced_id` | BIGINT UNSIGNED | yes | NULL | FK by convention; points to device_id / subscription_id depending on type |
| `description` | VARCHAR(160) | no | — | Snapshot of item description for receipt |
| `quantity` | SMALLINT UNSIGNED | no | 1 | |
| `unit_amount_minor` | INT UNSIGNED | no | — | |
| `total_amount_minor` | INT UNSIGNED | no | — | quantity × unit |
| `metadata` | JSON | yes | NULL | |
| `created_at` | TIMESTAMP | no | CURRENT_TIMESTAMP | |

**Indexes**
- `idx_order_items_order (order_id)`.
- `idx_order_items_type_ref (item_type, referenced_id)`.

**Foreign Keys**
- `order_id` → `orders.id` ON DELETE CASCADE (items live and die with order).

**Soft Delete** — No.

---

### 5.3 `payments`

**Purpose.** Actual financial events (charge, refund). One order may have many: initial charge, partial refund, etc.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `order_id` | BIGINT UNSIGNED | no | — | FK |
| `bank_transaction_id` | VARCHAR(80) | yes | NULL | |
| `type` | ENUM('charge','refund','reversal') | no | — | |
| `amount_minor` | INT | no | — | Charge positive, refund negative |
| `currency` | CHAR(3) | no | `AZN` | |
| `status` | ENUM('approved','declined','reversed','pending','error') | no | — | |
| `card_brand` | VARCHAR(20) | yes | NULL | `VISA`, `MC` |
| `card_last4` | CHAR(4) | yes | NULL | |
| `card_token_id` | BIGINT UNSIGNED | yes | NULL | FK; only present for tokenized charges |
| `raw_response_encrypted` | TEXT | yes | NULL | App-layer encrypted; for audit |
| `occurred_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |
| `created_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_payments_bank_tx` on `bank_transaction_id` (nullable).

**Indexes**
- `idx_payments_order (order_id)`.
- `idx_payments_status_time (status, occurred_at)`.
- `idx_payments_type_time (type, occurred_at)` — reconciliation.

**Foreign Keys**
- `order_id` → `orders.id` ON DELETE RESTRICT.
- `card_token_id` → `card_tokens.id` ON DELETE SET NULL.

**Soft Delete** — No (financial).

---

### 5.4 `card_tokens`

**Purpose.** Stores Kapital-issued card tokens (when tokenization becomes available) to enable auto-renew.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `user_id` | BIGINT UNSIGNED | no | — | FK |
| `bank_token_encrypted` | VARBINARY(255) | no | — | App-layer encrypted |
| `pan_masked` | VARCHAR(20) | no | — | `**** **** **** 1234` |
| `card_brand` | VARCHAR(20) | yes | NULL | |
| `expiry_month` | TINYINT UNSIGNED | yes | NULL | 1–12 |
| `expiry_year` | SMALLINT UNSIGNED | yes | NULL | 4-digit |
| `status` | ENUM('active','revoked','expired') | no | `active` | |
| `revoked_at` | TIMESTAMP | yes | NULL | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_card_tokens_user_token` on `(user_id, bank_token_encrypted)`.

**Indexes**
- `idx_card_tokens_user_status (user_id, status)`.

**Foreign Keys**
- `user_id` → `users.id` ON DELETE CASCADE.

**Soft Delete** — No.

---

### 5.5 `payment_logs`

**Purpose.** Append-only log of every outbound call to Kapital Bank and every inbound callback. Required for compliance and dispute resolution. Bodies redacted before persistence.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `partition_key` | INT UNSIGNED | no | — | `YYYYMM` for partitioning |
| `order_id` | BIGINT UNSIGNED | yes | NULL | FK (may be null for unmatched callbacks) |
| `direction` | ENUM('outbound','inbound') | no | — | |
| `endpoint` | VARCHAR(200) | no | — | |
| `http_method` | VARCHAR(10) | yes | NULL | |
| `http_status` | SMALLINT UNSIGNED | yes | NULL | |
| `request_redacted_encrypted` *(v1.1, renamed per HIGH-15)* | TEXT | yes | NULL | App-layer encrypted. Body is JSON serialized via an **allowlist** (only whitelisted fields persist; everything else dropped before encryption). |
| `response_redacted_encrypted` *(v1.1)* | TEXT | yes | NULL | Same. |
| `signature_provided` | TINYINT(1) | yes | NULL | inbound only |
| `signature_valid` | TINYINT(1) | yes | NULL | inbound only |
| `ip` | VARCHAR(45) | yes | NULL | inbound only |
| `latency_ms` | INT UNSIGNED | yes | NULL | outbound only |
| `created_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |

**Indexes**
- `idx_payment_logs_order_time (order_id, created_at)`.
- `idx_payment_logs_direction_time (direction, created_at)`.
- `idx_payment_logs_signature_invalid (signature_valid, created_at)` — security alerting (look at `=0`).

**Foreign Keys**
- `order_id` → `orders.id` ON DELETE SET NULL.

**Partitioning** — RANGE on `partition_key` (`YYYYMM`), monthly. 12 forward partitions provisioned.

**Soft Delete** — No.

**Retention** — 5 years (regulatory).

---

### 5.6 `payment_callbacks` *(new — §3 missing-tables)*

**Purpose.** Idempotency table for inbound Kapital callbacks. A callback may arrive multiple times for the same bank order; this table ensures effect-once processing.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `bank_order_id` | VARCHAR(80) | no | — | |
| `bank_status` | VARCHAR(40) | no | — | As reported |
| `payload_hash` | CHAR(64) | no | — | SHA-256 of canonical body; dedupe key |
| `order_id` | BIGINT UNSIGNED | yes | NULL | Matched order |
| `processed_at` | TIMESTAMP | yes | NULL | |
| `outcome` | ENUM('applied','duplicate','signature_invalid','unmatched','error') | no | — | |
| `created_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |

**Unique Constraints**
- `uq_payment_callbacks_payload` on `payload_hash` — primary dedupe.
- `uq_payment_callbacks_bank_status` on `(bank_order_id, bank_status)` — secondary safety.

**Indexes**
- `idx_payment_callbacks_order (order_id)`.
- `idx_payment_callbacks_outcome_time (outcome, created_at)`.

**Foreign Keys**
- `order_id` → `orders.id` ON DELETE SET NULL.

**Soft Delete** — No.

**Retention** — 5 years.

---

### 5.7 `refunds` *(new — workflow tracking)*

**Purpose.** Tracks the workflow state of a refund initiated by an admin (which may involve approvals, retries, partial application). The financial `payments` row is the result; this row is the *intent*.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `order_id` | BIGINT UNSIGNED | no | — | FK |
| `amount_minor` | INT UNSIGNED | no | — | Requested refund |
| `reason` | VARCHAR(255) | no | — | Free-text |
| `status` | ENUM('requested','processing','approved','rejected','failed') | no | `requested` | |
| `requested_by_admin_id` | BIGINT UNSIGNED | no | — | FK |
| `processed_by_admin_id` | BIGINT UNSIGNED | yes | NULL | If different |
| `linked_payment_id` | BIGINT UNSIGNED | yes | NULL | FK → payments.id once issued |
| `error_message` | VARCHAR(255) | yes | NULL | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Indexes**
- `idx_refunds_order (order_id)`.
- `idx_refunds_status_created (status, created_at)`.

**Foreign Keys**
- `order_id` → `orders.id` ON DELETE RESTRICT.
- `requested_by_admin_id`, `processed_by_admin_id` → `admin_users.id` ON DELETE SET NULL.
- `linked_payment_id` → `payments.id` ON DELETE SET NULL.

**Soft Delete** — No.

**Audit Fields** — `requested_by_admin_id`, `processed_by_admin_id`.

---

## 6. Device Operations

### 6.1 `open_commands`

> *(v1.2)* The `driver` column now holds `traccar` / `ble` / `sms` (was `clip/sms/clip_sms/mqtt`). No schema change — the column is `VARCHAR(20)`; the enum value change is coordinated with the `DriverType` code enum + OpenAPI at the start of batch 09-B. Table built in batch 09-A.

**Purpose.** Every attempted open. The single most write-heavy table.

**Columns**

| # | Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|---|
| 1 | `id` | BIGINT UNSIGNED PK | no | auto | |
| 2 | `partition_key` | INT UNSIGNED | no | — | `YYYYMM` |
| 3 | `device_id` | BIGINT UNSIGNED | no | — | FK |
| 4 | `user_id` | BIGINT UNSIGNED | no | — | FK |
| 5 | `device_user_id` | BIGINT UNSIGNED | no | — | FK; snapshot |
| 6 | `subscription_id` | BIGINT UNSIGNED | yes | NULL | FK; snapshot at command time |
| 7 | `idempotency_key` | VARCHAR(60) | yes | NULL | Client-supplied UUID |
| 8 | `driver` | VARCHAR(20) | no | — | snapshot of `devices.driver_type` |
| 9 | `state` | ENUM('queued','dispatching','dispatched','opened','failed','expired') | no | `queued` | |
| 10 | `failure_reason` | VARCHAR(120) | yes | NULL | Code-style (e.g. `device_offline`) |
| 11 | `attempts` | TINYINT UNSIGNED | no | 0 | Retries |
| 12 | `requested_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |
| 13 | `dispatched_at` | TIMESTAMP(3) | yes | NULL | |
| 14 | `completed_at` | TIMESTAMP(3) | yes | NULL | |
| 15 | `latency_ms` | INT UNSIGNED | yes | NULL | completed_at − requested_at |
| 16 | `source` | ENUM('mobile','admin','automation') | no | `mobile` | |
| 17 | `client_app_version` | VARCHAR(20) | yes | NULL | |
| 18 | `client_ip` | VARCHAR(45) | yes | NULL | |
| 19 | `metadata` | JSON | yes | NULL | Provider call id, etc. |
| 20 | `created_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |

**Unique Constraints**
- `uq_open_commands_idempotency` on `(user_id, idempotency_key)` where idempotency_key NOT NULL.

**Indexes**
- `idx_open_commands_device_time (device_id, requested_at)` — user history per device.
- `idx_open_commands_user_time (user_id, requested_at)` — user history.
- `idx_open_commands_state_time (state, requested_at)` — queue scans.
- `idx_open_commands_partition_state (partition_key, state)` — partition pruning aid.

**Foreign Keys**
- `device_id` → `devices.id` ON DELETE RESTRICT.
- `user_id` → `users.id` ON DELETE RESTRICT.
- `device_user_id` → `device_users.id` ON DELETE RESTRICT.
- `subscription_id` → `subscriptions.id` ON DELETE SET NULL.

**Partitioning** — RANGE on `partition_key`, monthly.

**Soft Delete** — No (event-style append-only).

**Retention** — 24 months hot in DB; older months archived to S3-compatible storage as Parquet and dropped from primary.

**Related tables (v1.1):** `open_command_attempts` (§6.1.1) for per-attempt history when fallback drivers are invoked; `open_command_feedback` (§6.1.2) for optional user-reported actuation confirmation.

---

### 6.1.1 `open_command_attempts` *(new — v1.1, HIGH-03 / MED-13)*

**Purpose.** Per-attempt history of dispatch tries for a parent `open_commands` row. A primary attempt is always recorded; a fallback attempt (per spec §12.7) appears as a second row.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `open_command_id` | BIGINT UNSIGNED | no | — | FK → `open_commands.id` |
| `attempt_no` | TINYINT UNSIGNED | no | — | 1 = primary, 2 = fallback |
| `driver` | VARCHAR(20) | no | — | Snapshot |
| `voice_gateway_id` | VARCHAR(40) | yes | NULL | *(v1.2 vestigial — voice gateway retired; repurpose as `transport_ref`, e.g. Traccar command id, in a 09-B migration; kept stable for now)* |
| `state` | ENUM('queued','dispatching','dispatched','opened','failed','expired') | no | `queued` | |
| `failure_reason` | VARCHAR(120) | yes | NULL | Code-style |
| `started_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |
| `completed_at` | TIMESTAMP(3) | yes | NULL | |
| `latency_ms` | INT UNSIGNED | yes | NULL | |
| `metadata` | JSON | yes | NULL | Provider call/SMS id |

**Unique Constraints**
- `uq_open_command_attempts` on `(open_command_id, attempt_no)`.

**Indexes**
- `idx_open_command_attempts_command (open_command_id)`.

**Foreign Keys**
- `open_command_id` → `open_commands.id` ON DELETE CASCADE.

**Soft Delete** — No.

**Retention** — Inherits parent's 24-month hot retention.

---

### 6.1.2 `open_command_feedback` *(new — v1.1, CRIT-06)*

**Purpose.** Optional user-reported confirmation that the relay actuated. Surfaced as a prompt after CLIP-only opens where the backend cannot observe actuation directly.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `open_command_id` | BIGINT UNSIGNED | no | — | FK |
| `user_id` | BIGINT UNSIGNED | no | — | FK |
| `gate_moved` | TINYINT(1) | no | — | 1 = yes, 0 = no |
| `comment` | VARCHAR(255) | yes | NULL | Optional |
| `created_at` | TIMESTAMP | no | CURRENT_TIMESTAMP | |

**Unique Constraints**
- `uq_open_command_feedback_command_user` on `(open_command_id, user_id)`.

**Indexes**
- `idx_open_command_feedback_command (open_command_id)`.

**Foreign Keys**
- `open_command_id` → `open_commands.id` ON DELETE CASCADE.
- `user_id` → `users.id` ON DELETE RESTRICT.

**Soft Delete** — No.

**Retention** — 12 months (aggregates flow into `device_daily_stats.actuation_confirmation_rate`, Phase 2).

---

### 6.2 `device_diagnostics`

> *(v1.2)* Unchanged shape; the data source is **Traccar telemetry event-forward** (positions, I/O state, online status), not a CLIP/SMS driver ping. The table + `adminDeviceDiagnostics` / `techDiagnosticsPing` endpoints are created in batch 09-B (deferred from 09-A, which had no telemetry source).

**Purpose.** Periodic and event-driven device health reports.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `partition_key` | INT UNSIGNED | no | — | `YYYYMM` |
| `device_id` | BIGINT UNSIGNED | no | — | FK |
| `source` | ENUM('scheduled_ping','open_dispatch','admin_ping','device_initiated') | no | — | |
| `online` | TINYINT(1) | no | — | Derived |
| `signal_strength` | TINYINT | yes | NULL | 3GPP 0–31 |
| `battery_level` | TINYINT | yes | NULL | If supported |
| `firmware_version` | VARCHAR(40) | yes | NULL | |
| `raw` | JSON | yes | NULL | Whatever the device returned |
| `reported_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |

**Indexes**
- `idx_device_diagnostics_device_time (device_id, reported_at)`.
- `idx_device_diagnostics_online_time (online, reported_at)`.

**Foreign Keys**
- `device_id` → `devices.id` ON DELETE RESTRICT.

**Partitioning** — RANGE on `partition_key`, monthly.

**Soft Delete** — No.

**Retention** — 12 months.

---

### 6.3 `whitelist_changes`

> *(v1.2)* Re-scoped: the outbox carries **device-config / BLE-credential provisioning + Traccar authorisation sync**, not on-device caller-ID phone numbers for CLIP. Columns are unchanged (`phone` holds the relevant identifier or `*` for a clear). Enqueue + tracking built in 09-A; the `WhitelistSyncJob` drain (calls the resolved driver) lands in 09-B.

**Purpose.** Outbox of pending provisioning mutations to be applied to devices (via Traccar / BLE credential push). Drained in order by `WhitelistSyncJob`.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `seq` *(v1.1, MED-09)* | BIGINT UNSIGNED | no | auto-inc | Monotonic ordering key; drain order is `(device_id, priority ASC, seq ASC)`. Independent from `created_at` to survive sub-second bursts. |
| `device_id` | BIGINT UNSIGNED | no | — | FK |
| `device_user_id` | BIGINT UNSIGNED | yes | NULL | FK; what change is about |
| `action` | ENUM('add','remove','clear') | no | — | |
| `phone` | VARCHAR(20) | no | — | Snapshot |
| `priority` *(v1.1, HIGH-01)* | TINYINT UNSIGNED | no | 50 | Lower drains first. Admin-forced resync uses 10; routine roster changes use 50. |
| `status` | ENUM('pending','in_progress','synced','failed','cancelled') | no | `pending` | |
| `attempt_count` | TINYINT UNSIGNED | no | 0 | |
| `max_attempts` | TINYINT UNSIGNED | no | 5 | |
| `last_error` | VARCHAR(255) | yes | NULL | |
| `last_attempt_at` | TIMESTAMP | yes | NULL | |
| `next_attempt_at` | TIMESTAMP | yes | NULL | Backoff schedule |
| `synced_at` | TIMESTAMP | yes | NULL | |
| `requested_by_user_id` | BIGINT UNSIGNED | yes | NULL | Owner / admin |
| `requested_by_admin_id` | BIGINT UNSIGNED | yes | NULL | |
| `created_at` | TIMESTAMP | no | CURRENT_TIMESTAMP | |

**Indexes**
- `idx_whitelist_device_status_next (device_id, status, priority, seq)` — drain order per device *(v1.1)*.
- `idx_whitelist_status_next (status, next_attempt_at)` — global worker pick.

**Burst guard** *(v1.1, HIGH-01)* — `InvitationService::create` and `RosterService::addUser` reject the addition if `count(whitelist_changes WHERE device_id=? AND status='pending') >= 5`, with HTTP `409 too_many_pending_changes`. The UI surfaces the cap and shows a "Provisioning…" chip on roster rows until their corresponding `whitelist_changes.status='synced'`.

**Foreign Keys**
- `device_id` → `devices.id` ON DELETE RESTRICT.
- `device_user_id` → `device_users.id` ON DELETE SET NULL.
- `requested_by_user_id` → `users.id` ON DELETE SET NULL.
- `requested_by_admin_id` → `admin_users.id` ON DELETE SET NULL.

**Soft Delete** — No.

**Retention** — Synced rows retained 90 days then archived/purged.

---

## 7. Notifications

### 7.1 `notification_templates` *(new — §3 missing-tables)*

**Purpose.** Catalogue of notification templates. Admin-editable (admin panel), versioned via `updated_at`. Bodies are in `notification_template_locales`.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | SMALLINT UNSIGNED PK | no | auto | |
| `template_key` | VARCHAR(80) | no | — | e.g. `subscription.expiring_7d` |
| `default_channels_mask` | TINYINT UNSIGNED | no | — | Bitmask: push=1, sms=2, inapp=4, email=8 |
| `category` | ENUM('security','billing','operational','marketing') | no | — | Determines whether user can mute |
| `is_user_mutable` | TINYINT(1) | no | 0 | Marketing only is mutable |
| `is_active` | TINYINT(1) | no | 1 | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_notification_templates_key` on `template_key`.

**Soft Delete** — No.

---

### 7.2 `notification_template_locales` *(new)*

**Purpose.** Locale-specific bodies. Three rows per template (az/ru/en) once seeded.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | SMALLINT UNSIGNED PK | no | auto | |
| `notification_template_id` | SMALLINT UNSIGNED | no | — | FK |
| `locale` | ENUM('az','ru','en') | no | — | |
| `subject` | VARCHAR(160) | yes | NULL | Email/push title |
| `body` | TEXT | no | — | Mustache/Twig-style placeholders |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |
| `updated_by_admin_id` | BIGINT UNSIGNED | yes | NULL | |

**Unique Constraints**
- `uq_template_locale` on `(notification_template_id, locale)`.

**Foreign Keys**
- `notification_template_id` → `notification_templates.id` ON DELETE CASCADE.

**Soft Delete** — No.

**Audit Fields** — `updated_by_admin_id`.

---

### 7.3 `notifications`

**Purpose.** Per-user notification record. Drives in-app inbox; also marks delivery outcome for push/SMS/email channels.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `partition_key` | INT UNSIGNED | no | — | `YYYYMM` |
| `user_id` | BIGINT UNSIGNED | no | — | FK |
| `template_key` | VARCHAR(80) | no | — | Snapshot |
| `channel` | ENUM('push','sms','inapp','email') | no | — | |
| `payload` | JSON | no | — | Rendered payload, deep links |
| `dedupe_key` | VARCHAR(120) | yes | NULL | For idempotent fan-out |
| `status` | ENUM('queued','sent','failed','read') | no | `queued` | |
| `failure_reason` | VARCHAR(255) | yes | NULL | |
| `sent_at` | TIMESTAMP | yes | NULL | |
| `read_at` | TIMESTAMP | yes | NULL | inapp only |
| `created_at` | TIMESTAMP | no | CURRENT_TIMESTAMP | |
| `campaign_id` | BIGINT UNSIGNED | yes | NULL | FK → `notification_campaigns.id` (§7.5); NULL for automatic business notifications. Admin-campaign rows set the reserved `template_key = 'system.admin_campaign'`. *(new — Reconciliation §C)* |

**Unique Constraints**
- `uq_notifications_dedupe` on `(user_id, dedupe_key, channel)` where dedupe_key NOT NULL.

**Indexes**
- `idx_notifications_user_channel_status (user_id, channel, status)` — inbox unread count.
- `idx_notifications_user_created (user_id, created_at)` — inbox list.
- `idx_notifications_status_created (status, created_at)` — retry sweeps.
- `idx_notifications_campaign (campaign_id)` — admin-campaign delivery rollup. *(new — Reconciliation §C)*

**Foreign Keys**
- `user_id` → `users.id` ON DELETE CASCADE.
- `campaign_id` → `notification_campaigns.id` ON DELETE SET NULL. *(new — Reconciliation §C)*

**Payload** — `payload` carries the rendered `title`, `body`, `type`, and deep-link entity ids; it drives the in-app inbox **and** is the source for the FCM `notification{title,body}` + `data:{type, notification_id, ids}` message. No auth tokens/JWTs, no PII beyond the display title/body (Constitution R-NOT-17). *(clarified — Reconciliation §C)*

**Partitioning** — RANGE on `partition_key`, monthly.

**Soft Delete** — No.

**Retention** — 12 months for inapp; 90 days for non-inapp channels.

---

### 7.4 `user_notification_settings`

**Purpose.** Per-user channel preferences (only for mutable categories).

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `user_id` | BIGINT UNSIGNED | no | — | FK |
| `template_key` | VARCHAR(80) | no | — | |
| `channel` | ENUM('push','sms','inapp','email') | no | — | |
| `enabled` | TINYINT(1) | no | 1 | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_unotif_user_template_channel` on `(user_id, template_key, channel)`.

**Foreign Keys**
- `user_id` → `users.id` ON DELETE CASCADE.

**Soft Delete** — No.

---

### 7.5 `notification_campaigns` *(new — Reconciliation §C / admin-initiated §17.6)*

**Purpose.** One row per admin-initiated notification ("a send"). Records compose + audience + dispatch stats. Automatic business notifications do NOT use this table (`notifications.campaign_id` is NULL).

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `created_by_admin_id` | BIGINT UNSIGNED | no | — | FK → `admin_users.id` |
| `type` | VARCHAR(40) | no | — | MVP: `system` |
| `title` | VARCHAR(160) | no | — | free-text, rendered as-is |
| `body` | TEXT | no | — | free-text, rendered as-is |
| `language` | ENUM('az','ru','en') | no | — | one language per campaign (no auto-translation) |
| `audience_scope` | ENUM('all_users','user_ids','filter','complex') | no | — | |
| `audience_filter` | JSON | yes | NULL | `user_ids[]`, or `{q,complex_id,role,subscription_status}`, or `{complex_id}` |
| `status` | ENUM('draft','queued','sending','sent','failed') | no | `draft` | lifecycle |
| `total_recipients` | INT UNSIGNED | yes | NULL | server-resolved distinct `user_id` at send |
| `sent_count` | INT UNSIGNED | no | 0 | rollup |
| `failed_count` | INT UNSIGNED | no | 0 | rollup |
| `confirmed_at` | TIMESTAMP | yes | NULL | mandatory admin confirmation |
| `sent_at` | TIMESTAMP | yes | NULL | dispatch start |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Indexes** — `idx_campaigns_created (created_by_admin_id, created_at)`, `idx_campaigns_status (status)`.

**Foreign Keys** — `created_by_admin_id` → `admin_users.id` ON DELETE RESTRICT.

**Soft Delete** — No. **Partitioning** — No (low volume).

**Notes** — Delivery is always **push + inapp** (fixed MVP channels); a campaign has **no** `channels_mask` — the channel bitmask is a template-level selector only (Constitution R-NOT-04). Fan-out writes per-channel `notifications` rows (`template_key='system.admin_campaign'`, `campaign_id`, rendered title/body in `payload`), idempotent by `dedupe_key='campaign:{id}:{user_id}'`. Each send is emitted as an `AuditableEvent`.

---

## 8. Audit & Operations

### 8.1 `audit_log`

**Purpose.** Immutable, partitioned forensic log of every privileged action.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `partition_key` | INT UNSIGNED | no | — | `YYYYMM` |
| `actor_kind` | ENUM('user','admin','system') | no | — | |
| `actor_id` | BIGINT UNSIGNED | yes | NULL | |
| `actor_label` | VARCHAR(120) | yes | NULL | Snapshot (e.g. masked phone, admin email) |
| `action` | VARCHAR(80) | no | — | Dotted key, e.g. `device.user_added` |
| `entity_type` | VARCHAR(40) | yes | NULL | `device`, `order`, … |
| `entity_id` | BIGINT UNSIGNED | yes | NULL | |
| `payload_redacted` | JSON | yes | NULL | Before/after where applicable |
| `request_id` | CHAR(26) | yes | NULL | Correlates with request log |
| `ip` | VARCHAR(45) | yes | NULL | |
| `user_agent` | VARCHAR(255) | yes | NULL | |
| `created_at` | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | |

**Indexes**
- `idx_audit_actor_time (actor_kind, actor_id, created_at)`.
- `idx_audit_entity_time (entity_type, entity_id, created_at)`.
- `idx_audit_action_time (action, created_at)`.

**Foreign Keys** — None. `actor_id` is polymorphic; integrity enforced at app layer (which is acceptable for an audit table). Avoids cascade ambiguity.

**Partitioning** — RANGE on `partition_key`, monthly.

**Constraints (DB role)** *(v1.1, HIGH-13 — concrete enforcement)*

Database-level immutability is enforced by explicit GRANT statements committed to source:

- `database/grants/runtime.sql` — `GRANT INSERT, SELECT ON audit_log TO 'salam_runtime'@'%';` (no UPDATE, no DELETE). Same pattern for `payment_logs`.
- `database/grants/migrator.sql` — `GRANT ALL ON audit_log TO 'salam_migrator'@'%';` used only by partition-roll operations.
- **CI integrity check**: a step queries `information_schema.user_privileges` for the runtime user; build fails if `UPDATE` or `DELETE` is found on `audit_log` or `payment_logs`.
- **Daily production monitor**: the same check runs as a scheduled command; alerts on drift to oncall.

**Soft Delete** — No.

**Retention** — 5 years.

---

### 8.2 `settings`

**Purpose.** Singleton key/value runtime configuration (prices, cooldowns, thresholds).

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `key` | VARCHAR(80) PK | no | — | Dotted key |
| `value` | JSON | no | — | Even scalars wrapped in JSON for type safety |
| `value_type` | ENUM('int','string','bool','json','money_minor') | no | — | Hint for admin editor |
| `description` | VARCHAR(255) | yes | NULL | |
| `updated_by_admin_id` | BIGINT UNSIGNED | yes | NULL | |
| `updated_at` | TIMESTAMP | yes | NULL | |
| `created_at` | TIMESTAMP | yes | NULL | |

**Foreign Keys**
- `updated_by_admin_id` → `admin_users.id` ON DELETE SET NULL.

**Soft Delete** — No.

**Audit Fields** — `updated_by_admin_id`; every change also in `audit_log`.

---

### 8.3 `feature_flags` *(new)*

**Purpose.** Boolean and rollout-percent toggles. Separate from `settings` because they have a different operational pattern (frequent toggling, percent rollouts).

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | INT UNSIGNED PK | no | auto | |
| `key` | VARCHAR(80) | no | — | |
| `is_enabled` | TINYINT(1) | no | 0 | Global on/off |
| `rollout_percent` | TINYINT UNSIGNED | no | 0 | 0–100; bucketed by user_id hash |
| `target_user_ids` | JSON | yes | NULL | Explicit allowlist (array of ids) |
| `description` | VARCHAR(255) | yes | NULL | |
| `updated_by_admin_id` | BIGINT UNSIGNED | yes | NULL | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_feature_flags_key` on `key`.

**Soft Delete** — No.

---

### 8.4 `idempotency_keys` *(new — §3 missing-tables)*

**Purpose.** Stores client-supplied `Idempotency-Key` headers along with the canonical response. Replays return the same response without re-executing the operation.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `scope` | VARCHAR(40) | no | — | e.g. `open`, `order_create` |
| `actor_kind` | ENUM('user','admin') | no | — | |
| `actor_id` | BIGINT UNSIGNED | no | — | |
| `key` | VARCHAR(60) | no | — | UUID or arbitrary string |
| `request_hash` | CHAR(64) | no | — | SHA-256 of canonical request — detects mismatched reuse |
| `response_status` | SMALLINT UNSIGNED | yes | NULL | HTTP status of cached response |
| `response_body` | TEXT | yes | NULL | Cached response |
| `state` | ENUM('pending','complete','failed') | no | `pending` | |
| `created_at` | TIMESTAMP | no | CURRENT_TIMESTAMP | |
| `expires_at` | TIMESTAMP | no | — | 24 h default |

**Unique Constraints**
- `uq_idempotency_scope_actor_key` on `(scope, actor_kind, actor_id, key)`.

**Indexes**
- `idx_idempotency_expires (expires_at)` — sweep.

**Soft Delete** — No.

**Storage note** — Hot path; consider Redis primary with DB as durable fallback. Schema above is the DB durable copy.

---

### 8.5 `data_subject_requests` *(new — privacy)*

**Purpose.** Tracks user-initiated data-export and data-deletion requests (AZ Personal Data Law analogue of GDPR Articles 15/17).

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `user_id` | BIGINT UNSIGNED | no | — | FK |
| `kind` | ENUM('export','deletion','correction') | no | — | |
| `status` | ENUM('received','processing','ready','delivered','failed','cancelled') | no | `received` | |
| `result_url` | VARCHAR(255) | yes | NULL | Signed S3 URL for export |
| `result_expires_at` | TIMESTAMP | yes | NULL | |
| `processed_by_admin_id` | BIGINT UNSIGNED | yes | NULL | |
| `notes` | VARCHAR(255) | yes | NULL | |
| `created_at`, `updated_at` | TIMESTAMP | yes | NULL | |

**Indexes**
- `idx_dsr_status_created (status, created_at)`.
- `idx_dsr_user (user_id)`.

**Foreign Keys**
- `user_id` → `users.id` ON DELETE RESTRICT.
- `processed_by_admin_id` → `admin_users.id` ON DELETE SET NULL.

**Soft Delete** — No.

---

### 8.6 `report_jobs` *(new — admin async exports)*

**Purpose.** Tracks long-running admin report generations (revenue, audit export, device CSV). Background workers fulfill them and place artefact on S3.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `requested_by_admin_id` | BIGINT UNSIGNED | no | — | |
| `report_kind` | VARCHAR(60) | no | — | e.g. `revenue.monthly`, `audit.export` |
| `parameters` | JSON | yes | NULL | Date range, filters |
| `status` | ENUM('queued','running','succeeded','failed','expired') | no | `queued` | |
| `result_url` | VARCHAR(255) | yes | NULL | |
| `result_expires_at` | TIMESTAMP | yes | NULL | |
| `error_message` | VARCHAR(255) | yes | NULL | |
| `started_at`, `finished_at` | TIMESTAMP | yes | NULL | |
| `created_at` | TIMESTAMP | no | CURRENT_TIMESTAMP | |

**Indexes**
- `idx_report_jobs_admin_created (requested_by_admin_id, created_at)`.
- `idx_report_jobs_status_created (status, created_at)`.

**Foreign Keys**
- `requested_by_admin_id` → `admin_users.id` ON DELETE RESTRICT.

**Soft Delete** — No.

---

## 9. Reporting Aggregates (Materialised)

These tables exist purely for performance — they are derived nightly from event tables. See §10 for the rationale (online reports against `open_commands`/`payments` would not scale).

### 9.1 `device_daily_stats` *(new)*

**Purpose.** One row per (device, day): open attempts/success/fail counts, avg latency, last online.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `device_id` | BIGINT UNSIGNED | no | — | FK |
| `stat_date` | DATE | no | — | UTC day |
| `opens_total` | INT UNSIGNED | no | 0 | |
| `opens_success` | INT UNSIGNED | no | 0 | |
| `opens_failed` | INT UNSIGNED | no | 0 | |
| `avg_latency_ms` | INT UNSIGNED | yes | NULL | |
| `was_online` | TINYINT(1) | yes | NULL | Any successful ping that day |
| `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_device_daily_stats_device_date` on `(device_id, stat_date)`.

**Indexes**
- `idx_device_daily_stats_date (stat_date)`.

**Foreign Keys**
- `device_id` → `devices.id` ON DELETE RESTRICT.

**Soft Delete** — No.

---

### 9.2 `revenue_daily_stats` *(new)*

**Purpose.** Aggregated revenue per day (gross, refunds, net), per purpose.

**Columns**

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | no | auto | |
| `stat_date` | DATE | no | — | |
| `purpose` | ENUM('device_sale','sub_main','sub_additional','sub_renewal','bundle') | no | — | |
| `gross_minor` | BIGINT UNSIGNED | no | 0 | |
| `refund_minor` | BIGINT UNSIGNED | no | 0 | |
| `net_minor` | BIGINT | no | 0 | |
| `tx_count` | INT UNSIGNED | no | 0 | |
| `updated_at` | TIMESTAMP | yes | NULL | |

**Unique Constraints**
- `uq_revenue_daily_stats` on `(stat_date, purpose)`.

**Indexes**
- `idx_revenue_daily_stats_date (stat_date)`.

**Soft Delete** — No.

---

### 9.3 `subscription_daily_stats` *(new)*

**Purpose.** Sub counts by status per day (active, expired, renewed, new).

**Columns** — `id`, `stat_date`, `active_count`, `expired_count`, `new_count`, `renewed_count`, `cancelled_count`, `updated_at`. Unique on `stat_date`.

---

## 10. Framework / System Tables

Laravel-standard. Listed for completeness; default Laravel definitions are sufficient unless noted.

| Table | Notes |
|---|---|
| `migrations` | Laravel default |
| `jobs` | Queue durable fallback (Redis primary, DB driver as backup) |
| `failed_jobs` | Default; reviewed daily |
| `sessions` | Admin panel sessions (DB driver). Mobile is stateless JWT |
| `password_reset_tokens` | Admin password reset |
| `cache` | Only if app falls back to DB cache driver (Redis primary) |
| `cache_locks` | Same |

No bespoke columns unless explicitly required.

---

## 11. Migration Plan (Ordering)

Migrations are split into logical bundles by domain to keep PRs reviewable. Order matters: each batch declares only FKs to tables in itself or prior batches.

| # | Batch | Tables |
|---|---|---|
| 1 | `00_system` | Laravel defaults (`migrations`, `jobs`, `failed_jobs`, `sessions`, `password_reset_tokens`, `cache`, `cache_locks`) |
| 2 | `01_identity_lookups` | `sim_operators`, `device_models`, `regions` |
| 3 | `02_identity_users` | `users`, `admin_users` |
| 4 | `03_identity_auth` | `user_devices`, `refresh_tokens`, `otps`, `auth_attempts`, `user_consents` |
| 5 | `04_devices` | `devices`, `device_users` (with virtual `is_active`), `device_user_history`, `invitations` |
| 6 | `05_payments` | `card_tokens`, `orders`, `order_items`, `payments`, `payment_logs` (partitioned), `payment_callbacks`, `refunds` |
| 7 | `06_subscriptions` | `subscriptions`, `subscription_periods` |
| 8 | `07_device_ops` | `open_commands` (partitioned), `device_diagnostics` (partitioned), `whitelist_changes` |
| 9→**11** | `11_notifications` *(real: appended **after** the deployed `08_rbac` / `09_settings` / `10_access`; the §11 ordering below predates the shipped batch layout, so the notifications bundle is the new last batch — not `08`)* | `notification_templates`, `notification_template_locales`, `notification_campaigns`, `notifications` (partitioned), `user_notification_settings` |
| 10 | `09_audit_ops` | `audit_log` (partitioned), `settings`, `feature_flags`, `idempotency_keys`, `data_subject_requests`, `report_jobs` |
| 11 | `10_stats` | `device_daily_stats`, `revenue_daily_stats`, `subscription_daily_stats` |
| 12 | `11_post_seed_constraints` | Cross-table circular FKs (e.g. `subscriptions.latest_order_id` requires `orders` to exist; that one is in 06 — already correct. Other circulars revisited here.) |

### 11.1 Seed Data

After batch 2 (lookups): seed `sim_operators` (Azercell, Bakcell, Nar), `device_models` (initial supported list), `regions` (Baku districts).

After batch 9: seed `notification_templates` and az/ru/en locale rows.

After batch 10: seed `settings` (prices, cooldowns, reminder days), `feature_flags` (initial flags off).

---

## 12. Normalization Review

### 12.1 Issues Found and Resolved in This Plan

| # | Original (v1.0 spec) | Problem | Resolution in v1.0 DB |
|---|---|---|---|
| N-1 | `devices.model` as `VARCHAR(80)` | Free-text duplicates, no driver-capability lookup | Introduced `device_models` lookup; FK |
| N-2 | `devices.sim_operator` as `VARCHAR(40)` | Same — analytics impossible without canonical list | Introduced `sim_operators`; FK |
| N-3 | Region absent | Reporting groupings impossible | Added `regions` |
| N-4 | `device_users` revocation overwrote roster, losing history | Cannot answer "who had access on date X" | Added `device_user_history` append-only |
| N-5 | Invitations modelled in `device_users` directly | Conflated pending invites with active roster; complicated payer logic | Added `invitations` |
| N-6 | Subscription renewals just bumped `ends_at` | Lost period history → no pro-rata refund support, no LTV math | Added `subscription_periods` append-only |
| N-7 | Refunds were rows in `payments` only | Conflated financial-event with admin workflow | Added `refunds` (workflow) ↔ `payments` (financial result) |
| N-8 | Inbound callbacks deduped via Redis only | At-least-once delivery + restart risks double processing | Added `payment_callbacks` table with `payload_hash` unique |
| N-9 | Notification templates inline in code | Admin cannot edit copy without deploy | Added `notification_templates` + `_locales` |
| N-10 | Settings + feature flags mixed | Different update cadence and audit needs | Split `feature_flags` from `settings` |
| N-11 | No idempotency persistence | Restart loses Redis dedupe | Added `idempotency_keys` |
| N-12 | No privacy / consent tracking | AZ personal data law non-compliance risk | Added `user_consents`, `data_subject_requests` |

### 12.2 Deliberate Denormalizations (with justification)

| Column | Why kept denormalized |
|---|---|
| `subscriptions.price_minor`, `term_days`, `currency` | Snapshot pricing — protects historical records against future price changes |
| `open_commands.driver` | Snapshot of driver type at command time; needed for forensic correlation if device driver was later changed |
| `payments.card_brand`, `card_last4` | Snapshot for receipts even if `card_tokens` row is revoked |
| `audit_log.actor_label`, `payload_redacted` | Snapshot identities/values for legal preservation |
| `order_items.description` | Receipt fidelity if product naming changes |

### 12.3 Schema Smells Accepted (with mitigation)

| Smell | Acceptance reason | Mitigation |
|---|---|---|
| ENUMs for many state fields | Read-only stable sets; storage-efficient | Schema versioning + migrations for changes; documented in `audit_log` |
| `audit_log.actor_id` polymorphic, no FK | Cannot FK to two parent tables; integrity at app layer | Strict app-layer test coverage; periodic referential-check job |
| JSON `metadata` columns | Future-proofing without table churn | Promoted to columns once query patterns stabilise |
| `device_users` UNIQUE via virtual column | MariaDB lacks partial unique indexes | Generated `is_active` STORED column carries the constraint; verified in CI |

---

## 13. Performance Bottlenecks Identified

### 13.1 Hot-write Tables

| Table | Risk | Mitigation |
|---|---|---|
| `open_commands` | Highest write volume; every tap. ~50/s sustained, 200/s burst target. Could reach 1–5 M rows/month at scale. | Monthly RANGE partitioning from day 1; archive partitions > 24 mo to S3 Parquet and DROP; secondary indexes kept minimal (4) |
| `audit_log` | Mid-write, but every privileged action plus financial; 5-year retention | Partitioned monthly; archive partitions > 12 mo to cold storage; indexes covered above |
| `device_diagnostics` | 4 pings/device/day × N devices × multiple sources | Partitioned monthly; 12-month retention |
| `notifications` | Fan-out on subscription reminders + opens | Partitioned monthly; only the inbox-channel (`channel='inapp'`) kept for 12 mo |
| `payment_logs` | Every Kapital interaction (req + resp pair) | Partitioned; secrets redacted; 5-year retention required |

### 13.2 Hot-read Patterns and Plans

| Query | Path planned |
|---|---|
| "Is user X allowed to open device Y?" | Single index hit on `device_users(device_id, user_id, status)` then `subscriptions(device_user_id, status, ends_at)`. Both covered by primary indexes. p99 ≤ 5 ms target. |
| "List user X's devices" | `device_users(user_id, status)` + join. Capped at ~50 devices/user. Acceptable as inline query. |
| "Owner roster of device Y" | `device_users(device_id, status)` + join. Capped. Inline. |
| "Device open history (user view)" | `open_commands(device_id, requested_at DESC)` with cursor; partition pruned by date filter. |
| "Admin dashboard KPIs" | NOT live SQL against `open_commands`/`payments`. Reads from `*_daily_stats`. Today's partial values computed via Redis counters then folded into the stats row at next nightly run. |
| "Subscription expiry sweep" | Index on `subscriptions(status='active', ends_at)`. Batch in 1k chunks. |
| "Reminder sweep" | Index on `subscriptions(ends_at)` filtered on `last_reminder_kind` to be idempotent. |

### 13.3 Cooldown / Idempotency Hot Path

- `open` cooldown checks live in **Redis** (`SETNX` with TTL), NOT DB. Database is consulted only for the durable command row.
- `Idempotency-Key` first checked in Redis cache; falls back to `idempotency_keys` table on miss. This avoids DB load on hot retries.

### 13.4 Read-Replica Candidates

| Workload | Replica |
|---|---|
| Admin dashboards, reports | Replica only |
| User open-history (long lookback) | Replica acceptable; tolerates seconds of lag |
| Open-permission check | **Primary only** — replica lag could allow expired-sub user to open |
| Payment-callback processing | **Primary only** |
| Notification fan-out | Replica acceptable for read of recipient list; writes go primary |

### 13.5 Connection Pool Sizing (Initial)

Per API node: PHP-FPM `pm.max_children = 64`, MariaDB pool ≤ 64 conns. With 2 API + 2 worker nodes: peak 256 conns to primary; raise `max_connections=400` for headroom. Replica sized same.

### 13.6 Backup / Restore Impact

- `mysqldump` on a 200 GB primary would block writes — **not acceptable**.
- Use `mariabackup` (physical hot backup) or `xtrabackup` for nightly full + binlog incremental.
- Test restore monthly; document RTO and validate against the 4-hour SLO.

---

## 14. Missing Tables Identified (Beyond Spec v1.0)

Summary of all additions made to the original schema, with rationale and priority. Already incorporated above.

| New table | Priority | Why missing in v1.0 spec |
|---|---|---|
| `sim_operators` | MVP | v1.0 used VARCHAR; cannot drive reporting/driver routing |
| `device_models` | MVP | v1.0 used VARCHAR; cannot drive driver-capability checks |
| `regions` | MVP-light | Reporting groupings; lightweight, easy to add |
| `device_user_history` | MVP | Audit "who had access when" requires append-only history |
| `invitations` | MVP | Pending invitations distinct from accepted roster |
| `subscription_periods` | MVP | Per-term history for pro-rata refunds, LTV |
| `refunds` | MVP | Workflow separate from financial event |
| `payment_callbacks` | MVP | At-least-once webhooks need durable dedupe |
| `notification_templates` + `_locales` | MVP | Admin-editable copy |
| `user_notification_settings` | MVP-light | Required for mute toggles even if minimal use at MVP |
| `feature_flags` | MVP-light | Operationally invaluable for rollouts |
| `idempotency_keys` | MVP | Restart resilience for retry-safe writes |
| `auth_attempts` | MVP | Brute-force defence |
| `user_consents` | MVP | AZ personal-data law compliance |
| `data_subject_requests` | MVP-light | Compliance |
| `report_jobs` | MVP-light | Admin async exports |
| `*_daily_stats` (3 tables) | Phase 2 | Performance for admin dashboards |

### 14.1 Tables Considered and Rejected

| Table | Reason rejected |
|---|---|
| `sessions` for mobile | Mobile is JWT-stateless; refresh tracked separately |
| `personal_access_tokens` (Sanctum) | Custom JWT pipeline planned; would duplicate |
| `media` for device photos | Photos can live on S3 keyed by `device_id`; no separate table needed for MVP |
| `webhooks_outbox` | No outbound webhooks to third parties at MVP |
| `device_firmware_versions` (history table) | Current snapshot on `devices` + entries in `device_diagnostics.firmware_version` cover need |
| `tax_invoices` | Deferred to Phase 2 per spec §0 open items |

---

## 15. Open Schema Questions Pending Decision

These DO NOT block migration drafting but must be resolved before Phase 2 cutover.

1. **Phone uniqueness across soft-deleted users.** If a user self-deletes and the same phone re-registers, is it a new user_id or the resurrected one? Current plan: new user_id. Confirm with product.
2. **Whitelist capacity overflow.** Hardware-defined limit per device; current schema records `whitelist_capacity_used`. Policy when an owner tries to exceed capacity is product-side (block with error vs. queue) — DB supports either.
3. **Owner transfer mechanics.** Should `device_users.role` upgrades preserve subscription, or trigger a fresh purchase? Schema supports both; product policy TBD.
4. **Refund proration on partial-term refund.** Schema supports negative `subscription_periods` rows; product policy on whether to actually shorten `subscriptions.ends_at` is TBD.
5. **Auto-renew billing model.** Will Kapital token be charged in our background job, or by Kapital scheduler? Affects whether we need an `autorenew_attempts` table later.

---

*End of Database Architecture v1.0.*
