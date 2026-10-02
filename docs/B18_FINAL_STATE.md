# B18 — Final State, Production Readiness & Release Preparation

> Tarix: 2026-10-02 · Status: **B1–B18 lokal working tree-də TAM**, commit/push/deploy edilməyib (ayrıca GO gözlənilir).
> Mənbə planı: [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) · API: [openapi/openapi.json](openapi/openapi.json) (v1.3.0, 188 path / 211 əməliyyat).

---

## 1. Batch-lərin vəziyyəti

| Batch | Mövzu | Vəziyyət |
|---|---|---|
| B1 | Fake payment gateway + feature flag-lar (BR-9/16) | ✅ |
| B2 | Subscription reopen/cancel, 12 AZN / 30 gün (BR-13/17/21) | ✅ |
| B3 | Invitation ümumiləşdirilməsi (kind, device_id, token hash) | ✅ |
| B4 | Complex membership + complex device mode | ✅ |
| B5 | Komendant identity + Komendant API (BR-11/15/22) | ✅ |
| B6 | Invitation acceptance, web landing, `.well-known` | ✅ |
| B7 | Resident complex → subscribe → payer qaydası; whitelist abunəliyə bağlı | ✅ |
| B8 | Family links + family invitation + payment (BR-1..4/19) | ✅ |
| B9 | Fiziki / Hüquqi müraciətlər (BR-5..7) | ✅ |
| B10 | Notification template editor (backend) | ✅ |
| B11 | Admin panel: cihaz/kompleks/Komendant/müraciət/istifadəçi/dəvət | ✅ |
| B12 | Admin panel: bildiriş şablonları UI | ✅ |
| B13 | Mobile foundation: deep links, rollar, payments (checkout WebView) | ✅ (iOS Associated Domains — TestFlight GO-da) |
| B14 | Mobile qeydiyyat (növ + OSM picker + müraciətlər) | ✅ |
| B15 | Mobile Komendant UI | ✅ |
| B16 | Mobile sakin: invite → kompleks → cihaz → abunə → aktiv | ✅ |
| B17 | Mobile ailə üzvləri + Cihazlar redesign | ✅ |
| B18 | Final audit, hardening, docs, release hazırlığı | ✅ (bu sənəd) |

Testlər (2026-10-02): backend **663/663** (3158 assertion) · mobile **253/253** · `flutter analyze` təmiz · admin-ui lint + build təmiz.

## 2. İstifadə olunan endpoint-lər (qruplar)

Tam siyahı və sxemlər `docs/openapi/openapi.json`-dadır (B18-də additiv sinxron olunub).

| Qrup | Endpoint-lər |
|---|---|
| Auth (email-OTP) | `POST /v1/auth/register`, `/verify-email`, `/resend-otp`, `/login`, `/refresh`, `/logout`; `GET /v1/me`, `/v1/bootstrap` |
| Müraciətlər | `POST /v1/applications/individual|legal`, `PUT …/{id}`, `GET /v1/applications/mine` |
| Cihazlar | `GET /v1/devices`, `POST /v1/devices/{id}/open`, `GET /v1/commands/{id}` |
| Kompleks (sakin) | `GET /v1/complexes`, `/v1/complexes/{id}`, `/v1/complexes/{id}/devices`, `POST …/devices/{deviceId}/subscribe` |
| Dəvət (deep link) | `GET /v1/invites/{token}`, `POST /v1/invites/{token}/accept|decline`; web: `GET /invite/{token}`; `GET /.well-known/assetlinks.json`, `/.well-known/apple-app-site-association` |
| Komendant | `GET /v1/komendant/complex|devices|residents|invitations`, `POST /v1/komendant/invitations`, `…/{id}/resend|revoke`, `DELETE /v1/komendant/residents/{userId}` |
| Ailə | `GET|POST /v1/devices/{id}/invitations`, `GET|POST /v1/family/members`, `DELETE /v1/family/members/{userId}`, `GET /v1/family/subscriptions`, `POST /v1/family/invitations/{id}/resend|revoke` |
| Ödəniş | `POST /v1/orders`, `GET /v1/orders/{id}`, `GET /v1/subscriptions?status=pending_payment`, `POST /v1/subscriptions/{id}/renew`; fake: `GET /v1/payments/fake-checkout/{ref}` (signed), `POST …/{ref}/{action}`; `GET /v1/payments/return` |
| Qonaq linkləri | `GET|POST /v1/devices/{id}/visitor-links`, `GET /v1/visitor-links`, `POST /v1/visitor-links/{id}/revoke`; public `GET /v/{token}`, `GET /v1/visit/{token}`, `POST /v1/visit/{token}/open` |
| Admin (yeni) | `/admin/v1/applications/*`, `/admin/v1/complexes/{id}/devices/{deviceId}`, `/admin/v1/complexes/{id}/members`, `/admin/v1/devices/{id}/ownership-mode`, `/admin/v1/admins/{id}/mobile-user`, `/admin/v1/invitations`, `/admin/v1/notification-templates*` |

