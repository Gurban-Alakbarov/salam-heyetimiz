# Salam Həyətimiz — Admin API Integration Guide (Frontend)

**Audience:** Frontend developers building the Admin Panel.
**API surface:** `/admin/v1` (admin back-office).
**Status of this doc:** Endpoints are split into **✅ implemented (deployed)** and **⏳ planned (backend not yet deployed)**. Only build production flows against ✅ endpoints. Planned endpoints exist in the OpenAPI contract (`docs/openapi/v1.yaml`) but are **not** in the deployed route table — treat their shapes as provisional.

> Source of truth for "what is live": `docs/_api_ground_truth.md` (verified against the deployed `route:list`).
> Source of truth for request/response shapes: `docs/openapi/v1.yaml`.

---

## 1. Intro

### Base URL

```
https://salamheyetimiz.com/admin/v1
```

> The OpenAPI `servers:` block lists `api.salamhayetimiz.az` — that is aspirational. The **real deployed host** is `salamheyetimiz.com`, which serves both `/v1` (mobile) and `/admin/v1` (admin).

### Required headers

| Header | Value | When |
|---|---|---|
| `Accept` | `application/json` | Every request |
| `Content-Type` | `application/json; charset=utf-8` | Requests with a body |
| `Authorization` | `Bearer <admin_access_token>` | Every authenticated request (everything except `POST /auth/login`, `POST /auth/2fa/verify`) |
| `Accept-Language` | `az` \| `ru` \| `en` (default `az`) | Optional; localizes `error.message` |
| `Idempotency-Key` | UUID v4 / string ≤ 60 chars | Mutating endpoints; **required** on `POST /orders/{id}/refund` (see §9) |

### Admin tokens

- **Format:** RS256 JWT (asymmetric). The `kid` header selects the signing key.
- **TTL:** 30 minutes. There is **no admin refresh-token endpoint** — when the access token expires, the admin re-runs the login (+ 2FA) flow. Plan UX for re-login on 401.
- **Identity space:** disjoint from mobile tokens. An admin JWT is **not** accepted on `/v1` mobile endpoints (except the two `technical/*` provisioning endpoints which explicitly use `adminBearerAuth`), and a mobile JWT is never accepted on `/admin/v1`.
- **Subject / verification keys:** public verification keys (JWKS) are published at:

  ```
  https://salamheyetimiz.com/.well-known/jwks.json
  ```

  Two `kid`s may be valid at once during a key rotation. The frontend does **not** normally verify the JWT itself — the backend does. JWKS is here for completeness and for any edge/gateway tooling.

### Conventions you must handle

- **Money:** integer **minor units** (qəpik). `1 AZN = 100`. Field suffix `_minor`. Currency is always `AZN`.
- **Time:** UTC, RFC 3339 (`2026-06-21T18:04:00Z`).
- **IDs:** 64-bit unsigned integers serialized as JSON numbers. Treat as 64-bit in JS (they currently fit in `Number.MAX_SAFE_INTEGER`, but use a bigint-safe approach if you ever do arithmetic on them).
- **Nulls:** absence is `null`, never `""` or `0`.
- **Booleans:** `true`/`false`, never `0`/`1`.

---

## 2. Recommended stack

Framework-agnostic — works with React, Vue, Svelte, or vanilla. Recommendation:

- One configured HTTP client (axios instance shown below) with a **base URL** and a **401 interceptor**.
- Keep the admin access token **in memory** or in an **httpOnly, Secure, SameSite=Strict cookie** set by a thin same-origin backend-for-frontend (BFF). **Do not use `localStorage`/`sessionStorage` for admin tokens** — admin is a high-privilege surface and `localStorage` is XSS-readable. With a 30-minute TTL and no refresh token, in-memory storage (lost on tab close → re-login) is acceptable and the safest default.

### Axios instance with 401 interceptor

```js
// adminApi.js
import axios from "axios";

let adminAccessToken = null; // in-memory; survives navigations within the SPA, not reloads

export function setAdminToken(token) { adminAccessToken = token; }
export function clearAdminToken() { adminAccessToken = null; }

export const adminApi = axios.create({
  baseURL: "https://salamheyetimiz.com/admin/v1",
  headers: { Accept: "application/json" },
  timeout: 20000,
});

// Attach bearer + default Accept-Language on every request.
adminApi.interceptors.request.use((config) => {
  if (adminAccessToken) {
    config.headers.Authorization = `Bearer ${adminAccessToken}`;
  }
  config.headers["Accept-Language"] = config.headers["Accept-Language"] || "az";
  return config;
});

// Global 401 handling: token expired/invalid → force re-login.
adminApi.interceptors.response.use(
  (res) => res,
  (error) => {
    const status = error.response?.status;
    if (status === 401) {
      clearAdminToken();
      // Redirect to the login screen. (No admin refresh token exists.)
      if (typeof window !== "undefined") window.location.assign("/admin/login");
    }
    return Promise.reject(error);
  }
);
```

### Same call with `fetch`

