# IMPLEMENTATION PLAN — Kompleks / Komendant / Sakin / Ailə / Qeydiyyat / Fake Payment / Bildiriş Redaktoru

> Status: **PLAN v2 — məhsul qərarları FINAL (2026-10-01).** Kod yazılmayıb, migration yaradılmayıb. Əsas: read-only audit + istifadəçinin final qərarları (BR-15..BR-21).
> Hər batch ayrıca GO ilə başlayır. Heç bir batch production-a avtomatik deploy olunmur.

---

## 1. Executive Summary

Məqsəd: mövcud **şəxsi cihaz** modelini pozmadan **kompleks (Hüquqi şəxs) → Komendant → sakin → ailə üzvü** axınını, **Fiziki/Hüquqi qeydiyyatı** (OpenStreetMap), **Fake Gateway ilə tam ödəniş axınını** və **admin bildiriş redaktorunu** əlavə etmək.

Prinsiplər:
- **Reuse:** mövcud `invitations` cədvəli (dormant, 0 sətir) ümumiləşdirilir — ikinci dəvət sistemi yaradılmır. `orders`/`subscriptions`/`subscription_periods`/`OrderService`/`ActivateSubscriptionOnOrderPaid`/`PaymentGateway` abstraction-ı, `RosterService`, `TemplatedMailer`, notification templates olduğu kimi istifadə olunur.
- **Additiv dəyişikliklər:** mövcud ENUM-lar dəyişdirilmir; yeni sütunlar nullable/default; mövcud 5 cihaz, 29 user, comp abunəliklər davranışını saxlayır.
- **Sərhədlər açıqdır:** cihaz satış qiyməti ≠ aylıq abunəlik; ödəyən (payer) ≠ benefisiar (beneficiary); ailə əlaqəsi ≠ cihaz girişi ≠ abunəlik; Fiziki ≠ Hüquqi müraciət modeli.
- **Sıra:** backend/domain → admin → mobile. 18 batch.

---

## 2. Final Business Rules

| # | Qayda |
|---|---|
| BR-1 | Sakin ailə üzvlərini dəvət edə bilir; hər ailə üzvü **ayrıca user account**. |
| BR-2 | Hər ailə üzvü üçün **ayrıca 12 AZN/ay** abunəlik (5 üzv = 5 abunəlik). |
| BR-3 | Ailə üzvünün abunəliyini **ailə başçısı və ya üzvün özü** ödəyə bilər; payer ≠ beneficiary ola bilər. |
| BR-4 | Ailə üzvü yalnız cihazdan **istifadə (açma)** edir; başqa userləri idarə edə bilməz, dəvət göndərə bilməz. |
| BR-5 | Qeydiyyatda **PHYSICAL** və ya **LEGAL** seçilir; modellər qarışmır. |
| BR-6 | PHYSICAL: şəxsi məlumat + ünvan + xəritə lokasiyası → admin satış/quraşdırmanı idarə edir. |
| BR-7 | LEGAL: kompleks adı, hüquqi məlumat, əlaqə şəxsi + xəritə lokasiyası → **admin təsdiqləyir** → kompleks yaranır → admin Komendant təyin edir → admin cihazları bağlayır və qiymət qoyur. |
| BR-8 | Xəritə: **OpenStreetMap** (Google Maps yoxdur). |
| BR-9 | Ödəniş: **Fake Gateway**; real BirPay-ə keçid yalnız gateway binding dəyişikliyi ilə. Statuslar: pending / authorising / paid / failed / cancelled / expired + return flow. |
| BR-10 | Komendant sakinə **ad, soyad, email** ilə dəvət göndərir; link **7 gün** etibarlı; resend rate-limitli. |
| BR-11 | Komendant sakinləri görür, idarə edir, **kompleksdən çıxara** bilir; **pulsuz abunəlik yarada bilməz**. |
| BR-12 | Cihaz qiyməti (150/200/250 AZN) = **birdəfəlik satış qiyməti**; aylıq abunəlikdən tam ayrıdır. |
| BR-13 | Abunəlik: sakin **12 AZN/ay**, ailə üzvü **12 AZN/ay**; abunəlik cihaz girişini aktivləşdirir. |
| BR-14 | Admin mövcud bildiriş şablonlarının **subject + body**-sini AZ/EN/RU üzrə redaktə edir. |
| BR-15 | **Komendant** mövcud email-OTP ilə girir; admin onun hesabını `complex_manager` rolu + konkret kompleksə bağlayır; ayrıca auth sistemi YOXDUR. |
| BR-16 | **Fake payment** config/feature flag ilə; bütün fake ekranlarda "TEST ÖDƏNİŞ"; axın real BirPay ilə eyni (gateway dəyişəndə biznes logic dəyişmir). |
| BR-17 | **Legacy comp abunəliklər** avtomatik kommersiyaya çevrilmir, charge yaradılmır; yeni qaydalar yalnız yeni abunəliklərə və user-in başlatdığı renewal-a tətbiq olunur. |
| BR-18 | Sakin/ailə üzvü çıxarıldıqda aktiv abunəlik **ləğv olunur**, avtomatik refund YOX (yalnız admin ayrıca əməliyyatla), audit log yazılır, ödənişlər və period-lar silinmir. |
| BR-19 | Ailə üzvü **qonaq (visitor) link yarada bilməz**; giriş və abunəlik hüquqları ayrıca saxlanılır; hər üzv ayrıca 12 AZN/ay (head + 5 üzv = 6 × 12 = 72 AZN/ay). |
| BR-20 | Hüquqi şəxsə cihaz satışı bu mərhələdə **app daxilində ödənişsizdir**: admin satış qiymətini qeyd edir və sahibliyi idarə edir; ödəniş app xaricində; `device_sale` flow-una toxunulmur. |
| BR-21 | Qiymət: **12 AZN / 30 gün / hər abunəlik** (main və additional eyni); 6 AZN qaydası istifadə olunmur. **Demo Komendant hesabı, demo kompleks və cihazlar saxlanılır**, adi complex_manager kimi işləyir, kodda ona xüsusi asılılıq yoxdur. |
| BR-22 | **Komendant ↔ mobil hesab bağlantısı (FINAL, 2026-10-01, B5 qərarı (b)):** yalnız **artıq mövcud, email-i təsdiqlənmiş və aktiv** mobil user hesabına, admin panelindən (`POST /admin/v1/admins/{id}/mobile-user`) bağlanır. Qeydiyyatdan keçməmiş email üçün **avtomatik bağlantı YOXDUR** (`pending_user_email` sütunu, migration və registration hook yaradılmır). |

---

## 3. Current Architecture (auditdən məlum olanlar)

- **Identity:** `users` (phone NOT NULL UNIQUE — R-DOM-01, email unique nullable, email-OTP; `RegisterUser` təsdiqlənməmiş sətri reuse edir), `admin_users` (role `complex_manager` + tək `complex_id`, RBAC, `AuthorizesAdmin::complexScopeId`). Guard-lar: `user`, `admin`.
- **Kompleks:** `complexes` (name, code, region_id, address); admin CRUD + `assignManager`; admin-ui `AdminsPage`/`ComplexDetailPage`.
- **Cihaz:** `devices.owner_user_id` (tək owner), `complex_id`, lokasiya; `device_users` (owner/user, active/revoked, `added_by_user_id`), `DeviceOwnershipService`, `RosterService::addMember` (owner yoxdursa `DeviceNotAssignedException`), `AssignPurchasedDeviceOnOrderPaid`, `DevicePolicy` (view: owner/üzv; configure: owner).
- **Dəvət:** `invitations` (device_id, invited_by_user_id, invitee_phone, role, payer, token char40, status, expires_at, accepted_device_user_id, linked_order_id) + `Invitation` model + enum-lar — **dormant, 0 sətir**. Spec: UC-06/§5.2/S-13/S-18. `visitor_links` = qonaq girişi (ayrı, toxunulmur).
- **Abunəlik:** device_user başına unikal; tier main/additional; snapshot `price_minor`, `term_days` (config 365); `createPending` (heç çağırılmır), `grantManual`, activate/renew/expire; `SubscriptionStatusQuery` = hər üzv öz abunəliyi ilə açır.
- **Ödəniş:** `orders.payer_user_id`, purpose (device_sale/sub_main/sub_additional/sub_renewal/bundle), `order_items.referenced_id` (sub_* → subscription_id); `OrderService::create` (idempotent, server pricing, in-flight dedupe); `PaymentGateway` interface — `BirPayGateway` (non-testing), `FakeKapitalGateway` (yalnız testing); webhook + `PaymentReturnController` (`salam://payment/return`), recheck. `OrderPricing` qlobal config qiymətləri istifadə edir (device_sale 135 / sub_main 12 / sub_additional 6 AZN).
- **Bildiriş:** `notification_templates` (9) + `notification_template_locales` (az/ru/en subject/body, `updated_by_admin_id`), kampaniyalar, inbox, FCM; admin-də yalnız kampaniya.
- **Email:** `TemplatedMailer` → Brevo SMTP; `EmailType` enum; Blade `emails/*`; subject settings-dən. Plain-text fallback OTP-spesifikdir.
- **Mobile:** Riverpod 3, go_router (tək redirect), dio `ApiClient`/`Envelope`, design system, ARB l10n az/en/ru, HomeShell 3 tab. Ödəniş UI, webview, deep link, xəritə, rol anlayışı **yoxdur**.
- **Prod fakt:** 6 kompleks (2 demo), 1 complex_manager (**demo seeded hesab**), 5 cihaz (hamısı owner-li, 1-i kompleksə bağlı), abunəliklər əsasən comp (price 0), 5 sifariş — hamısı `declined`.

