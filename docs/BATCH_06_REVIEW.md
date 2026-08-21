# Batch 06 — Subscriptions — Review Package

**Version:** 1.0
**Date:** 2026-06-14
**Scope:** the Subscriptions bounded context only (entitlement lifecycle, subscription periods,
activation, manual renewal, auto-renew toggle, expiry sweep, reminders, device-access eligibility /
§13.9 suspension reasons, OrderPaid / OrderRefunded / OrderPartiallyRefunded reactions, §14.5.1
pro-rata refund math, tier-aware renewal pricing). **No Auth code** (next batch) was written.
**Sources of truth:** PROJECT_CONSTITUTION.md, TECHNICAL_SPECIFICATION §13/§14.5.1,
DATABASE_ARCHITECTURE §6, BACKEND_ARCHITECTURE §14.7, openapi/v1.yaml.

## Verification (run on Windows / PHP 8.2.12 / MariaDB 10.4.32)

| Step | Result |
|---|---|
| `php artisan migrate` (batch 06) | ✅ 2 tables created (`subscriptions`, `subscription_periods`); `Nothing to migrate` on re-run |
| `php artisan test` (full suite) | ✅ **77 passed, 218 assertions**, 0 failed (30 Payments + **47 Subscriptions**) |
| `php artisan route:list` | ✅ 43 routes total; **5 new subscription routes** |
| `php artisan schedule:list` | ✅ `subscriptions:send-renewal-reminders` 01:00, `subscriptions:expire` 02:00 (Asia/Baku) |
| `php docs/openapi/validate.php` | ✅ green (100 operationIds, 96 refs resolve, 26 tags) |

---

## 1. File tree (batch 06)

```
app/
├── Console/Commands/Subscriptions/
│   ├── ExpireSubscriptionsCommand.php          # subscriptions:expire (daily 02:00 Asia/Baku)
│   └── SendExpiryRemindersCommand.php           # subscriptions:send-renewal-reminders (daily 01:00)
├── Domain/Subscriptions/
│   ├── ModuleServiceProvider.php                # SubscriptionPolicy + OrderPaid/Refunded/PartiallyRefunded listeners
│   ├── Enums/                                   # 5: SubscriptionStatus, SubscriptionTier,
│   │                                            #     SubscriptionPeriodKind, ReminderKind, SuspensionReason
│   ├── Models/                                  # 2: Subscription, SubscriptionPeriod
│   ├── Support/                                 # 3: RefundProration, RefundProrationResult,
│   │                                            #     SubscriptionTerm  (pure calculators, §13.6 / §14.5.1)
│   ├── DTOs/                                    # 3: RenewSubscriptionData, ToggleAutoRenewData (laravel-data),
│   │                                            #     DeviceAccessResult (readonly)
│   ├── Exceptions/                              # 2: SubscriptionNotEligibleForRenewal, AutoRenewUnavailable (409)
│   ├── Events/                                  # 6: SubscriptionActivated/Renewed/ExpiringSoon/Expired/
│   │                                            #     Cancelled/Refunded  (all AuditableEvent)
│   ├── Services/                                # 4: SubscriptionService (lifecycle, txn boundary R-ARCH-05),
│   │                                            #     RenewalService, AutoRenewService, ExpirySweep
│   ├── Queries/                                 # 2: SubscriptionQuery (cursor lists),
│   │                                            #     SubscriptionStatusQuery (§13.9 eligibility)
│   ├── Listeners/                               # 3: ActivateSubscriptionOnOrderPaid,
│   │                                            #     AdjustSubscriptionOnOrderRefunded,
│   │                                            #     AdjustSubscriptionOnOrderPartiallyRefunded
│   ├── Actions/                                 # 2: RenewSubscription, ToggleAutoRenew
│   ├── Jobs/                                    # 2: ExpireSubscriptionsBatchJob, SendRenewalRemindersJob
│   └── Policies/SubscriptionPolicy.php          # view/renew/toggleAutoRenew (owner) + view (admin)
├── Domain/Devices/Services/DeviceLookup.php     # EXTENDED: status(int): ?DeviceStatus (for eligibility read)
├── Http/
│   ├── Api/V1/Requests/Subscriptions/           # RenewSubscriptionRequest, ToggleAutoRenewRequest
│   ├── Api/V1/Controllers/Subscriptions/SubscriptionController.php
│   ├── Admin/V1/Controllers/Subscriptions/AdminSubscriptionController.php
│   └── Resources/                               # SubscriptionResource, SubscriptionPeriodResource,
│                                                #   SubscriptionDetailResource
database/migrations/06_subscriptions/
│   ├── 2026_06_13_060001_create_subscriptions_table.php
│   └── 2026_06_13_060002_create_subscription_periods_table.php
tests/
├── Unit/Subscriptions/        RefundProrationTest · SubscriptionTermTest · ReminderKindTest
└── Feature/Subscriptions/     ListMySubscriptionsTest · GetSubscriptionTest · RenewSubscriptionTest ·
                               ToggleAutoRenewTest · AdminListSubscriptionsTest · OrderPaidActivationTest ·
                               OrderRefundedTest · ExpirySweepTest · SubscriptionStatusQueryTest
```

