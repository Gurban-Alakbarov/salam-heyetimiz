# SPEC CONSISTENCY REPORT — v1.2 Transport Amendment

**Date:** 2026-06-14
**Scope:** verify the v1.2 transport pivot is applied consistently across the corpus, validate
cross-references and the OpenAPI contract, and disclose every known/intentional divergence.

---

## 1. Driver-taxonomy consistency (`traccar / ble / sms`)

| Artifact | Value | Status |
|---|---|---|
| PROJECT_CONSTITUTION R-GSM-01 | `traccar, ble, sms` | ✅ |
| TECHNICAL_SPECIFICATION glossary + scope + FR-DEV-01 | `traccar / ble / sms` | ✅ |
| BACKEND_ARCHITECTURE §13/§14.6 | `TraccarDriver / BleProvisioningDriver / SmsDriver` | ✅ |
| DATABASE_ARCHITECTURE §2.2/§6.1 (notes) | `traccar/ble/sms` | ✅ |
| openapi/v1.yaml (5 enum sites) | `[traccar, ble, sms]` | ✅ |
| **`app/Domain/Devices/Enums/DriverType.php` (code)** | still `clip/sms/clip_sms/mqtt` | ⚠️ **Intentional spec-ahead gap — see §5.1** |

## 2. Risk-register status consistency

| Risk | New status | Stated in |
|---|---|---|
| CRIT-01 (operator caller-ID rewrite) | **Retired / N-A** | Constitution banner + R-GSM-02; TechSpec banner; FINAL_TRANSPORT_DECISION §7 | ✅ |
| CRIT-03 (voice-gateway HA) | **Retired / N-A** | Constitution R-GSM-05; BackendArch §14.6; FINAL_TRANSPORT_DECISION §7 | ✅ |
| CRIT-06 (CLIP cannot confirm actuation) | **Resolved** | Constitution principle 4 + R-GSM-03; OpenAPI `driver_confirms_actuation`; TechSpec banner | ✅ |

Consistent everywhere. (Note: `AUDIT_REPORT.md` / `AUDIT_RESOLUTION_PLAN.md` retain the original CRIT
wording as historical audit artefacts; they are not part of the live spec and are not edited — the live
status is carried by the Constitution + FINAL_TRANSPORT_DECISION. Flagged, not a defect.)

## 3. Cross-reference validation

- All v1.2 banners link to `FINAL_TRANSPORT_DECISION.md`, `DOCUMENT_CHANGE_PLAN.md`,
  `TRANSPORT_MIGRATION_CHANGELOG.md`, `BATCH_09B_SCOPE.md` — files exist in `docs/`. ✅
- `FINAL_TRANSPORT_DECISION.md` ↔ `DOCUMENT_CHANGE_PLAN.md` ↔ `BATCH_09B_SCOPE.md` ↔
  `devicecomm-transport-reassessment.md` cross-links resolve. ✅
- Version headers updated and mutually consistent: Constitution v1.2, TechSpec v1.2, DBArch v1.2
  (source-spec pointer → v1.2), BackendArch v1.2, OpenAPI 1.2.0. ✅
- `CHANGELOG.md` v1.2 entry present and links resolve. ✅

## 4. OpenAPI validation (post-edit)

```
schemas=81, parameters=7, responses=7, headers=4, securitySchemes=4
Unique refs used: 96 — All $refs resolve.
operationIds: 100 (all unique) · tags: 26 declared, 26 used (all valid)
```
**Green.** No structural change; only the driver enum values + two descriptions + the version. No
operationId or `$ref` was added or removed.

## 5. Known / intentional divergences (tracked, not defects)

### 5.1 Spec-ahead-of-code: `DriverType` enum
The specs + OpenAPI now declare `traccar/ble/sms`; the PHP `DriverType` enum, the batch-09-A tests, the
`device_comm` config, and the `DeviceModelSeeder` (RTU5024) still use `clip_sms…`. This is **deliberate
and sequenced**: the enum change is breaking and must be one coordinated commit (code + OpenAPI + data
migration) at the **start of batch 09-B** (`DOCUMENT_CHANGE_PLAN.md` §7.3). The full 09-A test suite
remains **green** because it asserts code values, which are unchanged in this doc-only pass.

### 5.2 §12 historical subsections + diagrams (TECHNICAL_SPECIFICATION)
§12.1–§12.9 and the device-comm mermaid diagrams still contain CLIP/voice-gateway prose. They are
**superseded by the §12 banner** (which carries the authoritative model) and retained as historical
context. Residual CLIP/voice mentions in the corpus are therefore either (a) explicit "retired/superseded"
annotations, or (b) these historical §12 bodies. A full §12 rewrite + diagram redraw is **non-blocking
doc-polish** scheduled for the 09-B doc pass; it does not affect implementation, which follows the banner
+ FINAL_TRANSPORT_DECISION.

### 5.3 Vestigial schema fields (kept stable, documented)
`open_command_attempts.voice_gateway_id` and `device_models.supports_clip/_sms/_mqtt` (+ OpenAPI
`DeviceModel.supports_clip`) are vestigial under the new transport. They are **not dropped** in this pass
to avoid premature DDL/contract churn; a 09-B migration repurposes/generalises them (`voice_gateway_id` →
`transport_ref`). Documented in DBArch §6.1.1 / §2.2.

## 6. Verdict

The documentation corpus is **consistent at the authoritative level** — every header, rule (R-GSM-*,
R-DOM-05, R-SEC-04), glossary term, integration table, queue profile, NFR target, and OpenAPI enum
reflects the v1.2 hybrid transport. The three residual categories in §5 are **intentional, sequenced, and
documented**, not contradictions. The OpenAPI contract validates green.

**No un-annotated contradiction remains in the live spec.** The corpus is ready for the 09-B readiness
review (`BATCH_09B_READINESS.md`).
