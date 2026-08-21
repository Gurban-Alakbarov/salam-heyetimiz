# PHASE-0 TRANSPORT VALIDATION — UMKa 310 v2L

**Date:** 2026-06-14
**Objective:** determine whether **Traccar can reliably trigger `cmdout.p` / the relay** on UMKa devices,
and whether **BLE should remain the primary local-open** mechanism — before any batch 09-B code.
**Method:** full read of the UMKa 310 English manual + Traccar docs (desk validation); plus the on-hardware
tests defined in §4 (to be executed by the integrator/ops on a real UMKa + Traccar instance).
**Companion docs:** `UMKA_COMMAND_REFERENCE.md`, `TRACCAR_INTEGRATION_STRATEGY.md`, `BLE_FEASIBILITY_REPORT.md`.

---

## 1. Headline findings

1. ✅ **Remote open has a documented native command:** `OUTPUT0=1` (relay ON) / `OUTPUT0=0` (OFF),
   firmware 0.12.8+ (manual cmd #25). A 1-second pulse is the owner's on-device `cmdout.p`.
2. ✅ **Commands are deliverable over GPRS and SMS** (manual §2.17, §3.22). Traccar supports a `custom`
   text command for Wialon devices and queues commands when offline.
3. ⚠️ **The gating unknown:** whether `OUTPUT0=1` executes when delivered via **Traccar's** Wialon
   downlink framing (vs only via GLONASSSoft's remote-config server / SMS). One test decides it (§4, T1).
4. ⚠️ **`cmdout.p` trigger:** the base manual exposes **no generic "run script" command**; the exact way
   `cmdout.p` is invoked over the wire must be confirmed (likely `OUTPUT0=1` itself, or a paired off-command).
5. ❌ **BLE is NOT a secure primary local-open.** UMKa BLE is **iBeacon identification** (UUID match) —
   unauthenticated/clonable, iOS background-advertising-restricted. This **contradicts** the BLE-first
   assumption in `FINAL_TRANSPORT_DECISION.md`.

## 2. Assumption status

| Assumption (from the approved decision) | Status |
|---|---|
| Traccar can command the UMKa to open | **Likely ✅** via `custom`→`OUTPUT0`; **pending T1** on-hardware proof |
| Actuation can be confirmed (→ `opened`) | **Plausible ✅** via OUT0 bit in telemetry + `STATMASK`; **pending T3** |
| SMS fallback works | ✅ documented (`AUTH`+`OUTPUT0=1`); **pending T4** for latency/cost |
| **BLE is the primary local open** | ❌ **REJECTED** — not secure/feasible with native iBeacon mechanism |
| Self-hosted Traccar is the right command+telemetry platform | ✅ confirmed suitable |

## 3. Unknowns that could break Batch 09-B (ranked)

| ID | Unknown | If it fails | Mitigation |
|---|---|---|---|
| **U1** | Traccar/Wialon `custom` command not executed by the UMKa | Traccar can't open; only telemetry | Use GLONASSSoft remote-config API as command channel, or SMS-primary; both behind the `DeviceDriver` seam |
| **U2** | `cmdout.p` trigger-over-wire unknown | Can't get the clean 1 s pulse | Use `OUTPUT0=1` + delayed `OUTPUT0=0`; or extract the trigger from firmware/scenario doc |
| **U3** | No prompt actuation-confirming telemetry | Stuck at `dispatched`, never `opened` | Tune `STATMASK`/report interval; accept `dispatched` as terminal for CLIP-equivalent (R-GSM-03) |
| **U4** | Session not durable in deployment coverage | Remote opens slow/queued | Permanent-connection mode (§3.20); SMS fallback; siting/antenna |
| **U5** | BLE unusable as secure primary | In-person UX degrades to online-only | **Already mitigated:** make Traccar primary for in-person too (BLE report) |

## 4. On-hardware test plan (go/no-go gates)

Run on **one real UMKa 310 v2L** pointed at a **self-hosted Traccar**, with a test relay/LED on OUT0.

- **T1 — Traccar command → relay (PRIMARY GATE).** Send Traccar `custom` `OUTPUT0=1` (and `cmdout.p`
  trigger). **Pass:** relay actuates. Capture the exact accepted command string + Traccar protocol/port.
- **T2 — Latency.** Measure tap→pulse with the device in permanent-connection mode, 30 samples. **Target:**
  p95 ≤ 3 s. Record distribution.
- **T3 — Confirmation read-back.** Confirm the OUT0 state change appears in the next telemetry record and
  measure its latency; set `STATMASK` accordingly. **Pass:** backend can derive `opened`.
- **T4 — SMS fallback.** `AUTH <pw>` then `OUTPUT0=1` via SMS; measure latency + cost; verify inbound reply
  correlation.
- **T5 — Offline queue.** Take the device offline, send a Traccar command, bring it online; verify delivery
  behaviour (and that the backend does not present a stale "queued" open as success).
- **T6 — BLE (only if BLE is pursued).** Phone advertises the recognised iBeacon UUID; verify the script
  fires; characterise iOS foreground/background behaviour and document the clone risk.

**Artifacts:** commit results to `docs/phase0/transport-validation.md` (per R-WF-07) — command strings,
latency tables, Traccar config, verdicts.

## 5. Recommendations (require owner sign-off)

1. **Adopt Traccar as the PRIMARY transport for both remote and in-person opens** (pending T1). The mobile
   app issues an online open; the backend authorises in real time; Traccar fires `OUTPUT0`/`cmdout.p`.
   → This **keeps R-DOM-05's real-time server check for all opens** and **removes the R-DOM-05 BLE
   exception** from the critical path.
2. **SMS = fallback** (T4-gated).
3. **Demote BLE** to optional/deferred (foreground-only convenience, clone-risk accepted) — not MVP-critical.
   → This is a **change to `FINAL_TRANSPORT_DECISION.md`** (BLE was primary-local) and must be re-approved;
   it **simplifies** 09-B and eliminates the largest unknown (B4/U5).
4. **Do not start 09-B coding until T1–T3 pass** (T1 is the hard gate). T4/T5 can run in parallel; T6 only
   if BLE is pursued.
5. If T1 fails: fall back to the GLONASSSoft remote-config command channel or SMS-primary (decision tree in
   `TRACCAR_INTEGRATION_STRATEGY.md` §2) — the `DeviceDriver` seam absorbs either without core change.

## 6. Net effect on the plan

The architecture's **control plane and 09-A core remain correct**. The main revision is **BLE → optional**,
making **Traccar the single primary** — which is *simpler and more secure* than the BLE-first plan, and
fully supported by the confirmed hardware command (`OUTPUT0`). The remaining risk is concentrated in **T1**
(Traccar↔UMKa command execution); everything else has a documented fallback.
