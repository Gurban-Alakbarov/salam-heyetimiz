# Notifications — Architecture

**Version:** 0.1 (DRAFT — pending documentation-set sign-off)
**Status:** DRAFT. Elaborates the frozen corpus; **no canonical document is modified by this file.**
**Date:** 2026-08-11
**Depends on:** [NOTIFICATIONS_RECONCILIATION.md](NOTIFICATIONS_RECONCILIATION.md) (approved 2026-08-11) · Tech Spec §17 · Backend Arch §14.9 · DB Arch §7, §1.3 · Constitution R-ARCH-06/07/08, Principle 4.
**Scope guard:** push + inapp (MVP) · SMS/email are future channels · **VL110C / Traccar / OpenCommand only** · **UMKa completely OUT OF SCOPE** · notification-preferences UI deferred · `device.offline` deferred.

---

## 1. Purpose & Role in the Salam Həyətimiz Ecosystem

Notifications is a **cross-cutting, event-driven consumer module** (Backend Arch §14.9; Constitution R-ARCH-07). It is the single place that turns *business facts already committed by other modules* into **user-facing messages**, delivered across two surfaces of **one** domain:

- **Push** — transient delivery to the user's devices via FCM.
- **In-app inbox** — the durable notification history rendered in the Flutter `NotificationScreen` (S-18).

It **owns no business truth of its own.** It never opens barriers, changes subscriptions, mutates rosters, or writes to another module's tables. It reacts to domain events and produces `notifications` rows + push sends. (Constitution R-ARCH-06/07.)

## 2. Domain Boundaries

### Belongs to Notifications
- The `notifications`, `notification_templates`, `notification_template_locales`, `notification_campaigns`, `user_notification_settings` tables (DB Arch §7 + the new §7.5 — see [DATABASE_PLAN](NOTIFICATIONS_DATABASE_PLAN.md)).
- `NotificationDispatcher`, `PushClient` (the FCM seam behind the `IntegrationsServiceProvider` interface per R-ARCH-08; concrete `FcmPushClient`), notification Jobs, notification Listeners, notification Policies.
- The push-token **read/registration/invalidation lifecycle** as it pertains to delivery (the `user_devices.push_token` columns are **owned by Auth/Users** per DB Arch §1.3 — Notifications only reads them and flags `push_invalid`).
- Admin-initiated campaigns ([ADMIN_SPEC](NOTIFICATIONS_ADMIN_SPEC.md)).

### Does NOT belong to Notifications
- Opening barriers, command state machines, Traccar (**DeviceComm**).
- Subscription lifecycle, billing (**Subscriptions/Payments**).
- Visitor-link issuance, validation, usage accounting (**Visitor**).
- User/device identity, `user_devices` schema ownership, auth sessions (**Auth/Users**).
- Real-time WebSocket/Reverb broadcasting (separate concern; not push).

### Relationships (consumer only — never a direct call; R-ARCH-07)

| Module | Notifications relationship |
|---|---|
| **DeviceComm / OpenCommand** | Consumes `OpenCommandCompleted`. Barrier-opened notifications fire **only** on `state = Opened` (actuation-confirmed for VL110C; Constitution Principle 4). Filtered to `source = Visitor` for MVP. See [INVENTORY](NOTIFICATIONS_INVENTORY.md). |
| **Visitor** | No direct dependency. The owning resident is resolved from the completed command's `metadata.visitor_link_id`. Mirrors the existing `IncrementVisitorUsageOnOpenCompleted` listener pattern (which keys on the same signal). |
| **Subscriptions** | Consumes `SubscriptionExpiringSoon`, `SubscriptionExpired`, `SubscriptionActivated`, `SubscriptionRenewed` (Backend Arch event matrix; Tech Spec §17.2 templates). |
| **Admin** | Admin-initiated campaigns enter through an Admin API → the **same** `NotificationDispatcher`. Gated by `notifications.view` / `notifications.send` (RBAC). Audience is resolved against the **residents** directory + **complex** entity. |
| **Flutter / mobile** | Registers push tokens, receives push, renders the inbox, resolves deep links. See [MOBILE_FLOW](NOTIFICATIONS_MOBILE_FLOW.md). |