```js
async function adminFetch(path, { method = "GET", body, token, idempotencyKey } = {}) {
  const res = await fetch(`https://salamheyetimiz.com/admin/v1${path}`, {
    method,
    headers: {
      Accept: "application/json",
      "Accept-Language": "az",
      ...(body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (res.status === 401) { /* clear token + redirect to login */ }
  const data = res.status === 204 ? null : await res.json();
  if (!res.ok) throw Object.assign(new Error("api_error"), { status: res.status, data });
  return data;
}
```

---

## 3. Admin authentication (two-phase) ✅ implemented

The admin login is a **two-phase** flow. Phase 1 verifies email + password. If 2FA is enabled, the server returns a short-lived **challenge token** instead of a session; Phase 2 exchanges that challenge plus a TOTP/recovery code for the actual admin JWT.

> Note on the success contract: the OpenAPI spec shows `POST /auth/login` returning a 2FA challenge and `POST /auth/2fa/verify` returning the `AdminAuthSuccess`. Per the deployed two-phase model, treat login as possibly returning **either** an `AdminAuthSuccess` (when 2FA is not required) **or** a challenge. Always branch on the presence of `challenge_token`.

### Flow

```
POST /auth/login {email, password}
        │
        ├─ 2FA enabled  → { challenge_token, expires_in_seconds, requires_totp } → go to 2fa/verify
        │
        └─ 2FA not req. → AdminAuthSuccess { access_token, ... } → done

POST /auth/2fa/verify {challenge_token, totp | recovery_code}
        → AdminAuthSuccess { access_token, token_type, expires_in, admin, used_recovery_code?, recovery_codes_remaining? }
```

### 3.1 `POST /auth/login` — Phase 1

| | |
|---|---|
| **Purpose** | Verify email + password; start a 2FA challenge (or, if 2FA disabled, authenticate directly). |
| **Auth** | Public (no bearer) |
| **Body** | `{ email: string(email), password: string(8–128) }` |

**Success — challenge issued (2FA enabled):**
```json
{
  "challenge_token": "chl_8f2c...",
  "expires_in_seconds": 300,
  "requires_totp": true
}
```

**Success — direct (`AdminAuthSuccess`, when 2FA not required):** see §3.6 shape.

**Errors:** `401` bad credentials or account locked (see lockout, §3.5); `422` validation.

**curl**
```bash
curl -sS -X POST https://salamheyetimiz.com/admin/v1/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@salamheyetimiz.com","password":"S3cret-Passw0rd!"}'
```

**axios**
```js
const { data } = await adminApi.post("/auth/login", { email, password });
if (data.challenge_token) {
  // go to 2FA screen, keep challenge_token in memory
} else {
  setAdminToken(data.access_token); // direct success
}
```

### 3.2 `POST /auth/2fa/verify` — Phase 2

| | |
|---|---|
| **Purpose** | Exchange the challenge token + TOTP (or recovery code) for an admin JWT. |
| **Auth** | Public (no bearer); the `challenge_token` is the credential. |
| **Body** | `{ challenge_token: string }` **plus exactly one of** `totp: "\d{6}"` **or** `recovery_code: "[0-9a-fA-F]{10}"` |

`totp` = 6-digit TOTP from the authenticator app. `recovery_code` = 10 hex chars, case-insensitive, **single-use** (consumed on success).

**Success (`AdminAuthSuccess` + recovery extras):**
```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6...",
  "token_type": "Bearer",
  "expires_in": 1800,
  "admin": {
    "id": 1,
    "email": "admin@salamheyetimiz.com",
    "name": "Operator One",
    "role": "super_admin",
    "phone": null,
    "is_2fa_enabled": true,
    "status": "active",
    "last_login_at": "2026-06-21T18:00:00Z",
    "created_at": "2026-01-10T09:00:00Z"
  },
  "used_recovery_code": false,
  "recovery_codes_remaining": 8
}
```

If `used_recovery_code` is `true`, show a banner prompting the admin to regenerate recovery codes (and surface `recovery_codes_remaining`, range 0–8).

**Errors:** `401` with codes `wrong_totp`, `recovery_code_already_used`, expired/invalid challenge.

**curl**
```bash
curl -sS -X POST https://salamheyetimiz.com/admin/v1/auth/2fa/verify \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"challenge_token":"chl_8f2c...","totp":"123456"}'
```

**axios**
```js
const { data } = await adminApi.post("/auth/2fa/verify", {
  challenge_token,
  totp, // OR recovery_code
});
setAdminToken(data.access_token);
if (data.used_recovery_code) showRegenerateCodesBanner(data.recovery_codes_remaining);
```

### 3.3 `GET /auth/me` ✅

| | |
|---|---|
| **Purpose** | Current admin profile (hydrate the session, gate UI by role). |
| **Auth** | `adminBearerAuth` |
| **Response** | `AdminUser` (same shape as `admin` in §3.2). |

```js
const { data: me } = await adminApi.get("/auth/me");
// me.role ∈ ["super_admin","technical"]; gate menus on this.
```

### 3.4 `POST /auth/logout` ✅

| | |
|---|---|
| **Purpose** | Revoke the current admin session server-side. |
| **Auth** | `adminBearerAuth` |
| **Response** | `204 No Content` |

```js
await adminApi.post("/auth/logout");
clearAdminToken();
```

### 3.5 `POST /auth/recovery-codes` ✅ — regenerate recovery codes

| | |
|---|---|
| **Purpose** | Generate a fresh set of **8** single-use recovery codes; invalidates the previous set atomically. |
| **Auth** | `adminBearerAuth` **+ re-auth**: requires a fresh TOTP in the body (re-auth / tfa-verified pattern). |
| **Body** | `{ totp: "\d{6}" }` |
| **Header** | `Idempotency-Key` accepted (optional). |

**Success (the plaintext codes are returned exactly once — display, never store):**
```json
{
  "codes": ["a1b2c3d4e5", "f6e7d8c9b0", "...8 total..."],
  "generated_at": "2026-06-21T18:10:00Z"
}
```

**Errors:** `401` `wrong_totp` (the fresh TOTP did not match).

```js
const { data } = await adminApi.post(
  "/auth/recovery-codes",
  { totp },
  { headers: { "Idempotency-Key": crypto.randomUUID() } }
);
// Render data.codes once for the admin to save. Do not persist them client-side.
```

### 3.6 `AdminAuthSuccess` shape (reference)

```jsonc
{
  "access_token": "string (RS256 JWT)",
  "token_type": "Bearer",
  "expires_in": 1800,          // seconds (30 min)
  "admin": { /* AdminUser */ }
  // 2fa/verify additionally returns: used_recovery_code, recovery_codes_remaining
}
```

### 3.7 Lockout & challenge behavior

- **Lockout:** after **5 failed login attempts** the account is locked for **15 minutes**. During lockout, `POST /auth/login` returns `401` (account locked). Surface a clear "try again in N minutes" message; do not let the UI hammer the endpoint.
- **Challenge TTL:** the `challenge_token` is short-lived (`expires_in_seconds`, e.g. 300s). If it expires, restart from `POST /auth/login`.
- **Recovery codes:** single-use; a consumed code returns `401 recovery_code_already_used`.

### 3.8 Token storage recommendation (admin)

| Option | Verdict |
|---|---|
| In-memory variable (SPA) | ✅ Recommended default. XSS-safe-ish, lost on reload → re-login (acceptable at 30-min TTL). |
| httpOnly + Secure + SameSite=Strict cookie (via same-origin BFF) | ✅ Best if you have a BFF. Not JS-readable. |
| `localStorage` / `sessionStorage` | ❌ **Do not use for admin.** XSS-readable; high-privilege surface. |

---

## 4. Role model & authorization

### Confirmed roles (from `AdminUser.role` enum in the OpenAPI spec)

The deployed schema defines exactly **two** admin roles:

| Role | Scope |
|---|---|
| `super_admin` | Full admin access — all device lifecycle actions, refunds, transfers, settings. |
| `technical` | Devices module + read-only on most other resources; can create devices and resync whitelists. |

> ⚠️ **`support` and `finance` roles are NOT in the deployed contract.** The `AdminUser.role` enum is `[super_admin, technical]` only. If a product spec mentions `support`/`finance`, treat those as **planned** and confirm with the backend before building UI around them. Do **not** invent role strings.

### Role-gated endpoints (from `x-authorization` in the spec)

For implemented endpoints, the spec annotates these authorization requirements:

| Action | Endpoint | Required role |
|---|---|---|
| Disable device | `POST /devices/{id}/disable` | **`super_admin`** only |
| Enable device | `POST /devices/{id}/enable` | **`super_admin`** only |
| Transfer ownership | `POST /devices/{id}/transfer` | **`super_admin`** only |
| Decommission device | `DELETE /devices/{id}` | **`super_admin`** only |
| Create device | `POST /devices` | `technical` or `super_admin` |
| Update device | `PATCH /devices/{id}` | `technical` or `super_admin` |
| Resync whitelist | `POST /devices/{id}/whitelist/resync` | `technical` or `super_admin` |
| Refund order | `POST /orders/{id}/refund` | **`super_admin`** *(spec `x-authorization`)* |
| Recheck order (admin) | `POST /orders/{id}/recheck` | **`super_admin`** *(spec `x-authorization`)* |

> **On refunds:** the prompt's product model describes refunds as "finance/super". The **deployed spec** gates `adminRefundOrder` to `super_admin`. Since `finance` is not a deployed role, treat refunds as **`super_admin`** for now — **role-gated; confirm exact role** with the backend if a `finance` role is later added.
>
> Read-only list/detail endpoints (`GET /devices`, `GET /orders`, `GET /subscriptions`, `GET /refunds`, command/diagnostics/whitelist reads) carry no `x-authorization` restriction in the spec → available to any authenticated admin. **Confirm with backend** whether `technical` is further read-scoped in practice.

Authorization is enforced **server-side**. The frontend should hide/disable controls by `role` for UX, but never rely on client gating for security. A blocked action returns `403` (see §11).

---

## 5. Devices — CRUD & lifecycle ✅ implemented

Device states: `unassigned → active → suspended/disabled → decommissioned`.

### 5.1 `GET /devices` — list devices

| | |
|---|---|
| **Purpose** | Paginated device list for the admin devices module. |
| **Auth** | `adminBearerAuth` |

**Query params / filters:**

| Param | Type | Notes |
|---|---|---|
| `limit` | int (1–100, default 25) | Page size |
| `cursor` | string | Opaque; from previous `page.next_cursor` |
| `status` | enum | `unassigned` \| `active` \| `suspended` \| `disabled` \| `decommissioned` |
| `owner_user_id` | int64 | Filter by owner |
| `region_id` | int | Filter by region |
| `q` | string (≤80) | Free-text search by **serial** or **sim_phone** |

> Note: the deployed filter key is **`owner_user_id`** (not `owner`) and **`region_id`** (not `region`). Use these exact names.

**Success (`AdminDeviceListResponse`):**
```json
{
  "data": [
    {
      "id": 42,
      "serial": "SH-0001-AA",
      "sim_phone": "+994501112233",
      "sim_operator": { "id": 1, "code": "azercell", "name": "Azercell", "country_iso": "AZ", "mcc_mnc": "400-01", "is_active": true },
      "device_model": { "id": 3, "vendor": "Acme", "model_code": "GSM-RELAY-2", "default_driver_type": "traccar", "whitelist_capacity": 200, "is_active": true },
      "driver_type": "traccar",
      "status": "active",
      "owner": { "id": 1001, "phone_masked": "+994 50 *** 22 33", "full_name": "Aysel M." },
      "region": { "id": 5, "code": "BAK", "name": "Bakı", "parent_id": null, "is_active": true },
      "location_label": "Yard gate",
      "latitude": 40.4093,
      "longitude": 49.8671,
      "last_online_at": "2026-06-21T17:55:00Z",
      "last_signal_strength": 21,
      "whitelist_capacity_used": 7,
      "created_at": "2026-02-01T10:00:00Z"
    }
  ],
  "page": { "next_cursor": "eyJpZCI6NDJ9", "has_more": true, "limit": 25 }
}
```

**curl**
```bash
curl -sS "https://salamheyetimiz.com/admin/v1/devices?status=active&region_id=5&q=SH-00&limit=25" \
  -H "Accept: application/json" -H "Authorization: Bearer $ADMIN_TOKEN"
