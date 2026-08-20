@php
    /** @var array<string,mixed> $status */
    /** @var array{lat: float, lng: float}|null $coordinates */
    $reasonMessages = [
        'expired' => __('visitor.expired'),
        'revoked' => __('visitor.revoked'),
        'usage_exceeded' => __('visitor.usage_exceeded'),
        'device_inactive' => __('visitor.device_inactive'),
        'not_found' => __('visitor.not_found'),
    ];
    $reason = $status['reason'] ?? null;
    $reasonText = $reason !== null ? ($reasonMessages[$reason] ?? __('visitor.unavailable')) : null;
    $barrierName = $status['barrier_name'] ?: __('visitor.default_barrier');
    $isValid = (bool) ($status['valid'] ?? false);

    // Payload for the inline script (only rendered for live links). Built here so the @json directive
    // takes a single variable — a function call inside @json(...) confuses Blade's argument matcher.
    $visitData = [
        'api' => url('/v1/visit/'.$token),
        'oneTime' => ($status['access_type'] ?? null) === 'one_time',
        'expiresAt' => $status['expires_at'] ?? null,
        'coordinates' => $coordinates, // {lat,lng} | null — powers the directions app picker
        'i18n' => [
            'opening' => __('visitor.opening'),
            'opened' => __('visitor.opened'),
            'failed' => __('visitor.open_failed'),
            'expiresIn' => __('visitor.expires_in'),
            'open' => __('visitor.open_barrier'),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('visitor.page_title') }}</title>
    <style>
        :root {
            --green: #5FAF1D;
            --green-dark: #4C8F16;
            --green-press: #427D12;
            --bg: #f4f6f2;
            --card: #ffffff;
            --ink: #17210c;
            --muted: #6b7563;
            --danger: #c0392b;
            --line: #e6ebe0;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border-radius: 22px;
            box-shadow: 0 12px 40px rgba(23, 33, 12, .10);
            padding: 28px 24px 24px;
            text-align: center;
        }
        .badge {
            width: 66px; height: 66px; margin: 0 auto 16px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green), var(--green-dark));
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 20px rgba(95, 175, 29, .35);
        }
        .badge svg { width: 34px; height: 34px; fill: #fff; }
        h1 { font-size: 22px; line-height: 1.25; margin: 0 0 4px; }
        .invited { color: var(--muted); font-size: 14px; margin: 0 0 20px; }
        .invited strong { color: var(--ink); font-weight: 600; }
        .expiry {
            display: inline-block; font-size: 13px; color: var(--muted);
            background: #f0f4ec; border-radius: 999px; padding: 6px 14px; margin-bottom: 20px;
        }
        button {
            font: inherit; cursor: pointer; border: none; width: 100%;
            border-radius: 14px; padding: 16px; font-size: 17px; font-weight: 600;
            transition: transform .05s ease, background .15s ease, opacity .15s ease;
        }
        button:active { transform: scale(.985); }
        .btn-open { background: var(--green); color: #fff; margin-bottom: 12px; }
        .btn-open:active { background: var(--green-press); }
        .btn-open:disabled { background: #b9c6ad; cursor: default; }
        /* Directions: same size + full width as the open button, but secondary/outlined. */
        .btn-dir {
            background: var(--card); color: var(--green-dark);
            border: 1.5px solid var(--green);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-dir:active { background: var(--bg); }
        .btn-dir svg { width: 20px; height: 20px; fill: currentColor; }
        /* Native-style app picker (bottom sheet) */
        .sheet-backdrop {
            position: fixed; inset: 0; z-index: 50; background: rgba(0,0,0,.4);
            display: flex; align-items: flex-end; justify-content: center;
        }
        .sheet-backdrop[hidden] { display: none; }
        .sheet {
            width: 100%; max-width: 460px; background: var(--card);
            border-radius: 20px 20px 0 0; padding: 8px 16px calc(20px + env(safe-area-inset-bottom));
            box-shadow: 0 -8px 30px rgba(0,0,0,.18); animation: sheetup .22s ease;
        }
        @keyframes sheetup { from { transform: translateY(24px); opacity: .5; } to { transform: none; opacity: 1; } }
        .sheet-handle { width: 40px; height: 4px; border-radius: 2px; background: var(--line); margin: 6px auto 12px; }
        .sheet-title { font-size: 14px; font-weight: 600; color: var(--muted); margin: 0 4px 6px; }
        .app-row {
            display: flex; align-items: center; gap: 14px; width: 100%;
            background: transparent; border: none; padding: 13px 6px; margin: 0;
            cursor: pointer; text-align: left; border-radius: 12px; font-weight: 600;
        }
        .app-row:active { background: var(--bg); }
        .app-icon {
            width: 42px; height: 42px; border-radius: 11px; flex: 0 0 auto;
            display: flex; align-items: center; justify-content: center;
        }
        .app-icon svg { width: 24px; height: 24px; fill: #fff; }
        .app-name { font-size: 16px; color: var(--ink); }
        .msg { margin: 16px 2px 0; font-size: 15px; line-height: 1.4; min-height: 20px; }
        .msg.ok { color: var(--green-dark); font-weight: 600; }
        .msg.err { color: var(--danger); }
        .hint { color: var(--muted); font-size: 12.5px; margin-top: 10px; }
        .state-invalid h1 { color: var(--muted); }
        .spinner {
            display: inline-block; width: 16px; height: 16px; vertical-align: -3px; margin-right: 6px;
            border: 2px solid rgba(255,255,255,.5); border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-color-scheme: dark) {
            :root { --bg:#12160e; --card:#1b2116; --ink:#eef2e8; --muted:#9aa691; --line:#2b3323; }
            .expiry { background:#232b1c; }
            .btn-dir { background:#1b2116; }
        }
    </style>
</head>
<body>
    <main class="card {{ $isValid ? '' : 'state-invalid' }}">
        <div class="badge" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5zm3 8H9V7a3 3 0 0 1 6 0v3z"/></svg>
        </div>

        <h1>{{ $barrierName }}</h1>

        @if($status['resident_name'] ?? null)
            <p class="invited">{{ __('visitor.invited_by') }}: <strong>{{ $status['resident_name'] }}</strong></p>
        @endif

        @if($isValid && ($status['expires_at'] ?? null))
            <div class="expiry" id="expiry" hidden></div>
        @endif

        @if($isValid)
            <button class="btn-open" id="openBtn" type="button">{{ __('visitor.open_barrier') }}</button>
        @endif

        @if($coordinates)
            <button class="btn-dir" id="dirBtn" type="button">
                <svg viewBox="0 0 24 24"><path d="M21.71 11.29l-9-9a1 1 0 0 0-1.42 0l-9 9a1 1 0 0 0 0 1.42l9 9a1 1 0 0 0 1.42 0l9-9a1 1 0 0 0 0-1.42zM14 14.5V12h-4v3H8v-4a1 1 0 0 1 1-1h5V7.5l3.5 3.5z"/></svg>
                {{ __('visitor.directions') }}
            </button>
        @endif

        <p class="msg {{ $isValid ? '' : 'err' }}" id="msg">{{ $isValid ? '' : $reasonText }}</p>

        @if($isValid && ($status['access_type'] ?? null) === 'time_limited')
            <p class="hint">{{ __('visitor.reopen_hint') }}</p>
        @endif
    </main>

    @if($coordinates)
        {{-- Native-style navigation app picker (built client-side; AzNav only). --}}
        <div class="sheet-backdrop" id="dirSheet" hidden>
            <div class="sheet" role="dialog" aria-modal="true" aria-label="{{ __('visitor.choose_app') }}">
                <div class="sheet-handle"></div>
                <div class="sheet-title">{{ __('visitor.choose_app') }}</div>
                <div id="dirList"></div>
            </div>
        </div>
    @endif

    @if($isValid)
    <script>
        (function () {
            var VISIT = @json($visitData);

            var btn = document.getElementById('openBtn');
            var msg = document.getElementById('msg');
            var expiryEl = document.getElementById('expiry');

            function setMsg(text, kind) {
                msg.textContent = text || '';
                msg.className = 'msg' + (kind ? ' ' + kind : '');
            }
            function busy(on) {
                if (!btn) return;
                btn.disabled = on;
                btn.innerHTML = on ? '<span class="spinner"></span>' + VISIT.i18n.opening : VISIT.i18n.open;
            }
            function lock() { if (btn) { btn.disabled = true; btn.textContent = VISIT.i18n.open; } }
            function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

            function success() {
                setMsg(VISIT.i18n.opened, 'ok');
                if (VISIT.oneTime) { lock(); } else { busy(false); }
            }

            async function poll(id, expectedMs, confirms) {
                var deadline = Date.now() + Math.max(expectedMs || 0, 4000) + 6000;
                while (Date.now() < deadline) {
                    await sleep(1200);
                    var state = null;
                    try {
                        var res = await fetch(VISIT.api + '/command/' + id, { headers: { 'Accept': 'application/json' } });
                        var body = await res.json();
                        state = body && body.state;
                    } catch (e) { continue; }
                    if (state === 'opened') { success(); return; }
                    if (state === 'failed' || state === 'expired') { setMsg(VISIT.i18n.failed, 'err'); busy(false); return; }
                    if (state === 'dispatched' && !confirms) { success(); return; }
                }
                if (!confirms) { success(); } else { setMsg(VISIT.i18n.failed, 'err'); busy(false); }
            }

            async function open() {
                busy(true);
                setMsg('');
                try {
                    var res = await fetch(VISIT.api + '/open', { method: 'POST', headers: { 'Accept': 'application/json' } });
                    var body = await res.json().catch(function () { return {}; });
                    if (res.status === 202 && body.command_id) {
                        await poll(body.command_id, body.expected_completion_ms, !!body.driver_confirms_actuation);
                    } else {
                        var code = body && body.error && body.error.code;
                        var text = (body && body.error && body.error.message) || VISIT.i18n.failed;
                        setMsg(text, 'err');
                        if (['visitor_link_expired', 'visitor_link_revoked', 'visitor_link_usage_exceeded', 'visitor_link_not_found'].indexOf(code) !== -1) {
                            lock();
                        } else {
                            busy(false);
                        }
                    }
                } catch (e) {
                    setMsg(VISIT.i18n.failed, 'err');
                    busy(false);
                }
            }

            if (btn) { btn.addEventListener('click', open); }

            // Directions app picker. Android: AzNav is the only provider — an intent:// opens it,
            // and if AzNav is not installed Chrome follows browser_fallback_url to its Play Store
            // page. iOS / any non-Android: AzNav is Android-only, so open Apple Maps with the
            // coordinates instead — the Play Store (Android) link is NEVER used off Android.
            var dirBtn = document.getElementById('dirBtn');
            var dirSheet = document.getElementById('dirSheet');
            var dirList = document.getElementById('dirList');
            var NAV_SVG = '<svg viewBox="0 0 24 24"><path d="M21.71 11.29l-9-9a1 1 0 0 0-1.42 0l-9 9a1 1 0 0 0 0 1.42l9 9a1 1 0 0 0 1.42 0l9-9a1 1 0 0 0 0-1.42zM14 14.5V12h-4v3H8v-4a1 1 0 0 1 1-1h5V7.5l3.5 3.5z"/></svg>';

            var AZNAV_PLAY = 'https://play.google.com/store/apps/details?id=net.sinam.aznav';

            function navApps() {
                var c = VISIT.coordinates;
                if (!c) return [];
                var ll = c.lat + ',' + c.lng;
                var isAndroid = /android/i.test(navigator.userAgent);

                // Android → AzNav. The intent:// tries the geo: route; if AzNav is not installed,
                // Chrome follows browser_fallback_url to its Play Store page (Android-only path).
                if (isAndroid) {
                    var azNavUrl = 'intent://' + ll + '?q=' + ll + '#Intent;scheme=geo;package=net.sinam.aznav;S.browser_fallback_url=' + encodeURIComponent(AZNAV_PLAY) + ';end';
                    return [
                        { name: 'AzNav', color: '#0b7285', url: azNavUrl },
                    ];
                }

                // iOS / any non-Android → Apple Maps directions to the destination coordinates
                // (opens the native Maps app on iOS, web Apple Maps elsewhere). The Android-only
                // Play Store link is never used here.
                var appleMapsUrl = 'https://maps.apple.com/?daddr=' + ll + '&dirflg=d';
                return [
                    { name: 'Apple Maps', color: '#0b7285', url: appleMapsUrl },
                ];
            }

            function buildPicker() {
                if (dirList.childElementCount) return;
                navApps().forEach(function (app) {
                    var row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'app-row';
                    row.innerHTML = '<span class="app-icon" style="background:' + app.color + '">' + NAV_SVG + '</span><span class="app-name">' + app.name + '</span>';
                    row.addEventListener('click', function () {
                        dirSheet.hidden = true;
                        window.open(app.url, '_blank', 'noopener');
                    });
                    dirList.appendChild(row);
                });
            }

            if (dirBtn && dirSheet) {
                dirBtn.addEventListener('click', function () {
                    buildPicker();
                    dirSheet.hidden = false;
                });
                dirSheet.addEventListener('click', function (e) {
                    if (e.target === dirSheet) dirSheet.hidden = true;
                });
            }

            // Live countdown to expiry (display only — the server is the source of truth).
            if (expiryEl && VISIT.expiresAt) {
                var end = new Date(VISIT.expiresAt).getTime();
                var tick = function () {
                    var diff = end - Date.now();
                    if (diff <= 0) { expiryEl.hidden = true; return; }
                    var h = Math.floor(diff / 3600000);
                    var m = Math.floor((diff % 3600000) / 60000);
                    var s = Math.floor((diff % 60000) / 1000);
                    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
                    var t = (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(s);
                    expiryEl.textContent = VISIT.i18n.expiresIn + ' ' + t;
                    expiryEl.hidden = false;
                };
                tick();
                setInterval(tick, 1000);
            }
        })();
    </script>
    @endif
</body>
</html>