## 3. Delivery Pipeline (canonical, Tech Spec §17.3)

```
Domain event (committed truth)          Admin API (compose + confirm)
   OpenCommandCompleted[Opened,Visitor]     POST /admin/v1/notifications
   SubscriptionExpiring/Expired/…                 │  (notifications.send)
        │  synchronous Listener (thin)            │
        ▼                                          ▼
   dispatch queued SendPushNotificationJob ──►  NotificationDispatcher
        (Horizon queue: `notifications`)          │  idempotency (user_id, dedupe_key, channel)
                                                   ├─ write per-channel `notifications` rows (inapp always; push when selected)
                                                   ├─ resolve recipient's active user_devices (revoked_at NULL, push_invalid=false)
                                                   ├─ PushClient.send(notification{title,body} + data{type, notification_id, ids})  ← push channel
                                                   └─ FCM UNREGISTERED → user_devices.push_invalid = true  (soft)
                                                          │
                                                          ▼
                                                   FCM → Android / iOS (all devices) · inapp row → NotificationScreen
```

- Listeners are **thin + synchronous**; the real work is a **queued Job** on the existing Horizon `notifications` queue (Constitution R-ARCH-05; no push inside the request lifecycle).
- The **channel bitmask** (`config/domain/notifications.php`, `notification_templates.default_channels_mask`) selects *which* channels a template fans out to; each selected channel becomes a **separate `notifications` row** (identity model locked in [INVARIANTS](NOTIFICATIONS_INVARIANTS.md) R-NOT).

## 4. Push + In-App Inbox = One Domain, Two Surfaces

Push delivery and the in-app inbox are **not two systems.** A single notification produces:
- an **`inapp`** row (durable history → `NotificationScreen`), and
- a **`push`** row (transient FCM delivery), when the template/campaign selects the push channel.

Both are `notifications` rows of the **same** record set, keyed by the same `user_id` + `dedupe_key`, differing only by `channel`. The inbox is the durable projection; push is the delivery nudge. (DB Arch §7.3.)

## 5. Firebase / FCM Responsibilities vs Backend

| Concern | FCM (Firebase) | Our backend |
|---|---|---|
| Device token issuance/rotation | ✅ issues + rotates | reads via app; stores in `user_devices.push_token` |
| Transport to Android/iOS (+ APNs) | ✅ (FCM fronts APNs) | — |
| Delivery attempt + retry to the OS | ✅ | — |
| Token validity feedback (`UNREGISTERED`) | ✅ reports | acts on it → `push_invalid=true` (D11) |
| **Who to notify (recipient resolution)** | — | ✅ backend (`user_id` → active `user_devices`) |
| **What/when (business trigger, dedupe, content)** | — | ✅ backend (events, `NotificationDispatcher`, templates/campaigns) |
| **Durable history / inbox / read state** | — | ✅ backend (`notifications`) |
| **Payload policy (display content + routing ids; no secrets/PII)** | — | ✅ backend (see §5.1; R-NOT-17) |

FCM is a **dumb transport**. All identity, targeting, content, idempotency, and history remain backend responsibilities. FCM sits behind the `PushClient` interface bound in `IntegrationsServiceProvider` (concrete `FcmPushClient`, with a `FakePushClient` double) (R-ARCH-08).

### 5.1 Two payloads: persisted record vs FCM transport  *(Revision-1)*

A notification exists as **two payloads** that MUST NOT be conflated (security boundary: [INVARIANTS R-NOT-17](NOTIFICATIONS_INVARIANTS.md)):

