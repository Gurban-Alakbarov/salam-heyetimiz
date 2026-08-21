# RBAC — Demo Admin Accounts

**Date:** 2026-06-27 · **Status:** seeded on production (`admin.salamheyetimiz.com`) and immediately usable.

> ⚠️ **SECURITY:** these are demo accounts with documented passwords + a shared 2FA secret. **Disable or
> rotate them before real production launch** — especially the demo super admin. Deactivate via the Admins
> page or re-seed with new values. The original production super admin (`admin@salamheyetimiz.com`) is
> unchanged.

## Login URL

`https://admin.salamheyetimiz.com/login`

Login is **two-phase**: (1) email + password → (2) 2FA code. After login each role sees a **different
sidebar**, and unauthorized pages/buttons are hidden (and return 403 if reached directly).

## Accounts (one per role)

| Role | Email | Password | Scope |
|---|---|---|---|
| **Super Admin** | `super@salamheyetimiz.com` | `Sup3r!Salam-RBAC#2026` | all |
| **Technical** | `technical@salamheyetimiz.com` | `Tech!Salam-RBAC#2026` | all complexes |
| **Operator** | `operator@salamheyetimiz.com` | `Oper!Salam-RBAC#2026` | all complexes |
| **Finance** | `finance@salamheyetimiz.com` | `Fin!Salam-RBAC#2026x` | all complexes |
| **Support** | `support@salamheyetimiz.com` | `Supp!Salam-RBAC#2026` | all complexes |
| **Complex Manager** | `manager@salamheyetimiz.com` | `Mgr!Salam-RBAC#2026x` | **Salam Həyət A only** |

## 2FA (same for every demo account)

Admin login requires a 2FA code. Two ways to get one:

1. **Authenticator app (reusable):** add this TOTP secret to Google Authenticator / Authy and enter the
   current 6-digit code:
   ```
   JBSWY3DPEHPK3PXP
   ```
2. **Recovery code (no app needed, single-use each):** enter one of these on the 2FA screen (each works once):
   ```
   SALAM-RBAC-0001   SALAM-RBAC-0002   SALAM-RBAC-0003   SALAM-RBAC-0004
   SALAM-RBAC-0005   SALAM-RBAC-0006   SALAM-RBAC-0007   SALAM-RBAC-0008
   ```

## What each role should see (quick check)

- **Super Admin** — every sidebar item incl. Adminlər + Audit jurnalı; every action; the **Daxil ol**
  (impersonate) button on the Admins page.
- **Technical** — Cihazlar only (create/edit/reconcile/diagnostics/whitelist/commands/test-open/traccar). No
  finance, no admins, no audit.
- **Operator** — Cihazlar (view + assign/residents/whitelist); no device create/reconcile/diagnostics; no finance.
- **Finance** — Abunəliklər, Sifarişlər, Geri qaytarmalar; no devices, no admins.
- **Support** — read-only Cihazlar/Sifarişlər/Abunəliklər (no delete/payments); can resend OTP.
- **Complex Manager** — residents/whitelist/reports/device-status **for Salam Həyət A only** (other complexes' devices are invisible / 404).

Full detail: `ADMIN_TEST_SCENARIOS.md` and `RBAC_PERMISSION_MATRIX.md`.

## Impersonation

Log in as **Super Admin** → **Adminlər** → press **Daxil ol** next to any admin → you now act as that admin
(amber banner shows it) → press **Öz hesabıma qayıt** to return. Every start/stop is written to the Audit log.
