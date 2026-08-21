# VL110C — Command Reference

> Source: *VL110C Series Command List V1.0* (+ protocol doc for delivery framing).
> Research only. Commands are ASCII strings terminated by `#`. They are **identical
> whether sent by SMS or as a platform online command (0x80)** — only the transport
> differs. The result comes back as an ASCII string: over SMS as a reply SMS; over
> TCP as a `0x21` command-response packet.

## 1. Command delivery + authorization model

- **Two channels, same syntax:** SMS (to the SIM number) or platform/TCP (0x80).
- **Password (PWDSW):** an SMS command password (default `666666`). **"Only SMS
  settings need password if PWDSW is ON; serial port and platform settings do not
  need password."** → over TCP we send commands with **no password prefix**.
- **SMS whitelist (SOS + SOSPERMIT):** with `SOSPERMIT,1#`, only the configured SOS
  numbers may query/set parameters **by SMS**. This does **not** restrict the
  platform channel. So the device's own whitelist is an SMS-hardening feature; our
  backend roster/whitelist is the real access control for relay opens over TCP.

## 2. Server / connectivity commands (provisioning)

| Command | Meaning | Reply |
|---|---|---|
| `SERVER,1,<DOMAIN>,<PORT>[,PROTOCOL]#` | point device at a server by **domain**; PROTOCOL `0`=TCP (default), `1`=UDP | `SERVER set OK!` |
| `SERVER,0,<IP>,<PORT>[,PROTOCOL]#` | point device at a server by **IP** | `SERVER set OK!` |
| `SECOND_SERVER,<SW>,1,<DOMAIN>,<PORT>[,PROTOCOL]#` | secondary/failover server (SW ON/OFF) | — |
| `APN,<APNNAME>[,USER][,PASSWORD]#` | set APN manually (device auto-restarts, delayed) | — |
| `ASETAPN,<SW>#` | APN auto-adaptation on/off (default ON) | — |
| `GPRSON,<SW>#` | GPRS on/off (default ON) | — |
| `HBT,<T1>[,T2]#` | heartbeat interval min: T1 ACC-on (1–10, def 3), T2 ACC-off (1–10, def 5) | — |
| `RESET#` | reboot after 20 s | `The terminal will restart after 20 second!` |

