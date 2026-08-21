# Decision — Notifications Reconciliation

**Date:** 2026-08-11
**Status:** Accepted (documentation sign-off given). Implementation pending a separate "Implementation Go".
**Sources:** [NOTIFICATIONS_RECONCILIATION.md](../notifications/NOTIFICATIONS_RECONCILIATION.md) (§B/§C, approved) + the 7-document `docs/notifications/` set + CHANGELOG v1.3.

This record fixes the notification decisions folded into the canonical corpus. **No new architecture** was introduced; these reconcile the approved MVP plan with the pre-existing frozen notification spec (Tech Spec §17, DB Arch §7, Backend Arch §14.9).

## Locked decisions
1. **Identity model:** per-`(user × channel)` `notifications` rows; recipient = `user_id`; multi-device push fan-out inside the push channel. No `channels_sent` bitmask (the mask is a template-level selector only).
2. **Idempotency:** `(user_id, dedupe_key, channel)`; `dedupe_key` encodes the business key.
3. **Templates:** hybrid — business/system = `notification_templates` + `_locales`; admin free-text = `notification_campaigns` (non-templated). **No automatic translation.**
4. **Channels:** MVP = push + inapp. SMS + email = future (no identity-model change to add).
5. **Admin-initiated:** new Tech Spec §17.6 + `notification_campaigns` (DB Arch §7.5) + `notifications.campaign_id`; reserved `template_key='system.admin_campaign'`; audience all/user_ids/filter/complex with distinct-`user_id` dedupe; `complex_manager`-scoped; `notifications.view`/`notifications.send`; mandatory confirmation; server-resolved count; single language.
6. **Payload:** persisted `notifications.payload` + FCM transport `notification{title,body}` + `data:{type, notification_id, ids}`. `notification_id` = row id; `ids` = target entity ids. No secrets/PII beyond display title/body; detail fetched on tap.
7. **Tokens:** reuse `user_devices.push_token`; soft `push_invalid=true` on `UNREGISTERED`, reactivate on refresh.
8. **Retention/partitioning:** canonical monthly partition + 12 mo inapp / 90 d others preserved.
9. **Scope:** VL110C/Traccar/OpenCommand only; barrier-opened on `OpenCommandCompleted[state=Opened, source=Visitor]`. **UMKa fully out of scope.**
10. **Deferred:** notification-preferences UI, `device.offline`, admin scheduling.

## Resolved — D1–D5 (locked 2026-08-11)
1. **D1 — RBAC (Variant B).** `notifications.view` / `notifications.send` → **super_admin** + **Operator** (global) + **Complex Manager** (own-complex scope); `view` also to **Support**; **Technical / Finance: none**. Complex scope enforced via the existing `complexScopeId()` (cannot be bypassed); recipients dedupe by `user_id`. Grid updated in `RBAC_PERMISSION_MATRIX.md` (→ seed 46 permissions / 60 assignments when the module ships) + narrative in `ADMIN_PERMISSION_MATRIX.md`.
2. **D2 — Component naming (canonical).** `NotificationDispatcher`, `PushClient` (interface) + `FcmPushClient` (concrete) + `FakePushClient`, `SendPushNotificationJob`. Module docs aligned to these; **no real code renamed** (not yet built).
3. **D3 — Push-token endpoint.** `PUT /v1/notifications/push-token` (`upsertPushToken`, body `{push_token}` only, 204). Register + refresh share this upsert; the install is resolved from the JWT `fp` claim (= install_uuid) — no body `install_uuid`.
4. **D4 — De-registration.** `DELETE /v1/notifications/push-token` (no body; current install from JWT `fp`; clears **only** this install's `push_token`, other devices untouched). Used on logout. Confirmed the existing auth model supports it (`LogoutUser` + `UserDeviceService::findByFingerprint`); **no new mechanism**.
5. **D5 — Admin OpenAPI.** `GET /admin/notifications`, `POST /admin/notifications/audience/preview`, `POST /admin/notifications` (Idempotency-Key + `confirmed=true`), `GET /admin/notifications/{campaignId}` + schemas `NotificationCampaign` / `NotificationCampaignCreate` / `AudienceSpec` / `AudiencePreview`. Fixed push+inapp (no `channels_mask`); no scheduling / recall / per-recipient drill-down; audience types unchanged.

## Note
- `LOCALIZATION_SPECIFICATION` already covers notification localization (§4.6 templates, §6.8 pre-rendered push strings) — no change required.
