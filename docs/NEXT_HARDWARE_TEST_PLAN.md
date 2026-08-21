# Next Hardware Test Plan — UMKa 310 v2L (IMEI 868184062169571)

**Date:** 2026-06-26
**Goal:** get the device communicating with Traccar and verify inbound telemetry (incl. OUT0 status), then —
as a **separate, approval-gated step** — prove the outbound open command (HB1). Stages 1–4 are
**non-destructive** (read-only / config-only). Stage 5 is **destructive** and is **not** to be run without
explicit approval.

---

## Stage 1 — Configure the device (owner)  · non-destructive

Using the **UMKa Configurator** (USB + GLONASSSoft desktop tool), set:

| Field | Value |
|---|---|
| Server host | `gps.salamheyetimiz.com` (or `185.208.206.174`) |
| Server port | `5011` |
| Protocol | **Wialon IPS v2.0** (`SETPROTOCOL` #30 = `1`); Combine v1.04 = `2` also acceptable |
| Mobile data / APN | the SIM operator's APN (confirm with Azercell/Bakcell/Nar) |

SMS alternative: `AUTH <password>` (default `0`) then `SETSERV`/`SETPROTOCOL` — **use the exact syntax from the
UMKa manual Appendix A** (do not guess the argument format).

**Exit criteria:** device powered, on data, configured; owner confirms.

## Stage 2 — Verify the device reaches Traccar  · non-destructive (read-only)

On the server (we run, read-only):
- Watch the Traccar log for a `wialon` connection from the IMEI:
  `docker logs -f traccar` / `grep 868184062169571 /opt/traccar/logs/*`
- Confirm an established TCP session on `:5011`.

**Exit criteria:** a log line shows the **`wialon`** protocol tag + a session from IMEI `868184062169571`
(this also **definitively confirms** 5011 = Wialon decoder). If nothing appears → re-check Stage 1
(server/port/protocol/APN) per `UMKA_CONNECTION_STATUS.md` causes.

## Stage 3 — Register the device in Traccar  · minimal config (one-time onboarding)

- `POST /api/devices { name: "UMKa-868…571", uniqueId: "868184062169571" }` (via the Traccar API / UI), so
  Traccar stores the device's telemetry. (This is operational onboarding, not an app-DB/feature change.)
- Backend mapping (`traccar_devices`) can be created later when the device is provisioned in the app.

**Exit criteria:** device appears in Traccar with `uniqueId = IMEI`.

## Stage 4 — Verify inbound telemetry  · non-destructive (read-only) — THE communication validation

Read from Traccar (no commands sent):
- **Online/last-update:** `GET /api/devices` → `status`, `lastUpdate`.
- **Protocol:** `GET /api/positions?deviceId=…` / device `protocol`.
- **Position:** latest position (lat/lon/time) — note a stationary barrier may need a GPS fix.
- **Attributes / I-O:** position `attributes` — look for input/ignition/**output** bits (e.g. `out`, `output`,
  `io`/`din`/`dout` keys; the exact key name is device/firmware-specific).
- **OUTPUT0 visibility:** confirm whether the current OUT0 state is present in the telemetry attributes.
  If not surfaced, set **`STATMASK`** (#26) on the device to include the output bit so its change forces an
  immediate record.

**Exit criteria:** Traccar shows the device **online**, with position + attributes, and we can **read** the
OUT0/output status (or determine it is not exposed and configure `STATMASK`). **This completes the
communication validation** and confirms the **Wialon → Traccar** channel as primary.

> If telemetry is healthy but OUT0 is never surfaced even with `STATMASK`, record that as a finding — it
> affects actuation read-back (`dispatched → opened`) and may require the SMS/`OUTPUT0` query path instead.

---

## Stage 5 — HB1: outbound open command  · ⛔ DESTRUCTIVE — APPROVAL REQUIRED — NOT NOW

> **This stage triggers OUTPUT0 / the relay. It is explicitly out of scope for the current task and must
> not be run without separate, explicit approval and physical safety precautions.**

When approved, and **with the relay physically disconnected from the real barrier** (or the barrier safe to
actuate):
1. Send `OUTPUT0=1` via Traccar `custom` command (`POST /api/commands/send {deviceId, type:"custom",
   attributes:{data:"OUTPUT0=1"}}`) and observe whether the relay actuates → confirms Traccar's Wialon framing
   reaches the device command parser (the core HB1 question).
2. Observe the telemetry OUT0 bit flip (actuation read-back latency → `opened`).
3. Test the `cmdout.p` 1-second pulse trigger (or paired `OUTPUT0=1`/`OUTPUT0=0`).
4. If Traccar's downlink does **not** actuate OUTPUT0 → fall back to: GLONASSSoft remote-config command channel,
   or SMS-primary (`AUTH`+`OUTPUT0=1`). The `DeviceDriver` seam already supports SMS.

**Decision recorded by HB1:** whether the transport stays **Traccar-primary** or pivots — this gate must pass
**before** investing in the mobile open-gate UX (see `GO_LIVE_BLOCKERS.md` C3).

---

## Summary

| Stage | Type | Status |
|---|---|---|
| 1 Configure device | config (owner) | ⏳ pending |
| 2 Verify reaches Traccar | read-only | ⏳ |
| 3 Register in Traccar | onboarding | ⏳ |
| 4 Verify telemetry + OUT0 read | **read-only — this task's goal** | ⏳ |
| 5 HB1 open command | ⛔ destructive | **gated — separate approval** |

**Right now:** the only blocker is **Stage 1 (owner configures the device)**. Everything on our side is ready.
