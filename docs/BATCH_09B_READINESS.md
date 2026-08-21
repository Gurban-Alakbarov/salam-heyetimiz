# BATCH 09-B READINESS ASSESSMENT

**Date:** 2026-06-14
**Subject:** readiness to implement batch 09-B (Traccar + BLE + SMS transport drivers) after the v1.2
documentation alignment.
**Inputs:** `FINAL_TRANSPORT_DECISION.md`, `DOCUMENT_CHANGE_PLAN.md`, `BATCH_09B_SCOPE.md`,
`TRANSPORT_MIGRATION_CHANGELOG.md`, `SPEC_CONSISTENCY_REPORT.md`.

**Verdict:** **Documentation-ready; implementation-blocked on Phase-0 empirical validation + two
infrastructure/sequencing prerequisites.** Do not begin 09-B coding until B1–B4 below are cleared.

---

## 1. What is ready ✅

- **Spec corpus aligned to v1.2** across Constitution, TechSpec, DBArch, BackendArch, OpenAPI; cross-refs
  resolve; OpenAPI validates green; CRIT-01/03 retired, CRIT-06 resolved consistently
  (`SPEC_CONSISTENCY_REPORT.md`).
- **09-A core stands valid and unchanged** — `DeviceDriver` seam, `DriverResolver`, open-command state
  machine, attempts, feedback, cooldown, idempotency, command queue, whitelist outbox, stats. **Full
  suite: 202 passed (673 assertions).**
- **The seam for new drivers exists**: `device-driver.<type>` container binding; `CommandDispatcher`
  already contains the success/confirmation/fallback logic; `FakeDeviceDriver` proves the path.
- **Scope is defined** (`BATCH_09B_SCOPE.md`): TraccarDriver + event-forward, BLE provisioning +
  entitlement + reconciliation, SMS fallback, `WhitelistSyncJob` drain, `device_diagnostics`, Reverb.

## 2. Remaining blockers ⛔ (must clear before/at the start of 09-B)

| # | Blocker | Type | Owner | Gate |
|---|---|---|---|---|
| **B1** | **Phase-0 transport validation** — prove a Traccar custom/output command runs `cmdout.p` on a real UMKa 310 over a live Wialon session; measure tap→pulse latency; confirm output-state read-back (→ `opened`). | Empirical | Owner / ops + integrator | Hard — defines whether the remote model works as designed. |
| **B2** | **Self-hosted Traccar provisioned** + at least one UMKa pointed at it (server/port, Wialon Combine) via configuration software / SMS config. | Infra | Owner / ops | Hard — no remote path without it. |
| **B3** | **Coordinated breaking `DriverType` enum change** (PHP enum + OpenAPI already done + dev/test data migration of `clip_sms` rows + update 09-A test assertions + `DeviceModelSeeder` → UMKa). | Code/data | Implementation | Must be the **first 09-B commit** (single coordinated change — `DOCUMENT_CHANGE_PLAN.md` §7.3). |
| **B4** | **BLE security/provisioning model** — how the UMKa BLE script authorises a trigger; credential format; rolling/authenticated keys (relay-attack resistance); OS BLE constraints. **Highest unknown.** | Design + empirical | Owner / mobile + integrator | Hard for the BLE *primary* path; the remote path can proceed without it. |
| **B5** | **New schema + API surface to specify** — BLE-entitlement/credential table, Traccar device-mapping table (DBArch §6.x); BLE credential-issuance + open-reconciliation endpoints (OpenAPI). | Design | Architect | Soft — specify at 09-B start; not blocking the Traccar half. |
| **B6** | **UMKa command syntax confirmation** — the exact `cmdout.p` trigger command string + SMS command, from the UMKa manual / `dop` docs (owner-supplied references). | Documentation | Integrator | Feeds B1; needed before TraccarDriver/SmsDriver coding. |

## 3. Non-blockers / acceptable to carry

- The §12 historical subsections + diagrams (superseded-by-banner) — non-blocking doc-polish
  (`SPEC_CONSISTENCY_REPORT.md` §5.2).
- Vestigial schema fields (`voice_gateway_id`, `supports_*`) — kept stable; repurposed in a 09-B migration.
- MQTT driver — remains future/optional, out of 09-B.

## 4. Recommended sequencing

1. **Phase-0 (before coding):** clear **B1, B2, B6**, and de-risk **B4** (at least enough to commit BLE
   as primary, or temporarily make Traccar the sole primary and BLE a fast-follow).
2. **09-B step 1:** execute **B3** (the coordinated enum/data change) — first commit, keep the suite green.
3. **09-B step 2:** **B5** (new tables + endpoints), then build **TraccarDriver + event-forward**
   (remote path first — it is the better-understood half and unblocks remote/guest opening), then
   **SMS fallback**, then **BLE provisioning + reconciliation**, then `WhitelistSyncJob` drain,
   `device_diagnostics`, and the Reverb broadcast.

## 5. Risk note

The single largest residual unknown is **B4 (BLE)**. If BLE provisioning/security proves harder than
expected in Phase-0, the approved hybrid still functions with **Traccar as the sole primary** (remote +
in-person both over Traccar) and BLE delivered as a fast-follow — the architecture and 09-A core do not
change, only the in-person latency/offline-resilience benefit is deferred. This keeps 09-B unblocked on
the Traccar half while B4 is resolved.

---

*Readiness assessment complete. Recommend clearing B1/B2/B4/B6 in Phase-0 and executing B3 as the first
09-B commit. Awaiting approval to begin batch 09-B.*
