# Admin UI — Deployment Report

**Date:** 2026-06-21
**Server:** `185.208.206.174` (Ubuntu 24.04) · **Domain:** `admin.salamheyetimiz.com` (Cloudflare, proxied)
**Result:** ✅ Live. The React admin SPA is served at `https://admin.salamheyetimiz.com`; the Laravel API is
reachable same-origin at `/admin/v1` and unchanged at `https://api.salamheyetimiz.com`. **No backend code modified.**

---

## 1. Architecture

```
https://admin.salamheyetimiz.com  ──Cloudflare(Full Strict)──►  Nginx (admin vhost)
        ├── /                     → /var/www/salam-admin/dist  (React SPA, try_files → index.html)
        ├── /assets/*             → static, immutable, 1y cache
        └── /admin/v1/*           → PHP-FPM → /var/www/salam/public/index.php  (Laravel, same-origin)

https://api.salamheyetimiz.com    ──►  Nginx (app vhost) → Laravel  (unchanged, backend API)
https://salamheyetimiz.com / www  ──►  Nginx (app vhost) → Laravel  (unchanged)
https://traccar.salamheyetimiz.com──►  Nginx → Traccar 8082          (unchanged)
```

The SPA was built with a **relative API base** (`baseURL=''` → calls `/admin/v1`), so on `admin.` it talks to
the Laravel backend **same-origin → no CORS, no backend change**. `api.salamheyetimiz.com` remains the API host.

## 2. Steps performed

| # | Action | Detail |
|---|---|---|
| 1 | Build | `npm run build` → `dist/` (index.html + assets/index-B8uSqbc_.js 481 kB + index-B8NNkOss.css 24 kB) |
| 2 | Upload | `pscp` tarball → `/root/admin-dist.tgz` |
| 3 | Directory | `mkdir -p /var/www/salam-admin/dist`; extract; `chown -R www-data:www-data /var/www/salam-admin` |
| 4 | Nginx | Rewrote `/etc/nginx/sites-available/salam`: removed `admin.` from the app block; added a dedicated `admin.salamheyetimiz.com` server block (SPA + `/admin/v1` fastcgi). `nginx -t` ✅ → `systemctl reload nginx` |

Deploy script: [`deploy/phaseJ_admin_spa.sh`](../deploy/phaseJ_admin_spa.sh).

## 3. Nginx — admin vhost (key parts)

```nginx
server {
    listen 443 ssl http2;
    server_name admin.salamheyetimiz.com;
    ssl_certificate     /etc/ssl/cloudflare/salam-origin.pem;
    ssl_certificate_key /etc/ssl/cloudflare/salam-origin.key;
    root /var/www/salam-admin/dist;
    index index.html;

    location ^~ /admin/v1 {                       # API → Laravel front controller (same-origin)
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/salam/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/salam/public;
        fastcgi_param HTTPS on;
    }
    location /assets/ { add_header Cache-Control "public, max-age=31536000, immutable"; try_files $uri =404; }
    location /       { try_files $uri $uri/ /index.html; }   # React Router fallback
}
```

## 4. Verification (origin + public via Cloudflare)

| Check | Origin (127.0.0.1) | Public (Cloudflare) |
|---|---|---|
| `GET /` serves the SPA | ✅ `<title>Salam — Admin Panel</title>` | ✅ 200, text/html, Server: cloudflare |
| `GET /assets/index-*.js` | ✅ 200 | ✅ 200 `application/javascript`, SSL valid |
| `POST /admin/v1/auth/login` (same-origin API) | ✅ 422 (Laravel validation) | ✅ 422 |
| Nested refresh `GET /devices` | ✅ 200 (index.html) | ✅ SPA served |
| Deep nested `GET /devices/123` | — | ✅ 200 |
| `api.salamheyetimiz.com` health | ✅ 200 | ✅ 200 `{database:true,redis:true}` |
| SSL (Full Strict) | wildcard cert `*.salamheyetimiz.com` | ✅ `ssl_verify_result=0` |

- **Browser refresh on nested routes works** (`/devices`, `/devices/123` → index.html via `try_files`).
- **Build assets load** (200, correct MIME, cached immutable).
- **API requests keep working** (`/admin/v1/*` same-origin → Laravel).
- **SSL intact** (Cloudflare Origin cert, Full Strict, publicly-valid edge cert).
- Cloudflare cache: root HTML `CF-Cache-Status: DYNAMIC` (not stale) — no purge required.

## 5. URLs

| URL | Serves |
|---|---|
| `https://admin.salamheyetimiz.com` | **React admin panel (SPA)** |
| `https://admin.salamheyetimiz.com/admin/v1/...` | Laravel admin API (same-origin) |
| `https://api.salamheyetimiz.com` | Laravel backend API (unchanged) |
| `https://salamheyetimiz.com` / `www` | Laravel app (unchanged) |
| `https://traccar.salamheyetimiz.com` | Traccar web panel (unchanged) |

## 6. Paths on server

| Path | Contents |
|---|---|
| `/var/www/salam-admin/dist` | SPA build (index.html, assets/) — owner `www-data` |
| `/var/www/salam/public` | Laravel front controller (API) |
| `/etc/nginx/sites-available/salam` | vhost (app + admin SPA + traccar) |

## 7. Redeploy procedure (future updates)

```bash
# local
cd admin-ui && npm run build && (cd dist && tar czf /tmp/admin-dist.tgz .)
pscp /tmp/admin-dist.tgz root@185.208.206.174:/root/admin-dist.tgz
# server
rm -rf /var/www/salam-admin/dist && mkdir -p /var/www/salam-admin/dist
tar xzf /root/admin-dist.tgz -C /var/www/salam-admin/dist
chown -R www-data:www-data /var/www/salam-admin
# (Nginx unchanged; purge Cloudflare cache only if asset names were pinned)
```

## 8. Notes

- Login requires a provisioned admin account (mandatory password + TOTP 2FA). None is seeded in production
  yet, so the panel loads and reaches the API, but sign-in needs a super-admin created first (a data action).
- Backend was **not** modified — only the SPA was deployed and Nginx reconfigured.
