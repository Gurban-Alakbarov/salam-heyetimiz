# Notifications — Reconciliation Gate

**Version:** 0.1 (proposal — awaiting sign-off)
**Status:** DRAFT — reconciliation gate. Blocks the Notification documentation set **and** implementation until the deltas below are approved.
**Date:** 2026-08-11
**Owner decision input:** the 10 approved directions provided 2026-08-11 (recorded verbatim in §2).
**Nature:** This document changes **nothing**. It reconciles the approved *Push Notification Implementation Plan* against the frozen v1.1 canonical corpus, records every delta, and lists the exact canonical edits that will be required **after** sign-off. Per Constitution Principle 2 (*"No redesign without explicit request"*) and Principle 1 (*"the spec is the contract"*), these deltas MUST be resolved explicitly — never silently in code.

---

## 0. Authority & Sources Reconciled

| Frozen source | Authority over | Notification section |
|---|---|---|
| `PROJECT_CONSTITUTION.md` | Invariants, rule codes (R-*), RFC-2119 | R-ARCH-07, R-ARCH-08, Principle 4 |
| `TECHNICAL_SPECIFICATION.md` | FR/flows, behaviour | **§17** (Channels, Templates, Delivery, Preferences, Reliability), FR-NOT-01/02, UC-04 |
| `DATABASE_ARCHITECTURE.md` | Physical schema, partitioning, retention | **§7** (§7.1 templates, §7.2 template_locales, §7.3 notifications, §7.4 user_notification_settings), §1.3 user_devices |
| `BACKEND_ARCHITECTURE.md` | Module layout, event→consumer, policies | **§14.9**, event matrix, NotificationPolicy, NotificationTemplatePolicy |
| `UI_UX_SPECIFICATION.md` | Screens/states/copy | S-18 (Notifications) |
| `LOCALIZATION_SPECIFICATION.md` | Locale keys | `lang/{az,ru,en}/notifications.php` |
| `openapi/v1.yaml` | Binding API | `/notifications*` |
| `ADMIN_PERMISSION_MATRIX.md` | Admin RBAC | (no notifications perms yet) |

**Confirmed existing implementation substrate** (audit, read-only): `user_devices.push_token` / `push_token_updated_at` / `push_invalid` (DB Arch §1.3), Horizon `notifications` queue, `config/integrations/fcm.php`, `config/domain/notifications.php` (channel mask push=1/sms=2/inapp=4/email=8), `app/Domain/Notifications` stub, `OpenCommandCompleted` event, `OpenCommandState::Opened`, VL110C actuation confirm (`awaitCommandResult` → 0x21 `Success!`), `AdminResidentController` residents directory, admin `Permission` enum, residential **complex** entity.

---

## 1. Scope & Exclusions (locked by this gate)

- **In scope (MVP):** delivery channels **push + inapp**; device path **VL110C + Traccar + OpenCommand** only; automatic business/system notifications; **admin-initiated** notifications.
- **Out of scope (explicitly):**
  - **UMKa — completely.** No UMKa event/listener/webhook/migration/architecture/abstraction/future-support placeholder anywhere in the notification domain (Decision 10). The barrier-open trigger uses `OpenCommandCompleted[state=Opened]`, which for VL110C is actuation-confirmed (Constitution Principle 4). UMKa is a device-transport concern orthogonal to notifications.
  - **SMS + email delivery** — future channels; the identity model must remain capable of them without redesign (Decision 4).
  - **Multi-language admin campaigns** — future; single language per campaign at MVP (Decision 7). No automatic translation, ever.
  - **Notification preferences UI** — deferred; `user_notification_settings` (DB Arch §7.4) schema is preserved but not surfaced at MVP.

---

## 2. Reconciliation Deltas

Each delta: **Canonical** · **Plan** · **Conflict?** · **Resolution** (approved direction) · **Reason** · **Canonical update required**.

