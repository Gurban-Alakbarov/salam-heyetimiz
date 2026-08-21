# Salam Həyətimiz — Backend

Remote GSM access-control management platform. Backend: **Laravel 12 / PHP 8.4 / MariaDB 11 / Redis 7 / Horizon / Reverb**.

> The single source of truth for design and implementation is the frozen v1.1 document set in
> [`docs/`](docs/), governed by [`docs/PROJECT_CONSTITUTION.md`](docs/PROJECT_CONSTITUTION.md).
> Code conforms to the docs; do not redesign architecture without a signed doc revision.

## Status

Implementation in progress — **Phase 1A foundations**. This commit delivers:

- Laravel 12 project skeleton (`bootstrap/`, `public/`, `config/`, `routes/`).
- Domain module structure: `app/Domain/<Module>/` (13 bounded contexts) with `ModuleServiceProvider`s
  auto-discovered by `App\Providers\DomainServiceProvider`.
- Base architecture: Support layer (`Money`, `PhoneNumber`, `Clock`, `LocaleResolver`, `Redactor`,
  `Cursor`), exception envelope (`DomainException` + `ApiExceptionRenderer`), foundational middleware.
- **Migration batches 00–04** (DB Arch §11): system, identity lookups, users/admins, auth, devices.
- Lookup seeders and `lang/{az,ru,en}` baselines (`errors.*`, `validation.*`, `common.*`).

Migration batches 05–11 (payments, subscriptions, device-ops, notifications, audit/ops, stats,
post-seed constraints) are **not** in this commit — awaiting review per the agreed plan.

## Requirements

- PHP **8.4+** (the project targets 8.4; local XAMPP here is 8.2 — install 8.4 to run).
- Composer 2, MariaDB 11, Redis 7.
- No `ext-redis` required locally: `REDIS_CLIENT=predis` (see `.env.example`).

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Create the database (utf8mb4_unicode_ci), then:
php artisan migrate --seed
```

Migrations live in batch subfolders under `database/migrations/NN_*`; they are registered
recursively by `App\Providers\AppServiceProvider` (the default flat `database/migrations` glob
does not recurse).

## Quality gates (CI — PROJECT_CONSTITUTION §10.5)

```bash
composer analyse      # PHPStan / Larastan level 8 (R-CODE-01)
composer lint         # Laravel Pint (PSR-12)
composer test         # Pest; Feature/Integration run against MariaDB (R-CODE-09)
php docs/openapi/validate.php   # OpenAPI contract integrity (R-API-01)
```

## Locales

`az` is the default and source of truth; `ru` and `en` are secondary (R-LOC-01).
The API returns `code` + `message_key` (+ `details`), never a translated `message` as the contract
(R-LOC-02).
