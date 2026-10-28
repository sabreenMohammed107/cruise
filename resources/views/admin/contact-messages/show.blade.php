@extends('layouts.admin')
@use('App\Support\Format')

@section('title', 'Message from '.$msg->name)

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb"><a href="{{ route('admin.contact-messages.index') }}" class="hover:text-ocean-600">Contact messages</a> <span class="mx-1">/</span> <span class="text-ink">{{ $msg->name }}</span></nav>

  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $msg->name }}</h1>
      <p class="text-muted mt-1">Received {{ Format::date($msg->created_at, 'j M Y, H:i') }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
      @if ($msg->phone)
        <a href="https://wa.me/{{ preg_replace('/\D/', '', $msg->full_phone) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><i data-icon="chat" class="w-4 h-4"></i> WhatsApp</a>
        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $msg->full_phone) }}" class="btn btn-outline btn-sm"><i data-icon="phone" class="w-4 h-4"></i> Call</a>
      @endif
      <a href="mailto:{{ $msg->email }}?subject={{ rawurlencode('Re: your message to Safer Cruise') }}" class="btn btn-primary btn-sm"><i data-icon="mail" class="w-4 h-4"></i> Reply by email</a>
    </div>
  </div>

  <section class="card p-5 sm:p-6 mt-6">
    <dl class="grid sm:grid-cols-3 gap-5 text-sm">
      <div><dt class="text-xs font-semibold text-muted">Email</dt><dd class="font-bold mt-0.5 break-all">{{ $msg->email }}</dd></div>
      <div><dt class="text-xs font-semibold text-muted">Phone</dt><dd class="font-bold mt-0.5" dir="ltr">{{ $msg->full_phone ?: '—' }}</dd></div>
      <div><dt class="text-xs font-semibold text-muted">Received</dt><dd class="font-bold mt-0.5">{{ Format::date($msg->created_at, 'j M Y, H:i') }}</dd></div>
    </dl>
  </section>

  <section class="card p-5 sm:p-6 mt-6">
    <h2 class="font-display font-semibold text-lg mb-3">Message</h2>
    {{-- Escaped, then line breaks kept with CSS (no nl2br / {!! !!}) --}}
    <p class="whitespace-pre-line break-words leading-relaxed" dir="auto">{{ $msg->message }}</p>
  </section>

  @can('delete-records')
    <form method="POST" action="{{ route('admin.contact-messages.destroy', $msg) }}" class="mt-6" onsubmit="return confirm('Delete this message? This cannot be undone.')">
      @csrf @method('DELETE')
      <button class="btn btn-ghost btn-sm text-[#C42F2F]"><i data-icon="x" class="w-4 h-4"></i> Delete message</button>
    </form>
  @endcan
@endsection
