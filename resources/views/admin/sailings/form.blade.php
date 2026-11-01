@extends('layouts.admin')
@use('App\Support\Format')

@php
  $title = $sailing ? 'Sailing '.$sailing->code : 'New sailing';
  $cabins = $cruise->ship->cabinCategories;
@endphp

@section('title', $title)

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb">
    <a href="{{ route('admin.cruises.index') }}" class="hover:text-ocean-600">Cruises</a> <span class="mx-1">/</span>
    <a href="{{ route('admin.cruises.sailings.index', $cruise) }}" class="hover:text-ocean-600">{{ $cruise->name }}</a> <span class="mx-1">/</span>
    <span class="text-ink">{{ $sailing?->code ?? 'New' }}</span>
  </nav>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $title }}</h1>
    @if ($sailing)
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('cruises.show', $cruise) }}?sailing={{ $sailing->code }}" target="_blank" class="btn btn-ghost btn-sm"><i data-icon="external" class="w-4 h-4"></i> View on site</a>
        @can('delete-records')
          <form method="POST" action="{{ route('admin.sailings.destroy', $sailing) }}" onsubmit="return confirm('Delete sailing {{ $sailing->code }}?')">
            @csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-[#C42F2F]"><i data-icon="x" class="w-4 h-4"></i> Delete</button>
          </form>
        @endcan
      </div>
    @endif
  </div>

  <form method="POST" action="{{ $sailing ? route('admin.sailings.update', $sailing) : route('admin.cruises.sailings.store', $cruise) }}" class="mt-6 grid gap-6" novalidate>
    @csrf
    @if ($sailing) @method('PUT') @endif

    <section class="card p-5 sm:p-6 grid sm:grid-cols-3 gap-5">
      <div>
        <label class="label" for="code">Code</label>
        <input id="code" name="code" class="field tabular uppercase" value="{{ old('code', $sailing?->code) }}" placeholder="e.g. RSD-270320">
        <p class="text-xs text-muted mt-1.5">Shown to guests in the address (?sailing=).</p>
      </div>
      <div>
        <label class="label" for="departure_date">Departure date</label>
        <input id="departure_date" name="departure_date" type="date" class="field" value="{{ old('departure_date', $sailing?->departure_date?->toDateString()) }}">
        <p class="text-xs text-muted mt-1.5">Returns {{ $cruise->nights }} nights later.</p>
      </div>
      <div>
        <label class="label" for="status">Status</label>
        <select id="status" name="status" class="field">
          @foreach (\App\Enums\SailingStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected(old('status', $sailing?->status?->value ?? 'open') === $s->value)>{{ ucfirst($s->value) }}</option>
          @endforeach
        </select>
        <p class="text-xs text-muted mt-1.5">Only open sailings can be booked.</p>
      </div>
    </section>

    <section class="card overflow-x-auto">
      <div class="p-5 sm:p-6 pb-0">
        <h2 class="font-display font-semibold text-lg">Cabin prices</h2>
        <p class="text-sm text-muted max-w-[80ch]">
          Price of the whole cabin for the {{ $cruise->nights }}-night cruise, in US dollars, by the number of paying guests (adults and children):
          <b>Single</b> = 1 guest, <b>Double</b> = 2 guests, <b>3rd guest</b> = added to the double price for a third adult or child.
          A cabin holds at most {{ config('cruise.max_cabin_guests') }} guests, infants included. Infants travel free and must be with at least one adult.
          Prices include tax: there are no separate taxes or port fees.
        </p>
        @if (! $sailing && $prices->isNotEmpty())
          <p class="text-sm text-muted mt-2">Filled in from this cruise's latest sailing. Change them as needed.</p>
        @endif

        {{-- Agent type tabs: every type's "Agent %" column is in the page, the script below shows one (no reload),
             and saving saves them all. agent_tab reopens the same tab after saving. --}}
        <input type="hidden" name="agent_tab" value="{{ old('agent_tab', $agentTab) }}">
        @if (count($agentTabs) > 1)
          <div class="flex flex-wrap items-center gap-2 mt-4 text-sm" role="tablist" aria-label="Agent type">
            <span class="font-semibold text-muted me-1">Agent % for:</span>
            @foreach ($agentTabs as $key => $name)
              <button type="button" role="tab" data-agent-tab="{{ $key }}" data-name="{{ $name }}" aria-selected="false"
                      class="rounded-full px-3 py-1 font-semibold border border-line text-ink hover:border-ocean-600">{{ $key === 'all' ? 'All agent types' : $name }}</button>
            @endforeach
          </div>
        @endif
      </div>
      <table class="w-full text-sm mt-4">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr>
            <th class="text-start font-semibold p-3">Cabin</th>
            <th class="text-start font-semibold p-3">Single <span class="font-normal text-muted">(1 guest)</span></th>
            <th class="text-start font-semibold p-3">Double <span class="font-normal text-muted">(2 guests)</span></th>
            <th class="text-start font-semibold p-3">3rd guest <span class="font-normal text-muted">(adult or child)</span></th>
            <th class="text-start font-semibold p-3">Infant</th>
            <th class="text-start font-semibold p-3">Cabins available</th>
            <th class="text-start font-semibold p-3">Sold out</th>
            <th class="text-start font-semibold p-3">Agent % <span class="block font-normal text-xs text-muted" data-agent-heading>all agent types</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($cabins as $cabin)
            @php($p = $prices->get($cabin->id))
            {{-- Per tab key: the cabin's fallback without its own rate (null: use the live "all" column) --}}
            <tr class="border-t border-line align-top" data-agent-row
                data-agent-fallback="{{ json_encode(collect($agentFallback)->map(fn ($byCabin) => $byCabin[$cabin->id] ?? null)) }}">
              <td class="p-3 font-bold">{{ $cabin->name_en }}<p class="text-xs text-muted font-normal">Up to {{ $cabin->capacity() }} guests incl. infants</p></td>
              @foreach (['single' => 'single', 'double' => 'double', 'extra' => '3rd guest'] as $tier => $label)
                <td class="p-3">
                  <input name="prices[{{ $cabin->id }}][{{ $tier }}]" type="number" step="0.01" min="0" class="field !py-2 tabular !w-28 @error("prices.{$cabin->id}.{$tier}") is-invalid @enderror" aria-label="{{ $cabin->name_en }} {{ $label }} price"
                         value="{{ old("prices.{$cabin->id}.{$tier}", $p ? (float) $p->{'price_'.$tier} : '') }}" required data-agent-tier>
                  {{-- Filled in by the script below: this price less the row's Agent % --}}
                  <p class="text-xs font-semibold text-[#12805C] mt-1 tabular" data-agent-price hidden></p>
                  @error("prices.{$cabin->id}.{$tier}")<p class="text-xs text-[#C42F2F] mt-1">{{ $message }}</p>@enderror
                </td>
              @endforeach
              <td class="p-3 pt-5 text-muted">Free</td>
              <td class="p-3"><input name="prices[{{ $cabin->id }}][capacity]" type="number" min="0" class="field !py-2 tabular !w-28" placeholder="No limit" aria-label="{{ $cabin->name_en }} cabins available"
                    value="{{ old("prices.{$cabin->id}.capacity", $p?->capacity) }}"></td>
              <td class="p-3">
                <input type="hidden" name="prices[{{ $cabin->id }}][sold_out]" value="0">
                <input type="checkbox" name="prices[{{ $cabin->id }}][sold_out]" value="1" class="check" aria-label="{{ $cabin->name_en }} sold out" @checked(old("prices.{$cabin->id}.sold_out", $p?->is_sold_out))>
              </td>
              <td class="p-3">
                {{-- One field per agent type, the script shows the current tab's. Empty: the general rate (placeholder) applies --}}
                @foreach ($agentTabs as $key => $name)
                  <div data-agent-col="{{ $key }}" @if ($key !== $agentTab) hidden @endif>
                    <input name="agent[{{ $key }}][{{ $cabin->id }}]" type="number" step="0.01" min="0" max="100" data-agent-percent="{{ $key }}"
                           class="field !py-2 tabular !w-28 @error("agent.{$key}.{$cabin->id}") is-invalid @enderror" aria-label="{{ $cabin->name_en }} agent commission percent, {{ $name }}"
                           value="{{ old("agent.{$key}.{$cabin->id}", $agentRates[$key][$cabin->id] ?? '') }}">
                    @error("agent.{$key}.{$cabin->id}")<p class="text-xs text-[#C42F2F] mt-1">{{ $message }}</p>@enderror
                  </div>
                @endforeach
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <p class="p-5 text-xs text-muted">
        Example: 2 adults + 1 child pay Double + 3rd guest; 2 adults + 1 infant pay Double; 1 adult + 2 infants pay Single.
        "Cabins available" stops online bookings once that many confirmed bookings exist for the cabin on this date. Leave empty for no limit.
      </p>
      <p class="px-5 pb-5 -mt-3 text-xs text-muted">
        <b>Agent %</b>: agents of the chosen type pay these prices less this commission (shown in green under each price). It applies to
        <b>every sailing of {{ $cruise->name }}</b>. Leave it empty to use the general rate shown in grey; 0 = no agent commission for this cabin.
        @if (count($agentTabs) > 1) An agent gets their own type's rates first, then the ones for all agent types. Saving saves every type. @endif
        All rates: <a href="{{ route('admin.agent-rates.index') }}" class="text-ocean-600 font-semibold hover:underline">Agents → Agent rates</a>.
      </p>
    </section>

    <div class="flex flex-wrap justify-between gap-3">
      <a href="{{ route('admin.cruises.sailings.index', $cruise) }}" class="btn btn-outline">Back to sailings</a>
      <button class="btn btn-primary">{{ $sailing ? 'Save sailing' : 'Create sailing' }}</button>
    </div>
  </form>

  @push('scripts')
    <script>
      /* "Agent %" by agent type, without reloading: every type's fields are in the page, one tab's are shown.
         Same rules as App\Services\AgentPricing: a cabin's own rate → its type's wider rates → the "All" column. */
      (() => {
        const tabField = document.querySelector('[name="agent_tab"]');
        const tabs = [...document.querySelectorAll('[data-agent-tab]')];
        const rows = [...document.querySelectorAll('[data-agent-row]')];
        const heading = document.querySelector('[data-agent-heading]');
        // 846, 436.50 (cents always in two digits)
        const money = (n) => n.toLocaleString('en-US', { minimumFractionDigits: Number.isInteger(n) ? 0 : 2, maximumFractionDigits: 2 });
        const nameOf = (key) => tabs.find((t) => t.dataset.agentTab === key)?.dataset.name || 'Agent'; // no types: no tabs

        const own = (row, key) => {
          const value = row.querySelector(`[data-agent-percent="${key}"]`)?.value.trim() ?? '';
          return value === '' ? null : (parseFloat(value) || 0);
        };
        // Without the cabin's own rate: for "all" its general rate; for a type its wider rates, else the live "All" column
        const fallback = (row, key) => {
          const saved = JSON.parse(row.dataset.agentFallback)[key];
          return key === 'all' ? (saved ?? 0) : (saved ?? percent(row, 'all'));
        };
        const percent = (row, key) => own(row, key) ?? fallback(row, key);

        const paint = () => {
          const key = tabField.value;
          tabs.forEach((t) => {
            const on = t.dataset.agentTab === key;
            t.setAttribute('aria-selected', on);
            ['bg-ocean-600', 'border-ocean-600', 'text-white'].forEach((c) => t.classList.toggle(c, on));
            ['border-line', 'text-ink'].forEach((c) => t.classList.toggle(c, !on));
          });
          heading.textContent = key === 'all' ? 'all agent types' : `${nameOf(key)} agents`;

          rows.forEach((row) => {
            row.querySelectorAll('[data-agent-col]').forEach((col) => { col.hidden = col.dataset.agentCol !== key; });
            row.querySelectorAll('[data-agent-percent]').forEach((f) => { f.placeholder = `${money(fallback(row, f.dataset.agentPercent))}%`; });
            // Price × (100 − Agent %) / 100, to the cent (AgentPricing::discounted), labelled with the type: "Gold: $1,332"
            const p = percent(row, key);
            row.querySelectorAll('[data-agent-tier]').forEach((field) => {
              const out = field.parentElement.querySelector('[data-agent-price]');
              const price = parseFloat(field.value);
              out.hidden = !(p > 0 && price > 0);
              if (!out.hidden) out.textContent = `${nameOf(key)}: $${money(Math.round(price * (100 - p)) / 100)}`;
            });
          });
        };

        const show = (key) => {
          tabField.value = key;
          // Keeps the tab on a browser refresh, without reloading now
          const url = new URL(location.href);
          key === 'all' ? url.searchParams.delete('tier') : url.searchParams.set('tier', key);
          history.replaceState(null, '', url);
          paint();
        };

        tabs.forEach((t) => t.addEventListener('click', () => show(t.dataset.agentTab)));
        rows.forEach((row) => row.querySelectorAll('input').forEach((input) => input.addEventListener('input', paint)));
        // After a failed save, open the tab whose field needs fixing
        const invalid = document.querySelector('[data-agent-percent].is-invalid');
        invalid ? show(invalid.dataset.agentPercent) : paint();
      })();
    </script>
  @endpush
@endsection
