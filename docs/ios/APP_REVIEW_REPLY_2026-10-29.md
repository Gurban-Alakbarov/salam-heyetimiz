# App Review reply — Guideline 2.1 Information Needed (2026-10-29, updated 2026-10-30 for 1.0.1)

The reply goes with **version 1.0.1 (build 4)**: the App Store version 1.0 is renamed to 1.0.1 and build 4 is
attached before sending. The recording must be made with build 4 from TestFlight.

Paste the **Reply** into App Store Connect → App Review (the message thread) and attach the screen recording.
The same text (without point 1) also goes into **App Review Information → Notes**.
The demo login is the one already in **Sign-In Information** (review@sezam.salam.az + its code).

---

## Reply (English)

Hello App Review team,

Thank you for reviewing Salam Sesame. Please find the requested information below.

**1. Screen recording**
Attached: a recording on a physical iPhone running the latest iOS. It starts with launching the app and shows
registration, login, opening a gate, sharing a guest link, inviting a family member, subscribing (checkout), and
account deletion.

**2. Purpose and target audience**
Salam Sesame lets residents and property owners in Azerbaijan open their building's or private gate/barrier from
their phone. The gates are fitted with our GSM barrier controllers. Instead of remote fobs, SMS calls or a guard,
the user taps "Open" in the app.
Users can also:
- send a guest a time-limited or one-time link to open the gate;
- add family members;
- manage their subscription.
Residential complexes use it so their managers (komendants) can control who has access.
Target audience: residents of residential complexes, owners of private houses / parking gates, and complex
managers in Azerbaijan.

**3. How to access the main features**
1. Launch the app and sign in with the demo account in Sign-In Information. Enter the e-mail; the app asks for a
   6-digit code, and for this review account the code given in Sign-In Information always works (no e-mail is
   needed).
2. **Cihazlar (Devices)** lists the account's gate "Salam Test barrier". Tap the round green button to send an open
   command. The button turns red while the command travels to the barrier controller, then the result is shown.
   This is a real test controller in our office, so you will see the real command status.
3. Guest link: tap the person-plus icon on the device card → choose one-time or time-limited → share the link.
4. Family: Profile → Ailə üzvləri (Family), or the ⋮ menu on the device card → Ailə üzvləri → invite by e-mail.
5. Subscription: Ana səhifə (Home) → Aktiv abunəliklər (Active subscriptions) → Yenilə (Renew), which opens the
   card checkout page.
6. New account: on the welcome screen choose Register, enter an e-mail and the 6-digit code that is e-mailed.
7. Account deletion: Profil (Profile) → Hesabı sil (Delete account) → Hesabı həmişəlik sil (confirm). The account is
   erased.

The app is available in Azerbaijani, English and Russian. It opens in the phone's language (Azerbaijani if the
phone uses another language); Profile → Tətbiq ayarları / App settings → Language changes it.

**4. External services**
- Our own backend (api.sezam.salam.az): accounts, access rules, gate commands.
- Traccar, self-hosted on our servers: communicates with the GSM barrier controllers.
- Firebase Cloud Messaging with Apple Push Notification service: notifications such as "your guest opened the gate"
  or a subscription reminder.
- Firebase Crashlytics and Firebase Analytics: crash reports and usage statistics, not used for advertising or
  tracking; usage statistics can be turned off in Profile → App settings → Privacy.
- Kapital Bank BirPay: card payment gateway (hosted checkout page) for subscriptions.
- Brevo: sends the e-mail one-time sign-in codes.
- GoMap.az (through our own server): the map, address search and the address under a picked point in the
  installation request form. Only the searched address and the picked point are sent, never the user's details.
- AzNav: the "Directions" button opens the AzNav navigation app or its App Store page.

There is no third-party sign-in, no advertising, no tracking and no AI service.

**Payments.** The subscription pays for access to a physical gate/barrier, a real-world service used outside the
app. Under Guideline 3.1.5(a) it is paid by card through Kapital Bank and not through In-App Purchase. While the
bank completes its certification of our integration, the checkout runs in the bank's test mode. It is marked
"TEST ÖDƏNİŞ" and no real money is charged.

**5. Regional differences**
The app is distributed only in Azerbaijan, where our gate controllers are installed. All features work the same for
every user.

**6. Regulated industry / third-party material**
Salam Sesame is not in a regulated industry and contains no protected third-party content. The barrier controllers
are installed and maintained by us for the gate owners who order the service.
There is no public user-generated content. A visitor name typed for a guest link is private to the user who
created it. The app is a consumer app: any resident or owner in Azerbaijan with a Salam-equipped gate can register.
It is not limited to one company's employees.

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
