@extends('layouts.admin')
@use('App\Support\Format')
@use('App\Enums\BookingStatus')

@php($title = $agent->company_name ?: $agent->name)
@section('title', 'Agent: '.$title)

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb"><a href="{{ route('admin.agents.index') }}" class="hover:text-ocean-600">Agents</a> <span class="mx-1">/</span> <span class="text-ink">{{ $title }}</span></nav>

  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $title }}</h1>
        @if ($agent->agentTier)<span class="tag tag-lagoon">{{ $agent->agentTier->name }}</span>@endif
        @unless ($agent->isAgent())<span class="tag tag-white">No longer an agent</span>@endunless
      </div>
      <p class="text-muted mt-1">{{ $agent->name }} · {{ $agent->email }}@if ($agent->full_phone) · <span dir="ltr">{{ $agent->full_phone }}</span>@endif</p>
    </div>
    <div class="flex flex-wrap gap-2">
      @if ($bookings->isNotEmpty())
        <a href="{{ route('admin.bookings.index', ['channel' => 'agent', 'agent' => $agent->id]) }}" class="btn btn-outline btn-sm"><i data-icon="ticket" class="w-4 h-4"></i> In Bookings</a>
      @endif
      @can('manage-users')
        @if ($agent->isAgent())
          <a href="{{ route('admin.agents.edit', $agent->id) }}" class="btn btn-primary btn-sm"><i data-icon="edit" class="w-4 h-4"></i> Edit</a>
        @endif
      @endcan
    </div>
  </div>

  {{-- Balance: bookings not cancelled. The commission is already taken off the price, so "Due" is what the agent owes. --}}
  <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-6">
    @foreach ([
        ['Bookings', $totals['count'], $totals['cancelled'] ? $totals['cancelled'].' cancelled, not counted' : null],
        ['Original price', Format::money($totals['original'], 2), 'Public price of these bookings'],
        ['Total commission', Format::money($totals['commission'], 2), $totals['original'] > 0 ? round($totals['commission'] / $totals['original'] * 100, 2).'% of the original price' : null],
        ['Net sales', Format::money($totals['net'], 2), $hasPromo ? 'After commission and '.Format::money($totals['promo'], 2).' in promo codes' : 'After commission'],
        ['Paid', Format::money($totals['paid'], 2), 'Recorded on the bookings'],
        ['Due', Format::money($totals['due'], 2), 'Still owed by the agent'],
    ] as [$label, $value, $hint])
      <div class="card p-5 {{ $label === 'Total commission' ? 'ring-2 ring-[#12805C]/30' : '' }}">
        <p class="text-xs font-semibold text-muted">{{ $label }}</p>
        <p class="font-display font-bold text-2xl mt-1 tabular {{ $label === 'Total commission' ? 'text-[#12805C]' : '' }}">{{ $value }}</p>
        @if ($hint)<p class="text-xs text-muted mt-1">{{ $hint }}</p>@endif
      </div>
    @endforeach
  </div>

  {{-- Every booking, with its commission --}}
  <section class="mt-6">
    <h2 class="font-display font-semibold text-lg mb-3">Bookings and commission</h2>
    <div class="card overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr>
            <th class="text-start font-semibold p-3">Reference</th>
            <th class="text-start font-semibold p-3">Sailing</th>
            <th class="text-start font-semibold p-3">Status</th>
            <th class="text-end font-semibold p-3">Original price</th>
            <th class="text-end font-semibold p-3">Commission</th>
            @if ($hasPromo)<th class="text-end font-semibold p-3">Promo</th>@endif
            <th class="text-end font-semibold p-3">Net total</th>
            <th class="text-end font-semibold p-3">Paid</th>
            <th class="text-end font-semibold p-3">Due</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($bookings as $b)
            @php($cancelled = $b->status === BookingStatus::Cancelled)
            @php($percent = $b->price_snapshot['agent']['percent'] ?? null)
            <tr class="border-t border-line align-top {{ $cancelled ? 'text-muted' : '' }}">
              <td class="p-3"><a href="{{ route('admin.bookings.show', $b) }}" class="font-bold text-ocean-700 hover:underline tabular">{{ $b->reference }}</a>
                <p class="text-xs text-muted">{{ Format::date($b->confirmed_at ?? $b->created_at, 'j M Y') }}@if ($b->agentTier) · {{ $b->agentTier->name }}@endif</p></td>
              <td class="p-3">{{ $b->sailing->cruise->name }}<p class="text-xs text-muted">{{ Format::date($b->sailing->departure_date) }} · {{ $b->cabinSummary() }}</p></td>
              <td class="p-3"><div class="flex flex-wrap gap-1">@include('admin.partials.badge', ['status' => $b->status])@include('admin.partials.badge', ['status' => $b->payment_status])</div></td>
              <td class="p-3 text-end tabular">{{ Format::money($b->fare_total + $b->agent_discount_total, 2) }}</td>
              <td class="p-3 text-end tabular font-semibold {{ $cancelled ? '' : 'text-[#12805C]' }}">{{ Format::money($b->agent_discount_total, 2) }}
                <p class="text-xs font-normal">{{ $percent !== null ? rtrim(rtrim(number_format($percent, 2), '0'), '.').'%' : ((float) $b->agent_discount_total > 0 ? 'Varies by cabin' : '0%') }}</p></td>
              @if ($hasPromo)<td class="p-3 text-end tabular">{{ (float) $b->discount_total > 0 ? '− '.Format::money($b->discount_total, 2) : '—' }}</td>@endif
              <td class="p-3 text-end tabular font-bold">{{ Format::money($b->grand_total, 2) }}</td>
              <td class="p-3 text-end tabular">{{ Format::money($b->amount_paid, 2) }}</td>
              <td class="p-3 text-end tabular font-bold">{{ $cancelled ? '—' : Format::money($b->amountDue(), 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="{{ $hasPromo ? 9 : 8 }}" class="p-8 text-center text-muted">No bookings yet.</td></tr>
          @endforelse
        </tbody>
        @if ($bookings->isNotEmpty())
          <tfoot class="bg-page font-bold">
            <tr class="border-t-2 border-line">
              <td class="p-3" colspan="3">Total <span class="font-normal text-xs text-muted">({{ $totals['count'] }} {{ Str::plural('booking', $totals['count']) }}@if ($totals['cancelled']), cancelled not counted @endif)</span></td>
              <td class="p-3 text-end tabular">{{ Format::money($totals['original'], 2) }}</td>
              <td class="p-3 text-end tabular text-[#12805C]">{{ Format::money($totals['commission'], 2) }}</td>
              @if ($hasPromo)<td class="p-3 text-end tabular">{{ $totals['promo'] > 0 ? '− '.Format::money($totals['promo'], 2) : '—' }}</td>@endif
              <td class="p-3 text-end tabular">{{ Format::money($totals['net'], 2) }}</td>
              <td class="p-3 text-end tabular">{{ Format::money($totals['paid'], 2) }}</td>
              <td class="p-3 text-end tabular">{{ Format::money($totals['due'], 2) }}</td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
    <p class="text-xs text-muted mt-2">Original price − commission{{ $hasPromo ? ' − promo' : '' }} = net total, in US dollars. Commission is the agent rate at booking time (Agents → Agent rates); net total − paid = due.</p>
  </section>
@endsection
