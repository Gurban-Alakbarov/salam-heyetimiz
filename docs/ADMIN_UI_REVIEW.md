# Admin Panel UI — Review

**Date:** 2026-06-21
**Location:** `admin-ui/` (isolated SPA; **no backend code modified**)
**Stack:** React 18 · TypeScript 5.6 · Vite 5 · TailwindCSS 3 · React Router 6 · TanStack Query 5 · Axios · shadcn/ui (Radix)
**Target API (live):** `https://admin.salamheyetimiz.com/admin/v1`
**Result:** ✅ Build clean · ✅ Lint clean (0 warnings) · ✅ API integration verified against the real backend.

---

## 1. Build & lint

```
npm run build   →  tsc -b && vite build  →  2109 modules, dist/ (JS 480 kB / gzip 150 kB, CSS 24 kB) ✅
npm run lint    →  eslint --max-warnings 0  →  0 errors, 0 warnings ✅
```

63 TypeScript/TSX source files. No `any`, no unused locals, no TODO comments, no mock data.

## 2. API integration verification (real backend, no mocks)

Verified via the Vite dev proxy (`/admin/v1` → live backend) and direct calls:

| Check | Result |
|---|---|
| SPA loads (`GET /`) | 200 text/html ✅ |
| `POST /admin/v1/auth/login {}` | 422 `validation_failed` with `fields.{email,password}` — parsed by `ApiError` ✅ |
| `POST /auth/login` (bad creds) | 401 `invalid_credentials` ✅ |
| Expired/invalid token → `GET /auth/me` | 401 `unauthenticated` → interceptor clears session + redirects to `/login` ✅ |
| Built bundle references | real paths `/admin/v1/auth/login`, `/admin/v1/auth/me`, `/admin/v1/devices` ✅ |

**Backend note (NOT fixed — backend frozen):** a protected admin endpoint hit with **no `Authorization`
header at all** returns **500** instead of 401. The SPA never triggers this — `AuthProvider` only calls
protected endpoints when a token exists, and authenticated requests always send the Bearer header; an
**expired** token correctly yields 401. Recommend the backend return 401 for a missing token (hardening).

## 3. Implemented screens (all bound to real endpoints)

| Route | Screen | Endpoints |
|---|---|---|
| `/login` · `/login/2fa` | Login + mandatory 2FA (TOTP/recovery) | `POST /auth/login`, `/auth/2fa/verify` |
| `/` | Dashboard (real recent devices/subs/orders/refunds) | list endpoints `?limit=5` |
| `/devices` | List + search (`q`) + status filter + cursor pagination | `GET /devices` |
| `/devices/new` · `/devices/:id/edit` | Create / edit (technical+super) | `POST /devices`, `PATCH /devices/{id}` |
| `/devices/:id` | Detail: overview, roster, **Diagnostics / Commands / Whitelist** tabs | `GET /devices/{id}` + `/diagnostics` `/commands` `/whitelist-queue` |
| device actions | Enable / Disable / Transfer / Decommission / Resync (role-gated) | `/enable` `/disable` `/transfer` `DELETE` `/whitelist/resync` |
| `/subscriptions` | List + status/tier filters + status badges | `GET /subscriptions` |
| `/orders` · `/orders/:id` | List + detail + recheck + refund (super) | `GET /orders`, `/orders/{id}`, `/recheck`, `/refund` |
| `/refunds` | List + status filter | `GET /refunds` |
| `/account` | Profile + regenerate recovery codes (TOTP) + logout | `GET /auth/me`, `POST /auth/recovery-codes`, `/auth/logout` |
| `*` | 404 | — |

**Common UI:** AppLayout (responsive sidebar + mobile sheet + header/admin menu), error envelope handling
(`ApiError` with `code`/`message`/`fields`/`request_id`), loading skeletons, empty/error states, toasts,
role-based action gating (`super_admin` vs `technical`), cursor pagination, debounced search.

## 4. Authentication & security

- Mandatory two-phase: email+password → 2FA challenge → TOTP or recovery code → `access_token`.
- Token in `sessionStorage` (no backend refresh token / httpOnly admin session exists → documented).
- Axios interceptor injects Bearer; **401 → clear session + redirect to `/login`** (no silent refresh — admin tokens are stateless 30-min, no refresh endpoint).
- Route guards (`ProtectedRoute`/`PublicRoute`) + UI `RoleGate` for privileged actions; create/edit routes redirect non-privileged roles.

## 5. Phase completion

| Phase | Scope | Status |
|---|---|---|
| A — Foundation & Common UI | scaffold, theme, api client, types, states, layout, routing, guards, toasts | ✅ |
| B — Authentication | login, 2FA, logout, session hydration, error/lockout surfacing | ✅ |
| C — Dashboard | real recent data, online/offline derivation, navigation cards | ✅ |
| D — Device Management | list/search/filter/detail/tabs/lifecycle/create/edit | ✅ |
| E — Subscriptions & Orders/Refunds | subscription list, orders list/detail/recheck/refund, refunds list | ✅ |

## 6. Not built — backend dependency (NOT stubbed, per "no mock data / no placeholder")

| Requested | Why not built |
|---|---|
| **User Management** (user list/detail, phone search, device/sub assignments) | No `adminListUsers`/`adminGetUser` endpoints deployed. User data is visible only via Device → roster. |
| **Dashboard aggregate counters** (total/online/offline counts) | No stats endpoint; list endpoints are cursor-paginated (no totals). Real recent data is shown instead — no invented numbers. |
| **Subscription detail page** | Only `GET /subscriptions` (list) is deployed; no detail endpoint. |
| **Create-form dropdowns** (device model / region / operator) | No lookups endpoint; create/edit use numeric ID inputs (server-validated `exists:`). Dropdowns need a lookups endpoint. |
| **Admin token refresh** | Backend has no admin refresh endpoint; 401 → re-login. |

These are tracked in `ADMIN_UI_ARCHITECTURE.md` §1 as backend dependencies for a future batch.

## 7. How to run

```bash
cd admin-ui
npm install
npm run dev      # http://localhost:5173 — proxies /admin/v1 to the live backend (no CORS)
npm run build    # → dist/  (deploy to https://admin.salamheyetimiz.com, same-origin)
npm run lint
```

`.env` is optional: `VITE_API_BASE_URL` empty = dev proxy / prod same-origin; set an absolute origin only when hosting the SPA elsewhere.

## 8. Verification note (full E2E)

Production has **no admin account seeded**, so authenticated end-to-end flows (logged-in screens) could not
be exercised live. Verified instead: unauthenticated contracts (login 401/422, expired-token 401), correct
endpoint wiring (built bundle + dev-proxy round-trips), clean build + lint. Full E2E requires provisioning a
super-admin account (a data action, not a code change) — out of scope here.

## 9. Stop point

Admin Panel UI complete. **Flutter app NOT started. Backend NOT modified.** Awaiting review.