---

## 4. Domain Model Changes

### 4.1 Konseptual xəritə
```
LegalEntityApplication ─(approve)→ Complex 1─* Device(ownership_mode=complex, sale_price_minor)
IndividualApplication  ─(admin lead)→ [satış/quraşdırma] → Device(ownership_mode=private, owner_user_id)
AdminUser(complex_manager, complex_id) ─user_id→ User  ==  Komendant
Complex 1─* ComplexMember(role=resident) *─1 User(Resident)
Invitation(kind=complex_resident | family_member)
FamilyLink(head_user_id → member_user_id)            ← AİLƏ ƏLAQƏSİ (giriş/ödənişdən asılı deyil)
DeviceUser(device, user, role=user, family_link_id?)  ← CİHAZ GİRİŞİ
Subscription(device_user_id ⇒ BENEFICIARY)            ← ABUNƏLİK
Order(payer_user_id ⇒ PAYER) ─items→ Subscription    ← ÖDƏNİŞ
SubscriptionPeriod(order_id, paid_by_user_id)         ← ledger (kim ödədi)
```

### 4.2 Üç qatın ayrılması (ailə)
| Qat | Cədvəl | Sual | Silinmə təsiri |
|---|---|---|---|
| Əlaqə | `family_links` | "X Y-nin ailə üzvüdürmü?" | Link `removed` → üzvün başçı tərəfindən verilmiş bütün `device_users` revoke olunur |
| Giriş | `device_users` | "X bu cihazı aça bilərmi (roster)?" | Revoke → açma dərhal dayanır |
| Abunəlik | `subscriptions` | "X-in bu cihazda aktiv dövrü varmı?" | Revoke olunmuş roster-də abunəlik açmağa icazə vermir; status siyasəti §25-də |

### 4.3 Payer vs Beneficiary
- **Beneficiary** = `subscriptions.device_user_id → device_users.user_id` (dəyişmir).
- **Payer** = `orders.payer_user_id` (mövcud).
- **Ledger:** `subscription_periods.paid_by_user_id` (yeni, nullable, `orders.payer_user_id`-dən backfill) — hesabat/admin üçün açıq.
- **Payer qaydası (yeni, server-side):** payer = beneficiary **və ya** beneficiary-nin həmin cihazdakı aktiv `family_link` başçısı. Komendant, admin, digər sakinlər payer ola bilməz (admin comp yolu ayrıca və Komendant-a bağlı deyil).

### 4.4 Cihaz rejimi
- `ownership_mode = private` (default; bütün mövcud cihazlar): mövcud məntiq 100% dəyişmir.
- `ownership_mode = complex`: `owner_user_id = NULL`, `complex_id` məcburi; sakinlər `role=user`; hər birinin öz abunəliyi.

### 4.5 Qiymət sərhədi
| Qiymət | Yer | İstifadə | Qarışmama qaydası |
|---|---|---|---|
| Cihaz satış qiyməti (birdəfəlik) | `devices.sale_price_minor` (+ `sale_recorded_at`, `sale_recorded_by_admin_id`) | **Yalnız admin qeydi/hesabatı** (BR-20); ödəniş app xaricində | `OrderPricing` device budağına **toxunulmur**; abunəlik hesablanmasında heç vaxt oxunmur |
| Aylıq abunəlik | config `subscriptions.default_prices_minor.sub_main = sub_additional = 1200`, `term_days=30` | İlkin: `createPending` snapshot → `subscriptions.price_minor`; renewal: **cari commercial config yenidən snapshot** olunur | `OrderPricing` sub_* üçün subscription snapshot-unu oxuyur (renewal-da yenilənmiş snapshot) |

### 4.6 Legacy comp abunəliklər (BR-17)
- **Təyini (derived, sütun yoxdur):** `price_minor = 0` və heç bir `subscription_periods.amount_minor > 0` yoxdur.
- **Avtomatik dəyişiklik yoxdur:** status, `ends_at`, qiymət, müddət olduğu kimi qalır; `auto_renew=false` olduğu üçün avtomatik charge mümkün deyil.
- **Renewal:** yalnız user özü başladanda → `RenewalService` renewal order-dən əvvəl abunəliyi **cari commercial qiymət/müddətlə yenidən snapshot** edir (1200 / 30 gün); comp `0` qiyməti heç vaxt renewal məbləği olmur.
- **Reconciliation:** `subscriptions:legacy-report` artisan əmri — **read-only** hesabat (comp sayı, bitmə tarixləri); yazma əməliyyatı yoxdur.

---

## 5. Database Changes (hamısı additiv)

| # | Migration | Dəyişiklik | Backfill |
|---|---|---|---|
| M1 | `generalize_invitations_table` (dormant, 0 sətir) | + `kind` ENUM(`family_member`,`complex_resident`) default `family_member`; `device_id` → nullable; + `complex_id` FK nullable; `invited_by_user_id` → nullable; + `invited_by_admin_id` FK nullable; `invitee_phone` → nullable; + `invitee_email` (160) nullable; + `invitee_first_name`, `invitee_last_name` (60); + `token_hash` CHAR(64) unique; `token` → nullable (sonra drop-plan); + `send_count`, `last_sent_at`, `revoked_at`, `revoked_by_user_id`, `revoked_by_admin_id`; + `family_link_id` nullable; yeni unikal: aktiv `(kind, complex_id, invitee_email, is_pending)` | Yoxdur (0 sətir). Pre-check: `SELECT COUNT(*)=0` |
| M2 | `create_complex_members` | complex_id, user_id, role ENUM(`resident`) , status (active/removed), `is_active` generated, invitation_id, joined_at, removed_at, removed_by_user_id, removed_by_admin_id; unikal aktiv (complex_id,user_id) | Opsional: kompleks cihazlarında aktiv roster-i olan userlər üçün `resident` sətri (B4-də report-only, sonra qərar) |
| M3 | `create_family_links` | head_user_id, member_user_id, status (active/removed), `is_active` generated, invitation_id, timestamps; unikal aktiv (head,member); CHECK head≠member (app-level) | Yoxdur |
| M4 | `add_family_link_id_to_device_users` | nullable FK | Yoxdur (mövcud sətirlər NULL = legacy) |
| M5 | `add_ownership_and_sale_price_to_devices` | `ownership_mode` ENUM(`private`,`complex`) default `private`; `sale_price_minor` INT UNSIGNED nullable; `sale_recorded_at` nullable; `sale_recorded_by_admin_id` FK nullable | Bütün mövcud → `private` (default) |
| M6 | `add_location_to_complexes` | `latitude`, `longitude` DECIMAL nullable, `legal_entity_application_id` nullable | Yoxdur |
| M7 | `add_user_id_to_admin_users` | nullable unique FK → users | Yoxdur (demo hesab qərarı §25) |
| M8 | `add_paid_by_to_subscription_periods` | `paid_by_user_id` nullable FK | `UPDATE … JOIN orders` (idempotent) |
| M9 | `add_account_type_to_users` | `account_type` ENUM(`physical`,`legal`) **nullable** (NULL = legacy) | Yoxdur |
| M10 | `create_individual_applications` | user_id, full_name, phone, email, address, latitude, longitude, region_id, note, status (new/contacted/in_progress/installed/rejected), handled_by_admin_id, device_id nullable, timestamps | — |
| M11 | `create_legal_entity_applications` | applicant_user_id, complex_name, legal_name, voen (10), legal_address, contact_person_name, contact_phone, contact_email, address, latitude, longitude, region_id, apartments_count nullable, note, status (pending/approved/rejected), reviewed_by_admin_id, reviewed_at, rejection_reason, complex_id nullable | — |
| M12 | Seeder/data | yeni permission-lar + rol təyinatları; yeni bildiriş şablonları + az/en/ru locale; `system.admin_campaign` çatışmayan locale-lər; email subject açarları | Idempotent `updateOrInsert` |

> Config (migration deyil): `subscriptions.term_days 365→30`, `reminder_days [30,15,7,1]→[7,1]`, `default_prices_minor.sub_main=1200`, `sub_additional 600→1200`, `payments.gateway = fake|birpay`, `payments.fake_enabled` (feature flag), `payments.allow_fake_in_production`. Mövcud abunəliklər snapshot olduğu üçün təsirlənmir (BR-17). Qeyd: legacy illik comp-lar üçün xatırlatmalar `[7,1]`-ə enir (d30/d15 artıq göndərilmir) — data dəyişmir, yalnız bildiriş tezliyi.

