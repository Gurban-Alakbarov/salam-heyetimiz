# Admin Panel UI — Architecture

**Date:** 2026-06-21
**Target backend (live):** `https://admin.salamheyetimiz.com/admin/v1` (same Laravel app also at `https://api.salamheyetimiz.com`).
**Stack:** React 18 + TypeScript + Vite + TailwindCSS 3 + React Router 6 + TanStack Query 5 + Axios + shadcn/ui (Radix primitives).
**Project location:** `admin-ui/` (isolated SPA; does not touch backend code).

> This document is the pre-code source of truth: backend coverage matrix, auth flow, route map, screen
> map, component hierarchy, and folder structure. Every screen is bound to a REAL deployed endpoint.

---

## 1. Backend coverage matrix (verified against production `route:list` + resource classes)

| Requested feature | Endpoint(s) | Status | Notes |
|---|---|---|---|
| Login | `POST /auth/login` | ✅ | Always returns a 2FA challenge (mandatory TOTP) |
| OTP / 2FA verification | `POST /auth/2fa/verify` | ✅ | TOTP **or** recovery code |
| Logout | `POST /auth/logout` | ✅ | 204 |
| Current admin (me) | `GET /auth/me` | ✅ | role-based nav source |
| Token refresh | — | ⛔ **N/A** | admin tokens are stateless 30-min; **no refresh endpoint** → on 401 re-login |
| Protected routes / role nav | derived from `/auth/me` `role` | ✅ | roles: `super_admin`, `technical` |
| Dashboard — recent activity | `adminListDevices`, `adminListOrders`, `adminListRefunds` (first page) | ✅ | real recent rows |
| Dashboard — aggregate counters (totals, online/offline counts) | — | ⛔ **no stats endpoint** | lists are cursor-paginated (no totals). We show real recent data + section counts of the loaded page, **never invented totals** |
| Device list / search / filters | `GET /devices?status,owner_user_id,region_id,q,limit,cursor` | ✅ | cursor pagination |
| Device details | `GET /devices/{id}` | ✅ | includes roster `users[]` + each user's subscription brief |
| Enable / Disable | `POST /devices/{id}/enable`, `/disable` | ✅ | **super_admin only**; disable needs `reason` |
| Transfer ownership | `POST /devices/{id}/transfer` | ✅ | **super_admin**; `{new_owner_phone, reason, keep_existing_users}` |
| Decommission | `DELETE /devices/{id}` | ✅ | **super_admin**; `{reason}` |
| Create / Update device | `POST /devices`, `PATCH /devices/{id}` | ✅ | **technical/super** |
| Diagnostics view | `GET /devices/{id}/diagnostics` | ✅ | cursor |
| Command history | `GET /devices/{id}/commands` | ✅ | cursor |
| Whitelist queue + resync | `GET /devices/{id}/whitelist-queue`, `POST /devices/{id}/whitelist/resync` | ✅ | resync = technical/super |
| Subscription list | `GET /subscriptions` | ✅ | cursor + filters |
| Subscription details | — | ⛔ **no detail endpoint** | only the list row data is available (shown in a detail drawer from list data) |
| Subscription status indicators | from list `status` | ✅ | |
| Orders list / detail / recheck / refund | `GET /orders`, `/orders/{id}`, `POST /orders/{id}/recheck`, `/refund` | ✅ | refund = super_admin |
| Refunds list | `GET /refunds` | ✅ | |
| **User Management** (user list, details, device/subscription assignments, phone search) | — | ⛔ **NOT DEPLOYED** | no `adminListUsers`/`adminGetUser` endpoints exist. **Not built** (would require mock data, which is forbidden). User data is visible only via Device → roster. Tracked as a backend dependency. |

**Honesty rule applied:** ⛔ items are NOT shipped as fake/placeholder screens. They are documented here and
in `ADMIN_UI_REVIEW.md` as backend dependencies. Everything ✅ is implemented end-to-end against the real API.

