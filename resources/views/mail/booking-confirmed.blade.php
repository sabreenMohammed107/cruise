{{-- To the guest after booking (App\Mail\BookingConfirmed), in the language they booked in. --}}
@extends('mail.layout')
@use('App\Mail\BookingEmailData')

@php
  $d = BookingEmailData::make($booking);
  $guest = $booking->primaryTraveller();
  $rtl = app()->getLocale() === 'ar';
@endphp

@section('title', __('emails.guest_title'))
@section('preheader', __('emails.guest_subject', ['ref' => $booking->reference]))
@section('footer_note', __('emails.footer_why'))

@section('content')
  <h1 style="margin:0 0 8px;font-size:24px;line-height:1.3;color:#124A80;">{{ __('emails.guest_title') }}</h1>
  @if ($guest)<p style="margin:0 0 6px;">{{ __('emails.guest_greeting', ['name' => $guest->first_name]) }}</p>@endif
  <p style="margin:0 0 18px;color:#3A4B5C;">{{ __('emails.guest_intro') }}</p>

  {{-- Reference card --}}
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#155C9E;border-radius:12px;">
    <tr>
      <td style="padding:16px 18px;color:#FFFFFF;text-align:{{ $rtl ? 'right' : 'left' }};">
        <div style="font-size:12px;color:#BFD6EC;">{{ __('emails.reference') }}</div>
        <div style="font-size:24px;font-weight:700;letter-spacing:1px;" dir="ltr">{{ $booking->reference }}</div>
      </td>
      <td style="padding:16px 18px;color:#FFFFFF;text-align:{{ $rtl ? 'left' : 'right' }};">
        <div style="font-size:12px;color:#BFD6EC;">{{ __('emails.total') }}</div>
        <div style="font-size:20px;font-weight:700;" dir="ltr">{{ $d['total'] }}</div>
      </td>
    </tr>
  </table>

  {{-- What happens next --}}
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;background:#FFF4E5;border-radius:12px;">
    <tr>
      <td style="padding:14px 18px;font-size:14px;color:#5A3A08;text-align:{{ $rtl ? 'right' : 'left' }};">
        <div style="font-weight:700;margin-bottom:4px;">{{ __('emails.next_title') }}</div>
        {{-- Our own text; the phone number put into it is escaped --}}
        {!! __('emails.next_contact', ['hours' => (int) config('cruise.payment_contact_hours'), 'phone' => '<b dir="ltr">&#x2066;'.e($d['contact']['phone']).'&#x2069;</b>']) !!}
        {{ __('emails.next_no_online') }}
      </td>
    </tr>
  </table>

  @include('mail.partials.booking-details', ['d' => $d])

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto 8px;">
    <tr>
      <td style="background:#F7941D;border-radius:999px;">
        <a href="{{ \App\Http\Controllers\BookingController::signedConfirmationUrl($booking) }}" style="display:inline-block;padding:13px 30px;color:#1B2A3A;font-weight:700;font-size:15px;text-decoration:none;">{{ __('emails.view_booking') }}</a>
      </td>
    </tr>
  </table>

  <p style="margin:20px 0 0;font-size:13px;color:#5B6B7C;">{{ __('emails.next_documents') }}</p>
  <p style="margin:8px 0 0;font-size:13px;color:#5B6B7C;">{{ __('emails.questions') }}</p>
@endsection
