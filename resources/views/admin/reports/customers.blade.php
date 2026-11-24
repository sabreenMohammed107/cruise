@use('App\Support\Format')
@use('App\Reports\SalesSummaryReport')
{{-- Report 11: Customers (App\Reports\CustomersReport). Inside admin.reports.show. --}}
@php
  $c = $current;
  $p = $previous;
  $chg = fn (string $k) => $p ? SalesSummaryReport::change($c[$k], $p[$k]) : null;
  $prev = fn (string $k, bool $money = false) => $p ? ($money ? Format::money($p[$k]) : number_format($p[$k])) : null;
  $pct = fn (float $v) => number_format($v, 0).'%';
  $th = 'font-semibold p-3 whitespace-nowrap';
@endphp

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 mt-6" data-print-cols style="--cols: 3">
  @include('admin.reports.partials.kpi', ['icon' => 'users', 'label' => 'Customers who bought', 'value' => number_format($c['customers']), 'change' => $chg('customers'), 'previous' => $prev('customers'),
    'hint' => number_format($c['new']).' new · '.number_format($c['returning']).' returning'])
  @include('admin.reports.partials.kpi', ['icon' => 'star', 'label' => 'New customers', 'value' => number_format($c['new']), 'change' => $chg('new'), 'previous' => $prev('new'),
    'hint' => 'First booking ever · '.Format::money($c['new_revenue']).' of the revenue'])
  @include('admin.reports.partials.kpi', ['icon' => 'heart', 'label' => 'Booked more than once', 'value' => number_format($c['repeat']), 'change' => $chg('repeat'), 'previous' => $prev('repeat'),
    'hint' => $c['customers'] ? $pct($c['repeat'] / $c['customers'] * 100).' of these customers, all time' : null])
  @include('admin.reports.partials.kpi', ['icon' => 'chart', 'label' => 'Revenue per customer', 'value' => Format::money($c['per_customer']), 'change' => $chg('per_customer'), 'previous' => $prev('per_customer', true),
    'hint' => Format::money($c['revenue']).' from '.number_format($c['bookings']).' '.($c['bookings'] == 1 ? 'booking' : 'bookings')])
  @include('admin.reports.partials.kpi', ['icon' => 'users', 'label' => 'Guests', 'value' => number_format($c['guests']), 'change' => $chg('guests'), 'previous' => $prev('guests'),
    'hint' => $c['guests'] ? Format::guests($c['adults'], $c['children'], $c['infants']).' · '.number_format($c['guests_per_booking'], 1).' per booking' : null])
  @include('admin.reports.partials.kpi', ['icon' => 'user', 'label' => 'Accounts opened', 'value' => number_format($c['accounts']), 'change' => $chg('accounts'), 'previous' => $prev('accounts'),
    'hint' => 'Website sign-ups in the period'])
</div>

