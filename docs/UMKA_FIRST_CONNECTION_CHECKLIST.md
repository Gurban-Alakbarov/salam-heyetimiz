# UMKa 310 v2L — First Connection Checklist (IMEI 868184062169571)

**Date:** 2026-06-27
**Scope:** Get the **real device** to appear and report telemetry in our Traccar for the **first time**.
**Method:** based on the device's **exported config** (`config_868184062169571.json`) + a fresh, full
production re-verification (below). Audit/plan only — **no code, no DB writes, no scripts, no OUTPUT0, no BLE,
no digital-output changes.**

---

## 0. Root cause (CONFIRMED by the exported config)

The device's primary telematics server is set to **`yollarpro.az : 20332`** — a third-party "Yollar" Wialon
platform (20332 is the standard Wialon Hosting IPS port). **The device has been reporting to *that* platform,
not to us.** That is precisely why it never appeared in our Traccar. Nothing on our server was the blocker for
*this* symptom (our side is independently verified ready below).

Exported evidence:
- `server_params = { ip:"yollarpro.az", port:"20332" }`
- `protocol = { main:"0" (Wialon IPS v1.1), alt:"2" (Combine), third:"2" (Combine) }`
- `device_name = "YollarLR"`, `script_autorun = /eeprom/yollaz.amx` → all "Yollar"-platform setup.

---

## 1. The ONLY changes required on the device (in the UMKa Configurator)

