# Google Play Console — Salam Sesame (az.salam.sezam)

Build: `salam-sesame-1.0.0-1.aab` (versionName 1.0.0, versionCode 1; next upload needs a higher versionCode —
bump `version:` in `mobile/pubspec.yaml`). Signed with the upload key in `C:\Users\User\salam-release-keys\`
(back it up). Graphics in this folder: `play-icon-512.png`, `play-feature-graphic-1024x500.png`.

## 1. Create app
- App name: **Salam Sesame** · Default language: **Azerbaijani (az-AZ)** · App · Free
- Accept the declarations (Developer Program Policies, US export laws).

## 2. Play App Signing
Keep the default (Google manages the app signing key). After the first upload copy the **App signing key
certificate SHA-256** (Setup → App signing) and send it — it goes into the server's `APP_LINKS_ANDROID_SHA256`
next to the upload key, otherwise invitation links will not open the app for users who install from Play.

## 3. Store listing (Main store listing; add en-US and ru-RU translations)

**az** — Short description (≤80):
Şlaqbaum, qaraj və giriş qapılarını telefonunuzdan açın.

**az** — Full description:
Salam Sesame şlaqbaum, qaraj qapısı və avtomatik giriş qapılarını telefonunuzdan idarə etməyə imkan verir. Fiziki pultu bir tətbiqlə əvəz edin.

• Bir toxunuşla açın — tətbiqdən və ya ana ekran vidcetindən
• Ailə üzvləri və qonaqlar — ailənizə giriş verin, qonaqlara müddətli link göndərin
• Yaşayış kompleksləri — komendant sakinləri və dəvətləri idarə edir
• Təhlükəsizlik — hər açılış qeydə alınır, girişlər icazə ilə verilir
• Yol göstər — qonaqlarınız ünvana AzNav ilə gəlsin
• Bildirişlər — abunəlik və giriş haqqında vaxtında xəbər

Cihaz quraşdırılması və hesabın cihaza bağlanması Salam Sesame komandası tərəfindən edilir. Əlaqə: support@sezam.salam.az

**en** — Short: Open barriers, garage doors and gates from your phone.
**en** — Full:
Salam Sesame lets you control barriers, garage doors and automatic gates from your phone. Replace the physical remote with one app.

• Open with one tap — from the app or the home-screen widget
• Family and guests — give your family access, send guests a time-limited link
• Residential complexes — the building manager handles residents and invitations
• Security — every opening is logged, access is granted by permission
• Directions — guests reach the address with AzNav
• Notifications — timely updates about your subscription and access

Devices are installed and linked to your account by the Salam Sesame team. Contact: support@sezam.salam.az

**ru** — Short: Открывайте шлагбаумы, ворота и гаражи с телефона.
**ru** — Full:
Salam Sesame позволяет управлять шлагбаумами, гаражными и автоматическими воротами с телефона. Замените физический пульт одним приложением.

• Открывайте одним касанием — в приложении или виджетом на главном экране
• Семья и гости — дайте доступ семье, отправьте гостю временную ссылку
• Жилые комплексы — комендант управляет жителями и приглашениями
• Безопасность — каждое открытие записывается, доступ выдаётся по разрешению
• Маршрут — гости доберутся до адреса через AzNav
• Уведомления — своевременные сообщения о подписке и доступе

Установку устройства и привязку аккаунта выполняет команда Salam Sesame. Контакты: support@sezam.salam.az

- App icon: `play-icon-512.png` · Feature graphic: `play-feature-graphic-1024x500.png`
- Phone screenshots: 2–8, from the phone (login, device list, open button, family/guest link).
- Category: **House & Home** · Email: support@sezam.salam.az · Website: https://sezam.salam.az

## 4. App content
- **Privacy policy:** https://sezam.salam.az/privacy
- **App access:** "All or some functionality is restricted" → add instructions with the review account
  from `C:\Users\User\salam-release-keys\play-review-account.txt` (email + fixed code). Note for the reviewer:
  "The account has no gate assigned; gate devices are installed on site by our staff."
- **Ads:** No ads.
- **Content rating:** questionnaire → category "Utility, Productivity, Communication or Other"; answer No to
  violence, sexual content, drugs, gambling, user-to-user chat; "shares user location with other users": No.
- **Target audience:** 18+ only.
- **News app:** No. **Government app:** No. **Financial features:** none (payments go to the bank page).
- **Health:** No.
- **Data safety:**
  - Collects data: Yes. Encrypted in transit: Yes. Users can request deletion: Yes
    (in app: Profile → Delete account; web: https://sezam.salam.az/account).
  - Personal info → **Name, Email address, Phone number**: collected, not shared; required; purposes
    App functionality, Account management.
  - Personal info → **Address** (location picked on the map in a device application): collected, optional,
    App functionality.
  - Location → **Precise location**: collected, optional, App functionality (only when opening a gate that
    has a distance limit; used for the check, not shared).
  - App activity → **Other actions** (gate openings, invitations): collected, App functionality, Fraud
    prevention/security.
  - Device or other IDs → **Device or other IDs** (push token, install id): collected, App functionality.
  - Financial info: not collected by the app (card entry happens on the bank's page).
- **Account deletion:** URL https://sezam.salam.az/account
- **Location permission:** foreground only — no background-location declaration needed.

## 5. Release
Production → Create new release → upload `salam-sesame-1.0.0-1.aab` → release name `1.0.0 (1)` →
notes "İlk buraxılış." → Countries: Azerbaijan (or all) → Review → Start rollout. First review: ~1–7 days.
