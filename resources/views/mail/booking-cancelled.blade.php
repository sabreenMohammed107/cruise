{{-- To the guest when the booking was cancelled automatically: nothing paid within the hold (App\Mail\BookingCancelled). --}}
@extends('mail.layout')
@use('App\Mail\BookingEmailData')

@php
  $d = BookingEmailData::make($booking);
  $guest = $booking->primaryTraveller();
  $rtl = app()->getLocale() === 'ar';
  $start = $rtl ? 'right' : 'left';
  $hours = (int) round($booking->confirmed_at->diffInHours($booking->hold_until ?? $booking->confirmed_at->copy()->addHours((int) config('cruise.hold_hours'))));
@endphp

@section('title', __('emails.cancelled_title'))
@section('preheader', __('emails.cancelled_subject', ['ref' => $booking->reference]))
@section('footer_note', __('emails.footer_why'))

@section('content')
  <h1 style="margin:0 0 8px;font-size:24px;line-height:1.3;color:#124A80;">{{ __('emails.cancelled_title') }}</h1>
  @if ($guest)<p style="margin:0 0 6px;">{{ __('emails.guest_greeting', ['name' => $guest->first_name]) }}</p>@endif
  <p style="margin:0 0 18px;color:#3A4B5C;">{{ __('emails.cancelled_intro', ['hours' => $hours]) }}</p>

  {{-- Reference card --}}
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F8FB;border:1px solid #E3EAF1;border-radius:12px;">
    <tr>
      <td style="padding:16px 18px;text-align:{{ $start }};">
        <div style="font-size:12px;color:#5B6B7C;">{{ __('emails.reference') }}</div>
        <div style="font-size:22px;font-weight:700;letter-spacing:1px;color:#5B6B7C;text-decoration:line-through;" dir="ltr">{{ $booking->reference }}</div>
        <div style="margin-top:8px;font-size:15px;font-weight:700;">{{ $d['cruise'] }}</div>
        @foreach ($d['trip'] as $name => $text)
          @continue($text === '' || $text === null)
          <div style="font-size:13px;color:#5B6B7C;margin-top:2px;">{{ $name }}: <span style="color:#1B2A3A;font-weight:600;">{{ $text }}</span></div>
        @endforeach
      </td>
    </tr>
  </table>

  <p style="margin:18px 0 0;color:#3A4B5C;">{{ __('emails.cancelled_again') }}</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px auto 8px;">
    <tr>
      <td style="background:#F7941D;border-radius:999px;">
        <a href="{{ route('cruises.show', $booking->sailing->cruise) }}" style="display:inline-block;padding:13px 30px;color:#1B2A3A;font-weight:700;font-size:15px;text-decoration:none;">{{ __('emails.cancelled_button') }}</a>
      </td>
    </tr>
  </table>

  <p style="margin:20px 0 0;font-size:13px;color:#5B6B7C;">{{ __('emails.cancelled_paid') }}</p>
  <p style="margin:8px 0 0;font-size:13px;color:#5B6B7C;">{{ __('emails.questions') }}</p>
@endsection