## 2. Authentication flow (mandatory 2FA)

```
[Login] email+password ──POST /auth/login──► { challenge_token, expires_in_seconds, requires_totp }
   └─► [2FA] TOTP (6 digits) or recovery code ──POST /auth/2fa/verify { challenge_token, totp|recovery_code }
          └─► { access_token, token_type:"Bearer", expires_in, admin:{id,email,name,role,...} }
                 └─► store token (sessionStorage) + admin in memory ─► /auth/me on app boot to re-hydrate
```

- **Token storage:** `sessionStorage` (survives reload, cleared on tab close). The backend exposes no
  httpOnly-cookie admin session and **no refresh token**, so sessionStorage is the practical choice for a
  30-min token; a security note + backend recommendation is recorded in the review.
- **401 handling:** any 401 → clear session → redirect to `/login` (no silent refresh possible).
- **Lockout:** 5 failed logins → 15-min lockout (backend-enforced); surfaced via the error envelope.

## 3. Route map

| Path | Screen | Guard | Role |
|---|---|---|---|
| `/login` | Login (email+password) | public | — |
| `/login/2fa` | 2FA challenge | public (needs challenge in memory) | — |
| `/` | Dashboard | protected | any admin |
| `/devices` | Device list | protected | any admin |
| `/devices/new` | Create device | protected | technical/super |
| `/devices/:id` | Device detail (tabs: overview, roster, diagnostics, commands, whitelist) | protected | any admin |
| `/devices/:id/edit` | Edit device | protected | technical/super |
| `/subscriptions` | Subscription list | protected | any admin |
| `/orders` | Orders list | protected | any admin |
| `/orders/:id` | Order detail (+ recheck/refund) | protected | any admin (refund=super) |
| `/refunds` | Refunds list | protected | any admin |
| `/account` | Current admin profile + recovery codes | protected | any admin |
| `*` | 404 Not Found | — | — |

Role-gated UI: action buttons (enable/disable/transfer/decommission/refund) render only for `super_admin`;
create/edit/resync for `technical`+`super`. Routes for `/devices/new` & `/edit` redirect non-privileged users.

## 4. Screen map (screen → endpoints)

| Screen | Endpoints used |
|---|---|
| Login | `POST /auth/login` |
| 2FA | `POST /auth/2fa/verify` |
| Dashboard | `GET /devices?limit=5`, `GET /orders?limit=5`, `GET /refunds?limit=5`, `GET /subscriptions?status=active&limit=5` |
| Device list | `GET /devices` (status/owner_user_id/region_id/q/limit/cursor) |
| Device detail | `GET /devices/{id}`; actions: enable/disable/transfer/decommission |
| Device · diagnostics tab | `GET /devices/{id}/diagnostics` |
| Device · commands tab | `GET /devices/{id}/commands` |
| Device · whitelist tab | `GET /devices/{id}/whitelist-queue`, `POST /devices/{id}/whitelist/resync` |
| Create / Edit device | `POST /devices`, `PATCH /devices/{id}` |
| Subscription list | `GET /subscriptions` (status/tier/limit/cursor) |
| Orders list | `GET /orders` (status/limit/cursor) |
| Order detail | `GET /orders/{id}`; `POST /orders/{id}/recheck`, `POST /orders/{id}/refund` |
| Refunds list | `GET /refunds` |
| Account | `GET /auth/me`, `POST /auth/recovery-codes`, `POST /auth/logout` |

## 5. Component hierarchy

