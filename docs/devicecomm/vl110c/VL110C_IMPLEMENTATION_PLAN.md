# VL110C — Implementation Plan (proposal, NOT executed)

> This is the plan we would follow **after** approval. **Nothing here is
> implemented in this task** — no code, no migrations, no endpoint changes, no
> deploy. Sequenced so each phase is independently verifiable and reversible.

## Phase 0 — Prove the transport (no code)
**Goal:** confirm Option A (via Traccar) is viable before touching the codebase.
1. Expose the Traccar **GT06 decoder port** in the Traccar compose (test env).
2. SMS the test unit `SERVER,1,<traccar-host>,<gt06-port>,0#`; confirm it logs in to
   Traccar (Test Plan Steps 1–4).
3. From Traccar, send `type:custom data:RELAY3,ON,1,1000#`; observe the `0x80` send,
   the `0x21` response, and whether the relay actuates + whether Traccar surfaces a
   confirmation signal.
- **Exit gate:** device logs in AND the RELAY command actuates via Traccar.
  - ✅ → proceed with Option A (below).
  - ❌ → escalate to Option B decision (bespoke GT06 listener; separate plan).

## Phase 1 — Catalog + config (small, additive)
1. **Schema:** add `open_command_text` (and `close_command_text`, `open_style`
   enum `pulse|latch`) to `device_models` (nullable; NULL = use global default).
   *Only schema change in the whole plan; backward-compatible.*
2. **Seed:** `device_models` row — `vendor='Jimi'`, `code='VL110C'`,
   `default_driver_type='traccar'`, `fallback_open_driver='sms'`,
   `open_command_text='RELAY3,ON,1,1000#'` (or `RELAY,1#` if latching),
   `close_command_text='RELAY,0#'` (latch only), `whitelist_capacity=3`.
3. **Config:** keep `config/domain/device_comm.php.open_command` as the global
   default; add per-model override resolution.
- **Tests:** migration + seed unit test; model factory.

## Phase 2 — Driver wiring (reuse Traccar, per-model text)
1. `TraccarDriver.open()` / `CommandDispatcher`: resolve the command text from the
   device's `device_model.open_command_text` (fallback to global). No new driver
   class; `DriverType::Traccar` reused.
2. Keep `confirmsActuation()` semantics; set the VL110C actuation-confirm source
   per Phase 0 findings (0x21 text / output bit / heartbeat fuel-cut bit).
3. SMS fallback (`SmsDriver`) uses the same per-model text.
- **Tests:** unit — dispatch sends `RELAY…#` for a VL110C device, `OUTPUT0=1` for a
  UMKa device; fallback path; state transitions (`dispatched`/`opened`/`failed`).

## Phase 3 — Traccar telemetry + actuation confirmation
1. Confirm `TraccarIngestionService` maps the VL110C online/last-seen + the relay/
   output signal into `last_online_at` + actuation confirmation within
   `TRACCAR_ACTUATION_WINDOW`.
2. If Traccar does not surface relay confirmation for GT06, either (a) accept
   `dispatched` as success, or (b) add a heartbeat-bit reader that flips
   `dispatched → opened` when the next `0x13` shows the relay state.
- **Tests:** webhook ingestion for a GT06 position/status payload → device online +
  (if available) actuation confirm.

## Phase 4 — Onboarding + ops
1. Register the fleet via `devices:reconcile-traccar <imei> --model=<vl110c-id>
   --sim-phone=… --driver=traccar` (or a small bulk-onboard admin flow).
2. Provisioning runbook: `SERVER,1,…#`, optional `SOSPERMIT,1#` + ops SOS number,
   `HBT` tuning.
3. Traccar GT06 port exposed in **production** compose + firewall/carrier reachability.
- **Tests:** reconcile CLI idempotency; end-to-end open on the test unit.

## Phase 5 — Admin/app polish (optional)
1. admin-ui: show relay-state / `open_style` for GT06 devices; command history
   already works via `AdminDeviceCommController`.
2. Flutter app already opens via `POST /v1/devices/{id}/open` (Phase 3 of the app) —
   **no app change needed**; VL110C is just another device with `can_open`.

## Phase 6 — Verify + roll out
1. `flutter analyze`/tests unaffected (no mobile change). Backend: `php artisan test`
   green for the new driver-text logic + ingestion.
2. Bench relay test → live barrier test on the IMEI-863767070453873 unit
   (Test Plan Steps 7–10).
3. Roll out per region behind the existing device/subscription gating.

---

## What is explicitly NOT in scope (guardrails)
- No new TCP listener / socket server (unless Phase 0 fails → separate Option B plan).
- No change to the open API, `OpenCommand` state machine, dispatch job, driver
  interface, whitelist model, admin endpoints, or CLI signatures.
- No new dependency, no deploy in this task.

## Effort estimate (contingent on Phase 0)
- **Option A (Traccar supports it):** ~1 migration + small driver-text change +
  seed + Traccar port + onboarding runbook → **days**, low risk, fully within the
  existing architecture.
- **Option B (bespoke GT06 listener):** new always-on process + parser + `JimiDriver`
  + supervision + HA → **weeks**, new operational surface, higher risk. Avoid unless
  forced.

## Decision record needed before Phase 1
1. Traccar GT06 send/decode confirmed on the test unit? (Phase 0 exit gate)
2. Barrier wiring = **pulse** (`RELAY3`) or **latch** (`RELAY`)? → command text.
3. Actuation-confirm signal available via Traccar? → `dispatched` vs `opened` policy.
