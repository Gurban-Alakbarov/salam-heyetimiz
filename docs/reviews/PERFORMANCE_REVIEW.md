# PERFORMANCE REVIEW

> Pre-Flutter audit. **Read-only.** Focus: N+1, inline I/O on the request path, pagination, indexes, cache/queue/Redis opportunities.

---

## 0. Verdict

**Performance grade: GOOD (7.5/10).** Pagination is correct everywhere (cursor), indexes cover the hot paths, rate-limit + OTP-throttle already use the cache (Redis in prod). The two real items are (1) **N+1 on two admin device endpoints** and (2) **inline email/SMS on the auth request path**. Neither affects the mobile read paths; both are straightforward to fix.

---

## 1. N+1 queries

### PERF-1 — Admin device LIST: COUNT-per-device (HIGH, admin-only)
`AdminDeviceController::index()` → for every device in the page it runs a separate `DeviceUser…count()` to compute `whitelist_used`. **100 devices = ~101 queries.**
**Fix:** one grouped query — `DeviceUser::whereIn('device_id',$ids)->where('status','active')->groupBy('device_id')->selectRaw('device_id,count(*) c')->pluck('c','device_id')` — then attach. Admin-only endpoint, so not user-facing, but should be fixed.

### PERF-2 — Admin device DETAIL: subscription-per-roster-member (HIGH, admin-only)
`AdminDeviceController::show()` → for each roster member it calls `SubscriptionQuery::forDeviceUser()` (one query each). A device with N residents = N+ queries.
**Fix:** eager-load `deviceUsers.subscription` or batch with `whereIn('device_user_id',$ids)->keyBy(...)`.

### PERF-3 — Refund list loads full linkedPayment (MEDIUM)
`RefundQuery::adminList()` eager-loads `linkedPayment` (a `Payment` that may carry a large `raw_response_encrypted` blob) for every row. Memory bloat on large pages.
**Fix:** select only the needed columns: `->with(['linkedPayment:id,…,raw_response_encrypted'])`, or load the blob lazily only when the expandable detail is opened.

> All other admin + mobile list endpoints (orders, refunds, residents, subscriptions, payment-logs, devices) are cursor-paginated with appropriate eager-loads. No N+1 found there.

---

## 2. Inline I/O on the request path (HIGH — biggest UX/scale item)

### PERF-4 — OTP email + SMS sent synchronously
- `RegisterUser` / `RequestEmailLogin` / `ResendEmailOtp` → `OtpService::issueToEmail` → `TemplatedMailer` → `RuntimeMailer` → **live SMTP** (Brevo).
- `SendOtp` (phone) → `SmsOtpTransport` → **live HTTP** to the SMS provider.

Every register/login/resend request **blocks a worker on an external network call** (SMTP up to ~30s on a bad day; SMS HTTP up to its timeout). Under load this serialises and exhausts concurrency, and a transient mail/SMS outage fails the user.
**Fix (no contract change — the endpoints already return 202):** dispatch a queued `SendEmailOtpJob` / `SendSmsOtpJob` after persisting the OTP; the worker retries with backoff. **This is the top performance recommendation before launch-scale.** (In dev `QUEUE_CONNECTION=sync` so it runs inline locally — confirm prod is `redis`.)

### PERF-5 — Traccar telemetry ingestion synchronous (MEDIUM)
`TraccarForwardController` ingests each position/event webhook synchronously (device lookup + diagnostics write + open-command confirmation + online-state update). At fleet scale (many devices, frequent reports) this serialises webhook workers.
**Fix:** queue `IngestTraccarPositionJob`, return 200 immediately. (Note: the BirPay payment webhook already persists synchronously + queues processing — good pattern to mirror.)

---

## 3. Cache / Redis opportunities (Low–Medium)

| # | Spot | Note |
|---|---|---|
| PERF-6 | **Guest bootstrap** reads ~8 settings per call; called on every cold app launch. | Cache the assembled guest payload for ~1h, bust on settings write. Medium (high call volume). |
| PERF-7 | **`/v1/me`** runs `hasActiveForUser` + `forUser('active')` (2 subscription queries) per launch. | Coalesce into one query (active list → `has_active = count>0`); optionally cache per-user 5 min. Medium. |
| PERF-8 | **Payment stats** dashboard runs ~7 aggregate queries per call. | `Cache::remember('payment_stats',300,…)`; admin-only/low-traffic. Low. |
| PERF-9 | **ApplyRuntimeSettings** middleware overlays 9 settings → config every request. | The settings map is already `Cache::rememberForever` (1 Redis read), but it calls `value()` 9×. Fetch the map once + overlay in a single pass. Low. |

Already correct: `OtpThrottle` (Redis counters), HTTP rate limiters (Redis in prod). Settings map cached.

---

## 4. Indexes & pagination — GOOD
Hot-path indexes verified (see `DATABASE_REVIEW §3`). All lists cursor-paginated with a consistent `{data,page}` contract. No unbounded `->get()/->all()` on a user-facing list found.

---

## 5. Queue posture
Production must run `QUEUE_CONNECTION=redis` + Horizon (the repo ships `salam-horizon`). Local is `sync` by design. Once PERF-4/PERF-5 are queued, the request paths become fast + resilient. **Confirm prod queue + worker health as a go-live item.**

---

## 6. Summary

| Severity | Count | Items |
|---|---|---|
| High | 3 | PERF-1, PERF-2 (admin N+1), PERF-4 (queue email/SMS) |
| Medium | 3 | PERF-3 (refund blob), PERF-5 (queue Traccar ingest), PERF-6/7 (bootstrap + /me cache) |
| Low | 2 | PERF-8 (stats cache), PERF-9 (settings overlay) |

**No performance issue blocks Flutter** (the mobile read paths are paginated + indexed). **PERF-4 (queue email/SMS)** is the one to do before real launch traffic; PERF-1/2 are admin-only and can follow.
