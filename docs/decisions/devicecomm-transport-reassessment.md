# Decision: DeviceComm transport reassessment for UMKa 310 v2L hardware

**Status:** Accepted — 2026-06-14. Transport roles finalised in §8 from the project owner's UX answers. Implementation (frozen-doc revision + 09-B re-scope) awaits an explicit go-ahead.
**Trigger:** The project owner supplied the actual field hardware — **GLONASSSoft UMKa 310 v2L** (telematics tracker), IMEI example `868184062169571` — replacing the previously-assumed GSM relay-controller class (the `RTU5024` seeded in batch 04).

**Reference sources (owner-supplied):**
- https://glonasssoft.ru/ru/equipment/umka310new
- https://qr-service.ru/assets/files/310/rukovodstvoumkaen310.pdf
- https://qr-service.ru/assets/files/dop/dop_k_umka310.pdf

**Owner-confirmed device facts:** on-device `cmdout.p` script pulses an output for 1 second; a BLE-triggered output script exists; supports Wialon IPS and Wialon Combine; configurable via configuration software and via SMS; Traccar is under consideration as the telemetry/command platform.

---

## 1. Core finding — the device class changed

The current DeviceComm design (`ClipDriver`, voice gateway, per-operator caller-ID validation, an on-device **whitelist of phone numbers**, "an authorised number calls the device SIM → relay pulses") was built for a **GSM relay controller** (RTU5024 class). Authorisation in that model lives **on the device** (caller-ID match).

The UMKa 310 is a **telematics tracker**: it opens a persistent **outbound data connection** (Wialon IPS/Combine over GPRS/LTE) to a telematics server, exposes discrete outputs (the relay), and runs an **on-device scripting engine** (`cmdout.p`, BLE script). It does **not** open on an incoming caller-ID. The barrier is actuated by a **command** (from the server over the live session, from a local BLE trigger, or via SMS) that runs `cmdout.p`.

**Consequence:** the CLIP / voice-gateway / on-device caller-ID-whitelist model is **the wrong paradigm** for this hardware. Open authorisation must live in the **platform**, not the device.

---

## 2. Evaluation of the four candidate transports

| # | Transport | Verdict | Rationale |
|---|---|---|---|
| 1 | Direct GSM/SMS | **Fallback only** | UMKa supports SMS command/config, but latency is high (tens of seconds), per-message cost, weak delivery confirmation, one-directional. Fails the p95 ≤ 5 s open target. CLIP does not apply to this device at all. Keep only as the offline last resort. |
| 2 | Direct UMKa command interface (we operate a Wialon server) | **Reject** | Building/operating a Wialon IPS/Combine TCP server — sessions, reconnect, keepalive, NAT, the binary Combine protocol — reinvents a telematics platform. High build + operational risk, out of scope for an access-control team. |
| 3 | **Traccar API** | **Recommended (remote/command + telemetry layer)** | Open-source telematics server that already speaks Wialon IPS **and** Combine; manages device sessions, online/offline, telemetry, a **command API** (custom/output command → `cmdout.p`), and event forwarding (webhooks). We integrate over clean HTTP REST and never build the socket/protocol layer. Self-hostable, no licensing cost. |
| 4 | Wialon infrastructure | **Reject for MVP** | Most native to the device, but commercial licensing (per-device cost), vendor lock-in, heavyweight SaaS, data-residency concerns. Overkill for barrier control; Traccar provides the same protocols free/self-hosted. Retain only as a future managed-platform alternative. |

### Fifth path the hardware implies — **BLE local open**
The device ships with a BLE-triggered output script, and for a barrier the user is almost always **physically present**. A BLE local open is the **simplest and most reliable** in-person path: sub-second, **no network/server dependency**, works in low-signal basements/garages where barriers commonly sit. The phone replaces the physical remote — the standard model for modern gate systems.

---

## 3. Recommended production architecture — hybrid: BLE-first + Traccar + SMS fallback

