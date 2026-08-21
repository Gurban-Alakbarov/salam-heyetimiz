# VL110C — Traccar Configuration (audit + required change)

> Scope: **online connectivity only**. No relay/open/whitelist. This documents the
> **current** Traccar setup (audited from the deploy scripts) and the **minimal
> change** needed so a GT06 device (VL110C) can connect. **Nothing is applied yet —
> plan for approval.**

## A. Current state (audited)

| Item | Value | Source |
|---|---|---|
| Traccar | `traccar/traccar:6.14.5` (Docker) + `mariadb:11.4` | `deploy/phaseE_traccar.sh` |
| Compose file | `/opt/salam-traccar/docker-compose.yml` | phaseE §E2 |
| Config file | `/opt/salam-traccar/traccar.xml` | phaseE §E2 |
| **Published ports** | `127.0.0.1:8082:8082` (UI, localhost only) · **`5011:5039`** (public device port → container **5039 = Wialon** decoder) | phaseE `ports:` |
| Device-facing host | **`gps.salamheyetimiz.com`** (→ VPS `185.208.206.174`) | phaseE comment + prod deploy memory |
| Forward webhook | `forward.enable=true`, `forward.url = http://host.docker.internal/v1/traccar/forward?token=<TRACCAR_FORWARD_TOKEN>`, `forward.type=json` (**positions only**; no `event.forward.url`) | traccar.xml |
| Firewall (UFW) | open: `22`, **`5011`**, `80/443` (Cloudflare only), docker→`80`. Default deny incoming. | `deploy/phaseH_ufw.sh` |

## B. Answers to the 10 audit questions

1. **Docker compose** — `/opt/salam-traccar/docker-compose.yml`, two services
   (`traccar`, `traccar-db`), single network `traccarnet`.
2. **Open protocol ports** — only **host 5011** publicly (→ container 5039, Wialon)
   and 8082 on localhost. No other device port is published.
3. **GT06 decoder active?** — Traccar enables **all** protocol decoders by default
   inside the container, so the **GT06 decoder is running on its default container
   port 5023** — but it is **not published to the host**, so it is unreachable from
   the internet today.
4. **Which port for GT06** — Traccar's default GT06 port is **`5023`**. We publish
   `host 5023 → container 5023`. (5011 is taken by Wialon; keep GT06 on its own
   port.)
5. **Firewall allows it?** — **No.** UFW opens only 22/5011/80/443. A rule
   `ufw allow 5023/tcp` is required.
6. **Laravel receives Traccar webhooks?** — **Yes.** `POST /v1/traccar/forward`
   (`TraccarForwardController`), gated by a shared token (query `token` or header
   `X-Traccar-Token`), constant-time compared.
7. **Webhook works?** — Yes; validates token, calls `TraccarIngestionService.ingest`,
   always returns 200 (avoids Traccar retry storms). Covered by
   `TraccarForwardWebhookTest` + `TraccarIngestionTest`. Note it forwards
   **positions** only (no separate event forward).
8. **`devices:reconcile-traccar` sufficient?** — Yes to create the `devices` row +
   `traccar_devices` mapping, **but it ADOPTS an existing Traccar device** by
   `uniqueId/IMEI` (throws `TraccarDeviceNotFoundException` if the device isn't in
   Traccar yet). So the device must be **added in Traccar first** (uniqueId = IMEI),
   and a valid `--model=<device_model_id>` must exist.
9. **Admin online/offline calc** — `config/domain/devices.php`:
   **`offline_threshold_minutes = 15`** → a device is "online" only if
   `last_online_at` is within the last 15 minutes. (Separate legacy 24 h
   `offline_threshold_hours` is only for stale-device cleanup.)
10. **`last_online_at` update** — set by the forward webhook →
    `TraccarIngestionService.updateDeviceStatus()`: when a position arrives and the
    device is not `offline`, `last_online_at = position.deviceTime/fixTime`. So it
    updates **only when Traccar forwards a position** for the device.

## C. Required change (minimal, additive — to apply AFTER approval)

Two edits, both infra (no app code, no migration):

### C.1 Publish the GT06 port (compose)
In `/opt/salam-traccar/docker-compose.yml` `traccar.ports:` add:
```yaml
      - "5023:5023"   # GT06 decoder (Jimi VL110C) → container 5023
```
Then `cd /opt/salam-traccar && docker compose up -d` (recreates the container;
existing 5011/Wialon mapping unchanged).

> The GT06 decoder is already listening on container `5023`; we are only publishing
> it. No Traccar `.xml` change is required to *enable* GT06. (Optionally we can pin
> `<entry key='gt06.port'>5023</entry>` in `traccar.xml` to make it explicit.)

### C.2 Open the firewall (UFW)
```bash
ufw allow 5023/tcp comment 'Traccar GT06 (VL110C)'
```
(Mirror this in `deploy/phaseH_ufw.sh` so a re-provision keeps it.)

### C.3 (No change) Webhook + ingestion
Already correct — the same `forward.url` handles all protocols. Once the device is
mapped in `traccar_devices`, its forwarded positions flow straight into
`last_online_at`. No webhook/ingestion change needed for online status.

## D. Risks / decisions for online status

1. **Position-dependent `last_online_at` (main risk).** `last_online_at` updates
   only on a forwarded **position**. GT06 devices normally emit periodic
   location/LBS packets that Traccar turns into positions (even without a GPS fix),
   so forwards should fire. **But if the VL110C only logs in + heartbeats without
   producing a Traccar position, `last_online_at` will not advance even though
   Traccar shows the device online.** Mitigations to decide at approval:
   - (a) **Rely on GT06 LBS/location positions** (simplest; verify on the test unit).
   - (b) **Add a small scheduled "Traccar status pull"** (`GET /api/devices?uniqueId=…`
     → read `lastUpdate`/`status` → update `last_online_at`) for devices that don't
     emit positions. New read-only sync; no schema change. *(Recommended hardening.)*
   - (c) Enable Traccar `event.forward.url` so status/heartbeat events also forward.
2. **Device pre-registration in Traccar** is a manual prerequisite (add device with
   uniqueId = IMEI in the Traccar UI) before `reconcile-traccar`.
3. **Two device families on one Traccar** — Wialon (UMKa, 5011) + GT06 (VL110C, 5023)
   coexist as separate decoders/ports; both must be published + firewalled.