## 3. Qüvvədə olan biznes qaydaları

Plan §2-dəki **BR-1 … BR-22** dəyişməz qüvvədədir. Əsasları:
- **Üç qatlı ailə modeli:** `family_links` (əlaqə) · `device_users.family_link_id` (giriş) · üzvün öz `additional` abunəliyi (ödəniş). Ailə üzvü kompleks üzvü olmur; Komendant ailə üzvü yaratmır.
- **Qiymət:** hər abunəlik 12 AZN / 30 gün (main və additional eyni, BR-21).
- **Payer qaydası:** main — beneficiary (və private cihaz sahibi); additional — üzvün özü və ya **aktiv** `family_links` ilə başçı; başqası 403.
- **Complex cihazda GSM whitelist abunəliyə bağlıdır:** aktiv abunəlik → `add`, cancelled/expired/refunded → `remove`; roster sətri tək başına zəng-ilə-açma vermir.
- **Çıxarma (BR-18):** abunəlik `cancelled`, refund yoxdur (yalnız admin ayrıca), audit, period/order-lər silinmir.
- **Ailə üzvü qonaq linki yarada bilməz (BR-19)** — server 403.
- **Dəvət linki** 7 gün, bir dəfəlik, DB-də yalnız hash; ölü token (expired/revoked/used/rotated) vahid **410 `invitation_invalid`**; yanlış email **403 `invitation_email_mismatch`**.

## 4. Ödəniş hazırda necə işləyir

```
Mobil "Abunə ol" / "Ödə" ──► POST /v1/orders (və ya …/subscribe)   order: pending → authorising
          │                                   bank_redirect_url = signed fake-checkout link (TTL 30 dəq)
          ▼
WebView: GET /v1/payments/fake-checkout/{ref}  ── "TEST ÖDƏNİŞ" səhifəsi (Ödə / Rədd et / Ləğv et / Gözlət)
          │  POST …/{ref}/{action}
          ▼
PaymentCallbackService::receive (BirPay webhook ilə EYNİ pipeline) → getOrderStatus re-check (R-PAY-04)
          ▼
order: paid | failed | cancelled   →  subscription: active (period yaranır) | pending_payment qalır
          ▼
active → ComplexWhitelistReconciler → whitelist_changes `add` → WhitelistSyncJob → cihaz;  can_open=true
          ▼
/v1/payments/return → mobil nəticə ekranı (server polling) → Cihazlar / Ana səhifə / Ailə siyahıları yenilənir
```

### 4.1 Fake payment production-da QƏSDƏN aktivdir

Real payment API hələ inteqrasiya olunmayıb. Cihazlar bizə məxsusdur və tətbiq store-da deyil; 3–4 əməkdaş real cihazlarda production tətbiqini test edir. Ona görə fake checkout **production-da işləməlidir** (development-only deyil).

