@extends('emails.layouts.base')

{{-- A receipt for a payment or a refund (refund-receipt includes this view); not a tax invoice. --}}
@section('content')
  <p style="margin:0 0 16px;font-size:16px;font-weight:600;">{{ $title }}</p>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">
    @foreach ($rows as [$label, $value])
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;vertical-align:top;">{{ $label }}</td>
        <td style="padding:6px 0;color:#111827;text-align:right;">{{ $value }}</td>
      </tr>
    @endforeach
  </table>
  @if (! empty($notice))
    <p style="margin:16px 0 0;font-size:13px;color:#b45309;">{{ $notice }}</p>
  @endif
@endsection
