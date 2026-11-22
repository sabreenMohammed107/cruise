@extends('layouts.admin')
@use('App\Support\Format')

@section('title', 'Chat #'.$chat->id)

@php
  $tools = [
      'search_cruises' => 'Searched cruises',
      'get_cruise_details' => 'Opened cruise details',
      'quote_price' => 'Calculated a price',
      'list_destinations' => 'Looked up destinations',
      'get_ship_info' => 'Looked up ship information',
      'get_policies' => 'Looked up booking rules',
      'contact_team' => 'Gave the team\'s contacts',
      'get_my_bookings' => 'Looked up the customer\'s bookings',
      'offer_choices' => 'Showed choice buttons',
      'run_report' => 'Made a report',
      'list_report_templates' => 'Listed saved report templates',
  ];
  // Who wrote: the account's name (agent or customer), or "Visitor" when not signed in
  $who = $chat->user?->name ?? 'Visitor';
  $when = fn (?string $at) => $at ? Format::date(\Illuminate\Support\Carbon::parse($at)->timezone(config('app.timezone')), 'j M Y, H:i') : null;
  // Newest first
  $entries = array_reverse($transcript);
@endphp

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb"><a href="{{ route('admin.assistant.index') }}" class="hover:text-ocean-600">Assistant chats</a> <span class="mx-1">/</span> <span class="text-ink">Chat #{{ $chat->id }}</span></nav>

  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $chat->user?->name ?? 'Guest' }} <span class="text-muted font-normal text-lg">· Chat #{{ $chat->id }}</span></h1>
      <p class="text-muted mt-1">Started {{ Format::date($chat->created_at, 'j M Y, H:i') }} · last message {{ Format::date($chat->updated_at, 'j M Y, H:i') }}</p>
    </div>
    @if ($chat->user && ! $chat->user->isStaff())
      <a href="{{ $chat->user->isAgent() ? route('admin.agents.show', $chat->user) : route('admin.customers.show', $chat->user) }}" class="btn btn-outline btn-sm"><i data-icon="user" class="w-4 h-4"></i> {{ $chat->user->isAgent() ? 'Agent' : 'Customer' }} details</a>
    @endif
  </div>

  <section class="card p-5 sm:p-6 mt-6">
    <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 text-sm">
      <div><dt class="text-xs font-semibold text-muted">{{ $chat->user ? ($chat->user->isAgent() ? 'Agent' : 'Customer') : 'Visitor' }}</dt><dd class="font-bold mt-0.5 break-all">{{ $chat->user ? $chat->user->name.' · '.$chat->user->email : 'Not signed in' }}</dd></div>
      <div><dt class="text-xs font-semibold text-muted">Website language</dt><dd class="font-bold mt-0.5">{{ $chat->locale === 'ar' ? 'Arabic' : 'English' }}</dd></div>
      <div><dt class="text-xs font-semibold text-muted">Messages sent</dt><dd class="font-bold mt-0.5 tabular">{{ $chat->user_messages }}</dd></div>
      <div>
        <dt class="text-xs font-semibold text-muted">Tokens</dt>
        <dd class="font-bold mt-0.5 tabular">{{ number_format($chat->input_tokens) }} in · {{ number_format($chat->output_tokens) }} out @if ($cost !== null)<span class="text-muted font-normal">(≈ ${{ number_format($cost, 4) }})</span>@endif</dd>
      </div>
      @if ($chat->ip)
        <div><dt class="text-xs font-semibold text-muted">IP address</dt><dd class="font-bold mt-0.5" dir="ltr">{{ $chat->ip }}</dd></div>
      @endif
    </dl>
  </section>

  <section class="card p-5 sm:p-6 mt-6">
    <h2 class="font-display font-semibold text-lg mb-1">Conversation</h2>
    <p class="text-sm text-muted mb-4">Newest first. The grey lines are the steps the assistant took (looking up data, showing buttons, making a report) before answering.</p>

    <div class="grid gap-3">
      @forelse ($entries as $entry)
        @if ($entry['kind'] === 'user')
          <div class="max-w-[85%] rounded-2xl rounded-ss-md bg-ocean-50 border border-ocean-100 px-4 py-3">
            <p class="text-xs font-semibold text-ocean-700 mb-1 flex flex-wrap items-center gap-x-2">
              <span>{{ $who }}</span>
              @if ($chat->user?->isAgent())<span class="tag tag-ocean">Agent</span>@endif
              @if ($t = $when($entry['at']))<span class="font-normal text-muted tabular">{{ $t }}</span>@endif
              @if (! empty($entry['image']))<span class="tag tag-sun" title="Photos are sent to Claude with the message and never saved">Sent a photo (not saved)</span>@endif
            </p>
            <p class="whitespace-pre-line break-words leading-relaxed" dir="auto">{{ $entry['text'] }}</p>
          </div>
        @elseif ($entry['kind'] === 'assistant')
          <div class="max-w-[85%] ms-auto rounded-2xl rounded-se-md bg-white border border-line px-4 py-3">
            <p class="text-xs font-semibold text-muted mb-1 flex flex-wrap items-center gap-x-2">
              <span>Assistant</span>
              @if ($t = $when($entry['at']))<span class="font-normal tabular">{{ $t }}</span>@endif
            </p>
            <p class="whitespace-pre-line break-words leading-relaxed" dir="auto">{{ $entry['text'] }}</p>
          </div>
        @else
          <div class="ms-auto w-full sm:w-[85%] rounded-xl bg-page border border-line text-sm px-4 py-2 flex flex-wrap items-center gap-2 text-muted">
            <i data-icon="search" class="w-4 h-4 shrink-0"></i>
            <span class="font-semibold">{{ $tools[$entry['name']] ?? $entry['name'] }}</span>
            @if ($entry['name'] === 'run_report' && is_array($entry['result']) && isset($entry['result']['title']))
              <span dir="auto">· {{ $entry['result']['title'] }}@isset($entry['result']['rows']) ({{ $entry['result']['rows'] }} rows)@endisset</span>
            @endif
            @if ($entry['error'])<span class="tag tag-sun">No result</span>@endif
            @if ($t = $when($entry['at']))<span class="ms-auto text-xs tabular">{{ $t }}</span>@endif
          </div>
        @endif
      @empty
        <p class="text-muted">This chat has no messages.</p>
      @endforelse
    </div>
  </section>

  @can('delete-records')
    <form method="POST" action="{{ route('admin.assistant.destroy', $chat) }}" class="mt-6" onsubmit="return confirm('Delete this chat? This cannot be undone.')">
      @csrf @method('DELETE')
      <button class="btn btn-ghost btn-sm text-[#C42F2F]"><i data-icon="x" class="w-4 h-4"></i> Delete chat</button>
    </form>
  @endcan
@endsection
