@use('App\Support\Format')
@use('App\Reports\SalesSummaryReport')
{{-- Report 13: Seasonality & lead time (App\Reports\SeasonalityReport). Inside admin.reports.show. --}}
@php
  $c = $current;
  $p = $previous;
  $chg = fn (string $k) => $p && $c[$k] !== null && $p[$k] !== null ? SalesSummaryReport::change($c[$k], $p[$k]) : null;
  $days = fn (?float $v) => $v === null ? '—' : number_format($v).' '.(round($v) == 1 ? 'day' : 'days');
  $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 0).'%';
  $th = 'font-semibold p-3 whitespace-nowrap';
  $hasSales = $c['bookings'] > 0;
  // Chart data: band labels without the days in brackets; months keyed Y-m so both series share one axis
  $chartBands = $bands->map(fn ($r) => ['label' => trim(explode('(', $r['name'])[0]), 'n' => $r['bookings']])->values();
  $chartMonths = fn ($rows) => $rows->map(fn ($r) => ['k' => $r['month']->format('Y-m'), 'label' => $r['name'], 'n' => $r['bookings']])->values();
  $chartSold = $chartMonths($soldByMonth);
  $chartSailing = $chartMonths($sailingByMonth);
@endphp

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 mt-6" data-print-cols style="--cols: 3">
  @include('admin.reports.partials.kpi', ['icon' => 'clock', 'label' => 'Average days before departure', 'value' => $days($c['average_lead']), 'change' => $chg('average_lead'), 'previous' => $p ? $days($p['average_lead']) : null,
    'hint' => 'Half of the bookings were made '.$days($c['median_lead']).' or less before (median)'])
  @include('admin.reports.partials.kpi', ['icon' => 'alert', 'label' => 'Last minute (30 days or less)', 'value' => $pct($c['last_minute']), 'change' => null, 'previous' => $p ? $pct($p['last_minute']) : null,
    'hint' => 'Of the bookings sold in this period'])
  @include('admin.reports.partials.kpi', ['icon' => 'calendar', 'label' => 'Early (more than 90 days)', 'value' => $pct($c['early']), 'change' => null, 'previous' => $p ? $pct($p['early']) : null,
    'hint' => 'Of the bookings sold in this period'])
  @include('admin.reports.partials.kpi', ['icon' => 'ticket', 'label' => 'Bookings', 'value' => number_format($c['bookings']), 'change' => $chg('bookings'), 'previous' => $p ? number_format($p['bookings']) : null,
    'hint' => number_format($c['guests']).' guests · cancelled not counted'])
  @include('admin.reports.partials.kpi', ['icon' => 'chart', 'label' => 'Net revenue', 'value' => Format::money($c['revenue']), 'change' => $chg('revenue'), 'previous' => $p ? Format::money($p['revenue']) : null])
  @include('admin.reports.partials.kpi', ['icon' => 'tag', 'label' => 'Average booking value', 'value' => $c['average_value'] === null ? '—' : Format::money($c['average_value']), 'change' => $chg('average_value'), 'previous' => $p && $p['average_value'] !== null ? Format::money($p['average_value']) : null])
</div>