| Env | Dəyər | Qeyd |
|---|---|---|
| `PAYMENT_GATEWAY` | `fake` | |
| `PAYMENT_FAKE_ENABLED` | `true` | |
| `PAYMENT_ALLOW_FAKE_IN_PRODUCTION` | `true` | production üçün ikinci açar |

Qoruyucular (kodda, test ilə sübut):
- İki açardan biri yoxdursa → `UnavailablePaymentGateway` (503). **Heç vaxt real banka düşmür və səssizcə simulyasiya etmir.** B18-də `APP_ENV=production` ilə yoxlanıb: deny → `disabled`, allow → `FakeKapitalGateway`.
- Fake order-lər `KB-FAKE-` bank id prefiksi ilə tanınır → `is_test=true` → mobil checkout/nəticə, web checkout/return və admin order ekranlarında "TEST ÖDƏNİŞ".
- Heç bir şəbəkə sorğusu, kart məlumatı və pul hərəkəti yoxdur; hər fake action audit log-a yazılır (`payment.fake_checkout_action`).
- Fake checkout linkləri imzalıdır (`signed:relative`), 30 dəqiqə etibarlıdır; fake gateway söndürüləndə route 404 verir.

### 4.2 Real payment API gələndə nə dəyişir

| Hissə | Dəyişiklik |
|---|---|
| Env | `PAYMENT_GATEWAY=birpay` + `KAPITAL_*` (base URL, merchant, HMAC secret, IP allowlist); fake flag-lar `false` |
| Backend adapter | **BirPay V1.3 adapter-i yenidən yazılmalıdır** (OAuth2/Keycloak, `docs/integrations/kapital/` — mövcud wire uyğun deyil; ayrıca layihə) |
| Biznes logic / order / subscription / whitelist | **Dəyişmir** (eyni callback → getOrderStatus pipeline) |
| Mobil | Dəyişmir — WebView `bank_redirect_url`-u açır; `is_test=false` olanda TEST banneri avtomatik itir |
| Admin | Dəyişmir — TEST badge bank id prefiksindən hesablanır |
| Mövcud fake order-lər | Tarixçə kimi qalır (`is_test=true`) |

## 5. Production deployment — konfiqurasiya və runbook (qərarlar 2026-10-02)

### 5.1 Prod `.env` dəyişiklikləri (yalnız bunlar; secret-lərə toxunulmur)
```
PAYMENT_GATEWAY=fake                       # yeni (hazırda yoxdur → default birpay)
PAYMENT_FAKE_ENABLED=true                  # yeni
PAYMENT_ALLOW_FAKE_IN_PRODUCTION=true      # yeni
```
Dəyişməyənlər (qəsdən): `APP_URL=https://salamheyetimiz.com` (invite link-i `https://salamheyetimiz.com/invite/{token}` olur — `INVITATION_LINK_BASE` lazım deyil); `APP_LINKS_ANDROID_SHA256` və `APP_LINKS_IOS_APP_ID` **boş qalır** (release SHA-256 / iOS bu mərhələdə toxunulmur) → `/.well-known/*` 404. Android-da invite linki brauzerdə web landing-i açır, "Tətbiqdə aç" düyməsi explicit-paket `intent://` ilə tətbiqi açır (verify tələb etmir). `APP_STORE_URL_*` boş.

