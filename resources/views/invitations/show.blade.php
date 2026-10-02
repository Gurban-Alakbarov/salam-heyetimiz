<!doctype html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Salam Həyətimiz — dəvət</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; background: #f4f7f2; color: #1f2a1a; }
        main { max-width: 420px; margin: 0 auto; padding: 32px 16px; }
        .card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { line-height: 1.5; margin: 0 0 12px; }
        .muted { color: #667060; font-size: 14px; }
        a.btn { display: block; text-align: center; padding: 14px; border-radius: 12px; text-decoration: none; font-weight: 600; margin-top: 12px; }
        .primary { background: #00B800; color: #fff; }
        .secondary { background: #eef3ea; color: #1f2a1a; }
    </style>
</head>
<body>
<main>
    <div class="card">
        @if ($invite)
            <h1>Salam, {{ $invite['first_name'] }}!</h1>
            <p><strong>{{ $invite['inviter_name'] }}</strong> sizi Salam Həyətimiz tətbiqinə dəvət edir.</p>
            <p class="muted">Dəvət {{ $invite['email_masked'] }} ünvanı üçündür. Qeydiyyatda bu email-i istifadə edin.</p>
            <a class="btn primary" id="open" href="{{ $openUrl }}">Tətbiqdə aç</a>
            @if ($storeAndroid)<a class="btn secondary" href="{{ $storeAndroid }}">Google Play</a>@endif
            @if ($storeIos)<a class="btn secondary" href="{{ $storeIos }}">App Store</a>@endif
        @else
            <h1>Dəvət etibarsızdır</h1>
            <p class="muted">Bu dəvətin vaxtı bitib, ləğv edilib və ya artıq istifadə olunub. Yeni dəvət üçün göndərən şəxslə əlaqə saxlayın.</p>
        @endif
    </div>
</main>
@if ($invite)
<script>
    // Android: an intent link hands the URL to the installed app (falls back to the page when absent).
    if (/Android/i.test(navigator.userAgent)) { document.getElementById('open').href = @json($androidIntent); }
</script>
@endif
</body>
</html>
