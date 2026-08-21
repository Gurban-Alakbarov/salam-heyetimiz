# Audit — Port 21787 & Traccar Web UI Access

**Date:** 2026-06-26
**Method:** direct production-server checks (`185.208.206.174`) + the **official UMKa 310 manual**
(`rukovodstvoumkaen310.pdf`, ВБРМ.022.000.000, GLONASSSoft). Audit only — no code/DB/feature changes.
Only verified facts reported; no guessing.

---

## 1. Port 21787

### Production server — VERIFIED NOT USED
| Check | Result |
|---|---|
| Host TCP listening on 21787 | **NOT listening** (`ss -tlnp`) |
| Host UDP listening on 21787 | **NOT listening** (`ss -ulnp`) |
| Docker port mappings (traccar) | only `5039→0.0.0.0:5011` (Wialon) and `8082→127.0.0.1:8082` — **no 21787** |
| Listening inside the Traccar container | **NOT** (container ports are 5001–5267 + 8082; 21787 absent) |
| UFW firewall | **21787 not present** (rules: 22, 5011, 80/443→Cloudflare) |
| compose / traccar.xml | **no reference to 21787** |

### Official UMKa 310 manual — VERIFIED NOT A DOCUMENTED PORT
- Full-text search of the manual (4113 lines): **`21787` does not appear anywhere.** The only port literal in
  the manual is `:12358` (an example, unrelated).
- Server configuration is command **`SETSERV SERVER=D1:P1,D2:P2,D3:P3`** (cmd #7/#30): the operator enters the
  **telematics server domain + port** for up to **3 servers** (primary + 2 alternates). **No fixed/default
  port (and certainly not 21787) is mandated** — the port is whatever the destination server uses.
- A **separate** `REMCFG` service exists ("remote configuration service", domain `D` + port `P`) — this is
  **GLONASSSoft's own remote-config/cloud service**, distinct from the telematics data server.

### Conclusion — 21787 is NOT required; keep it CLOSED
- Our device→server telematics path is **Wialon IPS** to **`gps.salamheyetimiz.com:5011`** (→ Traccar wialon
  decoder on container 5039), already verified working. Traccar receives telematics **only** on its Wialon
  port — it never uses 21787.
- **21787 is not a Traccar port, not a documented UMKa port, and not used anywhere in our deployment.**
- If 21787 is meaningful at all, it is a **GLONASSSoft cloud / remote-config platform port** — i.e. a
  destination the device would dial **outbound** to GLONASSSoft's own servers, **not** an inbound port on our
  server. We host our own Traccar and do **not** use GLONASSSoft hosting, so it is irrelevant to us.
- **Action: do NOT open 21787 in our firewall.** Opening it would have no effect (nothing listens on it) and
  would only widen the attack surface. Keep it closed.
- **What the device actually needs from us:** the single Wialon endpoint `gps.salamheyetimiz.com:5011`
  (see `UMKA_CONFIGURATOR_CHECKLIST.md`). The device may additionally keep GLONASSSoft's own server as a 2nd/3rd
  `SETSERV` target if desired — that traffic goes to GLONASSSoft, not to us, and needs no firewall change here.

> **Honest limit:** I could not find an authoritative statement of *what exactly* 21787 is, because it is not
> in the UMKa manual and not in our config. What I can state as **verified** is: it is **not used by us** and
> **not required** for the UMKa→our-Traccar integration. Treat any "21787" instruction as belonging to a
> GLONASSSoft-hosted setup, which is not our architecture.

## 2. Traccar Web UI — credentials (VERIFIED WORKING)

| Item | Value |
|---|---|
| **URL** | `https://traccar.salamheyetimiz.com/login` (also reachable internally at `127.0.0.1:8082`) |
| **Admin email/username** | `admin@salamheyetimiz.com` |
| **Password** | `AUGA5w6UTE092GSZypW3oEwZ` |
| **Password storage** | `/root/salam_secrets.env` → `TRACCAR_ADMIN_PASSWORD` (mode 600, root-only). Randomly generated at provisioning (`deploy/phaseE2c_token.sh`). Not a Docker secret. |
| **Account** | the **only** Traccar user: `tc_users` id=1, `administrator=1` ("Salam Admin") |

### Verification (login actually works — tested both paths)
- **Local** (`POST 127.0.0.1:8082/api/session`): **HTTP 200**, returns `{"id":1,"name":"Salam Admin",
  "email":"admin@salamheyetimiz.com","administrator":true,…}`.
- **Public** (`POST https://traccar.salamheyetimiz.com/api/session` via Cloudflare): **HTTP 200**, same admin
  user. ✅ The credentials work at the expected URL.

> The Traccar UI is publicly reachable (password-protected, behind Cloudflare). Recommendation (not done):
> add Cloudflare Access / IP allowlist for `traccar.salamheyetimiz.com`, and rotate this password if it has
> been shared widely.

---

**Sources:** [Official UMKa 310 manual (rukovodstvoumkaen310.pdf)](https://qr-service.ru/assets/files/310/rukovodstvoumkaen310.pdf) ·
[UMKa310 on Wialon](https://wialon.com/en/gps-hardware/auto/umka310) ·
[UMKa310 on flespi](https://flespi.com/devices/umka310) ·
[Configurator UMKa3XX (Google Play)](https://play.google.com/store/apps/details?id=ru.glonasssoft.configurator3xx)
