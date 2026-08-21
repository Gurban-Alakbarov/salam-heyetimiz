# Salam Həyətimiz — Localization Specification

**Version:** 1.0
**Date:** 2026-06-09
**Status:** Draft for review
**Cross-references:**
- [TECHNICAL_SPECIFICATION.md](TECHNICAL_SPECIFICATION.md) v1.1 — §1.6 `FR-LOC`
- [BACKEND_ARCHITECTURE.md](BACKEND_ARCHITECTURE.md) v1.1 — §6 (HTTP / locale negotiation)
- [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md) v1.1 — §7 `notification_templates`, `notification_template_locales`
- [openapi/v1.yaml](openapi/v1.yaml) v1.1.0 — `Error` envelope (changes flagged below as v1.2)
- [UI_UX_SPECIFICATION.md](UI_UX_SPECIFICATION.md) v1.1 — §3.7 Localization

---

## Table of Contents

0. Document Purpose & Scope
1. Translation Architecture
2. Localization Standards
3. Key Naming Convention
4. Folder Structure
5. Admin Localization Rules
6. Mobile Localization Rules
7. Error Message Localization Rules
8. Future Language Expansion Strategy

Appendix A — Locale Code Reference
Appendix B — Date / Time Format Quick Reference
Appendix C — Translation File Catalogue
Appendix D — Required OpenAPI v1.2 Changes
Appendix E — Open Items for Sign-off

---

## 0. Document Purpose & Scope

### 0.1 What This Spec Defines

The end-to-end localization (i18n / l10n) contract for the Salam Həyətimiz platform across the Flutter mobile app, the Laravel admin panel, and the API. It binds engineering, design, QA, and product to a single set of rules so translations, formatting, and the API surface remain consistent and additively extensible.

### 0.2 Locked Decisions

| # | Decision |
|---|---|
| L1 | **Primary locale:** Azerbaijani (`az`). Default for mobile and admin. |
| L2 | **Secondary locales:** Russian (`ru`), English (`en`). |
| L3 | **API NEVER returns translated strings.** It returns `code` + `message_key` + structured `details`. Clients resolve to localized strings. |
| L4 | **Source of truth for static UI strings**: backend repo `lang/{locale}/` files (committed). |
| L5 | **Source of truth for notification content**: `notification_template_locales` DB table (admin-editable). |
| L6 | **Source of truth for legal documents**: versioned static files under `public/legal/`. |
| L7 | **Mobile bundles ARB** files at build time. OTA translation updates are out of MVP scope. |
| L8 | **Plural and date/number formatting** uses CLDR data via `intl` (Flutter) and `IntlDateFormatter` / `symfony/translation` (PHP). |
| L9 | **All three locales are LTR.** No RTL support shipped or tested. |

### 0.3 What This Spec Does NOT Define

- Specific translator vendor or TMS choice (Crowdin / Lokalise) — Phase 2 selection.
- Final translated copy for every key — that's a separate translator deliverable using this spec's keys and rules.
- Exact wording reviews for legal / marketing copy — handled by counsel and product.
- Mobile and admin font choices beyond confirming Inter covers all three scripts.

### 0.4 Required Contract Change

This spec mandates a backwards-compatible change to the API error envelope. See **Appendix D** for the proposed `openapi/v1.yaml` revision (target version: 1.2.0). The v1.1 envelope's translated `message` field becomes obsolete.

---

## 1. Translation Architecture

### 1.1 Source-of-Truth Map

There are **three distinct localized-content streams**, each with its own source of truth, lifecycle, and consumer.

| Stream | Lives in | Edited by | Shipped via | Consumed by |
|---|---|---|---|---|
| **UI strings** (buttons, labels, screen copy, error messages) | `lang/{locale}/*.php` (backend repo) and mirrored `lib/l10n/intl_{locale}.arb` (mobile repo) | Engineers + translators via PRs | Bundled with app/admin deploys | Admin Blade templates; Flutter widgets |
| **Notification content** (push/SMS/inapp bodies) | `notification_template_locales` DB table | Super admin via A-86 / A-87 | Rendered at notification dispatch time | Backend `TemplateRenderer` |
| **Legal / CMS documents** (Terms, Privacy, Help FAQ) | Versioned files under `public/legal/{slug-vN}/{locale}.html` | Counsel / product via PRs | Served as static assets | In-app WebView (`S-04 Phone Entry` Terms link, `S-07 Consents`, `S-54 Privacy`) |

These streams have different cadences (UI = release-bound, notifications = real-time, legal = versioned consent triggers) and must not be conflated in one storage layer.

### 1.2 Data Flow Diagram

```
                            ┌─────────────────────────────┐
                            │  Backend repo (Git)         │
                            │  lang/az,en,ru/             │
  Translator → PR ────────► │  intl_az,en,ru.arb (mirror) │
                            └────────────┬────────────────┘
                                         │ build/deploy
                                         ▼
                  ┌──────────────────────┴───────────────────────┐
                  │                                              │
                  ▼                                              ▼
        ┌───────────────────┐                          ┌─────────────────┐
        │  Mobile app .apk/ │                          │  Admin Blade    │
        │  .ipa with ARB    │                          │  served from    │
        │  embedded         │                          │  lang/ files    │
        └───────────────────┘                          └─────────────────┘
                  ▲                                              ▲
                  │  resolve(key, locale)                        │  __('key')
                  │                                              │
   API ──── code + message_key + details ──┐         API ─── code + message_key ┐
                                            │                                    │
                                            ▼                                    ▼
                                    [client resolves]               [server resolves
                                                                     for THIS admin]
```

The API never sees a locale when generating error responses. The locale is a client concern.

### 1.3 The "API Returns Keys, Not Strings" Principle

This is the architectural keystone. Rules:

1. The API returns `error.code` (stable, machine-readable, locale-independent) and `error.message_key` (the lookup token clients use).
2. The API does **not** return a translated `message`. (Required v1.2 OpenAPI change — see Appendix D.)
3. The API may return `details` (a JSON object) carrying placeholder values for interpolation by the client.
4. Server logs use the `code`, never the translated string.