---

## 6. Backend/API Changes (modul üzrə)

| Modul | Yeni | Dəyişən (geriyə uyğun) |
|---|---|---|
| Payments | `PaymentGatewayResolver` (config binding + feature flag), `FakeCheckoutController` + Blade səhifə ("TEST ÖDƏNİŞ"), `FakeKapitalGateway` genişlənməsi (redirect URL + simulyasiya state), `SubscriptionPaymentAuthorizer` (payer qaydası) | `IntegrationsServiceProvider` binding; `OrderPricing` (sub_* → snapshot; **device budağı dəyişmir**, BR-20); `OrderService::create` payer yoxlaması; `CreateOrderRequest` (dəyişmir) |
| Subscriptions | `SubscriptionPriceResolver`, `StartDeviceSubscription`, `ReopenSubscriptionForPayment` (cancelled/expired → pending_payment, period-lar qorunur), `CancelOnAccessRemoval`, `subscriptions:legacy-report` (read-only) | `createPending` (resolver + term; mövcud cancelled/expired sətir varsa reopen); `RenewalService` (renewal-dan əvvəl commercial re-snapshot); `SendRenewalRemindersJob` (config günlər); `SubscriptionStatusQuery` (complex mode budağı) |
| Visitor | — | `VisitorLinkController::store` / `assertCanShare`: çağıranın `device_users.family_link_id IS NOT NULL` isə **403** (BR-19); legacy roster userlər (`family_link_id=NULL`) dəyişmir |
| Roster | `InvitationService` (create/resend/revoke/lookup/accept/decline/expire), `InvitationTokens`, `FamilyService`, `ComplexMembershipService` | `RosterService::addMember` (complex mode-da owner tələbi yoxdur, yalnız role=user); `Invitation` model (kind, cast-lar) |
| Devices | `ComplexDeviceQuery` | `DevicePolicy` (+`subscribe`, `manageFamily`); `UpdateDevice`/request (+ownership_mode, sale_price_minor, complex_id); `DeviceResource` (+`sale_price` yalnız admin resource-da; mobile-da `subscription_price`) |
| Admin/Komendant | `KomendantContext`, `EnsureKomendant` middleware, Komendant controller-ləri | `AdminManagementController` (+user link), `ComplexManagementController` (+devices assign, location) |
| Users/Registration | `ApplicationService` (individual/legal), `ApproveLegalEntityApplication` (→ Complex) | `RegisterUser`/`VerifyEmailAndIssueTokens` (+`invitation_token` claim hook, `account_type` opsional) |
| Mail | `EmailType::ResidentInvitation`, `FamilyInvitation`, `ApplicationReceived` + Blade | `TemplatedMailer::text()` data-driven |
| Notifications | `NotificationTemplateAdminController`, `TemplateUpdateValidator` (placeholder whitelist), preview | Yoxdur (mövcud `TemplateRenderer` istifadə olunur) |
| Me/Bootstrap | — | `/v1/me` +`roles`, `komendant`, `complexes`, `family` (yalnız yeni açarlar) |

---

## 7. Admin Panel Changes
1. **Cihaz forması/detalı:** `ownership_mode`, kompleks seçimi, `sale_price_minor` (AZN input, "birdəfəlik satış qiyməti" label-ı; abunəlik qiyməti burada göstərilmir).
2. **Kompleks detalı:** cihaz bağla/çıxar, OSM lokasiya (Leaflet), sakinlər (`complex_members`), dəvətlər (read-only + revoke), mənbə müraciət linki.
3. **Adminlər:** Komendant üçün "Mobil hesab (email)" link/unlink + status badge.
4. **Müraciətlər** (yeni bölmə): Fiziki (lead pipeline statusları) və Hüquqi (təsdiq/rədd → kompleks yarat) — **iki ayrı tab/cədvəl**.
5. **İstifadəçilər** (yeni): bütün `users` + account_type + müraciət + kompleks/ailə əlaqələri (roster-siz userlər də görünür).
6. **Bildirişlər → Şablonlar** tabı (§17).
7. RBAC: yeni permission-lar `PERM`-ə, `navItems`-ə.

---

## 8. Mobile App Changes
- Paketlər: `app_links` (deep/universal link), `flutter_map` + `latlong2` (OSM), `webview_flutter` (Fake checkout; BirPay-də də eyni).
- Router: `/invite/:token`, `/payment/return`, `/register/type`, `/register/physical`, `/register/legal`, `/complex/:id`, `/complex/:id/device/:deviceId`, `/checkout/:orderId`, `/komendant/*`, `/family/*`. Tək redirect qalır; pending invite token saxlanılır.
- Session: `/v1/me` → `roles` (`resident`, `komendant`, `family_member`, `head`) — UI gating (server həmişə həqiqət mənbəyidir).
- Yeni feature folder-lər (mövcud `invitations/` pattern-i): `payments/`, `registration/` (auth genişlənməsi), `complex/`, `komendant/`, `family/`.
- Mövcud Devices/açma/widget/geofence/visitor links **toxunulmur**.

---

## 9. Invitation & Account Activation Flow
1. Yaradılış (`kind=complex_resident` Komendant tərəfindən; `kind=family_member` ailə başçısı tərəfindən): `token` = 32 bayt random (base64url), DB-də yalnız `sha256` (`token_hash`), `expires_at = now+7d`, status `pending`.
2. Email (`TemplatedMailer`, queue): link `https://salamheyetimiz.com/invite/{token}`.
3. Link → universal link app-i açır; app yoxdursa web landing (mağaza linkləri + "app-də aç").
4. `GET /v1/invites/{token}` (public, throttle): kind, kompleks adı/dəvət edən adı, ad-soyad, maskalı email, status. Etibarsız/vaxtı keçmiş/ləğv olunmuş → **eyni** 410 envelope (enumeration yoxdur).
5. **Yeni user:** register formu (ad/soyad/email prefill, email kilidli) + **telefon** (R-DOM-01) → `POST /v1/auth/register` (`invitation_token` ilə) → `verify-email` → `ClaimInvitation` (şərtli update `WHERE status='pending' AND expires_at>now`) → token-lər.
6. **Mövcud user:** email-OTP login → `POST /v1/invites/{token}/accept` → yalnız `auth.user.email == invitee_email` (case-insensitive, verified).
7. Claim nəticəsi: `complex_resident` → `complex_members(resident)`; `family_member` → `family_links(active)` + başçının seçdiyi cihaz(lar) üçün `device_users(role=user, added_by_user_id=head, family_link_id)`.
8. Resend: eyni dəvət, **yeni token** (köhnə hash etibarsız), `expires_at` yenilənir; limit: dəvət başına 60s cooldown, max 5/gün; dəvət edən başına 30/gün.
9. Expire job (saatlıq): pending + `expires_at<=now` → `expired`.

---

## 10. Physical Person Registration Flow
1. Welcome → "Fiziki şəxs" → mövcud register (ad, soyad, telefon, email) → OTP.
2. "Ünvan və lokasiya": ünvan + OSM xəritədə pin (geolocator ilə ilkin mövqe) → `POST /v1/applications/individual`.
3. `users.account_type=physical`; `individual_applications(status=new)`; admin-ə bildiriş/email (opsional).
4. Admin: Müraciətlər → Fiziki → status pipeline (contacted → in_progress → installed); quraşdırma zamanı mövcud `RegisterDevice` + `AssignDeviceOwner` (private mode) və ya `device_sale` sifarişi istifadə olunur — yeni satış məntiqi yazılmır.
5. User app-də müraciət statusunu görür (`GET /v1/applications/mine`).

## 11. Legal Person / Residential Complex Flow
1. Welcome → "Hüquqi şəxs" → register (əlaqə şəxsinin hesabı) → OTP.
2. Forma: kompleks adı, hüquqi ad, VÖEN, hüquqi ünvan, əlaqə şəxsi (ad/telefon/email), kompleks ünvanı, OSM pin, mənzil sayı (opsional) → `POST /v1/applications/legal`.
3. `account_type=legal`; `legal_entity_applications(status=pending)`.
4. Admin təsdiq → `ApproveLegalEntityApplication` (transaction): `complexes` yaradılır (ad, ünvan, lat/lng, region, `legal_entity_application_id`) → status `approved`, `complex_id` yazılır. Rədd → `rejected` + səbəb + email.
5. Admin Komendant təyin edir (§12) və cihazları bağlayır (§15).
6. Fiziki və Hüquqi **ayrı cədvəl, ayrı endpoint, ayrı admin tab** — heç bir ortaq status sütunu yoxdur.