### 5.2 Pre-flight (2026-10-02, read-only yoxlanıb)
| Yoxlama | Nəticə |
|---|---|
| Prod HEAD | `0bcda54`, working tree təmiz; remote `github.com/Gurban-Alakbarov/salam-heyetimiz` |
| `invitations` sətir sayı | **0** (B3 migration-ı yalnız boş cədvəldə işləyir — şərt ödənir) |
| Yeni migration-lardan artıq işləyən | 0 / 11; `family_links`, `complex_members` yoxdur |
| Data snapshot | devices 6 · device_users active 33 · subscriptions active 33 · subscription_periods 0 · orders 5 · complexes 6 · admin_users 12 · visitor_links 188 |
| Migration dry-run | prod **sxem** kopyası (data yox) üzərində 11/11 DONE, `rollback --step=11` 11/11 DONE |
| Seeder dry-run | 3 seeder prod sxemində işləyir; demo admin/kompleks YARATMIR |
| Seeder-in mövcud dataya təsiri | şablonlar: prod-dakı 24 locale sətri seed ilə hash-eyni (mətn, aktivlik, kanal, kateqoriya) → **0 dəyişiklik**; permissions: 51 mövcud hash-eyni, 4 yeni; rol qrantları: 0 silinir, 5 əlavə |
| Rol qrantları | prod 66 qrant ⊂ matris 71 → **heç nə silinmir**, 5 əlavə (operator/support: `applications.*`, `notifications.templates.view`); permissions 51 → 55 |
| Composer | `composer.json/lock` dəyişməyib → vendor yeniləməsi lazım deyil (serverdə composer/node yoxdur) |
| Horizon | queue-lar `default, device-comm, notifications, payments` supervisor-larda var |
| Scheduler | `salam-scheduler.timer` (hər dəqiqə) aktivdir |
| Disk | `/var` 2.6G boş; DB 171 MB |
| **Gecə backup-ı** | `salam-backup.service` **hər gün FAILED** (`/root/salam_secrets.env` yoxdur, `/var/backups/salam` heç yaranmayıb) → deploy backup-ı əl ilə alınır (§7.2) |

### 5.3 Migration siyahısı (11, hamısı additiv)
| # | Migration | Nə edir |
|---|---|---|
| 1 | `2026_10_01_020010_add_account_type_to_users` | `users.account_type` (nullable enum) |
| 2 | `2026_10_01_040030_add_ownership_and_sale_price_to_devices` | `devices.ownership_mode` (default `private`), satış qeydi sütunları |
| 3 | `2026_10_01_040040_generalize_invitations_table` | invitations ümumiləşdirmə (yalnız boş cədvəldə) |
| 4 | `2026_10_01_040050_add_family_link_id_to_device_users` | `device_users.family_link_id` (nullable) |
| 5 | `2026_10_01_040060_create_family_links_table` | yeni cədvəl |
| 6 | `2026_10_01_060010_add_paid_by_to_subscription_periods` | `paid_by_user_id` + backfill (prod-da 0 period) |
| 7 | `2026_10_01_080010_create_complex_members_table` | yeni cədvəl |
| 8 | `2026_10_01_080020_add_location_to_complexes` | lat/lng + legal application ref (nullable) |
| 9 | `2026_10_01_080030_add_user_id_to_admin_users` | Komendant ↔ mobil user (nullable, unique) |
| 10 | `2026_10_01_080040_create_individual_applications_table` | yeni cədvəl |
| 11 | `2026_10_01_080050_create_legal_entity_applications_table` | yeni cədvəl |

### 5.4 Seeder-lər (YALNIZ bu üçü; tam `db:seed` QADAĞANDIR — `AdminUserSeeder` demo hesabların parollarını sıfırlayır, `DemoDataSeeder` demo data yaradır)
```
php artisan db:seed --force --class='Database\Seeders\Rbac\PermissionSeeder'
php artisan db:seed --force --class='Database\Seeders\Rbac\RolePermissionSeeder'
php artisan db:seed --force --class='Database\Seeders\Notifications\NotificationTemplatesSeeder'
```