```
AppProviders (QueryClientProvider, AuthProvider, ToastProvider, Router)
└── RootRoutes
    ├── PublicRoute → LoginPage / TwoFactorPage
    └── ProtectedRoute (guards token; redirects to /login)
        └── AppLayout
            ├── Sidebar (nav items filtered by role)
            ├── Header (breadcrumbs, admin menu, logout)
            └── <Outlet/> (page)
                ├── DashboardPage → StatCard, RecentDevicesCard, RecentOrdersCard, RecentRefundsCard
                ├── DevicesPage → DataTable, DeviceFilters, StatusBadge, Pagination(cursor)
                ├── DeviceDetailPage → DeviceHeader, DeviceActions, Tabs
                │     ├── OverviewTab, RosterTab(DeviceUserRow)
                │     ├── DiagnosticsTab(DataTable), CommandsTab(DataTable), WhitelistTab(DataTable + ResyncButton)
                │     └── TransferDialog, DisableDialog, DecommissionDialog
                ├── DeviceFormPage (create/edit) → DeviceForm
                ├── SubscriptionsPage → DataTable, SubscriptionFilters, SubscriptionDrawer
                ├── OrdersPage → DataTable; OrderDetailPage → OrderSummary, RefundDialog, RecheckButton
                ├── RefundsPage → DataTable
                └── AccountPage → ProfileCard, RecoveryCodesDialog

ui/ (shadcn primitives): button, input, label, card, table, badge, dialog, dropdown-menu, select,
    tabs, separator, toast/toaster, skeleton, avatar, alert, sheet
components/ (shared): DataTable, CursorPagination, StatusBadge, PageHeader, EmptyState, ErrorState,
    LoadingState, ConfirmDialog, RoleGate, Money, RelativeTime
```

## 6. Folder structure

```
admin-ui/
├── index.html
├── package.json · tsconfig*.json · vite.config.ts · tailwind.config.js · postcss.config.js · .eslintrc.cjs
├── .env.example · .env.development
└── src/
    ├── main.tsx · App.tsx · index.css
    ├── lib/        (api client, query client, cn util, format, env)
    ├── types/      (api.ts — all response/request TS types from real resources)
    ├── auth/       (AuthProvider, useAuth, guards, token store)
    ├── api/        (resource hooks: useDevices, useDevice, useSubscriptions, useOrders, useRefunds, mutations)
    ├── components/ (shared + ui/)
    ├── layout/     (AppLayout, Sidebar, Header)
    └── pages/      (login, dashboard, devices, subscriptions, orders, refunds, account, NotFound)
```

## 7. API client & error handling

- Axios instance, `baseURL = import.meta.env.VITE_API_BASE_URL || ''` → requests use `/admin/v1/...`.
  - **Dev:** Vite proxy `/admin/v1` → `https://admin.salamheyetimiz.com` (avoids CORS).
  - **Prod:** served from `admin.salamheyetimiz.com` → same-origin.
- Request interceptor: inject `Authorization: Bearer <token>`, `Accept: application/json`, `Accept-Language: az`.
- Response interceptor: unwrap, and on error normalise the envelope to a typed `ApiError`:
  - `{ error: { code, message_key, message, details?, request_id } }` (general)
  - `{ error: { code:'validation_failed', fields: { [field]: string[] }, ... } }` (422)
  - 401 → clear session + redirect `/login`.
- TanStack Query for all reads (cursor pagination via `fetchNextPage` pattern) and mutations (with cache invalidation + toast).

## 8. Phasing (A–E)

- **Phase A — Foundation & Common UI:** scaffold, Tailwind/shadcn theme, api client, types, error/loading/empty states, layout (sidebar/header), router, protected routes, role gate, toasts.
- **Phase B — Authentication:** login, 2FA, logout, session hydration, lockout/error surfacing.
- **Phase C — Dashboard:** real recent devices/orders/refunds/active-subs; navigation cards.
- **Phase D — Device Management:** list/search/filters/detail/tabs (diagnostics, commands, whitelist), enable/disable/transfer/decommission, create/edit.
- **Phase E — Subscriptions & Orders/Refunds:** subscription list + status, orders list/detail/recheck/refund, refunds list.

**Out of scope (backend dependency):** User Management module, dashboard aggregate counters, subscription
detail endpoint, admin token refresh. These are documented, not stubbed.