**Why this design.**
- **Single translation registry** — clients own the rendering, so all locales live in one place; the server doesn't.
- **Locale changes need no server deploy** — fixing copy is a mobile/admin release.
- **Stateless wrt locale** — no `Accept-Language` parsing for content, no per-locale tests on the API.
- **Cheaper to validate** — CI verifies `code → message_key` 1:1, not per-locale strings.

**Trade-off accepted.** Server-rendered emails (Phase 2) DO have to know the recipient's locale because there is no client to resolve the string. For those paths the server reads `users.preferred_language` and renders templates from `notification_template_locales`. This does not violate the principle — emails are *content*, not API responses.

### 1.4 Locale Negotiation

| Surface | Locale derived from |
|---|---|
| Mobile | `users.preferred_language` (server-stored, master) → app's stored override → device `Platform.localeName` → `az` |
| Admin | `admin_users.preferred_language` (new column in v1.1+; see §5.1) → `az` |
| Server-rendered content (emails, PDFs, SMS) | Recipient `users.preferred_language` → `az` |
| API logs | n/a — locale is irrelevant to logs |

`Accept-Language` is consulted only for non-content paths: e.g. setting the locale of the marketing landing page if a user hits an unauthenticated endpoint. It is **never** used to decide error-response language because errors carry no language.

### 1.5 Translation Workflow

| Step | Owner | Tool |
|---|---|---|
| New feature ships → engineer adds keys + AZ source string | Engineer | Git PR |
| Translator picks up missing-keys report | Translator(s) | PR review on `lang/{en,ru}/*.php` and `intl_{en,ru}.arb` |
| Review by native speaker | Reviewer per language | PR comments |
| Merge | Engineering lead | — |
| CI sync check | Automated | `i18n-sync` script verifies key parity across locales and across backend/mobile bundles |
| Pseudolocalization smoke (Phase 2) | QA | `qps-ploc` synthetic locale |

Phase 2 plan: lift the manual PR loop into a TMS (Crowdin or Lokalise). Source-of-truth stays in Git; TMS is a translator UX on top.

---

## 2. Localization Standards

### 2.1 Supported Locales

| Locale | BCP-47 | Endonym | Direction | CLDR plural forms | Notes |
|---|---|---|---|---|---|
| Azerbaijani | `az` | Azərbaycan dili | LTR | 2 (`one`, `other`) | Latin script. Region tag omitted; we are region-agnostic. |
| Russian | `ru` | Русский | LTR | 4 (`one`, `few`, `many`, `other`) | Important: plural rules are non-trivial. |
| English | `en` | English | LTR | 2 (`one`, `other`) | International English, NOT US-localized (date format and spelling — see §2.2). |

### 2.2 Date & Time

#### 2.2.1 Date Patterns

| Pattern | AZ | EN (intl) | RU |
|---|---|---|---|
| Short | `09.06.2026` | `09/06/2026` (DD/MM/YYYY) | `09.06.2026` |
| Medium | `9 İyun 2026` | `9 Jun 2026` | `9 июн. 2026 г.` |
| Long | `9 İyun 2026, Çərşənbə axşamı` | `Tuesday, 9 June 2026` | `вторник, 9 июня 2026 г.` |
| Time (24-hour) | `14:30` | `14:30` | `14:30` |
| Date + time | `9 İyun 2026, 14:30` | `9 Jun 2026, 14:30` | `9 июн. 2026 г., 14:30` |

The platform uses **24-hour time everywhere**, including the English locale. The product audience is unfamiliar with AM/PM and 24-hour matches Azerbaijani and Russian convention. English follows international (D/M/Y), not US (M/D/Y), again for cross-locale consistency.

#### 2.2.2 Month Names (Azerbaijani)

| # | Name | Genitive (used after day number) |
|---|---|---|
| 1 | Yanvar | yanvar |
| 2 | Fevral | fevral |
| 3 | Mart | mart |
| 4 | Aprel | aprel |
| 5 | May | may |
| 6 | İyun | iyun |
| 7 | İyul | iyul |
| 8 | Avqust | avqust |
| 9 | Sentyabr | sentyabr |
| 10 | Oktyabr | oktyabr |
| 11 | Noyabr | noyabr |
| 12 | Dekabr | dekabr |

In AZ the standalone form is capitalised (`İyun`); when following a day number it is conventionally lowercase (`9 iyun`). `intl` defaults to standalone — we override to lowercase for `dM`-style patterns by using `MMMM` in genitive context via a custom skeleton.

#### 2.2.3 Day-of-Week Names (Azerbaijani)

| Day | Full | Short |
|---|---|---|
| Monday | Bazar ertəsi | B.E. |
| Tuesday | Çərşənbə axşamı | Ç.A. |
| Wednesday | Çərşənbə | Ç. |
| Thursday | Cümə axşamı | C.A. |
| Friday | Cümə | C. |
| Saturday | Şənbə | Ş. |
| Sunday | Bazar | B. |

#### 2.2.4 Relative Times

Used in lists where exact timestamps add clutter (e.g. Inbox, History).

| Concept | AZ | EN | RU |
|---|---|---|---|
| now | indi | now | сейчас |
| seconds ago | bir neçə saniyə əvvəl | a few seconds ago | несколько секунд назад |
| 5 minutes ago | 5 dəqiqə əvvəl | 5 min ago | 5 минут назад |
| 1 hour ago | 1 saat əvvəl | 1 hour ago | 1 час назад |
| 3 hours ago | 3 saat əvvəl | 3 hours ago | 3 часа назад |
| yesterday | dünən | yesterday | вчера |
| 5 days ago | 5 gün əvvəl | 5 days ago | 5 дней назад |
| 2 weeks ago | 2 həftə əvvəl | 2 weeks ago | 2 недели назад |

Rule: switch from relative to absolute when delta > 30 days.

#### 2.2.5 Timezone Display

All API timestamps are UTC; clients convert. UI never displays the offset; it converts to the user's device timezone. Exception: admin pages that may need explicit timezone (audit logs) display `Asia/Baku` next to timestamps.

### 2.3 Currency & Money

#### 2.3.1 AZN Format

The Azerbaijani manat symbol is **₼** (U+20BC).

