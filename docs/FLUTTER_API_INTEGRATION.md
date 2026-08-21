# Salam Həyətimiz — Flutter API Integration Guide

> Practical, copy-paste-ready integration reference for the Flutter mobile app.
> Accurate to `docs/openapi/v1.yaml` (v1.2.0) and `docs/_api_ground_truth.md` (verified 2026-06-21).
>
> **Only the endpoints documented here are actually deployed.** Anything marked
> **⏳ planned (backend not yet deployed)** exists in the OpenAPI contract but is NOT in the
> production `route:list` — do not call it; gate it behind a feature flag until it ships.

---

## 1. Intro

### Base URL

```
https://salamheyetimiz.com/v1
```

All mobile paths in this guide are relative to that base (e.g. `POST /devices/{id}/open` →
`https://salamheyetimiz.com/v1/devices/42/open`).

> Note: the OpenAPI `servers:` block lists `api.salamhayetimiz.az` — that is aspirational.
> The **real** production host is `salamheyetimiz.com`.

### Required / standard headers

| Header | Value | When |
|---|---|---|
| `Accept` | `application/json` | always |
| `Content-Type` | `application/json; charset=utf-8` | on any request with a body |
| `Accept-Language` | `az` \| `ru` \| `en` (default `az`) | always — drives the localized `message` in errors |
| `Authorization` | `Bearer <access_token>` | on every authenticated call |
| `Idempotency-Key` | a UUID v4 string (≤ 60 chars) | **required** on `openDevice`, `createOrder`, `renewSubscription`; accepted (optional) on all other mutating calls |

### Money, time, IDs

- **Money** is always in **minor units** (qəpik). `1 AZN = 100`. Currency is always `AZN`.
  Format for display as `amount_minor / 100`.
- **Time** is **UTC**, RFC 3339 (`2026-06-21T08:30:00Z`). Parse with `DateTime.parse(...).toLocal()`.
- **IDs** are unsigned 64-bit integers serialized as JSON numbers. In Dart use `int` (64-bit on all
  supported platforms). Booleans are always real JSON booleans; `null` (never `""`) means absent.

### Token TTLs

| Token | TTL | Notes |
|---|---|---|
| Access token (JWT, RS256) | **15 min** | sent as `Authorization: Bearer` |
| Refresh token (opaque) | **60 days** | **rotates on every use** — always persist the new one |

---

## 2. Recommended Flutter setup

### Dependencies (`pubspec.yaml`)

```yaml
dependencies:
  dio: ^5.4.0
  flutter_secure_storage: ^9.0.0
  uuid: ^4.3.0
```

- **`dio`** — HTTP client with interceptors (used for auth header injection + 401 refresh).
- **`flutter_secure_storage`** — Keychain/Keystore-backed store for the access + refresh tokens
  and the stable `install_uuid`.
- **`uuid`** — generate the per-install `install_uuid` once, and a fresh v4 for each
  `Idempotency-Key`.

### Token + install storage

```dart
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

class SecureStore {
  SecureStore(this._s);
  final FlutterSecureStorage _s;

  static const _kAccess = 'access_token';
  static const _kRefresh = 'refresh_token';
  static const _kInstall = 'install_uuid';

  Future<String?> get accessToken => _s.read(key: _kAccess);
  Future<String?> get refreshToken => _s.read(key: _kRefresh);

  Future<void> saveTokens(String access, String refresh) async {
    await _s.write(key: _kAccess, value: access);
    await _s.write(key: _kRefresh, value: refresh); // rotated value
  }

  Future<void> clearTokens() async {
    await _s.delete(key: _kAccess);
    await _s.delete(key: _kRefresh);
  }

  /// Stable per-install UUID. Generated once, reused forever (until reinstall).
  Future<String> installUuid() async {
    var v = await _s.read(key: _kInstall);
    if (v == null) {
      v = const Uuid().v4();
      await _s.write(key: _kInstall, value: v);
    }
    return v;
  }
}
```

### Configured `Dio` instance

```dart
import 'package:dio/dio.dart';

Dio buildDio(SecureStore store) {
  final dio = Dio(BaseOptions(
    baseUrl: 'https://salamheyetimiz.com/v1',
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 20),
    headers: {
      'Accept': 'application/json',
      'Accept-Language': 'az', // set from the user's locale: az | ru | en
    },
    // Treat only 2xx as success; we map everything else ourselves.
    validateStatus: (code) => code != null && code < 400,
  ));

  // 1) Attach the bearer token to every request.
  dio.interceptors.add(InterceptorsWrapper(
    onRequest: (options, handler) async {
      final token = await store.accessToken;
      if (token != null) {
        options.headers['Authorization'] = 'Bearer $token';
      }
      handler.next(options);
    },
  ));

  // 2) Refresh-on-401 interceptor (see §4.2) — add AuthInterceptor(dio, store).
  dio.interceptors.add(AuthInterceptor(dio, store));

  // 3) Convert DioException → ApiException (see §9).
  dio.interceptors.add(InterceptorsWrapper(
    onError: (e, handler) => handler.reject(ApiException.from(e).asDioError(e)),
  ));

  return dio;
}
```

> Add an `Idempotency-Key` only on the specific calls that need one (see each endpoint),
> not globally — a global key would be reused across distinct requests and break idempotency.

```dart
import 'package:uuid/uuid.dart';
Options idem() => Options(headers: {'Idempotency-Key': const Uuid().v4()});
```

---

## 3. Endpoint groups overview

