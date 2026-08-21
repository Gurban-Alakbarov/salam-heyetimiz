# Salam Həyətimiz — Migration Audit (batches 00–04)

**Version:** 1.0
**Date:** 2026-06-13
**Scope:** the migrations created in this commit — batches **00–04** only. Batches 05–11 are not yet written and are out of scope.
**Method:** the as-built migration DDL (Laravel Blueprint → MariaDB) is reconstructed per table and compared, column-by-column and constraint-by-constraint, against [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md) v1.1. Type mapping used: `id`→BIGINT UNSIGNED AI, `smallIncrements`→SMALLINT UNSIGNED AI, `string(n)`→VARCHAR(n), `boolean`→TINYINT(1), `unsignedTinyInteger`→TINYINT UNSIGNED, `unsignedSmallInteger`→SMALLINT UNSIGNED, `tinyInteger`→TINYINT, `integer`→INT, `unsignedBigInteger`→BIGINT UNSIGNED, `binary(255)`→VARBINARY(255), `json`→JSON (LONGTEXT+CHECK on MariaDB), `timestamp(3)`→TIMESTAMP(3), `decimal(10,7)`→DECIMAL(10,7), `softDeletes`→`deleted_at TIMESTAMP NULL`, `timestamps`→`created_at/updated_at TIMESTAMP NULL`.
**No code was modified to produce this audit.**

## Verdict summary

| | Count |
|---|---|
| Tables audited | 21 (7 framework + 14 domain across batches 00–04) |
| Column-/constraint-level **matches** | 19 tables fully conform |
| **Deviations** (documented, intentional) | 2 |
| Undocumented mismatches / errors | **0** |

### Deviation register

| # | Table | Deviation | Type | Why | Action |
|---|---|---|---|---|---|
| D-1 | `admin_users` | `preferred_language ENUM('az','ru','en') NOT NULL DEFAULT 'az'` is **present in the migration but absent from DATABASE_ARCHITECTURE.md §1.2** | Addition (impl ahead of doc) | Required by LOCALIZATION_SPECIFICATION §6.2 / Appendix E; consolidated as PROJECT_CONSTITUTION Appendix A.3 (a documentation-reconciliation item, explicitly not a redesign). | **Update `DATABASE_ARCHITECTURE.md §1.2`** to add this column (doc catch-up). The column itself is correct. |
| D-2 | `invitations` | `linked_order_id` column exists **without** its `→ orders.id` FK | Deferred constraint | `orders` is created in **batch 05**; DB §11 mandates each batch reference only prior/own tables, and cross-batch circular FKs are added in **batch 11 (`11_post_seed_constraints`)**. | Add the FK in batch 11 (already planned). No action now. |

Everything else below is a **MATCH**. Two items are flagged **VERIFY-AT-DDL** (correct by intent; confirm the emitted DDL once `php artisan migrate` runs on PHP 8.4 + MariaDB 11):
- **V-1** `admin_users.totp_secret` — `binary('totp_secret', 255)` must emit `VARBINARY(255)` (Laravel 11/12 binary-with-length), not `BLOB`.
- **V-2** `device_users.is_active` / `invitations.is_pending` — `storedAs(...)` must emit `TINYINT UNSIGNED GENERATED ALWAYS AS (...) STORED` participating in the composite UNIQUE; the CI concurrency check `ci:device-users-uniqueness` (HIGH-07 Plan A) is the gate.

---

## A. Batch 00 — `00_system` (framework tables)

DATABASE_ARCHITECTURE.md §10 declares these **Laravel-standard** ("default Laravel definitions are sufficient unless noted"), so there is no column-level spec to diff; the audit confirms they are the standard L12 shapes and nothing project-specific leaked in.

