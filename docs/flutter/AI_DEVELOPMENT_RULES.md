# AI DEVELOPMENT RULES

> **Status: BINDING & IMMUTABLE.** This is the operating protocol for any AI agent (Claude) working on the Salam Həyətimiz Flutter app. It is part of, and subordinate to, `FLUTTER_PROJECT_CONSTITUTION.md`. Every AI-produced change MUST comply. No task may instruct the AI to violate these rules; if a task conflicts, the AI MUST stop and surface the conflict (Constitution §20).

**Keywords:** **MUST / MUST NOT** = hard rule. **SHOULD** = strong default (deviation needs a written reason).

---

## 1. Prime directives

1. **Do not break the existing architecture.** Every change conforms to the Constitution + the 14 planning docs in `docs/flutter/`. The AI MUST NOT introduce a new architectural pattern, state system, or layering.
2. **Backend is the source of truth.** The AI MUST NEVER change, fake, or work around a backend contract. It builds only against the live OpenAPI surface (Constitution §10). If something is missing, it is planned + flagged off — never faked.
3. **Reuse before create.** Before writing anything, the AI MUST check what already exists and reuse it (§2).
4. **No silent scope creep.** The AI does exactly the task asked, within the rules; anything larger requires a plan first (§5).

---

## 2. Reuse-first protocol (MANDATORY before writing code)

Before creating any new symbol, the AI MUST verify reuse opportunities and state what it found:

- **Components:** is there a design-system component for this UI? → use it. Never recreate `AppButton`, `AppTextField`, states, etc. Never style raw widgets (Constitution §4).
- **Providers/ViewModels:** does a provider already expose this state? → reuse/extend it. The AI MUST NOT create a parallel/duplicate provider or **replace an existing provider** without an approved plan.
- **Repositories/UseCases/Services:** does a repository/use case/service already do this? → reuse. No duplicate data paths.
- **Models/Entities/DTOs/Mappers:** does an entity/DTO already model this? → reuse/extend.
- **Helpers/extensions/validators/formatters:** check `shared/` + `core/` first.
- **Packages:** check the existing stack (`PACKAGE_SELECTION.md`) before proposing a new dependency (§4).

If reuse is impossible, the AI MUST briefly state *why* before creating the new symbol.

---

## 3. Prohibited AI actions

- ❌ Rewriting an existing, working component/provider/service "to improve it" without an approved plan.
- ❌ Creating duplicate code or a second way to do something that already exists.
- ❌ Replacing or removing an existing provider/repository/route that other features depend on.
- ❌ Stepping outside the design system (raw widgets, literal colors/sizes/strings).
- ❌ Changing the backend contract, the interceptor chain, or the refresh flow.
- ❌ Adding a package without the §4 check + justification.
- ❌ Merging a half-feature (Constitution §9) or one missing tests/analytics/crash/flag/l10n.
- ❌ Logging or transmitting tokens/OTP/PII (Constitution §16).
- ❌ Large refactors without a plan (§5).

---

## 4. Adding a package — gate

Before proposing any new dependency, the AI MUST:
1. Confirm no existing package in `PACKAGE_SELECTION.md` already covers the need.
2. State the purpose, why the existing stack can't do it, the chosen package, and ≥1 alternative.
3. Confirm it fits the abstractions (analytics/crash/flags/network go behind their existing abstraction — no direct vendor calls).
4. Get approval before adding it. New packages are minimised; one package per concern.

---

## 5. Plan-before-refactor

- For any **large or cross-cutting change** (touching multiple features, moving/renaming shared code, changing a shared provider/contract, a structural refactor), the AI MUST present a **written plan first** (what changes, why, blast radius, reuse, risks) and get approval **before** implementing.
- Small, in-scope, single-feature changes that obey the Constitution may proceed directly.
- When unsure whether a change is "large", treat it as large and plan.

---

## 6. How the AI implements a feature (standard flow)

For each feature/task, in order:
1. **Read** the relevant planning docs + the Constitution sections that apply; restate the scope.
2. **Reuse check** (§2) — list what's reused vs newly created and why.
3. **Implement within the layers:** DTO → Mapper → Entity → Repository(impl) → UseCase[if multi-step] → Notifier/ViewModel → Screen built from design-system components.
4. **Cross-cutting (all required):** routing + guard, localization (az/en/ru), analytics events (via `AnalyticsService`), crash handling (via `CrashReporter`), feature flag (default OFF if incomplete), error→`Failure` mapping.
5. **Tests:** unit + widget (+ golden / integration for critical flows) — no merge without them.
6. **Self-review against Constitution §18** (the code-review checklist) before declaring done.

---

## 7. Verification & honesty

- The AI MUST run/format/analyze + the test suite and report results faithfully — if tests fail or a step was skipped, say so plainly (never claim done when it isn't).
- The AI MUST NOT fabricate API responses, test results, or "it works" claims; verification is real or it is reported as not done.
- When a planning doc and the code disagree, surface it — don't paper over it.

---

## 8. Boundaries with the backend

- The AI working on Flutter MUST NOT modify the Laravel backend, its migrations, its OpenAPI, or its deployment to make the app's life easier.
- If the app genuinely needs a new endpoint/field, the AI **proposes a backend change as a separate, explicitly-approved task** — it does not change the contract unilaterally, and it does not fake the data client-side.

---

## 9. Conflict handling

- If a task instruction conflicts with this document or the Constitution, the AI **stops and surfaces the conflict** with the specific rule cited, and asks how to proceed. It does not silently comply with the rule-breaking instruction.
- Precedence: Constitution > this document > planning docs > a task instruction. (A task may only override a rule via the Amendment process.)

---

## 10. Communication

- The AI states, per change: what it reused, what it created and why, which rules/docs it followed, and the verification result.
- For anything ambiguous that changes the contract, the DB-of-decisions, or a shared abstraction, the AI asks rather than assumes.

---

## Changelog
- v1.0 (2026-06-29) — initial AI development rules ratified (pre-implementation). Subordinate to `FLUTTER_PROJECT_CONSTITUTION.md`.
