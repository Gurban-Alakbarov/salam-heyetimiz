# Notifications — Implementation Plan (roadmap only)

**Version:** 0.1 (DRAFT — pending documentation-set sign-off)
**Status:** DRAFT **roadmap**. This is a reviewed, file-by-file plan **only**. It authorises **no** code, migration, dependency, native config, or deploy. Implementation starts only on a separate explicit "go".
**Date:** 2026-08-11
**Depends on:** the entire `docs/notifications/` set + [RECONCILIATION](NOTIFICATIONS_RECONCILIATION.md) · IMPLEMENTATION_ROADMAP (phase conventions).
**Scope guard:** push + inapp · FCM · **VL110C/Traccar/OpenCommand only** · **UMKa excluded** · sms/email, preferences UI, `device.offline`, admin scheduling = future.

---

## 1. Phase map

| Phase | Goal | Gate |
|---|---|---|
| **0** | Firebase/APNs provider prep + inputs | inputs received (§5) |
| **1a** | Android native FCM + Flutter token pipeline | token registers on a real Android device |
| **1b** | Backend Notification core (dispatcher, PushClient/FcmPushClient, schema) | fake-FCM unit green |
| **2** | First notification E2E — `visitor_link_used` | real-device E2E |
| **3** | Flutter receive + deep-link + inbox + badge | fg/bg/terminated verified |
| **4** | Admin campaigns (backend + React UI) + subscription notifications | admin send E2E |
| **5** | iOS native + TestFlight + visitor-expired sweeper | TestFlight push on real iPhone |

**Real dependency order:** Android native (1a) is required for tokens to exist; backend (1b) runs in parallel; iOS + TestFlight (5) is genuinely last. Everything ships behind `FeatureFlag.push`.

## 2. File-by-file plan

### Backend — new
`app/Domain/Notifications/`:
- `Models/Notification.php`, `Models/NotificationTemplate.php`, `Models/NotificationTemplateLocale.php`, `Models/NotificationCampaign.php`
- `Enums/NotificationChannel.php`, `Enums/NotificationCategory.php`, `Enums/CampaignStatus.php`
- `Services/NotificationDispatcher.php` (dispatch + idempotency + per-channel rows + enqueue)
- `Services/CampaignDispatcher.php` (audience resolve → distinct user_id → chunked jobs)
- `Contracts/PushClient.php` (interface) + `Adapters/FcmPushClient.php` (+ `FakePushClient` double, bound in `IntegrationsServiceProvider`)
- `Jobs/SendPushNotificationJob.php` (queue `notifications`)
- `Listeners/SendVisitorOpenedNotification.php` (+ subscription listeners in Phase 4)
- `Policies/NotificationPolicy.php`, `NotificationCampaignPolicy.php`
- `app/Http/Api/V1/Controllers/Notifications/NotificationController.php` (inbox + token endpoints)
- `app/Http/Admin/V1/Controllers/Notifications/AdminNotificationController.php`
- `app/Http/Resources/NotificationResource.php`, `NotificationCampaignResource.php`
- migrations (batch **`11_notifications`** — new, after deployed `10_access`): `notification_templates`, `notification_template_locales`, `notification_campaigns` (no `channels_mask` — fixed push+inapp, D1/R-NOT-04), `notifications` (partitioned, `campaign_id` inline), `user_notification_settings`
- seeders: `NotificationTemplatesSeeder` (initial live types + az/ru/en locales)

### Backend — changed
- `app/Domain/Notifications/ModuleServiceProvider.php` (bindings + `Event::listen` wiring)
- `routes/api.php` (`PUT`/`DELETE /v1/notifications/push-token`; `GET /v1/notifications`; `POST /v1/notifications/{id}/read`; unread via list `unread_count`), `routes/admin.php` (campaign endpoints)
- `app/Http/Api/V1/Controllers/BootstrapController.php` (`unread_notifications_count` → real)
- `app/Domain/Auth/Services/UserDeviceService.php` (token endpoint reuse), logout controller (de-register)
- **Untouched:** OpenCommand/Traccar/Visitor/Subscription business logic, `/v1/traccar/forward`, `user_devices` schema. No UMKa anything.