| Table | Columns (type) | Keys / Indexes | vs DB §10 |
|---|---|---|---|
| `cache` | `key` VARCHAR PK · `value` MEDIUMTEXT · `expiration` INT | PK(key) | ✓ standard |
| `cache_locks` | `key` VARCHAR PK · `owner` VARCHAR · `expiration` INT | PK(key) | ✓ standard |
| `jobs` | `id` BIGINT AI · `queue` VARCHAR · `payload` LONGTEXT · `attempts` TINYINT UNSIGNED · `reserved_at` INT UNSIGNED NULL · `available_at` INT UNSIGNED · `created_at` INT UNSIGNED | PK(id), INDEX(queue) | ✓ standard |
| `job_batches` | `id` VARCHAR PK · `name` · `total_jobs` INT · `pending_jobs` INT · `failed_jobs` INT · `failed_job_ids` LONGTEXT · `options` MEDIUMTEXT NULL · `cancelled_at` INT NULL · `created_at` INT · `finished_at` INT NULL | PK(id) | ✓ standard |
| `failed_jobs` | `id` BIGINT AI · `uuid` VARCHAR UNIQUE · `connection` TEXT · `queue` TEXT · `payload` LONGTEXT · `exception` LONGTEXT · `failed_at` TIMESTAMP useCurrent | PK(id), UNIQUE(uuid) | ✓ standard; matches `QUEUE_FAILED_DRIVER=database-uuids` |
| `sessions` | `id` VARCHAR PK · `user_id` BIGINT NULL · `ip_address` VARCHAR(45) NULL · `user_agent` TEXT NULL · `payload` LONGTEXT · `last_activity` INT | PK(id), INDEX(user_id), INDEX(last_activity) | ✓ standard |
| `password_reset_tokens` | `email` VARCHAR PK · `token` VARCHAR · `created_at` TIMESTAMP NULL | PK(email) | ✓ standard (admin reset; mobile is OTP) |

