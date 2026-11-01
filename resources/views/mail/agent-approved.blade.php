{{-- To an approved agent (App\Notifications\AgentApproved): sign-in email and temporary password. --}}
@extends('mail.layout')

@section('title', __('emails.agent_title'))
@section('preheader', __('emails.agent_subject'))
@section('footer_note', __('emails.agent_footer'))

@section('content')
  <h1 style="margin:0 0 8px;font-size:24px;line-height:1.3;color:#124A80;">{{ __('emails.agent_title') }}</h1>
  @if ($name)<p style="margin:0 0 6px;">{{ __('emails.guest_greeting', ['name' => $name]) }}</p>@endif
  <p style="margin:0 0 6px;color:#3A4B5C;">{{ __('emails.agent_intro', ['company' => $company]) }}</p>

  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0;background:#F4F8FC;border-radius:12px;">
    <tr><td style="padding:16px 20px;">
      <p style="margin:0 0 10px;font-weight:700;color:#124A80;">{{ __('emails.agent_details') }}</p>
      <p style="margin:0 0 4px;font-size:14px;color:#5B6B7C;">{{ __('emails.agent_email') }}</p>
      <p style="margin:0 0 12px;font-size:16px;font-weight:700;" dir="ltr">{{ $email }}</p>
      <p style="margin:0 0 4px;font-size:14px;color:#5B6B7C;">{{ __('emails.agent_password') }}</p>
      <p style="margin:0;font-size:18px;font-weight:700;font-family:Consolas,Menlo,monospace;letter-spacing:1px;" dir="ltr">{{ $password }}</p>
    </td></tr>
  </table>

  <p style="margin:0 0 8px;font-size:13px;color:#5B6B7C;">{{ __('emails.agent_change_password') }}</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto;">
    <tr>
      <td style="background:#F7941D;border-radius:999px;">
        <a href="{{ $url }}" style="display:inline-block;padding:13px 30px;color:#1B2A3A;font-weight:700;font-size:15px;text-decoration:none;">{{ __('emails.agent_button') }}</a>
      </td>
    </tr>
  </table>
  {{-- For mail apps that strip the button --}}
  <p style="margin:16px 0 0;font-size:12px;color:#8494A5;word-break:break-all;" dir="ltr">{{ $url }}</p>
@endsection
