# Batch 05 — Payments — Review Package

**Version:** 1.0
**Date:** 2026-06-14
**Scope:** the Payments bounded context only (orders, Kapital integration, callback hardening,
signature verification, getOrderStatus verification, idempotency, payment logs, status transitions,
refund workflow, subscription-activation trigger). **No Subscriptions code** (batch 06) was written.
**Sources of truth:** PROJECT_CONSTITUTION.md, TECHNICAL_SPECIFICATION §14, DATABASE_ARCHITECTURE §5,
BACKEND_ARCHITECTURE §6.5/§9/§14.8, openapi/v1.yaml.

## Verification (run on Windows / PHP 8.2.12 / MariaDB 10.4.32)

| Step | Result |
|---|---|
| `php artisan migrate` (batch 05) | ✅ 7 tables created (incl. partitioned `payment_logs`) |
| `php artisan test` (Unit + Feature) | ✅ **30 passed, 106 assertions**, 0 failed |
| `php artisan route:list` | ✅ 38 routes total; 10 new payment routes |
| `php docs/openapi/validate.php` | ✅ green (100 operationIds, all $refs resolve) |
| `php -l` (app + routes + database + tests) | ✅ all clean |

---

## 1. File tree (batch 05)

```
app/
├── Console/Commands/Payments/
│   └── ReconcileOrdersCommand.php              # payments:reconcile-orders (hourly)
├── Domain/Payments/
│   ├── ModuleServiceProvider.php               # policies + PaymentCallbackReceived listener
│   ├── Enums/                                   # 10: OrderStatus, OrderPurpose, OrderItemType,
│   │                                            #     PaymentType, PaymentStatus, RefundStatus,
│   │                                            #     CardTokenStatus, PaymentLogDirection,
│   │                                            #     PaymentCallbackOutcome, BankStatus
│   ├── Models/                                  # 7: CardToken, Order, OrderItem, Payment,
│   │                                            #     PaymentLog, PaymentCallback, Refund
│   ├── DTOs/                                    # OrderCreationData, OrderItemData, RefundRequestData
│   │                                            #   (laravel-data); GatewayRegisterResult,
│   │                                            #   GatewayStatusResult, GatewayRefundResult,
│   │                                            #   KapitalCallbackData (readonly)
│   ├── Adapters/                                # PaymentGateway (interface), KapitalBankClient,
│   │                                            #   FakeKapitalGateway (test-only)
│   ├── Support/                                 # KapitalSignature, OrderPricing
│   ├── Exceptions/                              # PaymentProviderUnavailable, OrderNotRefundable,
│   │                                            #   SignatureInvalid
│   ├── Services/                                # PaymentLogger, OrderService, PaymentVerifierService,
│   │                                            #   PaymentCallbackService, RefundService, OrderReconciler
│   ├── Queries/                                 # OrderQuery, RefundQuery (cursor read models)
│   ├── Actions/                                 # CreateOrder, RecheckOrder, RequestRefund,
│   │                                            #   ProcessPaymentCallback, ApplyRefund, ExpireStaleOrders
│   ├── Events/                                  # 11: OrderCreated/Authorising/Paid/Failed/Cancelled/
│   │                                            #   Expired/RefundRequested/Refunded/PartiallyRefunded,
│   │                                            #   PaymentCallbackReceived/Processed
│   ├── Listeners/HandlePaymentCallbackReceived.php
│   ├── Jobs/                                    # ProcessPaymentCallbackJob, RecheckOrderStatusJob,
│   │                                            #   ApplyRefundJob, ExpireStaleOrdersJob, PaymentLogsScannerJob
│   └── Policies/                                # OrderPolicy, RefundPolicy
├── Domain/Devices/Services/DeviceLookup.php     # minimal cross-module read for R-DOM-13
├── Http/
│   ├── Middleware/CaptureRawBody.php            # raw bytes for HMAC (CRIT-07)
│   ├── Middleware/VerifyKapitalSignature.php    # HMAC + ip-allowlist metric (CRIT-07)
│   ├── Api/V1/Requests/Orders/CreateOrderRequest.php
│   ├── Api/V1/Controllers/Orders/OrderController.php
│   ├── Admin/V1/Requests/Orders/RefundOrderRequest.php
│   ├── Admin/V1/Controllers/Orders/AdminOrderController.php
│   ├── Admin/V1/Controllers/Refunds/AdminRefundController.php
│   ├── Webhooks/Controllers/KapitalCallbackController.php
│   └── Resources/                               # OrderResource, OrderItemResource, OrderDetailResource,
│                                                #   PaymentResource, RefundResource
├── Support/Audit/AuditableEvent.php             # shared contract for the batch-09 audit listener
database/migrations/05_payments/
│   ├── 2026_06_13_050001_create_card_tokens_table.php
│   ├── 2026_06_13_050002_create_orders_table.php
│   ├── 2026_06_13_050003_create_order_items_table.php
│   ├── 2026_06_13_050004_create_payments_table.php
│   ├── 2026_06_13_050005_create_payment_logs_table.php       # raw SQL, RANGE-partitioned
│   ├── 2026_06_13_050006_create_payment_callbacks_table.php
│   └── 2026_06_13_050007_create_refunds_table.php
tests/
├── Unit/Payments/OrderPricingTest.php · KapitalSignatureTest.php
└── Feature/Payments/CreateOrderTest.php · GetOrderTest.php · PaymentCallbackTest.php ·
                     RefundTest.php · OrderReconcilerTest.php
```

