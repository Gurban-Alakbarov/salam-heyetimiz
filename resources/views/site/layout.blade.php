@php
    $title = $t($page['title']);
    $shareTitle = $t($page['share_title'] ?? '') ?: $title;
    $shareText = $t($page['share_text'] ?? '') ?: $t($page['description']);
    $base = rtrim($site['base_url'], '/');
    $abs = fn (string $p, string $l) => $base.$langUrl($p, $l);
    $hero = collect($page['sections'])->firstWhere('type', 'home-hero');
    $navLinks = collect($site['menu'])->filter(fn ($k) => isset($pages[$k]));
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}"><head><meta charset="utf-8"/><meta content="width=device-width,initial-scale=1" name="viewport"/><meta content="#ffffff" name="theme-color"/><meta content="{{ $t($page['description']) }}" name="description"/><title>{{ $title }} | Salam Sesame</title><link rel="canonical" href="{{ $abs($path, $lang) }}"/>
@foreach ($langs as $l)<link rel="alternate" hreflang="{{ $l }}" href="{{ $abs($path, $l) }}"/>
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $abs($path, 'az') }}"/><link rel="icon" href="/landing/favicon.svg" type="image/svg+xml"/><link rel="icon" href="/landing/favicon-32.png" sizes="32x32" type="image/png"/><link rel="apple-touch-icon" href="/landing/apple-touch-icon.png"/><link rel="preconnect" href="https://fonts.googleapis.com"/><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400..800&amp;display=swap"/>
@if ($hero)<link rel="preload" as="image" href="{{ $hero['image'] }}" fetchpriority="high"/>
@endif
<link rel="stylesheet" href="/landing/site.css?v={{ $version('site.css') }}"/><meta property="og:type" content="website"/><meta property="og:site_name" content="Salam Sesame"/><meta property="og:locale" content="{{ $shareLocale }}"/><meta property="og:url" content="{{ $abs($path, $lang) }}"/><meta property="og:title" content="{{ $shareTitle }}"/><meta property="og:description" content="{{ $shareText }}"/><meta property="og:image" content="{{ $base }}/landing/og-image.jpg"/><meta property="og:image:width" content="1200"/><meta property="og:image:height" content="630"/><meta property="og:image:alt" content="{{ $text['share_image_alt'] }}"/><meta name="twitter:card" content="summary_large_image"/></head>
<body>
@include('site.parts.header')
<main id="main"><div class="page-panel" data-page="{{ $page['key'] }}" id="page-{{ $page['key'] }}">
@foreach ($page['sections'] as $s)
@include('site.sections.'.$s['type'])
@endforeach
</div></main>
@include('site.parts.footer')
<div aria-live="polite" class="toast" id="toast" role="status"></div>
<script id="site-texts" type="application/json">{!! json_encode(collect($text)->only(['menu_open', 'menu_close', 'msg_demo', 'msg_busy', 'msg_failed', 'msg_offline']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script src="/landing/site.js?v={{ $version('site.js') }}"></script></body></html>
