# API REVIEW

> Pre-Flutter audit. **Read-only.** Compares the real registered routes against the served OpenAPI spec and checks response/status/pagination consistency.

---

## 0. Verdict

**API grade: GOOD (7.5/10).** The *implemented* surface is clean: consistent cursor pagination, consistent versioning, correct status codes, and the new mobile surface uses one unified envelope. The drag is **spec-vs-reality drift** — the served OpenAPI spec still carries the original design contract, so ~45 documented operations are not implemented. This is the #1 thing to reconcile before Flutter relies on Swagger.

---

## 1. Real implemented surface (what Flutter should build against)

**Mobile `/v1` (implemented):** health/live, health/ready, **bootstrap**, payments/return, auth/otp/request, auth/otp/verify, auth/refresh, **auth/register, auth/verify-email, auth/resend-otp, auth/login**, auth/logout, **me**, me/biometrics(enroll/disable), orders(index/store/show/recheck), subscriptions(index/show/renew/auto-renew), devices(index/show/open/commands/stats), commands/{id}(show/feedback), technical/devices(register/assign). · **Webhooks `/v1`:** payments/webhook, traccar/forward. · **JWKS:** /.well-known/jwks.json.

**Admin `/admin/v1` (implemented):** auth(login/2fa-verify/logout/me/recovery-codes/stop-impersonation), admins(CRUD + impersonate), audit, access(roles/permissions/user-perms/grant/revoke/reset), complexes(CRUD + managers), settings(get/patch{group}/test/export/import/versions/compare/restore/email+sms test/traccar status/force-logout/payments test-create), system/health, orders(index/show/refund/recheck), payment-logs, payments/stats, refunds, subscriptions, residents, devices(full CRUD + disable/enable/transfer/assign/users/reconcile/commands/diagnostics/whitelist-queue/resync). · **Docs `/api`:** docs/swagger/redoc/openapi.json/openapi.yaml/postman + version-scoped.

This surface is **stable, prod-verified, and safe to build a Flutter app against.**

---

## 2. Findings

### API-1 — OpenAPI ↔ route parity drift (HIGH, documentation accuracy)
The served `openapi.json` is the merge of the original **design contract** (`v1.yaml`) + the implemented-endpoint supplement (`v1.extra.yaml`). The design contract documents **~45 operations that are NOT implemented**, e.g.:
- Mobile: `notifications/*`, `consents`, `privacy/export|deletion`, device `invitations/*`, device roster `users` (mobile side), `technical/.../diagnostics/ping`, `sms/inbound`, the legacy `payments/callback` design path.
- Admin: `users/*` (user management), `metrics/overview`, `lookups/*` (sim-operators/device-models/regions), `reports/*`, `report-jobs/*`, `feature-flags/*`, `notification-templates/*`, `settings/{key}` (singular — real route is `settings/{group}`).

**Why it matters:** a Flutter developer reading Swagger will see endpoints that return 404, and may build against them. This is a **doc-accuracy** issue, not a runtime bug.
**Fix direction (no backend code):** before Flutter, either (a) prune the unimplemented paths from the spec, or (b) tag them `x-status: planned` + a visible "NOT IMPLEMENTED" note, or (c) split the spec into "implemented" (served) vs "roadmap". Cheapest: filter the build (`docs/openapi/build-openapi.mjs`) to keep only paths that map to a real route (intersect with `php artisan route:list`). **This is the single most valuable pre-Flutter doc fix.**

### API-2 — Response-shape multiplicity (MEDIUM)
Five shapes coexist: (1) unified envelope `{success,message,data,meta,errors}` (new mobile auth + bootstrap), (2) legacy bare auth `{access_token,…}` (otp/verify, refresh, admin auth), (3) list `{data,page}` + single `JsonResource`, (4) `204` void (logout, biometrics), (5) domain-specific (open device).
**For Flutter:** the **new** mobile surface (register/verify/resend/login/bootstrap/me) is consistently the unified envelope — that's what the app binds to. The legacy phone-OTP shape is frozen additive coexistence. **Recommendation:** document the shape per endpoint in the spec, and treat the unified envelope as the v1 mobile standard; converging the legacy + list endpoints onto the envelope is a v2 breaking change, not required for launch.

### API-3 — Documented error codes not implemented (MEDIUM)
The spec lists rich error responses on some endpoints (`openDevice` → 402/409/503, `createOrder` → 402/503, `renewSubscription` → 409) that the controllers don't emit (they return success or a generic error). **For Flutter:** don't build UI for status codes the backend can't produce yet; align the spec to actual behavior or implement the codes. Medium.

### API-4 — Intentional duplicates / retired (LOW)
- Legacy phone-OTP (`otp/request`+`otp/verify`) vs new email flow — intentional coexistence (frozen). The new app should use email + ignore phone-OTP.
- `sms_login` feature flag is hardcoded `false` (SMS login retired). Remove from the spec/flags or leave as an explicit "off".
- The base design `/me` stub was correctly superseded by the implemented `currentUser` in the merged spec (resolved).

### API-5 — Pagination + versioning (GOOD)
Every list endpoint uses the same cursor contract `{data, page:{next_cursor, has_more, limit}}` with `limit` (1–100) + opaque `cursor`. All routes are consistently `/v1` or `/admin/v1`. `/.well-known/jwks.json` at host root is per RFC (correct). No issues.

---

## 3. HTTP status codes — mostly correct
`201` for creates (createOrder, adminCreateDevice, adminRefundOrder), `202` for deferred (requestOtp, register, openDevice), `204` for void (logout, biometrics), `200` for reads + idempotent. The only gaps are the unimplemented error codes (API-3). No "errors returning 200" found in the implemented surface.

---

## 4. Recommendations (priority)
1. **HIGH (pre-Flutter, doc-only):** reconcile the OpenAPI spec with the real route list — prune or clearly mark the ~45 phantom operations. (Edit `build-openapi.mjs` to intersect with `route:list`, or trim `v1.yaml`.)
2. **MEDIUM:** annotate response-shape per endpoint; affirm the unified envelope as the v1 mobile contract.
3. **MEDIUM:** align documented error codes with actual controller behavior.
4. **LOW:** drop/clarify retired flags + duplicate legacy endpoints in the spec.

**The implemented API is solid and Flutter-ready; the spec just needs to tell the truth about what's built.**