## 12. Manager/Komendant Flow
- **Identity (FINAL, BR-15):** `admin_users(role=complex_manager, complex_id)` + `admin_users.user_id` → mobil user. Komendant mobil-ə mövcud email-OTP ilə girir (ayrıca auth yoxdur); `KomendantContext` qoşulmuş admin sətrini, rolu, `status=active`-i, `complex_id`-ni həll edir. Demo hesab (`manager@…`) adi complex_manager kimi işləyir — kodda heç bir email/ID-yə əsaslanan xüsusi hal yoxdur.
- Admin addımı: Admin yaradır/seçir → kompleks təyin edir (mövcud) → "Mobil hesab" email-i ilə link — **yalnız mövcud, təsdiqlənmiş, aktiv user-ə** (BR-22). User hələ qeydiyyatdan keçməyibsə, əvvəl qeydiyyatdan keçir, sonra admin əl ilə bağlayır; avtomatik/pending link yoxdur.
- Komendant imkanları: kompleks icmalı, cihazlar (status, online, qiymət görünmür — yalnız abunəlik qiyməti), sakinlər siyahısı (abunəlik statusu ilə), dəvət göndər/siyahı/resend/revoke, sakini kompleksdən çıxar.
- Qadağalar: pulsuz abunəlik, qiymət dəyişmək, cihaz bağlamaq, başqa kompleks — server-side rədd.
- Sakini çıxarma (`RemoveComplexResident`): `complex_members→removed` → həmin kompleks cihazlarında sakinin və onun ailə üzvlərinin `device_users` revoke (RosterService) → whitelist eventləri (mövcud) → **aktiv abunəliklər `cancelled` (`CancelOnAccessRemoval`, səbəb `removed_by_komendant`), avtomatik refund YOX**, `subscription_periods`/`orders` silinmir → audit (`complex.resident_removed`, `subscription.cancelled_on_removal`). Refund yalnız admin-in mövcud refund əməliyyatı ilə.

## 13. Resident Flow
1. Dəvəti qəbul edir (§9) → app "Kompleksim" ekranı.
2. `GET /v1/complexes/{id}/devices` → cihazlar + `subscription_price` (12 AZN/ay) + çağıranın statusu (`none|pending_payment|active|expired`).
3. Cihaz seçir → "Abunə ol" → `POST /v1/complexes/{c}/devices/{d}/subscribe` → `StartDeviceSubscription` (transaction + `lockForUpdate`): üzvlük → `RosterService::addMember(role=user)` → `createPending(tier=main)` → `OrderService::create(payer=self, sub_main→subscription_id)` → `{order, checkout_url}`.
4. Checkout (§16) → paid → abunəlik aktiv → cihaz "Cihazlar" tab-ında `can_open=true` (mövcud ekran).
5. Yeniləmə: mövcud `POST /v1/subscriptions/{id}/renew` (aylıq).

## 14. Family Member Flow
1. Başçı (complex resident və ya private owner): cihaz detalı → "Ailə üzvləri" → dəvət: ad, soyad, email + giriş veriləcək cihaz(lar) (başçının **aktiv roster**-i olan cihazlardan) → `invitations(kind=family_member)`.
2. Üzv qəbul edir (§9) → `family_links` + `device_users(role=user, family_link_id)`.
3. Abunəlik: hər cihaz üçün üzvün **öz** pending abunəliyi (`tier=additional`, 12 AZN). Ödəniş: başçı ("Ailə üzvü üçün ödə") və ya üzvün özü — eyni `POST /v1/orders` (`sub_additional → subscription_id`); server `SubscriptionPaymentAuthorizer` ilə yoxlayır.
4. Üzv: yalnız açma; `manageFamily`, dəvət, roster, geofence, **visitor link yaratmaq — qadağan** (BR-19, `VisitorLinkController`-də server-side 403).
5. Başçı üzvü silə bilər → `family_links→removed` + üzvün başçı tərəfindən verilmiş `device_users` revoke + **aktiv abunəlik `cancelled`, refund yox, audit, tarixçə qorunur** (BR-18).
6. Qiymət nümunəsi: head (main, 12) + 5 üzv (additional, hər biri 12) = **6 ödənişli abunəlik = 72 AZN/ay**; hər biri ayrıca order/period ilə.
7. Yenidən əlavə olunma: eyni user + cihaz → mövcud revoked `device_users` reaktivləşir (mövcud RosterService) → köhnə cancelled abunəlik `ReopenSubscriptionForPayment` ilə yeni snapshot-la `pending_payment` olur (period tarixçəsi saxlanılır).
6. Complex mode-da ailə üzvü `complex_members` sətri **almır** → kompleksin digər cihazlarını görmür (eskalasiya yoxdur).

## 15. Device Assignment & Pricing Flow
1. Admin: cihaz → kompleks seç + `ownership_mode=complex` + `sale_price_minor` (məs. 15000/20000/25000). Validation: complex mode → `owner_user_id` NULL olmalıdır (owner-li cihazın rejim dəyişməsi yalnız aktiv rosteri yoxdursa və ya explicit transfer ilə — bloklanır, xəta mesajı).
2. Statusu `active` admin təyin edir (complex mode-da owner assignment tələb olunmur).
3. Satış qiyməti **yalnız admin qeydidir** (BR-20): admin qiyməti yazır (`sale_recorded_at/by`), ödəniş app xaricində qəbul olunur, sahiblik prosesini admin idarə edir. Mövcud `device_sale` order flow-una və abunəlik ödənişinə toxunulmur; mobil sakin satış qiymətini görmür. In-app `device_sale` gələcəkdə ayrıca feature.
4. Abunəlik qiyməti cihazdan **asılı deyil** (12 AZN/ay qlobal config); gələcəkdə cihaz-spesifik abunəlik lazım olsa ayrıca sütun — bu planda yoxdur.

## 16. Subscription & Fake Payment Flow
**Gateway seçimi (FINAL, BR-16):** `config('domain.payments.gateway')` (`PAYMENT_GATEWAY` env): `fake` → `FakeKapitalGateway`, `birpay` → `BirPayGateway`; `testing` env həmişə fake. Fake ayrıca feature flag (`payments.fake_enabled`) + production-da əlavə `payments.allow_fake_in_production=true` tələb edir. "TEST ÖDƏNİŞ" göstərilir: fake checkout səhifəsində, return səhifəsində, mobil CheckoutScreen/PaymentResultScreen-də (`order.is_test=true` — resource-da gateway-dən derived) və admin order detalında. Biznes logic (OrderService, callback pipeline, aktivləşmə) gateway-dən asılı deyil.

**Axın:**
1. `OrderService::create` → `gateway->registerOrder` → Fake: `bank_order_id = fake_{uuid}`, `bank_redirect_url = /v1/payments/fake-checkout/{reference}` (signed URL, 30 dəq).
2. Fake checkout səhifəsi (Blade): məbləğ, təyinat, düymələr **Ödə / Rədd et / Ləğv et / Gözlət**.
3. Düymə → `FakeKapitalGateway` state yazır → **mövcud callback pipeline** (`ProcessPaymentCallback` — real BirPay ilə eyni yol; imza fake mode-da daxili) → `markPaid`/`markFailed`/`markCancelled` → `OrderPaid` → `ActivateSubscriptionOnOrderPaid`.
4. Redirect → mövcud `GET /v1/payments/return?paymentId=` → `verifyAndApply` (fake `getOrderStatus`) → HTML + `salam://payment/return?status=&order=`.
5. App: deep link qəbul edir və ya `GET /v1/orders/{id}` polling (2s × 30) + `recheck`; nəticə: success / failed / cancelled / pending.
6. Expiry: mövcud `ExpireStaleOrdersJob` (30 dəq) → `expired`; pending abunəlik qalır, yenidən cəhd eyni abunəliyi istifadə edir.
7. BirPay-ə keçid: env `PAYMENT_GATEWAY=birpay` + real E2E — kod dəyişikliyi yoxdur.

**Statuslar:** order: pending → authorising → paid | failed | cancelled | expired; subscription: pending_payment → active → expired/cancelled.

## 17. Notification Editor
- API: `GET /admin/v1/notification-templates` (key, category, channels, is_active, locale tamlığı), `GET /{id}` (az/en/ru subject+body), `PUT /{id}/locales/{locale}` (subject ≤120, body ≤1000, placeholder whitelist — `TemplateRenderer`-in dəstəklədiyi dəyişənlər; naməlum `{x}` → 422), `POST /{id}/preview` (nümunə data ilə render).
- `updated_by_admin_id` + audit_logs (`notification.template_updated`, köhnə/yeni dəyər).
- Permission: `notifications.templates.view`, `notifications.templates.manage`.
- `system.admin_campaign` redaktə siyahısında read-only (wrapper).
- UI: Bildirişlər → **Kampaniyalar | Şablonlar**; redaktə: AZ/EN/RU tabları, dəyişən chip-ləri, push önizləmə, "Yadda saxla" + dəyişiklik tarixi.
- Mövcud dispatcher/FCM toxunulmur; cache varsa invalidasiya.