| Group | Endpoints |
|---|---|
| **Auth** | requestOtp, verifyOtp, refreshToken, logout, enrollBiometrics, disableBiometrics |
| **Devices** | listMyDevices, getDevice, getDeviceStats |
| **Commands** (open the gate) | openDevice, getCommand, listDeviceCommands, submitOpenFeedback |
| **Subscriptions** | listMySubscriptions, getSubscription, renewSubscription, toggleAutoRenew |
| **Orders & Payments** | listMyOrders, createOrder, getOrder, recheckOrder |
| **Technical** (admin JWT) | techRegisterDevice, techAssignDevice |
| **Health** | getHealthLive, getHealthReady |

Webhooks (`paymentCallback`, `traccarForward`, `smsInbound`) are server-to-server and are **not**
called by the mobile app.

---

## 4. AUTH

### 4.1 OTP login flow

```
requestOtp  →  (user types the SMS code)  →  verifyOtp  →  store tokens
```

#### Step 1 — request OTP — `POST /auth/otp/request` (public)

Unknown phones auto-register; the 202 response is identical whether or not the phone exists
(anti-enumeration). Rate limit: **3 / phone / 10 min, 30 / IP / hour**.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `phone` | string | yes | E.164, must match `^\+994\d{9}$` |
| `purpose` | enum `login`\|`recover` | no | default `login` |

```bash
curl -X POST https://salamheyetimiz.com/v1/auth/otp/request \
  -H 'Accept: application/json' \
  -H 'Accept-Language: az' \
  -H 'Content-Type: application/json' \
  -d '{"phone":"+994501234567"}'
```

**202 response**

```json
{ "expires_in_seconds": 120, "resend_available_in_seconds": 30 }
```

```dart
Future<({int expiresIn, int resendIn})> requestOtp(String phone) async {
  final r = await dio.post('/auth/otp/request', data: {'phone': phone});
  return (
    expiresIn: r.data['expires_in_seconds'] as int,
    resendIn: r.data['resend_available_in_seconds'] as int,
  );
}
```

Errors: `422 validation_failed` (bad phone format), `429 rate_limited` (+ `Retry-After`).

#### Step 2 — verify OTP — `POST /auth/otp/verify` (public)

Rate limit: **10 / phone / 10 min**.

**Request body**

| Field | Type | Required | Notes |
|---|---|---|---|
| `phone` | string | yes | same E.164 pattern |
| `code` | string | yes | exactly 6 digits (`^\d{6}$`) |
| `device` | object | yes | `DeviceFingerprintInput` (below) |

`device` (`DeviceFingerprintInput`):

| Field | Type | Required |
|---|---|---|
| `install_uuid` | string (uuid) | yes |
| `platform` | enum `ios`\|`android` | yes |
| `os_version` | string | no |
| `app_version` | string | no |
| `device_model` | string | no |
| `push_token` | string \| null | no |

```bash
curl -X POST https://salamheyetimiz.com/v1/auth/otp/verify \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{
    "phone": "+994501234567",
    "code": "123456",
    "device": { "install_uuid": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
                "platform": "android", "os_version": "14",
                "app_version": "1.0.0", "device_model": "Pixel 8" }
  }'
```

**200 response — `AuthSuccess`**

```json
{
  "access_token": "eyJhbGciOiJSUzI1Ni␊...",
  "refresh_token": "rt_9f1c...60-day-opaque",
  "token_type": "Bearer",
  "expires_in": 900,
  "refresh_expires_in": 5184000,
  "user": {
    "id": 42,
    "phone": "+994501234567",
    "full_name": null,
    "email": null,
    "email_verified_at": null,
    "preferred_language": "az",
    "status": "active",
    "created_at": "2026-06-21T08:00:00Z",
    "last_login_at": "2026-06-21T08:30:00Z",
    "has_active_subscription": false
  }
}
```

Errors: `401` with `code` ∈ `wrong_code` / `otp_expired` / `otp_max_attempts`;
`422 validation_failed`; `429 rate_limited`.

```dart
Future<AuthSuccess> verifyOtp(String phone, String code) async {
  final r = await dio.post('/auth/otp/verify', data: {
    'phone': phone,
    'code': code,
    'device': {
      'install_uuid': await store.installUuid(),
      'platform': Platform.isIOS ? 'ios' : 'android',
      'os_version': /* ... */ '',
      'app_version': /* package_info */ '',
      'device_model': /* device_info */ '',
    },
  });
  final auth = AuthSuccess.fromJson(r.data as Map<String, dynamic>);
  await store.saveTokens(auth.accessToken, auth.refreshToken);
  return auth;
}
```

### 4.2 Token refresh (Dio interceptor, single-flight)

Access tokens live 15 min. On any `401 unauthenticated`, refresh once and retry the original
request. Refresh tokens **rotate** — persist the new pair. Use a single in-flight lock so
concurrent 401s trigger exactly one refresh (no stampede); a replayed/old refresh token is
rejected as `401`, which means the session is dead → force re-login.

`POST /auth/refresh` (public) — body `{ "refresh_token": "<opaque>" }` → `200 AuthSuccess`.

