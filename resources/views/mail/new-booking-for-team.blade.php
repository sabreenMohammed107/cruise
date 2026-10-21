{{-- To the team after a booking (App\Mail\NewBookingForTeam), in the language the guest booked in. --}}
@extends('mail.layout')
@use('App\Mail\BookingEmailData')

@php
  $d = BookingEmailData::make($booking);
  $guest = $booking->primaryTraveller();
  $rtl = app()->getLocale() === 'ar';
  $start = $rtl ? 'right' : 'left';
  $label = "padding:7px 0;color:#5B6B7C;font-size:14px;vertical-align:top;width:34%;text-align:{$start};";
  $value = "padding:7px 0;font-size:14px;font-weight:600;vertical-align:top;text-align:{$start};";
  $tel = 'tel:'.preg_replace('/[^0-9+]/', '', $booking->contact_dial.$booking->contact_phone);
  $wa = 'https://wa.me/'.preg_replace('/\D/', '', $booking->contact_dial.$booking->contact_phone);
  $hours = (int) config('cruise.payment_contact_hours');
@endphp

@section('title', __('emails.team_title', ['ref' => $booking->reference]))
@section('preheader', __('emails.team_intro', ['hours' => $hours, 'amount' => $d['total']]))

@section('content')
  {{-- Translations are our own text; the values put into them are escaped --}}
  <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#124A80;">{!! __('emails.team_title', ['ref' => '<span dir="ltr">'.e($booking->reference).'</span>']) !!}</h1>

  {{-- Call to action: amount and deadline --}}
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF4E5;border-radius:12px;">
    <tr>
      <td style="padding:14px 18px;font-size:15px;color:#5A3A08;text-align:{{ $start }};">
        {!! __('emails.team_intro', ['hours' => $hours, 'amount' => '<b dir="ltr">'.e($d['total']).'</b>']) !!}
      </td>
    </tr>
  </table>

  {{-- Guest contact --}}
  <h2 style="margin:24px 0 10px;font-size:16px;font-weight:700;color:#124A80;">{{ __('emails.contact') }}</h2>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    @if ($guest)
      <tr><td style="{{ $label }}">{{ __('emails.primary') }}</td><td style="{{ $value }}">{{ $guest->first_name }} {{ $guest->last_name }}</td></tr>
    @endif
    <tr><td style="{{ $label }}">{{ __('emails.call') }}</td><td style="{{ $value }}"><a href="{{ $tel }}" dir="ltr" style="color:#155C9E;">&#x2066;{{ $d['contact']['phone'] }}&#x2069;</a> &nbsp;·&nbsp; <a href="{{ $wa }}" style="color:#155C9E;">{{ __('emails.whatsapp') }}</a></td></tr>
    <tr><td style="{{ $label }}">{{ __('emails.email_guest') }}</td><td style="{{ $value }}"><a href="mailto:{{ $d['contact']['email'] }}" style="color:#155C9E;">{{ $d['contact']['email'] }}</a></td></tr>
    <tr><td style="{{ $label }}">{{ __('emails.booked_in') }}</td><td style="{{ $value }}">{{ __('emails.language_names.'.$booking->emailLocale()) }}</td></tr>
    <tr><td style="{{ $label }}">{{ __('emails.booked_at') }}</td><td style="{{ $value }}">{{ ($booking->confirmed_at ?? $booking->created_at)?->translatedFormat('j F Y · H:i') }}</td></tr>
    <tr><td style="{{ $label }}">{{ __('emails.account') }}</td><td style="{{ $value }}">{{ $booking->user ? $booking->user->name.' ('.$booking->user->email.')' : __('emails.guest_checkout') }}</td></tr>
  </table>

  @include('mail.partials.booking-details', ['d' => $d, 'team' => true])

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto 4px;">
    <tr>
      <td style="background:#155C9E;border-radius:999px;">
        <a href="{{ route('admin.bookings.show', $booking) }}" style="display:inline-block;padding:13px 30px;color:#FFFFFF;font-weight:700;font-size:15px;text-decoration:none;">{{ __('emails.open_admin') }}</a>
      </td>
    </tr>
  </table>
@endsection