```

**axios**
```js
const { data } = await adminApi.get("/devices", {
  params: { status: "active", region_id: 5, q: "SH-00", limit: 25, cursor },
});
```

### 5.2 `POST /devices` — register a device

| | |
|---|---|
| **Purpose** | Provision a new physical device (back-office alternative to mobile technical mode). |
| **Auth** | `adminBearerAuth` — `technical` or `super_admin` |
| **Header** | `Idempotency-Key` (accepted, recommended) |
| **Body** | `DeviceTechRegister` |

**Body (`DeviceTechRegister`):**
```jsonc
{
  "serial": "SH-0009-BB",        // required, 4–64 chars
  "device_model_id": 3,           // required, int64
  "sim_phone": "+994501234567",   // required, ^\+994\d{9}$
  "sim_operator_id": 1,           // optional
  "sim_iccid": "8994000000000000001", // optional, 19–22 digits
  "driver_type": "traccar",       // required: traccar | ble | sms
  "firmware_version": "1.4.0",    // optional
  "region_id": 5,                 // optional
  "location_label": "Yard gate",  // optional, ≤160
  "latitude": 40.4093,            // optional, -90..90
  "longitude": 49.8671,           // optional, -180..180
  "metadata": { "note": "..." }   // optional object
}
```

**Success:** `201` → `DeviceAdmin` (created in `unassigned`). **Errors:** `422` validation.

**axios**
```js
const { data } = await adminApi.post("/devices", body, {
  headers: { "Idempotency-Key": crypto.randomUUID() },
});
```

### 5.3 `GET /devices/{deviceId}` — device detail (with roster)

| | |
|---|---|
| **Purpose** | Full admin detail incl. roster (`users[]`) and 30-day stats. |
| **Auth** | `adminBearerAuth` |
| **Response** | `DeviceAdminDetail` |

`DeviceAdminDetail` = `DeviceAdmin` (§5.1) **plus**:
```jsonc
{
  "users": [
    {
      "id": 88, "role": "owner", "status": "active",
      "user": { "id": 1001, "phone_masked": "+994 50 *** 22 33", "full_name": "Aysel M." },
      "added_by_user": { "id": 1001, "phone_masked": "...", "full_name": "..." },
      "added_at": "2026-02-01T10:05:00Z",
      "last_open_at": "2026-06-21T08:00:00Z",
      "subscription": { "id": 9001, "tier": "main", "status": "active", "ends_at": "2026-08-01T00:00:00Z", "days_remaining": 41, "auto_renew": false }
    }
  ],
  "stats": { "opens_30d": 312, "success_rate_30d": 0.98, "last_open_at": "2026-06-21T08:00:00Z" },
  "metadata": { "...": "..." }
}
```

**Errors:** `404` not found.

### 5.4 `PATCH /devices/{deviceId}` — update device

| | |
|---|---|
| **Purpose** | Edit mutable device fields. |
| **Auth** | `adminBearerAuth` — `technical` or `super_admin` |
| **Body** | `DeviceAdminUpdate` (all optional): `firmware_version`, `driver_type`, `sim_operator_id`, `sim_iccid`, `region_id`, `location_label`, `latitude`, `longitude`, `metadata` |
| **Response** | `200` → `DeviceAdmin`. **Errors:** `422`. |

```js
await adminApi.patch(`/devices/${id}`, { location_label: "Front gate", region_id: 5 });
```

### 5.5 `DELETE /devices/{deviceId}` — decommission (soft delete)

| | |
|---|---|
| **Purpose** | Soft-delete / decommission a device. |
| **Auth** | `adminBearerAuth` — **`super_admin`** |
| **Header** | `Idempotency-Key` (accepted) |
| **Body** | `{ reason: string (3–255) }` *(required)* |
| **Response** | `204 No Content` |

```js
await adminApi.delete(`/devices/${id}`, {
  data: { reason: "Hardware retired" },
  headers: { "Idempotency-Key": crypto.randomUUID() },
});
```

### 5.6 `POST /devices/{deviceId}/disable` — disable (block opens)

| | |
|---|---|
| **Purpose** | Block all opens but keep the device and its history. |
| **Auth** | `adminBearerAuth` — **`super_admin`** |
| **Body** | `{ reason: string (≤255) }` *(required)* |
| **Response** | `200` → `DeviceAdmin` (`status: "disabled"`). |

```bash
curl -sS -X POST https://salamheyetimiz.com/admin/v1/devices/42/disable \
  -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
  -d '{"reason":"Non-payment dispute"}'