```
A) In-person open — PRIMARY (BLE):
   Mobile app (biometric + entitlement checked in-app)
   → BLE → UMKa BLE script → cmdout.p → 1 s relay pulse → barrier opens
   → app reports the open to the backend afterwards (audit + entitlement reconciliation)

B) Remote / guest open — SECONDARY (Traccar):
   Mobile tap → Salam backend (auth, subscription, cooldown, audit — UNCHANGED)
   → Traccar REST: custom/output command to the device
   → Traccar relays it over the live Wialon session → cmdout.p → 1 s pulse → opens
   → output state / command ack → Traccar forward → backend (confirmation)

C) Offline fallback (SMS):
   Backend → SMS provider → device SMS command → cmdout.p (slow; last resort)
```

"Simplest to build/operate" = **Traccar** (no protocol server of our own). "Most reliable at the barrier" = **BLE** (no network dependency). **Direct GSM-modem integration is not required.**

---

## 4. Impact on the implemented codebase (batches 08 / 09-A)

**Keep — the control plane is correct; the driver abstraction is vindicated:**
- Salam backend as the auth / subscription / cooldown / audit control plane — unchanged.
- `open_commands` lifecycle (queued → dispatching → dispatched/opened/failed/expired), attempts, feedback, stats, idempotency, cooldown, command queue, status polling — all retained; states map cleanly onto Traccar/BLE outcomes.
- The `DeviceDriver` interface + `DriverResolver` + dispatch/fallback machinery — exactly why batch 09-A built "driver interfaces only" (R-GSM-01). The driver becomes `TraccarDriver` / `BleProvisioningDriver` instead of `ClipDriver`. **No core change** — the seam pays off.
- `OpenCommandPolicy`, R-DOM-05 open-permission rule.

**Change / re-evaluate:**
- Driver taxonomy `clip / clip_sms / mqtt` → `traccar` (primary), `sms` (fallback), `ble` (local). Update `DriverType` enum + `config/domain/device_comm.php`. **This touches frozen docs → requires a doc revision** (permitted because the owner supplied new hardware facts).
- `driver_confirms_actuation` (CRIT-06): now legitimately **`true`** — Traccar/the device report discrete-output state / command ack, so actuation is observable and the terminal `opened` state is reachable. The CLIP "cannot confirm" limitation is **resolved**.
- `expected_completion_ms` baselines re-derived for Traccar/BLE.

**Drop / de-scope (major simplification and de-risking):**
- Voice-gateway infrastructure (two gateways, health-aware round-robin, circuit breaker — CRIT-03 / R-GSM-05). Not needed.
- CLIP driver + per-operator caller-ID validation (**CRIT-01**, Phase-0 G1). The project's #1 risk — operators rewriting caller ID — **evaporates**.
- The on-device phone-number whitelist for opening (the CLIP semantics of `whitelist_changes`).

**Re-purpose:**
- `whitelist_changes` outbox → a **device-config / BLE-credential provisioning outbox** (push authorised BLE identities to the device), or drop it from the open path entirely. Phase-0 decision.

---

## 5. Most important new design decision — BLE open vs. server-time entitlement

R-DOM-05 currently assumes a **server round-trip per open** (active subscription checked at open time). A BLE local open has **no server round-trip**. Therefore:
- The backend must issue a **time-boxed BLE entitlement / credential** (e.g. valid N hours/days); the app stores it, enforces **biometric + entitlement in-app** at open time (R-SEC-04 retained), and the open is **reconciled/audited asynchronously**.
- When the entitlement expires the app must refresh it from the backend → subscription/access stays server-authoritative, but **periodically** rather than in real time.
- BLE security: **authenticated/rolling credentials** (never static) to resist BLE relay attacks.

This is the single most consequential change: "open = real-time server authorisation" becomes "open = pre-granted, time-boxed, asynchronously reconciled" for the BLE path.

---

## 6. Phase-0 validation gates (replacing the CLIP gates)