| Locale | Pattern | Example for 1234.56 ₼ |
|---|---|---|
| `az` | `{amount} ₼` (non-breaking space) | `1 234,56 ₼` |
| `ru` | `{amount} ₼` | `1 234,56 ₼` |
| `en` | `{amount} ₼` *or* `AZN {amount}` (admin context) | `1,234.56 ₼` or `AZN 1,234.56` |

For the English locale in user-facing mobile contexts we still use `₼` after the amount; English admin reports may use `AZN` prefix for export clarity.

#### 2.3.2 Money Storage and Rendering

- Backend stores `amount_minor` (qəpik, integer). Never floats.
- Frontend reads the integer, divides by 100, applies locale decimal formatting, appends symbol.
- Helper API (both backend and frontend): `formatMoney(minor, locale, currency='AZN')` → localized string. Exposed as a Resource transformer in PHP and a `LocaleAware` helper in Flutter.

#### 2.3.3 Money Pluralization

We do NOT pluralize the word "manat" or "qəpik" in numbered contexts because Azerbaijani convention drops the noun after the symbol. UI never displays "1 manat" or "5 manats"; it displays `1,00 ₼`. The pluralization machinery is therefore not needed for currency; it IS needed for counts (see §2.5).

### 2.4 Numbers

| Locale | Decimal | Thousands | Example |
|---|---|---|---|
| `az` | `,` | non-breaking space ` ` | `1 234,56` |
| `ru` | `,` | non-breaking space ` ` | `1 234,56` |
| `en` | `.` | `,` | `1,234.56` |

Backend uses PHP's `NumberFormatter::create(locale, DECIMAL)`. Flutter uses `NumberFormat.decimalPattern(locale)`. Neither is hand-rolled.

### 2.5 Pluralization

#### 2.5.1 ICU MessageFormat Everywhere

Plural-aware messages use ICU MessageFormat in both PHP (`symfony/translation` MessageFormatter) and Flutter (`intl` package). This avoids inventing a custom syntax.

Example key (Azerbaijani):

```
roster.user_count =
  {count, plural,
    one {# istifadəçi}
    other {# istifadəçi}}
```

Russian (4 forms — crucial; English/Azerbaijani translators must understand this):

```
roster.user_count =
  {count, plural,
    one {# пользователь}
    few {# пользователя}
    many {# пользователей}
    other {# пользователя}}
```

English:

```
roster.user_count =
  {count, plural,
    one {# user}
    other {# users}}
```

#### 2.5.2 Russian Plural Rules (Reference)

Russian noun forms vary by the last digit of the count:

- `one`: count ends in 1 (but not 11): 1, 21, 31, … `1 пользователь`
- `few`: count ends in 2/3/4 (but not 12/13/14): 2, 3, 4, 22, 23, … `2 пользователя`
- `many`: 0, 5-20, 25-30, …: 0, 5, 11, 13, 25, … `5 пользователей`
- `other`: fractional or "any" fallback: 1.5, 2.5, … `1.5 пользователя`

Engineers writing keys for Russian content MUST provide all four forms (or rely on `other` only when the noun has a single invariant form, which is rare).

#### 2.5.3 Azerbaijani Plural Note

Azerbaijani's CLDR rules list two categories (`one`, `other`), but in practice the noun form after a numeral does not change (`1 saat`, `5 saat`). Translators may produce identical `one` and `other` forms — this is correct, not a translation bug. The ICU machinery still requires both because Russian needs it and the runtime is shared.

### 2.6 Gender

Azerbaijani has no grammatical gender. Russian and English do, marginally, for some pronouns. No screen in MVP requires gendered output. If a future feature needs it (e.g. "He / She added you"), use ICU `select`:

```
{gender, select,
  male {{name} sizi əlavə etdi}
  female {{name} sizi əlavə etdi}
  other {{name} sizi əlavə etdi}}
```

This is unused in MVP; documenting the pattern only.

### 2.7 Capitalization

| Rule | Apply when |
|---|---|
| Sentence case (first word + proper nouns capitalised) | Headings, screen titles, buttons |
| Title Case (all major words capitalised) | NOT used — too "American" for AZ/RU |
| ALL CAPS | Never. Use bold or size to draw attention. Accessibility: screen readers spell-out caps. |
| `İ` vs `I` (Azerbaijani) | Capital "I" with dot is `İ`; capital dotless is `I`. They are distinct letters. Use the correct one. |

### 2.8 Punctuation & Whitespace

- Use non-breaking space (` `) between number and unit (`5 ₼`, `10 dəq`, `25 %`).
- Use proper quotation marks per locale: AZ and RU use «guillemets», EN uses "double quotes".
- Em dash `—` for parenthetical breaks in all three locales.
- No double spaces. Linter enforced.

### 2.9 Fonts & Glyph Coverage

The **Inter** font family covers all required glyphs:
- Latin Extended-A for Azerbaijani diacritics: `ş ç ğ ə ı ö ü İ`
- Cyrillic for Russian
- Latin for English

No locale-specific font fallback is configured. If a glyph is missing it is a CDN / packaging bug; alert.

### 2.10 Right-to-Left

Not applicable. All three locales are LTR. The code base is not RTL-aware. Adding RTL is documented in §8.2 as a Phase 3+ exercise.

---

## 3. Key Naming Convention

### 3.1 Format

```
<domain>.<area>[.<subkey>][.<variant>]
```

Lowercase. Dot-separated segments. Each segment is `snake_case` (lowercase letters, digits, underscores). No locale or environment information in the key.

### 3.2 Domain Vocabulary

The domain prefix MUST come from this fixed set:

| Prefix | Owns |
|---|---|
| `auth.*` | Login, OTP, biometrics, sessions, tokens |
| `common.*` | Shared atoms: Save, Cancel, Continue, Done, OK, Yes, No, Loading, Error |
| `devices.*` | Mobile devices list / detail / open flow |
| `errors.*` | **API error codes** — 1:1 with `error.code` in OpenAPI |
| `invitations.*` | Invitation list, accept, decline, expire copy |
| `notifications.*` | Inbox UI, notification settings UI (note: notification *content* is in DB, not lang/) |
| `payments.*` | Checkout, order detail, payment result, receipt |
| `privacy.*` | Consents, DSR, account deletion |
| `profile.*` | Profile screens, language, biometrics, security |
| `roster.*` | Owner roster management screens |
| `subscriptions.*` | Sub list / detail / renewal copy |
| `validation.*` | **Field-level validation** — 1:1 with Laravel validation rules |
| `admin.*` | Admin-panel only. Sub-namespaces under `admin.<module>.*` (e.g. `admin.devices.*`) |

