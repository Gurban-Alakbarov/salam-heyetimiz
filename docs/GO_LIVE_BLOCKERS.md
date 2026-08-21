# Go-Live Blockers — Salam Həyətimiz

**Date:** 2026-06-21
**Basis:** `PRODUCTION_GAP_ANALYSIS.md` (verified live-server + codebase audit).
**Severity:** **Critical** = product cannot launch / no revenue or usage · **High** = launch is unsafe or unobservable · **Medium** = feature/operational completeness · **Low** = polish.
**Estimates:** engineering hours for 1 senior dev + AI pair (excludes external lead time — SMS/bank account approvals, device procurement). Ranges reflect unknowns.

---

## 🔴 CRITICAL

| ID | Blocker | Why it blocks launch | Est. hours | Depends on |
|---|---|---|---|---|
| **C1** | **SMS provider (real)** — `SMS_PROVIDER=fake`; OTP & SMS-fallback are in-memory fakes | No real user can receive an OTP → cannot register or log in → **product is unusable** | **8–12** | AZ SMS account/creds (external) |
| **C2** | **Kapital Bank payments** — `KAPITAL_*` empty; gateway unproven (Phase-0 G3) | No one can buy a device/subscription → **no revenue, no activation** | **16–24** | Kapital sandbox→prod creds (external) |
| **C3** | **HB1 — on-device open** — Traccar `custom`→`OUTPUT0=1` never run on a real UMKa 310 v2L; actuation read-back (T3) unconfirmed | The **core feature** (open the gate) is unproven; if the wire framing fails, the whole transport must pivot (GLONASSSoft API or SMS-primary) | **16–24** (eng) | 1× real UMKa 310 + SIM + Traccar routing (external) |
| **C4** | **Mobile app (Flutter)** — not started | End users open gates / pay **through the app**; without it there is no consumer product | **120–200** | C1+C2+C3 backends working |

**Critical subtotal:** ~**160–260 h** (≈ **40–60 h** for the three integrations C1–C3; the mobile app C4 is the dominant, separable workstream).

## 🟠 HIGH

| ID | Blocker | Why | Est. hours |
|---|---|---|---|
| **H1** | **Off-site backups + first restore drill** — local-only today | A server/disk loss = total data loss (payments, audit) | **6–10** |
| **H2** | **Monitoring + uptime + alerting** — none | Production is blind; outages found only via user complaints | **10–16** |
| **H3** | **Error tracking (Sentry)** — backend + frontend | No visibility into runtime exceptions / failed jobs | **4–6** |
| **H4** | **Roster module** (sub-user invite/add/remove/list, invitations) — not built | Residential complexes need multiple users per device; owner-only is a hard MVP limitation | **24–40** |
| **H5** | **SSH hardening** — non-root user + key auth, disable root/password | Root+password over the internet (fail2ban mitigates but high risk) | **2–3** |

**High subtotal:** ~**46–75 h**.

## 🟡 MEDIUM

| ID | Blocker | Why | Est. hours |
|---|---|---|---|
| **M1** | **Notifications module (FCM push + templates)** | Open confirmations / expiry / security alerts; `FCM_*` empty | **24–32** |
| **M2** | **Privacy module (GDPR export/delete/consent)** | Legal/compliance for personal data (phones, locations) | **16–24** |
| **M3** | **Admin operational endpoints + UI** (dashboard stats, customer/user mgmt, admin mgmt, audit-log viewer, settings) | Day-2 operations & support currently impossible from the panel | **24–40** |
| **M4** | **Subscription auto-renew (card tokenization)** | Recurring revenue; P2-gated, depends on Kapital tokenization | **8–12** |
| **M5** | **Reverb (real-time push)** deploy + wire | Snappier open feedback; polling fallback works so non-blocking | **8–12** |
| **M6** | **Load/latency test (Phase-0 T2)** | Validate persistent-session latency + sizing under real telemetry | **8** |
| **M7** | **Backup restore automation + verification** | Prove backups are usable (partly overlaps H1) | **3–4** |
| **M8** | **Cloudflare Access / IP allowlist** for admin + Traccar panels | Reduce exposure of admin surfaces beyond password/2FA | **2–3** |

**Medium subtotal:** ~**93–135 h**.

## 🟢 LOW

| ID | Blocker | Why | Est. hours |
|---|---|---|---|
| **L1** | Enable **HSTS** at Cloudflare | Hardening (after HTTPS soak) | **0.5** |
| **L2** | **CI/CD** pipeline (build/test/deploy automation) | Repeatable, safer deploys (manual today) | **12–16** |
| **L3** | Backend missing-token **500→401** (auth guard) | Cosmetic robustness; SPA never triggers it | **1** |
| **L4** | Runbook / docs polish | Operational clarity | **4** |

**Low subtotal:** ~**17–22 h**.

---

## Totals

| Severity | Hours |
|---|---|
| Critical (C1–C3 integrations) | 40–60 |
| Critical incl. mobile app (C4) | 160–260 |
| High | 46–75 |
| Medium | 93–135 |
| Low | 17–22 |
| **Total excl. mobile app** | **~196–292 h** |
| **Total incl. mobile app** | **~316–492 h** |

## Minimum go-live path (smallest launchable product)

To launch a usable paid product (owner-only, single-user-per-device acceptable for v1):

1. **C1 SMS** → users can log in. (8–12 h + account lead time)
2. **C3 HB1** → gates actually open; if it passes, keep Traccar; if not, fall back to SMS-primary. (16–24 h + device)
3. **C2 Kapital** → purchases work. (16–24 h + bank lead time)
4. **C4 Mobile app MVP** → end-user product exists. (120–200 h)
5. **H1 off-site backup + H2 monitoring + H5 SSH hardening** → safe, observable launch. (~18–29 h)

**Minimum go-live ≈ 178–289 engineering hours**, plus external lead times for SMS account, Kapital
onboarding, and UMKa device procurement (run these in parallel — they gate C1/C2/C3 regardless of dev time).

**Deferrable to fast-follow:** Roster (H4), Notifications (M1), Privacy (M2 — confirm legal timing), Admin ops
(M3), auto-renew (M4), Reverb (M5), CI/CD (L2).

## Sequencing notes

- C1/C2/C3 each have an **external dependency** (SMS vendor, bank, hardware) — start procurement now; they are the long poles, not the code.
- C3 (HB1) is a **decision gate**: its outcome determines whether the device transport stays Traccar-primary or pivots — do it **before** investing in the mobile app's open-gate UX.
- The mobile app (C4) can start in parallel against the deployed backend (with `FakeOtpTransport` for dev), but its launch is gated on C1–C3 being real.