**Touched (wiring):** `routes/{api,admin,console}.php` (subscription routes + 2 scheduled commands),
`lang/{az,ru,en}/errors.php` (2 new keys: `subscription_not_renewable`, `auto_renew_unavailable`),
`tests/Pest.php` (shared `makeActiveDevice` / `makeDeviceUser` / `makeSubscription` helpers).

---

## 2. Implemented endpoints (all per openapi/v1.yaml)

| operationId | Method | Path | Status | Notes |
|---|---|---|---|---|
| listMySubscriptions | GET | `/v1/subscriptions` | ✅ | cursor pagination, status filter, caller-scoped via `device_users.user_id` |
| getSubscription | GET | `/v1/subscriptions/{subscriptionId}` | ✅ | SubscriptionDetail (periods[] + latest_order); owner-only, 404 otherwise |
| renewSubscription | POST | `/v1/subscriptions/{subscriptionId}/renew` | ✅ | Idempotency-Key required → 200 Order; **409** when status ∉ {active, expired} |
| toggleAutoRenew | PATCH | `/v1/subscriptions/{subscriptionId}/auto-renew` | ✅ | disable → 200; enable → **409 `auto_renew_unavailable`** until tokenization (P2) |
| adminListSubscriptions | GET | `/admin/v1/subscriptions` | ✅ | filters `status`, `expires_within_days` (1–90) |

> **Auth guards:** routes are wired and **fail closed** — `getSubscription` / `renew` / `auto-renew`
> deny non-owners (404 / request-`authorize` 403); admin list requires an `AdminUser` (401). The
> `auth.user` / `auth.admin` JWT guards attach when the Auth module lands; tests use `actingAs`.

### Behaviours implemented
Single pending subscription per `device_user` (unique `device_user_id`) with snapshot pricing ·
**activation** on first OrderPaid (status→active, `starts_at`/`ends_at` from term, `initial` period,
`SubscriptionActivated`) · **renewal** on subsequent OrderPaid (term continues from `ends_at` within
grace, else from now — §13.6; `renewal` period; `SubscriptionRenewed`) · **manual renewal** builds a
**tier-aware** order (`item_type` = `sub_main`/`sub_additional` so Payments prices correctly) and
delegates to Payments (R-ARCH-06) · **expiry sweep** (active past `ends_at` → expired,
`SubscriptionExpired`) · **reminders** D-30/15/7/1 fired **once per threshold** via
`last_reminder_kind` rank progression (`SubscriptionExpiringSoon`) · **§14.5.1 pro-rata refund**
(full money refund → full revoke `status=refunded`, `ends_at=now`; partial → floor(price/term) per-day
removal, falls through to full revoke if remaining term zeroes; negative-amount `refund` period) ·
**§13.9 device-access eligibility** with per-caller suspension reason (real-time active check on the
primary DB, never cached — HIGH-06) · **auto-renew** toggle (P2-gated, validates an active card token
owned by the subscriber when enabling).

