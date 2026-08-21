# VL110C — Packet Reference

> Source: *Jimi IoT VL110C Series GPS Tracker Communication Protocol V1.2.2 (2025-08-29)*.
> Status: **research only** — no code. This is the byte-level reference the future
> driver/parser will be built against.

The VL110C speaks the **Jimi/Concox GT06-family binary protocol** over a raw TCP
socket. This is the same protocol family Traccar implements as `gt06`.

---

## 1. Frame format (every packet)

| Field | Bytes | Notes |
|---|---|---|
| Start bit | 2 | `0x78 0x78` (1-byte length variant) **or** `0x79 0x79` (2-byte length variant) |
| Packet length | 1 (or 2 for `0x7979`) | # bytes from **Protocol Number → Error Check inclusive** |
| Protocol number | 1 | packet type (see §2) |
| Information content | N | payload, type-dependent |
| Message sequence number | 2 | terminal: starts at 1 after boot, +1 per sent packet |
| Error check (CRC) | 2 | **CRC-ITU** over **Packet Length → Message Sequence (inclusive)** |
| Stop bit | 2 | fixed `0x0D 0x0A` |

**Two framings coexist:** short frames start `0x7878` + 1-byte length; long frames
start `0x7979` + 2-byte length. The parser must branch on the start bits to know
the length-field width. Terminal command responses (`0x21`) and some address/info
packets use the `0x7979` long framing.

---

## 2. Protocol numbers (packet types)

| Protocol # | Packet | Direction | Server must ACK? |
|---|---|---|---|
| `0x01` | **Login** | terminal → server | **YES** (echo `0x01` + seq) |
| `0x13` | **Heartbeat** (status) | terminal → server | **YES** (echo `0x13` + seq) |
| `0x22` | **GPS location** (UTC) | terminal → server | No (optional) |
| `0x26` | **Alarm** (UTC) | terminal → server | **YES** (echo `0x26` + seq) |
| `0x28` | LBS multi-base extended info | terminal → server | No |
| `0x2A` | GPS address request | terminal → server | server returns `0x17`/`0x97` address |
| `0x8A` | **Time calibration** | terminal → server | **YES** (server returns `0x8A` + UTC datetime) |
| `0x94` | **Information transfer** (battery, door status, ICCID, RFID…) | terminal → server | **No response required** |
| `0x80` | **Online command** | **server → terminal** | terminal replies `0x21` (or legacy `0x15`) |
| `0x21` | **Response to online command** | terminal → server | No (this IS the reply) |
| `0x15` | Response to online command (earlier firmware) | terminal → server | No |
| `0x17` / `0x97` | Chinese / English address reply | server → terminal | — |
| `0xA0` | GPS location (4G base-station) | terminal → server | No |
| `0xA1` | LBS multi-base ext info (4G) | terminal → server | No |
| `0xA4` | Multi-fence alarm (4G) | terminal → server | **YES** |

**ACK rule of thumb:** the server MUST reply to **Login (0x01)**, **Heartbeat
(0x13)**, and **Alarm (0x26 / 0xA4)** — otherwise the terminal counts a response
timeout. The server does NOT need to reply to plain **location (0x22/0xA0)** or
**information-transfer (0x94)** packets. **Online command (0x80)** is initiated by
the server; the terminal answers with **0x21**.

---

## 3. Server ACK packets (exact bytes to send back)

The ACK is a minimal frame echoing the protocol number + the terminal's message
sequence number, with a fresh CRC.

- **Login ACK (0x01):** `78 78 05 01 <seqHi> <seqLo> <crcHi> <crcLo> 0D 0A`
  Example from spec: `7878 05 01 0005 9FF8 0D0A`.
- **Heartbeat ACK (0x13):** `78 78 05 13 <seq> <crc> 0D 0A`
  Example: `7878 05 13 0100 E1A0 0D0A`.
- **Alarm ACK (0x26):** `78 78 05 26 <seq> <crc> 0D 0A`
  Example: `7878 05 26 001C 9D86 0D0A`.
- **Time-calibration reply (0x8A):** `78 78 0B 8A <YY MM DD HH MM SS> <seq> <crc> 0D 0A`
  Example: `7878 0B 8A 0F0C1D000015 0006 F086 0D0A` (6-byte UTC date/time).

> Length byte for a bare ACK is `0x05` = protocol(1) + seq(2) + crc(2).

---

## 4. CRC-ITU (a.k.a. CRC16/X.25)

- Width 16, polynomial **0x1021**, init **0xFFFF**, input reflected, output
  reflected, **XOR-out 0xFFFF**.
- Computed over bytes from **Packet Length** through **Message Sequence Number**
  (inclusive) — i.e. everything between the start bits and the CRC field.
- Packets failing CRC are silently discarded by the terminal.
- This is identical to the CRC Traccar uses for GT06
  (`Checksum.crc16(Checksum.CRC16_X25, …)`), so an existing implementation can be
  reused rather than re-derived. The spec ships reference C in "Appendix 1:
  CRC-ITU Algorithm in C".

---

## 5. Message sequence number

- Maintained by the **terminal**: initialised to **1** after boot, incremented by
  1 for every packet the terminal sends.
