@extends('emails.layouts.base')

{{-- A8 — legal or individual application rejected; every line comes in the recipient's language (lines[]). --}}
@section('content')
  <p style="margin:0 0 8px;font-size:16px;font-weight:600;">{{ $lines[0] }}</p>
  <p style="margin:0 0 16px;font-size:14px;color:#374151;">{{ $lines[1] }}</p>
  <p style="margin:0 0 16px;font-size:14px;color:#374151;"><strong>{{ $lines[2] }}</strong></p>
  <p style="margin:0;font-size:13px;color:#6b7280;">{{ $lines[3] }}</p>
@endsection