### D1 — `notifications` row model  *(Decision 1)*
- **Canonical (DB Arch §7.3):** `notifications` is **per-channel** — `id, partition_key(YYYYMM), user_id, template_key, channel ENUM(push,sms,inapp,email), payload JSON (rendered + deep links), dedupe_key, status ENUM(queued,sent,failed,read), failure_reason, sent_at, read_at, created_at`. One row per (recipient × channel).
- **Plan:** single row + `channels_sent` bitmask + separate `title/body/data/deep_link/category/read_at` columns.
- **Conflict:** **YES.**
- **Resolution:** **KEEP the canonical per-channel model.** Drop `channels_sent`. `title/body/deep-link/ids` → `payload` JSON. `category` is a **template** attribute (§7.1), not a row column. The channel **bitmask** survives only as `notification_templates.default_channels_mask` (which channels a template fans out to) — it selects channels that become **per-channel rows**; it is **not** a per-row "sent" mask.
- **Reason:** preserves multi-channel identity (sms/email later = new rows, zero schema change); per-channel `status`/`failure_reason`/retry tracking; DB Arch governs schema (Constitution source table).
- **Canonical update:** **None** — the plan's schema is superseded by §7.3 (plan conforms).

### D2 — Idempotency / dedupe key  *(Decision 2)*
- **Canonical:** DB Arch §7.3 physical constraint `uq_notifications_dedupe (user_id, dedupe_key, channel)`. Tech Spec §17.3 phrases it **logically** as `(template_key, user_id, dedupe_key)`.
- **Plan:** `(user_id, dedupe_key)`.
- **Conflict:** **YES** (plan) — plus an internal §17.3 ↔ §7.3 wording drift.
- **Resolution:** **KEEP the canonical constraint `(user_id, dedupe_key, channel)`.** `dedupe_key` encodes the business/template key: `visitor_opened:{open_command_id}`, `sub_expiring:{subscription_id}:{cycle}`, `campaign:{campaign_id}:{user_id}`.
- **Reason:** uniqueness must be **per channel** (one event may legitimately yield both a push and an inapp row); embedding the business key in `dedupe_key` satisfies §17.3's intent without a `template_key` term in the physical index.
- **Canonical update:** **Tech Spec §17.3** — reword idempotency to the physical `(user_id, dedupe_key, channel)` and define `dedupe_key` composition (removes the §17.3↔§7.3 drift). DB Arch §7.3 unchanged.

### D3 — Templates (hybrid)  *(Decision 3)*
- **Canonical (DB Arch §7.1/§7.2, Tech Spec §17.2):** admin-editable `notification_templates` + `notification_template_locales` (per-locale bodies); a template catalogue.
- **Plan:** skip the template DB for MVP; hardcode copy in lang files.
- **Conflict:** **YES.**
- **Resolution:** **HYBRID.** (a) **Business/system automatic** notifications → **templated** (§7.1/§7.2), localised per recipient. (b) **Admin free-text** announcements → **`notification_campaigns`** (new), **no** predefined template; rendered title/body written directly into `notifications.payload`. **No automatic translation.** MVP seeds templates only for the initially live business types (visitor-opened, subscription_*).
- **Reason:** templates give admin-editable, per-locale business copy without a deploy (canonical intent); admin ad-hoc text is inherently free-text — cannot be pre-templated or safely auto-translated.
- **Canonical update:** **Tech Spec §17.2** (note: catalogue = templated business events; admin free-text is a separate non-templated path → §17.6). **DB Arch §7** (add §7.5 `notification_campaigns`; add nullable `campaign_id` to §7.3 — see D5).

### D4 — Delivery channels (MVP scope)  *(Decision 4)*
- **Canonical (Tech Spec §17.1):** push + sms + inapp + email[P2].
- **Plan:** push + inapp (MVP).
- **Conflict:** **NO** (scoping, not redesign).
- **Resolution:** MVP delivers **push + inapp**. **SMS + email = future channels.** The `channel` ENUM + `default_channels_mask` + per-channel rows already carry them; enabling them later needs **no change to the notification identity model**.
- **Reason:** fastest production-safe path; canonical schema is already channel-generic.
- **Canonical update:** **Tech Spec §17.1** — annotate sms/email as post-MVP delivery (phase marker). Optional `docs/futures/notifications-sms-email.md` pointer. **No schema change.**

