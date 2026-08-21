# DESIGN SYSTEM

> Planning only — no code, no widgets. A modular, token-driven design system under `design_system/`. **Every screen composes ONLY these tokens + components** — no raw, ad-hoc-styled Material widgets in `features/`. Enforced by lint conventions + code review + golden tests.

---

## 1. Structure

```
design_system/
  tokens/
    colors.dart        # brand, semantic, neutral ramp (+ dark variants)
    typography.dart     # text styles (display/title/body/label)
    spacing.dart        # 2,4,8,12,16,20,24,32,40,48
    radius.dart         # xs8, sm12, md16, lg20, full
    elevation.dart      # e0..e4 levels
    shadows.dart        # token shadows per elevation (light/dark aware)
    durations.dart      # motion: fast120, base200, slow320 + curves
    breakpoints.dart    # phone/tablet thresholds (responsive-ready)
    icons.dart          # semantic icon set (maps names → IconData/SVG)
    assets.dart         # logo, illustration, lottie paths
  theme/
    app_theme.dart      # ThemeData light + dark, built FROM tokens only
    color_scheme.dart   # Material 3 ColorScheme (light/dark) from tokens
    text_theme.dart     # TextTheme from typography tokens
    component_themes.dart # per-component theming (buttons, inputs, dialogs…)
  components/
    …(see §3)
  gallery/
    component_gallery.dart  # debug-only "storybook" screen for visual QA + goldens
```

**Single source of truth:** light + dark themes both derive from the same tokens. No feature ever hardcodes a color, size, radius, shadow, or duration — it references a token.

---

## 2. Tokens (the foundation)

| Token group | Contents |
|---|---|
| **Colors** | Brand primary `#6D28D9` (+ on-primary, container); semantic `success/warning/danger/info` (+ container/on); neutral ramp `n0…n900`; surface/background/outline; **explicit dark-mode variants** for each |
| **Typography** | scale: `displayL/M`, `titleL/M/S`, `bodyL/M/S`, `labelL/M/S`; one font family (+ fallback); weights 400/500/600/700; az/ru/en glyph coverage verified |
| **Spacing** | 4-based scale (2…48) — all paddings/gaps use it |
| **Radius** | xs(8) sm(12) md(16) lg(20) full — cards md, sheets lg, chips full |
| **Elevation** | e0–e4 mapped to shadow tokens (subtle, light/dark aware) |
| **Shadows** | per-elevation shadow specs; dark mode uses lower-opacity/elevation tints |
| **Icons** | semantic mapping (e.g. `AppIcons.open`, `.device`, `.subscription`) → consistent icon set; SVG-first |
| **Animations** | durations + standard curves; page transitions; micro-interactions (button press, OTP success pulse, skeleton shimmer) |

---

## 3. Components (the only widgets screens use)

### Inputs & actions
| Component | Variants / states |
|---|---|
| **AppButton** | primary · secondary · tertiary/text · destructive; sizes sm/md/lg; states: idle/loading(spinner)/disabled; leading/trailing icon |
| **AppTextField** | label, helper, error, prefix/suffix, password toggle; states default/focused/error/disabled |
| **OtpField** | 6-box code input; auto-advance/paste; shake-on-error; success state |
| **SearchField** | debounced search input + clear + leading icon |
| **AppChip / AppToggle / AppCheckbox / AppRadio** | selection controls (token-styled) |

### Containers & surfaces
| Component | Use |
|---|---|
| **AppCard** | elevated/outlined content container (device card, order card) |
| **AppListTile** | standard list row (leading/title/subtitle/trailing) |
| **AppDialog** | confirm/alert/custom; primary+cancel actions |
| **AppBottomSheet** | modal sheet (open-barrier sheet, pickers, confirmations) |
| **AppDivider / AppSection** | layout separators + section headers |
| **AppAvatar** | user/device avatar with fallback initials |

### Feedback & state
| Component | Use |
|---|---|
| **AppLoading** | full-screen overlay · inline spinner · in-button spinner |
| **AppSkeleton** | shimmer placeholders: list-skeleton, card-skeleton, detail-skeleton |
| **EmptyState** | icon + message + optional CTA (no data) |
| **ErrorState** | message + **Retry** (mapped from `Failure`) |
| **SuccessState** | confirmation screen/inline (e.g. payment success, open success) |
| **AppSnackBar** | success/error/info transient bar (bottom) |
| **AppToast** | lightweight top toast (non-blocking notices) |
| **ConnectivityBanner** | persistent "offline / last updated …" banner |
| **StatusBadge** | typed badge for device/order/subscription/refund status (color + label from tokens) |

### Navigation & scaffold
| Component | Use |
|---|---|
| **AppScaffold** | consistent safe-area + app bar + connectivity banner + bottom-nav slot |
| **AppTopBar (AppBar)** | title, back, actions; large/small variants |
| **AppBottomNav + AppNavItem** | the 4-tab bottom navigation (Home·Devices·Orders·Profile) |
| **AppTabBar** | in-page tabs (e.g. Device Detail: Overview/Stats/History/Commands) |
| **AppRefreshIndicator** | standardized pull-to-refresh wrapper |

---

## 4. Rules

1. **Screens import only `design_system` + feature widgets** — never style raw `ElevatedButton`/`Container` with literal colors/sizes.
2. **No magic numbers / hex colors** in `features/` — only token references; a lint/review gate flags violations.
3. **Every data screen** uses the state components (loading→skeleton, empty→EmptyState, error→ErrorState) — uniform UX, no bespoke spinners.
4. **Dark mode is first-class** — every token + component has a dark variant; never a one-off dark hack.
5. **Status colors** flow through `StatusBadge` + semantic tokens — consistent across devices/orders/subscriptions.
6. **Component Gallery** (`gallery/`) renders every component in light+dark×az/en/ru → the basis for **golden tests** (`RELEASE_PLAN.md §3`).

---

## 5. Long-term advantage
A token + component contract means: rebrand/theme = edit tokens (one place); a new screen = compose existing components (fast, consistent); visual QA = the gallery + goldens (regression-safe); accessibility (contrast, text scaling) tuned centrally. New modules (family, visitors, marketplace) inherit the system for free.
