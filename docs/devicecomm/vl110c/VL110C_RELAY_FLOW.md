# VL110C — Relay Flow (end to end)

> How a barrier-open travels from our backend to the VL110C relay and how we know
> it worked. Research only. Cross-refs: packet reference §8/§9, command reference §3,
> integration doc.

## 1. The two ways the relay can be driven

| Style | Command | Behaviour | Barrier fit |
|---|---|---|---|
| **Latching** | `RELAY,1#` / `RELAY,0#` | sets a persistent ON/OFF relay state | needs an explicit close after open |
| **Pulsed** | `RELAY3,ON,1,<T2ms>#` | energises the relay for `T2` ms (200–1000) then releases | **natural "open gate for ~1 s"** |

For a barrier controller the **pulsed** form is the clean model: one command → one
momentary contact → gate opens → relay auto-releases. The **latching** form works
too if the barrier hardware itself is edge/level triggered, but then a "close"
command is required to reset. **Which one to use is a hardware-wiring question to
confirm on the test unit.**

## 2. End-to-end flow (through Traccar — the recommended path)

```
Admin/app "Open"           Salam backend                Traccar (GT06)            VL110C
      │                          │                            │                      │
      │  POST /v1/devices/{id}/open (Idempotency-Key)         │                      │
      │─────────────────────────▶│                            │                      │
      │                          │ OpenDevice: authz, cooldown, issue OpenCommand(queued)
      │                          │ DispatchOpenCommandJob → CommandDispatcher        │
      │                          │  TraccarDriver.open()      │                      │
      │                          │  TraccarClient.sendCommand(traccarId, "RELAY3,ON,1,1000#")
      │                          │───────────────────────────▶│  builds 0x80 online  │
      │                          │  HTTP 200 → dispatched      │  command frame        │
      │                          │  (HTTP 202 → device offline)│─────────────────────▶│ relay pulses
      │                          │                            │◀─────────────────────│ 0x21 result string
      │                          │                            │  parses response /    │
      │                          │                            │  output/status change │
      │                          │◀───────────────────────────│  webhook: POST /v1/traccar/forward
      │                          │ TraccarIngestionService: within actuation window  │
      │                          │  output/relay bit flipped → mark OpenCommand OPENED│
      │  GET /v1/commands/{id} → state: dispatched → opened   │                      │
      │◀─────────────────────────│                            │                      │
```

## 3. State mapping (our OpenCommand state machine ← device reality)

| Device reality | Traccar signal | Our OpenCommand state |
|---|---|---|
| command accepted by Traccar, sent to device | `sendCommand` HTTP 200 | `dispatched` |
| device confirmed relay actuated (0x21 success string / output bit / fuel-cut bit flip) within window | webhook output/position event | `opened` |
| Traccar queued because device offline | HTTP 202 | `failed(device_offline)` → SMS fallback |
| device replied with an error / no confirm in window | none / negative | `failed` or stays `dispatched` |
| never confirmed before expiry | timeout | `expired` |

The existing pipeline already models exactly these states
(`OpenCommandState: queued→dispatching→dispatched|opened|failed|expired`), and the
`TRACCAR_ACTUATION_WINDOW` (default 30 s) is the confirmation window. **Whether we
reach `opened` (confirmed) vs stay at `dispatched` (sent) depends on whether the
GT06 relay actuation is surfaced to Traccar as an output/status change** — the key
verification item (see §5).

## 4. How "command success" is determined (three signals, strongest first)

1. **`0x21` result string** — `Cut off the fuel supply: Success!` /
   `Restore fuel supply: Success!` = success; `… command is not running!` = no-op
   (already in that state); anything else = failure. This is the most explicit
   signal but requires Traccar to surface the command response text.
2. **Heartbeat terminal-info bit** — Bit7 of the terminal-info byte (`1` = fuel/
   power cut, `0` = restored) reflects the **actual relay state**. After a
   `RELAY,1#` the next heartbeat should show Bit7=1. This is a reliable
   state-confirmation independent of the response string.
3. **`0x94` info-transfer `05` door/IO status** — the I/O port + door bits reflect
   external wiring; useful if the barrier reports back on a monitored input.

For a **pulsed** open (`RELAY3`), the relay returns to rest immediately, so signals
(2)/(3) are transient; the **`0x21` success string** (signal 1) is the primary
confirmation. For a **latching** open, signal (2) is the durable confirmation.

## 5. The confirmation open question (must verify on the device)

Traccar's GT06 decoder definitely handles login/heartbeat/location/alarm. What
must be verified on the physical unit + the deployed Traccar:
- (a) Does Traccar send the exact `RELAY…#` text as a `0x80` online command when
  given a `custom` command with `data=RELAY3,ON,1,1000#`?
- (b) Does Traccar parse the `0x21` response and/or the relay/output bit and forward
  it (so we can move `dispatched → opened`)? If not, we treat a successful *send*
  as `dispatched` and (optionally) read the next heartbeat's fuel-cut bit to confirm.
- (c) What is the precise success string for the chosen command on THIS firmware?

Until (b)/(c) are confirmed on the test device, the safe assumption is:
**success = Traccar accepted + sent the command (`dispatched`)**, with `opened`
confirmation added once we know the relay/output signal Traccar exposes.

## 6. Idempotency & duplicate-press (already handled)

- The API requires an **Idempotency-Key**; a repeated open with the same key
  replays the same `OpenCommand` (no double pulse). The Flutter app also locks the
  button while a command is in flight (Phase 3). So a double press cannot fire two
  relay pulses.
- A per-command **cooldown** (`CooldownGuard`) further rate-limits opens per user/
  device, matching the device's own relay-safety behaviour.