### D5 — Admin-initiated notifications (new capability)  *(Decision 5)*
- **Canonical:** **ABSENT.** §17 covers only templated business/system events; there is no admin compose/broadcast concept and no campaign table.
- **Plan:** admin composes + sends a `system` announcement to an audience; same NotificationDispatcher + queue + inbox.
- **Conflict:** **NO** (gap — genuinely new).
- **Resolution:** Formally **add Tech Spec §17.6 "Admin-Initiated Notifications."** Introduce **`notification_campaigns`** (DB Arch new §7.5) and a nullable **`campaign_id`** FK on `notifications` (§7.3). A campaign fans out into per-channel, per-recipient `notifications` rows; those rows carry `template_key = 'system.admin_campaign'` (reserved constant, satisfies the NOT-NULL — see E2), rendered title/body in `payload`, and `campaign_id`.
- **Reason:** real operational need (e.g. planned-maintenance announcements). MUST reuse the single NotificationDispatcher/queue/inbox — not a parallel push system.
- **Canonical update:** **Tech Spec §17.6** (new); **DB Arch §7.5** (new) + §7.3 (`campaign_id` + reserved template_key note); **Backend Arch §14.9** (admin dispatch path, `NotificationCampaignPolicy`); **ADMIN_PERMISSION_MATRIX** (see D6); **openapi** (admin endpoints); **UI/UX** (A-* admin screen).

### D6 — Admin audience + permissions  *(Decision 6)*
- **Canonical (existing code/docs):** residents directory `GET /admin/v1/residents` (`q` = name/email/phone search, `complex_id`/`role`/`subscription_status` filters, cursor) via `AdminResidentController`; admin RBAC `Permission` enum + `requirePermission`; residential **complex** entity (complexes.view/manage, `devices.complex_id`); `complex_manager` scoped role.
- **Plan:** audience = all / selected user_ids / filter / complex; dedupe by user_id; complex_manager scope; `notifications.view` + `notifications.send`.
- **Conflict:** **NO** (builds on existing).
- **Resolution:** Audience types = **`all_users | user_ids[] | filter{q,complex_id,role,…} | complex_id`**. Recipient resolution **dedupes to DISTINCT `user_id`** (residents rows are per `device_user_id`; a user on 2 devices = 1 recipient). `complex_manager` admins are scoped to their own complex. Gate with **`notifications.view`** (list/preview) + **`notifications.send`** (dispatch).
- **Reason:** reuses the proven residents search + RBAC + complex model; user-level dedupe prevents multi-device double counting at the recipient level (multi-device fan-out happens per-device *inside* delivery, not per audience row).
- **Canonical update:** **Tech Spec §17.6**; **ADMIN_PERMISSION_MATRIX** (`notifications.view`, `notifications.send`); reference `RBAC_ARCHITECTURE`.