```dart
class AuthInterceptor extends Interceptor {
  AuthInterceptor(this._dio, this._store);
  final Dio _dio;
  final SecureStore _store;

  Future<void>? _refreshing; // single-flight lock

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    final res = err.response;
    final isAuthCall = err.requestOptions.path.startsWith('/auth/');
    if (res?.statusCode != 401 || isAuthCall || err.requestOptions.extra['retried'] == true) {
      return handler.next(err);
    }

    try {
      await (_refreshing ??= _doRefresh());
    } catch (_) {
      return handler.next(err); // refresh failed → propagate 401 (force re-login)
    } finally {
      _refreshing = null;
    }

    // Retry the original request once with the new token.
    final token = await _store.accessToken;
    final opts = err.requestOptions
      ..headers['Authorization'] = 'Bearer $token'
      ..extra['retried'] = true;
    try {
      final clone = await _dio.fetch(opts);
      return handler.resolve(clone);
    } on DioException catch (e) {
      return handler.next(e);
    }
  }

  Future<void> _doRefresh() async {
    final refresh = await _store.refreshToken;
    if (refresh == null) throw StateError('no refresh token');
    // Use a bare Dio (no interceptors) to avoid recursion.
    final bare = Dio(BaseOptions(baseUrl: _dio.options.baseUrl, headers: {
      'Accept': 'application/json', 'Content-Type': 'application/json',
    }));
    final r = await bare.post('/auth/refresh', data: {'refresh_token': refresh});
    final auth = AuthSuccess.fromJson(r.data as Map<String, dynamic>);
    await _store.saveTokens(auth.accessToken, auth.refreshToken); // rotated
  }
}
```

> When `_doRefresh()` throws (refresh token revoked/replayed/expired → `401`), clear tokens and
> route the user back to the login screen.

### 4.3 Logout — `POST /auth/logout` (authed)

Revokes the refresh-token family server-side. Returns `204`. Always clear local tokens too.

```dart
Future<void> logout() async {
  try { await dio.post('/auth/logout'); } catch (_) {}
  await store.clearTokens();
}
```

### 4.4 Biometrics

Local biometric unlock is a per-install flag stored server-side (no body, both return `204`).
The actual biometric prompt is done locally (e.g. `local_auth`); these endpoints only record
that the install has biometrics enabled.

- Enroll — `POST /me/biometrics/enroll` → `204`
- Disable — `DELETE /me/biometrics` → `204`

```dart
Future<void> enrollBiometrics()  => dio.post('/me/biometrics/enroll');
Future<void> disableBiometrics() => dio.delete('/me/biometrics');
```

### 4.5 Dart models — `AuthTokens` / `User`

```dart
class AuthSuccess {
  AuthSuccess({
    required this.accessToken,
    required this.refreshToken,
    required this.expiresIn,
    required this.user,
  });
  final String accessToken;
  final String refreshToken;
  final int expiresIn; // seconds (≈900)
  final User user;

  factory AuthSuccess.fromJson(Map<String, dynamic> j) => AuthSuccess(
        accessToken: j['access_token'] as String,
        refreshToken: j['refresh_token'] as String,
        expiresIn: j['expires_in'] as int,
        user: User.fromJson(j['user'] as Map<String, dynamic>),
      );
}

class User {
  User({
    required this.id,
    required this.phone,
    this.fullName,
    this.email,
    required this.preferredLanguage,
    required this.status,
    required this.hasActiveSubscription,
  });
  final int id;
  final String phone;
  final String? fullName;
  final String? email;
  final String preferredLanguage; // az | ru | en
  final String status;            // active | blocked | self_deleted
  final bool hasActiveSubscription;

  factory User.fromJson(Map<String, dynamic> j) => User(
        id: j['id'] as int,
        phone: j['phone'] as String,
        fullName: j['full_name'] as String?,
        email: j['email'] as String?,
        preferredLanguage: j['preferred_language'] as String,
        status: j['status'] as String,
        hasActiveSubscription: j['has_active_subscription'] as bool? ?? false,
      );
}
```

> ⏳ planned: full profile (`GET/PATCH /me`), account deletion (`DELETE /me`), notifications,
> privacy/consents, and roster/invitations are in the OpenAPI but **not deployed** yet.

---

## 5. DEVICES

### 5.1 List my devices — `GET /devices` (user)

**Purpose:** all devices the user can see (owned + member). Cursor-paginated.

**Query params:** `limit` (1–100, default 25), `cursor` (opaque), `filter` ∈ `all`\|`owned`\|`member`\|`suspended` (default `all`).

```bash
curl 'https://salamheyetimiz.com/v1/devices?filter=all&limit=25' \
  -H 'Authorization: Bearer <access_token>' -H 'Accept: application/json'
```

**200 response — `DeviceListResponse`** (`data: Device[]`, `page: PageInfo`):

```json
{
  "data": [
    {
      "id": 42,
      "label": "Yard gate",
      "serial": "SH-000042",
      "status": "active",
      "role": "owner",
      "can_open": true,
      "suspension_reason": "none",
      "latitude": 40.4093,
      "longitude": 49.8671,
      "last_online_at": "2026-06-21T08:25:00Z"
    }
  ],
  "page": { "next_cursor": null, "has_more": false, "limit": 25 }
}
```

Key fields per caller: `role` (`owner`\|`user`), `can_open` (open allowed right now: sub active,
device active, not in cooldown), `suspension_reason` (`none` \| `subscription_expired` \|
`owner_sub_expired_others_active` \| `device_disabled` \| `device_suspended`).

### 5.2 Get device — `GET /devices/{deviceId}` (user)

Returns `DeviceDetail` = `Device` plus `device_model {vendor, model_code}`,
`cooldown_seconds_remaining` (int|null), and `subscription` (`SubscriptionBrief`).
`404 not_found` if not visible to the caller.

```json
{
  "id": 42, "label": "Yard gate", "serial": "SH-000042", "status": "active",
  "role": "owner", "can_open": true, "suspension_reason": "none",
  "device_model": { "vendor": "Concox", "model_code": "GT06N" },
  "cooldown_seconds_remaining": null,
  "subscription": { "id": 9, "tier": "main", "status": "active",
                    "ends_at": "2026-12-01T00:00:00Z", "days_remaining": 163, "auto_renew": false }
}
```

### 5.3 Device stats — `GET /devices/{deviceId}/stats` (user)