```

### 5.7 `POST /devices/{deviceId}/enable` — re-enable

| | |
|---|---|
| **Purpose** | Re-enable a previously disabled device. |
| **Auth** | `adminBearerAuth` — **`super_admin`** |
| **Body** | none |
| **Response** | `200` → `DeviceAdmin`. |

### 5.8 `POST /devices/{deviceId}/transfer` — transfer ownership

| | |
|---|---|
| **Purpose** | Reassign device ownership to another user (by phone). |
| **Auth** | `adminBearerAuth` — **`super_admin`** |
| **Body** | `{ new_owner_phone: "^\+994\d{9}$", reason: string(≤255), keep_existing_users?: boolean (default true) }` |
| **Response** | `200` → `DeviceAdmin`. |

```js
await adminApi.post(`/devices/${id}/transfer`, {
  new_owner_phone: "+994559876543",
  reason: "Property sold",
  keep_existing_users: true,
});
```

---

## 6. Device telemetry — commands, diagnostics, whitelist ✅ implemented

### 6.1 `GET /devices/{deviceId}/commands` — open-command history (all users)

| | |
|---|---|
| **Purpose** | Full open-command history for the device, across all users. |
| **Auth** | `adminBearerAuth` |

**Filters:** `limit`, `cursor`, `state` (`queued`\|`dispatched`\|`opened`\|`failed`\|`expired`), `since`, `until` (date-time).

**Success (`OpenCommandListResponse`):**
```json
{
  "data": [
    {
      "id": 55012, "device_id": 42, "state": "opened",
      "failure_reason": null, "driver": "traccar",
      "requested_at": "2026-06-21T08:00:00Z",
      "dispatched_at": "2026-06-21T08:00:01Z",
      "completed_at": "2026-06-21T08:00:03Z",
      "latency_ms": 2150, "attempts": 1
    }
  ],
  "page": { "next_cursor": null, "has_more": false, "limit": 25 }
}
```

> The `OpenCommand.state` enum also includes `dispatching` (intermediate). The list `state` **filter** accepts `queued|dispatched|opened|failed|expired`.

### 6.2 `GET /devices/{deviceId}/diagnostics` — diagnostics history

| | |
|---|---|
| **Purpose** | Historical device health pings (online status, signal, battery, firmware). |
| **Auth** | `adminBearerAuth` |
| **Filters** | `limit`, `cursor` |

**Success (`DeviceDiagnosticListResponse`):**
```json
{
  "data": [
    {
      "id": 7, "device_id": 42,
      "source": "scheduled_ping", "online": true,
      "signal_strength": 21, "battery_level": null,
      "firmware_version": "1.4.0", "reported_at": "2026-06-21T17:55:00Z"
    }
  ],
  "page": { "next_cursor": null, "has_more": false, "limit": 25 }
}
```
`source` ∈ `scheduled_ping | open_dispatch | admin_ping | device_initiated`. `signal_strength` 0–31.

### 6.3 `GET /devices/{deviceId}/whitelist-queue` — whitelist sync queue

| | |
|---|---|
| **Purpose** | Pending and recent whitelist changes being synced to the device. |
| **Auth** | `adminBearerAuth` |
| **Filters** | `limit`, `cursor`, `status` (`pending`\|`in_progress`\|`synced`\|`failed`\|`cancelled`) |

**Success (`WhitelistChangeListResponse`):**
```json
{
  "data": [
    {
      "id": 9, "device_id": 42, "action": "add", "phone": "+994559876543",
      "status": "pending", "attempt_count": 0, "last_error": null,
      "last_attempt_at": null, "next_attempt_at": "2026-06-21T18:05:00Z",
      "synced_at": null, "created_at": "2026-06-21T18:00:00Z"
    }
  ],
  "page": { "next_cursor": null, "has_more": false, "limit": 25 }
}
```
`action` ∈ `add | remove | clear`.

### 6.4 `POST /devices/{deviceId}/whitelist/resync` — force full resync

| | |
|---|---|
| **Purpose** | Enqueue a full whitelist resync to the device. |
| **Auth** | `adminBearerAuth` — `technical` or `super_admin` |
| **Header** | `Idempotency-Key` (accepted) |
| **Response** | `202 Accepted` (queued; no body). |

```js
await adminApi.post(`/devices/${id}/whitelist/resync`, null, {
  headers: { "Idempotency-Key": crypto.randomUUID() },
});
// then poll GET /devices/{id}/whitelist-queue to watch progress
```

---

## 7. Orders ✅ implemented

### 7.1 `GET /orders` — list orders

| | |
|---|---|
| **Purpose** | Paginated order list. |
| **Auth** | `adminBearerAuth` |

**Filters:** `limit`, `cursor`, `status`, `purpose`, `payer_user_id`, `since`, `until`.

| Filter | Values |
|---|---|
| `status` | `pending` \| `authorising` \| `paid` \| `failed` \| `cancelled` \| `refunded` \| `partially_refunded` \| `expired` |
| `purpose` | `device_sale` \| `sub_main` \| `sub_additional` \| `sub_renewal` \| `bundle` |
| `payer_user_id` | int64 |
| `since` / `until` | date-time |

**Success (`OrderListResponse`):**
```json
{
  "data": [
    {
      "id": 12345, "reference": "SH-202606-000123", "status": "paid",
      "purpose": "sub_main", "amount_minor": 1200, "currency": "AZN",
      "bank_redirect_url": null, "expires_at": null,
      "paid_at": "2026-06-15T12:00:05Z", "failed_at": null, "failed_reason": null,
      "created_at": "2026-06-15T12:00:00Z",
      "items": [
        { "id": 1, "item_type": "sub_main", "referenced_id": 9001, "description": "Main subscription", "quantity": 1, "unit_amount_minor": 1200, "total_amount_minor": 1200 }
      ]
    }
  ],
  "page": { "next_cursor": "eyJ...", "has_more": true, "limit": 25 }
}
```

### 7.2 `GET /orders/{orderId}` — order detail (full payment timeline)

| | |
|---|---|
| **Purpose** | Order with payments, refunds, and a timeline. |
| **Auth** | `adminBearerAuth` |
| **Response** | `OrderDetail` = `Order` + `payments[]` + `refunds[]` + `timeline[]`. |

```jsonc
{
  // ...all Order fields...
  "payments": [
    { "id": 5001, "order_id": 12345, "bank_transaction_id": "TX-998877", "type": "charge",
      "amount_minor": 1200, "currency": "AZN", "status": "approved",
      "card_brand": "visa", "card_last4": "1234", "occurred_at": "2026-06-15T12:00:05Z" }
  ],
  "refunds": [ /* Refund objects, see §8 */ ],
  "timeline": [ { "at": "2026-06-15T12:00:00Z", "event": "order_created", "detail": null } ]
}
```

### 7.3 `POST /orders/{orderId}/recheck` — force bank-status recheck

| | |
|---|---|
| **Purpose** | Force a reconciliation against the bank (the auto-reconciler does this periodically). |
| **Auth** | `adminBearerAuth` — **`super_admin`** *(spec `x-authorization`)* |
| **Header** | `Idempotency-Key` (accepted) |
| **Response** | `200` → `Order` (latest known state). |

```js
const { data: order } = await adminApi.post(`/orders/${id}/recheck`, null, {
  headers: { "Idempotency-Key": crypto.randomUUID() },
});
```

### 7.4 `POST /orders/{orderId}/refund` — initiate a refund

| | |
|---|---|
| **Purpose** | Refund an amount against a paid order. |
| **Auth** | `adminBearerAuth` — **`super_admin`** *(spec `x-authorization`; "finance" is not a deployed role — confirm if added later)* |
| **Header** | **`Idempotency-Key` REQUIRED** (server rejects on absence). |
| **Body** | `{ amount_minor: int ≥ 1, reason: string (3–255) }` |

`amount_minor` must be ≤ the order's **net** (gross minus prior refunds). Partial refunds are allowed.

**Success:** `201` → `Refund` (see §8). **Errors:** `409` order not refundable in current state; `422` validation; `409 idempotency_mismatch` on key reuse with a different body.

**curl**
```bash
curl -sS -X POST https://salamheyetimiz.com/admin/v1/orders/12345/refund \
  -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
  -H "Idempotency-Key: 9f1d2c3b-...-uuid" \
  -d '{"amount_minor":1200,"reason":"Customer cancelled within grace period"}'
