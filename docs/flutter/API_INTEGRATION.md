# API INTEGRATION

> Planning only. The HTTP layer that talks to the frozen `/v1` backend. Base URL per flavor (`https://api.salamheyetimiz.com` prod). All times UTC, money in minor units, currency AZN.

---

## 1. Client — Dio + a thin `ApiClient`

**Decision: `dio`** (interceptors, cancel tokens, typed options, multipart, good error model). A thin `ApiClient` wraps Dio and exposes typed methods; repositories depend on `ApiClient`, never on Dio directly.

```
Repository → ApiClient(dio) → [interceptor chain] → backend
                                     │
              Auth · Refresh · Retry · Connectivity · Logging · ErrorMapper
```

Per-flavor config: `baseUrl`, connect/receive/send timeouts, `Accept-Language` (current locale), `X-App-Version`/`X-Platform` headers (so the backend force-update logic can use them later).

---

## 2. Interceptor chain (order matters)

1. **ConnectivityInterceptor** — if offline and the request isn't queue-eligible, fail fast with a `NoConnection` failure (UI → Offline/retry); queue-eligible writes go to the offline queue (see `OFFLINE_STRATEGY.md`).
2. **AuthInterceptor** — attach `Authorization: Bearer <access>` to authed requests (skip for `bootstrap`, `register`, `verify-email`, `resend-otp`, `login`, `refresh`, `health`).
3. **RefreshInterceptor (single-flight)** — on **401**: pause the failing request, run `POST /v1/auth/refresh` **once** (a shared `Future` so concurrent 401s wait on the same refresh), store the rotated pair, replay the queued requests with the new token. If refresh fails (expired/revoked/reuse-detected) → clear session, flip `authState → guest`, surface "session expired". **Never** loop refresh on the refresh endpoint itself.
4. **RetryInterceptor** — idempotent GETs only: retry on network error / timeout / 502/503/504 with exponential backoff + jitter (e.g. 3 attempts). **Never** auto-retry POST/PATCH/DELETE (avoid double-open / double-charge); the barrier-open + order flows are idempotent server-side but the client still doesn't blind-retry them.
5. **LoggingInterceptor** — redacted request/response logging in dev/qa only (never logs tokens, OTP codes, or bodies in prod).
6. **ErrorMapperInterceptor** — converts `DioException` + the unified error envelope into a typed `Failure` (below).

---

## 3. Response handling — two shapes, one normaliser

The backend returns two relevant shapes; the data layer normalises both so features only see Entities:

- **Unified envelope** `{success, message, data, meta, errors}` — register/verify-email/resend-otp/login/bootstrap/me. Parser: on `success=false`, build a `Failure` from `errors` + `message`; on success, map `data` (+ `meta` for timings/pagination).
- **Legacy/list** — `{access_token,…}` (refresh), `{data:[…], page:{next_cursor,has_more,limit}}` (lists), bare `JsonResource` (single), `204` (void). Each has a dedicated DTO.

A `ResponseEnvelope<T>` codec + per-endpoint DTOs (freezed + json_serializable) keep this contained. **Mappers** convert DTO → Entity so the UI is insulated from the dual shape (the dual envelope never reaches presentation).

---

## 4. Error model → typed `Failure`

```
sealed Failure:
  NetworkFailure(noConnection|timeout)
  UnauthorizedFailure        // 401 (post-refresh) → session expired
  ForbiddenFailure           // 403
  NotFoundFailure            // 404
  ConflictFailure(code)      // 409 (e.g. email_already_registered)
  ValidationFailure(fields)  // 422 (errors.{field:[…]})
  RateLimitedFailure(retryAfter) // 429
  OtpFailure(code)           // 401 wrong_code/otp_expired/otp_max_attempts
  ServerFailure              // 5xx
  MaintenanceFailure / ForceUpdateFailure  // derived from bootstrap gates
  UnknownFailure
```

Repositories return `Result<T> = Success<T> | Failure`. No exceptions cross the data→domain boundary.

---

## 5. HTTP status → UX (the error-handling contract)

