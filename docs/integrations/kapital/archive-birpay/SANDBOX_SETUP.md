# Kapital / BirPay — Sandbox (Preprod) Setup & Live Verification

## Credentials (sandbox)

| Field | Value |
|---|---|
| API host | `https://preapi.birpay.az` **[VERIFIED]** |
| Token endpoint | `https://preapi.birpay.az/api/oauth2/token` **[VERIFIED]** |
| `client_id` | `birpay-test` |
| `client_secret` | `mc8JHRvS9JyaElcj1ozm1Fpd5Gpaj73q` *(rotate before launch; never commit)* |
| `scope` | `email` (server returns granted scope `profile email`) |
| `merchant_id` | `E1040009` |
| `terminal_id` | `E1040009` |
| Test card | PAN `4169738886501026`, CVV `369`, expiry `10/25` |

> Reconciled with the official samples: `precheckout.kapitalbank.az` is the **hosted checkout page** host
> (preprod) — it is where the create-payment response's `confirmUrl` points and where we redirect the customer;
> it is **not** the API base. Our API calls go to **`preapi.birpay.az`**. Both roles are legitimate.

## Live verification performed 2026-06-28 (read-only — no payments created)

1. **Authentication** — `POST https://preapi.birpay.az/api/oauth2/token`
   (form: `grant_type=client_credentials&scope=email&client_id&client_secret`) → **HTTP 200**:
   ```json
   { "access_token": "<JWT 1085 chars>", "expires_in": 300, "refresh_expires_in": 0,
     "token_type": "Bearer", "not-before-policy": 0, "scope": "profile email" }
   ```
2. **Token claims** (decoded JWT) — `iss: https://presso.kapitalbank.az/realms/acquiring` (Keycloak),
   `azp: birpay-test`, `typ: Bearer`, lifetime **300 s**, no usable refresh token.
3. **Authenticated `GET /v1/payments/{uuid}`** → **HTTP 400** `payment_not_found` (envelope confirmed).
4. **Authenticated `GET /v1/refunds/{uuid}`** → **HTTP 400** `payment_not_found`.
5. **Unauthenticated `GET /v1/payments/{uuid}`** → **HTTP 401** `{"code":"unauthorized","message":"Not Authenticated"}`.

No create-payment / capture-card / refund operations were performed (per task constraints).

## Recommended sandbox config (maps to our Settings → payments)

```
kapital_mode             = sandbox
kapital_api_base_url     = https://preapi.birpay.az
kapital_client_id        = birpay-test
kapital_client_secret    = <secret>            (encrypted)
kapital_scope            = email                (NEW — see gap analysis)
kapital_merchant_id      = E1040009
kapital_terminal_id      = E1040009
kapital_webhook_secret   = <shared secret from Kapital>  (encrypted)
```

## Next sandbox step (implementation phase only — NOT now)

A single end-to-end create→redirect→status test with the test card, to confirm:
- `confirmation.type=REDIRECT` + `paymentMethodData.type=BANK_CARD` returns a web `confirmUrl`;
- the webhook `X-Signature` secret and the exact webhook source IPs;
- `succeeded` status transition and 3-D Secure behaviour.