```

**axios**
```js
const { data: refund } = await adminApi.post(
  `/orders/${id}/refund`,
  { amount_minor: 1200, reason: "Customer cancelled" },
  { headers: { "Idempotency-Key": crypto.randomUUID() } } // REQUIRED
);
```

---

## 8. Refunds ✅ implemented

### 8.1 `GET /refunds` — list refunds (all orders)

| | |
|---|---|
| **Purpose** | Cross-order refund list. |
| **Auth** | `adminBearerAuth` |
| **Filters** | `limit`, `cursor`, `status` (`requested`\|`processing`\|`approved`\|`rejected`\|`failed`) |

**Success (`RefundListResponse`):**
```json
{
  "data": [
    {
      "id": 701, "order_id": 12345, "amount_minor": 1200,
      "reason": "Customer cancelled", "status": "approved",
      "requested_by_admin_id": 1, "processed_by_admin_id": 1,
      "linked_payment_id": 5002, "error_message": null,
      "created_at": "2026-06-16T09:00:00Z"
    }
  ],
  "page": { "next_cursor": null, "has_more": false, "limit": 25 }
}
```

> Refund `status` enum is `requested | processing | approved | rejected | failed` (the order's own status flips to `refunded` / `partially_refunded`).

---

## 9. Subscriptions ✅ implemented

### 9.1 `GET /subscriptions` — list subscriptions

| | |
|---|---|
| **Purpose** | Paginated subscription list (e.g. to find expiring subs). |
| **Auth** | `adminBearerAuth` |
| **Filters** | `limit`, `cursor`, `status` (`pending_payment`\|`active`\|`expired`\|`cancelled`\|`refunded`), `expires_within_days` (1–90) |

**Success (`SubscriptionListResponse`):**
```json
{
  "data": [
    {
      "id": 9001, "tier": "main", "status": "active",
      "ends_at": "2026-08-01T00:00:00Z", "days_remaining": 41, "auto_renew": false,
      "device_id": 42, "user_id": 1001,
      "starts_at": "2026-02-01T00:00:00Z",
      "price_minor": 1200, "currency": "AZN", "term_days": 365,
      "last_reminder_kind": "d30", "last_reminder_sent_at": "2026-07-02T09:00:00Z"
    }
  ],
  "page": { "next_cursor": "eyJ...", "has_more": true, "limit": 25 }
}
```
`tier` ∈ `main | additional`. `last_reminder_kind` ∈ `d30 | d15 | d7 | d1 | expired | null`.

```bash
curl -sS "https://salamheyetimiz.com/admin/v1/subscriptions?status=active&expires_within_days=30" \
  -H "Authorization: Bearer $ADMIN_TOKEN" -H "Accept: application/json"