**Query:** `period` ∈ `7d`\|`30d`\|`90d` (default `30d`). Returns `DeviceStats`:

```json
{
  "period": "30d", "opens_total": 128, "opens_success": 124, "opens_failed": 4,
  "success_rate": 0.969, "avg_latency_ms": 4200, "last_open_at": "2026-06-21T08:25:00Z"
}
```

### Dart model hint — `Device`

```dart
class Device {
  Device({
    required this.id, required this.label, required this.status,
    required this.role, required this.canOpen, required this.suspensionReason,
    this.serial, this.cooldownSecondsRemaining, this.lastOnlineAt,
  });
  final int id;
  final String label;
  final String? serial;
  final String status;            // active | suspended | disabled | decommissioned
  final String role;              // owner | user
  final bool canOpen;
  final String suspensionReason;  // none | subscription_expired | ...
  final int? cooldownSecondsRemaining;
  final DateTime? lastOnlineAt;

  factory Device.fromJson(Map<String, dynamic> j) => Device(
        id: j['id'] as int,
        label: j['label'] as String,
        serial: j['serial'] as String?,
        status: j['status'] as String,
        role: j['role'] as String,
        canOpen: j['can_open'] as bool,
        suspensionReason: (j['suspension_reason'] as String?) ?? 'none',
        cooldownSecondsRemaining: j['cooldown_seconds_remaining'] as int?,
        lastOnlineAt: j['last_online_at'] == null
            ? null : DateTime.parse(j['last_online_at'] as String),
      );
}
```

---

## 6. THE CORE FLOW — Open a gate

Real-time push (Laravel Reverb / WebSocket) is **DEFERRED**. The `websocket_channel` field is
returned but you should **not** rely on it yet. Instead: call `openDevice`, then **poll**
`GET /commands/{commandId}` (~once per second) until the command reaches a terminal state
(`opened`, `dispatched`, `failed`, or `expired`) or you hit a client-side timeout.

```
openDevice (202)  →  poll GET /commands/{id} every ~1s  →  terminal state  →  (optional) feedback
```

### 6.1 openDevice — `POST /devices/{deviceId}/open` (user, **Idempotency-Key REQUIRED**)

- Auth: caller must be on the roster, subscription `active`, device not `disabled`.
- Rate limit: **12 / user / min, 4 / device / min**. Cooldown returns `429 cooldown` + `Retry-After`.
- The `Idempotency-Key` is persisted as `open_commands.idempotency_key`: a replay with the **same
  key** returns the **same** command without re-dispatching. Generate a **new** key for each
  distinct open the user intends.

**Request body (optional):** `{ "client_app_version": "1.0.0" }`.

```bash
curl -X POST https://salamheyetimiz.com/v1/devices/42/open \
  -H 'Authorization: Bearer <access_token>' \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -H 'Idempotency-Key: 7c9e6679-7425-40de-944b-e07fc1f90ae7' \
  -d '{"client_app_version":"1.0.0"}'
```

**202 response — `OpenCommandAccepted`**

```json
{
  "command_id": 90817,
  "state": "queued",
  "expected_completion_ms": 5000,
  "driver_confirms_actuation": true,
  "websocket_channel": "private-user.42"
}
```

- `expected_completion_ms` — server estimate; size the progress UI to this, not a constant.
- `driver_confirms_actuation` — if `true`, terminal `opened` is real evidence → show "Açıldı".
  If `false`, the terminal state means "dispatched" → show "Göndərildi" and prompt
  `submitOpenFeedback` to ask the user whether the gate actually moved.

Errors: `403` (`subscription_required` / `device_disabled` / `forbidden`), `404 not_found`,
`409 idempotency_mismatch` (same key, different body), `429` (`cooldown`/`rate_limited` + `Retry-After`),
`502 device_offline`.

### 6.2 getCommand (poll) — `GET /commands/{commandId}` (user)

Returns `OpenCommand`. `state` ∈ `queued` \| `dispatching` \| `dispatched` \| `opened` \| `failed` \| `expired`.
Terminal states are `dispatched`, `opened`, `failed`, `expired`.

```json
{
  "id": 90817, "device_id": 42, "state": "opened",
  "failure_reason": null, "driver": "traccar",
  "requested_at": "2026-06-21T08:25:00Z",
  "dispatched_at": "2026-06-21T08:25:01Z",
  "completed_at": "2026-06-21T08:25:04Z",
  "latency_ms": 4000, "attempts": 1
}
```

### 6.3 submitOpenFeedback — `POST /commands/{commandId}/feedback` (user, Idempotency-Key optional)

Call after opens where `driver_confirms_actuation == false`. Body
`{ "gate_moved": true, "comment": null }`. `201` first time, `200` on replay. One per (command, user).

### 6.4 Complete Dart poll loop

