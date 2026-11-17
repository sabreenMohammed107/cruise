{{--
  The chats list with its filters (Admin → Assistant chats and Admin → Assistant tickets).
  $chats, $filters (App\Services\Assistant\ChatList), $route: the page's route name, $costs: show tokens and cost.
--}}
@php $canOpen = auth('admin')->user()?->can('manage-assistant'); @endphp

<form method="GET" action="{{ route($route) }}" class="mt-6 flex flex-wrap gap-2 items-end">
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
    <a href="{{ route($route) }}" class="btn btn-ghost btn-sm">Clear</a>
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
          @if ($costs)
            <th class="text-end font-semibold p-3">Tokens</th>
            <th class="text-end font-semibold p-3">Cost</th>
          @endif
          <th class="text-start font-semibold p-3">Last message</th>
          @if ($canOpen)<th class="p-3"><span class="sr-only">Actions</span></th>@endif
        </tr>
      </thead>
      <tbody>
        @forelse ($chats as $c)
          <tr class="border-t border-line hover:bg-page">
            <td class="p-3">
              @if ($canOpen)
                <a href="{{ route('admin.assistant.show', $c) }}" class="font-bold text-ocean-700 hover:underline">{{ $c->user?->name ?? 'Guest' }}</a>
              @else
                <span class="font-bold">{{ $c->user?->name ?? 'Guest' }}</span>
              @endif
              <span class="tag {{ $c->locale === 'ar' ? 'tag-ocean' : 'tag-lagoon' }} ms-1">{{ strtoupper($c->locale) }}</span>
              <p class="text-xs text-muted break-all">{{ $c->user?->email ?? 'Chat #'.$c->id }}</p>
            </td>
            <td class="p-3 max-w-md" dir="auto">{{ \Illuminate\Support\Str::limit($c->firstQuestion() ?? '—', 110) }}</td>
            <td class="p-3 text-end tabular">{{ $c->user_messages }}</td>
            @if ($costs)
              <td class="p-3 text-end tabular text-muted">{{ number_format($c->input_tokens + $c->output_tokens) }}</td>
              @php $chatCost = \App\Services\Assistant\Assistant::cost($c->input_tokens, $c->output_tokens); @endphp
              <td class="p-3 text-end tabular text-muted">{{ $chatCost !== null ? '$'.number_format($chatCost, 4) : '—' }}</td>
            @endif
            <td class="p-3 whitespace-nowrap text-muted">{{ \App\Support\Format::date($c->updated_at, 'j M, H:i') }}</td>
            @if ($canOpen)
              <td class="p-3 text-end whitespace-nowrap">
                <a href="{{ route('admin.assistant.show', $c) }}" class="btn btn-ghost btn-sm">Open</a>
              </td>
            @endif
          </tr>
        @empty
          <tr><td colspan="7" class="p-8 text-center text-muted">{{ array_filter($filters) ? 'No conversations match these filters.' : ($empty ?? 'No conversations yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="mt-5">{{ $chats->links() }}</div>
