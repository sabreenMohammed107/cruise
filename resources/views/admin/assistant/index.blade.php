@extends('layouts.admin')
@use('App\Support\Format')
@use('Illuminate\Support\Str')

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

  <form method="GET" class="mt-6 flex flex-wrap gap-2 items-end">
    <div class="w-full sm:w-72">
      <label for="q" class="label">Search</label>
      <input id="q" name="q" value="{{ $filters['q'] }}" class="field !py-2" placeholder="Customer name or email, chat number, IP">
    </div>
    <div>
      <label for="who" class="label">Visitor</label>
      <select id="who" name="who" class="field !py-2 !w-auto">
        <option value="">All</option>
        <option value="signed_in" @selected($filters['who'] === 'signed_in')>Signed in</option>
        <option value="guests" @selected($filters['who'] === 'guests')>Guests</option>
      </select>
    </div>
    <div>
      <label for="lang" class="label">Language</label>
      <select id="lang" name="lang" class="field !py-2 !w-auto">
        <option value="">All</option>
        <option value="ar" @selected($filters['lang'] === 'ar')>Arabic</option>
        <option value="en" @selected($filters['lang'] === 'en')>English</option>
      </select>
    </div>
    <div>
      <label for="period" class="label">Started</label>
      <select id="period" name="period" class="field !py-2 !w-auto">
        <option value="">Any time</option>
        <option value="today" @selected($filters['period'] === 'today')>Today</option>
        <option value="7" @selected($filters['period'] === '7')>Last 7 days</option>
        <option value="30" @selected($filters['period'] === '30')>Last 30 days</option>
      </select>
    </div>
    <button class="btn btn-primary btn-sm">Filter</button>
    @if (array_filter($filters))
      <a href="{{ route('admin.assistant.index') }}" class="btn btn-ghost btn-sm">Clear</a>
    @endif
  </form>

  <div class="card overflow-hidden mt-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr>
            <th class="text-start font-semibold p-3">Visitor</th>
            <th class="text-start font-semibold p-3">First question</th>
            <th class="text-end font-semibold p-3">Messages</th>
            <th class="text-end font-semibold p-3">Tokens</th>
            <th class="text-end font-semibold p-3">Cost</th>
            <th class="text-start font-semibold p-3">Last message</th>
            <th class="p-3"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($chats as $c)
            <tr class="border-t border-line hover:bg-page">
              <td class="p-3">
                <a href="{{ route('admin.assistant.show', $c) }}" class="font-bold text-ocean-700 hover:underline">{{ $c->user?->name ?? 'Guest' }}</a>
                <span class="tag {{ $c->locale === 'ar' ? 'tag-ocean' : 'tag-lagoon' }} ms-1">{{ strtoupper($c->locale) }}</span>
                <p class="text-xs text-muted break-all">{{ $c->user?->email ?? 'Chat #'.$c->id }}</p>
              </td>
              <td class="p-3 max-w-md" dir="auto">{{ Str::limit($c->firstQuestion() ?? '—', 110) }}</td>
              <td class="p-3 text-end tabular">{{ $c->user_messages }}</td>
              <td class="p-3 text-end tabular text-muted">{{ number_format($c->input_tokens + $c->output_tokens) }}</td>
              @php $chatCost = \App\Services\Assistant\Assistant::cost($c->input_tokens, $c->output_tokens); @endphp
              <td class="p-3 text-end tabular text-muted">{{ $chatCost !== null ? '$'.number_format($chatCost, 4) : '—' }}</td>
              <td class="p-3 whitespace-nowrap text-muted">{{ Format::date($c->updated_at, 'j M, H:i') }}</td>
              <td class="p-3 text-end whitespace-nowrap">
                <a href="{{ route('admin.assistant.show', $c) }}" class="btn btn-ghost btn-sm">Open</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7"class="p-8 text-center text-muted">{{ array_filter($filters) ? 'No chats match these filters.' : 'No chats yet.' }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-5">{{ $chats->links() }}</div>
@endsection