### 5.5 Deploy ardıcıllığı (yalnız ayrıca GO ilə; hamısı `sudo`, app əmrləri `sudo -u www-data`)
1. **Commit + push** (lokal → GitHub `main`).
2. **DB backup (əl ilə):** `mkdir -p /var/backups/salam-predeploy && mariadb-dump --single-transaction --routines --events salam | gzip > /var/backups/salam-predeploy/salam_$(date +%F_%H%M)_pre-b18.sql.gz`; ölçü + `gzip -t` yoxla. Kod backup-ı: `git rev-parse HEAD` qeyd et (`0bcda54`) + `.env` nüsxəsi (`cp -a .env .env.pre-b18`).
3. Pre-flight snapshot: `php artisan subscriptions:legacy-report --list > /var/backups/salam-predeploy/legacy_pre.txt` + §5.2 sayları.
4. `php artisan down --retry=60` (qısa maintenance; Horizon `horizon:pause`).
5. `git pull --ff-only origin main`.
6. `php artisan migrate --force` (11 migration, §5.3).
7. 3 seeder (§5.4).
8. `.env`-ə §5.1 flag-ları.
9. `php artisan optimize:clear && php artisan optimize` → `systemctl reload php-fpm` → `php artisan horizon:terminate` (systemd yenidən qaldırır; `systemctl status salam-horizon`).
10. `php artisan up`.
11. **nginx redaction** (§6): `bash deploy/phaseK_log_redaction.sh` (köhnə loglara toxunmur).
12. **admin-ui:** lokal `npm run build` → `dist` tar → scp → serverdə `dist` backup (`dist.bak.<ts>`) → yeni `dist` → `chown -R www-data`.
13. **Post-flight + smoke** (§8).
**Rollback (3 səviyyə):**
1. **Kod rollback (əsas yol, sxem qalır):** `php artisan down` → `git checkout 0bcda54` → `cp -a .env.pre-b18 .env` → `optimize:clear && optimize` → php-fpm reload → `horizon:terminate` → `up`. 11 migration additivdir (yeni cədvəllər + nullable/default-lu sütunlar; `invitations` köhnə kodda istifadə olunmur), ona görə köhnə kod yeni sxemlə işləyir; B18 dövründə yaranan data (ailə, kompleks üzvlüyü, dəvət, order) itmir.
2. **Sxem rollback:** `migrate:rollback --step=11 --force` — prod sxem kopyasında 11/11 sınanıb, **amma** `generalize_invitations_table` `down()` cədvəl boş deyilsə imtina edir (qəsdən) və yeni cədvəllərin datası silinir → yalnız deploy-dan dərhal sonra, heç bir dəvət yaranmamışsa.
3. **Tam bərpa:** `php artisan down` → backup-dan `gunzip < …pre-b18.sql.gz | mariadb salam` → 1-ci səviyyə.
nginx rollback: `cp -a /etc/nginx/conf.d/salam.conf.bak-<stamp> /etc/nginx/conf.d/salam.conf && rm -f /etc/nginx/conf.d/00-salam-log-redact.conf && nginx -t && systemctl reload nginx`. admin-ui rollback: `dist.bak.<ts>` → `dist`.

## 6. nginx — nə dəyişir, nə dəyişmir

**Dəyişir (yalnız yeni request-lər üçün):**
- Yeni fayl `/etc/nginx/conf.d/00-salam-log-redact.conf`: iki `map` (`$request`, `$http_referer`) + `log_format main_redacted` (sahələr `main` ilə eyni: IP, vaxt, request, status, bytes, referer, user-agent).
- `conf.d/salam.conf`-da 4 server blokunun hər birinə `server_name`-dən sonra bir sətir: `access_log /var/log/nginx/access.log main_redacted;`.
- Log faylı eyni (`/var/log/nginx/access.log`); bu yollarda token seqmenti `<redacted>` olur: `/invite/{t}`, `/v1/invites/{t}[/accept|/decline]`, `/v/{t}`, `/v1/visit/{t}[/open|/command/{id}]`, fake-checkout query (`?expires=&signature=` → `?<redacted>`); eyni qayda Referer-də.

