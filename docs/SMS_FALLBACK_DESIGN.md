# SMS Fallback Design — Salam Həyətimiz

**Date:** 2026-06-21
**Goal:** make the device **SMS-open fallback optional**, controlled by a **global admin setting
`sms_fallback_enabled`**, while keeping the invariant **SMS is never the primary transport**.
Design only — no code (the implementation is a future change; see effort at the end).

---

## 1. How the open-command transport works today (verified)

```
openDevice → queue → CommandDispatcher.dispatch(command)
   primaryType = command.driver  (snapshot of device.driver_type → "traccar")
   attempt #1: resolve device-driver.traccar → TraccarDriver.open()
      ├─ sent     → dispatched (terminal; later "opened" via telemetry read-back)
      ├─ queued   → failed("device_offline")  ← TRANSIENT
      └─ error    → failed(reason)
   if attempt#1 failed AND reason ∈ transient_failure_codes  (R-GSM-04):
        fallbackType = DeviceModel.fallback_open_driver  ("sms")
        attempt #2: resolve device-driver.sms → SmsDriver.open()  (OUTPUT0=1 to SIM)
```

- **Primary is always `device.driver_type` = `traccar`.** SMS is reached **only** via the one-shot fallback.
- Transient codes (`config/domain/device_comm.php`): `device_offline, timeout, busy, network_temporary`.
- Fallback is gated **per DeviceModel** by `fallback_open_driver` (set to `sms` for UMKa 310).
- **There is no global on/off switch**, and **no settings/feature-flag module is deployed.**
- `SMS_PROVIDER=fake` today, so the fallback would no-op-succeed against the fake gateway anyway.

**Conclusion:** "SMS never primary" is already structurally guaranteed. What's missing is a **global,
admin-toggleable enable/disable** for the fallback path.

## 2. Required behaviour

| `sms_fallback_enabled` | Behaviour |
|---|---|
| **false** (disabled) | **Traccar only.** A failed/offline Traccar attempt → command **fails** (no SMS attempt). |
| **true** (enabled) | Traccar first; on **transient** failure / device offline → **one** SMS attempt (existing R-GSM-04 path). |

Invariants preserved: SMS is never attempt #1; the global flag only enables/disables attempt #2.

## 3. Design

### 3.1 Where the setting lives — new lightweight Settings store
No settings/feature-flags table exists. Introduce a minimal, cached **global settings** mechanism (also
unblocks future flags):

- **Table `app_settings`**: `key` (PK, string), `value` (string/json), `type` (bool|int|string|json),
  `updated_by_admin_id`, `updated_at`.
- **`SettingsService`** with a **cached** accessor: `Settings::bool('sms_fallback_enabled', default=false)`
  — read-through cache (Redis), invalidated on write. Sub-millisecond on the open hot-path.
- **Seed** `sms_fallback_enabled` (recommended default **false** — explicit opt-in; or **true** to preserve
  current behaviour — business decision).
- **Admin API**: `GET /admin/v1/settings`, `PATCH /admin/v1/settings` (super_admin; audited). One row per key.
- **Admin UI**: a Settings page with a toggle (lands when the Admin settings module is built — `GO_LIVE`/M3).

> Alternative (faster, weaker): a config value `domain.device_comm.sms_fallback_enabled` from `.env`. Rejected
> as the primary design because the requirement says **admin setting** (runtime-toggleable without a deploy).

### 3.2 The gate (single decision point)
In `CommandDispatcher.dispatch()`, **before** the fallback attempt, add one guard:

```
if (result failed AND isTransient(reason)
        AND Settings::bool('sms_fallback_enabled')          ← NEW global gate
        AND fallbackType !== null AND fallbackType !== primaryType):
    attempt #2 via fallback driver
```

- The global flag is **ANDed** with the existing per-model `fallback_open_driver` capability:
  fallback fires only when **global=enabled AND model has a fallback driver**. This keeps per-model control
  (a model with no SMS capability never falls back) under a global master switch.
- Everything else (transient classification, one-shot, attempt recording) is unchanged.

### 3.3 SMS-never-primary guard (defensive)
- The primary is `device.driver_type`. Add an **admin/validation guard** so a device's `driver_type` cannot
  be set to `sms` (allowed values for primary: `traccar`, `ble`-reserved). SMS remains selectable only as a
  model's `fallback_open_driver`. (Today nothing enforces this at the API layer — recommend the `driver_type`
  request rule excludes `sms`, or the dispatcher rejects `sms` as a primary type.)

### 3.4 Observability
- The fallback attempt is already recorded as `open_command_attempts` (attempt_no=2, driver=sms). Add a
  metric/audit event "sms_fallback_used" for monitoring volume + cost (ties into `GO_LIVE` H2/M1).

## 4. Edge cases

| Case | Behaviour |
|---|---|
| Flag disabled, Traccar offline | command fails `device_offline` (no SMS) — correct |
| Flag enabled, model has no `fallback_open_driver` | no fallback (per-model AND-gate) |
| Flag enabled, `SMS_PROVIDER=fake` | fallback "succeeds" against fake — must wire a real SMS provider (C1) before relying on it |
| Flag toggled mid-flight | next command picks up the new value (cache TTL ≤ a few seconds) |
| Device `driver_type=sms` (misconfig) | rejected by the new primary-guard (§3.3) |

## 5. Effort (future change — not implemented here)

| Item | Effort |
|---|---|
| `app_settings` table + `SettingsService` (cached) | 3–4 h |
| Admin `GET/PATCH /admin/v1/settings` + policy + audit | 3–4 h |
| `CommandDispatcher` global gate + primary-guard + tests | 2–3 h |
| Admin UI settings toggle (with M3) | 2–3 h |
| **Total** | **~10–14 h** (plus a real SMS provider, C1, for it to do anything) |

**Note:** this also delivers a reusable global-settings/feature-flag foundation, useful for other runtime
toggles (e.g. maintenance mode, payment kill-switch).
