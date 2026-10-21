{{-- Layout of the booking emails (step 20). Right-to-left in Arabic. Styles are inline because Gmail and Outlook
     drop most <style> rules; colours are the site's (ocean-700 #124A80, ocean-600 #155C9E, lagoon-500 #12A9C2).
     Footer contact details: Admin → Footer & contact (App\Models\SiteSetting). --}}
@use('App\Models\SiteSetting')
@php
  $rtl = app()->getLocale() === 'ar';
  $font = $rtl ? "Tahoma, 'Segoe UI', Arial, sans-serif" : "'Segoe UI', Helvetica, Arial, sans-serif";
  $phone = SiteSetting::value('phone_1');
  $email = SiteSetting::value('email');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background:#EEF3F8;font-family:{{ $font }};color:#1B2A3A;">
  {{-- Inbox preview line --}}
  <div style="display:none;max-height:0;overflow:hidden;">@yield('preheader')</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EEF3F8;" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#FFFFFF;border-radius:16px;overflow:hidden;">
          {{-- Header: the site logo as text (images are often blocked in email) --}}
          <tr>
            <td style="background:#124A80;padding:22px 28px;text-align:{{ $rtl ? 'right' : 'left' }};">
              <span style="font-size:22px;font-weight:700;color:#FFFFFF;">Safer</span><span style="font-size:22px;font-weight:700;color:#2BC4D9;">Cruise</span>
              {{-- An explicit white link: otherwise mail apps turn "safer.travel" into a blue link that is hard to read on the dark header --}}
              <div style="font-size:12px;color:#FFFFFF;margin-top:2px;">{!! __('emails.tagline', ['site' => '<a href="https://safer.travel" style="color:#FFFFFF;text-decoration:none;font-weight:600;">safer.travel</a>']) !!}</div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px;text-align:{{ $rtl ? 'right' : 'left' }};font-size:15px;line-height:1.6;">
              @yield('content')
            </td>
          </tr>
          <tr>
            <td style="background:#F5F8FB;padding:20px 28px;text-align:center;font-size:12px;line-height:1.7;color:#5B6B7C;">
              @if ($phone !== '')<span dir="ltr">{{ $phone }}</span>@endif
              @if ($phone !== '' && $email !== '') &nbsp;·&nbsp; @endif
              @if ($email !== '')<a href="mailto:{{ $email }}" style="color:#155C9E;text-decoration:none;">{{ $email }}</a>@endif
              &nbsp;·&nbsp; <a href="https://wa.me/{{ SiteSetting::whatsapp() }}" style="color:#155C9E;text-decoration:none;">{{ __('emails.whatsapp') }}</a>
              <br>{{ __('emails.footer_rights', ['year' => now()->year]) }}
              @hasSection('footer_note')<br><span style="color:#8494A5;">@yield('footer_note')</span>@endif
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
