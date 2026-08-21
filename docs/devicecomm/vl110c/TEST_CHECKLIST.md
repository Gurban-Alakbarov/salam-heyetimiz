# VL110C — Test Checklist (online connectivity only)

> Verify the device connects to Traccar and shows **online** in the admin panel.
> No relay/open tests here. Device IMEI **863767070453873** · SIM **+994517371021**.

## Pre-req (infra, after approval)
- [ ] Traccar compose has `"5023:5023"`; `docker compose up -d` done.
- [ ] `ufw allow 5023/tcp` applied; `ufw status` shows it.
- [ ] `ss -tlnp 'sport = :5023'` shows Traccar listening.
- [ ] From an external host: `nc -vz gps.salamheyetimiz.com 5023` succeeds.

## Registration
- [ ] Device exists in Traccar with **uniqueId = 863767070453873** (Traccar UI).
- [ ] `device_models` has a `Jimi/VL110C` (or reused traccar) row; note its id.
- [ ] `php artisan devices:reconcile-traccar 863767070453873 --serial=<serial>
      --model=<id> --sim-phone=+994517371021 --driver=traccar` →
      prints `Reconciled: Laravel device #N <-> Traccar id M`.
- [ ] `traccar_devices` row present (device_id ↔ unique_id).

## Point the device at the server (SMS)
- [ ] SMS to `+994517371021`: **`SERVER,1,gps.salamheyetimiz.com,5023,0#`**
- [ ] Reply SMS received: **`SERVER set OK!`** (or set via IP form if DNS fails).
- [ ] (if needed) `RESET#` to force reconnect.

## Device connects
- [ ] Traccar UI: device turns **online**, `lastUpdate` recent.
- [ ] `docker logs -f traccar` shows a **gt06** login from IMEI `863767070453873`
      on `:5023` and the login-ACK.
- [ ] `ss -tnp | grep 5023` shows an ESTABLISHED connection from the SIM carrier IP.

## Telemetry reaches the backend
- [ ] Backend log / `device_diagnostics`: a new row appears for the device
      (source `device_initiated`, `raw` populated).
- [ ] `devices.last_online_at` is set to a recent timestamp
      (SQL: `SELECT id,last_online_at,last_signal_strength FROM devices WHERE serial='<serial>'`).
- [ ] (if positions are sparse and status-pull is enabled) the pull job also
      advances `last_online_at` from Traccar `lastUpdate`.

## Admin panel shows online
- [ ] Admin device detail page shows **Online** (because `now − last_online_at ≤ 15 min`).
- [ ] Diagnostics endpoint `GET /admin/v1/devices/{id}/diagnostics` returns fresh
      `last_online_at` + signal.
- [ ] (after building) **Raw Traccar Data** section renders IMEI, UniqueId,
      Protocol=`gt06`, Last Position, Last Update, Attributes, Network, Battery,
      Motion, and the full raw JSON.
- [ ] (after building) **Diagnostics** section shows heartbeat-arriving = yes, last
      heartbeat/last packet time, protocol; IP/Port shown as `—` (REST limitation).

## Negative / robustness
- [ ] Power the device off → after >15 min the admin shows **offline**.
- [ ] Power on → within one report cycle it returns to **online**.
- [ ] Wrong `token` on the webhook → `401` (no ingestion). (already covered by tests)
- [ ] Unknown IMEI forwarded → ignored, no error. (already covered by tests)

## Sign-off
- [ ] Device reliably flips online/offline in the admin panel across a power cycle.
- [ ] No relay/open/whitelist code was added (scope guard held).

---

### The one thing to do together now (before any of the above)
Send this SMS to **+994517371021** once the GT06 port is open, then we watch the
Traccar log for the login:
```
SERVER,1,gps.salamheyetimiz.com,5023,0#
```