The old G1 (per-operator CLIP validation) is **retired**. New gates:
1. **Traccar → UMKa command:** empirically confirm a Traccar `custom`/output command runs `cmdout.p` over the live session and measure latency (target 1–3 s), using the command syntax in the UMKa manual / `dop` docs.
2. **BLE provisioning + security model:** how the device decides which BLE identity may trigger the output → defines whether/what the provisioning outbox pushes.
3. **SMS command fallback:** syntax, cost, latency.
4. **Output-state read-back:** confirm actuation is observable (→ `opened` becomes real).
5. **Session durability:** measure how reliably the device holds a Wialon session in the deployment's coverage.

---

## 7. Recommendation

- **Halt / re-scope the GSM-driver batch (09-B):** reframe it as **"`TraccarDriver` + SMS-fallback driver + BLE provisioning"**, not "CLIP/SMS/voice-gateway".
- Adopt **Traccar** as the remote-command + telemetry layer (option 3), **BLE** as the in-person primary open, **SMS** as fallback; reject options 2 and 4.
- **Formally revise the frozen docs** (driver enum; CRIT-01 / CRIT-03 / R-GSM-05 status; the R-DOM-05 BLE exception) — an authorised exception to "generate implementation only / never redesign," because the owner supplied new hardware information.
- **Do not discard the batch 09-A core** — it is the correct control plane; only the concrete driver implementation changes.

---

## 8. Finalised transport decision (project-owner UX answers, 2026-06-14)

**Answers given:**
1. Primary use case → **Both equally important** (in-person *and* remote/guest).
2. Guest access / remote opening → **Yes, a core feature.**
3. Traccar as required infrastructure → **No preference** (deferred to the architect's recommendation).

**Determined architecture — HYBRID (not optional, required):**

Because remote/guest opening is a **core** feature, a **server-mediated command path is mandatory** — guest/remote opening is physically impossible over BLE (proximity-only). Because in-person opening is equally important, BLE remains the best in-person path. The two are therefore complementary, not alternatives.

| Path | Role | Transport |
|---|---|---|
| In-person open (resident at the barrier) | **Primary** | **BLE** local trigger → BLE script → `cmdout.p` → 1 s pulse. Sub-second, offline-capable, no server round-trip. |
| Remote / guest open (from anywhere) | **Primary for this case** | **Traccar** REST → custom/output command → live Wialon session → `cmdout.p`. Server-mediated; this is the path that makes guest/remote possible. |
| Device offline from Traccar | **Fallback** | **SMS** command → `cmdout.p`. Degraded; last resort. |

**Traccar is hereby a REQUIRED infrastructure component** (Q3 "no preference" → adopt the recommended option). It is self-hosted, speaks Wialon IPS/Combine natively, and avoids both building our own protocol server (option 2) and Wialon SaaS licensing (option 4).

**This finalises §1–§7.** Confirmed downstream consequences:
- Driver taxonomy → `traccar` (remote primary), `ble` (in-person primary), `sms` (fallback). `clip` / `clip_sms` / `mqtt` retired from the open path.
- CRIT-01 (operator caller-ID rewrite) and CRIT-03 (voice-gateway HA) are retired.
- `driver_confirms_actuation` can be `true` (Traccar/output-state read-back; BLE script ack) — CRIT-06 resolved.
- R-DOM-05 gains a **BLE exception**: in-person BLE opens use a backend-issued, time-boxed entitlement enforced in-app (biometric + entitlement) with asynchronous reconciliation; the remote/Traccar path keeps the real-time server check.
- Batch 09-A core (control plane, `open_commands` lifecycle, `DeviceDriver` seam) is retained unchanged.

**Pending explicit go-ahead before implementation:** (a) revise the frozen docs (driver enum; CRIT-01/CRIT-03/R-GSM-05 status; R-DOM-05 BLE exception); (b) re-scope batch 09-B as **`TraccarDriver` + BLE provisioning + SMS-fallback driver**.

---

*Architecture reassessment + finalised transport decision. No code generated. Frozen-doc revision and 09-B re-scoping await an explicit go-ahead.*