| # | Field (Configurator) | From (current) | To (set this) | Why |
|---|---|---|---|---|
| 1 | **Server host** (server #1) | `yollarpro.az` | `gps.salamheyetimiz.com` (or `185.208.206.174`) | point telemetry at *our* Traccar |
| 2 | **Server port** (server #1) | `20332` | **`5011`** | our public Wialon device port |
| 3 | **Protocol** (main) | `Wialon IPS v1.1` (=0) | **leave Wialon IPS** (v1.1=0 *or* v2.0=1) | already compatible with our decoder — see §3 |

> **That is the entire required change set: host + port.** Protocol is *already* a Wialon variant our Traccar
> decodes, so strictly you only must change **host + port**. If the UI makes you re-pick a protocol, choose
> **Wialon IPS** (v1.1 or v2.0) — **not** EGTS/NDTP/other.

**Answer to "is changing only Server + Port + Protocol enough for the first test?" → YES.**
Host + Port are mandatory; Protocol is already correct (Wialon IPS). No other field needs to change for first
contact, **provided the SIM has working mobile data** — which is already proven, because the device currently
reaches `yollarpro.az` over GPRS with its present (blank-APN) settings.

---

## 2. What you must NOT touch (leave exactly as-is)

| Area | Config key(s) | Why leave it |
|---|---|---|
| **Autorun / output script** | `script_autorun` (mode 1, `/eeprom/yollaz.amx`), `script_file` | barrier-open logic; out of scope; harmless while idle (see §4) |
| **Digital output 0 (relay)** | `digital_output0` (=0) | the barrier relay — never touch in a comms test |
| **BLE** | `ble_mode` (=3), `ble_id_beacon`, `ble_id_listen_*`, `llsble_*` | iBeacon + BLE sensors; irrelevant to connectivity |
| **Inputs** | `inputs_mode` (in0=1,in1=4), `input_limits_0` | wiring-dependent; not needed for first contact |
| **AUTH / device password** | `dev_pass` (=`0000`) | only used for SMS commands later; don't lock yourself out |
| **Authorized phone** | `phones.number_0` (=`+994505384489`) | SMS-fallback control number; leave |
| **APN / GPRS** | `gprs_settings_0` (apn empty) | **DO NOT assume it's wrong** — it currently works (device reaches yollarpro). Just *verify* it in screenshot D; change only if it turns out blank-APN no longer connects |
| **Timers / traffic** | `period` (30/300), `traffic` (keepalive 300, pack 1460, 5 rec), `upload_mode` (0) | sane defaults; changing them adds risk with no benefit |
| **Status mask / GNSS** | `statusMask`, `gnss_mode`, `maxhdop`, etc. | telemetry-content tuning; out of scope |

---

## 3. Protocol — Wialon IPS vs Combine (and why it already works)

- Our exposed port **`5011` → container `5039` = Traccar's single `wialon` decoder**, which handles **Wialon
  IPS v1.1, Wialon IPS v2.0, and Wialon Combine** — all three. (Verified: an external `#L#868184062169571;NA`
  login — Wialon IPS **v1.1** format — was decoded as `wialon` on our server.)
- The device's **main protocol is already `0` = Wialon IPS v1.1** → **already compatible.** You may keep `0`,
  or set `1` (v2.0) — both connect.
- **Recommendation:** keep it simple — **Wialon IPS** (either version). Combine also works but adds batching
  with no benefit for one stationary barrier. Avoid EGTS/other non-Wialon protocols.

---

## 4. Can `yollaz.amx` stay, or must it be replaced before testing?

**It can stay — leave it untouched for the first connection test.**

- `script_autorun.mode = 1` means the script auto-runs on boot, **but its decoded logic only *acts* when it
  receives a chat/command trigger** (the script's strings reference `setout` / `settimer` / OUTPUT0
  activate/deactivate / "Unknown command"). With **no command sent**, it just sits idle and does **not** touch
  the relay and does **not** interfere with telemetry.
- For a **telemetry-only** first test (we are *receiving* position/status, **not sending** any command), the
  script is inert and safe. **Do not modify or delete it** (you asked not to, and there's no need).
- It only becomes relevant later, in the separate, approval-gated **HB1** output test — at which point we'll
  decide whether to trigger via this script's chat-command or send `OUTPUT0` directly. **Out of scope now.**

> Safety guarantee for this test: because **we will not send any chat/command/OUTPUT0**, the barrier cannot
> open during the first connection test, regardless of the script being present.

---

## 5. Production server — RE-VERIFIED READY (2026-06-27, all checks live)

| # | Check | Result | Evidence |
|---|---|---|---|
| 1 | Traccar running + config | **READY** | `traccar Up`, `traccar-db Up (healthy)`; `web.port=8082`, `forward.enable=true`, `forward.type=json` |
| 2 | Docker mapping | **READY** | `5039/tcp → 0.0.0.0:5011`, `8082 → 127.0.0.1:8082` |
| 3 | Wialon listener | **READY** | live Wialon login decoded: `[wialon < …] #L#868184062169571;NA` on the 5011→5039 path |
| 4 | GPS hostname | **READY** | `gps.salamheyetimiz.com → 185.208.206.174` (DNS-only, correct for raw TCP) |
| 5 | Public port 5011 | **READY** | `LISTEN 0.0.0.0:5011` + `[::]:5011` (docker-proxy) — externally bound |
| 6 | Reverse proxy | **READY** | `https://traccar.salamheyetimiz.com/` → `<title>Traccar</title>` |
| 7 | Firewall (UFW) | **READY** | active; `5011/tcp ALLOW Anywhere` (v4+v6) |
| 8 | Device registration | **PRESENT in Traccar** | Traccar has device `id=1 "Sinam Test" uniqueId=868184062169571` (offline). App DB: `devices=0`, `traccar_devices=0` (not linked to Laravel yet) |
| 9 | Webhook Traccar→Laravel | **READY (path)** | container → `/v1/traccar/forward?token=…` → `{"status":"ok"}` (200) |

> **Note on #8 (honest):** since the last report, a Traccar device named **"Sinam Test"** with our exact IMEI
> now exists in Traccar's own DB (its `lastUpdate` matches an earlier synthetic test login, so it was created
> via the Traccar UI, not by app logic). **This is helpful** — when the real device connects, Traccar will
> immediately mark it **online** and store positions (no "Unknown device" rejection). It is **not** yet linked
> into the Laravel app DB (that mapping is a later, separate step and is not needed for first contact).

**Conclusion: the server side is completely ready. The only remaining variable is the device's server/port.**

---

## 6. EXACT order of operations — the first hardware test

> Roles: **You** = at the device with the UMKa Configurator (USB/Bluetooth). **Me** = watching the production
> server live over SSH. Do the steps in this order; don't skip.

### Phase A — Pre-flight (before changing anything)
1. **You:** open the Configurator, connect to the device, and **send me screenshots** of: Status/Info,
   Server/Connection, Protocol, and GPRS/APN (so we read the *actual* current values, not assumptions).
2. **You:** confirm the device shows mobile data / GPRS active (it already reaches yollarpro, so this should be green).
3. **Me:** confirm the server is ready (already done — §5) and start tailing the Traccar log filtered for the IMEI.

### Phase B — Apply the minimal change
4. **You:** in **Server settings (server #1)** set host = `gps.salamheyetimiz.com` and port = `5011`.
5. **You:** confirm **Protocol = Wialon IPS** (keep v1.1, or pick v2.0). Do **not** select EGTS/other.
6. **You:** **do not change** APN, outputs, scripts, BLE, inputs, password, phone (§2).
7. **You:** **Write/Save** settings to the device. If the Configurator doesn't apply live, **reboot the device**.

### Phase C — Watch for first contact (success criteria)
8. **Me (live):** watch for a Wialon session from the device's **mobile IP** carrying IMEI `868184062169571`:
   - Expected: `[Txxxx: wialon < <device-mobile-ip>] #L#868184062169571;…` followed by an **accepted login**
     (because the device is already registered in Traccar as "Sinam Test"). **This is success — first contact.**
9. **Me:** confirm in the **Traccar UI** (`https://traccar.salamheyetimiz.com`): device **status = online**,
   `lastUpdate` is current, **protocol = wialon**.
10. **Me:** confirm a **position/telemetry** record arrives (a stationary unit may need open sky for a GPS fix;
    even without a fix, the *connection* + status counts as first contact).

### Phase D — Confirm the data pipeline (still non-destructive)
11. **Me:** confirm Traccar **forwards** the position to Laravel — `/v1/traccar/forward` is hit and a row lands
    in `device_diagnostics` (proves the end-to-end Traccar→backend webhook with a **real** position).
12. **Me:** report inputs / ignition / **OUTPUT0 state** as *read* values (observe only — **no command sent**).

### Phase E — Stop here
13. **Outcome:** device confirmed communicating over **Wialon → Traccar → Laravel**. First connection done.
14. **Out of scope (separate, approval-gated test):** sending `OUTPUT0` / opening the relay = **HB1**
    (`NEXT_HARDWARE_TEST_PLAN.md`). **Not part of this test.**

---

## 7. If first contact does NOT appear (ordered triage — diagnose, don't guess)

1. **Settings didn't save** → re-open Configurator, re-read server #1 host/port; some firmwares need a reboot.
2. **Still pointing at a 2nd/3rd server** → if `SETSERV` has alt/third entries still set to yollarpro/GLONASSSoft,
   that's fine for *us* (it won't block server #1), but confirm **server #1** is ours.
3. **Wrong protocol picked** → must be **Wialon IPS** (not EGTS); re-check.
4. **No data session** → only *now* inspect APN. If blank-APN suddenly won't connect to us, set the SIM
   operator's APN. (We won't pre-emptively change a setting that currently works.)
5. **Me:** if I see the TCP connect but no valid Wialon login, I'll capture the exact bytes and we adjust the
   protocol version. If I see nothing at all, the device isn't reaching us (device/SIM/network side).

---

**Bottom line:** change **host → `gps.salamheyetimiz.com`** and **port → `5011`** (protocol already Wialon IPS),
save/reboot, and the device should make first contact. Everything else stays untouched. Server side is verified
ready. Send the screenshots and I'll watch the log live as you apply the change.