## 18. Authorization / Security Rules
| # | Qayda | Enforcement |
|---|---|---|
| S-1 | Sakin yalnız üzv olduğu kompleksin cihazlarını görür | `ComplexPolicy` + `complex_members` aktiv; əks halda 404 |
| S-2 | Sakin başqasının roster/abunəliyinə toxuna bilməz | `DevicePolicy`, `SubscriptionPolicy`, `SubscriptionPaymentAuthorizer` |
| S-3 | Komendant yalnız öz kompleksi | `KomendantContext.complexId`; client `complex_id` ignore; 404 |
| S-4 | Komendant pulsuz abunəlik/qiymət/cihaz bağlama edə bilməz | Endpoint yoxdur + permission yoxdur |
| S-5 | Ailə üzvü idarəetmə edə bilməz | `family_member` rolunda `manageFamily`/invite → 403 |
| S-6 | Payer qaydası | payer==beneficiary ∨ aktiv family head həmin cihazda |
| S-7 | Token | sha256 hash, birdəfəlik, 7 gün, email-bound, revoke, throttle, uniform 410 |
| S-8 | `pending_payment` heç vaxt açmır | `SubscriptionStatusQuery` (mövcud) |
| S-9 | Qiymət yalnız server | `OrderPricing` snapshot; client məbləği qəbul edilmir |
| S-10 | Fake gateway prod-da yalnız explicit flag | binding guard + banner + audit |
| S-11 | Race | unikal indekslər, `lockForUpdate`, idempotency key, şərtli claim update |
| S-12 | Demo complex_manager | **Saxlanılır** (BR-21), adi complex_manager kimi; kodda xüsusi hal yoxdur. Prod-da onunla test: credential-lar user tərəfindən verilir, hər prod əməliyyatı ayrıca GO ilə; credential repo/log/plan-a yazılmır |
| S-13 | Ailə üzvü visitor link yarada bilməz | `VisitorLinkController` server-side 403 (`family_link_id`) |
| S-14 | Çıxarılma → abunəlik ləğvi | `CancelOnAccessRemoval` transaction daxilində; refund yalnız admin; audit məcburi |

## 19. API Endpoint Plan
| Metod | Path | Guard | Qeyd |
|---|---|---|---|
| GET | `/v1/me` | user | +roles/komendant/complexes/family (additiv) |
| GET | `/v1/invites/{token}` | public, throttle | lookup |
| POST | `/v1/invites/{token}/accept` | user | mövcud hesab |
| POST | `/v1/invites/{token}/decline` | user | |
| POST | `/v1/auth/register` | public | **mövcud**; +`invitation_token`, +`account_type` (opsional) |
| POST | `/v1/auth/verify-email` | public | **mövcud**; claim hook |
| POST | `/v1/applications/individual` | user | |
| POST | `/v1/applications/legal` | user | |
| GET | `/v1/applications/mine` | user | |
| GET | `/v1/complexes` · `/v1/complexes/{id}` · `/v1/complexes/{id}/devices` | user | üzv |
| POST | `/v1/complexes/{id}/devices/{deviceId}/subscribe` | user | Idempotency-Key |
| POST | `/v1/orders` · GET `/v1/orders/{id}` · POST `/v1/orders/{id}/recheck` | user | **mövcud**; payer qaydası |
| POST | `/v1/subscriptions/{id}/renew` | user | **mövcud** |
| GET/POST | `/v1/komendant/invitations` · POST `/{id}/resend` · POST `/{id}/revoke` | user+komendant | |
| GET | `/v1/komendant/complex` · `/devices` · `/residents` | user+komendant | |
| DELETE | `/v1/komendant/residents/{userId}` | user+komendant | kompleksdən çıxar |
| GET/POST | `/v1/family/members` · DELETE `/v1/family/members/{userId}` | user | başçı |
| POST/GET | `/v1/devices/{id}/invitations` | user | spec endpoint-i (family) |
| GET | `/v1/family/subscriptions` | user | başçının ödəyə biləcəyi pending abunəliklər |
| GET | `/v1/payments/fake-checkout/{reference}` · POST `/{reference}/{action}` | signed URL | yalnız fake mode |
| GET | `/v1/payments/return` | public | **mövcud** |
| GET | `/invite/{token}` · `/.well-known/assetlinks.json` · `/.well-known/apple-app-site-association` | web | |
| PATCH | `/admin/v1/devices/{id}` | admin | **mövcud**; +ownership_mode/sale_price/complex |
| POST/DELETE | `/admin/v1/complexes/{id}/devices/{deviceId}` | admin | |
| POST/DELETE | `/admin/v1/admins/{id}/mobile-user` | admin | Komendant link |
| GET | `/admin/v1/complexes/{id}/members` · `/admin/v1/invitations` | admin | |
| GET/PATCH | `/admin/v1/applications/individual[/{id}]` | admin | status |
| GET | `/admin/v1/applications/legal[/{id}]` · POST `/{id}/approve` · `/{id}/reject` | admin | |
| GET | `/admin/v1/users[/{id}]` | admin | bütün hesablar |
| GET/PUT/POST | `/admin/v1/notification-templates…` | admin | §17 |

## 20. Migration Plan
1. **Pre-flight (read-only):** `invitations` sətir sayı = 0; DB backup; `migrate --pretend` çıxışı review.
2. Sıra: M5 → M7 → M2 → M3 → M1 → M4 → M6 → M8 → M9 → M10 → M11 → M12 (batch-lara bölünmüş, §23).
3. Hər migration `down()` tam; ENUM ALTER yalnız dormant `invitations` üzərində və yalnız yeni sütun/ENUM yaratmaq.
4. Backfill: M8 (idempotent JOIN); M5 default; digərləri yoxdur.
5. Deploy: `git pull --ff-only` → `migrate --force` → `optimize` → `php-fpm reload` → horizon restart (yeni job-lar üçün, user GO ilə).
6. Rollback: kod revert + `migrate:rollback --step=N` (yeni cədvəllər boş olduqda təhlükəsiz).

### Legacy / Compatibility qaydaları
| Obyekt | Qayda |
|---|---|
| Mövcud 5 cihaz | `ownership_mode=private`; owner/roster/abunəlik/açma/widget/geofence dəyişmir |
| Mövcud userlər | `account_type=NULL` = legacy; müraciət tələb olunmur; heç bir ekran onları bloklamır |
| Mövcud abunəliklər (comp, müxtəlif term) | **Dəyişmir, charge yaradılmır** (BR-17); `ends_at`-a qədər aktiv qalır; yalnız user özü renewal başladanda 12 AZN / 30 gün re-snapshot; read-only `subscriptions:legacy-report` |
| Demo complex_manager / demo komplekslər / cihazlar | Saxlanılır, toxunulmur; cleanup ayrıca post-release task (bu planın scope-u xaricində) |
| Mövcud roster userlərin visitor link hüququ | `family_link_id=NULL` → dəyişmir |
| Mövcud `device_users` | `family_link_id=NULL`; mövcud owner/user rolları eynidir |
| Mövcud complex_manager | Mobil link yoxdur → mobil Komendant UI görünmür; web dəyişmir |
| Köhnə app versiyaları | Bütün yeni sahələr additiv; köhnə endpoint-lərin cavab forması dəyişmir |
| Mövcud orders | Toxunulmur; `paid_by_user_id` backfill yalnız ledger-də |

## 21. Mobile Screen/Component Plan
| Ekran | Komponentlər (design system reuse) | API |
|---|---|---|
| RegisterTypeScreen | 2 seçim kartı (AppCard) | — |
| PhysicalRegistrationScreen | AppInputs + `OsmLocationPicker` | register + applications/individual |
| LegalRegistrationScreen | AppInputs (VÖEN validator) + `OsmLocationPicker` | register + applications/legal |
| `OsmLocationPicker` (shared widget) | flutter_map + pin + "mövqeyimi götür" (geolocator) + ünvan input | — |
| ApplicationStatusScreen | StatusBadge, timeline | applications/mine |
| InviteLandingScreen | dəvət kartı, Qəbul/İmtina | invites/{token} |
| ComplexHomeScreen | kompleks başlığı, cihaz kartları | complexes/{id}/devices |
| ComplexDeviceDetailScreen | qiymət bloku, "Abunə ol" CTA | subscribe |
| CheckoutScreen (`payments/`) | WebView + "TEST" banner + nəticə | orders, return deep link |
| PaymentResultScreen | success/failed/pending/cancelled state | orders/{id} |
| KomendantHomeScreen | statistik kartlar, cihazlar | komendant/complex, devices |
| KomendantInviteScreen | ad/soyad/email form | komendant/invitations |
| KomendantInvitationsScreen | status tabları, resend/revoke | komendant/invitations |
| KomendantResidentsScreen | siyahı, abunəlik chip, "Çıxar" | komendant/residents |
| FamilyMembersScreen | Active/Pending/Removed tabları | family/members |
| FamilyInviteScreen | ad/soyad/email + cihaz seçimi | devices/{id}/invitations |
| FamilyPaymentsScreen | üzvlərin pending abunəlikləri, "Ödə" | family/subscriptions, orders |
- HomeShell: Komendant üçün Home-da "Kompleksim (Komendant)" kartı/girişi; sakin üçün "Kompleksim" kartı; tab sayı dəyişmir (vizual dizayn mərhələsinə qədər).
- Bütün mətnlər ARB (az/en/ru); bütün ekranlar tokens/theme üzərində — sonrakı vizual dəyişikliklər token/komponent səviyyəsində.

