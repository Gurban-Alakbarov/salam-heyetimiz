# Salam — Admin Panel UI

React + TypeScript + Vite admin panel for the Salam Həyətimiz platform. Talks to the live backend at
`https://admin.salamheyetimiz.com/admin/v1`. No mock data — every screen is bound to a real endpoint.

## Stack
React 18 · TypeScript · Vite 5 · TailwindCSS 3 · React Router 6 · TanStack Query 5 · Axios · shadcn/ui (Radix).

## Scripts
```bash
npm install
npm run dev      # localhost:5173 — proxies /admin/v1 to the live backend (no CORS in dev)
npm run build    # → dist/  (deploy same-origin to admin.salamheyetimiz.com)
npm run lint
```

## Config
`VITE_API_BASE_URL` (optional) — leave empty for the dev proxy (dev) / same-origin (prod). Set an absolute
origin (e.g. `https://admin.salamheyetimiz.com`) only when hosting the SPA on a different host.

## Auth
Mandatory two-phase: email + password → 2FA (TOTP or recovery code). Admin tokens are 30-min stateless JWTs
(no refresh) stored in `sessionStorage`; a 401 clears the session and returns to `/login`.

See `../docs/ADMIN_UI_ARCHITECTURE.md` and `../docs/ADMIN_UI_REVIEW.md` for the full design, screen/route
map, backend coverage matrix, and known backend dependencies.