{{-- Lead time --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">How far ahead they book</h2>
    <p class="text-xs text-muted mt-0.5">Days from the sale to the departure, for the bookings sold in this period.</p>
  </div>
  @if ($hasSales)
    <div class="relative h-56 px-5"><canvas id="lead-chart" role="img" aria-label="Bookings by days before departure"></canvas></div>
  @endif
  <div class="overflow-x-auto report-table-wrap mt-3">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}">Booked before departure</th><th class="text-end {{ $th }}">Bookings</th><th class="text-end {{ $th }}">Share</th><th class="text-end {{ $th }}">Guests</th><th class="text-end {{ $th }}">Net revenue</th><th class="text-end {{ $th }}">Average booking</th></tr>
      </thead>
      <tbody>
        @foreach ($bands as $r)
          <tr class="border-t border-line {{ $r['bookings'] ? '' : 'text-muted' }}">
            <td class="p-3 font-semibold">{{ $r['name'] }}</td>
            <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
            <td class="p-3 text-end">{{ $pct($r['share']) }}</td>
            <td class="p-3 text-end">{{ number_format($r['guests']) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['revenue']) }}</td>
            <td class="p-3 text-end">{{ $r['average_value'] === null ? '—' : Format::money($r['average_value']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

{{-- Channels and cruises --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Lead time by channel and cruise</h2>
    <p class="text-xs text-muted mt-0.5">The median is the middle booking: half booked earlier, half later.</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}"></th><th class="text-end {{ $th }}">Bookings</th><th class="text-end {{ $th }}">Average</th><th class="text-end {{ $th }}">Median</th><th class="text-end {{ $th }}">30 days or less</th><th class="text-end {{ $th }}">More than 90 days</th><th class="text-end {{ $th }}">Average booking</th></tr>
      </thead>
      <tbody>
        @foreach ([['By channel', $channels], ['By cruise', $cruises]] as [$heading, $rows])
          <tr class="border-t border-line bg-page"><td colspan="7" class="p-2 px-3 text-xs font-semibold uppercase tracking-wide text-muted">{{ $heading }}</td></tr>
          @forelse ($rows as $r)
            <tr class="border-t border-line {{ $r['bookings'] ? '' : 'text-muted' }}">
              <td class="p-3 font-semibold">{{ $r['name'] }}</td>
              <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
              <td class="p-3 text-end">{{ $days($r['average_lead']) }}</td>
              <td class="p-3 text-end">{{ $days($r['median_lead']) }}</td>
              <td class="p-3 text-end">{{ $pct($r['last_minute']) }}</td>
              <td class="p-3 text-end">{{ $pct($r['early']) }}</td>
              <td class="p-3 text-end">{{ $r['average_value'] === null ? '—' : Format::money($r['average_value']) }}</td>
            </tr>
          @empty
            <tr class="border-t border-line"><td colspan="7" class="p-4 text-center text-muted">No bookings were sold in this period.</td></tr>
          @endforelse
        @endforeach
      </tbody>
    </table>
  </div>
</section>

{{-- Months --}}
<section class="card p-5 mt-6">
  <div class="flex flex-wrap items-baseline justify-between gap-2">
    <h2 class="font-display font-semibold text-lg">Which months sell, which months sail</h2>
    <p class="text-xs text-muted">
      <span class="inline-block w-3 h-3 rounded-sm align-middle bg-ocean-500"></span> Sold in the month
      <span class="inline-block w-3 h-3 rounded-sm align-middle bg-sun-500 ms-3"></span> Sailing in the month
    </p>
  </div>
  <p class="text-xs text-muted mt-0.5">The same bookings counted twice: by the month they were sold, and by the month they sail.</p>
  @if ($hasSales)
    <div class="relative h-64 mt-4"><canvas id="months-chart" role="img" aria-label="Bookings sold and sailing by month"></canvas></div>
  @else
    <p class="p-8 text-center text-muted">No bookings were sold in this period.</p>
  @endif
</section>

<div class="grid lg:grid-cols-2 gap-6 mt-6 items-start" data-print-group>
  @foreach (['Sold in the month' => $soldByMonth, 'Sailing in the month' => $sailingByMonth] as $heading => $rows)
    <section class="card overflow-hidden">
      <div class="p-5 pb-3"><h2 class="font-display font-semibold text-lg">{{ $heading }}</h2></div>
      <table class="w-full text-sm tabular">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr><th class="text-start {{ $th }}">Month</th><th class="text-end {{ $th }}">Bookings</th><th class="text-end {{ $th }}">Guests</th><th class="text-end {{ $th }}">Net revenue</th><th class="text-end {{ $th }}">Average days</th></tr>
        </thead>
        <tbody>
          @forelse ($rows as $r)
            <tr class="border-t border-line {{ $r['bookings'] ? '' : 'text-muted' }}">
              <td class="p-3 font-semibold whitespace-nowrap">{{ $r['name'] }}</td>
              <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
              <td class="p-3 text-end">{{ number_format($r['guests']) }}</td>
              <td class="p-3 text-end">{{ Format::money($r['revenue']) }}</td>
              <td class="p-3 text-end">{{ $r['average_lead'] === null ? '—' : number_format($r['average_lead']) }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="p-6 text-center text-muted">No bookings.</td></tr>
          @endforelse
        </tbody>
      </table>
    </section>
  @endforeach
</div>

@if ($hasSales)
  @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
      (() => {
        if (!window.Chart) return;
        const axis = { x: { grid: { display: false }, ticks: { color: '#5B6B7F', maxRotation: 0, autoSkip: true } },
          y: { beginAtZero: true, grid: { color: '#E2EAF2' }, ticks: { color: '#5B6B7F', precision: 0 } } };
        const opts = { maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: axis };

        const bands = @json($chartBands);
        new Chart(document.getElementById('lead-chart'), {
          type: 'bar',
          data: { labels: bands.map((b) => b.label), datasets: [{ data: bands.map((b) => b.n), backgroundColor: '#1B72BF', borderRadius: 4, maxBarThickness: 56 }] },
          options: { ...opts, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `${ctx.raw} bookings` } } } },
        });

        // One axis of months covering both series
        const sold = @json($chartSold);
        const sailing = @json($chartSailing);
        const months = [...new Map([...sold, ...sailing].sort((a, b) => a.k.localeCompare(b.k)).map((m) => [m.k, m.label])).entries()];
        const pick = (list) => months.map(([k]) => (list.find((m) => m.k === k) || { n: 0 }).n);
        new Chart(document.getElementById('months-chart'), {
          type: 'bar',
          data: { labels: months.map(([, l]) => l), datasets: [
            { label: 'Sold', data: pick(sold), backgroundColor: '#1B72BF', borderRadius: 3, maxBarThickness: 28 },
            { label: 'Sailing', data: pick(sailing), backgroundColor: '#F7941D', borderRadius: 3, maxBarThickness: 28 },
          ] },
          options: { ...opts, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false } } },
        });
      })();
    </script>
  @endpush
@endif
