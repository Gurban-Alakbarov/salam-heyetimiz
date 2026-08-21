# BACKEND ARCHITECTURE REVIEW

> Pre-Flutter backend audit. **Read-only — no code, migrations, or config were changed.**
> Method: 6 parallel read-only audits (architecture, database, performance, API parity, security, device subsystem) + first-hand verification of the load-bearing "critical" claims. Where an agent over-rated a finding, this report corrects it (noted inline).

---

## 0. Verdict

**Architecture grade: EXCELLENT (9.5/10).** A genuinely well-structured Laravel 12 modular monolith. Consistent DDD layering across 14 modules, thin controllers, zero business logic in HTTP layer, no dead code, no TODO/FIXME debt. The few findings are cleanups, not defects.

---

## 1. Layering & module structure

Code is organised as `app/Domain/<Module>/` with the same sub-structure everywhere: `Actions/`, `Services/`, `Queries/`, `Models/`, `Events/`, `Listeners/`, `Jobs/`, `Enums/`, `Adapters/`, `Contracts/`, `DTOs/`, `Policies/`. HTTP is `app/Http/{Api/V1, Admin/V1, Webhooks, Docs}` with `Resources/`, `Requests/`, `Middleware/`, `Concerns/`.

Modules: **Auth, Users, Devices, DeviceComm, Roster, Subscriptions, Payments, Admin (Settings + Authorization), Audit, Privacy, Lookups.**

| Check | Result |
|---|---|
| Controllers are thin (delegate to Actions/Services) | ✅ No business logic found in any controller |
| Requests validate + build DTOs; Resources format output | ✅ Consistent |
| One write path per use case (Action) with a transaction boundary | ✅ e.g. `VerifyEmailAndIssueTokens`, `RefundService`, `OpenDevice` |
| Read models isolated in Query classes | ✅ `OrderQuery`, `DeviceQuery`, `SubscriptionQuery`, `ResidentQuery`, `PaymentStatsQuery` |
| Cross-cutting via traits/concerns | ✅ `AuthorizesAdmin`, `RespondsWithEnvelope`, `PresentsAdminDevice` |
| Domain events + auditable wiring | ✅ `Event::listen('*')` auto-audits every `AuditableEvent` |

**No layering violations, no god-classes, no logic leakage.**

---

## 2. Findings

### A. Duplicate code — cursor pagination (Medium)
The identical `paginate(Builder, limit, cursor)` helper appears verbatim in **4 Query classes**: `OrderQuery`, `DeviceQuery`, `SubscriptionQuery`, `OpenCommandQuery` (~18 lines each). Also `RefundQuery`/`PaymentLog`/`ResidentQuery` re-implement the same cursor contract.
**Why it matters:** four copies drift independently; a cursor-encoding change touches four files.
**Fix direction (not applied):** extract a `CursorPaginatedQuery` trait or `Paginator` helper under `app/Support/Pagination/`.

### B. Two response envelopes coexist (Low — by design)
- New mobile surface (register / verify-email / resend-otp / login / bootstrap / me) → unified `{success,message,data,meta,errors}` (`RespondsWithEnvelope`).
- Legacy phone-OTP + refresh + admin auth → bare `{access_token,…}`.
This is documented additive coexistence, not an accident. See `API_REVIEW.md §2` for the consistency discussion. Flutter targets the unified surface.

### C. Config files never read (Low)
`config/audit.php`, `config/broadcasting.php` are not read at runtime — `audit` retention is enforced structurally (immutable table), `broadcasting`/Reverb is reserved for the future notifications/WebSocket feature. Not dead code; reserved. No action needed beyond a one-line header note.

### D. Single-impl interfaces (Not a problem)
`TraccarClient`, `DeviceSmsGateway` each have one HTTP impl + a Fake (testing). This is a legitimate external-boundary seam (vendor-replaceable + testable), not over-abstraction. `OtpTransport`/`EmailOtpTransport`/`DeviceDriver` each have 2 real impls. **No unnecessary abstraction found.**

### E. TODO / FIXME / @deprecated (None)
Zero TODO/FIXME/HACK/@deprecated markers across `app/`, `config/`, `routes/`. Unusually clean.

### F. Dead code (None confirmed)
Spot-checks confirmed all 41 Events are dispatched, all 12 Listeners + 5 Policies are registered in module providers, all 12 Jobs are dispatched, all Models are referenced. No orphan classes found.

---

## 3. Reserved-by-design (not debt, but track)
- `users.password` — reserved nullable column for the future Security/password-login feature (hidden in the model, never written today). Intentional forward-compat (Registration Phase 2 design).
- Dual OTP engine (phone/SMS + email) — one shared core (`OtpService::store/consume`), two transports. Reuse, not duplication.

---

## 4. Recommendations (priority)
1. **Low/Cleanup:** extract the cursor-pagination helper (removes 4× duplication).
2. **Low/Doc:** add a `docs/decisions/` note on the intentional dual-envelope + reserved configs so future devs don't "fix" them.
3. Nothing here blocks Flutter or v1.0.

**Architecture is release-ready.** The substantive pre-release items live in `API_REVIEW.md` (spec drift), `PERFORMANCE_REVIEW.md` (N+1 + inline I/O), and `DATABASE_REVIEW.md` (lifecycle FKs).