## 22. Test Strategy
- **Backend (Pest):** hər batch öz Feature + Unit testləri ilə; regression: mövcud 90 test faylı yaşıl qalmalıdır (xüsusən Devices, Roster, Subscriptions, Payments, DeviceComm).
- **Kritik ssenarilər:** token (valid/expired/revoked/replayed/başqa email), resend limit, Komendant scope 404, pulsuz abunəlik yolunun olmaması, complex mode roster, private mode regression, payer≠beneficiary (icazəli/icazəsiz), fake paid/failed/cancelled/expired/callback replay, ikiqat subscribe (idempotent), family removal → revoke, VÖEN/lat-lng validation, Fiziki/Hüquqi ayrılığı, şablon placeholder validation + audit.
- **Contract:** OpenAPI yenilənir; `Feature/Docs` testi.
- **Mobile:** repository/mapper/provider testləri; deep link parser; payment result state machine; role gating; ARB tamlığı; `flutter analyze` təmiz.
- **E2E (lokal, fake):** Hüquqi müraciət → təsdiq → Komendant link → dəvət → aktivləşmə → cihaz seçimi → fake ödəniş → açma; ailə dəvəti → başçı ödəyir → üzv açır.

## 23. Batch-by-Batch Implementation Plan

> Hər batch: ayrıca GO → implement → test → review → commit (GO ilə) → deploy (ayrıca GO).

### B1 — Payment gateway abstraction + Fake checkout
- **Məqsəd:** fake ilə tam ödəniş dövrü; real BirPay-ə keçid yalnız config (BR-16).
- **Backend:** `IntegrationsServiceProvider`, `FakeKapitalGateway`, yeni `FakeCheckoutController` + Blade ("TEST ÖDƏNİŞ"), `PaymentReturnController` (test banner), `OrderResource` (+`is_test`), `config/domain/payments.php` (`gateway`, `fake_enabled`, `allow_fake_in_production`).
- **DB:** yoxdur. **API:** fake-checkout GET/POST; mövcud return/orders reuse.
- **Admin/Mobile:** yoxdur (admin order detalında test badge B11-də).
- **Test:** fake paid/failed/cancelled/expired/pending, callback idempotency, signed URL, flag off → fake binding yoxdur, prod guard, `birpay` binding regression.
- **Dep:** — . **AC:** lokalda sifariş → fake səhifə (TEST ÖDƏNİŞ) → paid → `OrderPaid`; gateway dəyişəndə OrderService/callback kodu eynidir.
- **Risk:** prod-da təsadüfən fake aktiv olması → iki flag + banner + audit.

### B2 — Pricing, monthly term, legacy-safe renewal
- **Məqsəd:** 12 AZN / 30 gün hər abunəlik üçün; satış qiyməti yalnız admin qeydi; legacy comp-lar toxunulmaz (BR-17, BR-20, BR-21).
- **Backend:** `OrderPricing` (sub_* snapshot; device budağı dəyişmir), `SubscriptionPriceResolver`, `SubscriptionService::createPending` (+reopen), `ReopenSubscriptionForPayment`, `RenewalService` (commercial re-snapshot), `SendRenewalRemindersJob`, config subscriptions, `subscriptions:legacy-report` (read-only).
- **DB:** M5 (`ownership_mode`, `sale_price_minor`, `sale_recorded_*`), M8 (`paid_by_user_id` + backfill).
- **API:** yoxdur (daxili; mövcud renew endpoint davranışı: qiymət cari commercial).
- **Test:** main və additional = 1200/30; comp abunəlik dəyişmir və avtomatik order yaranmır; comp renewal → 1200/30 order; cancelled → reopen → pending (period-lar qorunur); sale_price heç bir order məbləğinə düşmür; reminders [7,1].
- **Dep:** B1. **AC:** qiymət heç vaxt client-dən gəlmir; satış və abunəlik qiyməti heç vaxt qarışmır; legacy-report heç bir yazma etmir.
- **Risk:** `subscriptions.device_user_id` UNIQUE → yenidən abunəlik eyni sətri reopen etməlidir (bax R-19).

### B3 — Invitation domain (generalized `invitations`)
- **Məqsəd:** tək dəvət mühərriki (complex_resident + family_member).
- **Backend:** `Invitation` model, `InvitationService`, `InvitationTokens`, `ExpireInvitationsJob`, `EmailType` + Blade, `TemplatedMailer::text()`.
- **DB:** M1. **API:** yoxdur (servis qatı + test). **Test:** token hash, expiry, resend rotation + rate limit, uniform 410, email render.
- **Dep:** — (B1/B2 ilə paralel mümkün, amma ardıcıl tövsiyə). **AC:** dormant cədvəl yeni sxemlə işləyir; köhnə sütunlar nullable.
- **Risk:** M1 ALTER — pre-check 0 sətir.

### B4 — Complex membership + complex device mode
- **Məqsəd:** owner-siz kompleks cihazı; sakin üzvlüyü.
- **Backend:** `ComplexMembershipService`, `RosterService::addMember` (complex budağı), `SubscriptionStatusQuery`, `DevicePolicy`, `ComplexPolicy`, `ComplexDeviceQuery`.
- **DB:** M2, M4, M6. **API:** yoxdur (B7-də açılır). **Test:** private regression (tam), complex roster, status query budaqları.
- **Dep:** B2. **AC:** mövcud cihaz testləri yaşıl; complex cihaza owner-siz `role=user` əlavə olunur.
- **Risk:** `DeviceNotAssignedException` davranışının yalnız complex mode-da dəyişməsi.

### B5 — Komendant identity + Komendant API
- **Məqsəd:** mobil Komendant səlahiyyəti.
- **Backend:** `KomendantContext`, `EnsureKomendant`, Komendant controller-ləri, `/v1/me` rolları, admin link endpoint-i, permission seed.
- **DB:** M7, M12 (permission-lar).
- **API:** `/v1/komendant/*`, `/admin/v1/admins/{id}/mobile-user`.
- **Test:** scope 404, linked/unlinked/suspended admin, pulsuz abunəlik yolunun olmaması, resident removal → revoke + abunəlik `cancelled` + refund yoxdur + audit + period-lar qorunur; kodda demo email/ID literal-ı yoxdur (grep testi).
- **Backend əlavə:** `CancelOnAccessRemoval` (B8 ilə paylaşılır).
- **Dep:** B2 (reopen/cancel), B3, B4. **AC:** Komendant yalnız öz kompleksi; dəvət yaradır (email queue); çıxarma ləğvi audit-də görünür.
- **Risk:** demo hesab saxlanılır (BR-21) — prod testi üçün onun mobil user-ə link edilməsi prod data dəyişikliyidir → yalnız ayrıca GO ilə, deploy-dan sonra.

### B6 — Invitation acceptance & account activation
- **Məqsəd:** linkdən aktiv hesaba.
- **Backend:** `ClaimInvitation`, register/verify hook, accept/decline controller, web landing, `.well-known` faylları (nginx).
- **DB:** yoxdur. **API:** `/v1/invites/*`, register/verify additiv.
- **Test:** yeni user claim, mövcud user accept, email mismatch, expired, ikiqat claim race.
- **Dep:** B3, B5 (complex), B4. **AC:** linkə klik → qeydiyyat → `complex_members` aktiv.
- **Risk:** R-DOM-01 — telefon məcburi saxlanılır.

### B7 — Resident complex browse + subscribe + payer rule
- **Məqsəd:** sakin cihaz seçir və fake ödəyir.
- **Backend:** complex controller-ləri, `StartDeviceSubscription`, `SubscriptionPaymentAuthorizer`, `OrderService::create` payer yoxlaması.
- **DB:** yoxdur. **API:** `/v1/complexes/*`, subscribe; orders reuse.
- **Test:** üzv/qeyri-üzv, idempotent subscribe, payer qaydası, paid → `canOpen=true`, failed → retry.
- **Dep:** B1, B2, B4, B6. **AC:** E2E (API səviyyəsində) dəvət → abunə → fake paid → açma icazəsi.
- **Risk:** abandoned intent zombi roster → sweep (B7 daxilində).
- **B7 implementasiya qeydləri (2026-10-01):** (1) **Complex cihazda GSM whitelist abunəliyə bağlıdır (FINAL — istifadəçi təsdiqi 2026-10-01; reconcile idempotentdir, hər add/remove `whitelist_changes` outbox-unda `device_user_id` ilə izlənir)** — roster sətri tək başına zəng-ilə-açma vermir; aktiv abunəlik → whitelist add, expired/cancelled/refunded → remove; admin resync də yalnız aktiv abunəliyi olanları əlavə edir. Private cihazların roster-əsaslı whitelist-i dəyişmir. (2) Payer qaydası §4.3-ə **private cihaz sahibi** də daxildir (mövcud single-owner modelin davamı); complex cihazda yalnız beneficiary və ya ailə başçısı. (3) Eyni abunəlik üçün başqa payer-in açıq checkout-u varsa yeni sifariş 422 ilə rədd olunur (başqasının checkout URL-i heç vaxt qaytarılmır). (4) Sweep: 24 saat (`subscriptions.abandoned_intent_hours`) ödənilməmiş, in-flight sifarişi olmayan, heç ödəniş tarixçəsi olmayan self-intent → abunəlik `cancelled` + roster revoke + audit.