**Dəyişmir:** `nginx.conf`, `log_format main`, error log, logrotate (daily, 10), location-level `access_log off`, proxy/fastcgi/SSL, app, `.env`, **köhnə loglar (qəsdən olduğu kimi saxlanılır)**.

Təhlükəsizlik: `salam.conf` backup-ı; `nginx -t` uğursuzdursa avtomatik rollback; sonda bir sintetik `GET /v1/invites/<probe>` ilə yoxlama (probe log-da görünməməli, `<redacted>` görünməli). Sintaksis prod konfiqurasiyasının kopyasında `nginx -t` ilə yoxlanıb. Qərar tələb edən əlavə hissə yoxdur.

## 7. Fake payment — production davranışı (TƏSDİQLƏNİB 2026-10-02)

- **Qlobal**, bütün istifadəçilər üçün açıqdır (qəsdən; məhdudiyyət / əlavə business rule yoxdur). Real gateway gələnə qədər qalır.
- Hər checkout "TEST ÖDƏNİŞ" (mobil checkout + nəticə, web checkout + return, admin order); heç bir bank sorğusu, kart məlumatı, pul hərəkəti yoxdur.
- Ödə → order `paid`, abunəlik `active`, period yaranır, complex cihazda whitelist `add`; Ləğv/Rədd → `pending_payment` qalır, sonra yenidən ödənilə bilər.
- Nəticələr: 33 mövcud (legacy comp) abunəlik dəyişmir (BR-17); onların user-in başlatdığı renewal-ı da fake checkout-dan keçəcək. 24 saat ödənilməyən self-intent abunəliklər sweep ilə `cancelled` olur (B7 dizaynı).

### 7.2 Gecə backup-ı (B18 scope-undan kənar — blocker deyil; deploy-da manual backup)
`/usr/local/bin/salam-backup.sh` 3-cü sətirdə `/root/salam_secrets.env`-i yükləyir, fayl yeni serverdə yoxdur → skript ilk sətirdə çıxır; journal-da 2026-09-22-dən bəri hər gecə FAILED, `/var/backups/salam` heç yaranmayıb. Deploy-a mane deyil (backup əl ilə, §5.5), amma ayrıca düzəldilməlidir (ayrıca qərar).

## 8. Production smoke planı (deploy-dan sonra)

**API/infra (dərhal, ~10 dəq):**
1. `GET /v1/health/live|ready` 200 (DB + Redis).
2. `php artisan migrate:status` → 0 pending; `subscriptions:legacy-report` pre ilə eyni; §5.2 sayları eyni (devices 6, active roster 33, active subs 33, visitor_links 188); permissions 55, role grants 71, şablon locale-ləri 24 (dəyişməyib).
   Fake env tətbiqi: `php artisan tinker --execute='echo App\Domain\Payments\Support\PaymentGatewayMode::current();'` → `fake` və `php artisan config:show domain.payments` (gateway=fake, fake_enabled=true, allow_fake_in_production=true). `.env` dəyişikliyi `optimize`-dan ƏVVƏL edilməlidir (config cache).
3. `GET /invite/<yanlış>` → landing "etibarsız"; `GET /v1/invites/<yanlış>` → 410; access.log-da `<redacted>`.
4. `GET /v1/payments/fake-checkout/x` (imzasız) → 403 (route aktiv, imza tələb olunur).
5. Admin panel: login, Müraciətlər, Kompleks detalı, Bildiriş şablonları açılır; order detalında TEST badge.
6. Horizon `running`, `failed_jobs` artmır; scheduler-də yeni job-lar (`ExpireInvitationsJob`, `SweepAbandonedSubscriptionIntentsJob`).