```dart
import 'dart:async';
import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

enum OpenResult { opened, dispatched, failed, expired, timedOut }

class OpenGate {
  OpenGate(this._dio);
  final Dio _dio;
  static const _terminal = {'opened', 'dispatched', 'failed', 'expired'};

  /// Opens [deviceId] and polls until terminal/timeout.
  /// Returns the result + the final OpenCommand JSON + driver actuation flag.
  Future<({OpenResult result, Map<String, dynamic> command, bool driverConfirms})>
      open(int deviceId, {Duration timeout = const Duration(seconds: 30)}) async {
    // 1) Issue the open command (Idempotency-Key REQUIRED, fresh per intent).
    late Response openRes;
    try {
      openRes = await _dio.post(
        '/devices/$deviceId/open',
        data: {'client_app_version': '1.0.0'},
        options: Options(headers: {'Idempotency-Key': const Uuid().v4()}),
      );
    } on DioException catch (e) {
      final api = ApiException.from(e);
      // Cooldown / rate limit → surface Retry-After to the user.
      if (api.statusCode == 429) {
        throw CooldownException(api.retryAfter ?? 30, api.message);
      }
      rethrow; // 403 subscription_required / device_disabled, 502 device_offline, etc.
    }

    final accepted = openRes.data as Map<String, dynamic>;
    final commandId = accepted['command_id'] as int;
    final driverConfirms = accepted['driver_confirms_actuation'] as bool? ?? false;
    final expectedMs = accepted['expected_completion_ms'] as int? ?? 5000;

    // 2) Poll /commands/{id} ~1/s until terminal or timeout.
    final deadline = DateTime.now().add(timeout);
    Map<String, dynamic> last = {'state': accepted['state'] ?? 'queued'};

    while (DateTime.now().isBefore(deadline)) {
      await Future.delayed(const Duration(seconds: 1));
      try {
        final r = await _dio.get('/commands/$commandId');
        last = r.data as Map<String, dynamic>;
        final state = last['state'] as String;
        if (_terminal.contains(state)) {
          return (
            result: _mapState(state),
            command: last,
            driverConfirms: driverConfirms,
          );
        }
      } on DioException catch (e) {
        final api = ApiException.from(e);
        if (api.statusCode == 429) {
          // Back off for Retry-After before resuming polling.
          await Future.delayed(Duration(seconds: api.retryAfter ?? 2));
          continue;
        }
        if (api.statusCode == 404) rethrow; // command vanished — real error
        // Transient 5xx: keep polling until the deadline.
      }
    }
    return (result: OpenResult.timedOut, command: last, driverConfirms: driverConfirms);
  }

  OpenResult _mapState(String s) => switch (s) {
        'opened' => OpenResult.opened,
        'dispatched' => OpenResult.dispatched,
        'failed' => OpenResult.failed,
        'expired' => OpenResult.expired,
        _ => OpenResult.timedOut,
      };

  Future<void> submitFeedback(int commandId, bool gateMoved, {String? comment}) =>
      _dio.post('/commands/$commandId/feedback',
          data: {'gate_moved': gateMoved, if (comment != null) 'comment': comment},
          options: Options(headers: {'Idempotency-Key': const Uuid().v4()}));
}

class CooldownException implements Exception {
  CooldownException(this.retryAfterSeconds, this.message);
  final int retryAfterSeconds;
  final String message;
}
```

**UX after the poll:**
- `opened` + `driverConfirms == true` → "Açıldı" (confirmed).
- `dispatched`, or `opened` with `driverConfirms == false` → "Göndərildi"; optionally call
  `submitFeedback(commandId, userSaysItMoved)`.
- `failed` → show `command['failure_reason']`. `expired` → ask the user to retry.
- `timedOut` → keep showing a spinner / let the user re-check via `getCommand`.

### 6.5 Command history — `GET /devices/{deviceId}/commands` (user)

Cursor-paginated `OpenCommandListResponse`. Query: `limit`, `cursor`, `since`/`until` (date-time),
`state` ∈ `queued`\|`dispatched`\|`opened`\|`failed`\|`expired`.

---

## 7. SUBSCRIPTIONS

### 7.1 List — `GET /subscriptions` (user)

Cursor-paginated `SubscriptionListResponse`. Query: `limit`, `cursor`, `status` ∈
`pending_payment`\|`active`\|`expired`\|`cancelled`\|`refunded`.

```json
{
  "data": [
    {
      "id": 9, "tier": "main", "status": "active",
      "ends_at": "2026-12-01T00:00:00Z", "days_remaining": 163, "auto_renew": false,
      "device_id": 42, "user_id": 42,
      "starts_at": "2026-06-01T00:00:00Z",
      "price_minor": 1200, "currency": "AZN", "term_days": 365
    }
  ],
  "page": { "next_cursor": null, "has_more": false, "limit": 25 }
}
```

`tier` ∈ `main`\|`additional`. `price_minor` is qəpik (1200 = 12.00 AZN).

### 7.2 Get — `GET /subscriptions/{subscriptionId}` (user)

Returns `SubscriptionDetail` = `Subscription` + `periods: SubscriptionPeriod[]` + `latest_order: Order`.

### 7.3 Renew — `POST /subscriptions/{subscriptionId}/renew` (user, **Idempotency-Key REQUIRED**)

Creates an order and returns it (with the bank redirect). Auth: caller must be the payer.

**Request body (optional):** `{ "return_url": "salam://payment/return" }`.

**200 response — `Order`** (see §8). Then drive the payment flow from `bank_redirect_url`.

```dart
Future<Order> renewSubscription(int subId, {String? returnUrl}) async {
  final r = await dio.post('/subscriptions/$subId/renew',
      data: {if (returnUrl != null) 'return_url': returnUrl},
      options: Options(headers: {'Idempotency-Key': const Uuid().v4()}));
  return Order.fromJson(r.data as Map<String, dynamic>);
}
```

Errors: `409 conflict` (not eligible for renewal in current state).

### 7.4 Auto-renew toggle — `PATCH /subscriptions/{subscriptionId}/auto-renew` (user)

Body `{ "auto_renew": true, "card_token_id": 5 }` (`card_token_id` required when enabling).
Returns `Subscription`. **Enabling may `409 conflict`** — auto-renew is not available until Kapital
tokenization ships, so handle the 409 gracefully (show "not available yet").

### Dart model hint — `Subscription`

