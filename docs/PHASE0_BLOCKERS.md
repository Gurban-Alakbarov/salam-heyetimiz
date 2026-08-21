# PHASE-0 BLOCKERS — classified

**Date:** 2026-06-14
**Legend:** **HARD** = must clear before the relevant milestone (cannot be worked around). **SOFT** = has a
documented fallback; resolve during/around 09-B. **DEFERRED** = not on the MVP critical path.
**Key framing:** 09-B **code** is built against a `FakeTraccarClient` (the proven 05–09A fake pattern), so
the HARD blockers below gate **production go-live / finalising the live command string**, **not** the start
of coding. This is stated explicitly per blocker.

---

## HARD blockers

### HB1 — Traccar `custom` command executes `OUTPUT0` on a real UMKa (test T1)
- **Why hard:** decides whether Traccar is the *command* channel or only the *telemetry* channel. The
  entire remote/primary open path depends on it.
- **Gates:** production go-live and the final command string. **Does NOT gate** writing `TraccarDriver`
  against the fake (the command string is parameterised from `UMKA_COMMAND_REFERENCE.md` = `OUTPUT0=1`).
- **Clears when:** T1 passes on one real UMKa + self-hosted Traccar. **If it fails:** switch the command
  channel to the GLONASSSoft remote-config API or SMS-primary (decision tree, `TRACCAR_INTEGRATION_STRATEGY.md` §2)
  — absorbed by the `DeviceDriver` seam with no core change.

### HB2 — A self-hosted Traccar instance + ≥1 real UMKa provisioned to it
- **Why hard:** prerequisite for HB1/T1 and for any end-to-end pilot.
- **Gates:** HB1 and pilot. **Does NOT gate** coding (fake client).
- **Clears when:** Traccar stood up (see `INFRASTRUCTURE_REQUIREMENTS.md`) and a device is pointed at it via
  `SETSERV`/`SETPROTOCOL`/configurator.

## SOFT blockers (documented fallback exists)

### SB1 — `cmdout.p` trigger over the wire
- **Fallback:** `OUTPUT0=1` then a backend-scheduled `OUTPUT0=0` ~1 s later achieves the pulse without the
  on-device script. **Clears when:** the trigger is extracted from the firmware/scenario doc or confirmed in T1.

### SB2 — Actuation read-back → `opened`
- **Fallback:** terminate at `dispatched` (R-GSM-03 permits this; `opened` is an enhancement). **Clears when:**
  T3 confirms the OUT0 bit appears in telemetry and `STATMASK` is tuned.

### SB3 — SMS provider selection + command syntax/cost (fallback path)
- **Fallback:** `SmsDriver` uses the documented `AUTH <pw>` + `OUTPUT0=1`; provider is the same Phase-0 SMS
  item already open for OTP. **Clears when:** provider chosen and T4 measures latency/cost.

### SB4 — New schema/design at 09-B start
- Device-mapping table (our id ↔ Traccar uniqueId/IMEI), `device_diagnostics` table (DB Arch §6.2), the
  `DriverType` enum change (`clip_sms…` → `traccar/sms`). **Not external** — these are the **first 09-B
  tasks**, sequenced (`DriverType` change is the breaking first commit). Listed as a blocker only to flag
  ordering.

## DEFERRED (off the MVP critical path)

| ID | Item | Rationale |
|---|---|---|
| D1 | **BLE** entirely (`BleProvisioningDriver`, entitlements, reconciliation, R-DOM-05 BLE exception) | Disproven as secure primary; revisit post-MVP only as foreground convenience. Removes the largest unknown (former B4). |
| D2 | Persistent-session durability in real coverage | Operational/siting; measure in pilot, not a code dependency. |
| D3 | Reverb WebSocket broadcast of `OpenCommandCompleted` | Optimisation; `/v1/commands/{id}` polling is the contract (R-ARCH-12). |
| D4 | GLONASSSoft remote-config API command channel | Only needed if HB1/T1 fails. |
| D5 | `techDiagnosticsPing` synchronous probe | Build after telemetry ingestion is proven; list-history (`adminDeviceDiagnostics`) ships in 09-B. |

## Readiness conclusion

- **To start 09-B coding (against `FakeTraccarClient`):** **no HARD blocker** — proceed; do SB4 first.
- **To go to production / pilot:** **HB1 + HB2** must clear (run T1–T3 on real hardware), and SB1–SB3 resolved.
- **Out of scope for MVP:** all DEFERRED items, BLE foremost.
