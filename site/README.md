# Website

Static pages generated from content. The website keeps working when the backend or the database is down.

| Where | What |
|---|---|
| `content/site.json` | Menus and texts shared by all pages |
| `content/pages/<key>.json` | One page: title, description and its sections, in order |
| `content/icons.json` | Icon drawings, by name |
| `landing/` | Images, `site.css`, `site.js`, copied as they are |
| `resources/views/site/` | Page frame and one template per section type |
| `app/Domain/Site/Publishing/` | `SectionCatalog` (the section types), `SiteContent`, `SiteBuilder` |

## Build

```bash
php artisan site:build
```

Writes 30 pages to `storage/app/site-build` (`index.html`, `services.html`, `en/index.html`, `en/services.html`, ...). With `--out=<empty folder>` it writes there instead.

## Content rules

- A translated value is `{"az": "...", "en": "...", "ru": "..."}`. An empty translation falls back to Azerbaijani.
- Links are written without a language (`/contact`). The build adds `/en` or `/ru`.
- A section's `type` must be listed in `SectionCatalog`, and each type has a view in `resources/views/site/sections/`.
- The campaigns page is built only while a campaign runs. A campaign's `ends_at` is its last day, checked at build time, so a daily build is needed to take it down on time.

## Web server

Each address serves its own file:

```nginx
location = / { root /var/www/salam-site; try_files /index.html =404; }
location ~ ^/(services|how-it-works|solutions|campaigns|faq|contact|privacy|terms|refund|account)/?$ {
    root /var/www/salam-site; try_files /$1.html =404;
}
location ~ ^/(en|ru)/?$ { root /var/www/salam-site; try_files /$1/index.html =404; }
location ~ ^/(en|ru)/(services|how-it-works|solutions|campaigns|faq|contact|privacy|terms|refund|account)/?$ {
    root /var/www/salam-site; try_files /$1/$2.html =404;
}
```