1. **Persisted — `notifications.payload`** (DB Arch §7.3). The durable rendered record that drives the **in-app inbox** and is the **source** for building the FCM message. Carries `title`, `body`, `type`, and deep-link entity ids. No secrets/tokens; no PII beyond what the title/body legitimately show.
2. **FCM transport message — `notification{title,body}` + `data: { type, notification_id, ids }`** (what `PushClient` sends). The **`notification` block carries the display content** so the OS renders it in the **background/terminated** tray on Android **and iOS** (iOS requires the `notification` block for reliable terminated display); the **`data` block** carries routing/identity for tap handling + foreground construction. In `data`, **`notification_id`** is our `notifications` DB row id (**not** an entity id — R-NOT-17 rationale), and **`ids`** is an object of the deep-link **target entity ids** (per-type shape in [MOBILE_FLOW §3](NOTIFICATIONS_MOBILE_FLOW.md)).

The earlier shorthand *"payload = ids + type only"* is **withdrawn**: a background/terminated push cannot reliably display a title/body from `data` alone (especially iOS), so the display content travels in the FCM `notification` block. Sensitive/authoritative detail is **not** in the message — it is fetched authenticated **on tap** ([MOBILE_FLOW §3](NOTIFICATIONS_MOBILE_FLOW.md)). Per-state display sourcing is defined in [MOBILE_FLOW §2](NOTIFICATIONS_MOBILE_FLOW.md).

## 6. Internal Components (elaborates Backend Arch §14.9)

- **`NotificationDispatcher`** — the single entry point: `dispatch(recipient, type/template|campaign, payload, dedupeKey, channels)`. Enforces idempotency, writes per-channel rows, enqueues delivery. Called only from Listeners and the Admin dispatch path.
- **`PushClient`** (interface, bound in `IntegrationsServiceProvider` per R-ARCH-08) with concrete **`FcmPushClient`** (reads `config/integrations/fcm.php`; FCM HTTP v1, service-account auth) and test double **`FakePushClient`**.
- **`SendPushNotificationJob`** — queued (`notifications`), performs FCM fan-out across the recipient's devices via `PushClient` + records per-channel outcome (`status`, `failure_reason`).
- **Listeners** — one thin listener per consumed event (e.g. `SendVisitorOpenedNotification`), wired in `Notifications/ModuleServiceProvider::boot()` via `Event::listen` (R-ARCH-02 pattern).
- **Models** — `Notification`, `NotificationTemplate`, `NotificationTemplateLocale`, `NotificationCampaign`.
- **Policies** — `NotificationPolicy(view, markRead)` (recipient), `NotificationTemplatePolicy` (super admin), `NotificationCampaignPolicy` (`notifications.view/send`). (Backend Arch §14.9.)

## 7. Communication Rules (locked)

- Notifications **reacts to events only**; feature code MUST NOT call it directly (R-ARCH-07).
- It MUST NOT reach into another module's models/jobs/migrations (R-ARCH-06); recipient/complex data is read via the owning module's public surface / read models.
- DB transactions only inside services (R-ARCH-05).

## 8. Scope Exclusions (restated, binding)

- **UMKa — completely out of scope.** No UMKa event/listener/webhook/migration/abstraction/future placeholder. Device path is **VL110C + Traccar + OpenCommand** only.
- **SMS + email** — future channels; identity model already carries them (no redesign to add later).
- **Notification-preferences UI** — deferred; `user_notification_settings` schema preserved (DB Arch §7.4).
- **`device.offline`** — deferred (no reliable emitted offline event today; do not invent one).

## 9. Cross-References
Invariants → [INVARIANTS](NOTIFICATIONS_INVARIANTS.md) · Schema → [DATABASE_PLAN](NOTIFICATIONS_DATABASE_PLAN.md) · Types → [INVENTORY](NOTIFICATIONS_INVENTORY.md) · Admin → [ADMIN_SPEC](NOTIFICATIONS_ADMIN_SPEC.md) · Mobile → [MOBILE_FLOW](NOTIFICATIONS_MOBILE_FLOW.md) · Roadmap → [IMPLEMENTATION_PLAN](NOTIFICATIONS_IMPLEMENTATION_PLAN.md).
