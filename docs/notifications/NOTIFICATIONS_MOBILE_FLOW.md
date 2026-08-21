# Notifications — Mobile Flow (Flutter)

**Version:** 0.1 (DRAFT — pending documentation-set sign-off)
**Status:** DRAFT. Elaborates the Flutter doc space; no canonical doc modified.
**Date:** 2026-08-11
**Depends on:** [NOTIFICATIONS_RECONCILIATION.md](NOTIFICATIONS_RECONCILIATION.md) · `docs/flutter/NAVIGATION.md`, `SCREEN_FLOW.md` (S-18), `FEATURE_FLAGS.md`, `FLUTTER_ARCHITECTURE.md` · [INVARIANTS](NOTIFICATIONS_INVARIANTS.md) R-NOT-08/11/12/14/17.
**Scope guard:** push + inapp · FCM only · deep-links reuse existing routes · UMKa irrelevant (device path is backend-side).

---

## 1. Token lifecycle (client side of R-NOT-08/09/14)

| Step | Behaviour |
|---|---|
| **Init** | `Firebase.initializeApp` in `bootstrap.dart`; behind `FeatureFlag.push`. |
| **Permission** | iOS `requestPermission`; Android 13+ `POST_NOTIFICATIONS` runtime prompt. |
| **Obtain** | `FirebaseMessaging.getToken()` → populate `DeviceInfoService.pushToken` (today always null). |
| **Register** | `PUT /v1/notifications/push-token` `{push_token}` (`upsertPushToken`) → backend upserts the **current** `user_devices` row. The install is resolved from the JWT `fp` claim (= `install_uuid`) — **no** `install_uuid` in the body. Additive to the verify-email `device` payload — **verify-email flow unchanged**. |
| **Refresh** | `onTokenRefresh` → the **same** `PUT /v1/notifications/push-token` upsert → `push_token` + `push_token_updated_at`; clears `push_invalid` (reactivation). Register and refresh share the one upsert endpoint. |
| **Logout** | `DELETE /v1/notifications/push-token` (no request body; current install from the JWT `fp` claim) — clears **only** this install's `push_token`; other `user_devices` rows are untouched (multi-device preserved). Called on logout. |
| **Multi-device** | each install = its own `user_devices` row; the user receives on **all** active devices (R-NOT-08). |

## 2. Message shape & receive flow — all app states (Reconciliation §12, Revision-1)

Every push is sent as a **`notification{title,body}` + `data: { type, notification_id, ids }`** hybrid (see [ARCHITECTURE §5.1](NOTIFICATIONS_ARCHITECTURE.md)). `notification_id` is our `notifications` DB row id; `ids` is an object of the deep-link **target entity ids** (per-type shape in §3). This is what makes background/terminated display reliable on both platforms.

| State | Mechanism | **Where title/body come from** | Tap |
|---|---|---|---|
| **Foreground** | `onMessage` (OS does not auto-display) | app builds a local notification via `flutter_local_notifications` from the message's `notification`/`data` | route by `data` |
| **Background** | OS auto-displays the `notification` block in the system tray | **FCM `notification` block** | `onMessageOpenedApp` → route by `data` |
| **Terminated** | OS auto-displays the `notification` block; app cold-starts on tap | **FCM `notification` block** | `getInitialMessage()` → route by `data` |
| **Arrived, user elsewhere** | `onMessage` | local notification + badge increment | **do not force-navigate** |

**Why `flutter_local_notifications`:** (1) render while the app is foreground (FCM does not auto-display then); (2) create/manage the Android **notification channel** (id/importance); (3) present the local copy consistently. It is a display helper — not a second notification system.

**Display vs detail:** the title/body shown in the tray are the FCM `notification` content (already the final display text). Rich or sensitive **detail** is never in the message; it is fetched authenticated on tap (§3). Security boundary: [INVARIANTS R-NOT-17](NOTIFICATIONS_INVARIANTS.md).

## 3. Deep-link routing (Reconciliation §12; R-NOT-17)

The FCM `data: { type, notification_id, ids }` carries no secrets/PII beyond the display title/body (R-NOT-17). `notification_id` is our `notifications` DB row id (**not** an entity id); **`ids`** holds the deep-link **target entity ids**. Tap → `go_router` route (by `data.type`) → **authenticated fetch of detail** using `ids` (the title/body were already displayed; the fetch retrieves the rich/authoritative record). **Reuse existing routes**; add the minimum.

| `type` | `data.ids` | Route (existing where possible) | Fetch |
|---|---|---|---|
| `visitor_link_used` | `{ visitor_link_id, device_id }` | device detail / visitor screen | by `visitor_link_id` / `device_id` |
| `subscription_*` | `{ subscription_id }` | Active Subscriptions screen | by `subscription_id` |
| `device_*` | `{ device_id }` | device detail | by `device_id` |
| `system_announcement` | `{ }` (route via `notification_id`) | `NotificationScreen` | notification row (by `notification_id`) |

**Graceful degradation:** if the target entity is expired/deleted (fetch 404), fall back to `NotificationScreen` (or a neutral message) — never crash.

## 4. In-app inbox — `NotificationScreen` becomes real (S-18)

Today it is an empty state. It becomes a real center backed by the notification feature (mirroring the devices/subscriptions feature architecture: datasource/DTO/entity/repository/provider/screen).

- **Data:** `GET /v1/notifications` (cursor pagination) — the recipient's `inapp` rows; `POST /v1/notifications/{id}/read`.
- **UI:** unread/read state, category, timestamp, title, body, tap action (§3), **empty / loading / error / cursor-pagination** states (reuse existing state widgets).
- **Read:** opening/marking sets `read_at` (R-NOT-11) → decrements the badge.

## 5. Badge & unread count (single shared state)

- One **shared** notification state (a single provider) drives both the app-bar bell (Home) **and** the Profile "Bildirişlər" row — **do not create two states** (Reconciliation §11-bis).
- Source: `GET /v1/me.unread_notifications_count` (now a **real** value — R-NOT-12) + local invalidation on open/read.

## 6. Feature-flag gating

- All of the above ships behind the existing **`FeatureFlag.push`** / `FeatureFlag.notifications` (currently `false`) — enabled per phase. Existing screens/behaviour are unaffected while off.

## 7. Canonical edits this flow will require (post-approval)
`docs/flutter/NAVIGATION.md` (deep-link routing table), `SCREEN_FLOW.md` (S-18 real inbox behaviour). No change to `FEATURE_FLAGS.md` (flags already defined). (Tracked in [RECONCILIATION §C](NOTIFICATIONS_RECONCILIATION.md).)
