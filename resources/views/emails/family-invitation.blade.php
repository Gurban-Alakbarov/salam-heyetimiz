@extends('emails.layouts.base')

@section('content')
  <p style="margin:0 0 8px;font-size:16px;font-weight:600;">Salam, {{ $firstName }}!</p>
  <p style="margin:0 0 20px;font-size:14px;color:#374151;">
    <strong>{{ $inviter }}</strong> sizi Salam Həyətimiz tətbiqində ailə üzvü kimi dəvət edir.
    Dəvəti qəbul etdikdən sonra sizə verilən cihazlardan istifadə edə biləcəksiniz.
  </p>
  <div style="text-align:center;margin:8px 0 20px;">
    <a href="{{ $link }}" style="display:inline-block;background:#00B800;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;border-radius:10px;padding:14px 26px;">Dəvəti qəbul et</a>
  </div>
  <p style="margin:0 0 18px;font-size:13px;color:#6b7280;text-align:center;">
    Link <strong>{{ $expiresAt }}</strong> tarixinədək etibarlıdır.
  </p>
  <p style="margin:0 0 6px;font-size:12px;color:#9ca3af;word-break:break-all;">Düymə işləmirsə, bu linki açın: {{ $link }}</p>
  <p style="margin:0;font-size:12px;color:#9ca3af;">Bu dəvəti gözləmirdinizsə, məktubu nəzərə almayın.</p>
@endsection
