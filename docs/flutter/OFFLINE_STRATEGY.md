# OFFLINE STRATEGY & LOCAL STORAGE

> Planning only. Covers what's stored where, what works offline, and cache/sync/conflict.

---

## 1. Storage tiers — what goes where

| Tier | Package | Stores | Why |
|---|---|---|---|
| **Secure (encrypted)** | `flutter_secure_storage` (Keychain/Keystore) | access token, refresh token, biometric-enabled flag, install_uuid | Sensitive credentials — never in plain prefs/DB |
| **Key-value (prefs)** | `shared_preferences` | theme mode, locale, onboarding-seen, last-known app version, notification prefs (local), feature-flag overrides (dev) | Small, simple, non-sensitive flags |
| **Structured cache (DB)** | `hive` / `hive_flutter` | cached `/me` snapshot, device list + details, subscriptions, orders list, last guest bootstrap, open-history, offline write queue | Fast typed object cache for offline reads + warm start |

**Rules:** tokens **only** in secure storage; never log them; clear secure + Hive on logout (keep theme/locale prefs). Hive boxes are versioned + lazily opened; a schema bump wipes the affected box (cache, not source of truth).

---

## 2. What works offline

| Screen / action | Offline behavior |
|---|---|
| Splash / launch | Use **cached guest bootstrap** to render gates; if min/maintenance unknown, allow in read-only with a banner; refresh when back online |
| Home, Devices list/detail, Subscriptions, Orders list/detail, Profile, Open history | **Read from Hive cache** (stale-while-revalidate) with an "offline / last updated …" banner |
| Settings, About, Support | Fully offline (local + cached bootstrap) |
| **Barrier Open** | **Blocked offline** — requires the server + device round-trip; show a clear "İnternet lazımdır" state (never queue an open) |
| **Payments / Checkout** | **Blocked offline** — financial + redirect-based; must be online |
| Register / Verify / Login | **Blocked offline** — OTP needs the server; clear offline prompt |
| Refresh / authed calls | If offline, serve cache; the AuthInterceptor doesn't try to refresh while offline |

Principle: **reads degrade gracefully (cache); state-changing/financial/real-time actions fail fast with a clear offline UX.** Safety > convenience for an access-control app.

---

## 3. Caching model — stale-while-revalidate

- Repository read = **emit cache immediately (if fresh-enough) → fetch network → update cache → emit fresh**. The UI shows cached data instantly with a subtle "updating…" indicator, then swaps to fresh.
- **TTL/staleness:** per resource — `/me` + devices ~ short (e.g. 2–5 min soft TTL, always revalidate on screen enter); orders/subscriptions ~ on-demand; guest bootstrap ~ 1h soft (revalidate on resume). Hard cache kept for offline display regardless of TTL.
- **Invalidation:** an action that changes server state (renew, auto-renew toggle, open feedback) invalidates the related cache + the `/me` snapshot so the next read refetches.
- **Pagination + cache:** the first page of each list is cached for offline display; deeper pages are network-only.

---

## 4. Offline write queue (minimal)

Only **safe, idempotent, non-financial, non-real-time** writes are queued (Hive box `outbox`): e.g. a future profile-field edit, a biometric-flag toggle, notification-pref change. Each queued op has an idempotency key + a created-at. On reconnect, a `SyncService` replays them in order, then refreshes affected caches.

**Never queued:** barrier open, order create/pay, OTP verify, login/register — these must be executed live against the server and are pointless/dangerous when stale.

---

## 5. Conflict resolution

- **Prefs / local-only state** (theme, locale, notification prefs): last-write-wins, local is authoritative.
- **Queued writes vs server:** the server is authoritative. On replay, if the server rejects (409/422/stale), the op is dropped and the user is notified; the local optimistic change is rolled back to the server truth (refetch). No silent merge.
- **Read cache vs server:** server always wins on revalidate; cache is display-only.
- Because the only queued writes are low-stakes prefs/flags, conflict surface is intentionally tiny in v1.

---

## 6. Sync triggers

- App resume (`AppLifecycleState.resumed`) → revalidate bootstrap + `/me` + drain outbox.
- Connectivity regained (`connectivity_plus` stream) → drain outbox + revalidate visible screen.
- Pull-to-refresh → force network for the current list/detail.
- Post-action → targeted cache invalidation + refetch.

---

## 7. Cold vs warm start

- **Cold start:** show Splash → render from cached bootstrap + cached `/me` if a session exists (instant perceived load) → revalidate in the background → reconcile UI.
- **Warm start (resume):** revalidate gates (maintenance/force-update) + `/me`; if a force-update/maintenance flips on while the app was backgrounded, route to the terminal screen.