```dart
class Subscription {
  Subscription({
    required this.id, required this.tier, required this.status,
    this.endsAt, this.daysRemaining, required this.autoRenew, this.priceMinor,
  });
  final int id;
  final String tier;     // main | additional
  final String status;   // pending_payment | active | expired | cancelled | refunded
  final DateTime? endsAt;
  final int? daysRemaining;
  final bool autoRenew;
  final int? priceMinor;

  factory Subscription.fromJson(Map<String, dynamic> j) => Subscription(
        id: j['id'] as int,
        tier: j['tier'] as String,
        status: j['status'] as String,
        endsAt: j['ends_at'] == null ? null : DateTime.parse(j['ends_at'] as String),
        daysRemaining: j['days_remaining'] as int?,
        autoRenew: j['auto_renew'] as bool? ?? false,
        priceMinor: j['price_minor'] as int?,
      );
}
```

---

## 8. ORDERS & PAYMENTS

### Payment flow

```
createOrder (Idempotency-Key REQUIRED)
   → response has bank_redirect_url
      → open it in a WebView / external browser (Kapital Bank checkout)
         → bank redirects back to your return_url (e.g. salam://payment/return)
            → call getOrder / recheckOrder to confirm the final status
```

The bank callback (`POST /payments/callback`) is server-to-server. Your client **must** confirm
the result by re-reading the order (`getOrder`) and, if it's still `pending`/`authorising` after
the user returns, force a reconcile via `recheckOrder`.

### 8.1 createOrder — `POST /orders` (user, **Idempotency-Key REQUIRED**)

**Request body — `OrderCreate`:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `purpose` | enum | yes | `device_sale`\|`sub_main`\|`sub_additional`\|`sub_renewal`\|`bundle` |
| `items` | array (1–10) | yes | each `OrderItemCreate` |
| `auto_renew` | bool | no | default `false` |
| `return_url` | uri | no | e.g. `salam://payment/return` |

`OrderItemCreate`: `item_type` ∈ `device`\|`sub_main`\|`sub_additional`\|`sub_renewal`,
`referenced_id` (device_id or subscription_id), `quantity` (1–10).

```bash
curl -X POST https://salamheyetimiz.com/v1/orders \
  -H 'Authorization: Bearer <access_token>' \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -H 'Idempotency-Key: 1e8b0c2a-3d4f-4a6b-9c1d-2e3f4a5b6c7d' \
  -d '{
    "purpose": "sub_main",
    "items": [{ "item_type": "sub_main", "referenced_id": 42, "quantity": 1 }],
    "return_url": "salam://payment/return"
  }'
```

**201 / 402 response — `Order`** (a `402` is normal: order created in `pending`, client should
redirect to `bank_redirect_url`):

```json
{
  "id": 5001, "reference": "SH-202606-000123", "status": "pending",
  "purpose": "sub_main", "amount_minor": 1200, "currency": "AZN",
  "bank_redirect_url": "https://kapital.../checkout/abc123",
  "expires_at": "2026-06-21T09:00:00Z",
  "paid_at": null, "failed_at": null, "failed_reason": null,
  "created_at": "2026-06-21T08:30:00Z",
  "items": [{ "id": 1, "item_type": "sub_main", "referenced_id": 42,
              "description": "Əsas abunəlik (1 il)", "quantity": 1,
              "unit_amount_minor": 1200, "total_amount_minor": 1200 }]
}
```

Errors: `422 validation_failed`, `503 payment_provider_unavailable` (Kapital degraded — retry later).

> **Idempotency:** the same `Idempotency-Key` replays the same order for 24 h (no duplicate
> charge). A retry after a flaky network is safe **only** if you reuse the same key. A different
> body with the same key → `409 idempotency_mismatch`.

```dart
Future<Order> createOrder(OrderCreate body, {required String idempotencyKey}) async {
  final r = await dio.post('/orders', data: body.toJson(),
      options: Options(headers: {'Idempotency-Key': idempotencyKey}));
  return Order.fromJson(r.data as Map<String, dynamic>);
}
// Persist `idempotencyKey` with the pending order so a retry reuses it.
```

### 8.2 Open the redirect

Open `order.bank_redirect_url` in a WebView (e.g. `webview_flutter`) or the external browser
(`url_launcher`). Detect the return by intercepting your custom-scheme `return_url`
(`salam://payment/return`) — close the WebView and move to confirmation.

### 8.3 getOrder — `GET /orders/{orderId}` (user)

Returns `OrderDetail` = `Order` + `payments: Payment[]` + `refunds: Refund[]` + `timeline[]`.
`status` ∈ `pending`\|`authorising`\|`paid`\|`failed`\|`cancelled`\|`refunded`\|`partially_refunded`\|`expired`.

### 8.4 recheckOrder — `POST /orders/{orderId}/recheck` (user, Idempotency-Key optional)

Forces a status reconcile against the bank. Use it if, after the user returns from checkout, the
order is still `pending`/`authorising`. Returns the latest `Order`. May `503 payment_provider_unavailable`.

```dart
Future<Order> confirmPayment(int orderId) async {
  var order = Order.fromJson((await dio.get('/orders/$orderId')).data);
  if (order.status == 'pending' || order.status == 'authorising') {
    order = Order.fromJson((await dio.post('/orders/$orderId/recheck')).data);
  }
  return order; // inspect order.status: paid | failed | ...
}
```

### 8.5 listMyOrders — `GET /orders` (user)

Cursor-paginated `OrderListResponse`. Query: `limit`, `cursor`, `status` (same enum as above).

### Dart model hint — `Order`

