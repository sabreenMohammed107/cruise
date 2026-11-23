@extends('layouts.admin')
@use('App\Support\Format')

@section('title', 'Assistant chats')

@section('content')
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">Assistant chats</h1>
      <p class="text-muted mt-1">What visitors asked the website chat and what it answered, from the live cruise data.</p>
    </div>
  </div>

  @include('admin.partials.flash')

  {{-- Status and on / off switch --}}
  <section class="card p-5 mt-5 flex flex-wrap items-center gap-4">
    <div class="flex-1 min-w-60">
      @if (! $configured)
        <p class="font-bold"><span class="tag tag-sun">Not set up</span></p>
        <p class="text-sm text-muted mt-1">The chat needs an API key: set <code dir="ltr">ANTHROPIC_API_KEY</code> in the server's <code>.env</code> (and <code dir="ltr">ASSISTANT_ENABLED=true</code>). Until then visitors don't see it.</p>
      @elseif ($switchedOn)
        @if ($audience === 'everyone')
          <p class="font-bold"><span class="tag tag-lagoon">On</span> Visitors see the chat on every page.</p>
        @else
          <p class="font-bold"><span class="tag tag-sun">Test mode</span> Only you and the agents chosen below see the chat.</p>
        @endif
        <p class="text-sm text-muted mt-1">Model <span dir="ltr">{{ $model }}</span>. Limits: {{ $limits['per_minute']['value'] }} messages a minute and {{ $limits['per_hour']['value'] }} an hour per visitor, {{ $limits['per_chat']['value'] }} messages per chat.</p>
      @else
        <p class="font-bold"><span class="tag tag-sun">Off</span> Switched off here: visitors don't see the chat.</p>
      @endif
    </div>
    @if ($configured)
      <form method="POST" action="{{ route('admin.assistant.toggle') }}">
        @csrf @method('PUT')
        <input type="hidden" name="enabled" value="{{ $switchedOn ? 0 : 1 }}">
        <button class="btn btn-sm {{ $switchedOn ? 'btn-outline' : 'btn-primary' }}">{{ $switchedOn ? 'Switch the chat off' : 'Switch the chat on' }}</button>
      </form>
    @endif
  </section>

  {{-- Who sees it --}}
  <section class="card p-5 mt-5">
    <h2 class="font-display font-semibold text-lg">Who sees the chat</h2>
    <p class="text-sm text-muted mt-1">While you try it on the live site, keep it in test mode: only you (the super admin, signed in here or on the website) and the agents you tick see the chat. Everyone else, admins and other agents included, sees nothing.</p>
    <form method="POST" action="{{ route('admin.assistant.audience') }}" class="mt-4 space-y-4">
      @csrf @method('PUT')
      <div class="flex flex-wrap gap-4">
        <label class="flex items-center gap-2"><input type="radio" name="audience" value="testers" @checked(old('audience', $audience) === 'testers')> Test mode: me and the chosen agents</label>
        <label class="flex items-center gap-2"><input type="radio" name="audience" value="everyone" @checked(old('audience', $audience) === 'everyone')> Everyone</label>
      </div>
      <div>
        <p class="label">Agents who see it in test mode (signed in on the website)</p>
        @if ($agents->isEmpty())
          <p class="text-sm text-muted">No agent accounts yet.</p>
        @else
          <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 max-h-56 overflow-y-auto">
            @foreach ($agents as $agent)
              <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="testers[]" value="{{ $agent->id }}" @checked(in_array($agent->id, old('testers', $testers)))>
                <span>{{ $agent->name }} <span class="text-muted break-all">{{ $agent->email }}</span></span>
              </label>
            @endforeach
          </div>
        @endif
        @error('testers.*')<p class="text-xs text-[#C42F2F] mt-1">{{ $message }}</p>@enderror
      </div>
      <button class="btn btn-primary btn-sm">Save</button>
    </form>
  </section>

  {{-- Limits --}}
  <section class="card p-5 mt-5">
    <h2 class="font-display font-semibold text-lg">Limits</h2>
    <p class="text-sm text-muted mt-1">Stop one visitor from sending too many messages (and spending the API balance). A visitor over a limit is asked to wait; a chat over its limit is asked to start a new one or contact the team.</p>
    <form method="POST" action="{{ route('admin.assistant.limits') }}" class="mt-4 flex flex-wrap items-start gap-4">
      @csrf @method('PUT')
      @foreach ($limits as $name => $limit)
        <div>
          <label for="limit-{{ $name }}" class="label">{{ $limit['label'] }}</label>
          <input id="limit-{{ $name }}" name="{{ $name }}" type="number" min="1" max="10000" required value="{{ old($name, $limit['value']) }}" class="field !py-2 !w-40 tabular">
          <p class="text-xs text-muted mt-1">Default {{ $limit['default'] }}</p>
          @error($name)<p class="text-xs text-[#C42F2F] mt-1">{{ $message }}</p>@enderror
        </div>
      @endforeach
      <button class="btn btn-primary btn-sm mt-6">Save limits</button>
    </form>
  </section>

  {{-- Tickets package (shown in tickets on Admin → Assistant tickets) --}}
  <section id="package" class="card p-5 mt-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="font-display font-semibold text-lg">Tickets package</h2>
        <p class="text-sm text-muted mt-1">Tickets for an amount in USD, for one month from the start. Each message uses tickets in proportion to what it cost; the chat stops when they are used up or the month is over. Renewing replaces the current package: full tickets and a new month.</p>
      </div>
      <a href="{{ route('admin.assistant-tickets.index') }}" class="btn btn-ghost btn-sm">Assistant tickets</a>
    </div>

    @if ($package)
      @php
        $state = $package->status();
        $label = ['active' => ['tag-lagoon', 'Active'], 'scheduled' => ['tag-sun', 'Starts later'], 'used_up' => ['tag-sun', 'Used up: renew'], 'expired' => ['tag-sun', 'Ended: renew']][$state];
      @endphp
      <p class="text-sm mt-3"><span class="tag {{ $label[0] }}">{{ $label[1] }}</span>
        <span class="tabular" dir="ltr">{{ number_format(floor($package->remaining())) }} / {{ number_format($package->tickets) }}</span> tickets left,
        until <span class="tabular" dir="ltr">{{ $package->ends_at->format('j M Y, H:i') }}</span>.
        ${{ number_format($package->price_usd, 2) }} for {{ number_format($package->tickets) }} tickets: 1 ticket = ${{ number_format($package->price_usd / max(1, $package->tickets), 4) }} of chat usage.
      </p>
    @else
      <p class="text-sm mt-3"><span class="tag tag-sun">No package</span> The chat runs without tickets until you start the first package.</p>
    @endif
    @unless ($pricesSet)
      <p class="text-xs text-[#C42F2F] mt-2">The model's prices aren't set, so messages can't be turned into tickets. Set <code dir="ltr">ASSISTANT_PRICE_INPUT</code> and <code dir="ltr">ASSISTANT_PRICE_OUTPUT</code> in <code>.env</code>.</p>
    @endunless

    <form method="POST" action="{{ route('admin.assistant.package') }}" class="mt-4 flex flex-wrap items-start gap-4">
      @csrf
      <div>
        <label for="tickets" class="label">Tickets</label>
        <input id="tickets" name="tickets" type="number" min="1" required value="{{ old('tickets', $package?->tickets ?? 1000) }}" class="field !py-2 !w-40 tabular">
        @error('tickets')<p class="text-xs text-[#C42F2F] mt-1">{{ $message }}</p>@enderror
      </div>
      <div>
        <label for="price_usd" class="label">For (USD)</label>
        <input id="price_usd" name="price_usd" type="number" min="0.01" step="0.01" required value="{{ old('price_usd', $package?->price_usd ?? 20) }}" class="field !py-2 !w-40 tabular">
        @error('price_usd')<p class="text-xs text-[#C42F2F] mt-1">{{ $message }}</p>@enderror
      </div>
      <div>
        <label for="starts_at" class="label">Starts</label>
        <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}" class="field !py-2 !w-auto">
        <p class="text-xs text-muted mt-1">Ends one month later</p>
        @error('starts_at')<p class="text-xs text-[#C42F2F] mt-1">{{ $message }}</p>@enderror
      </div>
      <button class="btn btn-primary btn-sm mt-6">{{ $package ? 'Renew' : 'Start package' }}</button>
    </form>
  </section>

  {{-- This month --}}
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 mt-5">
    @foreach ([
        ['chat', number_format($stats['today']), 'Chats today'],
        ['calendar', number_format($stats['chats']), 'Chats this month'],
        ['edit', number_format($stats['messages']), 'Messages this month'],
        ['card', $stats['cost'] !== null ? '$'.number_format($stats['cost'], 3) : number_format($stats['input'] + $stats['output']), $stats['cost'] !== null ? 'Cost this month (USD)' : 'Tokens this month'],
        ['card', $stats['cost_all'] !== null ? '$'.number_format($stats['cost_all'], 3) : '—', 'Total cost so far (USD)'],
    ] as [$icon, $value, $label])
      <div class="card p-5 flex items-center gap-4">
        <span class="w-11 h-11 rounded-2xl bg-ocean-50 text-ocean-700 grid place-items-center shrink-0"><i data-icon="{{ $icon }}" class="w-5 h-5"></i></span>
        <div><p class="font-display font-bold text-2xl tabular">{{ $value }}</p><p class="text-sm text-muted">{{ $label }}</p></div>
      </div>
    @endforeach
  </div>
  @if ($stats['cost'] === null)
    <p class="text-xs text-muted mt-2">{{ number_format($stats['input']) }} input and {{ number_format($stats['output']) }} output tokens. To see a cost estimate, set <code dir="ltr">ASSISTANT_PRICE_INPUT</code> and <code dir="ltr">ASSISTANT_PRICE_OUTPUT</code> (USD per million tokens) from the Anthropic pricing page.</p>
  @else
    <p class="text-xs text-muted mt-2">Estimated from the tokens used ({{ number_format($stats['input']) }} input, {{ number_format($stats['output']) }} output this month) at ${{ config('assistant.price_input_per_mtok') }} / ${{ config('assistant.price_output_per_mtok') }} per million input / output tokens. Saved (cached) instructions cost less, so the real bill is a little lower; the exact amount is in the Anthropic Console.</p>
  @endif

  @include('admin.assistant.partials.chats', ['route' => 'admin.assistant.index', 'costs' => true])
@endsection
