# FINAL PHASE-0 VERDICT — DeviceComm Transport

**Date:** 2026-06-14
**Status:** Architecture verdict — **recommended decisions below; applying them to the frozen docs awaits the owner's "proceed".**
**Basis:** `PHASE0_TRANSPORT_VALIDATION.md`, `UMKA_COMMAND_REFERENCE.md`, `TRACCAR_INTEGRATION_STRATEGY.md`,
`BLE_FEASIBILITY_REPORT.md`, `INFRASTRUCTURE_REQUIREMENTS.md`, `SERVER_SIZING_GUIDE.md`.
**Effect:** amends `FINAL_TRANSPORT_DECISION.md` (which made BLE the primary local transport).

---

## The five questions — verdict

### 1. Should BLE-first be officially abandoned? → **YES.**
The UMKa 310's BLE is **iBeacon identification by UUID** (manual §2.22/§3.17, App. F, `BLEID` #107) — a
sensor/identification facility, not an authenticated phone→device command channel. It is **unauthenticated
(clonable/replayable)**, **iOS background-advertising-restricted**, and provisioned as device config. It
**cannot** serve as a secure primary local-open. **Abandon BLE-first.** BLE may return post-MVP only as an
optional, foreground-only convenience with the clone risk explicitly accepted — not on the critical path.

### 2. Should Traccar be the PRIMARY transport for both local and remote opens? → **YES** (production-gated by T1).
The UMKa exposes a documented native command — **`OUTPUT0=1` / `OUTPUT0=0`** (cmd #25) — deliverable over
GPRS, and Traccar supports a text **`custom`** command for Wialon devices with offline queueing. Both
in-person and remote opens become: *app tap → backend (real-time auth) → Traccar `custom` (`OUTPUT0` /
`cmdout.p`) → relay pulse.* This is simpler and more secure than the BLE-first split. **One empirical gate
remains (T1):** prove Traccar's Wialon framing executes `OUTPUT0` on a real device (see Blockers).

### 3. Should the R-DOM-05 BLE entitlement exception be removed? → **YES.**
With BLE off the critical path there is no offline local-open, so the **time-boxed-entitlement +
in-app-enforcement + async-reconciliation exception is removed**. **All opens (local + remote) use the
original real-time server check** (active subscription + roster + not disabled + cooldown). This restores
R-DOM-05 to a single, stronger rule and deletes the largest design unknown (B4). *(If BLE is ever revived
as a convenience, a narrowly-scoped exception would be re-introduced for that path only.)*

### 4. Should Batch 09-B be redefined to these six items? → **YES.**
`TraccarDriver` · Traccar **webhook ingestion** (event-forward) · Traccar **device mapping** · **actuation
confirmation** · **`SmsDriver` fallback** · **device diagnostics**. **`BleProvisioningDriver` is dropped**
from 09-B (deferred). Full plan: `BATCH_09B_IMPLEMENTATION_PLAN.md`.

### 5. Assumption status

| Assumption | Status | Evidence |
|---|---|---|
| UMKa has a remote output/open command | **PROVEN** (docs) | `OUTPUT0=1/0`, cmd #25, fw 0.12.8+ |
| Commands deliverable over GPRS + SMS | **PROVEN** (docs) | §2.17 (GPRS/remote-config), §3.22 (`AUTH`+cmd) |
| Wialon IPS + Combine are the protocols | **PROVEN** (docs) | `SETPROTOCOL` #30 |
| Traccar can send a text `custom` command + queue offline | **PROVEN** (Traccar docs) | Traccar commands/forums |
| **BLE as secure primary local-open** | **DISPROVEN** | iBeacon UUID = unauthenticated, iOS-restricted |
| **"BLE-first local" architecture** | **DISPROVEN** → abandoned | BLE feasibility report |
| Traccar's Wialon framing executes `OUTPUT0` on the device | **UNPROVEN — needs real device (T1, HARD)** | — |
| `cmdout.p` trigger over the wire | **UNPROVEN — needs device (SOFT; `OUTPUT0` fallback exists)** | no "run script" cmd in manual |
| Actuation read-back → `opened` | **UNPROVEN — needs device (SOFT; `dispatched` acceptable)** | OUT0 bit + `STATMASK` |
| Persistent session durability in coverage | **UNPROVEN — measure in pilot (SOFT)** | §3.20 permanent connection |
| SMS fallback latency/cost | **UNPROVEN — measure (SOFT/deferred)** | documented mechanism |

## Doc amendments to apply on approval

1. `FINAL_TRANSPORT_DECISION.md` — change roles: **BLE = deferred/optional**; **Traccar = primary for both
   local and remote**; SMS = fallback. Mark §3/§5 superseded by this verdict.
2. `PROJECT_CONSTITUTION.md` **R-DOM-05** — remove the v1.2 BLE exception; all opens use the real-time check.
   **R-GSM-01** — driver set effectively `traccar` (primary) + `sms` (fallback); `ble` reserved/deferred.
3. `BATCH_09B_SCOPE.md` / `BATCH_09B_READINESS.md` — supersede with `BATCH_09B_IMPLEMENTATION_PLAN.md`
   (BLE removed; B4 dropped).
4. CHANGELOG / TRANSPORT_MIGRATION_CHANGELOG — note the BLE-demotion amendment.

## Bottom line

The pivot's **control plane and the 09-A core are confirmed correct**. The single substantive change vs.
the last decision is **BLE demoted from primary to deferred**, which **reduces risk and complexity** and
makes **Traccar the single primary transport**. The only material open risk is **T1** (one on-device test);
everything else has a documented fallback. **09-B can begin now against a `FakeTraccarClient`** (the proven
07–09A pattern), with the live command string finalised by T1.
