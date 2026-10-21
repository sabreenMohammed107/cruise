{{-- Booking details shared by both booking emails: trip, cabins with guests and travellers, price.
     $d = App\Mail\BookingEmailData::make($booking); $team = true adds what the team needs (date of birth, gender,
     paid / due). --}}
@php
  $team ??= false;
  $rtl = app()->getLocale() === 'ar';
  $start = $rtl ? 'right' : 'left';
  $end = $rtl ? 'left' : 'right';
  $h2 = "margin:28px 0 10px;font-size:16px;font-weight:700;color:#124A80;";
  $label = "padding:9px 0;border-bottom:1px solid #E3EAF1;color:#5B6B7C;font-size:14px;vertical-align:top;width:34%;text-align:{$start};";
  $value = "padding:9px 0;border-bottom:1px solid #E3EAF1;font-size:14px;font-weight:600;vertical-align:top;text-align:{$start};";
@endphp

{{-- Trip --}}
<h2 style="{{ $h2 }}">{{ $team ? __('emails.trip_team') : __('emails.trip') }}</h2>
<p style="margin:0 0 6px;font-size:17px;font-weight:700;">{{ $d['cruise'] }}</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
  @foreach ($d['trip'] as $name => $text)
    @continue($text === '' || $text === null)
    <tr>
      <td style="{{ $label }}">{{ $name }}</td>
      <td style="{{ $value }}" @if ($name === __('emails.sailing_code')) dir="ltr" @endif>{{ $text }}</td>
    </tr>
  @endforeach
</table>

{{-- Cabins, guests and travellers --}}
<h2 style="{{ $h2 }}">{{ __('emails.cabins_title') }}</h2>
@foreach ($d['cabins'] as $cabin)
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E3EAF1;border-radius:12px;margin-bottom:12px;">
    {{-- Round only the outer top corners, so no gap shows between the two header cells --}}
    <tr>
      <td style="background:#F5F8FB;padding:12px 14px;border-top-{{ $start }}-radius:12px;{{ $cabin['fare'] ? '' : "border-top-{$end}-radius:12px;" }}text-align:{{ $start }};">
        <span style="font-weight:700;font-size:15px;">{{ $cabin['title'] }}</span>
        <span style="color:#5B6B7C;font-size:13px;"> · {{ $cabin['guests'] }}</span>
      </td>
      @if ($cabin['fare'])
        <td style="background:#F5F8FB;padding:12px 14px;border-top-{{ $end }}-radius:12px;text-align:{{ $end }};font-weight:700;white-space:nowrap;" dir="ltr">{{ $cabin['fare'] }}</td>
      @endif
    </tr>
    @foreach ($cabin['travellers'] as $t)
      <tr>
        <td colspan="2" style="padding:10px 14px;border-top:1px solid #E3EAF1;text-align:{{ $start }};font-size:14px;">
          <span style="font-weight:600;">{{ $t['name'] }}</span>
          <span style="display:inline-block;margin-inline-start:6px;padding:1px 8px;border-radius:999px;background:#E6F0FA;color:#155C9E;font-size:12px;font-weight:600;">{{ $t['band'] }}</span>
          @if ($t['primary'])<span style="display:inline-block;padding:1px 8px;border-radius:999px;background:#E3F5EE;color:#0E6B4C;font-size:12px;font-weight:600;">{{ __('emails.primary') }}</span>@endif
          <div style="color:#5B6B7C;font-size:13px;">
            {{ $t['nationality'] }}@if ($team)@if ($t['gender']) · {{ $t['gender'] }}@endif @if ($t['dob']) · {{ __('emails.born', ['date' => $t['dob']]) }}@endif @endif
          </div>
        </td>
      </tr>
    @endforeach
  </table>
@endforeach

{{-- Price --}}
<h2 style="{{ $h2 }}">{{ __('emails.price_title') }}</h2>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
  @foreach ($d['prices'] as $name => $amount)
    <tr>
      <td style="padding:6px 0;color:#5B6B7C;font-size:14px;text-align:{{ $start }};">{{ $name }}</td>
      <td style="padding:6px 0;font-size:14px;text-align:{{ $end }};white-space:nowrap;" dir="ltr">{{ $amount }}</td>
    </tr>
  @endforeach
  <tr>
    <td style="padding:12px 0 6px;border-top:2px solid #124A80;font-size:16px;font-weight:700;color:#124A80;text-align:{{ $start }};">{{ __('emails.total') }}</td>
    <td style="padding:12px 0 6px;border-top:2px solid #124A80;font-size:18px;font-weight:700;color:#124A80;text-align:{{ $end }};white-space:nowrap;" dir="ltr">{{ $d['total'] }}</td>
  </tr>
  @if ($team)
    <tr>
      <td style="padding:4px 0;color:#5B6B7C;font-size:14px;text-align:{{ $start }};">{{ __('emails.paid') }}</td>
      <td style="padding:4px 0;font-size:14px;text-align:{{ $end }};" dir="ltr">{{ $d['paid'] }}</td>
    </tr>
    <tr>
      <td style="padding:4px 0;font-size:14px;font-weight:700;color:#B4570A;text-align:{{ $start }};">{{ __('emails.due') }}</td>
      <td style="padding:4px 0;font-size:14px;font-weight:700;color:#B4570A;text-align:{{ $end }};" dir="ltr">{{ $d['due'] }}</td>
    </tr>
  @endif
</table>
<p style="margin:8px 0 0;font-size:12px;color:#8494A5;">{{ __('emails.prices_note') }}</p>