```dart
class Order {
  Order({
    required this.id, required this.reference, required this.status,
    required this.amountMinor, required this.currency, required this.purpose,
    this.bankRedirectUrl, this.expiresAt, this.paidAt, this.failedReason,
  });
  final int id;
  final String reference;       // SH-202606-000123
  final String status;          // pending | authorising | paid | failed | ...
  final int amountMinor;        // qəpik
  final String currency;        // AZN
  final String purpose;
  final String? bankRedirectUrl;
  final DateTime? expiresAt;
  final DateTime? paidAt;
  final String? failedReason;

  bool get isPaid => status == 'paid';
  String get amountAzn => (amountMinor / 100).toStringAsFixed(2);

  factory Order.fromJson(Map<String, dynamic> j) => Order(
        id: j['id'] as int,
        reference: j['reference'] as String,
        status: j['status'] as String,
        amountMinor: j['amount_minor'] as int,
        currency: j['currency'] as String,
        purpose: j['purpose'] as String,
        bankRedirectUrl: j['bank_redirect_url'] as String?,
        expiresAt: j['expires_at'] == null ? null : DateTime.parse(j['expires_at']),
        paidAt: j['paid_at'] == null ? null : DateTime.parse(j['paid_at']),
        failedReason: j['failed_reason'] as String?,
      );
}
```

---

## 9. Error handling

Every 4xx/5xx (except `422`, which has its own field-level shape) uses this envelope:

```json
{
  "error": {
    "code": "subscription_required",
    "message_key": "errors.subscription_required",
    "message": "Aboneliyiniz başa çatıb.",
    "details": { "device_id": 42 },
    "request_id": "01JCGZ9F9XZ7P0X5MFK7Z7VYNZ"
  }
}
```

- `message` is **already localized** to your `Accept-Language` → safe to show directly to users.
- `code` / `message_key` are stable, locale-independent → branch your logic on these, never on `message`.
- `request_id` → log it for support correlation.

`422 validation_failed` uses a per-field shape:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "...",
    "fields": { "phone": ["Telefon nömrəsi düzgün formatda deyil."], "code": ["Kod 6 rəqəm olmalıdır."] },
    "request_id": "..."
  }
}
```

### Common codes to handle

| HTTP | `code` | Handling |
|---|---|---|
| 401 | `unauthenticated` | refresh once; if refresh fails → re-login |
| 401 | `wrong_code` / `otp_expired` / `otp_max_attempts` | OTP screen: show message, allow resend |
| 403 | `subscription_required` | route to renew/checkout |
| 403 | `device_disabled` | show disabled state; opening is blocked |
| 403 | `forbidden` | not on roster / not payer |
| 404 | `not_found` | resource gone |
| 409 | `idempotency_mismatch` | bug: same key reused with a different body |
| 409 | `conflict` | state precludes op (e.g. renewal/auto-renew) |
| 422 | `validation_failed` | map `error.fields` to form fields |
| 429 | `rate_limited` | read `Retry-After`; back off and retry |
| 429 | `cooldown` | open cooldown; show countdown from `Retry-After` |
| 502 | `device_offline` | driver couldn't reach the device; offer retry |
| 503 | `payment_provider_unavailable` | Kapital degraded; retry later |

### `ApiException`

```dart
class ApiException implements Exception {
  ApiException({
    required this.statusCode, required this.code, required this.message,
    this.messageKey, this.details, this.fields, this.requestId, this.retryAfter,
  });
  final int statusCode;
  final String code;        // stable machine key — branch on this
  final String message;     // localized — show to the user
  final String? messageKey;
  final Map<String, dynamic>? details;
  final Map<String, List<String>>? fields; // 422 only
  final String? requestId;
  final int? retryAfter;    // seconds, from Retry-After (429)

  static ApiException from(DioException e) {
    final res = e.response;
    final status = res?.statusCode ?? 0;
    final retryAfter = int.tryParse(res?.headers.value('retry-after') ?? '');
    final body = res?.data;

    if (body is Map && body['error'] is Map) {
      final err = (body['error'] as Map).cast<String, dynamic>();
      Map<String, List<String>>? fields;
      if (err['fields'] is Map) {
        fields = (err['fields'] as Map).map((k, v) =>
            MapEntry(k as String, (v as List).map((e) => e.toString()).toList()));
      }
      return ApiException(
        statusCode: status,
        code: (err['code'] as String?) ?? 'unknown',
        message: (err['message'] as String?) ?? 'Xəta baş verdi.',
        messageKey: err['message_key'] as String?,
        details: (err['details'] as Map?)?.cast<String, dynamic>(),
        fields: fields,
        requestId: err['request_id'] as String?,
        retryAfter: retryAfter,
      );
    }
    return ApiException(
      statusCode: status,
      code: e.type == DioExceptionType.connectionTimeout ||
              e.type == DioExceptionType.receiveTimeout
          ? 'network_timeout' : 'network_error',
      message: 'Şəbəkə xətası. Yenidən cəhd edin.',
      retryAfter: retryAfter,
    );
  }

  // Wrap back into a DioException so the interceptor chain stays typed.
  DioException asDioError(DioException original) =>
      original..error = this;

  @override
  String toString() => 'ApiException($statusCode $code: $message)';
}
```

Usage: catch `DioException` and read `e.error as ApiException`, or call `ApiException.from(e)`.
Show `.message` in a snackbar; switch on `.code` for navigation/logic.

---

## 10. Pagination

All list endpoints use **opaque cursor** pagination (never offset):

- **Request:** `?limit=25&cursor=<opaque>` (`limit` 1–100, default 25).
- **Response envelope:** `{ "data": [...], "page": { "next_cursor": string|null, "has_more": bool, "limit": int } }`.
- `next_cursor` is `null` when there are no more pages. Pass it back as `cursor` to fetch the next page.

```dart
class Page<T> {
  Page(this.items, this.nextCursor, this.hasMore);
  final List<T> items;
  final String? nextCursor;
  final bool hasMore;
}