<div class="grid lg:grid-cols-2 gap-6 mt-6 items-start" data-print-group>
  {{-- Nationalities --}}
  <section class="card overflow-hidden">
    <div class="p-5 pb-3">
      <h2 class="font-display font-semibold text-lg">Nationalities</h2>
      <p class="text-xs text-muted mt-0.5">Every traveller on the bookings (a family of four counts four times).</p>
    </div>
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}">Nationality</th><th class="text-end {{ $th }}">Travellers</th><th class="text-end {{ $th }}">Bookings</th><th class="text-start {{ $th }} w-2/5">Share</th></tr>
      </thead>
      <tbody>
        @forelse ($nationalities as $r)
          <tr class="border-t border-line">
            <td class="p-3 font-semibold">{{ $r['name'] }}</td>
            <td class="p-3 text-end">{{ number_format($r['travellers']) }}</td>
            <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
            <td class="p-3">
              <div class="flex items-center gap-2"><div class="h-2 flex-1 rounded-full bg-page overflow-hidden"><div class="h-2 rounded-full bg-ocean-500" style="width: {{ round($r['share'], 1) }}%"></div></div><span class="text-xs w-9 text-end">{{ $pct($r['share']) }}</span></div>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="p-6 text-center text-muted">No travellers in this period.</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>

  {{-- Parties --}}
  <section class="card overflow-hidden">
    <div class="p-5 pb-3">
      <h2 class="font-display font-semibold text-lg">Who travels together</h2>
      <p class="text-xs text-muted mt-0.5">Bookings by party: any child or infant makes it a family.</p>
    </div>
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}">Party</th><th class="text-end {{ $th }}">Bookings</th><th class="text-end {{ $th }}">Guests</th><th class="text-end {{ $th }}">Average booking</th><th class="text-end {{ $th }}">Share</th></tr>
      </thead>
      <tbody>
        @foreach ($parties as $r)
          <tr class="border-t border-line {{ $r['bookings'] ? '' : 'text-muted' }}">
            <td class="p-3 font-semibold">{{ $r['name'] }}</td>
            <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
            <td class="p-3 text-end">{{ number_format($r['guests']) }}</td>
            <td class="p-3 text-end">{{ $r['bookings'] ? Format::money($r['average']) : '—' }}</td>
            <td class="p-3 text-end">{{ $pct($r['share']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <div class="p-5 pt-3 border-t border-line text-sm">
      <p class="text-xs text-muted mb-2">Guests</p>
      @php($g = max(1, $c['guests']))
      <div class="flex h-3 rounded-full overflow-hidden bg-page">
        <div class="bg-ocean-500" style="width: {{ round($c['adults'] / $g * 100, 1) }}%"></div>
        <div class="bg-lagoon-500" style="width: {{ round($c['children'] / $g * 100, 1) }}%"></div>
        <div class="bg-sun-500" style="width: {{ round($c['infants'] / $g * 100, 1) }}%"></div>
      </div>
      <p class="mt-2 text-xs tabular flex flex-wrap gap-x-4">
        <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-ocean-500 align-middle"></span> Adults {{ number_format($c['adults']) }}</span>
        <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-lagoon-500 align-middle"></span> Children {{ number_format($c['children']) }}</span>
        <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-sun-500 align-middle"></span> Infants {{ number_format($c['infants']) }}</span>
      </p>
    </div>
  </section>

  {{-- Currency and language --}}
  @foreach (['Currency the guest booked in' => [$currencies, 'Amounts are kept in USD.'], 'Language of the booking' => [$languages, 'The website language when they booked.']] as $heading => [$rows, $note])
    <section class="card p-5">
      <h2 class="font-display font-semibold text-lg">{{ $heading }}</h2>
      <p class="text-xs text-muted mt-0.5">{{ $note }}</p>
      <div class="mt-3 space-y-3 text-sm tabular">
        @forelse ($rows as $r)
          <div>
            <div class="flex justify-between gap-4">
              <span class="font-semibold">{{ $r['name'] }} <span class="text-muted font-normal">· {{ number_format($r['bookings']) }} {{ $r['bookings'] == 1 ? 'booking' : 'bookings' }}</span></span>
              <span>{{ Format::money($r['revenue']) }} <span class="text-muted">· {{ $pct($r['share']) }}</span></span>
            </div>
            <div class="h-2 rounded-full bg-page mt-1.5 overflow-hidden"><div class="h-2 rounded-full bg-ocean-500" style="width: {{ round($r['share'], 1) }}%"></div></div>
          </div>
        @empty
          <p class="text-muted">No bookings.</p>
        @endforelse
      </div>
    </section>
  @endforeach
</div>

{{-- Top customers --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Customers who spent the most</h2>
    <p class="text-xs text-muted mt-0.5">The top 20 of this period; the Excel file lists every customer. "All time" counts every confirmed booking they made, at any time.</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start {{ $th }}">#</th>
          <th class="text-start {{ $th }}">Customer</th>
          <th class="text-start {{ $th }}">Nationality</th>
          <th class="text-end {{ $th }}">Bookings</th>
          <th class="text-end {{ $th }}">Guests</th>
          <th class="text-end {{ $th }}">Net revenue</th>
          <th class="text-end {{ $th }}">All time</th>
          <th class="text-start {{ $th }}">Customer since</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($top as $i => $r)
          <tr class="border-t border-line hover:bg-page">
            <td class="p-3 text-muted">{{ $i + 1 }}</td>
            <td class="p-3">
              @if ($r['user_id'] && auth()->user()->can('manage-content'))
                <a href="{{ route('admin.customers.show', $r['user_id']) }}" class="font-semibold text-ocean-700 hover:underline">{{ $r['name'] }}</a>
              @else
                <span class="font-semibold">{{ $r['name'] ?? 'Guest (no account)' }}</span>
              @endif
              <span class="tag {{ $r['new'] ? 'tag-lagoon' : 'tag-ocean' }} ms-1">{{ $r['new'] ? 'New' : 'Returning' }}</span>
              <p class="text-xs text-muted">{{ $r['email'] }}</p>
            </td>
            <td class="p-3">{{ $r['nationality'] ?? '—' }}</td>
            <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
            <td class="p-3 text-end">{{ number_format($r['guests']) }}</td>
            <td class="p-3 text-end font-semibold">{{ Format::money($r['revenue']) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['lifetime_spent']) }}<p class="text-xs text-muted">{{ $r['lifetime_bookings'] }} {{ $r['lifetime_bookings'] == 1 ? 'booking' : 'bookings' }}</p></td>
            <td class="p-3 whitespace-nowrap">{{ Format::date($r['first_booking']) }}</td>
          </tr>
        @empty
          <tr><td colspan="8" class="p-8 text-center text-muted">No bookings were sold in this period.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
