@extends('layouts.admin')

@section('title', 'Assistant tickets')

@section('content')
  @php
    $labels = ['active' => 'Active', 'scheduled' => 'Starts later', 'used_up' => 'Used up', 'expired' => 'Ended'];
    $badge = [
        'active' => ['tag-lagoon', 'Active'],
        'scheduled' => ['tag-sun', 'Starts later'],
        'used_up' => ['tag-sun', 'Used up'],
        'expired' => ['tag-sun', 'Ended'],
        'none' => ['tag-sun', 'No package'],
    ][$status];
    // Tickets are counted in fractions; shown rounded down, with one decimal under 100
    $fmt = fn (float $n) => number_format(floor($n * 10) / 10, $n < 100 && floor($n) != $n ? 1 : 0);
  @endphp

  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">Assistant tickets</h1>
      <p class="text-muted mt-1">The website chat's package: each message uses tickets, and the package lasts one month.</p>
    </div>
  </div>

  @include('admin.partials.flash')

  {{-- Current package --}}
  <section class="card p-5 mt-5">
    <p class="font-bold"><span class="tag {{ $badge[0] }}">{{ $badge[1] }}</span>
      @switch($status)
        @case('active') The chat is answering visitors. @break
        @case('scheduled') The chat is stopped until the package starts. @break
        @case('used_up') The tickets are used up: the chat is stopped until the package is renewed. @break
        @case('expired') The month is over: the chat is stopped until the package is renewed. @break
        @default No package yet.
      @endswitch
    </p>

    @if ($package)
      @php $pct = $package->tickets > 0 ? min(100, $package->tickets_used / $package->tickets * 100) : 100; @endphp
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mt-4">
        @foreach ([
            ['ticket', $fmt($package->remaining()).' / '.number_format($package->tickets), 'Tickets left'],
            ['edit', $fmt($package->tickets_used), 'Tickets used'],
            ['calendar', $package->starts_at->format('j M Y, H:i'), 'Starts'],
            ['calendar', $package->ends_at->format('j M Y, H:i'), $package->ends_at->isFuture() ? 'Ends ('.max(0, (int) ceil(now()->floatDiffInDays($package->ends_at))).' days left)' : 'Ended'],
        ] as [$icon, $value, $label])
          <div class="rounded-2xl border border-line p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-2xl bg-ocean-50 text-ocean-700 grid place-items-center shrink-0"><i data-icon="{{ $icon }}" class="w-5 h-5"></i></span>
            <div><p class="font-display font-bold text-lg tabular" dir="ltr">{{ $value }}</p><p class="text-sm text-muted">{{ $label }}</p></div>
          </div>
        @endforeach
      </div>
      <div class="mt-4 h-2.5 rounded-full bg-ocean-50 overflow-hidden" role="progressbar" aria-valuenow="{{ round($pct) }}" aria-valuemin="0" aria-valuemax="100" aria-label="Tickets used">
        <div class="h-full {{ $pct >= 90 ? 'bg-[#C42F2F]' : 'bg-ocean-600' }}" style="width: {{ $pct }}%"></div>
      </div>
    @endif
  </section>

  {{-- Conversations: today, and in the package shown (this month before the first package) --}}
  @php $in = $package ? ($status === 'active' ? 'in this package' : 'in the last package') : 'this month'; @endphp
  <div class="grid gap-4 sm:grid-cols-3 mt-5">
    @foreach ([
        ['chat', number_format($stats['today']), 'Conversations today'],
        ['calendar', number_format($stats['conversations']), 'Conversations '.$in],
        ['edit', number_format($stats['messages']), 'Messages '.$in],
    ] as [$icon, $value, $label])
      <div class="card p-5 flex items-center gap-4">
        <span class="w-11 h-11 rounded-2xl bg-ocean-50 text-ocean-700 grid place-items-center shrink-0"><i data-icon="{{ $icon }}" class="w-5 h-5"></i></span>
        <div><p class="font-display font-bold text-2xl tabular">{{ $value }}</p><p class="text-sm text-muted">{{ $label }}</p></div>
      </div>
    @endforeach
  </div>

  @include('admin.assistant.partials.chats', ['route' => 'admin.assistant-tickets.index', 'costs' => false, 'empty' => $package ? 'No conversations in this package yet.' : null])

  {{-- Past packages --}}
  @if ($packages->isNotEmpty())
    <h2 class="font-display font-semibold text-lg mt-8">Packages</h2>
    <div class="card overflow-hidden mt-3">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-ocean-50 text-ocean-800">
            <tr>
              <th class="text-start font-semibold p-3">Starts</th>
              <th class="text-start font-semibold p-3">Ends</th>
              <th class="text-end font-semibold p-3">Tickets</th>
              <th class="text-end font-semibold p-3">Used</th>
              <th class="text-end font-semibold p-3">Messages</th>
              <th class="text-start font-semibold p-3">Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($packages as $p)
              <tr class="border-t border-line">
                <td class="p-3 tabular" dir="ltr">{{ $p->starts_at->format('j M Y, H:i') }}</td>
                <td class="p-3 tabular" dir="ltr">{{ $p->ends_at->format('j M Y, H:i') }}</td>
                <td class="p-3 text-end tabular">{{ number_format($p->tickets) }}</td>
                <td class="p-3 text-end tabular">{{ $fmt($p->tickets_used) }}</td>
                <td class="p-3 text-end tabular">{{ number_format($p->messages) }}</td>
                <td class="p-3 text-muted">{{ $labels[$p->status()] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    <div class="mt-4">{{ $packages->links() }}</div>
  @endif
@endsection