### B8 — Family links + family invitation + payment
- **Məqsəd:** BR-1..4.
- **Backend:** `FamilyService`, family controller-ləri, `devices/{id}/invitations`, policy-lər.
- **DB:** M3. **API:** `/v1/family/*`, `/v1/devices/{id}/invitations`.
- **Backend əlavə:** `VisitorLinkController` family restriction (BR-19).
- **Test:** head pays / member pays / unrelated user pays (rədd); head + 5 üzv = 6 abunəlik = 72 AZN/ay; üzv invite edə bilmir; **üzv visitor link yarada bilmir (403), legacy roster user yarada bilir**; removal → cancel + refund yox + audit; re-add → reopen; complex member olmayan üzv digər cihazları görmür.
- **Dep:** B2, B5 (`CancelOnAccessRemoval`), B6, B7. **AC:** üç qat (link/giriş/abunəlik) müstəqil test olunur; visitor qadağası server-side-dır.
- **Risk:** private owner + complex resident başçı semantikasının eyni servisdə olması — policy testləri ilə örtülür.
- **B8 implementasiya qeydləri (2026-10-01):** (1) Bir dəvət = **bir cihaz** (`invitations.device_id`; əlavə sütun yoxdur). Aktiv ailə üzvünə başqa cihaz eyni endpoint ilə **birbaşa** verilir (əlaqə artıq qəbul olunub). (2) Əlavə endpoint-lər: `POST /v1/family/invitations/{id}/resend|revoke` (yalnız başçının öz dəvətləri). (3) Payer qaydası: ailə başçısı yalnız **aktiv** `family_links` sətri ilə; beneficiary olmayan heç kim revoke olunmuş roster sətri üçün ödəyə bilməz. (4) `device_users.family_link_id` FK-sız qalır (B4); cihazsız köhnə `family_member` dəvəti `invitation_kind_unsupported` ilə toxunulmaz qalır.

### B9 — Registration applications (Physical / Legal)
- **Məqsəd:** BR-5..7.
- **Backend:** `ApplicationService`, `ApproveLegalEntityApplication`, `RejectApplication`, request validation (VÖEN, lat/lng).
- **DB:** M9, M10, M11.
- **API:** `/v1/applications/*`, `/admin/v1/applications/*`.
- **Test:** iki modelin ayrılığı, təsdiq → kompleks (lat/lng), rədd email, legacy user (`account_type=NULL`) bloklanmır.
- **Dep:** B4 (complex lokasiya). **AC:** təsdiq tək transaction-da kompleks yaradır.
- **Risk:** təkrar müraciət (eyni VÖEN) — unikal pending yoxlaması.
- **B9 implementasiya qeydləri (2026-10-01):** (1) `account_type` register-də opsional; NULL (legacy) user ilk müraciəti ilə tiplənir, tiplənmiş user yalnız öz tipində müraciət edir (409 `account_type_mismatch`). (2) Fiziki: user başına **bir açıq** müraciət; Hüquqi: VÖEN başına **bir pending**. (3) User öz müraciətini yalnız ilkin statusda (`new` / `pending`) `PUT /v1/applications/{individual|legal}/{id}` ilə dəyişə bilər. (4) Lokasiya: OSM pin → `latitude/longitude` DECIMAL(10,7), server-side xidmət zonası (default Azərbaycan bbox, `config/domain/applications.php`); server heç bir map provider-ə müraciət etmir. (5) Yeni permission-lar `applications.view` / `applications.manage` (operator: hər ikisi, support: view); hüquqi təsdiq əlavə olaraq `complexes.manage` tələb edir. Prod-da RBAC seed (M12) lazımdır. (6) Təsdiq Komendant / üzvlük / cihaz yaratmır — admin B5 linki və cihaz bağlanması ilə ayrıca edir.

### B10 — Notification template editor (backend)
- **Backend:** `NotificationTemplateAdminController`, validator, preview; seed (yeni şablonlar + çatışmayan locale-lər).
- **DB:** M12 (data). **API:** `/admin/v1/notification-templates*`.
- **Test:** permission, placeholder whitelist, audit, preview render.
- **Dep:** — (müstəqil). **AC:** subject/body az/en/ru redaktə olunur, dispatcher yeni mətni istifadə edir.
- **Risk:** body-də naməlum placeholder → 422.

### B11 — Admin panel: devices/complex/Komendant/applications/users/invitations
- **Admin-ui:** cihaz forması (mode, kompleks, **satış qiyməti — yalnız qeyd, "ödəniş app xaricində" qeydi ilə**), kompleks detalı (cihazlar, sakinlər, dəvətlər, OSM/Leaflet), Komendant mobil link, Müraciətlər (2 tab), İstifadəçilər səhifəsi, order detalında "TEST ÖDƏNİŞ" badge, abunəlik detalında "legacy comp" badge + ləğv səbəbi; `PERM`, `navItems`, API hook-ları.
- **Test:** `npm run lint` + `build`; manual RBAC ssenariləri (`ADMIN_TEST_SCENARIOS.md` genişlənir).
- **Dep:** B2, B4, B5, B9. **AC:** admin Hüquqi müraciəti təsdiqləyir → Komendant link edir → cihaz bağlayır + qiymət qoyur.
- **Risk:** Leaflet tile provider siyasəti (OSM usage policy).

### B12 — Admin panel: notification editor UI
- **Admin-ui:** Bildirişlər → Şablonlar tabı, redaktə, preview.
- **Dep:** B10. **AC:** dəyişiklik saxlanır, audit görünür.

### B13 — Mobile foundation: deep links + roles + payments feature
- **Mobile:** `app_links`, Android intent-filter/iOS Associated Domains, router route-ları, session rolları, `payments/` (CheckoutScreen WebView + result + polling).
- **Test:** deep link parser, payment state machine, `flutter analyze`, `flutter test`.
- **Dep:** B1, B6 (well-known). **AC:** fake checkout app-dən açılır və nəticə app-ə qayıdır.
- **Risk:** iOS universal link doğrulaması (TestFlight build lazımdır).

### B14 — Mobile registration (type + OSM picker + applications)
- **Mobile:** RegisterType, Physical/Legal ekranları, `OsmLocationPicker`, ApplicationStatus.
- **Dep:** B9, B13. **AC:** iki axın ayrıca işləyir; legacy userlər təsirlənmir.
- **Risk:** location permission UX (Android/iOS).

### B15 — Mobile Komendant UI
- **Mobile:** Komendant ekranları (home, invite, invitations, residents).
- **Dep:** B5, B13. **AC:** Komendant dəvət göndərir, statusları görür, resend/revoke/çıxarma işləyir.

### B16 — Mobile resident complex → device → subscribe → active
- **Mobile:** InviteLanding, ComplexHome, ComplexDeviceDetail, checkout integration.
- **Dep:** B6, B7, B13. **AC:** tam E2E: email linki → hesab → cihaz → fake ödəniş → mövcud "Cihazlar" tab-ında aç.

### B17 — Mobile family UI
- **Mobile:** FamilyMembers, FamilyInvite, FamilyPayments.
- **Dep:** B8, B13, B16. **AC:** başçı üzvü dəvət edir və onun üçün ödəyir; üzv yalnız açma görür.

### B18 — Hardening, docs, release
- OpenAPI/Postman/Bruno; tam regression; deploy runbook; prod Komendant E2E (demo hesabla, user-in verdiyi credential-larla, hər addım ayrıca GO); APK debug + (ayrıca GO) TestFlight. **Demo data cleanup bu batch-ə daxil DEYİL** — ayrıca post-release task.
- **AC:** §26 + §27 bütün bəndləri.
- **B18 implementasiya qeydləri (2026-10-02):** final vəziyyət, deploy runbook, fake payment-in prod-da qəsdən aktiv olması və release tələbləri → [B18_FINAL_STATE.md](B18_FINAL_STATE.md). Fake gateway production-da `PAYMENT_GATEWAY=fake` + `PAYMENT_FAKE_ENABLED` + `PAYMENT_ALLOW_FAKE_IN_PRODUCTION` ilə (kod dəyişikliyi yoxdur). nginx access-log redaction skripti `deploy/phaseK_log_redaction.sh` (tətbiqi ayrıca GO). OpenAPI additiv sinxron olunub (v1.extra.yaml).

