# VL110C — Online Status Flow

> How "is the device online?" is produced end-to-end, based on the **audited**
> code paths. Online only — no relay/command logic here.

## 1. The pipeline (device → admin panel)

```
VL110C ── TCP(GT06) ──▶ Traccar (:5023, gt06 decoder)
   │  login 0x01 / heartbeat 0x13 / location 0x22        │ Traccar: device.status=online,
   │  (Traccar ACKs login+heartbeat automatically)       │ device.lastUpdate=now, builds a Position
   │                                                      │
   │                                    forward.enable=true, forward.url=
   │                                    http://host.docker.internal/v1/traccar/forward?token=…
   │                                                      ▼
   │                              POST /v1/traccar/forward   (TraccarForwardController)
   │                                    token check (hash_equals) → 200
   │                                    TraccarIngestionService.ingest(payload)
   │                                      • match device by device.uniqueId (IMEI) via traccar_devices
   │                                      • online = device.status !== 'offline'
   │                                      • DeviceDiagnostic row (raw attributes, signal, battery)
   │                                      • if online → devices.last_online_at = position.deviceTime
   ▼                                                      ▼
Admin panel  ── online iff (now − last_online_at) ≤ offline_threshold_minutes (=15)
```

## 2. Component-by-component (audited)

| Stage | Component | Behaviour |
|---|---|---|
| Device TCP | Traccar `gt06` decoder (container `:5023`) | terminates TCP, ACKs login/heartbeat, decodes packets, marks device online, creates Position |
| Push | Traccar `forward.url` | POSTs each **position** as JSON to the backend webhook (positions only; no event forward configured) |
| Webhook | `TraccarForwardController` | token-gated (`token` query / `X-Traccar-Token`), constant-time compare, always 200 |
| Ingest | `TraccarIngestionService::ingest()` | matches `device.uniqueId` → `traccar_devices` → `devices`; unknown = ignored |
| Diagnostic | `recordDiagnostic()` | writes `device_diagnostics` (partition `Ym`): `online`, `signal_strength` (sat/rssi/signal), `battery_level`, `firmware`, **`raw` = attributes JSON**, `reported_at` |
| Status | `updateDeviceStatus()` | **only if online**: `devices.last_online_at = position.deviceTime\|fixTime`, `last_signal_strength` |
| Display | `offline_threshold_minutes = 15` | admin online = `last_online_at` within 15 min |

## 3. The single dependency to be aware of

**`last_online_at` advances only when Traccar forwards a *position*.** The forward
is positions-only (no `event.forward.url`). Therefore:

- If the VL110C emits periodic **location/LBS** packets (normal GT06 behaviour, even
  without a GPS fix), Traccar makes positions, forwards them, and `last_online_at`
  tracks connectivity correctly. ✅ expected path.
- If a login/heartbeat produces **no position**, Traccar still shows the device
  online (via `device.lastUpdate`), but our `last_online_at` would **not** advance —
  the admin could read "offline" while the device is actually connected. ⚠️

**Mitigation options (decision at approval):**
1. **Rely on positions** — verify on the test unit that heartbeat/LBS yields
   forwarded positions at least every ~5–15 min. Simplest; likely sufficient.
2. **Scheduled Traccar-status pull (recommended hardening):** a small periodic job
   that calls `GET /api/devices?uniqueId=…` for our mapped devices and sets
   `last_online_at` from Traccar's `lastUpdate` when `status != offline`. This makes
   online detection independent of positions. Read-only, no schema change, reuses
   `HttpTraccarClient`.
3. **Enable `event.forward.url`** in `traccar.xml` so status/heartbeat events also
   forward (then extend ingestion to accept event-shaped payloads).

Option 2 is the most robust for a **stationary barrier** device (which may rarely
produce meaningful positions).

## 4. Online vs offline — exact rule

```
online  ⟺  last_online_at != null  AND  now − last_online_at ≤ 15 minutes
offline ⟺  otherwise
unknown ⟺  last_online_at == null (never reported)
```
(The 15-minute window = `config('domain.devices.offline_threshold_minutes')`,
chosen as ~3× a 5-min report cadence to avoid flapping.)

## 5. What the admin "Raw Traccar Data" + "Diagnostics" sections will source

| Field the user asked for | Source |
|---|---|
| IMEI / UniqueId | `traccar_devices.unique_id` + Traccar `GET /api/devices` |
| Protocol | latest Traccar position `protocol` (`gt06`) |
| Last Position | latest Traccar position `fixTime` + lat/lon |
| Last Update | Traccar `device.lastUpdate` and our `devices.last_online_at` |
| Attributes / Network / Battery / Motion | Traccar position `attributes` + our `device_diagnostics.raw` |
| Full raw JSON | Traccar device+position JSON (pull) + `device_diagnostics.raw` |
| Heartbeat arriving? / last heartbeat / last packet | `last_online_at` freshness + Traccar `lastUpdate` |
| **IP / Port** | **not in Traccar REST** — Traccar logs / session only (show `—` + note) |

No new tables are required; `device_diagnostics` already stores `raw`. The only new
code is a **read-only admin endpoint** that pulls the live Traccar device/position
JSON and returns it alongside the stored diagnostic — and (optionally) the
status-pull job from §3.2.