```

> There is **no admin subscription-detail endpoint** deployed (`GET /admin/v1/subscriptions/{id}` is not in the route table). Use `GET /subscriptions` (list) for admin; the per-subscription detail endpoint exists only on the **mobile** surface.

---

## 10. Pagination & filtering

All list endpoints use **opaque cursor pagination** — never offset.

**Request params (shared):**

| Param | Type | Default | Max |
|---|---|---|---|
| `limit` | int | 25 | 100 |
| `cursor` | string (opaque) | — | — |

**Response envelope (every list):**
```json
{
  "data": [ /* items */ ],
  "page": { "next_cursor": "eyJ...", "has_more": true, "limit": 25 }
}
```
`next_cursor` is `null` on the last page. **Never build the cursor yourself** — only echo back `page.next_cursor`.

**Pagination loop pattern:**
```js
async function fetchAll(path, params = {}) {
  const out = [];
  let cursor = undefined;
  do {
    const { data } = await adminApi.get(path, { params: { ...params, cursor, limit: 100 } });
    out.push(...data.data);
    cursor = data.page.next_cursor ?? undefined;
  } while (cursor);
  return out;
}
```

**Per-endpoint filters:**

| Endpoint | Filters (besides `limit`/`cursor`) |
|---|---|
| `GET /devices` | `status`, `owner_user_id`, `region_id`, `q` (serial/sim_phone) |
| `GET /devices/{id}/commands` | `state`, `since`, `until` |
| `GET /devices/{id}/diagnostics` | — |
| `GET /devices/{id}/whitelist-queue` | `status` |
| `GET /orders` | `status`, `purpose`, `payer_user_id`, `since`, `until` |
| `GET /refunds` | `status` |
| `GET /subscriptions` | `status`, `expires_within_days` |

---

## 11. Error handling

### 11.1 Standard error envelope (all 4xx/5xx except 422)

```json
{
  "error": {
    "code": "device_disabled",
    "message_key": "errors.device_disabled",
    "message": "Cihaz deaktiv edilib.",
    "details": { "device_id": 42 },
    "request_id": "01JCGZ9F9XZ7P0X5MFK7Z7VYNZ"
  }
}
```
- `code` — stable machine key; **branch on this**, not on `message`.
- `message` — localized per `Accept-Language` (az/ru/en); safe to show users.
- `message_key` — locale-independent stable key.
- `details` — context object or `null`.
- `request_id` — correlation ID; log it and show it in error toasts for support.

### 11.2 Validation errors — `422`

Different envelope (`ValidationErrorEnvelope`) with per-field arrays:
```json
{
  "error": {
    "code": "validation_failed",
    "message": "Yoxlama xətası.",
    "fields": {
      "amount_minor": ["Məbləğ sıfırdan böyük olmalıdır."],
      "reason": ["Səbəb tələb olunur."]
    },
    "request_id": "01JCGZ..."
  }
}
```
Map `error.fields[fieldName][]` onto form inputs.

### 11.3 Status codes you must handle

| HTTP | `code` (typical) | Meaning | Frontend action |
|---|---|---|---|
| `400` | `bad_request` | Malformed request | Fix request shape |
| `401` | `unauthenticated` | Missing/invalid/**expired** token | **Clear token, redirect to login.** No admin refresh token — re-run login + 2FA. (The interceptor in §2 does this.) |
| `403` | `forbidden` | Authenticated but role-disallowed | Hide/disable the action; show "insufficient permissions". Gate by `role` from `GET /auth/me`. |
| `404` | `not_found` | Resource missing | Show not-found state |
| `409` | `conflict` / `idempotency_mismatch` | State precludes op; or same `Idempotency-Key` + different body | Refresh state; on idempotency_mismatch use a fresh key |
| `422` | `validation_failed` | Field errors | Map `fields` onto the form |
| `429` | `rate_limited` | Bucket exhausted (admin generic ~600/min/admin) | Back off using `Retry-After` header (seconds) |
| `503` | `payment_provider_unavailable` | Bank degraded (recheck/refund) | Retry later; surface "bank temporarily unavailable" |
| `500` | `internal_error` | Unexpected | Show generic error + `request_id` |

### 11.4 Rate-limit headers

Every response includes:
```
X-RateLimit-Limit: 600
X-RateLimit-Remaining: 597
X-RateLimit-Reset: 1717945200
```
On `429`, honor `Retry-After` (seconds) before retrying.

---

## 12. Admin endpoint cheat-sheet

✅ = deployed; build against these. ⏳ = planned (backend not deployed — provisional shapes).

| Group | Method | Path (`/admin/v1` …) | operationId | Auth / role | Status |
|---|---|---|---|---|---|
| Auth | POST | `/auth/login` | adminLogin | public | ✅ |
| Auth | POST | `/auth/2fa/verify` | adminVerify2fa | public (challenge) | ✅ |
| Auth | GET | `/auth/me` | adminMe | admin | ✅ |
| Auth | POST | `/auth/logout` | adminLogout | admin | ✅ |
| Auth | POST | `/auth/recovery-codes` | regenerateRecoveryCodes | admin + fresh TOTP | ✅ |
| Devices | GET | `/devices` | adminListDevices | admin | ✅ |
| Devices | POST | `/devices` | adminCreateDevice | technical/super | ✅ |
| Devices | GET | `/devices/{id}` | adminGetDevice | admin | ✅ |
| Devices | PATCH | `/devices/{id}` | adminUpdateDevice | technical/super | ✅ |
| Devices | DELETE | `/devices/{id}` | adminDecommissionDevice | **super** | ✅ |
| Devices | POST | `/devices/{id}/disable` | adminDisableDevice | **super** | ✅ |
| Devices | POST | `/devices/{id}/enable` | adminEnableDevice | **super** | ✅ |
| Devices | POST | `/devices/{id}/transfer` | adminTransferDevice | **super** | ✅ |
| Devices | GET | `/devices/{id}/commands` | adminDeviceCommands | admin | ✅ |
| Devices | GET | `/devices/{id}/diagnostics` | adminDeviceDiagnostics | admin | ✅ |
| Devices | GET | `/devices/{id}/whitelist-queue` | adminWhitelistQueue | admin | ✅ |
| Devices | POST | `/devices/{id}/whitelist/resync` | adminResyncWhitelist | technical/super | ✅ |
| Orders | GET | `/orders` | adminListOrders | admin | ✅ |
| Orders | GET | `/orders/{id}` | adminGetOrder | admin | ✅ |
| Orders | POST | `/orders/{id}/recheck` | adminRecheckOrder | **super** | ✅ |
| Orders | POST | `/orders/{id}/refund` | adminRefundOrder | **super** (Idempotency-Key required) | ✅ |
| Refunds | GET | `/refunds` | adminListRefunds | admin | ✅ |
| Subscriptions | GET | `/subscriptions` | adminListSubscriptions | admin | ✅ |
| **Dashboard** | GET | `/metrics/overview` | adminMetricsOverview | admin | ⏳ planned |
| **Users** | GET | `/users` · `/users/{id}` · `/users/{id}/block` · `/users/{id}/unblock` | adminListUsers / adminGetUser / adminBlockUser / adminUnblockUser | admin / super | ⏳ planned |
| **Admins** | GET/POST | `/admins` · PATCH/DELETE `/admins/{id}` | adminListAdmins / adminCreateAdmin / adminUpdateAdmin / adminOffboardAdmin | super | ⏳ planned |
| **Lookups** | GET | `/lookups/sim-operators` · `/lookups/device-models` · `/lookups/regions` | adminListSimOperators / adminListDeviceModels / adminListRegions | admin | ⏳ planned |
| **Reports** | GET | `/reports/revenue` · `/reports/devices` · `/reports/subscriptions` · `/report-jobs` | adminReportsRevenue / adminReportsDevices / adminReportsSubscriptions / adminListReportJobs | admin | ⏳ planned |
| **Audit** | GET | `/audit` | adminAuditSearch | admin | ⏳ planned |
| **Settings** | GET | `/settings` · PATCH `/settings/{key}` | adminListSettings / adminUpdateSetting | admin / super | ⏳ planned |
| **Feature Flags** | GET | `/feature-flags` · PATCH `/feature-flags/{key}` | adminListFeatureFlags / adminUpdateFeatureFlag | admin / super | ⏳ planned |
| **Notification Templates** | GET/PATCH/PUT | `/notification-templates*` | adminListNotificationTemplates / … | admin / super | ⏳ planned |

> ⏳ **Planned features — backend not yet deployed.** Dashboard (`metrics/overview`), Users (customer management), Admins (admin account CRUD), Lookups (SIM operators / device models / regions), Reports (revenue/devices/subscriptions + async report jobs), Audit log, Settings, Feature Flags, and Notification Templates are defined in `docs/openapi/v1.yaml` but are **not** in the deployed route table. Do not build production flows against them yet; their request/response shapes may change before deployment. When they ship, the lookup endpoints in particular (regions, device models, SIM operators) will be needed to populate the device create/edit forms in §5.2/§5.4 — until then, source those option lists from the backend separately or hard-code per environment.

---

## 13. Quick reference — enums

| Field | Values |
|---|---|
| `AdminUser.role` | `super_admin`, `technical` *(only these are deployed)* |
| `AdminUser.status` | `active`, `suspended`, `offboarded` |
| Device `status` | `unassigned`, `active`, `suspended`, `disabled`, `decommissioned` |
| `driver_type` | `traccar`, `ble`, `sms` |
| `OpenCommand.state` | `queued`, `dispatching`, `dispatched`, `opened`, `failed`, `expired` |
| Order `status` | `pending`, `authorising`, `paid`, `failed`, `cancelled`, `refunded`, `partially_refunded`, `expired` |
| Order `purpose` | `device_sale`, `sub_main`, `sub_additional`, `sub_renewal`, `bundle` |
| `Refund.status` | `requested`, `processing`, `approved`, `rejected`, `failed` |
| Subscription `status` | `pending_payment`, `active`, `expired`, `cancelled`, `refunded` |
| Subscription `tier` | `main`, `additional` |
| WhitelistChange `status` | `pending`, `in_progress`, `synced`, `failed`, `cancelled` |
| Diagnostic `source` | `scheduled_ping`, `open_dispatch`, `admin_ping`, `device_initiated` |

---

*Accurate to `docs/openapi/v1.yaml` (v1.2.0) and `docs/_api_ground_truth.md` (verified 2026-06-21). Where the product spec and the deployed contract differ (e.g. `support`/`finance` roles, refund role gating), this guide follows the **deployed contract** and flags the discrepancy. Confirm any "role-gated; confirm exact role" item with the backend before relying on it.*