- The **server echoes the terminal's sequence number** in its ACK so the terminal
  can correlate the reply. The server does not maintain its own independent
  counter for ACKs (it mirrors the terminal's).
- For a **server-initiated online command (0x80)**, correlation is done via the
  **4-byte Server Flag**, not the sequence number (see §6).

---

## 6. Login packet (0x01) — connection establishment

Payload: **Terminal ID (8 bytes, packed-BCD of the IMEI)** + Type/Time
Zone-Language fields.

- IMEI → Terminal ID: `"123456789123456"` → `01 23 45 67 89 12 34 56` (last 15
  digits packed as BCD into 8 bytes; leading nibble padded).
- Our test device IMEI **863767070453873** → Terminal ID `08 63 76 70 70 45 38 73`.
- Time-zone field encodes GMT offset ×100, shifted, combined with East/West +
  language bits (e.g. `0x3200` = GMT+8).

**Login sequence (spec §2.1):**
1. On GPRS link up, terminal sends the login packet.
2. If the server ACK arrives **within 5 s**, the link is "through".
3. If no ACK within 5 s → response timeout; terminal keeps re-sending login.
4. **3 consecutive timeouts → the terminal performs a timed restart.**

→ The server MUST answer the login promptly (well under 5 s) or the device never
comes online.

---

## 7. Heartbeat packet (0x13)

Payload: Terminal-info byte (fuel/power cut, position-fixed, charging, ACC,
defense bits) + **Voltage level (0x00–0x06)** + **GSM signal (0x00–0x04)** +
Language/extension + port status.

Same 5 s / 3-timeout → restart rule as login. Heartbeat interval is configurable
via the `HBT,<T1>,<T2>#` command (default 3 min ACC-on / 5 min ACC-off). The
server must ACK each heartbeat.

The terminal-info byte is the cheapest **online/liveness + relay-state** signal:
- Bit7: cut off fuel/power (1) vs restore (0) — **the relay/actuation state**.
- Bit5–3: position fixed.
- Bit2: charging. Bit1: ACC. Bit0: defense.

---

## 8. Online command (0x80) — server → terminal  *(the relay path)*

| Field | Bytes | Notes |
|---|---|---|
| Start | 2 | `0x78 0x78` |
| Length | 1 | |
| Protocol | 1 | `0x80` |
| Command length | 1 | = Server Flag(4) + Command content length |
| **Server Flag** | 4 | **opaque correlation token**; terminal echoes it back verbatim in the `0x21` reply |
| **Command content** | M | **ASCII string, identical to the SMS command** (e.g. `RELAY,1#`) |
| Language | 2 | `0x0001` Chinese / `0x0002` English |
| Msg sequence | 2 | |
| CRC | 2 | |
| Stop | 2 | `0x0D 0x0A` |

Spec example: `78 78 0E 80 08 00000000 736F7323 0001 6D6A 0D0A` → command length
`0x08`, server flag `00000000`, content `73 6F 73 23` = ASCII **`sos#`**, language
`0001`.

**Key facts:**
- The command payload is the **same ASCII string you would send by SMS** — so
  `RELAY,1#`, `RELAY,0#`, `RELAY3,ON,1,1000#`, `STATUS#`, etc. all travel as the
  command content.
- The **Server Flag (4 bytes)** is our correlation id: pick a per-command value,
  and match the `0x21` reply that echoes it. This is how one connection can have
  multiple outstanding commands.
- Commands over this platform channel need **no password and no SMS whitelist**
  (see Command Reference §PWDSW).

---

## 9. Terminal response to command (0x21) — the result string

| Field | Bytes | Notes |
|---|---|---|
| Start | 2 | `0x79 0x79` (long framing) |
| Length | 2 | |
| Protocol | 1 | `0x21` |
| **Server Flag** | 4 | echoes the flag from the `0x80` command → correlation |
| Code | 1 | `0x01` = ASCII, `0x02` = UTF-16BE |
| **Content** | M | **the human-readable result string** |
| Msg sequence | 2 | |
| CRC | 2 | |
| Stop | 2 | `0x0D 0x0A` |

Spec example content decodes to
`Battery:4.16V,NORMAL; GPRS:Link Up; GSM Signal Level:Strong; GPS:Searching …; ACC:OFF; Defense:OFF`.

For a RELAY command the content is the relay result string (see
VL110C_RELAY_FLOW.md), e.g. `Cut off the fuel supply: Success!`.

**Earlier-firmware variant:** protocol `0x15` with `0x7878` short framing — same
idea (Server Flag + ASCII content). The parser must handle both `0x21` and `0x15`.

---

## 10. Information-transfer packet (0x94)

Carries non-location data by sub-type: `00` external-battery voltage, `04`
terminal-status sync, **`05` door status** (I/O port + door bits), `08` self-check,
`09` visible-satellite info, `0A` ICCID, `1B` RFID, `0x10` Brazil cost counter.
**No server response required.** Useful later for reading door/IO state and ICCID.

---

## 11. Location (0x22 / 0xA0) & Alarm (0x26 / 0xA4)

Location payload: 6-byte UTC datetime, sat count, lat/lon (÷1,800,000), speed,
heading+status word, MCC/MNC/LAC/CellID, ACC, upload-mode. For a **fixed barrier**
these are low value (the unit is stationary) but still parsed for completeness +
`last_online_at`. Alarm payload adds an alarm code (SOS, power-cut, tamper,
`0x2D` rollover, jamming `0x0107/0x010A/B/C`, `0xC9` idling, …) and requires an ACK.

---

## 12. Parser implications (for the future driver)

1. Read a stream, frame on `0x7878`/`0x7979`, use the correct length width, verify
   `0x0D0A` trailer + CRC-ITU; discard on mismatch.
2. Dispatch by protocol number to per-type parsers (login/heartbeat/location/
   alarm/0x21/0x94/time-calibration).
3. Immediately ACK login/heartbeat/alarm/time-calibration.
4. Keep the socket + a per-connection outstanding-command map keyed by Server Flag.
5. On `0x21`/`0x15`, match the Server Flag → resolve the pending command with the
   result string.
