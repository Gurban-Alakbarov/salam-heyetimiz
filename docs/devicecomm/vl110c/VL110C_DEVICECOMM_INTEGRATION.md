# VL110C — DeviceComm Integration Plan (mapping, not code)

> How the VL110C fits the **existing** DeviceComm architecture without breaking it.
> Research/plan only — no code, no migrations, no endpoint changes, no deploy.

## 1. Existing architecture (as audited — the pieces we must respect)

- **Driver seam:** `app/Domain/DeviceComm/Contracts/DeviceDriver.php` —
  `open()`, `whitelistAdd/Remove()`, `diagnose()`, `supports()`.
- **Driver types:** `app/Domain/Devices/Enums/DriverType.php` — `traccar | sms |
  ble`, each with `confirmsActuation()`.
- **Drivers:** `TraccarDriver` (→ `TraccarClient.sendCommand(traccarId, text)`,
  text default `OUTPUT0=1`), `SmsDriver` (SMS `OUTPUT0=1`). Bound in
  `ModuleServiceProvider` as `device-driver.traccar` / `device-driver.sms`.
- **Resolution:** `DriverResolver` reads `devices.driver_type` → container binding.
- **Open pipeline:** `POST /v1/devices/{id}/open` → `OpenDevice` action (idempotency,
  `DeviceUser` authz, `CooldownGuard`) → `OpenCommand(queued)` → `DispatchOpenCommandJob`
  (`open` queue, Horizon) → `CommandDispatcher.dispatch()` (primary driver →
  transient fallback → `markDispatched/markOpened/markFailed`) → `OpenCommandCompleted`.
- **Inbound device traffic: NO backend TCP listener.** Device TCP terminates at the
  **Traccar** Docker service; Traccar → backend via webhook `POST /v1/traccar/forward`
  (`TraccarIngestionService` updates `last_online_at`, actuation within
  `TRACCAR_ACTUATION_WINDOW=30 s`). Backend → Traccar via HTTP (`HttpTraccarClient`:
  `registerDevice`, `sendCommand`, `findDeviceByUniqueId`); `traccar_devices` maps
  `device_id ↔ traccar_id/unique_id(IMEI)`.
- **Device registry:** `devices` (`driver_type`, `serial`, `sim_phone`, `status`,
  `device_model_id`, `last_online_at`, …) + `device_models` (`vendor`, `code`,
  `default_driver_type`, `fallback_open_driver`, `whitelist_capacity`).
- **Whitelist:** server-side roster (`device_users`) + `whitelist_changes` outbox;
  Traccar/SMS whitelist ops are no-ops (Traccar server-side authz).
- **Admin:** `AdminDeviceCommController` (commands list, whitelist-queue,
  diagnostics, whitelist/resync) + `admin-ui` React SPA.
- **CLI:** `php artisan devices:reconcile-traccar <imei> --serial --model
  --sim-phone [--driver] [--firmware] [--label]`.

## 2. The central finding — VL110C is GT06, Traccar decodes GT06

The VL110C uses the **GT06-family protocol**. Traccar ships a **`gt06` protocol
decoder** (widely used for Jimi/Concox). Therefore, exactly like the current UMKa
(Wialon) device, the VL110C can connect **to Traccar**, and the backend keeps
talking only to Traccar's HTTP API + webhook. **No new TCP listener, no byte parser
in our codebase.** This preserves the architecture 1:1.

The **only functional difference** from the UMKa device is the **open-command
text**:

| | UMKa 310 (today) | VL110C (new) |
|---|---|---|
| Traccar protocol | Wialon (`:5011`) | **GT06** (its own port, e.g. `:5023`) |
| Open command text | `OUTPUT0=1` | **`RELAY3,ON,1,1000#`** (pulse) or `RELAY,1#`/`RELAY,0#` (latching) |
| Driver | `traccar` | **`traccar`** (reused) |

## 3. Recommended integration shape (Option A — via Traccar)

**Reuse `DriverType::Traccar` + `TraccarDriver`.** Do **not** add a `jimi` driver or
a listener. The work is configuration + one small extensibility gap:

1. **Per-model open-command text.** Today `config/domain/device_comm.php.open_command`
   is a single global (`OUTPUT0=1`). VL110C needs a different string. Cleanest
   additive change (Phase 2, not now): add an `open_command_text` (and optional
   `close_command_text`) to **`device_models`**, and have `TraccarDriver`/`CommandDispatcher`
   read the per-model text (falling back to the global default). This keeps one
   driver serving multiple protocols. *This is the single code change the driver
   layer needs.*
2. **New `device_models` row** for `vendor='Jimi', code='VL110C'`,
   `default_driver_type='traccar'`, `fallback_open_driver='sms'`,
   `open_command_text='RELAY3,ON,1,1000#'` (or `RELAY,1#`), `whitelist_capacity=3`
   (SOS limit).
3. **Traccar port for GT06.** Enable/expose the GT06 decoder port in the Traccar
   Docker compose (`phaseE_traccar.sh`) — e.g. `:5023:5023` — analogous to the
   existing Wialon `:5011` mapping.
