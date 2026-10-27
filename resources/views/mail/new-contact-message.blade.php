{{-- To the team when a visitor uses the "Contact us" page (App\Mail\NewContactMessage). Replying goes to the visitor. --}}
@extends('mail.layout')

@php
  $label = 'padding:7px 0;color:#5B6B7C;font-size:14px;vertical-align:top;width:28%;text-align:left;';
  $value = 'padding:7px 0;font-size:14px;font-weight:600;vertical-align:top;text-align:left;';
@endphp

@section('title', 'New contact message')
@section('preheader', 'From '.$msg->name.' ('.$msg->email.')')

@section('content')
  <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#124A80;">New contact message</h1>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr><td style="{{ $label }}">Name</td><td style="{{ $value }}">{{ $msg->name }}</td></tr>
    <tr><td style="{{ $label }}">Email</td><td style="{{ $value }}"><a href="mailto:{{ $msg->email }}" style="color:#124A80;">{{ $msg->email }}</a></td></tr>
    @if ($msg->phone)
      <tr><td style="{{ $label }}">Phone</td><td style="{{ $value }}" dir="ltr">{{ $msg->full_phone }}</td></tr>
    @endif
    <tr><td style="{{ $label }}">Received</td><td style="{{ $value }}">{{ $msg->created_at->format('j M Y, H:i') }}</td></tr>
  </table>

  <div style="margin:18px 0 0;padding:16px 18px;background:#F3F7FB;border-radius:12px;font-size:15px;line-height:1.6;white-space:pre-line;" dir="auto">{{ $msg->message }}</div>

  <p style="margin:22px 0 0;">
    <a href="{{ route('admin.contact-messages.show', $msg) }}" style="display:inline-block;background:#124A80;color:#ffffff;text-decoration:none;font-weight:700;padding:11px 20px;border-radius:999px;font-size:14px;">Open in the admin panel</a>
  </p>
@endsection