Future<Page<T>> fetchPage<T>(
  String path,
  T Function(Map<String, dynamic>) parse, {
  int limit = 25,
  String? cursor,
  Map<String, dynamic>? extraQuery,
}) async {
  final r = await dio.get(path, queryParameters: {
    'limit': limit,
    if (cursor != null) 'cursor': cursor,
    ...?extraQuery,
  });
  final data = r.data as Map<String, dynamic>;
  final items = (data['data'] as List)
      .map((e) => parse(e as Map<String, dynamic>))
      .toList();
  final page = data['page'] as Map<String, dynamic>;
  return Page(items, page['next_cursor'] as String?, page['has_more'] as bool? ?? false);
}

// Example: loop all device pages.
Future<List<Device>> allDevices() async {
  final out = <Device>[];
  String? cursor;
  do {
    final p = await fetchPage('/devices', Device.fromJson, cursor: cursor);
    out.addAll(p.items);
    cursor = p.nextCursor;
  } while (cursor != null);
  return out;
}
```

---

## 11. Technical mode (admin JWT)

These two endpoints live on the mobile host but require an **admin** JWT
(`role = technical` or `super_admin`) — they are for the installer/technical app, not regular users.

| Method | Path | operationId | Body | Returns |
|---|---|---|---|---|
| POST | `/technical/devices` | techRegisterDevice | `DeviceTechRegister` | `201 DeviceAdmin` |
| POST | `/technical/devices/{deviceId}/assign` | techAssignDevice | `{ owner_phone, region_id?, location_label?, latitude?, longitude? }` | `200 DeviceAdmin` (409 if already assigned) |

`DeviceTechRegister` required fields: `serial`, `device_model_id`, `sim_phone` (`^\+994\d{9}$`),
`driver_type` ∈ `traccar`\|`ble`\|`sms`. Optional: `sim_operator_id`, `sim_iccid`,
`firmware_version`, `region_id`, `location_label`, `latitude`, `longitude`, `metadata`.
Both accept an optional `Idempotency-Key`.

---

## 12. Health (public)

- `GET /health/live` → `200 HealthStatus` (process up).
- `GET /health/ready` → `200` when DB + Redis + queue healthy, else `503`.

```json
{ "status": "ok", "version": "1.0.0",
  "checks": { "db": {"ok": true, "latency_ms": 2}, "redis": {"ok": true}, "queue": {"ok": true} } }
```

---

## 13. Endpoint cheat-sheet

| Group | Method | Path | operationId | Auth | Idempotency-Key | Notes |
|---|---|---|---|---|---|---|
| Auth | POST | `/auth/otp/request` | requestOtp | public | — | `{phone}` → 202 |
| Auth | POST | `/auth/otp/verify` | verifyOtp | public | — | → AuthSuccess |
| Auth | POST | `/auth/refresh` | refreshToken | public | — | `{refresh_token}` → AuthSuccess (rotates) |
| Auth | POST | `/auth/logout` | logout | user | — | 204 |
| Auth | POST | `/me/biometrics/enroll` | enrollBiometrics | user | — | 204 |
| Auth | DELETE | `/me/biometrics` | disableBiometrics | user | — | 204 |
| Devices | GET | `/devices` | listMyDevices | user | — | `?filter=all\|owned\|member\|suspended` |
| Devices | GET | `/devices/{deviceId}` | getDevice | user | — | DeviceDetail |
| Devices | GET | `/devices/{deviceId}/stats` | getDeviceStats | user | — | `?period=7d\|30d\|90d` |
| Commands | POST | `/devices/{deviceId}/open` | openDevice | user | **required** | 202; cooldown→429 |
| Commands | GET | `/commands/{commandId}` | getCommand | user | — | poll for terminal state |
| Commands | GET | `/devices/{deviceId}/commands` | listDeviceCommands | user | — | cursor |
| Commands | POST | `/commands/{commandId}/feedback` | submitOpenFeedback | user | optional | `{gate_moved}` |
| Subs | GET | `/subscriptions` | listMySubscriptions | user | — | cursor; `?status=` |
| Subs | GET | `/subscriptions/{subscriptionId}` | getSubscription | user | — | SubscriptionDetail |
| Subs | POST | `/subscriptions/{subscriptionId}/renew` | renewSubscription | user | **required** | 200 → Order |
| Subs | PATCH | `/subscriptions/{subscriptionId}/auto-renew` | toggleAutoRenew | user | — | enable may 409 |
| Orders | GET | `/orders` | listMyOrders | user | — | cursor; `?status=` |
| Orders | POST | `/orders` | createOrder | user | **required** | 201/402 → Order (`bank_redirect_url`) |
| Orders | GET | `/orders/{orderId}` | getOrder | user | — | OrderDetail |
| Orders | POST | `/orders/{orderId}/recheck` | recheckOrder | user | optional | reconcile vs bank |
| Technical | POST | `/technical/devices` | techRegisterDevice | admin (tech/super) | optional | admin JWT |
| Technical | POST | `/technical/devices/{deviceId}/assign` | techAssignDevice | admin (tech/super) | optional | admin JWT |
| Health | GET | `/health/live` · `/health/ready` | getHealthLive/Ready | public | — | readiness 503 if degraded |

**⏳ planned (backend not yet deployed)** — do **not** call from production builds: full profile
(`GET/PATCH/DELETE /me`), roster (`/devices/{id}/users…`), invitations (`/invitations…`),
notifications (`/notifications…`, push-token register), privacy/consents
(`/consents`, `/privacy/export`, `/privacy/deletion`), and technical diagnostics ping.
These exist in `docs/openapi/v1.yaml` but are absent from the deployed `route:list`.
