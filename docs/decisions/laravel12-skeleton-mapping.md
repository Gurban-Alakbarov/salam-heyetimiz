# Decision: Laravel 12 skeleton mapping of BACKEND_ARCHITECTURE §3

**Status:** Accepted — 2026-06-13
**Context:** PROJECT_CONSTITUTION R-ARCH-01 mandates **Laravel 12**; R-ARCH-03 mandates the
folder structure in `BACKEND_ARCHITECTURE.md` §3 verbatim. §3 was written against the
Laravel-10-era skeleton and lists `app/Http/Kernel.php`, `app/Console/Kernel.php`, and the
classic `RouteServiceProvider` / `EventServiceProvider` / `BroadcastServiceProvider`.
Laravel 11/12 removed `Http/Kernel.php` and `Console/Kernel.php`; middleware and bootstrapping
moved to `bootstrap/app.php`, and console scheduling moved to `routes/console.php`.

**Decision (no architecture change — framework-version mapping only):**

1. Middleware groups/aliases documented in §6.5 are registered in `bootstrap/app.php`
   (`->withMiddleware()`) instead of `app/Http/Kernel.php`.
2. Console scheduling (Backend Arch §10) is wired in `routes/console.php`; per-module
   `ModuleServiceProvider::schedule(Schedule $schedule)` static hooks are aggregated there.
   (Named `schedule()` rather than `register()` to avoid colliding with the
   `ServiceProvider::register()` instance method — the §10 wording said `register(Schedule $s)`.)
3. `RouteServiceProvider`, `EventServiceProvider`, `BroadcastServiceProvider`, `AuthServiceProvider`,
   `AppServiceProvider`, plus the project-specific `DomainServiceProvider` and
   `IntegrationsServiceProvider` are kept as explicit providers (valid in Laravel 12) and
   listed in `bootstrap/providers.php`.
4. `RouteServiceProvider` retains `configureRateLimiting()` (Backend Arch §6.5 / §9.5).

**Everything else in §3 is honored verbatim:** the `app/Domain/<Module>/` layout and its internal
shape (§3.1), `app/Http/{Api,Admin,Webhooks}`, `app/Support`, `app/Exceptions`, `app/Broadcasting`,
`config/domain/*`, `config/integrations/*`, the `database/migrations/NN_*` batch folders (§11),
`lang/{az,ru,en}`, and the `routes/*` split.

**Consequence:** No domain or architectural decision is altered. Only the framework's bootstrap
location of middleware/scheduling differs, which is intrinsic to the mandated Laravel 12 version.
