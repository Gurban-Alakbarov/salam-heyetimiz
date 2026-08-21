# SMS Provider Evaluation — Salam Həyətimiz

**Date:** 2026-06-21
**Purpose:** select a fundable Azerbaijan SMS provider for OTP + transactional SMS (invites, expiry
reminders, device SMS-fallback open). Audit + recommendation only — no code.
**Current state:** `SMS_PROVIDER=fake` in production (no real SMS sent → user OTP login is non-functional —
see `GO_LIVE_BLOCKERS.md` C1). The code is provider-agnostic behind `OtpTransport` / `DeviceSmsGateway`, so
only credentials + a small field-mapping confirmation are needed.

---

## 1. What the platform needs from SMS

| Use | Volume profile | Latency need | Channel |
|---|---|---|---|
| **OTP login** (6-digit, 120 s TTL) | spiky, per login | **p95 ≤ 15 s** | SMS (primary) |
| Roster invites | low | minutes ok | SMS |
| Subscription expiry reminders | daily batch | minutes ok | SMS |
| **Device SMS-fallback open** (`OUTPUT0=1` to device SIM) | rare (fallback only) | seconds | SMS to device SIM |
| Inbound SMS reply correlation (R-GSM-10) | rare | — | inbound (HMAC) |

Requirements: AZ mobile delivery to **Azercell / Bakcell / Nar**, a registered **alphanumeric sender ID**
(`SalamHayet`), 24/7 transactional (OTP) delivery, AZN funding ideally, an HTTP API, and a delivery-status
callback. The app already supports a **primary + `fallback_provider`** split (`config/integrations/sms.php`).

## 2. Candidate providers (Azerbaijan-capable)

| Provider | Type | AZ routing | Sender ID | Billing | API | OTP helper | Notes |
|---|---|---|---|---|---|---|---|
| **Lsim.az (LSIM)** | Local aggregator | ✅ direct to all 3 operators | ✅ via operators | **AZN, prepaid** | HTTP/SMPP | no (plain send) | Most common AZ business choice; cheapest; basic docs/support |
| **Atlas / ATL Telecom (sms.atl.az)** | Local aggregator | ✅ | ✅ | AZN | HTTP | no | Local alternative to LSIM |
| **Poctgon / Sims.az / Ucuzsms** | Local resellers | ✅ | ✅ (resold) | AZN | HTTP | no | Smaller; verify deliverability + uptime |
| **Infobip** | Global CPaaS | ✅ (local interconnects) | ✅ (registration) | USD/EUR, contract | REST, robust | **2FA API** + Viber/WhatsApp fallback | Strong OTP product, dashboards, SLAs; pricier |
| **Twilio** | Global CPaaS | ✅ (partner routes) | ✅ (AZ alphanumeric pre-reg) | USD, card | REST, best DX | **Verify API** (managed OTP) | Best developer experience; A2P/sender registration overhead; higher per-SMS |
| **Vonage (Nexmo)** | Global CPaaS | ✅ | ✅ | USD | REST | Verify | Similar to Twilio |
| **Direct operator** (Azercell/Bakcell/Nar corporate SMS) | Operator | ✅ best | ✅ | AZN, B2B contract | per-operator | no | Best price/deliverability at scale; heavy onboarding, 3 integrations |

## 3. Evaluation against our needs

- **Deliverability to AZ numbers:** local aggregators (LSIM/ATL) and direct-operator deals route on-net →
  fastest, cheapest, best OTP delivery. Global CPaaS (Infobip/Twilio) deliver via interconnect — reliable
  but slightly higher latency/cost.
- **OTP managed vs plain send:** our backend already generates, throttles, hashes and expires OTPs
  (`OtpService`: 6-digit, 120 s, 30 s resend, 5 attempts). We therefore need a **plain SMS-send API**, not a
  managed Verify/2FA product (which would duplicate our logic + cost more). Verify is only worth it if we
  later want carrier-grade fraud/throttling offload.
- **Sender ID / regulatory (AZ):** alphanumeric `SalamHayet` must be registered with each operator (handled
  by the aggregator). Transactional OTP is permitted 24/7; marketing has time windows — keep OTP/transactional
  separate from any future marketing sends.
- **Funding:** LSIM/ATL accept AZN prepaid (bank transfer) — fastest to start. Infobip/Twilio bill in USD by
  card/contract — more procurement overhead.
- **Fallback channel:** Infobip/Twilio add Viber/WhatsApp OTP (high AZ penetration, cheaper than SMS) — useful
  later; our `OtpTransport` interface can add channels without rework.

## 4. Recommendation

**Primary: LSIM (lsim.az)** — local, AZN prepaid, direct on-net routing, simplest/fastest to fund and
register `SalamHayet`. Best cost + OTP latency for AZ.

**International fallback: Infobip** (preferred) **or Twilio** — wire as `SMS_FALLBACK_PROVIDER` for
resilience if the local route degrades, and as the future multi-channel (Viber/WhatsApp) path.

**Rationale:** lowest cost + best AZ deliverability locally, with a global safety net — matching the code's
existing primary/fallback design and avoiding a managed-OTP product we don't need.

> **Decision needed from the business:** open + fund an **LSIM** account, register sender `SalamHayet` on all
> three operators, and (optionally) open an **Infobip** account for fallback. These are procurement actions
> with lead time — start now; they gate go-live regardless of engineering time.

## 5. What engineering needs once an account exists (no code in this doc)

1. Confirm the provider's **send-API contract** (URL, auth — bearer/basic/key, field names `to`/`message`/`sender`,
   success/failure shape) and map it in `SmsOtpTransport` + `HttpDeviceSmsGateway` (currently generic:
   `POST base_url {sender,to,message}` with bearer token).
2. Confirm **delivery-status callback** format (for observability).
3. Confirm **inbound SMS** format + signing (for R-GSM-10 device reply correlation; currently `inbound_hmac_secret`).
4. Set `.env`: `SMS_PROVIDER=<lsim|http>`, `SMS_BASE_URL`, `SMS_API_KEY`, `SMS_SENDER_ID=SalamHayet`,
   `SMS_INBOUND_HMAC_SECRET`, `SMS_FALLBACK_PROVIDER`.
5. **G2 test:** OTP delivery p95 ≤ 15 s across all three operators; SMS-fallback open to a device SIM.

**Engineering effort after account is live:** ~**8–12 h** (field mapping + status callback + tests). The
long pole is the **account/sender-ID registration lead time** (business/procurement), not code.
