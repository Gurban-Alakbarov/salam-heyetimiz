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

## Database

`php artisan site:import` fills `site_documents` from these files, once, and only while the table is empty. From then on the database holds the working drafts and each saved change is a row in `site_revisions`. `php artisan site:build --from-db` builds from the drafts. Icons stay in `content/icons.json`, they are part of the design.

The admin API is under `/admin/v1/site/` and needs the `site.manage` permission. `ContentNormalizer` shapes what is saved, and `SiteHtml` removes any markup that is not allowed, because pages render those fields unescaped.

## Publishing

The database is the source of the live site; the files here only seed it. `SitePublisher` builds a complete copy in `SITE_RELEASES_PATH/<release>` and moves the `current` link to it in one step. The web server serves `current`, so a failed build changes nothing and going back is the same step. The last 10 copies stay on disk.

`php artisan site:rebuild` builds what is already published again. Run it after a deploy that changes templates, `site.css` or `site.js`. The scheduler runs it daily, so a campaign ends on its last day without anyone editing it.

## Content rules

- A translated value is `{"az": "...", "en": "...", "ru": "..."}`. An empty translation falls back to Azerbaijani.
- Links are written without a language (`/contact`). The build adds `/en` or `/ru`.
- A section's `type` must be listed in `SectionCatalog`, and each type has a view in `resources/views/site/sections/`.
- The campaigns page is built only while a campaign runs. A campaign's `ends_at` is its last day.

## Web server

Each address serves its own file from the live copy:

```nginx
location = / { root /var/www/salam-site-releases/current; try_files /index.html =404; }
location = /sitemap.xml { root /var/www/salam-site-releases/current; try_files /sitemap.xml =404; }
location = /robots.txt { root /var/www/salam-site-releases/current; try_files /robots.txt =404; }
location ~ ^/(services|how-it-works|solutions|campaigns|faq|contact|privacy|terms|refund|account)/?$ {
    root /var/www/salam-site-releases/current; try_files /$1.html =404;
}
location ~ ^/(en|ru)/?$ { root /var/www/salam-site-releases/current; try_files /$1/index.html =404; }
location ~ ^/(en|ru)/(services|how-it-works|solutions|campaigns|faq|contact|privacy|terms|refund|account)/?$ {
    root /var/www/salam-site-releases/current; try_files /$1/$2.html =404;
}
location ^~ /landing/ { root /var/www/salam-site-releases/current; try_files $uri =404; expires 7d; }
```