### D7 — Admin language (MVP)  *(Decision 7)*
- **Canonical:** §17.2 templated notifications are per-locale (recipient-localised). No admin free-text language concept.
- **Plan:** admin picks **one** language (AZ/EN/RU), enters one title+body, sent as-is; no auto-translate; future multi-language possible.
- **Conflict:** **NO.**
- **Resolution:** campaign carries `language ∈ {az,en,ru}` + one title/body. **No automatic translation.** Future multi-language = campaign `translations` JSON resolved per user `preferred_language` **at fan-out** — additive, no identity-model change (each recipient's `notifications.payload` already stores the resolved copy).
- **Reason:** admin ad-hoc text can't be auto-translated safely; single language is production-safe; the "resolve per user at fan-out" seam keeps multi-language open without schema churn.
- **Canonical update:** **Tech Spec §17.6** (language field + future note).

### D8 — Mandatory send confirmation  *(Decision 8)*
- **Canonical:** none (UI concern).
- **Plan:** the send/POST fires **only** after an explicit admin confirmation showing recipient count, type, title, body, scope, language, and an irreversibility warning.
- **Conflict:** **NO.**
- **Resolution:** the dispatch endpoint executes **only** post-confirmation; the recipient **count** shown is **server-resolved (distinct user_id)** immediately before confirm (accurate for filter/all audiences).
- **Reason:** prevents accidental mass sends; count accuracy.
- **Canonical update:** **Tech Spec §17.6** (send flow); **UI/UX** A-* admin screen.

### D9 — Retention & partitioning  *(Decision 9)*
- **Canonical (DB Arch §7.3):** RANGE **monthly** partition on `partition_key`; retention **12 months inapp / 90 days non-inapp**.
- **Plan:** not mentioned (under-specified).
- **Conflict:** **NO** (omission, not contradiction).
- **Resolution:** **PRESERVE canonical partitioning + retention verbatim.** Implementation MUST set `partition_key` and honour the retention sweeps.
- **Reason:** canonical governs; do not silently drop.
- **Canonical update:** **None** (plan adopts canonical).

### D10 — UMKa  *(Decision 10)*
- **Canonical:** Constitution v1.2 names GLONASSSoft **UMKa 310** as the field-hardware pivot; the current live device is **VL110C** (`docs/devicecomm/vl110c/`); Principle 4 (dispatched vs opened); CRIT-06 resolved (actuation confirmed via read-back).
- **Plan:** UMKa **fully out of scope** for notifications.
- **Conflict:** **NO** (scope).
- **Resolution:** notification docs reference **only VL110C/Traccar/OpenCommand**. No UMKa event/listener/webhook/migration/abstraction/future placeholder. Barrier-opened trigger = `OpenCommandCompleted[state=Opened]` — for VL110C, actuation-confirmed, aligning Constitution Principle 4.
- **Reason:** single real device architecture; no speculative abstraction (Principle 10).
- **Canonical update:** **None** for notifications. (Corpus-wide UMKa↔VL110C device drift is a separate reconciliation — see E5 — outside this gate.)

### Additional reconciliations discovered
- **D11 — push token store:** DB Arch §1.3 `user_devices` already holds `push_token`; §17.5 *"FCM-reported invalid tokens removed"* → implement as **soft** `push_invalid=true` (+ reactivate on token refresh). **No new table.** *Canonical update:* Tech Spec §17.5 note (soft-invalidate flag).
- **D12 — deep-link storage:** canonical `payload` JSON carries deep links → **no separate `deep_link` column** (plan's column dropped). *Canonical update:* none.
- **D13 — unread count:** `GET /v1/me.unread_notifications_count` is hardcoded `0` → must become a **real** count of unread `inapp` rows (§7.3 `idx_notifications_user_channel_status` supports it). *Canonical update:* none (canonical already expects a real value).
- **D14 — barrier event hook:** canonical event matrix `OpenCommandCompleted → Notifications (optional inapp)` + template `device.opened`. Visitor-opened uses this filtered to **`state=Opened && source=Visitor`**. `OpenCommandFailed → Notifications (push)` is catalogued but **deferred** to a later inventory item. *Canonical update:* none (align).

---

## 3. Locked Notification Identity Model (protected by D1/D2/D4/D9)

The following is the **invariant recipient/record model**; MVP scoping (channels) MUST NOT alter it:

- A notification is a **per-(user × channel)** row in `notifications` (§7.3).
- Recipient identity is **`user_id`**; multi-device fan-out happens **inside** the push channel across `user_devices` (non-revoked, `push_invalid=false`), never by duplicating recipient rows.
- Idempotency = **`(user_id, dedupe_key, channel)`**.
- Copy/deep-link live in **`payload`** (rendered); category/channels-default live on the **template**.
- **Monthly partitioning + retention** (12mo inapp / 90d others) always apply.
- Adding sms/email later = **new rows only** (no identity change).

---

## A. Final Reconciliation Table

| # | Topic | Conflict | Resolution (approved direction) | Canonical update |
|---|---|---|---|---|
| D1 | notifications row model | Yes | Per-channel canonical; drop bitmask; payload JSON | None (plan conforms §7.3) |
| D2 | dedupe key | Yes | `(user_id, dedupe_key, channel)` | Tech Spec §17.3 (reword) |
| D3 | templates | Yes | Hybrid: business=templates, admin=campaigns; no auto-translate | §17.2 note; DB Arch §7.5 + §7.3 |
| D4 | channels | No | MVP push+inapp; sms/email future (no identity change) | §17.1 (phase note) |
| D5 | admin-initiated | No (new) | Add §17.6 + `notification_campaigns` + `campaign_id` | §17.6; DB Arch §7.5/§7.3; Backend §14.9; openapi; UI/UX |
| D6 | admin audience + perms | No | all/user_ids/filter/complex; dedupe user_id; complex_manager; `notifications.view/send` | §17.6; ADMIN_PERMISSION_MATRIX |
| D7 | admin language | No | One language/campaign; future multi-language additive | §17.6 |
| D8 | send confirmation | No | POST only after confirm; server-resolved count | §17.6; UI/UX |
| D9 | retention/partitioning | No | Preserve canonical verbatim | None |
| D10 | UMKa | No | Out of scope; VL110C only | None (see E5) |
| D11 | token invalidation | No | Soft `push_invalid`; reuse `user_devices` | §17.5 note |
| D12 | deep-link storage | No | In `payload` JSON | None |
| D13 | unread count | No | Real inapp-unread count | None |
| D14 | barrier hook | No | `OpenCommandCompleted[Opened, Visitor]` | None |

---

## B. Decisions Requiring Your Approval

Approve these before the doc set is written or any canonical doc is touched:

- **B1.** Lock the **notification identity model** (§3): per-channel rows, `payload` JSON, dedupe `(user_id, dedupe_key, channel)`, monthly partition + retention. *(Decisions 1, 2, 4, 9.)*
- **B2.** Add **`notification_campaigns`** (DB Arch §7.5) **+ nullable `campaign_id`** on `notifications` (§7.3), with reserved `template_key = 'system.admin_campaign'` for campaign rows. *(Resolves E2.)*
- **B3.** Add **Tech Spec §17.6 "Admin-Initiated Notifications"** as a new canonical section.
- **B4.** Add admin RBAC permissions **`notifications.view`** + **`notifications.send`** to `ADMIN_PERMISSION_MATRIX` and the `Permission` enum.
- **B5.** Reword **Tech Spec §17.3** idempotency to `(user_id, dedupe_key, channel)` (resolve the §17.3↔§7.3 wording drift).
- **B6.** Confirm the **hybrid template** model (business=templates, admin free-text=campaigns; no auto-translation).
- **B7.** Confirm **channel phasing**: MVP = push+inapp; sms/email annotated as future in §17.1.

---

## C. Canonical Documents That Will Need Updating (after approval)

> None are modified by *this* file. This is the post-approval work list.

| Canonical doc | Change |
|---|---|
| `TECHNICAL_SPECIFICATION.md` | §17.1 channel phasing; §17.2 hybrid note; §17.3 dedupe wording; §17.5 soft-invalidate note; **new §17.6** admin-initiated |
| `DATABASE_ARCHITECTURE.md` | **new §7.5** `notification_campaigns`; §7.3 add `campaign_id` + reserved `template_key` note (§7.1–7.4 unchanged) |
| `BACKEND_ARCHITECTURE.md` | §14.9 admin dispatch path; `OpenCommandCompleted[Opened,Visitor]` consumer; `NotificationCampaignPolicy` |
| `PROJECT_CONSTITUTION.md` | add **R-NOT-xx** invariants (from the module INVARIANTS doc); note admin-initiated + UMKa-exclusion scope |
| `ADMIN_PERMISSION_MATRIX.md` | `notifications.view`, `notifications.send` |
| `openapi/v1.yaml` | user endpoints (push-token register/refresh/logout, `GET/POST /notifications`, unread) + admin campaign endpoints |
| `LOCALIZATION_SPECIFICATION.md` | `lang/{az,ru,en}/notifications.php` keys (already anticipated by §17.2) |
| `docs/flutter/NAVIGATION.md`, `SCREEN_FLOW.md` (S-18) | deep-link routing + real inbox behaviour |
| `CHANGELOG.md` + `docs/decisions/notifications-reconciliation.md` | record the MVP-scoping + delta decisions |

---

## D. Proposed Order For Creating The Notification Documentation Set

1. **`NOTIFICATIONS_RECONCILIATION.md`** — *this document* (approval gate). ⛔ nothing proceeds until signed.
2. `NOTIFICATIONS_ARCHITECTURE.md` — purpose, boundaries, relationships, push+inapp unity, FCM responsibility split, UMKa exclusion.
3. `NOTIFICATIONS_INVARIANTS.md` — R-NOT-xx draft → feeds Constitution.
4. `NOTIFICATIONS_DATABASE_PLAN.md` — reconciled schema (+ §7.5 campaigns) → feeds DB Arch §7.
5. `NOTIFICATIONS_INVENTORY.md` — type catalogue reconciled with §17.2 → feeds Tech Spec §17.2.
6. `NOTIFICATIONS_ADMIN_SPEC.md` — §17.6 + audience/perms/confirmation/language → feeds §17.6, ADMIN_PERMISSION_MATRIX, openapi.
7. `NOTIFICATIONS_MOBILE_FLOW.md` — receive (fg/bg/terminated), deep-link, inbox (S-18), badge, token lifecycle → feeds Flutter docs.
8. `NOTIFICATIONS_IMPLEMENTATION_PLAN.md` — phases, file-by-file, order, Firebase prep, required inputs.
9. **Canonical in-place updates** (§C) — applied as one reviewed batch after the module set is signed off.

---

## E. Remaining Contradictions / Unknowns

- **E1 — §17.3 ↔ §7.3 dedupe drift (internal to the frozen corpus).** Tech Spec §17.3 says `(template_key, user_id, dedupe_key)`; DB Arch §7.3 says `(user_id, dedupe_key, channel)`. Resolved by **B5** (adopt the physical §7.3 constraint). *Needs sign-off.*
- **E2 — `notifications.template_key` is NOT NULL, but admin free-text has no template.** Recommended: reserved constant `template_key = 'system.admin_campaign'` for campaign rows (avoids making the column nullable). Alternative: relax `template_key` to nullable. *Choose one (B2).*
- **E3 — audience count source.** Filter/all-users counts must be server-resolved (distinct user_id): a `count_only` flag on the residents query vs a dedicated preview endpoint. Design detail — deferred to `NOTIFICATIONS_ADMIN_SPEC.md`; not a blocker.
- **E4 — `device.offline` notification.** Canonical §17.2 catalogues it (push, owner, 24h since last diag) and R-DOM-09 fixes an offline threshold, but the audit found **no emitted online/offline event** (only `last_online_at` / `consecutive_offline_diagnostics` fields). MVP **excludes** it; a diagnostics-driven trigger is a later item. *Confirm it stays out of MVP.*
- **E5 — corpus device drift (UMKa 310 ↔ VL110C).** Constitution v1.2 names UMKa 310; the live device is VL110C. This is a **broader corpus reconciliation** beyond notifications; here we simply scope notifications to VL110C. Flag that the device references in the frozen corpus may need their own separate reconciliation (not part of this gate).
- **E6 — `user_notification_settings` (§7.4, MVP-light).** Preferences UI is deferred; the schema is preserved. *Confirm deferral.*

---

**Gate status:** ⛔ **BLOCKED — awaiting approval of §B (B1–B7) and the §E confirmations (E1/E2, E4, E6).** On approval, proceed with §D order (documents only; canonical edits in §C applied as a reviewed batch). No code, no migrations, no canonical edits until then.
