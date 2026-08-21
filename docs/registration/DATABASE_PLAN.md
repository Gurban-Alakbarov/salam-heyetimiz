# DATABASE PLAN — REGISTRATION MODULE

> Planning only — **no migration is created yet.** Principle: **minimal, additive, backward-compatible.** Reuse `users`, `refresh_tokens`, `user_devices` as-is. The only schema change is making the OTP store email-capable.

---

## 1. Summary

| Table | Change | Risk |
|---|---|---|
| `otps` | **ALTER** — add `email`, add `channel`, relax `phone` to nullable, add index | Low (additive; existing rows keep working) |
| `users` | **One additive reserved column** — `password VARCHAR(255) NULL` (forward-compat for the future Security/password-login feature; **unused by this module**). Otherwise reuse `email`, `email_verified_at`, `full_name`, `phone`, `status` | None |
| `refresh_tokens`, `user_devices` | **No change** | None |
| (new tables) | **None required** | — |
| `settings` (data, not schema) | add catalog keys via `SettingsCatalog`: email-template toggle, unverified-prune grace, **per-`EmailType` subject/copy** keys (generic email system) | None (seed-only) |

**One migration**, additive (`otps` channel + reserved `users.password`). No new tables. No destructive operations.

---

## 2. Migration: extend `otps` for an email channel

Target file (created at implementation): `database/migrations/03_identity_auth/2026_XX_XX_XXXXXX_add_email_channel_to_otps.php`.

Planned schema delta (illustrative — not executed now):
```
ALTER TABLE otps
  ADD COLUMN email     VARCHAR(160) NULL AFTER phone,
  ADD COLUMN channel   ENUM('sms','email') NOT NULL DEFAULT 'sms' AFTER purpose,
  MODIFY  COLUMN phone VARCHAR(20)  NULL,                     -- was NOT NULL
  ADD INDEX idx_otps_email_purpose (email, purpose);
```

Why each:
- `email` — destination for email OTPs (registration + email login). Nullable because SMS rows have none.
- `channel` — selects the transport on issue (`email`→`EmailOtpTransport`, `sms`→`SmsOtpTransport`). **Default `'sms'` means every existing row + existing phone code path is unchanged** with zero backfill.
- `phone` → **nullable**: email rows carry no phone. This *relaxes* a constraint (safe for existing NOT-NULL data; no row violates a looser rule). The existing `idx_otps_phone_purpose` and all phone queries keep working.
- `idx_otps_email_purpose` — fast `(email, purpose)` lookup for verify, mirroring the phone index.

**Backward-compat guarantees:** existing phone-OTP issue/verify is byte-for-byte unaffected (channel defaults to `sms`, phone still written). No data migration/backfill. Reversible `down()` drops the two columns + index and restores `phone NOT NULL` (safe only while no email rows exist — documented in the migration).

> MariaDB note: `MODIFY COLUMN … NULL` on `phone` rewrites the column definition; on the production data volume this is a fast metadata/online operation. Verified compatible with the app's MariaDB. (Confirm with `pt-online-schema-change` only if the table is large at rollout — see `IMPLEMENTATION_PLAN.md §Rollout`.)

---

## 3. `users` — reuse + one reserved column

Registration uses existing columns; **one additive nullable column is reserved now** so the future password feature is pure additive code (no auth rewrite):
- `full_name` ← `trim(first_name.' '.last_name)` (the form's two fields collapse into the existing single column; **no new first/last columns** — keeps the model stable and the read API unchanged).
- `email` (UNIQUE, nullable) — set at register.
- `email_verified_at` — **the verification gate**: `NULL` until `/verify-email`, then `now()`.
- `phone` (UNIQUE) — set at register.
- `status` — stays `active` (no new enum value; the gate is `email_verified_at`, not status).
- **`password VARCHAR(255) NULL` — RESERVED** (`ADD COLUMN password VARCHAR(255) NULL AFTER email_verified_at`). **Unused by this module**; populated only when the user opts into a password from the Security cabinet later. Reserving it now means Email + Password login arrives as additive code against an existing column (see `USER_REGISTRATION_ARCHITECTURE.md §10`). Nullable ⇒ passwordless users are the norm; bcrypt hash when set.

> If product later wants distinct `first_name`/`last_name` columns, that is a **separate, optional** additive migration — explicitly **not** required for this flow and not planned here.

---

## 4. Settings (data seed, not schema)

Add to `SettingsCatalog` (seed-only, no table change):
- `email.html_enabled` (bool, default `true`) — toggle HTML templates vs. plain-text fallback (applies to all email types, not just OTP).
- `otp.unverified_prune_hours` (int, default `72`) — grace before pruning unverified accounts (used by the optional `auth:prune-unverified` command).
- **Per-`EmailType` subject + short-copy keys** (generic email system, §7 of the architecture) — e.g. `email.subject_registration_otp`, `email.subject_login_otp`, and reserved `…_welcome`, `…_password_set`, `…_password_changed`, `…_email_changed`, `…_security_alert`. Only the OTP subjects are needed by this module; the rest are seeded as reserved defaults so future email types are template-only.

Existing keys reused as-is (no hardcoding): `otp.otp_ttl_seconds`, `otp.otp_length`, `otp.otp_resend_seconds`, `otp.otp_max_attempts`, `otp.tpl_registration`, `otp.tpl_login`, `email.*` SMTP + `from_*` (**production-ready: Brevo, SPF/DKIM**), `otp.enable_email_otp`.

---

## 5. Rejected alternatives (with rationale)

| Option | Why rejected |
|---|---|
| **New `email_otps` table + `EmailOtpService`** | Duplicates the entire OTP engine (generate/hash/verify/throttle) — violates the "reuse, don't recreate" principle. Two code paths to keep in sync. |
| **`pending_registrations` table** (defer user creation until verify) | Extra table + extra lifecycle; loses reuse of the `users` unique constraints; the `email_verified_at` gate already gives a clean "unverified" state with zero new tables. |
| **Redis blob for pending registration** | Splits identity state across Redis + MySQL; inconsistent with the OTP store living in `otps`; harder to audit. |
| **Add `pending` to `users.status` enum** | Enum ALTER on a hot table; unnecessary — `email_verified_at IS NULL` already expresses "unverified". |
| **Generalise `otps.phone` → `destination`** | Larger rename touching all existing phone queries/indexes; higher risk than adding a nullable `email` + `channel`. |

---

## 6. Data-integrity rules enforced at the DB / app layer

- `users.email` UNIQUE + `users.phone` UNIQUE — duplicate verified accounts impossible.
- OTP single-use via `otps.consumed_at` (existing).
- OTP validity window via `otps.expires_at` (existing).
- Attempts cap via `otps.attempts`/`max_attempts` (existing).
- **No** FK from `users` to `devices`/`device_users`/`subscriptions`/`complexes` is written at registration (domain invariant — see `USER_REGISTRATION_ARCHITECTURE.md §6`).

---

## 7. Rollback (DB)

- The migration `down()` removes `email` + `channel` + `idx_otps_email_purpose` and (only if no email rows exist) restores `phone NOT NULL`. Because the change is additive and defaulted, rolling back the **code** while leaving the columns in place is also safe (the extra nullable columns are simply unused). Full rollback steps in `IMPLEMENTATION_PLAN.md §Rollback`.