### Flutter — new
- `lib/features/notifications/data|domain` (dto/entity/repository/datasource), `notifications_providers.dart`
- `lib/core/services/push_service.dart` (FCM init, token, receive handlers), deep-link handler

### Flutter — changed
- `pubspec.yaml` (`firebase_core`, `firebase_messaging`, `flutter_local_notifications`)
- `bootstrap.dart` (FCM init), `device_info_service.dart` (real `pushToken`), `auth_remote_datasource.dart` (token endpoint), logout, `notification_screen.dart` (real feed), `home_shell.dart`/`profile_screen.dart` (badge), `app_router.dart` (deep-link routes)

### Native
- **Android:** `AndroidManifest.xml` (INTERNET, POST_NOTIFICATIONS, channel meta-data), `app/build.gradle.kts` + `settings.gradle.kts` (google-services plugin), `google-services.json`, notification icon. **applicationId unchanged** (`com.salamheyetimiz.salam_mobile`).
- **iOS:** `AppDelegate.swift`, `Info.plist` (UIBackgroundModes remote-notification), `Runner.entitlements` (aps-environment), **Podfile** (create + `pod install`), `GoogleService-Info.plist`.

### Admin UI (React)
- New "Send Notification" page + API hooks (reuse `useResidents` picker + `ConfirmDialog`).

## 3. Backend / Flutter / DB / Native split

| Task | Backend | Flutter | DB | Native |
|---|---|---|---|---|
| Token pipeline | endpoint (reuse `user_devices`) | firebase pkgs, get/refresh/logout | — | Android FCM |
| Notification core | dispatcher+job+FcmPushClient | — | `campaigns` + `campaign_id` | — |
| Visitor-opened #1 | listener | — | template seed | — |
| Receive + deep-link | — | handlers + router | — | — |
| Inbox + badge | inbox API + real unread | screen + badge | — | — |
| Admin campaigns | admin API + dispatcher | — | — | — |
| Admin UI | — | (React admin-ui) | — | — |
| iOS enablement | — | — | — | iOS APNs/Podfile |

## 4. Risks (roadmap)
- iOS/APNs complexity (no Podfile today) — largest effort → Phase 5.
- Token staleness → `push_invalid` handling (R-NOT-14).
- Duplicate sends → `dedupe_key` mandatory (R-NOT-05).
- All-users fan-out load → chunked jobs + `notifications` queue.
- INTERNET permission may be missing from the release manifest → verify.
- Deep-link to expired entity → graceful fallback (Mobile §3).
- Existing flows (DeviceComm/Traccar/Visitor/Subscription) — additive listeners only; no business-logic edits.

## 5. Required inputs (Phase 0) — from the product owner
Firebase project · `google-services.json` (Android `com.salamheyetimiz.salam_mobile`) · iOS Bundle ID (finalize) + `GoogleService-Info.plist` · APNs Auth Key `.p8` (+ Key ID, Team ID) · FCM service-account JSON + `FCM_PROJECT_ID`. **Secrets never committed** (`.gitignore` + secure env/CI).

## 6. Test plan (per phase)
- **Backend unit/feature:** notification creation, idempotency (`dedupe_key`), token register/update, invalid-token → `push_invalid`, multi-device fan-out (3 devices → 3 sends, 1 recipient row/channel), authorization, admin send + scope + permission, `OpenCommandCompleted[Opened,Visitor]` → notification.
- **Flutter:** token register/refresh, fg/bg/terminated handlers, tap, deep-link, unread badge, read state.
- **E2E (real Android):** Resident A ← Visitor B opens (VL110C `Opened`) → A gets push → inbox shows it → tap → correct screen. Then **iOS/TestFlight** on a real iPhone.

## 7. Deployment (roadmap)
Per-phase, independently releasable behind `FeatureFlag.push`. Backend: existing deploy pattern (backup → migrate → `view:clear` → php-fpm/horizon restart → smoke); `notifications` queue already provisioned. Native secrets in secure store, not the repo.

## 8. Explicit non-goals of this document
No code, no migration, no dependency install, no Firebase setup, no native change, no deploy, no APK. **Implementation begins only on a separate explicit approval.**
