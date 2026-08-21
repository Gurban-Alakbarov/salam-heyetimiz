# Admin RBAC — Test Scenarios (what each role should see)

**Date:** 2026-06-27 · Log in with the accounts in `RBAC_TEST_USERS.md` and verify each row. "Sidebar" = the
left menu items visible; "403" = opening the route directly shows the 403 page; "hidden" = the button is not
rendered. Backend returns 403 on every forbidden API call regardless of the UI.

## Sidebar visibility per role

| Sidebar item | Super | Technical | Operator | Finance | Support | Complex Mgr |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| İdarə paneli | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Cihazlar | ✓ | ✓ | ✓ | | ✓ | ✓ |
| Abunəliklər | ✓ | | | ✓ | ✓ | |
| Sifarişlər | ✓ | | | ✓ | ✓ | |
| Geri qaytarmalar | ✓ | | | ✓ | | |
| Adminlər | ✓ | | | | | |
| Audit jurnalı | ✓ | | | | | |

## Per-role scenarios

### Super Admin
- Sees all 7 sidebar items. On a device: every action button (Redaktə, Bağla, Köçür, Çıxar, Sahib/Sakin, Çıxar).
- Adminlər page: list + **Admin yarat** + **Daxil ol** (impersonate) + **Deaktiv et**. Audit jurnalı populated.
- Opening `/audit`, `/admins` → OK.

### Technical
- Sidebar: İdarə paneli, Cihazlar. Opening `/orders`, `/refunds`, `/admins`, `/audit` directly → **403 page**.
- Device: can create (Cihazlar → "+"), edit, **reconcile**, see diagnostics/whitelist/commands tabs, send test
  open. Cannot see Köçür/Çıxar/Bağla (super-only) — hidden.

### Operator
- Sidebar: İdarə paneli, Cihazlar. `/orders`,`/refunds`,`/admins`,`/audit` → 403.
- Device detail: **Sahib təyin et / Sakin əlavə et / Çıxar** (roster) + whitelist tab. The device **create**
  button and reconcile are hidden / 403. No diagnostics action.

### Finance
- Sidebar: İdarə paneli, Abunəliklər, Sifarişlər, Geri qaytarmalar. `/devices`,`/admins`,`/audit` → 403.
- Can open orders, issue refunds, view subscriptions. No device or whitelist access.

### Support
- Sidebar: İdarə paneli, Cihazlar, Abunəliklər, Sifarişlər. `/refunds`,`/admins`,`/audit` → 403.
- Read-only: can view devices/orders/subscriptions + open-command history; **no** delete, refund, or device
  mutation buttons (hidden). Can resend OTP (when that control ships).

### Complex Manager (Salam Həyət A)
- Sidebar: İdarə paneli, Cihazlar. `/orders`,`/refunds`,`/admins`,`/audit` → 403.
- **Cihazlar lists only Salam Həyət A devices.** Opening a device from another complex → **404**. Can manage
  residents + whitelist for his complex; cannot create devices or see diagnostics.

## Impersonation scenario
1. Log in as Super Admin → Adminlər → **Daxil ol** next to `finance@…`.
2. The UI switches to the finance view (amber banner: "Demo Finance kimi baxırsınız"); sidebar now shows the
   finance items only; `/devices` → 403.
3. Press **Öz hesabıma qayıt** → back to super admin. Both transitions appear in **Audit jurnalı**
   (`admin.impersonation.start` / `.stop`).

## Audit scenario
Perform any action (assign a device, add a resident, create an admin, impersonate). Open **Audit jurnalı** as
super admin → the action appears with actor email, action key, target, IP, timestamp (and an `impersonation`
badge if done while impersonating).