| Status / case | Source | UX |
|---|---|---|
| **401** (no/invalid bearer) | any authed call | single-flight refresh; if it fails → clear session, snackbar "Sessiya bitdi", → Welcome |
| **401 OTP** (`wrong_code`/`otp_expired`/`otp_max_attempts`) | verify-email | inline on the OTP field: shake / "kod yanlışdır" / "vaxtı bitib → yenidən göndər" / "çox cəhd → yeni kod" |
| **403** | authed | "İcazə yoxdur" full/snackbar; usually shouldn't happen on mobile (ownership-gated) |
| **404** | resource | `EmptyState`/"Tapılmadı"; for owned resources treat as gone → back |
| **409** (`email_already_registered`) | register | → Already-Registered screen / "Giriş edin" CTA |
| **409** (conflict, e.g. subscription) | renew/order | dialog explaining the conflict |
| **422** validation | write | map `errors.{field}` → per-field inline errors |
| **429** rate-limited | otp/register/login/open | countdown lock using `Retry-After`/meta; "Çox sayda cəhd, {n}s sonra" |
| **5xx** | any | `ErrorState` + retry; report to crash/log sink |
| **offline** | any | Offline banner/screen; retry re-runs the last intent |
| **timeout** | any | "Bağlantı yavaşdır"; retry (GET auto, writes manual) |
| **refresh expired** | refresh | → Welcome ("session expired") |
| **maintenance / force-update** | bootstrap | full-screen Maintenance / Force-Update (terminal) |

---

## 6. Pagination

All lists use the backend cursor contract `{data, page:{next_cursor, has_more, limit}}`. The client implements **infinite scroll**: a `PaginatedNotifier<T>` holds `items + nextCursor + hasMore + isLoadingMore`; reaching the list end with `hasMore` fetches `?cursor=nextCursor&limit=25`. Pull-to-refresh resets the cursor. No page numbers (cursor only).

---

## 7. Rate-limit awareness

The client mirrors the server limits to avoid wasted calls: disable the **Resend OTP** button until `resend_available_in_seconds`, debounce the **Open** button + honor cooldown (the open endpoint is 12/user + 4/device per min), and back off on 429 using `Retry-After`. A central `RateLimitGuard` tracks per-action cooldowns in memory.

---

## 8. Timeouts & cancellation

- Connect 10s, receive 20s, send 20s (per flavor; longer for the BirPay webview which is out-of-band). 
- Cancel tokens on screen dispose (abort in-flight list/detail fetches when the user navigates away).
- The **barrier-open poll** uses a bounded poll loop (interval ~1.5s, max ~the command's expected window) with cancellation — not an open-ended timer.

---

## 9. Offline write queue (minimal in v1)

Most mobile actions are reads or must be online (open a barrier, pay). The offline queue is **deliberately minimal**: only safe, idempotent, non-financial writes (e.g. a future profile edit, biometric flag toggle) are queued + replayed on reconnect with conflict handling (last-write-wins for prefs). **Barrier open and payments are never queued** (must be real-time + server-authoritative). Detail in `OFFLINE_STRATEGY.md`.

---

## 10. Multipart / uploads

No upload surface in v1 (no avatar upload endpoint yet). The `ApiClient` supports multipart so avatar upload is a drop-in once the backend ships it. Planned, not used.

---

## 11. Push notifications

**Current backend state:** notifications + push-token registration are **not implemented** (`/v1/me.unread_notifications_count` is a reserved 0; the `notifications/push-token` path is design-only). `user_devices.push_token` exists in the schema (FCM-shaped).

**Plan (client-ready, flagged off):**
- **Provider: Firebase Cloud Messaging (FCM)** — see `PACKAGE_SELECTION.md` for the rationale (native, free, Flutter-first, schema already FCM-shaped).
- Build the FCM token retrieval + `firebase_messaging` foreground/background handlers + `flutter_local_notifications` for display **now**, but **gate token registration** behind a feature flag until the backend exposes the registration endpoint. When it ships, the client POSTs the token (and on the existing `verify-email` device payload we already send `push_token` — so the token can ride the device fingerprint as an interim path).
- Notification taps carry a deep-link payload → routed via `go_router`.
- In-app inbox is a **Future** module (shell only) until the backend notifications API exists.

---

## 12. Conventions

- One repository per feature; one DTO per endpoint; mappers isolate the dual envelope.
- The `device` fingerprint (install_uuid + platform + app/os version + push_token) is built once per install and sent on `verify-email` (binds the refresh token + carries the push token interim).
- All endpoint paths centralised in `core/config/endpoints.dart` (no string literals in repositories).
- Locale → `Accept-Language` on every call.