New top-level prefixes require a one-line note in this section. Engineers do not invent prefixes on the fly.

### 3.3 Examples

| Key | English source string |
|---|---|
| `auth.otp.enter_code` | `Enter the 6-digit code` |
| `auth.otp.error.wrong_code` | `Wrong code. {attempts_left} attempts remaining.` |
| `auth.biometric.enable_cta` | `Enable Face ID / fingerprint` |
| `common.cancel` | `Cancel` |
| `common.save` | `Save` |
| `devices.list.empty.title` | `No devices yet` |
| `devices.list.empty.body` | `An owner can add you to a device.` |
| `devices.detail.open_cta` | `Open` |
| `devices.detail.suspended.owner_sub_expired_others_active` | `Your subscription has expired. The device still works for other users. Renew yours.` |
| `errors.subscription_required` | `Your subscription has expired.` |
| `errors.cooldown` | `Wait {retry_after_seconds}s before trying again.` |
| `errors.successor_required` | `A new owner must be assigned.` |
| `validation.required` | `This field is required.` |
| `validation.phone.format` | `Phone number is invalid.` |
| `admin.devices.list.title` | `Devices` |
| `admin.devices.action.disable.confirm.title` | `Disable this device?` |

### 3.4 Placeholders

Use **named** ICU placeholders (`{phone}`, `{retry_after_seconds}`, `{count}`), not positional (`{0}`, `{1}`).

A key's set of placeholders is part of its contract. If a placeholder set changes, treat it as a breaking change and version the key (`.v2`). Translators in other locales must update simultaneously; CI fails on placeholder mismatch.

Reserved placeholder names appear in §3.7.

### 3.5 Plural Keys

Keys whose value depends on a count MUST use ICU `{count, plural, ...}` syntax. Per-locale forms vary; AZ/EN provide `one`/`other`; RU provides all four.

Example:

```
# lang/az/roster.php
'user_count' => '{count, plural, one {# istifadəçi} other {# istifadəçi}}'

# lang/ru/roster.php
'user_count' => '{count, plural, one {# пользователь} few {# пользователя} many {# пользователей} other {# пользователя}}'
```

### 3.6 Key Stability

- **Keys are forever**, like database column names. Avoid renaming.
- If the meaning of a key drifts substantively, add a new key (`.v2` suffix) and migrate consumers. Retire the old key after two release cycles.
- Removing a key requires a CI grep across all clients to confirm no callers.
- A key MUST exist in all three locales. CI fails if any locale is missing.

### 3.7 Reserved Placeholder Names

These names have agreed semantics across the codebase. Use them when applicable instead of inventing synonyms.

| Placeholder | Meaning |
|---|---|
| `{name}` | A human full name |
| `{phone}` | A phone (already masked for display) |
| `{email}` | An email |
| `{count}` | A number for pluralization |
| `{amount}` | A locale-formatted money string |
| `{currency}` | Currency code or symbol |
| `{date}` | A locale-formatted date |
| `{time}` | A locale-formatted time |
| `{datetime}` | A locale-formatted date + time |
| `{device}` | A device label |
| `{retry_after_seconds}` | Cooldown / rate-limit retry hint |
| `{days_remaining}` | Subscription days remaining |

### 3.8 What Must Be a Key

Every user-visible string MUST be a key. No inline literals in Blade templates or Flutter widgets.

CI rule: scan `.blade.php` and `.dart` (excluding `tests/` and `*.g.dart`) for string literals of length ≥ 3 that are not inside an `__()` / `AppLocalizations.of()` call. The build fails on detection. An opt-out comment `// i18n-ignore` is permitted only for genuinely locale-independent strings (asset names, technical IDs, etc.) and requires PR review.

---

## 4. Folder Structure

### 4.1 Backend — `lang/`

The backend repo holds the master copy.

```
lang/
├── az/                              ← source-of-truth locale
│   ├── auth.php
│   ├── common.php
│   ├── devices.php
│   ├── errors.php                   ← keys referenced by API
│   ├── invitations.php
│   ├── notifications.php
│   ├── payments.php
│   ├── privacy.php
│   ├── profile.php
│   ├── roster.php
│   ├── subscriptions.php
│   ├── validation.php
│   └── admin/
│       ├── audit.php
│       ├── dashboard.php
│       ├── devices.php
│       ├── feature_flags.php
│       ├── lookups.php
│       ├── notification_templates.php
│       ├── orders.php
│       ├── refunds.php
│       ├── reports.php
│       ├── settings.php
│       ├── subscriptions.php
│       └── users.php
├── en/
│   └── ... (same tree)
└── ru/
    └── ... (same tree)
```

Each file returns a flat or nested PHP array. Nested form is preferred for readability when a screen has many keys:

```
# lang/az/devices.php (sketch — not code; structural illustration)
return [
  'list' => [
    'title' => '…',
    'empty' => [
      'title' => '…',
      'body'  => '…',
    ],
  ],
  'detail' => [
    'open_cta' => '…',
    'suspended' => [
      'owner_sub_expired_others_active' => '…',
      'subscription_expired' => '…',
      'device_disabled' => '…',
    ],
  ],
  'user_count' => '{count, plural, one {# istifadəçi} other {# istifadəçi}}',
];
```

Access in Blade:

```
{{ __('devices.detail.open_cta') }}
{{ trans_choice('devices.user_count', $count, ['count' => $count]) }}
```

### 4.2 Mobile — `lib/l10n/`

The mobile repo holds **mirror** copies in ARB format for Flutter `gen-l10n`.

```
mobile/
├── lib/
│   └── l10n/
│       ├── intl_az.arb            ← MUST mirror lang/az/ keys
│       ├── intl_en.arb
│       ├── intl_ru.arb
│       └── l10n.yaml              ← config: arb-dir, template-arb-file, output-class
```