**Batch 00 verdict:** ✓ conforms to DB §10. No `users` table created here (the project `users` is the custom batch-02 table — correctly split from Laravel's default bundle).

---

## B. Batch 01 — `01_identity_lookups`

### `sim_operators` — DB §2.1

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | SMALLINT UNSIGNED AI PK | no | auto | |
| 2 | code | VARCHAR(20) | no | — | |
| 3 | name | VARCHAR(60) | no | — | |
| 4 | country_iso | CHAR(2) | no | `AZ` | |
| 5 | mcc_mnc | VARCHAR(10) | yes | NULL | |
| 6 | is_active | TINYINT(1) | no | 1 | |
| 7 | created_at | TIMESTAMP | yes | NULL | |
| 8 | updated_at | TIMESTAMP | yes | NULL | |

Unique: `uq_sim_operators_code(code)`. Indexes: PK only. FKs: none. Generated: none.
**vs DB §2.1:** ✓ MATCH (columns, types, unique, audit-fields-none all per spec).

### `device_models` — DB §2.2

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | SMALLINT UNSIGNED AI PK | no | auto | |
| 2 | vendor | VARCHAR(60) | no | — | |
| 3 | model_code | VARCHAR(60) | no | — | |
| 4 | supports_clip | TINYINT(1) | no | 1 | |
| 5 | supports_sms | TINYINT(1) | no | 1 | |
| 6 | supports_mqtt | TINYINT(1) | no | 0 | |
| 7 | default_driver_type | ENUM(clip,sms,clip_sms,mqtt) | no | clip_sms | |
| 8 | fallback_open_driver | ENUM(clip,sms,clip_sms,mqtt) | yes | NULL | v1.1 HIGH-03 |
| 9 | whitelist_capacity | SMALLINT UNSIGNED | **no** | **100** | v1.1 HIGH-05 (NOT NULL) |
| 10 | sms_open_command | VARCHAR(40) | yes | NULL | |
| 11 | notes | VARCHAR(255) | yes | NULL | |
| 12 | is_active | TINYINT(1) | no | 1 | |
| 13 | created_at / updated_at | TIMESTAMP | yes | NULL | |

Unique: `uq_device_models_vendor_model(vendor, model_code)`. FKs: none. Generated: none.
**vs DB §2.2:** ✓ MATCH (incl. `whitelist_capacity` NOT NULL default 100 and `fallback_open_driver`).

### `regions` — DB §2.3

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | SMALLINT UNSIGNED AI PK | no | auto | |
| 2 | code | VARCHAR(20) | no | — | |
| 3 | name | VARCHAR(80) | no | — | |
| 4 | parent_id | SMALLINT UNSIGNED | yes | NULL | self-ref |
| 5 | is_active | TINYINT(1) | no | 1 | |
| 6 | created_at / updated_at | TIMESTAMP | yes | NULL | |

Unique: `uq_regions_code(code)`. Index: `idx_regions_parent(parent_id)`. FK: `fk_regions_parent_id parent_id→regions.id` ON UPDATE CASCADE ON DELETE RESTRICT. Generated: none.
**vs DB §2.3:** ✓ MATCH.

---

## C. Batch 02 — `02_identity_users`

### `admin_users` — DB §1.2

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | email | VARCHAR(160) | no | — | |
| 3 | password | VARCHAR(255) | no | — | bcrypt |
| 4 | name | VARCHAR(120) | no | — | |
| 5 | role | ENUM(super_admin,technical) | no | technical | |
| 6 | phone | VARCHAR(20) | yes | NULL | |
| 7 | totp_secret | VARBINARY(255) | yes | NULL | **V-1** verify DDL |
| 8 | is_2fa_enabled | TINYINT(1) | no | 0 | |
| 9 | is_2fa_enforced_at | TIMESTAMP | yes | NULL | |
| 10 | recovery_codes_hashes | JSON | yes | NULL | CRIT-09 |
| 11 | recovery_codes_generated_at | TIMESTAMP | yes | NULL | CRIT-09 |
| 12 | password_changed_at | TIMESTAMP | yes | NULL | |
| 13 | failed_login_count | SMALLINT UNSIGNED | no | 0 | |
| 14 | locked_until | TIMESTAMP | yes | NULL | |
| 15 | status | ENUM(active,suspended,offboarded) | no | active | |
| **16** | **preferred_language** | **ENUM(az,ru,en)** | **no** | **az** | **⚠ D-1 — not in DB §1.2** |
| 17 | last_login_at | TIMESTAMP | yes | NULL | |
| 18 | last_login_ip | VARCHAR(45) | yes | NULL | |
| 19 | remember_token | VARCHAR(100) | yes | NULL | |
| 20 | created_at / updated_at | TIMESTAMP | yes | NULL | |
| 21 | created_by_admin_id | BIGINT UNSIGNED | yes | NULL | |
| 22 | updated_by_admin_id | BIGINT UNSIGNED | yes | NULL | |

Unique: `uq_admin_users_email(email)`. Indexes: `idx_admin_users_status(status)`, `idx_admin_users_role(role)`. FKs: `created_by_admin_id`, `updated_by_admin_id` → `admin_users.id` ON UPDATE CASCADE ON DELETE SET NULL (self-ref). Generated: none.
**vs DB §1.2:** ⚠ DEVIATION **D-1** — `preferred_language` is an addition beyond the spec (reconciliation per LOC §6.2 / Constitution A.3). All other 21 columns/constraints MATCH exactly.

### `users` — DB §1.1

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | phone | VARCHAR(20) | no | — | E.164 |
| 3 | phone_country | CHAR(2) | no | AZ | |
| 4 | full_name | VARCHAR(120) | yes | NULL | |
| 5 | email | VARCHAR(160) | yes | NULL | |
| 6 | email_verified_at | TIMESTAMP | yes | NULL | |
| 7 | preferred_language | ENUM(az,ru,en) | no | az | |
| 8 | status | ENUM(active,blocked,self_deleted) | no | active | |
| 9 | blocked_reason | VARCHAR(255) | yes | NULL | |
| 10 | blocked_by_admin_id | BIGINT UNSIGNED | yes | NULL | |
| 11 | last_login_at | TIMESTAMP | yes | NULL | |
| 12 | last_login_ip | VARCHAR(45) | yes | NULL | |
| 13 | created_at / updated_at | TIMESTAMP | yes | NULL | |
| 14 | deleted_at | TIMESTAMP | yes | NULL | soft delete |

Unique: `uq_users_phone(phone)` (plain — HIGH-12), `uq_users_email(email)` (NULL-distinct). Indexes: `idx_users_status_created_at(status,created_at)`, `idx_users_last_login_at(last_login_at)`. FK: `fk_users_blocked_by_admin_id blocked_by_admin_id→admin_users.id` ON UPDATE CASCADE ON DELETE SET NULL. Generated: none.
**vs DB §1.1:** ✓ MATCH (incl. plain phone UNIQUE per HIGH-12 and soft delete).

---

## D. Batch 03 — `03_identity_auth`

### `user_devices` — DB §1.3

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | user_id | BIGINT UNSIGNED | no | — | |
| 3 | install_uuid | CHAR(36) | no | — | |
| 4 | platform | ENUM(ios,android) | no | — | |
| 5 | os_version | VARCHAR(40) | yes | NULL | |
| 6 | app_version | VARCHAR(20) | yes | NULL | |
| 7 | device_model | VARCHAR(80) | yes | NULL | |
| 8 | push_token | VARCHAR(255) | yes | NULL | |
| 9 | push_token_updated_at | TIMESTAMP | yes | NULL | |
| 10 | push_invalid | TINYINT(1) | no | 0 | |
| 11 | biometric_enrolled | TINYINT(1) | no | 0 | |
| 12 | last_seen_at | TIMESTAMP | yes | NULL | |
| 13 | last_seen_ip | VARCHAR(45) | yes | NULL | |
| 14 | revoked_at | TIMESTAMP | yes | NULL | |
| 15 | created_at / updated_at | TIMESTAMP | yes | NULL | |

Unique: `uq_user_devices_user_install(user_id, install_uuid)`. Indexes: `idx_user_devices_push_token(push_token)`, `idx_user_devices_user_revoked(user_id, revoked_at)`. FK: `user_id→users.id` ON UPDATE CASCADE ON DELETE CASCADE. Generated: none.
**vs DB §1.3:** ✓ MATCH.

### `refresh_tokens` — DB §1.4

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | user_id | BIGINT UNSIGNED | no | — | |
| 3 | user_device_id | BIGINT UNSIGNED | no | — | |
| 4 | token_hash | CHAR(64) | no | — | SHA-256 |
| 5 | issued_at | TIMESTAMP | no | CURRENT_TIMESTAMP | |
| 6 | last_used_at | TIMESTAMP | yes | NULL | |
| 7 | expires_at | TIMESTAMP | no | — | 60 days |
| 8 | revoked_at | TIMESTAMP | yes | NULL | |
| 9 | revocation_reason | ENUM(rotated,logout,password_change,admin,security,expired) | yes | NULL | |
| 10 | replaced_by_id | BIGINT UNSIGNED | yes | NULL | self-ref |
| 11 | ip | VARCHAR(45) | yes | NULL | |
| 12 | user_agent | VARCHAR(255) | yes | NULL | |

Unique: `uq_refresh_tokens_token_hash(token_hash)`. Indexes: `idx_refresh_tokens_user_revoked(user_id, revoked_at)`, `idx_refresh_tokens_expires_at(expires_at)`. FKs: `user_id→users` CASCADE; `user_device_id→user_devices` CASCADE; `replaced_by_id→refresh_tokens` SET NULL. No `created_at/updated_at` (uses `issued_at`). Generated: none.
**vs DB §1.4:** ✓ MATCH.

### `otps` — DB §1.5

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | phone | VARCHAR(20) | no | — | |
| 3 | code_hash | CHAR(64) | no | — | |
| 4 | purpose | ENUM(login,recover,email_verify) | no | login | |
| 5 | attempts | TINYINT UNSIGNED | no | 0 | |
| 6 | max_attempts | TINYINT UNSIGNED | no | 5 | |
| 7 | expires_at | TIMESTAMP | no | — | TTL 120s |
| 8 | consumed_at | TIMESTAMP | yes | NULL | |
| 9 | issued_ip | VARCHAR(45) | yes | NULL | |
| 10 | created_at | TIMESTAMP | yes | NULL | (no updated_at) |

Unique: none. Indexes: `idx_otps_phone_purpose(phone, purpose)`, `idx_otps_expires_at(expires_at)`. FK: none. Generated: none.
**vs DB §1.5:** ✓ MATCH.

### `auth_attempts` — DB §1.6

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | actor_kind | ENUM(user,admin) | no | — | |
| 3 | identifier | VARCHAR(160) | no | — | phone/email |
| 4 | outcome | ENUM(success,wrong_credential,locked,rate_limited,otp_expired,otp_max_attempts,2fa_failed) | no | — | |
| 5 | ip | VARCHAR(45) | no | — | |
| 6 | user_agent | VARCHAR(255) | yes | NULL | |
| 7 | request_id | CHAR(26) | yes | NULL | ULID |
| 8 | created_at | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | ms precision |

Unique: none. Indexes: `idx_auth_attempts_identifier_time`, `idx_auth_attempts_ip_time`, `idx_auth_attempts_outcome_time`. FK: none. Generated: none.
**vs DB §1.6:** ✓ MATCH (incl. TIMESTAMP(3) and the 7-value outcome enum).

### `user_consents` — DB §1.7

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | user_id | BIGINT UNSIGNED | no | — | |
| 3 | consent_kind | ENUM(terms,privacy,marketing_push,marketing_sms,data_processing) | no | — | |
| 4 | document_version | VARCHAR(20) | no | — | e.g. terms-v3 |
| 5 | granted | TINYINT(1) | no | — | |
| 6 | ip | VARCHAR(45) | yes | NULL | |
| 7 | user_agent | VARCHAR(255) | yes | NULL | |
| 8 | created_at | TIMESTAMP | no | CURRENT_TIMESTAMP | append-only |

Unique: none. Index: `idx_user_consents_user_kind(user_id, consent_kind, created_at)`. FK: `user_id→users` ON DELETE CASCADE. Generated: none.
**vs DB §1.7:** ✓ MATCH.

---

## E. Batch 04 — `04_devices`

### `devices` — DB §3.1

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | serial | VARCHAR(64) | no | — | |
| 3 | device_model_id | SMALLINT UNSIGNED | no | — | FK |
| 4 | firmware_version | VARCHAR(40) | yes | NULL | |
| 5 | sim_phone | VARCHAR(20) | no | — | |
| 6 | sim_operator_id | SMALLINT UNSIGNED | yes | NULL | FK |
| 7 | sim_iccid | VARCHAR(22) | yes | NULL | |
| 8 | driver_type | ENUM(clip,sms,clip_sms,mqtt) | no | clip_sms | |
| 9 | status | ENUM(unassigned,active,suspended,disabled,decommissioned) | no | unassigned | |
| 10 | owner_user_id | BIGINT UNSIGNED | yes | NULL | FK |
| 11 | region_id | SMALLINT UNSIGNED | yes | NULL | FK |
| 12 | location_label | VARCHAR(160) | yes | NULL | |
| 13 | latitude | DECIMAL(10,7) | yes | NULL | |
| 14 | longitude | DECIMAL(10,7) | yes | NULL | |
| 15 | last_online_at | TIMESTAMP | yes | NULL | |
| 16 | last_signal_strength | TINYINT | yes | NULL | 0–31 |
| 17 | consecutive_offline_diagnostics | SMALLINT UNSIGNED | no | 0 | |
| 18 | whitelist_capacity_used | SMALLINT UNSIGNED | no | 0 | **DEPRECATED** (HIGH-05) |
| 19 | sim_credit_minor | INT | yes | NULL | placeholder (HIGH-04) |
| 20 | sim_credit_checked_at | TIMESTAMP | yes | NULL | |
| 21 | sim_status | ENUM(active,low_credit,suspended,unknown) | no | unknown | |
| 22 | metadata | JSON | yes | NULL | |
| 23 | registered_by_admin_id | BIGINT UNSIGNED | yes | NULL | FK |
| 24 | activated_at | TIMESTAMP | yes | NULL | |
| 25 | decommissioned_at | TIMESTAMP | yes | NULL | |
| 26 | decommission_reason | VARCHAR(255) | yes | NULL | |
| 27 | created_at / updated_at | TIMESTAMP | yes | NULL | |
| 28 | deleted_at | TIMESTAMP | yes | NULL | soft delete |
| 29 | created_by_admin_id | BIGINT UNSIGNED | yes | NULL | FK |
| 30 | updated_by_admin_id | BIGINT UNSIGNED | yes | NULL | FK |

Unique: `uq_devices_serial(serial)`, `uq_devices_sim_phone(sim_phone)`. Indexes: `idx_devices_owner(owner_user_id)`, `idx_devices_status(status)`, `idx_devices_status_last_online(status,last_online_at)`, `idx_devices_region_status(region_id,status)`, `idx_devices_model(device_model_id)`. FKs: `device_model_id→device_models` RESTRICT, `sim_operator_id→sim_operators` RESTRICT, `owner_user_id→users` RESTRICT, `region_id→regions` RESTRICT, `registered_by_admin_id`/`created_by_admin_id`/`updated_by_admin_id`→`admin_users` SET NULL. Generated: none.
**vs DB §3.1:** ✓ MATCH (incl. deprecated `whitelist_capacity_used` retained, sim_* placeholders, soft delete, all FK ON DELETE rules).

### `device_users` — DB §3.2

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | device_id | BIGINT UNSIGNED | no | — | FK |
| 3 | user_id | BIGINT UNSIGNED | no | — | FK |
| 4 | role | ENUM(owner,user) | no | user | |
| 5 | added_by_user_id | BIGINT UNSIGNED | yes | NULL | FK |
| 6 | added_by_admin_id | BIGINT UNSIGNED | yes | NULL | FK |
| 7 | access_window_start | TIME | yes | NULL | [P2] |
| 8 | access_window_end | TIME | yes | NULL | [P2] |
| 9 | access_days_mask | TINYINT UNSIGNED | yes | NULL | [P2] |
| 10 | status | ENUM(active,revoked) | no | active | |
| 11 | **is_active** | TINYINT UNSIGNED **GENERATED ALWAYS AS** (`CASE WHEN status='active' THEN 1 ELSE NULL END`) **STORED** | yes | (generated) | **V-2** |
| 12 | last_open_at | TIMESTAMP | yes | NULL | |
| 13 | revoked_at | TIMESTAMP | yes | NULL | |
| 14 | revoked_by_user_id | BIGINT UNSIGNED | yes | NULL | FK |
| 15 | revoked_by_admin_id | BIGINT UNSIGNED | yes | NULL | FK |
| 16 | created_at / updated_at | TIMESTAMP | yes | NULL | |

Unique: `uq_device_users_active(device_id, user_id, is_active)` — NULL-distinct ⇒ one active row per (device,user). Indexes: `idx_device_users_user_status(user_id,status)`, `idx_device_users_device_status(device_id,status)`, `idx_device_users_role(device_id,role)`. FKs: `device_id→devices` RESTRICT, `user_id→users` RESTRICT, `added_by_user_id`/`revoked_by_user_id`→`users` SET NULL, `added_by_admin_id`/`revoked_by_admin_id`→`admin_users` SET NULL. Generated: `is_active` (STORED).
**vs DB §3.2:** ✓ MATCH — STORED generated `is_active` + composite UNIQUE implements the partial-unique exactly as specified (Plan A; HIGH-07 fallback documented).

### `device_user_history` — DB §3.3

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | device_id | BIGINT UNSIGNED | no | — | FK |
| 3 | user_id | BIGINT UNSIGNED | no | — | FK |
| 4 | event | ENUM(added,revoked,role_changed,re_added) | no | — | |
| 5 | from_role | ENUM(owner,user) | yes | NULL | |
| 6 | to_role | ENUM(owner,user) | yes | NULL | |
| 7 | actor_kind | ENUM(user,admin,system) | no | — | |
| 8 | actor_id | BIGINT UNSIGNED | yes | NULL | polymorphic |
| 9 | created_at | TIMESTAMP(3) | no | CURRENT_TIMESTAMP(3) | append-only |

Unique: none. Indexes: `idx_dev_user_hist_device_time(device_id,created_at)`, `idx_dev_user_hist_user_time(user_id,created_at)`. FKs: `device_id→devices` RESTRICT, `user_id→users` RESTRICT. Generated: none.
**vs DB §3.3:** ✓ MATCH.

### `invitations` — DB §3.4

| # | Column | Type | Null | Default | Notes |
|---|---|---|---|---|---|
| 1 | id | BIGINT UNSIGNED AI PK | no | auto | |
| 2 | device_id | BIGINT UNSIGNED | no | — | FK |
| 3 | invited_by_user_id | BIGINT UNSIGNED | no | — | FK |
| 4 | invitee_phone | VARCHAR(20) | no | — | |
| 5 | invitee_user_id | BIGINT UNSIGNED | yes | NULL | FK |
| 6 | role | ENUM(user,owner) | no | user | |
| 7 | payer | ENUM(owner,invitee) | no | owner | |
| 8 | token | CHAR(40) | no | — | |
| 9 | status | ENUM(pending,accepted,declined,expired,cancelled) | no | pending | |
| 10 | **is_pending** | TINYINT UNSIGNED **GENERATED … STORED** (`CASE WHEN status='pending' THEN 1 ELSE NULL END`) | yes | (generated) | **V-2** |
| 11 | expires_at | TIMESTAMP | no | — | 7 days |
| 12 | accepted_at | TIMESTAMP | yes | NULL | |
| 13 | accepted_device_user_id | BIGINT UNSIGNED | yes | NULL | FK |
| 14 | linked_order_id | BIGINT UNSIGNED | yes | NULL | **⚠ D-2 — FK to orders deferred to batch 11** |
| 15 | created_at / updated_at | TIMESTAMP | yes | NULL | |

Unique: `uq_invitations_token(token)`, `uq_invitations_active_target(device_id, invitee_phone, is_pending)`. Indexes: `idx_invitations_status_expires(status,expires_at)`, `idx_invitations_invitee_phone(invitee_phone)`. FKs present: `device_id→devices` RESTRICT, `invited_by_user_id→users` RESTRICT, `invitee_user_id→users` SET NULL, `accepted_device_user_id→device_users` SET NULL. FK **absent**: `linked_order_id→orders` (D-2). Generated: `is_pending` (STORED).
**vs DB §3.4:** ⚠ DEVIATION **D-2** — `linked_order_id` FK intentionally deferred to batch 11 (`orders` is batch 05). All columns, the second STORED-generated partial-unique, and the four present FKs MATCH.

---

## F. Cross-cutting conformance checks

| Check | DB Arch ref | Result |
|---|---|---|
| Money in integer minor units (no floats) | §0.1 | ✓ no money columns in 00–04; `sim_credit_minor` is INT |
| utf8mb4_unicode_ci + InnoDB | §0 | ✓ global via `config/database.php` |
| Timestamps UTC; TIMESTAMP(3) on hot event tables | §0.1 | ✓ `auth_attempts`, `device_user_history` use (3) |
| Soft delete only on `users`, `devices` | §0.3 | ✓ exactly those two |
| FK default ON UPDATE CASCADE / ON DELETE RESTRICT unless noted | §0.2 | ✓ all FKs follow §0.2 / per-table overrides |
| Lookups carry no `created_by/updated_by` (audit via audit_log) | §0.4 | ✓ sim_operators/device_models/regions have none |
| Index naming `idx_*` / `uq_*` / `fk_*` | §0.1 | ✓ throughout |
| Partitioned tables in 00–04 | §0.6 | ✓ none (open_commands/audit_log/etc. are batches 05–09) |

*End of Migration Audit v1.0.*
