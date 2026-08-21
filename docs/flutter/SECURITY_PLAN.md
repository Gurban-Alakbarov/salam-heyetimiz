# SECURITY PLAN

> Planning only. Client-side security for a GSM access-control app. Phasing: **P1 = launch** · **P2 = before/short-after GA** · **Future = when the matching backend/feature ships**.

---

## 1. Token & session security — P1

- **Storage:** access + refresh tokens **only** in `flutter_secure_storage` (iOS Keychain / Android Keystore-backed). Never in prefs, Hive, logs, or analytics.
- **Access token:** RS256, 15-min TTL, held in memory + secure storage; attached by the AuthInterceptor.
- **Refresh:** opaque, 60-day, rotating. **Single-flight** refresh on 401 (one shared refresh future); the rotated pair replaces the old. The backend does **reuse-detection** — if a stolen/old refresh is replayed, the server revokes the whole device family; the client treats a refresh failure as "session compromised/expired" → wipe + → Welcome.
- **Logout:** call `POST /v1/auth/logout` (revokes the install's tokens) → clear secure storage + caches → `authState=guest`.
- **No token in URLs / deep links / webview** (the BirPay webview gets no app token).

---

## 2. Biometric — P1 (note: app-lock + barrier gate, NOT login)

The login is **email-OTP**, not biometric. Biometric (`local_auth`) is used for:
- **App-lock (optional):** require Face/Touch ID to open the app when a valid session exists (toggle in Security). This unlocks access to the already-stored session; it is not an auth factor with the server.
- **Barrier-open gate:** the backend gates each open on a per-install biometric flag (`me/biometrics/enroll`). The app performs a local biometric check before `POST /open`, matching that policy.
Biometric failure falls back to device passcode; never to bypass. (Password-based login is a Future feature — see §7.)

---

## 3. Transport security — P1 baseline, **SSL/Certificate pinning P2**

- **P1:** HTTPS only (ATS/cleartext disabled); reject invalid certs (default).
- **P2 — Certificate / public-key pinning:** pin the API + (optionally) the BirPay host public keys via Dio's `badCertificateCallback` / a pinning package. **Requires backend coordination** (publish the cert/SPKI pins + a rotation plan, ideally 2 pins for rotation). Plan a **kill-switch / soft-fail** so a mis-rotation doesn't brick the app (report + allow, or a remote pin update). Defer to P2 because it needs the ops cert-rotation procedure agreed.

---

## 4. Platform hardening — P2 / optional

| Control | Phase | Note |
|---|---|---|
| **Screenshot / screen-recording protection** | P2 | `FLAG_SECURE` (Android) + secure overlay (iOS) on sensitive screens (OTP, payment webview, profile). |
| **Root / jailbreak detection** | P2 (optional) | Warn / restrict barrier-open on compromised devices (e.g. `freerasp`/`flutter_jailbreak_detection`). Soft-warn, don't hard-block (false positives). |
| **Debug / emulator / Frida detection** | Future/optional | Detect debuggable/hooked runtime; raise risk signal. Low priority for v1. |
| **Code obfuscation + R8/ProGuard** | P1 | `flutter build --obfuscate --split-debug-info` for release; minify Android. Cheap, do at P1. |
| **Tamper/integrity (Play Integrity / DeviceCheck)** | Future | Server-side attestation when the backend supports it. |

---

## 5. Data-at-rest & privacy — P1

- Only non-sensitive data in Hive (device/profile cache); tokens in secure storage; clear on logout.
- No PII in logs/analytics; OTP codes never persisted or logged.
- Redacted logging in dev/qa only; **no network logging in prod**.
- Respect the backend's masked outputs (phone/email already masked server-side where shown).

---

## 6. Input & client-side validation — P1

- Mirror the backend rules client-side for UX (phone `+994XXXXXXXXX`, email format, OTP 6 digits) — but treat the **server as authoritative** (always handle 422).
- Rate-limit-aware UI (resend cooldown, open cooldown) to reduce 429s.
- The BirPay checkout runs in an isolated webview; the app never reads card fields; it only watches for the `payments/return` URL then rechecks.

---

## 7. Future-aligned: password & MFA

The backend reserves password login (`has_password`, `users.password`) for a future Security feature. The app is built **forward-compatible**:
- The login endpoint is designed to accept an optional `password` later; the client's auth flow can add an "Email + Password" path **without restructuring** (a new screen + the same token-issuing result).
- Set-Password / Change-Password screens are stubbed (hidden behind `has_password`/a flag) until the backend ships them → then they light up.

---

## 8. Phasing summary

| Control | P1 | P2 | Future |
|---|---|---|---|
| Secure token storage | ✅ | | |
| Single-flight refresh + session wipe on compromise | ✅ | | |
| Biometric app-lock + barrier gate | ✅ | | |
| HTTPS-only, code obfuscation | ✅ | | |
| Certificate / SPKI pinning (+ rotation) | | ✅ | |
| Screenshot protection on sensitive screens | | ✅ | |
| Root/jailbreak soft-detection | | ✅ | |
| Debug/Frida detection, Play Integrity | | | ✅ |
| Password login + MFA | | | ✅ (when backend ships) |

**No client security control depends on a backend change** except pinning (cert publication) and password/attestation (future features) — all flagged accordingly.