**To onboard onto our platform (TCP):**
`SERVER,1,<traccar-host>,<gt06-port>,0#` → device connects to Traccar over TCP.
(Sent by SMS first, since the device isn't online yet.)

## 3. Relay / IO commands  *(the barrier actuation)*

> The VL110C relay was designed for **vehicle fuel/power cut-off**. Physically the
> relay just opens/closes a circuit, so for a barrier "cut off" = energise relay,
> "restore" = de-energise. See [VL110C_RELAY_FLOW.md](VL110C_RELAY_FLOW.md).

### `RELAY,<SW>[,V]#` — latching relay with speed judgment
- `SW`: `1` = cut off (relay ON), `0` = restore (relay OFF). Default OFF.
- `V`: safe speed 0–30 (default 10) — device only actuates below this speed (a
  safety interlock for moving vehicles; for a stationary barrier it is effectively
  always satisfied).
- **This is a latching command** — it sets a persistent state, it does not pulse.
- Replies (exact strings):
  | Current state | Command | Reply |
  |---|---|---|
  | relay 0 (restored) | `RELAY,1#` | `Cut off the fuel supply: Success!` |
  | relay 1 (cut) | `RELAY,1#` | `Already in the state of fuel supply cut off, the command is not running!` |
  | relay 1 (cut) | `RELAY,0#` | `Restore fuel supply: Success!` |
  | relay 0 (restored) | `RELAY,0#` | `Already in the state of fuel supply to resume, the command is not running!` |

- **`RELAY,1,1#`** = cut off, safe-speed 1 (actuate only below 1 km/h → basically
  always for a fixed barrier).
- **`RELAY,1,1000#`** — `1000` is **out of the documented `V` range (0–30)**. For a
  barrier you almost never want a *latching* cut anyway; you want a **momentary
  pulse**. A 1000 ms pulse is the `RELAY3` T2 parameter (see below). Treat
  `RELAY,1,1000#` as **to-be-verified on the device** — it is likely either
  rejected/clamped or interpreted as a pulse by firmware. **Recommendation: use
  `RELAY3` for pulsed barrier opens, not `RELAY,…,1000`.**

### `RELAY2,<SW>#` — relay gated by ACC state
- ON → cut fuel after ACC OFF; OFF → restore. Vehicle-specific; not useful for a
  barrier.

### `RELAY3,<SW>[,T1][,T2]#` — pulsed relay  ← **best fit for a barrier**
- If ACC OFF, relay activates directly and continuously.
- `SW`: ON = cut / OFF = restore.
- `T1`: interval of relay activation 1–5 s (default 1 s).
- `T2`: **length of relay activation 200–1000 ms (default 500 ms)** — i.e. the
  **momentary pulse width** that "opens the gate" then auto-releases.
- Example: `RELAY3,ON,1,1000#` → a single 1000 ms pulse. This is the natural
  "open the barrier for ~1 s" behaviour.

### Defense (unused for barrier)
`111#` active defense; `DSRESET#` withdraw defense.

## 4. Whitelist / authorization commands (SMS channel hardening)

| Command | Meaning |
|---|---|
| `SOS,A,<PHONE1>[,PHONE2][,PHONE3]#` | add SOS (authorized) number(s), max 3 |
| `SOS,D,<SEQ>#` or `SOS,D,<PHONE>#` | delete an SOS number |
| `SOSPERMIT,<M>[,Y]#` | `M=1` → **only SOS numbers may query/set by SMS** (the SMS whitelist); `M=0` any number. `Y` = alert-fan-out mode |
| `CENTER,A,<PHONE>#` / `CENTER,D#` | set / delete center number |
| `PERMIT,<M>#` | who may set SOS/center numbers |
| `PWDSW,<SW>#` | enable SMS command password; `PWDSW,<PWD>,OFF#` to disable (default pwd `666666`) |
| `PASSWORD,<OLD>,<NEW>#` | change command password |

**Mapping to our system:** these lock down *SMS* control of the SIM. Our backend
does **not** rely on them for relay authorization (that's roster/whitelist +
platform channel). We would set them once during provisioning as a hardening step
(e.g. `SOSPERMIT,1#` + our ops SIM as the sole SOS number) so a random SMS can't
open the barrier. See integration doc §Whitelist mapping.

## 5. Query / diagnostic commands (read state)

| Command | Reply example |
|---|---|
| `STATUS#` | `Battery:3.48V,…; GPRS:Link Down; GPS:Successful positioning; SVS…; ACC:OFF; Defense:OFF;` |
| `PARAM#` | `IMEI:…;SOS:,,;CENTER:;TimeZone:E,8,0 (AUTO);` |
| `VERSION#` | `[VERSION]VT81_V141_AAAP_V1.0_20200821_1344` |
| `ICCID#` | `ICCID:898604231919C2690159` |
| `IMSI#` | `IMSI:460044335609859` |
| `WHERE#` / `POSITION#` / `URL#` | latitude/longitude / address / maps URL |
| `CXSV#` | `CXSV set OK!` |

These are how, at the wire level, we can confirm the device is reachable and read
its relay/defense/battery state (also visible via the heartbeat terminal-info byte
and the `0x94` info-transfer packets).

## 6. Practical command set for the barrier integration

| Purpose | Command | Notes |
|---|---|---|
| Onboard to our platform | `SERVER,1,<host>,<port>,0#` | via SMS, one-time |
| Lock SMS control | `SOSPERMIT,1#` + `SOS,A,<opsSIM>#` | hardening |
| Open barrier (pulse) | `RELAY3,ON,1,<pulseMs>#` | **preferred** momentary open |
| Open barrier (latching, if hardware needs it) | `RELAY,1#` / `RELAY,0#` | verify with hardware |
| Read state | `STATUS#` | diagnostics |
| Reboot | `RESET#` | recovery |

**Open item to confirm on the test device:** whether the barrier hardware is wired
for a **momentary pulse** (→ `RELAY3`) or a **latching** open/close (→ `RELAY`),
and the exact success string(s) for the chosen command. This determines the driver
command text and the success-detection rule.
