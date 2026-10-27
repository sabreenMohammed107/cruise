{{-- To a website customer who asked for a new password (App\Notifications\ResetPassword), in the language they used. --}}
@extends('mail.layout')

@section('title', __('emails.reset_title'))
@section('preheader', __('emails.reset_expires', ['minutes' => $minutes]))
@section('footer_note', __('emails.reset_footer'))

@section('content')
  <h1 style="margin:0 0 8px;font-size:24px;line-height:1.3;color:#124A80;">{{ __('emails.reset_title') }}</h1>
  @if ($name)<p style="margin:0 0 6px;">{{ __('emails.guest_greeting', ['name' => $name]) }}</p>@endif
  <p style="margin:0 0 6px;color:#3A4B5C;">{{ __('emails.reset_intro') }}</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">
    <tr>
      <td style="background:#F7941D;border-radius:999px;">
        <a href="{{ $url }}" style="display:inline-block;padding:13px 30px;color:#1B2A3A;font-weight:700;font-size:15px;text-decoration:none;">{{ __('emails.reset_button') }}</a>
      </td>
    </tr>
  </table>

  <p style="margin:0 0 8px;font-size:13px;color:#5B6B7C;">{{ __('emails.reset_expires', ['minutes' => $minutes]) }}</p>
  <p style="margin:0 0 8px;font-size:13px;color:#5B6B7C;">{{ __('emails.reset_ignore') }}</p>
  {{-- For mail apps that strip the button --}}
  <p style="margin:16px 0 0;font-size:12px;color:#8494A5;word-break:break-all;" dir="ltr">{{ $url }}</p>
@endsection