Keys in `.arb` follow the same dotted notation, transformed to method-callable form when generated (dots become underscores in the generated Dart class):

```
intl_az.arb entry:   "devices.detail.open_cta": "Aç"
Generated method:    AppLocalizations.of(context)!.devices_detail_open_cta
```

Optional helper extension to keep the call site dot-natural:

```
context.l10n('devices.detail.open_cta')
```

### 4.3 Sync Script (`tools/i18n-sync`)

A single source-of-truth check, runnable locally and in CI:

| Check | Failure mode |
|---|---|
| Every key in `lang/az/**` exists in `lang/en/**` and `lang/ru/**` | Build fails listing missing keys |
| Every key in `lang/az/**` exists in `lib/l10n/intl_az.arb` (and likewise for en/ru) | Build fails |
| Placeholder sets match across locales for the same key | Build fails |
| Plural form coverage is sufficient (RU keys have all four; AZ/EN have at least `one` and `other`) | Build fails |
| Every `errors.*` key has a matching `error.code` defined in `openapi/v1.yaml` (and vice versa) | Build warns (allow lead time for new errors) |

A "missing keys" report can be exported to CSV for translators.

### 4.4 Admin Localization Files

Admin strings live under `lang/{locale}/admin/*.php`. They are isolated so:
- A translator working only on admin strings has a clean scope.
- Admin copy can have a more formal tone than the mobile app without polluting the user-facing namespace.

### 4.5 Legal & CMS Content

Static legal documents are versioned files:

```
public/legal/
├── terms-v3/
│   ├── az.html
│   ├── en.html
│   └── ru.html
├── privacy-v2/
│   ├── az.html
│   ├── en.html
│   └── ru.html
└── help-faq/
    ├── az.html
    ├── en.html
    └── ru.html
```

URL pattern: `https://api.salamhayetimiz.az/legal/{slug-vN}/{locale}.html`

**Versioning rule:** version number (`-vN`) bumps on any substantive change. `user_consents.document_version` references the slug-version (e.g. `terms-v3`). Older versions remain reachable until purged.

### 4.6 Notification Templates (Database)

Per `DATABASE_ARCHITECTURE.md` §7.1–7.2, content lives in the `notification_template_locales` table with `(notification_template_id, locale)` unique. Admin edits via A-86 / A-87. The `body` uses Twig-style placeholders matching backend variable conventions.

Notification content does **not** live in `lang/` — its lifecycle is real-time admin edits, not code deploys.

---

## 5. Admin Localization Rules

### 5.1 Default Locale

`az`. Stored per admin in `admin_users.preferred_language ENUM('az','ru','en') NOT NULL DEFAULT 'az'`.

*(Schema note: this column is implied by `BACKEND_ARCHITECTURE.md` §7 / Auth domain but not currently spelled out in `DATABASE_ARCHITECTURE.md` §1.2. Add it in a follow-up DB revision. Until then, admins default to `az` server-side and persist their choice in the new column when it lands.)*

### 5.2 Locale Switcher

A topbar control on every admin page exposes three options. Selecting:

1. PATCHes the admin's preference via the admin auth endpoint.
2. Reloads the current route in the new locale.
3. Records an `audit_log` entry `admin.locale.changed` with from/to.

No URL change (`/admin/v1/...` stays the same regardless of locale). The locale is a header / cookie concern, not a path concern, to avoid URL multiplication.

### 5.3 Locale Resolution Per Request

`LocaleNegotiator` middleware (Backend Architecture §6.5) resolves admin requests as:

1. Authenticated admin's `preferred_language` (DB).
2. Else `Accept-Language` (only when no auth — login screen).
3. Else `az`.

### 5.4 Server-Rendered Formatting

Admin Blade pages format dates, numbers, and money via `IntlDateFormatter` and `NumberFormatter`, given the resolved locale. There is no JS-side formatting on the admin (Blade is server-rendered); avoid mixing formatting concerns.

### 5.5 Email & PDF Receipts (Phase 2)

Outbound emails / PDFs sent to a user use the *user's* `preferred_language`, never the admin's. This matters when a super admin issues a refund: the receipt is in the customer's language, not the operator's.

### 5.6 Mixed Locale Cases

| Case | Locale used |
|---|---|
| Admin viewing a user's notification history | Notification content rendered in the **user's** locale (as it was sent); admin chrome in the admin's locale |
| Admin reading audit-log `actor_label` strings | `actor_label` is a snapshot; do not retranslate |
| Admin editing a notification template | The currently-edited locale tab dictates which row is shown; chrome in admin's locale |

The pattern: chrome follows admin; payload follows the entity's locale of origin.

### 5.7 Admin Translator Workflow Notes

- Admin strings tend to be terse and operational. Translators should preserve the "operator voice" — direct, factual, no marketing flourishes.
- Avoid idioms; admin operators may rotate and not be native AZ speakers.
- Per-action confirmation copy MUST be precise: "Are you sure?" → "Bu əməliyyatı təsdiqləyirsiniz?" is preferred over informal "Davam edək?".

---

## 6. Mobile Localization Rules

### 6.1 Default Locale on First Run

Algorithm:

1. Read `Platform.localeName` (device locale).
2. If language part matches `az`, `ru`, or `en` → use that.
3. Otherwise → fall back to `az`.

After the user logs in, the server's `users.preferred_language` becomes authoritative. If the server says `ru` but the device is `en`, the app uses `ru` (server wins; multi-device consistency).

### 6.2 Locale Switcher (S-52 Language)

User selects one of three options. Effect:

1. Updates the in-memory `Locale` (rebuild via `MaterialApp.locale`).
2. Persists to local secure storage.
3. PATCH `/v1/me` with `{ preferred_language: 'ru' }`.
4. UI re-renders immediately. No restart.

### 6.3 ARB Bundling

`gen-l10n` compiles `intl_{locale}.arb` files into a `AppLocalizations` Dart class shipped in the app binary. Updates require an app release.

OTA translation updates are deliberately out of MVP scope. They would require:
- Remote ARB delivery (Firebase Remote Config or a custom CDN).
- Bundle hash + signature.
- Per-key cache invalidation.

Phase 2+ consideration.

### 6.4 Fallback Chain at Render Time

If a key lookup fails in the active locale:

1. Fall back to `az` (project default).
2. If still missing → render the key itself (`devices.detail.open_cta`) and emit a `missing_key` telemetry event with `{key, locale, build_version}`.

Empty strings or `null` MUST NOT be rendered. A visibly-broken translation is preferable to a silently-missing one.

### 6.5 Formatting Helpers

Centralized via a `LocaleAware` helper (one entry point per concern):

| Concern | Method | Underlying |
|---|---|---|
| Date | `LocaleAware.formatDate(date, style: 'short|medium|long')` | `intl` `DateFormat` |
| Time | `LocaleAware.formatTime(date)` | `DateFormat.Hm` (24-hour) |
| Date-time | `LocaleAware.formatDateTime(date, style: ...)` | composition |
| Relative time | `LocaleAware.formatRelative(date)` | `intl` `IntlRelativeFormat` (or `timeago` per locale) |
| Money | `LocaleAware.formatMoney(minor, currency: 'AZN')` | `NumberFormat.currency` with `symbol: '₼'` (or 'AZN' for `en`) and locale-driven decimal/group |
| Number | `LocaleAware.formatNumber(n, fractionDigits: 0..2)` | `NumberFormat.decimalPattern` |

Widgets never call `intl` directly. The indirection makes locale-rule changes one-place edits.

### 6.6 Plural Resolution

Use `Intl.plural()` via the generated AppLocalizations for keys with `{count, plural, …}`. Translators do not write Dart; they fill `.arb` placeholders. The runtime evaluates the right form per the active locale.

### 6.7 Images and Locale-Specific Assets

No locale-specific assets in MVP. Onboarding illustrations are language-neutral (no embedded text).

If a future asset embeds text:

```
assets/images/
└── feature_name/
    ├── illustration_az.png
    ├── illustration_en.png
    └── illustration_ru.png
```

Loader picks by active locale; fallback `az`.

### 6.8 Push Notification Localization

Push notifications are dispatched by the backend (Notifications module). Backend uses the recipient user's `preferred_language` to fetch the template from `notification_template_locales`. The push payload's title and body are *already-rendered strings* — the mobile app does not re-localize them.

This is the one exception where the user sees server-rendered localized text: push notifications. The mechanism is dictated by FCM not supporting client-side key resolution at notification display time (FCM displays the text it receives).

---

## 7. Error Message Localization Rules

### 7.1 The Contract

Every error response from the API carries:

```
error.code           — stable machine-readable token (e.g. "subscription_required")
error.message_key    — translation lookup key (e.g. "errors.subscription_required")
error.details        — optional object with placeholder values
error.request_id     — correlation token for support
```

`error.message` (translated string) is **removed** compared to v1.1. Required OpenAPI v1.2 change documented in Appendix D.

### 7.2 1:1 Mapping Discipline

Every `error.code` defined in the OpenAPI MUST have:

- A corresponding `errors.<code>` key in `lang/az/errors.php`.
- The same key in `lang/en/errors.php` and `lang/ru/errors.php`.
- A matching key in each of `lib/l10n/intl_az.arb`, `intl_en.arb`, `intl_ru.arb`.

CI invariant `ci:i18n:error-codes-cover`:

1. Extract every `error.code` from the OpenAPI (it appears in `examples` and in our enumerated codes list).
2. Assert each has `errors.<code>` in all three locales backend-side.
3. Assert each is exported in the mobile bundles.
4. Build fails on miss.

### 7.3 Validation Error Envelope

A separate envelope from the generic `Error`. The v1.1 OpenAPI returns `fields: {phone: ["error string"]}` — strings that violate the "no translated strings" rule. The v1.2 form is structured:

```
{
  "error": {
    "code": "validation_failed",
    "fields": {
      "phone": [
        { "rule": "required",     "key": "validation.required",     "params": {} },
        { "rule": "phone",        "key": "validation.phone.format", "params": {} }
      ],
      "amount_minor": [
        { "rule": "min",          "key": "validation.min.numeric",  "params": { "min": 1 } }
      ]
    },
    "request_id": "01J…"
  }
}
```

The client iterates `fields[fieldName]`, resolves each `key` through its bundle (with `params` interpolated), and displays.

### 7.4 Variable Substitution

Error keys with placeholders use ICU named placeholders. The `details` (for generic `Error`) or `params` (for `ValidationError`) carry the values.

Example:

```
errors.cooldown:  "Yenidən cəhd üçün {retry_after_seconds} saniyə gözləyin."

API response:
{
  "error": {
    "code": "cooldown",
    "message_key": "errors.cooldown",
    "details": { "retry_after_seconds": 3 }
  }
}
```

Client renders: `Yenidən cəhd üçün 3 saniyə gözləyin.`

### 7.5 Validation Rule Catalogue

Reserved `validation.*` keys mirror Laravel validation rules:

| Rule | Key | Placeholder params |
|---|---|---|
| `required` | `validation.required` | — |
| `email` | `validation.email` | — |
| `phone` (custom) | `validation.phone.format` | — |
| `min` (numeric) | `validation.min.numeric` | `{min}` |
| `min` (string) | `validation.min.string` | `{min}` |
| `max` (numeric) | `validation.max.numeric` | `{max}` |
| `max` (string) | `validation.max.string` | `{max}` |
| `in` | `validation.in` | (none — only enum value displayed) |
| `unique` | `validation.unique` | — |
| `confirmed` | `validation.confirmed` | — |
| `regex` | `validation.regex` | — |

Custom validation rules add new keys following the same prefix.

### 7.6 Logging vs Display

Backend application logs:

- Include the structural `code` and `request_id`. Never the translated string.
- Include `details` redacted per `Redactor` rules.

Mobile / admin logs (Sentry-like crash reports):

- Include the `code` for grouping.
- Include the rendered translated string for context (this is for engineers, not users; OK to leak the localized string here).

### 7.7 Generic vs Specific

`errors.internal_error` is generic and intentional. The user sees a generic apology; engineers see the `request_id` in the UI and can correlate to logs. Don't add `errors.internal_error.database_timeout` etc. — granularity belongs in logs, not in user-facing error keys.

### 7.8 Notification Content vs UI Errors

These are **different streams** (see §1.1):

