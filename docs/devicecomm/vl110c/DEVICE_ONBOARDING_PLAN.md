# VL110C — Device Onboarding Plan (online only)

> Goal for THIS stage: **get the physical VL110C online and visible (online/offline)
> in the Laravel admin panel.** No relay, no open command, no barrier, no whitelist,
> no SMS fallback. Reuses the existing DeviceComm + Traccar architecture unchanged
> except the two infra edits in TRACCAR_CONFIGURATION.md §C.
>
> **Plan only — implement after approval.**

**Device:** IMEI **`863767070453873`** · SIM **`+994517371021`**
**Traccar host:** `gps.salamheyetimiz.com` (→ `185.208.206.174`)
**GT06 port (to open):** `5023`

---

## Phase 1 — Infra: expose GT06 (see TRACCAR_CONFIGURATION.md §C)
1. Add `"5023:5023"` to Traccar compose `ports:` → `docker compose up -d`.
2. `ufw allow 5023/tcp`.
3. Verify the port listens + is reachable:
   `ss -tlnp 'sport = :5023'` and, from outside, `nc -vz gps.salamheyetimiz.com 5023`.
- **No app code / migration in this phase.**

## Phase 2 — Register the device (Traccar first, then Laravel)
1. **Add the device in Traccar** (web UI at `http://127.0.0.1:8082`, via SSH tunnel):
   Devices → Add → **Name:** `VL110C test`, **Identifier (uniqueId):**
   `863767070453873`. (Traccar rejects unknown devices, so it must exist first.)
2. **Device model row** — ensure a `device_models` row exists for this family, e.g.
   `vendor='Jimi', code='VL110C', default_driver_type='traccar', is_active=1`
   (a seed row, **not** a migration). Note its `id` for `--model`. *(If you prefer,
   reuse an existing `traccar` model id for the online-only stage — the model only
   matters for future relay work.)*
3. **Adopt into Laravel** with the existing CLI:
   ```bash
   php artisan devices:reconcile-traccar 863767070453873 \
     --serial=<serial> --model=<vl110c-model-id> \
     --sim-phone=+994517371021 --driver=traccar --label="VL110C test"
   ```
   This creates the `devices` row + `traccar_devices` (device_id ↔ uniqueId=IMEI)
   mapping. Idempotent.

## Phase 3 — Point the device at our server (SMS) — **the SERVER command**
Send this **SMS to the SIM `+994517371021`** (device is not online yet, so SMS is
the only channel):

```
SERVER,1,gps.salamheyetimiz.com,5023,0#
```
- `1` = domain form · `gps.salamheyetimiz.com` = our Traccar host · `5023` = GT06
  port · `0` = **TCP**.
- Expected reply SMS: **`SERVER set OK!`**.
- IP fallback (if DNS misbehaves): `SERVER,0,185.208.206.174,5023,0#`.
- If the device has a command password enabled (PWDSW ON), prefix per the vendor
  format; a fresh unit has PWDSW **OFF** by default, so the bare command works.
- (Optional) `APN,<carrier-apn>#` if data doesn't attach; `RESET#` to force
  reconnect after changing SERVER.

## Phase 4 — Device connects → becomes online
1. Device attaches GPRS → **TCP connect to `gps.salamheyetimiz.com:5023`** → GT06
   **login (0x01)** → Traccar login-ACK → **heartbeat/location** loop.
2. Traccar marks the device online and **forwards positions** →
   `POST /v1/traccar/forward` → `TraccarIngestionService.ingest()` matches by
   uniqueId → writes a `device_diagnostics` row + sets **`last_online_at`**.
3. Admin panel shows **online** because `now − last_online_at ≤ 15 min`
   (`offline_threshold_minutes`).

## Phase 5 — Admin visibility (the two read-only additions to build after approval)
These are **new admin read-only features** on the Device Detail page (no device
control):

### 5.1 "Raw Traccar Data" section
A new admin endpoint (e.g. `GET /admin/v1/devices/{id}/traccar-raw`) that proxies
Traccar's REST API for the mapped `traccar_id`/`uniqueId` and returns the raw JSON:
- from `GET /api/devices?uniqueId=863767070453873`: **IMEI/uniqueId, name,
  `status`, `lastUpdate`, `model`, `disabled`**;
- from `GET /api/positions?deviceId=…` (latest): **protocol, fixTime/deviceTime
  (Last Position / Last Update), latitude/longitude, `attributes` (network, battery,
  motion, sat, rssi, …)**;
- plus the latest `device_diagnostics.raw` we already store.
- Render the full raw JSON verbatim + the parsed fields the user listed (IMEI,
  UniqueId, Protocol, Last Position, Last Update, Attributes, Network, Battery,
  Motion).
- **Caveat:** the device's source **IP/Port** is not exposed by Traccar's REST API
  (only in Traccar server logs / sessions) — see Diagnostics note.

### 5.2 "Diagnostics" section
Compute from what we already have + the Traccar pull:
- **Heartbeat arriving?** = `last_online_at` within 15 min (online) → yes.
- **Last heartbeat / last packet time** = `last_online_at` (our DB) and Traccar
  `lastUpdate` (pull).
- **Protocol** = `gt06` (from the latest position).
- **IP / Port** = **not available via Traccar REST** — show `—` with a note that
  it's visible in `docker logs traccar` / Traccar session log only (a later
  enhancement could parse the log or use Traccar's session endpoint).

## Scope guard (this stage)
- **Do NOT** implement: `RELAY`, open command, barrier, whitelist push, SMS relay,
  actuation confirmation. (The ingestion service's `confirmRecentOpen` stays dormant
  because no open command will exist.)
- **Only** build: the GT06 port/UFW change + the two admin read-only sections.

## Approval gates before implementation
1. OK to publish Traccar port **5023** + open UFW `5023/tcp`?
2. Create a `Jimi/VL110C` `device_models` seed row, or reuse an existing `traccar`
   model id for now?
3. `last_online_at` strategy: rely on **position forwards** only, or also add the
   **scheduled Traccar-status pull** (recommended) for robust online detection?
4. Confirm `gps.salamheyetimiz.com` is the correct device-facing host (vs IP).
