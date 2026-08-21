# UMKa Configurator — Onboarding Checklist (IMEI 868184062169571)

**Date:** 2026-06-26
**Server side:** ✅ verified & fixed (Wialon now reachable on `gps.salamheyetimiz.com:5011` → Traccar `wialon`
decoder; see `UMKA_CONNECTION_STATUS.md`). **The remaining blocker is the device configuration below.**
Connect the device to the **UMKa Configurator** (GLONASSSoft desktop app, via USB) and work through this list.

---

## 0. Known target values (from verified server checks — do not change)

| Setting | Value |
|---|---|
| **Server host** | `gps.salamheyetimiz.com` (preferred) **or** `185.208.206.174` |
| **Server port** | **`5011`** |
| **Protocol** | **Wialon IPS v2.0** (Wialon IPS v1.1 and Wialon Combine also work — see §5) |
| **Device id / login** | the device sends its **IMEI `868184062169571`** automatically (no need to type it) |

## 1. Screenshots to send me

Please send a screenshot of each of these (so I guide based on the **actual** device state, not assumptions):

- **A. Main / Status / Device info** — firmware version, IMEI, and any "server connection / GPRS" status indicator.
- **B. Server / Connection settings** — the current **server address** and **port** the device is pointing at.
- **C. Protocol** — the currently selected telematics protocol.
- **D. GPRS / APN / Mobile-data** — the APN value and whether mobile data/GPRS is enabled.
- **E. Security / Password (AUTH)** — the device access password (needed only if we ever send SMS commands).
- **F. (if visible) Connection log / online indicator** — anything showing whether it currently has a server session.

> Send A–D at minimum; E–F are helpful. Screenshots beat descriptions — I'll read the exact field names/values.

## 2. Tabs to open

| Tab (by function — exact label may differ in your Configurator version) | Purpose |
|---|---|
| Main / Status | confirm firmware + IMEI + current connection state |
| **Server / Connection settings** | where we set host + port |
| **Protocol** | where we set Wialon IPS |
| **GPRS / Network / APN** | confirm the SIM data/APN |
| Security / Password | note the AUTH password |
| (reference only) Inputs/Outputs, Scripts, BLE | **do not change** — out of scope for connectivity |

## 3. Fields to verify (read first, before changing anything)

- Current **server host** + **port** — is it the factory/GLONASSSoft default, or already ours?
- Current **protocol** — Wialon vs EGTS vs other?
- **APN** — set and matching the SIM operator?
- **Mobile data / GPRS** — enabled?
- **Firmware version** — `OUTPUT0` needs ≥ 0.12.8 (relevant later for HB1, not now).

## 4. Values to change (only what's needed for connectivity)

1. **Server host** → `gps.salamheyetimiz.com` (or `185.208.206.174`).
2. **Server port** → `5011`.
3. **Protocol** → **Wialon IPS v2.0** (if the device is on EGTS/other or a different Wialon variant).
4. **APN** → the SIM operator's APN, only if empty/wrong (you confirmed data is active, so likely already set —
   don't change a working APN; just verify it in screenshot D).
5. Save/write settings to the device, then reboot it if the Configurator doesn't apply live.

> Change **only** these. Leave outputs, scripts, BLE, and the AUTH password as-is.

## 5. Wialon IPS or Wialon Combine — recommendation + why

**Select Wialon IPS v2.0.**

- Both **Wialon IPS** (v1.1 / v2.0) and **Wialon Combine** are decoded by Traccar's **single `wialon`
  decoder** (our port 5011 → container 5039), so **either will connect**. This is verified — a Wialon IPS
  login from outside was decoded as `wialon` on our server.
- **Wialon IPS v2.0** is the recommendation because it is the standard, widely-deployed Wialon telematics
  protocol, includes a CRC for packet integrity, and is the simplest fully-supported choice. **v1.1** also works.
- **Wialon Combine** is a batching/compression variant (packs multiple records per packet) aimed at saving
  bandwidth on high-frequency fleets. For one stationary barrier with infrequent telemetry it adds complexity
  with no benefit — **not needed**. (If you can only pick "Wialon Combine", it will still work on our decoder.)

## 6. Server host and port (final)

```
Host:     gps.salamheyetimiz.com   (or 185.208.206.174)
Port:     5011   (TCP)
Protocol: Wialon IPS v2.0
```

(Host `5011` is firewalled-open and DNS-ready; internally it now maps to Traccar's Wialon decoder.)

## 7. Step-by-step verification that the device connected (we do this together)

After you apply the settings and the device is powered + on data:

1. **You:** confirm the Configurator shows a "connected to server / GPRS active" state (screenshot F).
2. **Me (live on the server):** watch the Traccar log for the device's session:
   - expect a line `[Txxxx: wialon < <device-mobile-IP>] …` carrying IMEI `868184062169571`, followed by
     `Unknown device - 868184062169571` (this is **success** — it reached Traccar; it's just not registered yet).
3. **Me:** register the device in Traccar (`uniqueId = 868184062169571`) so its telemetry is stored.
4. **Me:** confirm in Traccar:
   - device **online** + `lastUpdate` recent,
   - **protocol = wialon**,
   - **position** (note: a stationary unit may need a GPS fix / open sky),
   - **attributes / I-O** (inputs, ignition, and whether the **OUTPUT0 / output** state is present).
5. **Me:** confirm the position-forward webhook delivers a real position into Laravel (`device_diagnostics`).
6. **Outcome:** device confirmed communicating over **Wialon → Traccar** (the chosen channel).

> Still **out of scope** here: sending `OUTPUT0` / opening the relay (HB1) — that's a separate, approval-gated,
> destructive test (`NEXT_HARDWARE_TEST_PLAN.md` Stage 5).

**Send the screenshots (§1) and I'll guide you field-by-field from there.**