4. **Device registration** via the existing CLI:
   `php artisan devices:reconcile-traccar 863767070453873 --serial=<serial>
   --model=<vl110c-model-id> --sim-phone=+994517371021 --driver=traccar`.
5. **Onboard the physical device** by SMS `SERVER,1,<traccar-host>,5023,0#` so it
   connects to Traccar's GT06 port.
6. **Actuation confirmation** — verify what Traccar's GT06 decoder surfaces (0x21
   response text / output bit / fuel-cut heartbeat bit) so `dispatched → opened`
   works; if nothing is surfaced, treat a successful send as `dispatched` and
   optionally confirm via the next heartbeat's relay bit.

### Option B (only if Traccar can't drive the VL110C relay)
If the deployed Traccar's GT06 encoder cannot send the `RELAY…#` online command (or
cannot decode VL110C login), the fallback is a **bespoke TCP listener** — a new
long-running process (ReactPHP/Workerman/Swoole or an artisan daemon under
Supervisor) that terminates GT06 TCP, ACKs login/heartbeat, parses `0x21`, and a
new `DriverType::Jimi` + `JimiDriver` that writes to that listener. **This is a
major architectural addition (new always-on process, supervision, scaling) and
should be avoided unless Option A is proven impossible.** All the byte-level detail
needed to build it is in the packet reference.

## 4. Mapping table (VL110C concept → existing component)

| VL110C concept | Existing DeviceComm component | Action |
|---|---|---|
| Device family | `device_models` row | add `Jimi/VL110C` |
| Transport | `DriverType::Traccar` + `TraccarDriver` | reuse |
| TCP termination | Traccar `gt06` decoder | enable port |
| "Open barrier" | `POST /v1/devices/{id}/open` → `OpenCommand` | reuse unchanged |
| Relay command text | `TraccarClient.sendCommand(text)` | per-model text (RELAY…#) |
| Command lifecycle | `OpenCommandState` machine | reuse unchanged |
| Async dispatch | `DispatchOpenCommandJob` + Horizon `open` queue | reuse |
| Telemetry / online | Traccar webhook → `TraccarIngestionService` → `last_online_at` | reuse |
| Actuation confirm | `TRACCAR_ACTUATION_WINDOW` + output/relay signal | verify signal |
| Access control | roster `device_users` + `CooldownGuard` | reuse |
| Device SMS whitelist (SOS/SOSPERMIT/PWDSW) | provisioning hardening | set once; `whitelist_changes` no-op like Traccar |
| SMS fallback | `SmsDriver` | text = `RELAY…#` per-model |
| Audit | `AuditLog` + `OpenCommandIssued` | reuse |
| Admin visibility | `AdminDeviceCommController` + admin-ui | reuse |
| CLI onboarding | `devices:reconcile-traccar` | reuse |

## 5. Whitelist mapping

- **Our authorization** for a relay open stays server-side: `device_users`
  (roster) + subscription + cooldown, enforced in `OpenDevice`. Unchanged.
- **The device's SOS/SOSPERMIT/PWDSW** are an **SMS-channel hardening** step, not our
  access model. During provisioning we would (optionally) `SOSPERMIT,1#` and add
  only our ops SIM as SOS, so no random SMS can open the barrier. Because relay
  opens go over the **platform (TCP) channel**, they are unaffected by this.
- `whitelist_changes`/`WhitelistSyncJob` remain **no-ops** for this driver (as they
  are for Traccar today) unless we later choose to push SOS numbers to the device
  automatically.

## 6. What changes, what does NOT (scope guard)

**Does NOT change:** the open API/endpoints, `OpenCommand` model + state machine,
the dispatch job/queue, the driver interface, the Traccar webhook/ingestion, the
admin endpoints, the CLI, the whitelist model. **No migration is proposed in this
task.**

**Would change in a future implementation phase (planned, not done here):**
1. `device_models`: add `open_command_text`/`close_command_text` columns (+ seed
   the VL110C row).  ← the only schema touch.
2. `TraccarDriver`/`CommandDispatcher`: read per-model command text (fallback to
   global default) — small, backward-compatible.
3. Traccar compose: expose the GT06 port.
4. (Optional) admin-ui: show `RELAY`/relay-state fields for GT06 devices.

## 7. Risks / open questions (ranked)

1. **[Blocker to confirm] Traccar GT06 command support** — does the deployed Traccar
   6.14.5 GT06 encoder send `RELAY…#` as a `0x80` online command, and decode VL110C
   login? (Likely yes; must verify on the test device.) If no → Option B.
2. **Actuation confirmation signal** — what does Traccar expose after a relay
   command so we can reach `opened` (vs `dispatched`)? (0x21 text? output bit?
   heartbeat fuel-cut bit?)
3. **Pulse vs latch** — is the barrier wired for a momentary pulse (`RELAY3`) or a
   latching cut/restore (`RELAY`)? Determines the command text + success rule.
4. **GT06 port + firewall** — expose the correct Traccar port; the SIM's carrier
   must allow inbound to that host:port.
5. **Two device families on one Traccar** — Wialon (UMKa) + GT06 (VL110C) run as
   separate decoders on separate ports in the same Traccar; ensure both ports are
   mapped and `traccar_devices` unique-id (IMEI) routing is unambiguous.