## 24. Dependency Graph / Sequencing
```
B1 ─► B2 ─► B4 ─┬─► B5 ─► B6 ─► B7 ─► B8
B3 ─────────────┘   ▲              ▲
B2 (reopen/cancel) ─┴──────────────┘   (B5 və B8 B2-yə birbaşa asılıdır)
                           │      │
B4 ─► B9                   │      │
B10 (müstəqil) ─► B12      │      │
B2,B4,B5,B9 ─► B11         │      │
B1,B6 ─► B13 ─┬─► B14 (B9) │      │
              ├─► B15 (B5) │      │
              ├─► B16 (B7) ◄──────┘
              └─► B17 (B8, B16)
hamısı ─► B18
```
Tövsiyə olunan xətti sıra: **B1 → B2 → B3 → B4 → B5 → B6 → B7 → B8 → B9 → B10 → B11 → B12 → B13 → B14 → B15 → B16 → B17 → B18** (backend → admin → mobile; lazımsız paralellik yoxdur).

## 25. Risks & Edge Cases
| # | Hal | Plan / qərar |
|---|---|---|
| R-1 | Fake gateway prod-da | **FINAL:** iki flag + "TEST ÖDƏNİŞ" (bütün ekranlar) + audit; real pul hərəkəti yoxdur |
| R-2 | Mövcud comp abunəliyinin yenilənməsi | **FINAL (BR-17):** avtomatik dəyişiklik/charge yox; yalnız user renewal-da 12 AZN/30 gün re-snapshot |
| R-3 | Sakin kompleksdən çıxarıldıqda aktiv abunəlik | **FINAL (BR-18):** `cancelled`, refund yox (admin ayrıca), audit, tarixçə qorunur |
| R-4 | Family link silindikdə abunəlik | **FINAL:** R-3 ilə eyni |
| R-5 | Ailə üzvü visitor link | **FINAL (BR-19):** qadağan, server-side 403 |
| R-6 | Ailə üzvü başqa ailənin üzvü də olarsa | İcazəli (müstəqil link-lər); hər cihazda tək aktiv roster (mövcud unikal) |
| R-7 | Eyni email-ə iki kompleks dəvəti | Hər kompleks ayrıca; claim hər birini ayrı üzvlük edir |
| R-8 | Dəvət email-i başqa təsdiqlənmiş hesaba aiddir | Yalnız həmin hesabla accept; yeni register `alreadyRegistered` qaytarır → login yönləndirməsi |
| R-9 | Telefon artıq başqa hesabdadır | Mövcud `phoneTaken` xətası; UI izah edir |
| R-10 | Owner-li cihazın complex mode-a keçirilməsi | Aktiv roster varsa bloklanır; admin əvvəl transfer/revoke etməlidir |
| R-11 | Abandoned subscribe intent | Pending abunəlik reuse; 7 gündən sonra aktiv abunəliyi olmayan complex `device_users` sweep revoke |
| R-12 | Komendant admin hesabı suspend olunduqda | `KomendantContext` aktiv status tələb edir → mobil Komendant UI dərhal bağlanır |
| R-13 | Demo complex_manager + demo komplekslər prod-da | **FINAL (BR-21):** saxlanılır, adi hesab kimi; cleanup ayrıca post-release task |
| R-14 | Hüquqi müştəriyə cihaz satışının ödənişi | **FINAL (BR-20):** yalnız admin qeydi, ödəniş app xaricində; in-app `device_sale` gələcək feature |
| R-15 | Brevo kvotası | Rate limit + queue + "göndərilmədi" statusu |
| R-16 | Universal link doğrulaması / iOS | B13-də TestFlight ilə yoxlanış |
| R-17 | OSM tile istifadəsi siyasəti | User-Agent + attribution; trafik artsa self-hosted/tile provider |
| R-18 | `sub_additional` qiymətinin 6→12 AZN olması | **FINAL (BR-21):** config 1200; mövcud additional comp-lar snapshot (dəyişmir) |
| R-19 | **Texniki konflikt:** `subscriptions.device_user_id` UNIQUE + `createPending` mövcud sətri statusdan asılı olmayaraq qaytarır → çıxarılıb yenidən əlavə olunan user-in köhnə `cancelled` abunəliyi geri qaytarılır və ödəniş edilə bilmir | **Həll:** `ReopenSubscriptionForPayment` — eyni sətir `cancelled/expired → pending_payment`, yeni snapshot (1200/30), `subscription_periods` toxunulmur. Alternativ (tövsiyə edilmir): UNIQUE-ı partial-a çevirmək (aktiv-only) — mövcud sxemə daha invaziv |
| R-20 | **Texniki konflikt:** `RenewalService` renewal-ı mövcud snapshot (comp üçün `price_minor=0`) ilə qiymətləndirsə, legacy comp pulsuz yenilənər | **Həll:** renewal order-dən əvvəl commercial re-snapshot (B2); test ilə sübut |
| R-21 | **Texniki konflikt:** mövcud `VisitorLinkController::assertCanShare` yalnız `canOpen`-ə baxır → aktiv abunəli ailə üzvü bu gün visitor link yarada bilərdi | **Həll:** `family_link_id IS NOT NULL` → 403 (B8); legacy roster userlər dəyişmir |
| R-22 | Legacy illik comp-lar üçün xatırlatma `[7,1]`-ə enir | Data dəyişmir; yalnız d30/d15 bildirişləri dayanır — qəbul edilir |

## 26. Acceptance Criteria (ümumi)
1. Admin Hüquqi müraciəti təsdiqləyir → kompleks yaranır (OSM lokasiyası ilə).
2. Admin Komendant təyin edir və mobil hesabla link edir; cihazı kompleksə bağlayır, satış qiyməti qoyur.
3. Komendant ad/soyad/email ilə dəvət göndərir; email gəlir; link 7 gün etibarlıdır; resend limitlidir; revoke işləyir.
4. Sakin linkdən hesab aktivləşdirir, app-ə girir, yalnız öz kompleksinin cihazlarını görür.
5. Sakin cihaz seçir, 12 AZN/ay abunəliyi fake ödəyir; paid → cihaz açılır; failed/cancelled/expired düzgün göstərilir; pending bərpa olunur.
6. Sakin ailə üzvü dəvət edir; üzvün ayrıca hesabı və ayrıca 12 AZN abunəliyi olur; başçı və ya üzv ödəyə bilir; üzv başqasını idarə edə bilmir.
7. Komendant sakini kompleksdən çıxarır → giriş dərhal dayanır.
8. Komendant pulsuz abunəlik yarada bilmir (endpoint yoxdur, test sübut edir).
9. Fiziki şəxs müraciəti admin-də görünür və status pipeline işləyir; Fiziki və Hüquqi data qarışmır.
10. Admin bildiriş şablonlarının subject/body-sini az/en/ru redaktə edir; yeni mətn push/inbox-da görünür.
11. Mövcud 5 cihaz, owner-lər, roster, comp abunəliklər, açma, widget, geofence, visitor links dəyişməz işləyir.
12. `PAYMENT_GATEWAY=birpay` dəyişikliyi kod dəyişmədən real gateway-ə keçirir (test ilə sübut).
13. Komendant ayrıca auth olmadan, mövcud email-OTP ilə daxil olur; yalnız qoşulduğu `complex_manager` hesabının kompleksini görür.
14. Bütün fake ödəniş ekranlarında (checkout, return, mobil checkout/nəticə, admin order) "TEST ÖDƏNİŞ" görünür; fake yalnız feature flag aktiv olduqda mövcuddur.
15. Mövcud comp abunəliklər deploy-dan sonra **eyni** qalır (status, ends_at, qiymət, müddət); heç bir avtomatik order/charge yaranmır; user renewal-ı 12 AZN / 30 gün ilə yaranır.
16. Sakin/ailə üzvü çıxarıldıqda aktiv abunəlik `cancelled` olur, refund yaranmır, audit log-da görünür, `orders`/`subscription_periods` silinmir; refund yalnız admin əməliyyatı ilə.
17. Ailə üzvü visitor link yarada bilmir (403); legacy roster userlərin hüququ dəyişmir.
18. Head + 5 ailə üzvü = 6 ayrıca abunəlik × 12 AZN; hər biri öz order/period-u ilə.
19. Cihaz satış qiyməti yalnız admin qeydidir; heç bir mobil order məbləğinə daxil olmur; `device_sale` flow-u dəyişmir.
20. Demo Komendant hesabı, demo kompleks və cihazlar dəyişmədən qalır; kodda onlara xüsusi istinad yoxdur.

## 27. Definition of Done (hər batch)
- [ ] Scope yalnız batch fayllarıdır (git diff review).
- [ ] Yeni + mövcud backend testləri yaşıl; mobile batch-lərdə `flutter analyze` təmiz + `flutter test` yaşıl; admin batch-lərdə lint + build təmiz.
- [ ] Migration `up/down` lokal sınanıb; backfill idempotentdir; prod pre-flight sənədləşdirilib.
- [ ] Authorization testləri (mənfi ssenarilər daxil) mövcuddur.
- [ ] OpenAPI və l10n (az/en/ru) yenilənib.
- [ ] Legacy davranış regression testi keçir.
- [ ] Audit log yazılır (admin/Komendant mutasiyaları).
- [ ] Commit / push / deploy — yalnız ayrıca GO ilə.
