@extends('emails.layouts.base')

@section('content')
  <p style="margin:0 0 8px;font-size:16px;font-weight:600;">Salam, {{ $name }}!</p>
  <p style="margin:0 0 16px;font-size:14px;color:#374151;">
    <strong>{{ $complexName }}</strong> üçün göndərdiyiniz müraciət təəssüf ki, təsdiqlənmədi.
  </p>
  <p style="margin:0 0 16px;font-size:14px;color:#374151;"><strong>Səbəb:</strong> {{ $reason }}</p>
  <p style="margin:0;font-size:13px;color:#6b7280;">Məlumatları düzəldib tətbiqdən yenidən müraciət edə bilərsiniz.</p>
@endsection
