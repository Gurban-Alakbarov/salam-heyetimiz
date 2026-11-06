# Kapital / BirPay Checkout Merchant API — raw archive

Provenance of the curated docs in the parent folder. Captured 2026-06-28.

- **Source**: official "Checkout Merchant API Reference — V1.3" Notion page
  `https://silken-dolomite-a7f.notion.site/Checkout-Merchant-API-Reference-V1-3-1e548b507ada80cbaf5dd4458e2588ad`
- `01_reference_body.txt` — the page body (intro, status codes, payment status, cancellation, webhook, signature), reconstructed from the Notion `loadPageChunk` API.
- `02_endpoints_objects.txt` — the Endpoints + Objects inline databases (each endpoint's body params, response bodies, and cURL samples), reconstructed from the Notion `queryCollection` + per-row `loadPageChunk`.
- `notion_blocks.json` — raw merged Notion block records.

The page renders client-side, so a plain fetch returns nothing; the content above was extracted via Notion's public `api/v3` endpoints. Sequence-flow diagrams (Web2App / App2App / Card Payment / payment_state.svg) are images in the source and are not reproduced as text — they are described in `PAYMENT_FLOW.md` from the verified API behaviour.

**Live verification (sandbox, read-only, no payments created):** authentication, token shape, JWT claims, and authenticated `GET /v1/payments/{id}` / `GET /v1/refunds/{id}` error responses were confirmed against `preapi.birpay.az` on 2026-06-28. See `SANDBOX_SETUP.md`.