**Mobil debug APK (əməkdaşlar, real cihazlar, real prod):**
1. Mövcud istifadəçi: login (email-OTP), Cihazlar (2 sütunlu kartlar), mövcud cihazda "Qapını Aç" əvvəlki kimi işləyir; widget/geofence/qonaq linki regressiyası.
2. Admin: Hüquqi müraciət təsdiqi → kompleks; Komendant təyin + mobil hesab linki; cihazı kompleksə bağlama (ownership `complex`).
3. Komendant (mobil): sakini email ilə dəvət → email gəlir → link → landing → "Tətbiqdə aç" → qeydiyyat/qəbul → kompleks üzvü.
4. Sakin: cihaz seç → Abunə ol → TEST checkout → **Ləğv et** → `pending_payment` qalır → "Ödəniş gözləyənlər" → **Ödə** → `paid` → Cihazlar-da "Qapını Aç" aktiv (refresh-siz) → real cihaz açılır (whitelist sync).
5. Ailə: başçı üzvü dəvət edir → üzv qəbul → üzv özü ödəyir / başçı üzv üçün ödəyir → üzv açır; kənar şəxs ödəyə bilmir (403); üzv qonaq linki yarada bilmir; başçı üzvü çıxarır → üzvün girişi dərhal dayanır.
6. Rədd ssenariləri: yanlış email ilə qəbul → xəta; ləğv/istifadə olunmuş link → "Dəvət etibarsızdır".

## 9. Məlum məsələlər

### 9.1 Real blocker
Yoxdur — kod, migration, seeder və konfiqurasiya deploy-a hazırdır. Deploy-un özü ayrıca GO tələb edir.

### 9.2 Non-blocker (B1–B18-dən qalan)
- **Gecə backup service-i** (§7.2) — bu deploy-un scope-unda deyil (qərar 2026-10-02); deploy zamanı manual DB backup alınır.
- **Köhnə nginx logları** qonaq linki tokenlərini saxlayır (653 sətir, 10 günlük rotasiya ilə ~10 günə özü silinəcək) — qərar: olduğu kimi saxlanılır.
- **Whitelist dublikat `remove`** ailə üzvü çıxarılanda — qərar: mövcud davranış saxlanılır.
- **OpenAPI qalıq drift** (`v1.yaml`: `Order.is_test`, köhnə `devices/{id}/invitations` təsviri, `RegisterInput.invitation_token`, `OpenCommand` sahələri; web route-lar `/invite/{t}`, `/v/{t}`; `validate.php` köhnə tag xətası) — qərar: indi toxunulmur.
- **Android App Links verify olunmur** (SHA-256 boş) — landing → "Tətbiqdə aç" ilə işləyir; release SHA Play Store mərhələsində.
- **iOS** Team ID / Associated Domains / TestFlight — bu mərhələdə yoxdur.
- **Kiçik UI:** ailə üzvünün qonaq linki cəhdində ümumi "icazəniz yoxdur" mesajı; ailə üzvündə "Yaşayış kompleksi" Profil bəndi görünür; pending siyahısında label yoxdursa "Cihaz #id".
- **Emulator freeze/ANR** (B16) B17/B18-də təkrarlanmayıb; real cihazda ilk yoxlama əməkdaş testində olacaq.

## 10. Android / iOS

| Platforma | Bu mərhələ | Sonra (store) |
|---|---|---|
| Android | **Debug APK**: `flutter build apk --debug -t lib/main_prod.dart` → prod API `https://api.salamheyetimiz.com`, logging off, debug imza. Paylanma **deploy-dan sonra** (yeni APK köhnə backend-də yeni endpoint-ləri tapmaz). | upload keystore, release signing, Play App Signing SHA-256 → `APP_LINKS_ANDROID_SHA256` |
| iOS | toxunulmur | Team ID, Associated Domains, `CODE_SIGN_ENTITLEMENTS`, TestFlight |
| Hər ikisi | cleartext yalnız `src/debug` manifestində (debug APK-da var, API https olduğu üçün istifadə olunmur); release manifestində yoxdur; prod flavor-da lokal URL yoxdur | — |