---

## 3. Database changes

2 tables (DB Arch §6), migrated after batch 05:

| Table | Highlights |
|---|---|
| `subscriptions` | `device_user_id` UNIQUE (`uq_subscriptions_device_user`, FK RESTRICT) · `tier`/`status` enums · `price_minor` (snapshot) · `term_days` default 365 · `starts_at`/`ends_at` · `auto_renew` + nullable `card_token_id` (FK SET NULL) · `last_reminder_kind`/`last_reminder_sent_at` markers · `cancelled_at`/`cancellation_reason` · indexes `(status, ends_at)`, `(ends_at)`, `(auto_renew, ends_at)` |
| `subscription_periods` | append-only history; `subscription_id`/`order_id` FK RESTRICT · `kind` enum (initial/renewal/extension/refund) · `period_start`/`period_end` · **signed** `amount_minor` (refunds negative) · `created_at` only (no `updated_at`) · indexes `(subscription_id, period_end)`, `(order_id)` |

All enum values, indexes, and FK `ON DELETE` rules match §6. Verified live on MariaDB 10.4.32.

---

## 4. Test results

```
PASS  Tests\Unit\Subscriptions\RefundProrationTest        (3)   §14.5.1 worked example + edge cases
PASS  Tests\Unit\Subscriptions\SubscriptionTermTest       (4)   §13.6 initial / within-grace / after-grace
PASS  Tests\Unit\Subscriptions\ReminderKindTest          (10)   threshold mapping (dataset) + rank order
PASS  Tests\Feature\Subscriptions\ListMySubscriptionsTest (2)   caller-scoped + status filter
PASS  Tests\Feature\Subscriptions\GetSubscriptionTest     (3)   detail+periods+latest_order; 404 cross-user/missing
PASS  Tests\Feature\Subscriptions\RenewSubscriptionTest   (5)   active/expired→order; pending→409; key; 404
PASS  Tests\Feature\Subscriptions\ToggleAutoRenewTest     (4)   enable→409; disable→200; validation; 404
PASS  Tests\Feature\Subscriptions\AdminListSubscriptionsTest (3) list + expires_within_days + non-admin 401
PASS  Tests\Feature\Subscriptions\OrderPaidActivationTest (2)   pending→activate; active→renew (+365d)
PASS  Tests\Feature\Subscriptions\OrderRefundedTest       (2)   full revoke; partial pro-rata −133d
PASS  Tests\Feature\Subscriptions\ExpirySweepTest         (3)   expire past-due; single-fire reminder; >30d skip
PASS  Tests\Feature\Subscriptions\SubscriptionStatusQueryTest (6) §13.9 eligibility / suspension reasons

Subscriptions: 47 passed   ·   Full suite: 77 passed (218 assertions)   ·   ~9s (MariaDB, R-CODE-09)
```

Notable cases proving the spec-critical paths:
- **§14.5.1 worked example** verified to the qəpik: 12 AZN sub, 365-day term, 4 AZN refund → 133 days
  removed → `ends_at` 2027-01-01 → 2026-08-21 (unit), and end-to-end through the admin refund endpoint
  (feature: `ends_at` moves −133 days, status stays `active`, `−400` refund period written).
- **Full money refund** (OrderRefunded) → `status=refunded`, `ends_at=now`, `−1200` refund period.
- **OrderPaid trigger** → pending activates (initial period, +365d); already-active renews (+365d from
  old `ends_at`, renewal period) — the events fire the listeners synchronously.
- **§13.9 eligibility:** active→open; disabled device→`device_disabled`; owner-expired-with-others→
  `owner_sub_expired_others_active`; lapsed-caller-nobody-else→`device_suspended`; lapsed-sub-user-with-
  others→`subscription_expired`; no roster row→no access. Active is evaluated in real time (HIGH-06).
