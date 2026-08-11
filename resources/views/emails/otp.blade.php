@extends('emails.layouts.base')

@section('content')
  <p style="margin:0 0 8px;font-size:16px;font-weight:600;">Təsdiq kodunuz</p>
  @if(! empty($intro))
    <p style="margin:0 0 20px;font-size:14px;color:#6b7280;">{{ $intro }}</p>
  @endif
  <div style="text-align:center;margin:8px 0 20px;">
    <span style="display:inline-block;font-size:34px;letter-spacing:10px;font-weight:700;color:#111827;background:#f3f4f6;border-radius:10px;padding:14px 22px;">{{ $code }}</span>
  </div>
  <p style="margin:0 0 18px;font-size:13px;color:#6b7280;text-align:center;">
    Kod <strong>{{ $ttlMinutes }} dəqiqə</strong> ərzində etibarlıdır.
  </p>
  <table role="presentation" width="100%" style="background:#fef2f2;border-radius:8px;">
    <tr><td style="padding:12px 14px;font-size:12px;color:#991b1b;">
      &#9888; Bu kodu heç kimlə paylaşmayın. {{ $brand ?? 'Salam Həyətimiz' }} əməkdaşları sizdən kodu soruşmaz.
      Bu sorğunu siz etməmisinizsə, məktubu nəzərə almayın.
    </td></tr>
  </table>
@endsection