**Touched (wiring):** `routes/{api,admin,webhooks,console}.php`, `bootstrap/app.php` (webhook group
+ verify.kapital alias), `app/Providers/AppServiceProvider.php` (nested-config loader + JsonResource
unwrap), `app/Providers/IntegrationsServiceProvider.php` (PaymentGateway binding),
`lang/{az,ru,en}/errors.php` (4 new keys).

---

## 2. Implemented endpoints (all per openapi/v1.yaml)

| operationId | Method | Path | Status | Notes |
|---|---|---|---|---|
| listMyOrders | GET | `/v1/orders` | ✅ | cursor pagination, status filter, payer-scoped |
| createOrder | POST | `/v1/orders` | ✅ | server-priced, Idempotency-Key, device-sale link (R-DOM-13), bank register |
| getOrder | GET | `/v1/orders/{orderId}` | ✅ | OrderDetail (payments+refunds+timeline); owner-only (R-PAY-12) |
| recheckOrder | POST | `/v1/orders/{orderId}/recheck` | ✅ | getOrderStatus re-apply |
| paymentCallback | POST | `/v1/payments/callback` | ✅ | raw-body HMAC + dedupe + getOrderStatus + PENDING handling |
| adminListOrders | GET | `/admin/v1/orders` | ✅ | filters status/purpose/payer/since/until |
| adminGetOrder | GET | `/admin/v1/orders/{orderId}` | ✅ | OrderDetail |
| adminRefundOrder | POST | `/admin/v1/orders/{orderId}/refund` | ✅ | super-admin, Idempotency-Key, 409 not-refundable |
| adminRecheckOrder | POST | `/admin/v1/orders/{orderId}/recheck` | ✅ | super-admin |
| adminListRefunds | GET | `/admin/v1/refunds` | ✅ | status filter |

> **Auth guards:** routes are wired and **fail closed** (mobile policies require an authenticated
> user; admin endpoints require an AdminUser; refund/recheck require super_admin). The `auth.user` /
> `auth.admin` JWT guards attach to these groups when the Auth module lands; tests use `actingAs`.

### Behaviours implemented
Order creation · server-side pricing · idempotency (orders.idempotency_key) · device-sale↔device link
(R-DOM-13) · in-flight dedupe per subject (R-PAY-11) · bank register/redirect · **raw-body HMAC
signature verification** (CRIT-07) · **always-getOrderStatus defence-in-depth** (R-PAY-04) · callback
`payload_hash` dedupe (R-PAY-06) · PENDING → no mutation + scheduled recheck (R-PAY-05) · allowlist +
encrypted payment_logs + daily PAN/CVV scanner (HIGH-15) · order status transitions
(pending→authorising→paid/failed/cancelled/refunded/partially_refunded/expired) · refund workflow
(request → ApplyRefundJob → bank refund → payments row → order status) · hourly reconciler + expiry
sweep (R-PAY-13) · **OrderPaid / OrderRefunded events** as the subscription-activation / pro-rata
triggers consumed by batch 06.

---

## 3. Database changes

7 tables (DB Arch §5), migrated in dependency order after batch 04:

| Table | Highlights |
|---|---|
| `card_tokens` | VARBINARY(255) encrypted token; user FK |
| `orders` | reference, purpose/status enums, idempotency unique `(payer, key)`, bank_order_id |
| `order_items` | item snapshot + server-priced amounts |
| `payments` | signed amount_minor (charge +, refund −); encrypted raw_response |
| `payment_logs` | **RANGE-partitioned monthly** (12 fwd + MAXVALUE); PK `(id, partition_key)`; **no FK** (partitioning constraint) |
| `payment_callbacks` | dedupe ledger: unique `payload_hash` + `(bank_order_id, bank_status)` |
| `refunds` | workflow intent; FK order/admins/payment |

All enum values, indexes, and FK `ON DELETE` rules match §5. Verified live on MariaDB 10.4.32.

---

## 4. Test results

