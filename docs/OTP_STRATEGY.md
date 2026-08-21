# OTP Strategy — Salam Həyətimiz

**Date:** 2026-06-21
**Scope:** current OTP design + an evaluation of **adding Email OTP** alongside SMS OTP. Audit/planning only.

---

## 1. Current OTP design (verified in code)

| Aspect | Value | Source |
|---|---|---|
| Identity | **phone-first** — `POST /v1/auth/otp/request {phone}`; unknown phone auto-registers (R-DOM-01) | routes + Auth module |
| Code | 6 digits | `config/domain/auth.php` `otp.length=6` |
| TTL | 120 s | `otp.ttl_seconds=120` |
| Resend cooldown | 30 s | `otp.resend_seconds=30` |
| Max attempts | 5 | `otp.max_attempts=5` |
| Storage | `otps` table, **SHA-256 hashed**, superseded on re-request | `OtpService` |
| Transport | `OtpTransport` interface → `SmsOtpTransport` (real) / `FakeOtpTransport` (test/`fake`) | Auth/Adapters |
| Rate limiting | route limiters (R-SEC-16) + verify-outcome-then-throw so attempts persist | `OtpService`, Pest |
| **Channel** | **SMS only** | — |
| **Production status** | ❌ `SMS_PROVIDER=fake` → codes never delivered → **login broken** | `.env` |

**Strength:** the transport is a clean, channel-agnostic interface (`OtpTransport::send(phone, code, locale)`),
so a second channel can be added without touching `OtpService`. **Weakness:** no real provider is wired, and
the channel is hard-bound to phone.

## 2. Email OTP — feasibility

- **Users have an `email` column** (nullable, unique, `email_verified_at`) — so storing/using email is
  technically possible today.
- **But** users register **by phone**; `email` is optional and almost always empty for residents. There is
  no email-collection step in the mobile onboarding, and `MAIL_MAILER=log` (no mail transport configured).
- **Admins** use email + password + **TOTP** (not OTP), so admin auth does not need email OTP.

## 3. Should we add Email OTP?

| Driver | Assessment |
|---|---|
| Reach the user | ❌ Most residents have no email on file → email OTP can't reach them |
| Cost saving vs SMS | ✅ Email is ~free, but only helps the minority with a verified email |
| SMS-outage resilience | ⚠️ A fallback channel is valuable, but Viber/WhatsApp (via Infobip/Twilio) reaches AZ users far better than email |
| Compliance/deliverability | ⚠️ Email OTP has spam/inbox-delay risk; worse OTP latency than SMS |
| Effort | Mail transport + email-OTP path + email collection/verification UX |

**Verdict:** **Do not prioritise Email OTP for MVP.** It does not fit the phone-first identity model and
reaches too few users to be a primary or even a useful fallback. A better resilience channel for AZ is
**Viber/WhatsApp OTP** (high penetration, supported by Infobip/Twilio, and addable via the same
`OtpTransport` interface).

## 4. Recommended OTP strategy

1. **MVP:** SMS OTP only — wire the real provider (`SMS_PROVIDER` real; see `SMS_PROVIDER_EVALUATION.md`).
   Unblocks login (C1). No new channels.
2. **Keep the channel abstraction** (`OtpTransport`) — already in place; do not couple OTP logic to SMS.
3. **Post-MVP resilience (optional):** add a **multi-channel** OTP with priority **SMS → Viber/WhatsApp**
   (via the chosen CPaaS), selected per attempt or on SMS-delivery failure. Design it as a channel registry
   behind `OtpTransport` with a per-user/per-locale preference.
4. **Email OTP (low priority, opt-in only):** only for users who explicitly add + verify an email
   (e.g. a "backup login" setting). Requires: a mail transport (`MAIL_MAILER` real, e.g. a transactional
   email provider), an email-OTP template, and an email-collection/verification flow. **Defer.**
5. **Do not adopt a managed Verify/2FA product** — `OtpService` already owns generation, hashing, expiry,
   throttling and attempt accounting; a managed product would duplicate logic and add cost.

## 5. Effort (when scheduled — not in this doc)

| Item | Effort |
|---|---|
| Wire real SMS OTP (C1) | ~8–12 h (mostly account lead time) |
| Multi-channel SMS→Viber/WhatsApp (post-MVP) | ~12–16 h |
| Email OTP (opt-in, incl. mail transport + collection UX) | ~16–24 h — **defer** |

**Recommendation:** SMS OTP for launch; treat Email OTP as a deferred, opt-in convenience, not an MVP item.
