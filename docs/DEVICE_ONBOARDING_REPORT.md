# Device Onboarding Report — UMKa 310 v2L (IMEI 868184062169571)

**Date:** 2026-06-26
**Scope:** real-hardware **communication validation only** — verify whether the device is online on our
production Traccar and determine the best communication channel. **No features, no DB/code changes, no
device commands, no OUTPUT0/cmdout.p/barrier action.**

---

## 1. Device facts (provided + documented)

| | |
|---|---|
| Model | **GLONASSSoft UMKa 310 v2L** (GPS/GLONASS telematics tracker) |
| IMEI | `868184062169571` |
| SIM | installed, on mobile network; phone number known to owner |
| Telematics protocols | Wialon IPS v1.1/v2.0, **Wialon Combine v1.04**, EGTS (`SETPROTOCOL` #30) |
| Discrete outputs | **1** (OUT0 / open-drain) — drives the relay; command `OUTPUT0=1/0` (cmd #25, fw 0.12.8+) |
| Command channels | GPRS telematics (Wialon), GLONASSSoft remote-config server, **SMS** (`AUTH`+cmd), BLE (iBeacon identification — not a command channel) |
| Source | `docs/UMKA_COMMAND_REFERENCE.md` (manual extract) |

## 2. What was checked (read-only)

Connected to production Traccar (`6.14.5`) via the REST API + container logs + host/container sockets:
- Device registration + device list (Traccar API).
- Traccar logs (stdout + `/opt/traccar/logs/*`) for the IMEI and Wialon activity.
- Listening + established TCP on the Wialon port (host + container).
- Traccar listener/forward configuration.

## 3. Result

**The device is NOT connected to our Traccar** (not registered, no connection attempts, no session on 5011).
Full evidence + the exact reason + the Configurator settings are in **`UMKA_CONNECTION_STATUS.md`**.

- Our infrastructure is **ready**: Wialon listener on **5011**, host port open (UFW), `gps.salamheyetimiz.com`
  → `185.208.206.174` reachable, position-forward webhook configured.
- The gap is **device-side configuration**: the UMKa has not been pointed at our server (`SETSERV` +
  `SETPROTOCOL` + APN). It is likely still on its factory/GLONASSSoft default server.

## 4. Onboarding state

| Step | State |
|---|---|
| Traccar server + Wialon listener ready | ✅ done |
| Public device endpoint (`gps.salamheyetimiz.com:5011`) reachable | ✅ done |
| Device configured to our server (Configurator/SMS) | ❌ **pending (owner action)** |
| Device opens Wialon session to Traccar | ❌ pending (follows config) |
| Device registered in Traccar (`uniqueId = IMEI`) | ❌ pending (after first session) |
| Telemetry/online/I-O/OUTPUT0 status visible in Traccar | ❌ pending |
| Command channel (OUTPUT0 via Traccar) proven — **HB1** | ❌ **separate later test** (out of scope here; destructive) |

> Note: registering the device in Traccar was **intentionally not done** now — it would not make the device
> connect (the device must be configured first), and the task constraints say no DB changes. Registration is
> the first step **after** the owner configures the device (see test plan).

## 5. Best communication channel — determination

**Cannot be empirically determined yet** because the device has not connected. Based on the hardware and the
v1.2 architecture:

- **Recommended primary: Wialon IPS → Traccar** (telematics session on `gps.salamheyetimiz.com:5011`). This
  is the intended channel and what our infrastructure is built for. **To be confirmed** the moment the device
  connects (this report's prerequisite).
- **Fallback: SMS** (`AUTH` + command to the device SIM) — independent of data/Traccar; emergency only.
- **Not a channel: BLE** (iBeacon identification only — see `BLE_FEASIBILITY_REPORT.md`).

**Important scope boundary:** this validation concerns **inbound telemetry / communication** (does the device
reach Traccar and report status, incl. the OUT0 bit). It does **not** test the **outbound open command**
(`OUTPUT0`) — that is **HB1**, a separate, deliberately-destructive test that opens the relay and requires
explicit approval. It is **not** part of this task.

## 6. Immediate next action (owner)

Configure the UMKa via the **Configurator** (USB) — Server `gps.salamheyetimiz.com`, Port `5011`, Protocol
**Wialon IPS v2.0**, and ensure mobile-data/APN is set — then notify, so we can register it in Traccar and
verify the live session. Step-by-step in **`NEXT_HARDWARE_TEST_PLAN.md`**.