```
PASS  Tests\Unit\Payments\KapitalSignatureTest        (3)
PASS  Tests\Unit\Payments\OrderPricingTest            (2)
PASS  Tests\Feature\Payments\CreateOrderTest          (7)
PASS  Tests\Feature\Payments\GetOrderTest             (4)
PASS  Tests\Feature\Payments\OrderReconcilerTest      (2)
PASS  Tests\Feature\Payments\PaymentCallbackTest      (6)
PASS  Tests\Feature\Payments\RefundTest               (6)

Tests: 30 passed (106 assertions)   Duration: ~7s   (MariaDB, R-CODE-09)
```

Notable cases proving the security-critical paths:
- **Defence-in-depth:** a callback body claiming `APPROVED` is overridden when `getOrderStatus`
  returns `DECLINED` → order ends `failed` (R-PAY-04).
- **Invalid signature** → 401 + `payment_logs.signature_valid = 0`.
- **Duplicate re-delivery** → 200, single callback row, charged once (R-PAY-06).
- **PENDING** → order unchanged + `RecheckOrderStatusJob` scheduled (R-PAY-05).
- **Ownership:** another user requesting an order → 404 (R-PAY-12).
- **Refund:** full → `refunded`; partial → `partially_refunded`; over-net → 409; non-paid → 409;
  non-super-admin → 403.

---

## 5. Coverage summary

- **Functional coverage:** every "Implement:" bullet and every batch-05 endpoint has at least one
  passing feature/unit test (see §4). The highest-risk surfaces (signature verification, callback
  idempotency, getOrderStatus authority, refund state machine, order pricing) are covered directly.
- **Line coverage:** not measured — no coverage driver (pcov/xdebug) is installed in this XAMPP
  build. To produce a percentage: enable `pcov` (or xdebug coverage) and run
  `php artisan test --coverage --min=70` (R-CODE-09 target ≥ 70% backend lines). All Payments code
  paths exercised by the suite are listed above; the untested remainder is mainly the real
  `KapitalBankClient` HTTP wiring (integration-tested against the Kapital sandbox in Phase 0/1, not
  unit-tested — the `FakeKapitalGateway` stands in for unit/feature tests per R-ARCH-08).

---

## 6. Known limitations / boundaries (for review)

1. **Kapital wire format is provisional.** `KapitalBankClient` implements the documented flow
   (register → redirect, getOrderStatus, refund, cancel) with real HTTP + HMAC signing, but the exact
   endpoint paths and field names are the Phase-0 sandbox-confirmable surface (Tech Spec §0 open
   items #3/#4). Transport, signing, parsing, error handling and logging are production-real; field
   mapping is the single place needing sandbox confirmation. **Not a mock** — the test double
   (`FakeKapitalGateway`) is test-environment-only (R-ARCH-08 / §13.1).
2. **Subscription impact deferred to batch 06.** Payments performs the *financial* refund and fires
   `OrderRefunded` / `OrderPartiallyRefunded`; the §14.5.1 pro-rata `ends_at` math and
   `subscription_periods` rows are the Subscriptions module's reaction (R-ARCH-06/07). Likewise
   `OrderPaid` is the activation trigger — no subscription/device listener exists yet (batch 06 / Devices).
3. **`payment_logs` has no `order_id` FK.** MariaDB forbids foreign keys on partitioned InnoDB tables;
   integrity is app-enforced (PaymentLogger only writes a valid order_id or NULL). Documented in the
   migration. The PK is `(id, partition_key)` as partitioning requires.
4. **`refunds.requested_by_admin_id` uses ON DELETE RESTRICT**, not the §5.7 "SET NULL" (invalid on a
   NOT NULL column; admins are never hard-deleted per §1.2). `processed_by_admin_id` keeps SET NULL.
5. **Request-level idempotency** for `createOrder` uses `orders.idempotency_key` (unique per payer);
   the generic two-tier `IdempotencyHandler` + `idempotency_keys` table is batch 09. Refund idempotency
   is at the bank-call level (`refund-{id}` key) plus state guards.
6. **Auth guards pending** (see §2) — endpoints fail closed until the Auth module.
7. **`sub_renewal` priced as the main-tier fee** as a placeholder; tier-aware renewal pricing is
   finalised in batch 06 (no subscriptions table yet).
8. **Foundation fix applied:** Laravel does not auto-load `config/domain/*` and `config/integrations/*`
   (nested dirs). Added a loader in `AppServiceProvider::register` (mergeConfigFrom, Windows-safe glob)
   so `config('integrations.kapital.*')` / `config('domain.*')` resolve in all environments — previously
   only the in-code fallbacks worked, and the Kapital HMAC secret would have been null in production.
9. **`JsonResource::withoutWrapping()`** enabled globally so single resources match the OpenAPI bare
   objects; list endpoints build the explicit `{data, page}` envelope.

---

*End of Batch 05 Review v1.0. Stopping here — awaiting review before Subscriptions (batch 06).*