| Stream | Source | Locale resolved by |
|---|---|---|
| API error response | OpenAPI codes | **Client** (key resolution) |
| Push / SMS notification | `notification_template_locales` table | **Server** (recipient's preferred_language) |

A confusion to avoid: do NOT put notification content under `errors.*`. Notification content lives in DB; error keys live in code.

---

## 8. Future Language Expansion Strategy

### 8.1 Adding a New Language (e.g. Turkish)

Step-by-step checklist:

1. **Decision** — product confirms the demand and budgets translator time.
2. **CLDR & plural form check** — Turkish has 2 forms (`one`, `other`). Confirm in `intl` library data.
3. **Add to enum sources** (backend migration):
   - `users.preferred_language` enum gains `tr`.
   - `admin_users.preferred_language` enum gains `tr`.
   - `notification_template_locales.locale` enum gains `tr`.
   - Any other enum referencing supported locales.
4. **Add to application config**:
   - `config('app.available_locales')` adds `tr`.
   - Flutter `supportedLocales` adds `Locale('tr')`.
5. **Create files**:
   - `lang/tr/` directory mirroring `lang/az/` structure exactly.
   - `lib/l10n/intl_tr.arb`.
   - `public/legal/{*}/tr.html` for each versioned legal doc.
6. **Translate** — native translator works through the missing-keys report.
7. **Seed notification template locales** — add a `tr` row for every active `notification_template` (admin task, can be batched via a migration script).
8. **Run sync check** — must pass with zero missing keys.
9. **QA** — screen-by-screen review by native speaker; pseudolocalization smoke if available.
10. **Add to switcher UIs** — S-02 / S-52 (mobile) and the admin topbar.
11. **Deploy backend + admin first**, then mobile in next release cycle.

**Estimated effort:** 8–12 engineering days for plumbing + translator time (varies by translator throughput; 5,000–8,000 strings for a fresh language).

### 8.2 Adding a Right-to-Left Language

If Arabic, Hebrew, or Persian is ever added, the effort is significantly more than a new LTR language. Additional work:

- **Mobile (Flutter)** —
  - Flutter handles `Directionality` automatically when a locale is RTL, but every **custom widget** must be audited for hardcoded `EdgeInsets.left/right`, `Alignment.centerLeft/Right`, etc. Replace with `EdgeInsetsDirectional` and `AlignmentDirectional`.
  - Icons that carry direction (arrows, chevrons, swipe affordances) must mirror.
  - Animations that translate left/right must mirror.
- **Admin (Bootstrap 5)** —
  - Use Bootstrap 5's RTL CSS variant (`bootstrap.rtl.min.css`) switched in based on locale.
  - Audit custom CSS for `float`, `margin-left/right`, `text-align: left/right`.
- **Layout audit** —
  - Bidirectional text mixing (e.g. an Arabic phrase with a Latin device serial) needs `dir="auto"` or BiDi marks.
- **Sort & input direction** —
  - Date pickers, number inputs, currency display patterns may change.
- **QA** —
  - Pseudolocalization with an RTL `qpa-Arab` flavour catches LTR-bleed bugs.

**Estimated additional effort:** 10–15 engineering days *on top of* the new-language baseline. Plan an Arabic addition as a 4-week project, not a 2-week one.

### 8.3 Translation Management System (Phase 2)

When the keyset grows past ~5,000 strings or the translator pool past two people, lift translations into a TMS:

| Option | Pros | Cons |
|---|---|---|
| **Crowdin** | Strong CI integration; good free tier for OSS-style flows; supports ARB and PHP arrays | Per-seat pricing scales |
| **Lokalise** | Excellent translator UX; strong glossary / TM features; API-first | Pricier |
| **Phrase** | Enterprise-y; deep tooling | Most expensive |

Source of truth stays in Git; the TMS is a UX over the same files. Workflow:

1. Engineer adds AZ source string in `lang/az/...` and pushes to `main`.
2. CI exports new/changed keys to TMS.
3. Translator works in TMS UI.
4. TMS exports translations as a PR; CI runs sync check.
5. Merge.

This is the only way translator productivity scales past ~3 people.

### 8.4 Pseudolocalization

A synthetic `qps-ploc` (or `en-xa`) locale generates exaggerated translations: doubled vowels, accented diacritics, +30 % length, bracketed.

Example: `Continue` → `[Çóóñtïïñúüé!!!]`.

QA value:
- Catches hardcoded literals (they appear unchanged).
- Catches layout overflow (longer strings expose tight widths).
- Catches encoding bugs (diacritics fail to render).

Phase 2: wire `qps-ploc` as an optional build flavour; run screenshot tests against it in CI.

### 8.5 Translation Memory & Glossary

Maintain a **product glossary** with non-translatable brand terms and preferred translations for ambiguous concepts:

| Term | AZ | EN | RU | Notes |
|---|---|---|---|---|
| Salam Həyətimiz | Salam Həyətimiz | Salam Həyətimiz | Salam Həyətimiz | Brand — never translated |
| Owner (device) | Sahib | Owner | Владелец | Not "Administrator" |
| Additional user | Əlavə istifadəçi | Additional user | Дополнительный пользователь | Distinct from "guest" |
| Open (a gate) | Aç | Open | Открыть | Verb form |
| Suspended (device) | Dayandırılıb | Suspended | Приостановлено | Past participle |
| Subscription | Abonelik | Subscription | Подписка | Not "membership" |
| Recovery code | Bərpa kodu | Recovery code | Код восстановления | — |

Glossary lives in repo (`docs/i18n/glossary.md`) and is required reading for any new translator.

### 8.6 Versioning Localized Content

For substantive UX rewrites that change a key's meaning:

- Add a new key with `.v2` suffix.
- Old key stays in lang files for at least 2 release cycles.
- After deprecation window, run a CI grep to confirm no callers, then remove.

This pattern avoids both translator confusion and silent breakage.

---

## Appendix A — Locale Code Reference

| Locale | BCP-47 | ISO 639-1 | Endonym | English name | Plural forms (CLDR) |
|---|---|---|---|---|---|
| Azerbaijani | `az` | az | Azərbaycan dili | Azerbaijani | one, other |
| English | `en` | en | English | English | one, other |
| Russian | `ru` | ru | Русский | Russian | one, few, many, other |

We do **not** use region-qualified tags (`az-AZ`, `ru-RU`, `en-GB`). Region-agnostic. If a region distinction is ever needed (e.g. `pt-BR` vs `pt-PT`-style), revisit.

---

## Appendix B — Date / Time Format Quick Reference

| Format | AZ | EN | RU |
|---|---|---|---|
| Short date | 09.06.2026 | 09/06/2026 | 09.06.2026 |
| Medium date | 9 İyun 2026 | 9 Jun 2026 | 9 июн. 2026 г. |
| Long date | 9 İyun 2026, Çərşənbə axşamı | Tuesday, 9 June 2026 | вторник, 9 июня 2026 г. |
| Time (24h) | 14:30 | 14:30 | 14:30 |
| Short date + time | 09.06.2026, 14:30 | 09/06/2026, 14:30 | 09.06.2026, 14:30 |
| Now (relative) | indi | now | сейчас |
| 5 minutes ago | 5 dəqiqə əvvəl | 5 min ago | 5 минут назад |
| 2 hours ago | 2 saat əvvəl | 2 hours ago | 2 часа назад |
| Yesterday | dünən | yesterday | вчера |
| 3 days ago | 3 gün əvvəl | 3 days ago | 3 дня назад |
| 2 weeks ago | 2 həftə əvvəl | 2 weeks ago | 2 недели назад |

---

## Appendix C — Translation File Catalogue

For each domain prefix, the table below names the file and gives a rough sense of size and edit cadence.

| File | Domain | Approx. key count (MVP) | Edit cadence |
|---|---|---|---|
| `auth.php` | `auth.*` | ~40 | Low |
| `common.php` | `common.*` | ~30 | Low |
| `devices.php` | `devices.*` | ~80 | Medium |
| `errors.php` | `errors.*` | ~50 (matches OpenAPI codes) | Low (tied to API contract) |
| `invitations.php` | `invitations.*` | ~30 | Low |
| `notifications.php` | `notifications.*` | ~30 | Low (note: notification *content* in DB, not here) |
| `payments.php` | `payments.*` | ~80 | Medium |
| `privacy.php` | `privacy.*` | ~30 | Low |
| `profile.php` | `profile.*` | ~50 | Low |
| `roster.php` | `roster.*` | ~40 | Medium |
| `subscriptions.php` | `subscriptions.*` | ~50 | Medium |
| `validation.php` | `validation.*` | ~25 | Low |
| `admin/audit.php` | `admin.audit.*` | ~30 | Low |
| `admin/dashboard.php` | `admin.dashboard.*` | ~20 | Medium |
| `admin/devices.php` | `admin.devices.*` | ~80 | Medium |
| `admin/feature_flags.php` | `admin.feature_flags.*` | ~15 | Low |
| `admin/lookups.php` | `admin.lookups.*` | ~30 | Low |
| `admin/notification_templates.php` | `admin.notification_templates.*` | ~25 | Low |
| `admin/orders.php` | `admin.orders.*` | ~60 | Medium |
| `admin/refunds.php` | `admin.refunds.*` | ~30 | Medium |
| `admin/reports.php` | `admin.reports.*` | ~40 | Medium |
| `admin/settings.php` | `admin.settings.*` | ~25 | Low |
| `admin/subscriptions.php` | `admin.subscriptions.*` | ~40 | Medium |
| `admin/users.php` | `admin.users.*` | ~40 | Medium |

**Total MVP key estimate:** ~900–1,100 keys × 3 locales = ~2,700–3,300 translation entries. Tractable without a TMS; revisit at ~5,000.

---

## Appendix D — Required OpenAPI v1.2 Changes

This spec mandates the following OpenAPI revision (apply when localization implementation lands; do not apply now):

### D.1 `Error` schema

**Before (v1.1):**

```
Error:
  type: object
  required: [error]
  properties:
    error:
      type: object
      required: [code, message]
      properties:
        code: ...
        message_key: ...
        message: ...        ← server-translated string
        details: ...
        request_id: ...
```

**After (v1.2):**

```
Error:
  type: object
  required: [error]
  properties:
    error:
      type: object
      required: [code, message_key]
      properties:
        code: ...
        message_key: ...    ← now required; client uses this to look up
        details: ...
        request_id: ...
        # message field removed
```

### D.2 `ValidationErrorEnvelope` schema

**Before (v1.1):**

```
fields:
  type: object
  additionalProperties:
    type: array
    items: { type: string }    ← translated strings
```

**After (v1.2):**

```
fields:
  type: object
  additionalProperties:
    type: array
    items:
      type: object
      required: [rule, key]
      properties:
        rule: { type: string }            # e.g. "required", "phone", "min"
        key:  { type: string }            # e.g. "validation.required"
        params: { type: object }          # placeholder values
```

### D.3 Notes

- This is a backwards-incompatible change to the body shape and bumps the spec to **v1.2.0**.
- Clients written against v1.1 that consumed `error.message` must migrate to resolving `error.message_key`.
- The cutover is coordinated: backend ships v1.2 in the same release as the mobile/admin clients that handle it. No mixed state.

---

## Appendix E — Open Items for Sign-off

1. **Admin English date format** — current spec uses D/M/Y for cross-locale consistency. Confirm with product. (Defaulting to international, not US.)
2. **`admin_users.preferred_language` column** — not yet in `DATABASE_ARCHITECTURE.md`; add in a small follow-up DB revision.
3. **OTA translation updates** — confirm deferred to Phase 2+.
4. **TMS choice (Crowdin vs Lokalise vs Phrase)** — Phase 2 decision; budget needed.
5. **Pseudolocalization in CI** — Phase 2 decision; modest effort.
6. **Glossary ownership** — who maintains `docs/i18n/glossary.md`? Product or engineering?
7. **Mobile fallback nuance** — if a device upgrades from `az` to an unsupported locale, do we keep the user's last explicit selection rather than re-deriving from device? (Recommended: yes — respect user choice.)
8. **Migration timing for v1.2 OpenAPI** — coordinate backend + mobile + admin in one release cycle.

---

*End of Localization Specification v1.0.*
