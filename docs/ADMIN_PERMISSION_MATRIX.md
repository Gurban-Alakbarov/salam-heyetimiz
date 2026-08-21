# Admin Permission Matrix — per-role capability view

**Date:** 2026-06-27 · Companion to `RBAC_PERMISSION_MATRIX.md` (the full permission × role grid). This is
the readable per-role CAN / CANNOT cut, matching the production requirement.

## SUPER ADMIN — full access
**CAN:** everything — system/sms/payment/traccar settings; create/update/delete admins; assign roles;
impersonate any admin; every complex, barrier, resident, device; audit log.
**CANNOT:** nothing (and cannot deactivate/demote the *last* active super admin, by guard).

## TECHNICAL
**CAN:** view/create/edit/**reconcile** devices · diagnostics · whitelist (view+manage) · open-command history
· **send test open** · Traccar status / firmware · assign a device to an owner (field install).
**CANNOT:** create admins · payments/refunds/finance · subscriptions · residents/apartments/vehicles ·
system/sms/settings · disable/decommission/transfer (super only).

## OPERATOR
**CAN:** residents (view/create/update/delete) · apartments · vehicles · **assign device** · **assign barrier**
· whitelist (view+manage) · open-command history · view devices · **notifications (view + send system announcements)**.
**CANNOT:** create devices · reconcile · diagnostics · payments/finance · admins · system settings.

## FINANCE
**CAN:** payments · orders · **refunds** (view+create) · subscriptions (view+manage) · invoices · reports.
**CANNOT:** device management · barrier management · whitelist · traccar · residents · admins · system settings.

## SUPPORT
**CAN:** view residents · view orders · view subscriptions · view device status · view open-command history ·
**resend OTP** · **view notification campaigns** · help customers (read-only).
**CANNOT:** delete anything · payments/refunds · settings · technical operations (create/edit/reconcile) · admins.

## COMPLEX MANAGER — **only his own complex**
**CAN (scoped to his complex):** residents (view/create/update/delete) · apartments · **barriers** · whitelist
(view+manage) · reports · device status · **notifications (view + send system announcements, own complex only)**.
**CANNOT:** access any other complex (devices/residents outside his complex are invisible / 404) · create
devices · diagnostics · payments/finance · admins · system settings.

> Grant counts (incl. planned `notifications.*`, seeded when the module ships): Super = 46 (all), Technical 12, Operator 17, Finance 9, Support 8, Complex Manager 14.
> Exact permission keys per role: `RBAC_PERMISSION_MATRIX.md`.
