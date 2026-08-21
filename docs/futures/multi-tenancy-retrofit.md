# Multi-Tenancy Retrofit Plan

**Status:** Filed, not invoked.
**Trigger:** First concrete white-label / B2B-resale conversation. Until then, MVP stays single-tenant.
**Why filed and not implemented now (HIGH-17 disagreement):** Adding `tenant_id` to every domain table now is cognitive tax on every query, every policy, every test fixture — for a feature that may never be needed. We documented the path; we will not pre-pay its cost.

## Trigger Conditions

Re-evaluate this plan only when ONE of the following is true:

- A signed letter-of-intent from a property-management chain, cooperative, or franchise.
- A funded pilot with a second operator in a different city / country.
- An acquisition or merger that brings a second tenant into scope.

Do NOT invoke this plan in response to a "what if" or "wouldn't it be nice."

## Scope When Invoked

Add `tenant_id INT UNSIGNED NOT NULL DEFAULT 1` to:

- `users`, `admin_users`, `devices`, `device_users`, `subscriptions`, `orders`, `payments`, `refunds`, `payment_logs`, `payment_callbacks`, `notifications`, `audit_log`, `invitations`, `whitelist_changes`, `open_commands`, `device_diagnostics`, `subscription_periods`, `data_subject_requests`, `report_jobs`, `notification_templates`, `feature_flags`, `settings`.

Lookups (`sim_operators`, `device_models`, `regions`) stay global unless the second tenant has a different region taxonomy — in which case `regions` gains `tenant_id` and the others don't.

## Sequencing (estimated 2 sprint weeks, ~10 engineering days)

1. **Day 1–2** — Migration: add nullable `tenant_id`, backfill `= 1`, set NOT NULL with default. One migration per logical batch; reversible.
2. **Day 3–4** — `TenantContext` accessor (request scope), `TenantScope` Eloquent global scope on every model in scope, `tenant_id` auto-assignment on `creating` event for every model.
3. **Day 5–6** — Update every Policy to require matching `tenant_id` between actor and target.
4. **Day 7** — CI integrity test: query each table without scope, expect `tenant_id` populated on every row; fail otherwise.
5. **Day 8** — Admin panel: super_admin scope per tenant; tenant-switcher in topbar.
6. **Day 9–10** — Mobile: no UI changes; the JWT gains a `tenant_id` claim that the API uses to seed `TenantContext`.

## What NOT to do

- Don't make `tenant_id` part of any PRIMARY KEY (use a separate UNIQUE per-tenant where needed). Single-PK keeps Eloquent and migrations clean.
- Don't shard by tenant in MVP. Logical isolation is enough until at least 100 K rows per tenant.
- Don't expose `tenant_id` in any client-facing payload. It's a server-side concern.

## Cost Estimate Sanity Check

If this estimate balloons past 3 sprint weeks during planning, **stop and re-scope**. The retrofit should be mechanical; if it has grown legs, something else changed in the meantime and this plan needs to be re-thought, not stretched.
