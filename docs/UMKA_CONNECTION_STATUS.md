# UMKa 310 v2L — Server-Side Verification Report

**Date:** 2026-06-26
**Device:** UMKa 310 v2L · **IMEI:** `868184062169571` · SIM active, mobile Internet confirmed by owner.
**Method:** direct checks on production (`185.208.206.174`) over SSH + tests from an external machine. **No
command sent to the device. No OUTPUT0 / cmdout.p / barrier. No backend code, no app-DB change, no features.**
Only facts I personally verified are reported.

---

## Verdict

- **Device is NOT yet connected to our Traccar** (not registered; only my own synthetic test logins seen).
- **A real server-side bug was found and fixed:** our exposed port **5011 was the SUNTECH decoder, not
  Wialon**. The real Wialon port is **5039**. I remapped host `5011 → container 5039` and **proved**, from an
  external machine, that a Wialon login now reaches Traccar's **wialon** decoder.
- After the fix, the **server side is ready**; the remaining step is **device configuration** (owner).

## 10-point verification

| # | Check | Result | Evidence (verified) |
|---|---|---|---|
| 1 | Connected to the production server | **VERIFIED** | SSH as `root@vmi3387750`, host IP `185.208.206.174` |
| 2 | Verified directly on the server (not only localhost) | **VERIFIED** | external-interface bindings inspected on the box; plus tests run **from my own machine** to the public host |
| 3 | Inspected Traccar container logs | **VERIFIED** | read `/opt/traccar/logs/tracker-server.log*`; saw protocol-tagged connection lines (suntech, wialon) |
| 4 | Inspected Laravel logs | **VERIFIED** | `storage/logs/laravel.log` = **0 bytes** (no app errors/exceptions; nothing logged) |
| 5 | Inspected nginx logs | **VERIFIED** | `access.log`/`error.log`: only WP-scanner probes (403 by rule), admin/Traccar UI hits; **no `/v1/traccar/forward` hits** (no real forwards yet) |
| 6 | Inspected active TCP connections | **VERIFIED** | 90 established; my external test IP `188.253.216.26` appeared on the device port |
| 7 | Port 5011 listening externally | **VERIFIED** | `LISTEN 0.0.0.0:5011` + `[::]:5011` (docker-proxy) — not localhost-only |
| 8 | From outside, `gps.salamheyetimiz.com:5011` reachable | **VERIFIED** | from my machine: DNS → `185.208.206.174` (DNS-only), `TCP_CONNECT=OK` |
| 9 | Traccar can receive **Wialon IPS** traffic | **VERIFIED (after fix)** | see "Port fix" below — external Wialon login now decoded as `wialon`, IMEI parsed |
| 10 | Webhook Traccar ↔ Laravel working | **VERIFIED (path)** · **CANNOT VERIFY (real end-to-end)** | container → `host.docker.internal/v1/traccar/forward` with the correct token → `{"status":"ok"}` (200); token identical in secrets/.env/traccar.xml. A **real auto-forwarded position** can't be verified until the device connects |

## The port fix (root cause)

**Empirical proof (no assumption):**
- 5011 decoder: `[T5f43fa92: suntech < 188.253.216.26] #L#868184062169571;NA` → **suntech**, not wialon.
- Marker scan (sent `#L#888<port>` to ports 5001–5267): the **`wialon`** decoder logged marker `8885039` →
  **Wialon IPS port = 5039**.
- Confirmation with the real IMEI on 5039: `[wialon < …] #L#868184062169571` → `WARN: Unknown device -
  868184062169571` (correct: parsed as Wialon, just not registered).
- Note: Traccar 6.14.5 ships **no `default.xml`** (in the jar or container) — port defaults are compiled in
  `org/traccar/config/Keys.class`, so the mapping could only be confirmed **empirically**, which I did.

**Fix applied (server-side infra; not backend code/feature):** changed the Traccar compose mapping
`"5011:5011"` → **`"5011:5039"`** and recreated the container (traccar-db untouched). Host `5011`, UFW, and
`gps.salamheyetimiz.com` are unchanged — the device still uses **5011**, which now reaches the Wialon decoder.

**Post-fix external proof:** from my machine, Wialon login to `gps.salamheyetimiz.com:5011` →
`[T98e29d3f: wialon < 188.253.216.26] #L#868184062169571` → `Unknown device - 868184062169571`. ✅

## What this proves / what remains

- ✅ Our public endpoint `gps.salamheyetimiz.com:5011` reaches Traccar's **Wialon IPS** decoder, end-to-end,
  and correctly parses the device's IMEI. The webhook path to Laravel works.
- ⏳ The device itself has **not connected** — it must be pointed at our server in the UMKa Configurator.
- I did **not** register the device in Traccar (registering won't make it connect; the device must be
  configured first). All my tests were synthetic logins; **no DB rows were created** (devices = 0,
  device_diagnostics = 0 before and after).

## What I did NOT verify (honest gaps)

| Item | Status | Why |
|---|---|---|
| Whether the real device is transmitting at all | **NOT VERIFIED** | it has never connected to us; cannot observe its traffic |
| The device's APN / data session | **CANNOT VERIFY** | not observable from our server until the device reaches us (owner states data is active — I cannot confirm or deny it from here, so I make no claim) |
| Real auto-forward (Traccar → Laravel) with a live position | **CANNOT VERIFY** | requires the device online + registered |
| OUTPUT0 command actuation (HB1) | **NOT TESTED — out of scope** | destructive; separate gated test |

Next: device-side configuration — see **`UMKA_CONFIGURATOR_CHECKLIST.md`**.
