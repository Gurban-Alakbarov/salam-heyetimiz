# UMKA 310 v2L — Command Reference (Phase-0 extract)

**Date:** 2026-06-14
**Source:** UMKa 310 English operation manual (`rukovodstvoumkaen310.pdf`, doc `022.000.000`, GLONASSSoft),
extracted and read in full (4113 lines of text). Command numbers below are from **Appendix A — Table of
supported SMS commands** (the same set is accepted over GPRS — manual §3.22, §2.17).
**Status:** Documented facts marked ✅; items needing on-hardware confirmation marked ⚠️ CONFIRM.

---

## 1. Device class & interfaces (✅ confirmed)

- GPS/GLONASS **telematics tracker**, GSM 850/900/1800/1900, data via **GPRS** (manual §1).
- **Telematics protocols** (cmd `SETPROTOCOL`, #30): `0`=Wialon IPS v1.1, `1`=Wialon IPS v2.0,
  `2`=**Wialon Combine v1.04**, `7`=EGTS, others (Avelon/ASC/ScoutOpen). → Traccar speaks Wialon IPS + Combine. ✅
- **Discrete output:** exactly **1** — "Output 0 / open-drain / OUT0 / DIN0" (manual §2.11; Table 1.2 "Number of discrete outputs = 1"). Drives the relay/external load (use an external relay for >0.5 A). ✅
- **Bluetooth:** v4.0 BLE (Table 1.2). Uses: BLE FLS/sensors (§2.13, §3.15, App. F), **BLE identification / iBeacon** (§2.22, §3.17), config-over-Bluetooth (§3.20). ✅
- **On-device scripts:** "Scripts" tab (§3.19) — scripts uploaded via the configurator, started with **Launch** or **Autostart**. `cmdout.p` (owner-confirmed) lives here. ✅

## 2. The open / output command (✅ confirmed — the critical one)

| # | Command | Syntax | Effect |
|---|---|---|---|
| 25 | **`OUTPUT0`** | `OUTPUT0=1` | **X=1 → output shorted to GND (relay ON)** |
|    |             | `OUTPUT0=0` | X=0 → output open (relay OFF) |
|    |             | `OUTPUT0`   | no arg → returns current state |

- Firmware **0.12.8+**. "Controlling the IN1 discrete output (DIN0)." ✅
- **A momentary 1-second pulse** is achieved by either: (a) the on-device **`cmdout.p`** script (owner-confirmed — sets the output and auto-clears after 1 s), or (b) `OUTPUT0=1` followed by `OUTPUT0=0` ~1 s later (two commands). Path (a) is preferred (atomic, no timing dependency on the link).
- ⚠️ **CONFIRM:** the exact trigger for `cmdout.p` over the wire — i.e. whether sending `OUTPUT0=1` (or another specific command/parameter) is what invokes the owner's `cmdout.p`, or whether `cmdout.p` reacts to a different event. The base manual exposes **no generic "run script" command** (no RUN/LAUNCH/EXEC in Appendix A); scripts launch via configurator/Autostart. So the robust, fully-documented remote open is `OUTPUT0` (with the 1 s handled by `cmdout.p` or a paired off-command). **Extract the `cmdout.p` trigger from the firmware/scenario doc or by test.**

## 3. Command delivery channels (✅ + ⚠️)

- **SMS** (manual §3.22, App. A): authorise the sender first — **`AUTH <password>`** (default password `0`; reply `AUTH OK +<phone>`); then any Appendix-A command, e.g. `OUTPUT0=1`. ✅
- **GPRS** (manual §2.17 "Remote configuration"; cmd notes "when sending the command via GPRS"): commands are delivered over GPRS through a **remote-control/intermediary server**. ✅ that GPRS command delivery exists.
- ⚠️ **CONFIRM (the #1 unknown for Traccar):** whether `OUTPUT0=1` is accepted over the **Wialon IPS/Combine telematics channel** (so Traccar can send it as a `custom` command), versus only over GLONASSSoft's **remote-configuration server** channel and SMS. GLONASSSoft units do accept "command to unit" over Wialon in Wialon hosting; the test is whether **Traccar's** Wialon downlink framing reaches the device's command parser. See `TRACCAR_INTEGRATION_STRATEGY.md`.

## 4. Actuation confirmation (⚠️ CONFIRM)

- The output state is part of the device's I/O status. `STATMASK` (#26) controls which status-bit changes force an immediate (non-queued) record. ✅
- ⚠️ **CONFIRM:** that flipping OUT0 produces a prompt telemetry record reflecting the output bit (so the backend can move `dispatched → opened`), and the typical latency of that confirming record. Configure `STATMASK` to include the output bit if needed.

## 5. BLE / iBeacon (✅ mechanism; ⚠️ for phone-trigger use)

- **`BLEID`** (#107, fw 0.27.0+): query visible BLE identifiers. ✅
- **BLE identification** (§3.17): tracker configured as **receiver or beacon**; identifiers entered by **UUID**. The "BLE identification system (iBeacon)" detail is a separate GLONASSSoft doc (§2.22 pointer). ✅
- ⚠️ This is **iBeacon identification by UUID** — see `BLE_FEASIBILITY_REPORT.md` for why this is not a secure phone-driven open mechanism out of the box.

## 6. Other relevant commands (✅, context)

`SETSERV`/`SETPROTOCOL` (#30, server+protocol), `ROAMING0` (#31), `REMCFG`/`SU` (#57/#58, remote-config session), `AUTH` (SMS authorisation), `UPDATE` (firmware). The full Appendix A set is available in the manual for the config runbook.

---

*Reference extract for Phase-0. The ⚠️ CONFIRM items (cmdout.p trigger over the wire, Wialon-channel command acceptance, actuation read-back latency) are the gating unknowns for the TraccarDriver — see `PHASE0_TRANSPORT_VALIDATION.md`.*
