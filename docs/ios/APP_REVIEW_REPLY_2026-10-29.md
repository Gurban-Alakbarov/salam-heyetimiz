# App Review reply — Guideline 2.1 Information Needed (2026-10-29, updated 2026-10-30 for 1.0.1)

The reply goes with **version 1.0.1 (build 4)**: the App Store version 1.0 is renamed to 1.0.1 and build 4 is
attached before sending. The recording must be made with build 4 from TestFlight.

Paste the **Reply** into App Store Connect → App Review (the message thread) and attach the screen recording.
The same text (without point 1) also goes into **App Review Information → Notes**.
The demo login is the one already in **Sign-In Information** (review@sezam.salam.az + its code).

---

## Reply (English) — as sent 2026-10-30 (ASC reply box limit: 4000 characters)

Hello App Review team,

Thank you for reviewing Salam Sesame. The requested information is below; the same text is in the App Review Information Notes. Version 1.0.1 (build 4) is attached to this submission.

1. Screen recording (attached)
Recorded on a physical iPhone with the latest iOS and build 1.0.1 (4). A new user opens the invitation e-mail from her residential complex, which launches the app. She registers with the 6-digit code e-mailed to her, subscribes to the gate (15 AZN / year, test checkout) and sends an open command. Then she opens the guest-link screen and the family-members menu, and finally deletes her account (Profile > Delete account > Delete permanently).
The gate in the recording, "Salam Test barrier", is a demo controller on our test bench, not wired to a real gate. That is why it shows as Offline and the app reports "Sent". On a customer's installed controller the same command opens the gate.

2. Purpose and audience
Residents and property owners in Azerbaijan open their building's or private gate/barrier from the phone instead of remote fobs, SMS calls or a guard. The gates are fitted with our GSM barrier controllers. Users can also send guests one-time or time-limited links, add family members and manage the subscription. Complex managers control who has access. Audience: residents of residential complexes, owners of private houses / parking gates, and complex managers in Azerbaijan. It is a consumer app: anyone with a Salam-equipped gate can register.

3. How to test
- Sign in with the demo account in Sign-In Information: enter the e-mail, then the 6-digit code given there (always valid for this account).
- Devices: tap the round green button on "Salam Test barrier" to send an open command.
- Guest link: person-plus icon on the device card > one-time or time-limited > share.
- Family: Profile > Family members > invite by e-mail.
- Subscription: Home > Active subscriptions > Renew > card checkout page.
- New account: Register on the welcome screen (e-mail + e-mailed code).
- Account deletion: Profile > Delete account > Delete permanently.
The app is in Azerbaijani, English and Russian and follows the phone language. Profile > App settings > Language changes it.

4. External services
- Our backend (api.sezam.salam.az).
- Self-hosted Traccar, which talks to the barrier controllers.
- Firebase Cloud Messaging + APNs for notifications.
- Firebase Crashlytics and Analytics: no ads or tracking, and the statistics can be turned off in the app.
- Kapital Bank BirPay: card payments on a hosted checkout page.
- Brevo: e-mail sign-in codes.
- GoMap.az through our server: the map in the installation request.
- AzNav: Directions.
No third-party sign-in, ads, tracking or AI.
Payments: the subscription pays for access to a physical gate, a real-world service used outside the app. Under Guideline 3.1.5(a) it is paid by card via Kapital Bank, not In-App Purchase. While the bank certifies our integration, checkout runs in test mode, marked "TEST ÖDƏNİŞ", and no money is charged.

5. Regions
Distributed only in Azerbaijan, where our controllers are installed. All features are the same for every user.

6. Regulation / content
Not a regulated industry and no protected third-party content. We install and maintain the barrier controllers for the gate owners who order the service. There is no public user-generated content: a visitor name typed for a guest link is private to the user who created it.

Kind regards,
Salam Sesame team (support@sezam.salam.az)

---

## Screen recording — shot list (≈2–3 min, physical iPhone, latest iOS)

Turn on recording: Control Center → Screen Recording.

1. Start on the home screen and launch Salam Sesame.
2. **Register** a new account: e-mail → code from the mailbox. Use a real mailbox you can open.
3. Sign out, then **sign in with the demo account** review@sezam.salam.az.
4. **Cihazlar:** tap open on an **online** device. The button turns red, then shows "Qapı açıldı" or the result.
   Make sure "Salam Test barrier" is online during the recording and the review.
5. **Guest link:** create one and open the share sheet.
6. **Family:** Profil → Ailə üzvləri → invite.
7. **Subscription:** Ana səhifə → Aktiv abunəliklər → Yenilə → the test checkout page → back to the result.
8. Profil → Tətbiq ayarları: switch the language briefly.
9. Sign out, sign in again with the account from step 2, Profil → **Hesabı sil** → Hesabı həmişəlik sil.


---

## What's New in 1.0.1 (App Store)

**en-US**
- New device card: a round Open button that turns red while the command is sent
- Simpler guest invite and Directions buttons
- GoMap map in the installation request: address search, pin, address filled in automatically
- Subscription is 15 AZN / year, shown on the Renew button
- The app opens in your phone's language (Azerbaijani, English, Russian)
- Crash reports for a more stable app; usage statistics can be turned off in Settings → Privacy

**ru**
- Новая карточка: круглая кнопка «Открыть», красная во время отправки команды
- Упрощены кнопки «Пригласить» и «Маршрут»
- Карта GoMap в заявке: поиск адреса, метка, адрес заполняется сам
- Подписка 15 AZN / год, цена на кнопке «Продлить»
- Приложение открывается на языке телефона (азербайджанский, английский, русский)
- Отчёты о сбоях; статистику можно отключить в Настройки → Конфиденциальность
