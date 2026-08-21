# VL110C — Protocol Analysis

> Source: *Jimi IoT VL110C GPS Tracker Communication Protocol V1.2.2*.
> Research only — no code, no migrations, no endpoint changes.
> Companion: [VL110C_PACKET_REFERENCE.md](VL110C_PACKET_REFERENCE.md) (byte-level).

## 1. What this device is, protocol-wise

The VL110C is a Jimi/Concox GPS tracker whose relay output we want to use as a
**barrier controller**. It speaks the **GT06-family binary TCP protocol** — the
same family Traccar decodes as **`gt06`**. This single fact drives the whole
integration (see [VL110C_DEVICECOMM_INTEGRATION.md](VL110C_DEVICECOMM_INTEGRATION.md)):
we almost certainly do **not** need to write a socket server or a byte parser
ourselves — Traccar already terminates GT06 TCP and exposes a command API.

Packet family: start bits `0x7878` (1-byte length) / `0x7979` (2-byte length),
CRC-ITU trailer, `0x0D0A` stop. Protocol numbers: login `0x01`, heartbeat `0x13`,
location `0x22`/`0xA0`, alarm `0x26`/`0xA4`, **online command `0x80`**, **command
response `0x21`** (legacy `0x15`), info-transfer `0x94`, time-calibration `0x8A`.

## 2. Connection lifecycle (what happens when the device powers on)

```
Power on
  → (SIM/GPRS attach; APN)
  → TCP connect to SERVER host:port  (configured by the SERVER command)
  → send LOGIN (0x01, carries IMEI as 8-byte BCD Terminal ID)
        server must reply LOGIN-ACK within 5 s
        no ACK in 5 s → timeout; resend login; 3 timeouts → timed restart
  → connection "through"
  → periodic HEARTBEAT (0x13)  [interval: HBT command, default 3 min ACC-on]
        server must reply HEARTBEAT-ACK within 5 s (same 3-timeout→restart rule)
  → LOCATION (0x22/0xA0) uploads by rule; ALARM (0x26) on events (need ACK)
  → INFORMATION-TRANSFER (0x94) for battery/door/ICCID (no ACK)
  ← server may push ONLINE COMMAND (0x80) at any time  → device replies 0x21
```

**Reconnect:** on socket drop or 3 response-timeouts the device restarts / re-attaches
and repeats login. There is no separate "reconnect" packet — a fresh login on a
new socket is the reconnect. Any buffered fixes are flushed after reconnect
(upload modes `0x01` buffered, `0x05` last-valid-before-interruption).

**Timeout model (spec §2.1/§2.2):** 5-second server-response window per
login/heartbeat; **3 consecutive misses → device performs a timed restart.** So the
server side is obligated to answer login + heartbeat quickly and reliably.

## 3. ACK obligations (who must reply to what)

| Packet | Server must reply? | Reply |
|---|---|---|
| Login `0x01` | **Yes, < 5 s** | echo `0x01` + terminal's seq + CRC |
| Heartbeat `0x13` | **Yes, < 5 s** | echo `0x13` + seq |
| Alarm `0x26` / `0xA4` | **Yes** | echo `0x26` + seq |
| Time-calibration `0x8A` | **Yes** | `0x8A` + 6-byte UTC datetime |
| Location `0x22` / `0xA0` | optional/no | — |
| Info-transfer `0x94` | **No** | — |
| Address request `0x2A` | returns address `0x17`/`0x97` | — |
| Online command `0x80` (server-initiated) | device replies `0x21`/`0x15` | — |

In the Traccar-based integration **Traccar owns all of these ACKs** — its GT06
decoder answers login/heartbeat/alarm automatically. We never hand-craft an ACK.
(The ACK byte formats are documented in the packet reference only so we can verify
Traccar's behaviour and debug at the wire level.)

## 4. CRC and message sequence

- **CRC-ITU (CRC16/X.25):** poly `0x1021`, init `0xFFFF`, reflected in/out, xor-out
  `0xFFFF`, computed over *Packet Length → Message Sequence* inclusive. Bad-CRC
  packets are dropped. Traccar's GT06 decoder computes/validates this; we do not.
- **Message sequence:** terminal-maintained (1 at boot, +1 per sent packet). The
  server ACK echoes the terminal seq. For a server→device **online command**, the
  4-byte **Server Flag** (not the seq) is the correlation token echoed back in the
  `0x21` reply.

## 5. Online-command flow (how we make the relay move)

1. Server sends `0x80` with **command content = ASCII SMS command** (e.g.
   `RELAY,1#`) + a 4-byte Server Flag.
2. Device executes and replies `0x21` (long `0x7979` framing) echoing the Server
   Flag + a **result string** (e.g. `Cut off the fuel supply: Success!`).
3. Command success = the result string indicates success (and/or the terminal-info
   fuel/power-cut bit in the next heartbeat flips). See
   [VL110C_RELAY_FLOW.md](VL110C_RELAY_FLOW.md).

Over the platform (TCP) channel, commands need **no password and no SOS
whitelist** — those only gate SMS commands. Access control is entirely ours.

## 6. Packet types worth parsing for a barrier

| Priority | Packet | Why |
|---|---|---|
| High | `0x01` login | binds IMEI ↔ session; sets device online |
| High | `0x13` heartbeat | liveness + **relay/fuel-cut bit** (relay state) + voltage/GSM |
| High | `0x80`/`0x21` | issue relay + read result |
| Medium | `0x94` info-transfer (`05` door, `00` battery, `0A` ICCID) | door/IO state, provisioning info |
| Low | `0x22`/`0xA0` location, `0x26` alarm | unit is fixed; low value but ok for `last_online_at`/tamper |

## 7. Analysis conclusions

1. **Do not build a parser.** VL110C is GT06; Traccar decodes GT06. Reuse it.
2. **ACKs and CRC are Traccar's job.** We only need to send commands + read state
   through Traccar's HTTP API + webhook (existing pattern).
3. **The relay is driven by an online command carrying an ASCII string** — the
   only real difference from the current UMKa/Wialon device is the *command text*
   (`RELAY,…#` vs `OUTPUT0=1`).
4. **Timeout discipline matters at the Traccar layer**, not ours: keep Traccar up
   and reachable so login/heartbeat are answered within 5 s.

Open question (see integration doc §Risks): confirm the deployed Traccar 6.14.5
GT06 decoder (a) accepts VL110C login, (b) sends the RELAY command as a `0x80`
online command, and (c) surfaces the relay/actuation state so we can confirm
"opened".
