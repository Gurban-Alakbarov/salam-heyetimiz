# NAVIGATION

> Planning only. Router, structure, guards, deep links, back stack.

---

## 1. Router choice — `go_router`

**Decision: `go_router`** (declarative, URL-based, official). Reasons: typed/named routes, `redirect` for auth + gate guards driven by Riverpod state, `ShellRoute` for persistent bottom navigation, first-class deep links, and a single source of truth for the navigation graph. Alternative considered: `auto_route` (codegen, powerful nested routing) — heavier codegen; `Navigator 2.0` raw — too much boilerplate. `go_router` is the best fit.

The router is provided via Riverpod (`routerProvider`) so `redirect` can read auth/bootstrap state and `refreshListenable` re-evaluates guards when auth state changes.

---

## 2. Structure — ShellRoute bottom nav + stacked sub-routes

```
/                         → Splash (gate)
/onboarding               → Onboarding (first run)
/welcome                  → Welcome (guest)
/auth/register            → Register
/auth/login               → Login
/auth/verify              → Verify OTP            (carries email + purpose)
/maintenance /force-update /offline   → system screens (full-screen, no shell)

ShellRoute (AUTHENTICATED, persistent bottom nav: Home · Devices · Orders · Profile)
  /home                   → Home
  /devices                → My Devices
    /devices/:id          → Device Detail         (pushed, hides bottom nav optional)
      /devices/:id/open   → Barrier Open (modal/sheet or full)
      /devices/:id/stats  → Device Stats
      /devices/:id/history→ Open History
  /orders                 → Orders
    /orders/:id           → Order Detail
    /orders/:id/checkout  → Checkout (webview)
  /subscriptions          → Subscriptions
    /subscriptions/:id    → Subscription Detail
  /profile                → Profile (tab root)
    /profile/edit         → Edit Profile (flagged)
    /security             → Security
    /security/password    → Set/Change Password (future, hidden)
    /settings             → Settings
    /support              → Support
    /about                → About
    /notifications        → Inbox (future, flagged)
```

**Bottom navigation (4 tabs):** Home · Devices · Orders · Profile. Subscriptions reachable from Home/Profile (not a 5th tab to avoid clutter; revisit if usage warrants). Each tab keeps its own navigation stack (`StatefulShellRoute.indexedStack`) so switching tabs preserves scroll/stack.

**Drawer:** **No drawer.** Bottom nav + a Profile tab covering Settings/Security/Support/About is cleaner for a focused access-control app. (Revisit only if the menu grows.)

---

## 3. Guards (`redirect`)

A single `redirect` evaluates, in order:

1. **Gate guard (highest):** if `bootstrap.maintenance_mode` → `/maintenance`; if version `< min_version` or `force_update` → `/force-update`. These win over everything (even authenticated deep links).
2. **Connectivity:** network-required route + offline → `/offline` (or serve cached read screens — see `OFFLINE_STRATEGY.md`).
3. **Auth guard:**
   - Going to an **authenticated** route while GUEST → redirect to `/welcome` (remember intended deep link, resume after login).
   - Going to a **guest-only** route (login/register) while AUTHENTICATED → redirect to `/home`.
4. Else allow.

Auth state = a Riverpod `authStateProvider` (`unknown / guest / authenticated`). `refreshListenable` is wired to it so the router re-runs guards on login/logout/refresh-failure.

---

## 4. Protected vs guest routes

| Group | Routes | Rule |
|---|---|---|
| **Guest-only** | /welcome, /auth/* | redirect to /home if already authed |
| **Authenticated** | /home, /devices*, /orders*, /subscriptions*, /profile*, /security*, /settings, /notifications | redirect to /welcome if guest (save intended URL) |
| **Public (either)** | /support, /about, system screens | always allowed |

A 401 that survives a refresh attempt (refresh expired/revoked) flips `authStateProvider` → guest → the router bounces the user to `/welcome` with a "session expired" snackbar.

---

## 5. Deep links

- **Scheme + universal links:** `salam://…` custom scheme + `https://app.salamheyetimiz.com/…` app links (Phase 1 minimal; expand later).
- **v1 targets:** open a specific device (`/devices/:id`), an order (`/orders/:id`), the support page. Push notifications (future) carry a deep link payload routed through `go_router`.
- **Guarded deep links:** a deep link into an authenticated route while GUEST → store as the post-login destination, send through Welcome → Login → resume.
- **Payment return:** the BirPay checkout webview is handled **in-app** (not via external deep link) — the app watches the webview for the `payments/return` URL, closes the webview, and rechecks. (Avoids fragile external-redirect deep links.)

---

## 6. Back stack behavior

- **Tabs:** `StatefulShellRoute.indexedStack` — each tab has an independent stack; Android back pops within the active tab, then switches to Home, then exits (with a "press again to exit" on Home root).
- **Verify OTP:** back returns to Register/Login (not the app exit); the OTP screen is a child of the auth flow.
- **Auto-login (post-verify):** replaces the auth stack entirely (`go('/home')`, not `push`) so back doesn't return to OTP.
- **Force-Update / Maintenance:** terminal — back is disabled (`PopScope`), no escape.
- **Checkout webview:** back inside the webview navigates the page; a top-level close returns to Order Detail + triggers recheck.
- **Logout:** clears storage + `go('/welcome')`, wiping the authenticated stack.

---

## 7. Navigation principles

- All navigation goes through `go_router` (no raw `Navigator.push` for routed screens); modals/sheets use `showModalBottomSheet`/`showDialog` (not routes) for transient UI (e.g. the Open sheet, confirmations).
- Intended-destination preservation for deep links + post-login resume.
- One place (the router redirect) owns auth/gate transitions — features never imperatively redirect on auth failure; they emit state and the router reacts.