- **Renewal eligibility:** pending-payment subscription → 409 `subscription_not_renewable`.
- **Auto-renew (P2):** enabling → 409 `auto_renew_unavailable` (no DB mutation); disabling → 200.
- **Single-fire reminders:** D-7 fires once; a second sweep at the same threshold sends nothing.

---

## 5. Coverage summary

- **Functional coverage:** every "Implement:" bullet (lifecycle, periods, activation, renewal, expiry,
  suspension rules, device-access eligibility, OrderPaid/OrderRefunded reactions, pro-rata refund,
  renewal pricing, events) has at least one passing test (see §4). The two pure calculators
  (`RefundProration`, `SubscriptionTerm`) and the reminder thresholds are unit-tested against the
  documented numbers; the lifecycle, listeners, sweep and §13.9 query are feature-tested against MariaDB.
- **Line coverage:** not measured — no coverage driver (pcov/xdebug) is installed in this XAMPP build.
  To produce a percentage: enable `pcov` and run `php artisan test --coverage --min=70` (R-CODE-09).

---

## 6. Design notes & boundaries (for review)

1. **Tier-aware renewal pricing lives in the order item type.** `RenewalService` sets the renewal
   order's `item_type` to `sub_main`/`sub_additional` (from `SubscriptionTier::renewalItemType()`), so
   the Payments `OrderPricing` charges the correct fee server-side. This finalises the batch-05
   placeholder ("`sub_renewal` priced as main"). `OrderPurpose` remains `sub_renewal`; the activation
   listener accepts `sub_main`/`sub_additional`/`sub_renewal` item types.
2. **Pro-rata math is owned by Subscriptions, not Payments** (R-ARCH-06). Payments performs the money
   refund and fires `OrderRefunded` (full) / `OrderPartiallyRefunded` (partial); this module reacts and
   adjusts entitlement time + writes the `refund` period. `moneyFull=false` still becomes a full revoke
   if the pro-rata removal zeroes the remaining term.
3. **`price_per_day` is floored** (`intdiv`) — conservative for the customer; if it floors to 0
   (`price_minor < term_days`) the refund falls through to a full revoke. Documented in `RefundProration`.
4. **Eligibility is never cached** (HIGH-06). `SubscriptionStatusQuery` re-derives "active" as
   `status = active AND ends_at > now` on the primary DB, so a lapsed-but-not-yet-swept subscription
   cannot open. This is the read the open-command pipeline (DeviceComm, batch 07) will consult.
5. **Whitelist removal / device re-derivation is NOT here.** `SubscriptionExpired` / `SubscriptionRefunded`
   are emitted; the DeviceComm reaction (remove from whitelist, recompute device status) lands in batch 07.
   This module owns entitlement time + status only.
6. **Auto-renew is P2-gated** (`config('domain.subscriptions.auto_renew_enabled') = false`). Enabling is
   rejected with 409 until Kapital card tokenization exists (R-PAY-14). The enable path (validate an
   active `card_token` owned by the subscriber, then persist `auto_renew=true` + `card_token_id`) is
   fully implemented and unblocks by flipping one config flag — **not a stub**.
7. **`extension` period kind** exists in the enum/schema for completeness (manual term grants) but is not
   emitted by any current flow; no endpoint creates it. Listed as a known no-op surface.
8. **Reminder delivery is event-only.** `SendRenewalRemindersJob` / the sweep fire `SubscriptionExpiringSoon`
   carrying the `ReminderKind`; the actual push/SMS/email send is the Notifications module's listener
   (batch 08). The single-fire progression marker (`last_reminder_kind`) is owned here.
9. **Auth guards pending** (see §2) — endpoints fail closed until the Auth module. `listMySubscriptions`
   has no unauthenticated test because, without the guard middleware, it would fault on a null user
   rather than return a clean 401 (same posture as batch-05 `listMyOrders`); the guard is the Auth batch.

---

*End of Batch 06 Review v1.0. Stopping here — Subscriptions complete, **not** starting Auth. Awaiting review.*
