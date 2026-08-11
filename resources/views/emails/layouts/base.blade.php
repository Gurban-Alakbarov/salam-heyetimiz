{{-- Generic transactional-email shell. All email types @extends this. Email-safe: inline CSS, table
     layout, no external CSS/JS. See docs/registration/USER_REGISTRATION_ARCHITECTURE.md §7. --}}
<!doctype html>
<html lang="az">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ $subject ?? ($brand ?? 'Salam Həyətimiz') }}</title>
</head>
<body style="margin:0;background:#f4f5f7;font-family:Segoe UI,Arial,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="480" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <tr><td style="background:#6d28d9;padding:20px 28px;">
          <span style="color:#ffffff;font-size:18px;font-weight:700;">{{ $brand ?? 'Salam Həyətimiz' }}</span>
        </td></tr>
        <tr><td style="padding:28px;">@yield('content')</td></tr>
        <tr><td style="padding:16px 28px;background:#fafafa;border-top:1px solid #eeeeee;font-size:11px;color:#9ca3af;">
          &copy; {{ date('Y') }} {{ $brand ?? 'Salam Həyətimiz' }}@if(! empty($supportEmail)) &middot; Dəstək: {{ $supportEmail }}@endif<br>
          Bu avtomatik mesajdır, cavab verməyin.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
