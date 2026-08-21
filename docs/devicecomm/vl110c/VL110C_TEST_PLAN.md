# VL110C — Test Plan (physical device bring-up)

> Step-by-step plan to bring up and relay-test the physical unit. **Plan only** —
> nothing here is executed in this task. Assumes Option A (via Traccar) from the
> integration doc.

**Test device:** IMEI **863767070453873** · SIM **+994517371021**
**Terminal ID (login, 8-byte BCD of IMEI):** `08 63 76 70 70 45 38 73`
**Prereqs to have ready before starting:** Traccar reachable with the **GT06 port
exposed** (e.g. `:5023`); backend + Horizon running; the device SIM has data + can
receive SMS; you can SMS the SIM from an ops phone.

---

## Step 0 — Provision the device onto our platform (one-time, by SMS)
1. SMS the SIM: `SERVER,1,<traccar-host>,5023,0#` → expect reply **`SERVER set OK!`**.
   (Use the GT06 port, TCP. If DNS is an issue use `SERVER,0,<ip>,5023,0#`.)
2. (Optional) `APN,<carrier-apn>#` if data doesn't attach automatically.
3. (Optional hardening) `SOS,A,+994517371021#` then `SOSPERMIT,1#` so only the ops
   SIM can SMS-command it. (Relay opens over TCP are unaffected.)
4. `RESET#` if needed to force reconnect.

## Step 1 — Verify the device connected to the server (TCP)
- **Traccar admin UI** (or `GET /api/devices?uniqueId=863767070453873` on Traccar):
  the device shows **online / lastUpdate recent**.
- **Traccar server log** (`docker logs <traccar>` / `tracker-server.log`): a GT06
  **login (0x01)** from IMEI `863767070453873`, followed by the server login-ACK.
- On the host: `ss -tnp | grep 5023` shows an ESTABLISHED connection from the SIM's
  carrier IP.

## Step 2 — See the LOGIN packet
- Traccar log line for the GT06 login on `:5023` with the IMEI, and Traccar's ACK.
- Wire-level (optional): `tcpdump -i any -X port 5023` → first frame `78 78 … 01 …
  0D 0A` (login) then the `78 78 05 01 … 0D 0A` ACK.

## Step 3 — See the HEARTBEAT packet
- Traccar log: periodic GT06 heartbeat (`0x13`) roughly every `HBT` interval
  (default 3 min ACC-on / 5 min ACC-off) + Traccar's heartbeat-ACK.
- In our backend: the Traccar webhook updates `devices.last_online_at` — check the
  admin **diagnostics** endpoint `GET /admin/v1/devices/{id}/diagnostics` shows a
  fresh `last_online_at` and signal.

## Step 4 — See the LOCATION packet
- Traccar log / Traccar UI position updates (`0x22`/`0xA0`). For a stationary unit
  outdoors it should get a GPS fix; indoors it may only send LBS — that's fine.
- Backend: the webhook forwards positions; `last_online_at` keeps advancing.
  (Location is not required for the relay to work.)

## Step 5 — Find the device in the admin panel
1. Register it in our DB first (if not already): `php artisan devices:reconcile-traccar
   863767070453873 --serial=<serial> --model=<vl110c-model-id>
   --sim-phone=+994517371021 --driver=traccar --label="VL110C test"`.
2. In **admin-ui** open the device by serial/IMEI. Confirm: status, `last_online_at`,
   diagnostics, and the command history panel (`GET /admin/v1/devices/{id}/commands`)
   render.

## Step 6 — Send a command to the device via CLI
- There is no dedicated "send command" artisan command today; two options:
  - (a) Trigger the real pipeline through the API (recommended): call
    `POST /v1/devices/{id}/open` with a bearer token + `Idempotency-Key` (via
    `curl`/HTTPie from the CLI). This exercises `OpenDevice → DispatchOpenCommandJob
    → TraccarDriver`.
  - (b) Directly hit Traccar to isolate transport: `POST <traccar>/api/commands/send`
    `{ deviceId:<traccarId>, type:"custom", attributes:{ data:"RELAY3,ON,1,1000#" } }`.
    Expect HTTP 200 (sent) or 202 (queued/offline).
- Watch the Horizon dashboard: the `open` queue job runs; `OpenCommand` moves
  `queued → dispatching → dispatched`.

## Step 7 — Open the relay from the admin panel
- Preferred once wired: use the app/admin "Open" that calls `POST /v1/devices/{id}/open`.
  (If the admin-ui has no open button yet, use the API call from Step 6a.)
- The device receives a `0x80` online command carrying `RELAY3,ON,1,1000#` (pulse)
  or `RELAY,1#` (latching), depending on the model's `open_command_text`.

## Step 8 — What must appear in the TCP-server (Traccar) log
- Outbound **online command (0x80)** frame to the device carrying the ASCII
  `RELAY…#` (Traccar logs the command send + the target IMEI).
- Inbound **command response (0x21)** from the device with the **result string**
  (e.g. `Cut off the fuel supply: Success!`), or a relay/output state change.
- The forward webhook `POST /v1/traccar/forward` firing into the backend.

## Step 9 — How to know the command succeeded
- **Backend:** `GET /v1/commands/{commandId}` → `state`:
  - `dispatched` = Traccar accepted + sent (device reachable).
  - `opened` = actuation confirmed within the window (if Traccar surfaces the
    relay/output signal).
  - `failed(device_offline)` = Traccar queued (device offline) → SMS fallback tried.
- **Traccar/wire:** the `0x21` result string reads `…: Success!`.
- **Heartbeat cross-check (latching):** next `0x13` shows terminal-info Bit7=1
  after `RELAY,1#` (fuel/power cut = relay energised), Bit7=0 after `RELAY,0#`.

## Step 10 — How to confirm the relay physically actuated
- **Electrical:** multimeter/continuity on the relay contacts — closes on
  `RELAY,1#` / pulses on `RELAY3` / opens on `RELAY,0#`.
- **Physical:** the barrier/gate actually moves on the pulse.
- **Device echo:** `STATUS#` (SMS or online) → the relay/defense state in the reply;
  and the `0x21` success string.
- **Audit:** the `OpenCommand` reaches a terminal success state and an `audit_logs`
  entry (`OpenCommandIssued`) is written.

---

## First-connection checklist (condensed)
```
1. SMS: SERVER,1,<traccar-host>,5023,0#      → "SERVER set OK!"
2. Traccar UI: device online + login in log
3. reconcile-traccar <imei> --model=<vl110c> --sim-phone=+994517371021
4. admin-ui: device visible, diagnostics fresh
5. POST /v1/devices/{id}/open (Idempotency-Key)   [or Traccar custom RELAY3,ON,1,1000#]
6. GET /v1/commands/{id} → dispatched/opened
7. verify relay clicks / gate moves; STATUS# echoes state
```

## Safety notes
- Bench-test the relay **disconnected from the live barrier** first (contacts +
  multimeter), then connect to the gate.
- Keep the ops SIM as the only SOS number (`SOSPERMIT,1#`) so stray SMS can't
  actuate the relay during testing.
- Every open is idempotency-keyed + cooldown-limited, so retries won't double-fire.
