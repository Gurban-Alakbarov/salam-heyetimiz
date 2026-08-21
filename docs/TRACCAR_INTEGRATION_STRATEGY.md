# Traccar Integration Strategy (Phase-0)

**Date:** 2026-06-14
**Scope:** how the Salam backend uses Traccar to (a) deliver the open command to a UMKa 310, (b) read
telemetry / online status, and (c) confirm actuation — and the concrete `TraccarDriver` / `SmsDriver` /
`BleProvisioningDriver` strategy. Grounded in the Traccar docs/forums and the UMKa manual extract
(`UMKA_COMMAND_REFERENCE.md`).

---

## 1. What Traccar gives us (✅ confirmed from Traccar docs/forums)

- Open-source Java telematics server; speaks **Wialon IPS** and **Wialon Combine** natively (the
  protocols the UMKa uses).
- **Commands:** every protocol supports at least the **`custom`** command; Wialon is **text-based**, so a
  custom command is a plain text string (no hex). Commands sent while a device is **offline are queued**
  and delivered when it reconnects.
- **REST API** to register devices, send commands (`POST /api/commands/send`), read positions/events.
- **Event/position forwarding** ("Forward" — `forward.url`, JSON) to push telemetry to our backend
  webhook; alternatively poll the REST API.
- Devices connect **outbound** to Traccar (server host/port + protocol set on the UMKa via `SETSERV` /
  `SETPROTOCOL` / configurator). For a mains-powered stationary barrier, the unit can hold a **permanent
  session**, giving low command latency.

Sources: [Traccar Commands](https://www.traccar.org/commands/), [custom command format](https://www.traccar.org/forums/topic/whats-the-correct-format-for-custom-commands/), [sending commands](https://www.traccar.org/forums/topic/sending-commands-to-device-howto-generate-command/), [Wialon attributes](https://www.traccar.org/forums/topic/sending-special-attributes-via-wialon-protocol/).

## 2. The open command path (design + the gating unknown)

```
backend → POST /api/commands/send  { deviceId, type:"custom", attributes:{ data:"OUTPUT0=1" } }
        → Traccar → Wialon downlink over the live session → UMKa executes → OUT0 → relay pulse
```

- **Preferred:** a single command that triggers the owner's `cmdout.p` (atomic 1-second pulse). If
  `cmdout.p` is invoked by `OUTPUT0=1`, one `custom` command suffices. Otherwise send `OUTPUT0=1` and a
  delayed `OUTPUT0=0` (~1 s) — Traccar can send two commands, but on-device `cmdout.p` is cleaner.
- ⚠️ **GATING UNKNOWN (B1):** does the UMKa execute `OUTPUT0=1` when delivered via **Traccar's** Wialon
  command framing? GLONASSSoft units accept "command to unit" over Wialon in Wialon hosting; the test is
  whether Traccar's encoder produces the same accepted framing. **This single test decides whether Traccar
  is the command channel or only the telemetry channel.**

### Decision tree (set by the Phase-0 test)

| Outcome | Command channel | Action |
|---|---|---|
| ✅ Traccar/Wialon `custom` triggers the pulse | **Traccar** (primary) | Build `TraccarDriver.open()` = REST `custom` command; SMS = fallback. |
| ❌ Wialon downlink not honoured, but GLONASSSoft remote-config server works | GLONASSSoft remote-config API | Use Traccar for telemetry only; add a `GlonassConfigClient` command channel. (More work; isolate behind the `DeviceDriver` seam.) |
| ❌ Neither online channel works reliably | **SMS** | `SmsDriver` becomes primary (degraded latency); revisit hardware/firmware. |

## 3. Telemetry, online status, confirmation

- **Online/offline + I/O state** arrive as Traccar positions/events → forwarded to the backend webhook →
  update `device_diagnostics` and device online status (DB Arch §6.2).
- **Actuation confirmation (`opened`):** observe the OUT0 bit flip in the device's next telemetry record
  (configure `STATMASK` to force an immediate record on output change). ⚠️ CONFIRM the latency of that
  confirming record; until confirmed, treat Traccar opens as `dispatched` (R-GSM-03 still honoured).

## 4. `TraccarDriver` strategy (09-B)

- Implements `DeviceDriver` (`open`, `whitelistAdd/Remove`, `diagnose`, `supports`); bound as
  `device-driver.traccar`.
- `open(device, command)` → `TraccarClient` REST `custom` command (`OUTPUT0=1` / `cmdout.p` trigger) to the
  device's Traccar id (resolved via a **device-mapping** table: our `device_id` ↔ Traccar `id`/`uniqueId`
  = IMEI). Returns `dispatched`; `opened` is set later by the telemetry-confirmation ingest.
- `whitelistAdd/Remove` → for UMKa these are **provisioning/identification** changes (e.g. BLE iBeacon
  UUID via `BLEID`/identification config, or roster authorisation server-side) rather than caller-ID
  numbers; `WhitelistSyncJob` drains the outbox through this.
- `diagnose` → Traccar device status / last position.
- `supports('actuation_confirmation')` → true iff §3 confirmation is validated.
- `TraccarClient` (integration adapter) — REST (command send, device CRUD) + an **event-forward webhook
  receiver** (`config/integrations/traccar.php`: base URL, API token, command template, forward secret).

## 5. `SmsDriver` strategy (09-B, fallback)

- Bound as `device-driver.sms`; used when the device is offline from Traccar (transient → `CommandDispatcher`
  fallback per R-GSM-04).
- `open()` → SMS provider sends `OUTPUT0=1` to the device SIM (sender pre-authorised on the device via
  `AUTH <password>` during provisioning — UMKa §3.22). Inbound SMS reply HMAC-correlated to the command
  (R-GSM-10, SMS path only).
- Latency seconds→tens of seconds; per-message cost. Last resort.

## 6. `BleProvisioningDriver` strategy (09-B) — see `BLE_FEASIBILITY_REPORT.md`

- BLE on the UMKa is **iBeacon identification**, not a phone command channel. A `BleProvisioningDriver`
  would, at most, manage the device's recognised iBeacon UUID set (`whitelistAdd/Remove` ⇒ BLE
  identification config) — **not** a real-time `open()`. Given the security/OS limits (BLE report), BLE is
  **not recommended as the primary local open**; treat as an optional, foreground-only convenience or defer.

## 7. Net recommendation

Make **Traccar the primary transport for both remote and in-person opens** (pending the §2 B1 test),
**SMS the fallback**, and **demote BLE** from "primary local" to optional/deferred. This inverts the
earlier BLE-first assumption — justified by the hardware reality (next report).
