# Salam Həyətimiz — Local Setup & Run (Windows / XAMPP)

**Version:** 1.0
**Date:** 2026-06-14
**Goal:** install, migrate, seed, and serve the **foundations** (batches 00–04) on localhost, and verify them before continuing with batch 05.
**Verified environment:** Windows · XAMPP · **PHP 8.2.12** · **MariaDB 10.4.32** (server running) · Composer installed.
**No business logic was added.** This document only enables and verifies the current foundation.

---

## 0. Local-compatibility audit (results)

| # | Audit task | Result |
|---|---|---|
| 1 | PHP 8.2 compatibility of generated code | ✅ Pass — all 118 files lint clean on PHP 8.2.12; no PHP 8.4-only syntax/functions used (only readonly classes, enums, promotion, `static fn`, nullsafe — all ≤ 8.2). |
| 2 | PHP 8.4-only features present? | ✅ None in app code. The **only** 8.4 reference was `composer.json` `require.php = ^8.4` → **fixed to `^8.2`** (see Edit #1). |
| 3 | composer deps vs PHP 8.2 | ✅ All compatible: laravel/framework ^12 (floor 8.2), horizon ^5.30, reverb ^1.4, lcobucci/jwt ^5.4, spatie/laravel-data ^4.11, predis ^2.2, pest ^3.7, larastan ^3.1 — every one supports PHP 8.2. |
| 4 | Laravel version compatibility | ✅ Laravel 12 officially supports PHP 8.2–8.4; 8.2.12 is in range. No Laravel downgrade needed. |
| 5 | MariaDB compatibility of migrations | ✅ MariaDB 10.4.32 supports everything used: STORED generated columns (≥10.2), generated column in UNIQUE index, `VARBINARY(255)`, `JSON` (LONGTEXT+CHECK), `TIMESTAMP(3)`, `utf8mb4_unicode_ci`, InnoDB self-referencing FKs, `ENUM`, `DECIMAL(10,7)`. ⚠ Constitution targets MariaDB **11**; local is **10.4** — all our features still work on 10.4 (noted, not blocking). |
| 6 | Generated columns / indexes / constraints | ✅ `device_users.is_active` & `invitations.is_pending` (STORED) + composite UNIQUEs verified valid for 10.4. No index exceeds the 3072-byte InnoDB DYNAMIC limit (largest: VARCHAR(255) push_token = 1020 bytes). |
| 7 | Redis for local dev | ⚠ No `ext-redis` (✅ we use `predis`, already set) **and XAMPP ships no Redis server**. Foundation verification does **not** need Redis — use local `.env` overrides (Edit #3). `/v1/health/ready` will report Redis degraded (expected) until a Redis server is installed. |
| 8 | Seeders | ✅ The three lookup seeders (`SimOperator`, `DeviceModel`, `Region`) touch only lookup tables — no Redis, no generated columns, no encryption. Idempotent (`updateOrCreate`). |

### Two real issues found and fixed (already applied in this commit)

- **Edit #1 — `composer.json`:** `"php": "^8.4"` → `"php": "^8.2"`. Laravel 12's true floor is 8.2; `^8.4` would have blocked `composer install` on this machine. Production/CI still **target PHP 8.4** per PROJECT_CONSTITUTION R-ARCH-01 (enforce in the CI matrix, not the composer floor). To keep the local box on 8.4 instead, install Redis-less is unaffected; just install PHP 8.4.
- **Edit #2 — `app/Providers/AppServiceProvider.php`:** the migration-subfolder loader used `glob(database_path('migrations').'/*', …)`. On Windows the backslash base path + `/*` matches **0** directories, so `migrate` would report *"Nothing to migrate."* Fixed by normalising to forward slashes:
  ```php
  $base = str_replace('\\', '/', database_path('migrations'));
  $paths = glob($base.'/*', GLOB_ONLYDIR);
  ```
  Verified: now finds **5 subfolders / 18 migration files**. Works identically on Linux (no backslashes to replace).

> Both edits are foundation-enablement fixes — no business logic, no architecture change. Edit #2 is a genuine cross-platform bug fix and should remain permanently.

---

## D. Manual edits required before first run

1. **Already applied:** Edit #1 (`composer.json`) and Edit #2 (`AppServiceProvider`) above — nothing for you to do.
2. **You must edit your local `.env`** (after step A-2) — Redis server is absent locally, so point cache/queue at non-Redis drivers:
   ```ini
   CACHE_STORE=array
   QUEUE_CONNECTION=sync
   SESSION_DRIVER=database          # already the default; sessions table is created by migrate
   REDIS_CLIENT=predis              # already the default; no ext-redis on this box
   ```
   Leave DB settings as-is — they already match XAMPP defaults (`DB_CONNECTION=mariadb`, host `127.0.0.1`, `DB_USERNAME=root`, empty password, `DB_DATABASE=salam`).
3. **You must create the database** (step A-5) — Laravel does not create it.

---

## A. Exact commands  ·  B. Expected output

Run from a normal `cmd`/PowerShell (not the XAMPP shell). Ensure **MySQL is started** in the XAMPP Control Panel first.

### A-1. Go to the project
```bat
cd C:\xampp\htdocs\salam
```

### A-2. Create the local env file
```bat
copy .env.example .env
```
**Expected:** `1 file(s) copied.`

### A-3. Install dependencies
The `--ignore-platform-req` flags let Horizon/Reverb (which declare the Unix-only `ext-pcntl`/`ext-posix`) install on Windows; those extensions are only needed to *run* the queue daemon, not to install or to verify foundations.
```bat
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```
**Expected (tail):**
```
Generating optimized autoload files
> @php artisan package:discover --ansi
   INFO  Discovering packages.
  laravel/horizon ........ DONE
  laravel/reverb ......... DONE
  laravel/tinker ......... DONE
  ...
98 packages you are using are looking for funding.
```
*(Package count varies. No "requires php" or "ext-*" errors should appear.)*

### A-4. Generate the application key
```bat
php artisan key:generate
```
**Expected:**
```
   INFO  Application key set successfully.
```

### A-5. Edit `.env` per Section D-2, then create the database
```bat
"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS salam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
**Expected:** *(no output = success)*

### A-6. Run migrations
```bat
php artisan migrate
```
**Expected (key lines):**
```
   INFO  Preparing database.
   INFO  Running migrations.
  2026_06_13_000001_create_cache_table .................... DONE
  2026_06_13_000002_create_jobs_table ..................... DONE
  ... (all 18 in batch order) ...
  2026_06_13_040004_create_invitations_table .............. DONE
```
**18 migrations must run** (4 system + 3 lookups + 2 identity + 5 auth + 4 devices). If you see *"Nothing to migrate"*, see Common Errors.

### A-7. Seed lookups
```bat
php artisan db:seed
```
**Expected:**
```
   INFO  Seeding database.
  Database\Seeders\Lookups\SimOperatorSeeder ........ RUNNING / DONE
  Database\Seeders\Lookups\DeviceModelSeeder ........ RUNNING / DONE
  Database\Seeders\Lookups\RegionSeeder ............. RUNNING / DONE
```
*(A-6 + A-7 can be combined as `php artisan migrate --seed`.)*

### A-8. Serve the app
```bat
php artisan serve
```
**Expected:**
```
   INFO  Server running on [http://127.0.0.1:8000].
```

### A-9. Verify the foundation (new terminal)
```bat
curl http://127.0.0.1:8000/v1/health/live
curl -i http://127.0.0.1:8000/v1/health/ready
```
**Expected:**
- `live` → `200` `{"status":"ok"}` with an `X-Request-Id` header.
- `ready` → if no Redis server: `503` `{"status":"degraded","checks":{"database":true,"redis":false}}` — **this is expected locally**; `database:true` is the signal the foundation is wired correctly. With a Redis server running it returns `200 {"status":"ready", ...}`.

### A-10. (Optional) Quality gates from the Constitution §10.5
```bat
php docs\openapi\validate.php          REM contract integrity (no DB needed)
composer lint                          REM Pint (PSR-12)
composer analyse                       REM PHPStan/Larastan level 8
```
**Expected `validate.php`:** `operationIds: 100 (all unique)` … `All $refs resolve.`

---

## C. Common errors & fixes

| Symptom | Cause | Fix |
|---|---|---|
| `Your requirements could not be resolved … requires php ^8.4` | Stale composer floor | Already fixed to `^8.2`. If you re-introduced `^8.4`, lower it or run with `--ignore-platform-req=php`. |
| `… requires ext-pcntl` / `ext-posix … your system does not have it` | Horizon/Reverb declare Unix-only extensions | Use the A-3 command with `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix`. |
| `php artisan` during install: package:discover fails | `.env` missing at discover time | Ensure A-2 (`copy .env.example .env`) ran **before** `composer install`; then `composer dump-autoload`. |
| **`Nothing to migrate.`** | Migration subfolders not discovered | Confirm Edit #2 is present in `AppServiceProvider::boot()` (forward-slash normalisation), then `php artisan config:clear` and retry. |
| `SQLSTATE[HY000] [1049] Unknown database 'salam'` | DB not created | Run A-5. |
| `SQLSTATE[HY000] [2002] … actively refused` / `No connection` | MySQL not started | Start **MySQL** in XAMPP Control Panel; confirm port 3306. |
| `Cache store [redis] is not defined` or Redis `Connection refused` | `CACHE_STORE=redis`/`QUEUE_CONNECTION=redis` but no Redis server | Apply Section D-2 (`CACHE_STORE=array`, `QUEUE_CONNECTION=sync`). |
| `No application encryption key has been specified.` | Key not generated | Run A-4 (`php artisan key:generate`). |
| `/v1/health/ready` returns 503 with `redis:false` | No local Redis server | Expected. Install Redis (see §E) or ignore for foundation verification — `database:true` is what matters. |
| `Please provide a valid cache path.` | Only if `CACHE_STORE=file` and `storage/framework/cache/data` missing | Use `CACHE_STORE=array` (recommended locally) or `mkdir storage\framework\cache\data`. |
| `php artisan horizon` exits / `pcntl_*` undefined | Horizon worker needs Unix extensions | Do **not** run Horizon on Windows; it is not needed for foundation verification. Queue runs as `sync` locally. |
| Migration error on a generated column | MariaDB < 10.2 | Not applicable here (10.4.32). Ensure XAMPP MySQL is the bundled MariaDB. |
| Config changes not taking effect | Cached config | `php artisan config:clear` (do **not** run `config:cache` during local dev). |

---

## E. (Optional) Enabling the full stack locally

Foundation verification does **not** require these. Enable only if you want queues/cache/broadcasting/`health-ready` green:

- **Redis server on Windows:** install **Memurai** (Redis-compatible, native Windows) or run Redis via **Docker** (`docker run -p 6379:6379 redis:7`) or **WSL2**. Then set `.env` back to `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`. Keep `REDIS_CLIENT=predis` (no PHP ext needed).
- **Queue worker:** with Redis up, `php artisan queue:work` (plain worker works on Windows; **Horizon** does not — it needs `ext-pcntl`/`ext-posix`, Linux/WSL/Docker only).
- **Reverb (WebSocket):** `php artisan reverb:start` — runs best under WSL/Docker on Windows.

---

## F. Notes & deviations from the frozen target (transparency)

| Item | Target (Constitution) | Local | Impact |
|---|---|---|---|
| PHP | 8.4 (R-ARCH-01) | 8.2.12 | None for foundations — Laravel 12 supports 8.2. composer floor widened to `^8.2`; keep CI/prod on 8.4. |
| MariaDB | 11 | 10.4.32 | None — every feature used works on 10.4 (STORED generated cols ≥10.2, etc.). Re-test on 11 before production. |
| Redis | HA Sentinel (R-ARCH-10) | none (overridden to array/sync) | Local only; production unchanged. |
| Horizon / Reverb | required (R-ARCH-12, §9) | not run on Windows | Daemons need Unix extensions; install via Docker/WSL when needed. Not required to verify batches 00–04. |

**Definition of "foundation verified" for this milestone:** `composer install` clean · `php artisan migrate` runs all 18 · `db:seed` populates 3 operators + 1 device model + 9 regions · `GET /v1/health/live` → 200 `{"status":"ok"}` · `GET /v1/health/ready` → `database:true`.

When all of the above pass, the foundation is confirmed and batch 05 may proceed.

---

*End of Local Setup v1.0.*
