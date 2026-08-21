# FINAL TRANSPORT DECISION — DeviceComm (UMKa 310 v2L)

**Status:** APPROVED — 2026-06-14 (project owner). Authoritative. Supersedes the CLIP/GSM-relay
transport assumptions throughout the v1.1 specification corpus.
**Supersedes / promotes:** `docs/decisions/devicecomm-transport-reassessment.md` (Accepted §8).
**Companion documents:** `docs/DOCUMENT_CHANGE_PLAN.md` (what must be revised), `docs/BATCH_09B_SCOPE.md` (what gets built).
**No code is changed by this document.** It is the decision of record that authorises the doc revisions and the 09-B re-scope.

---

## 1. Confirmed hardware

**GLONASSSoft UMKa 310 v2L** — a GPS/GLONASS **telematics tracker** (IMEI example `868184062169571`),
not a GSM relay controller. It opens an **outbound** data session to a telematics server over Wialon
IPS / Wialon Combine, exposes discrete outputs (the relay), and runs an on-device scripting engine.

Owner-confirmed capabilities: on-device `cmdout.p` pulses an output for 1 second; a BLE-triggered
output script exists; supports Wialon IPS and Wialon Combine; configurable via configuration software
and SMS; Traccar accepted as the platform.

This replaces the previously-assumed **King Pigeon RTU5024** (a caller-ID GSM relay controller — see
`database/seeders/Lookups/DeviceModelSeeder.php`) around which the original CLIP design was built.

## 2. Confirmed product decisions (verbatim)

1. **Primary use cases:** local/in-person opening is important; remote opening is also important;
   **both are first-class product requirements.**
2. **Guest access & remote opening:** **YES — a core product feature.** Users must be able to open
   gates/barriers remotely for guests, family, couriers and service personnel.
3. **Infrastructure:** **Traccar is accepted as a mandatory infrastructure component;** self-hosted
   Traccar is acceptable.

## 3. Approved transport architecture — HYBRID

Because remote/guest opening is a **core** feature, a server-mediated command path is **mandatory**
(remote opening is physically impossible over BLE alone — BLE is proximity-only). Because in-person
opening is equally first-class, BLE is the best path for it. The two are **complementary, not
alternatives.**

| Open path | Role | Transport | Notes |
|---|---|---|---|
| **Local / in-person** (resident at the barrier) | **Primary** | **BLE** | App → BLE → on-device BLE script → `cmdout.p` → 1 s relay pulse. Sub-second, offline-capable, no server round-trip. |
| **Remote** (incl. guest/family/courier/service) | **Primary for this case** | **Traccar** | Backend → Traccar REST command → live Wialon session → `cmdout.p`. Also the source of telemetry, online/offline status, and command confirmation. |
| Device offline from Traccar | **Emergency fallback only** | **SMS** | Backend → SMS command → `cmdout.p`. Degraded latency/cost; last resort. |

All three converge on the same device action: **command → `cmdout.p` → 1-second relay pulse → barrier opens.**

## 4. Barrier-opening flow (per path)

```
LOCAL (BLE) — primary:
  Mobile app (biometric + valid time-boxed entitlement, checked in-app)
  → BLE → UMKa BLE script → cmdout.p → 1 s pulse → barrier opens
  → app posts the open to the backend afterwards (audit + entitlement reconciliation)

REMOTE (Traccar) — primary for remote/guest:
  Mobile tap → Backend (auth, subscription, cooldown, audit — real-time check)
  → Traccar REST: custom/output command to the device
  → Traccar live Wialon session → cmdout.p → 1 s pulse → barrier opens
  → output-state / command ack → Traccar event forward → backend (confirmation → opened)

FALLBACK (SMS) — device offline from Traccar:
  Backend → SMS provider → device SMS command → cmdout.p (slow, last resort)
```

## 5. Authorization model

- **Control plane is unchanged.** The Salam backend remains the single authority for auth, subscription
  entitlement (R-DOM-05), cooldown, idempotency, audit, and command lifecycle.
- **Remote (Traccar) path keeps the real-time server check** — exactly as built in 09-A: the open is
  authorised at request time before a command is sent.
- **Local (BLE) path is the documented exception (R-DOM-05 amendment):** the backend issues a
  **time-boxed entitlement / credential** to the app; the app enforces **biometric + entitlement
  in-app** at open time (R-SEC-04 retained) and the open is **reconciled and audited asynchronously**.
  Entitlements expire, forcing periodic server re-validation → subscription/access stays
  server-authoritative, just not real-time for the local path. BLE credentials are
  **authenticated/rolling** (never static) to resist relay attacks.

## 6. Rejected alternatives

| Option | Verdict | Why |
|---|---|---|
| Direct GSM/SMS as primary | Rejected (kept only as emergency fallback) | High latency, per-message cost, weak confirmation; cannot meet a fast remote-open expectation. CLIP does not apply to this device. |
| Build our own Wialon IPS/Combine server | Rejected | Reinvents a telematics platform (sockets, sessions, Combine binary protocol); high build + ops risk. |
| Wialon SaaS | Rejected for MVP | Per-device licensing, vendor lock-in, heavyweight; Traccar gives the same protocols self-hosted. |

## 7. Consequences

- **Retired:** CLIP driver, voice gateway, `VoiceGatewaySelector` + circuit breaker, per-operator
  caller-ID (CLI) validation, and the on-device phone-number whitelist for opening.
- **Risks eliminated:** **CRIT-01** (operators rewriting caller ID — the project's #1 risk) and
  **CRIT-03** (voice-gateway HA) no longer apply.
- **CRIT-06 resolved:** with Traccar output-state read-back (and BLE script ack), `driver_confirms_actuation`
  can legitimately be `true`; the terminal `opened` state becomes reachable for real opens.
- **Driver taxonomy:** `clip / clip_sms / mqtt` → **`traccar` (remote), `ble` (local), `sms` (fallback)`.**
- **Performance targets** re-baselined (BLE sub-second local; Traccar ~1–3 s remote on a live session).
- **Phase-0 gates change:** the CLIP-per-operator gate (G1) is retired; new gates validate
  Traccar→`cmdout.p`, BLE provisioning/security, latency, and SMS fallback (see `BATCH_09B_SCOPE.md` §6).

## 8. What is preserved

- The **control plane** (backend as authorisation/billing/audit authority).
- **Batch 09-A core in full** — `open_commands` lifecycle + state machine, attempts, feedback, stats,
  cooldown, idempotency, command queue, status polling, the whitelist outbox table, and crucially the
  **`DeviceDriver` interface + `DriverResolver` seam**. The new transports plug into this seam with no
  core change — the reason 09-A was built "driver interfaces only" (R-GSM-01). Detail in
  `BATCH_09B_SCOPE.md` §3.

---

*Approved transport decision of record. No code generated. The doc revisions in `DOCUMENT_CHANGE_PLAN.md`
and the 09-B re-scope in `BATCH_09B_SCOPE.md` are authorised by this decision but await the owner's
explicit "proceed" before execution.*
