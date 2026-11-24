@use('App\Support\Format')
@use('App\Reports\SalesSummaryReport')
{{-- Report 1: Sales summary (App\Reports\SalesSummaryReport). Inside admin.reports.show. --}}
@php
  $c = $current;
  $p = $previous;
  $chg = fn (string $k) => $p ? SalesSummaryReport::change($c[$k], $p[$k]) : null;
  $prevMoney = fn (string $k) => $p ? Format::money($p[$k]) : null;
  $prevNum = fn (string $k) => $p ? number_format($p[$k]) : null;
  $bridgeGap = round($c['revenue'] - ($c['public_fare'] - $c['agent_discount'] + $c['fees'] - $c['promo'] - $c['offers']), 2);
  $chRevenue = max(1, $c['revenue']);
@endphp

{{-- Headline figures --}}
<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 mt-6" data-print-cols style="--cols: 3">
  @include('admin.reports.partials.kpi', ['icon' => 'ticket', 'label' => 'Bookings', 'value' => number_format($c['bookings']), 'change' => $chg('bookings'), 'previous' => $prevNum('bookings'),
    'hint' => $c['cancelled'] ? $c['cancelled'].' cancelled ('.Format::money($c['cancelled_value']).') not counted' : null])
  @include('admin.reports.partials.kpi', ['icon' => 'users', 'label' => 'Guests', 'value' => number_format($c['guests']), 'change' => $chg('guests'), 'previous' => $prevNum('guests'),
    'hint' => $c['guests'] ? Format::guests($c['adults'], $c['children'], $c['infants']) : null])
  @include('admin.reports.partials.kpi', ['icon' => 'chart', 'label' => 'Net revenue', 'value' => Format::money($c['revenue']), 'change' => $chg('revenue'), 'previous' => $prevMoney('revenue')])
  @include('admin.reports.partials.kpi', ['icon' => 'tag', 'label' => 'Average booking value', 'value' => Format::money($c['average']), 'change' => $chg('average'), 'previous' => $prevMoney('average')])
  @include('admin.reports.partials.kpi', ['icon' => 'check', 'label' => 'Paid on these bookings', 'value' => Format::money($c['collected']), 'change' => $chg('collected'), 'previous' => $prevMoney('collected'),
    'hint' => $c['collection_rate'] !== null ? number_format($c['collection_rate'], 0).'% of the revenue collected' : null])
  @include('admin.reports.partials.kpi', ['icon' => 'alert', 'label' => 'Still to collect', 'value' => Format::money($c['outstanding']), 'change' => $chg('outstanding'), 'previous' => $prevMoney('outstanding'), 'invert' => true,
    'hint' => 'On the bookings sold in this period'])
</div>

{{-- Trend --}}
<section class="card p-5 mt-6">
  <div class="flex flex-wrap items-baseline justify-between gap-2">
    <h2 class="font-display font-semibold text-lg">Revenue by {{ $trend['unit'] }}</h2>
    <p class="text-xs text-muted">
      <span class="inline-block w-3 h-3 rounded-sm align-middle bg-ocean-500"></span> This period
      @if ($trend['previous'])<span class="inline-block w-4 border-t-2 border-dashed border-sun-500 align-middle ms-3"></span> Comparison period @endif
    </p>
  </div>
  @if ($c['bookings'] || ($p['bookings'] ?? 0))
    <div class="relative h-64 mt-4"><canvas id="sales-trend" aria-label="Revenue by {{ $trend['unit'] }}" role="img"></canvas></div>
  @else
    <p class="p-8 text-center text-muted">No bookings were sold in this period.</p>
  @endif
</section>

<div class="grid lg:grid-cols-2 gap-6 mt-6 items-start" data-print-group>
  {{-- Revenue bridge --}}
  <section class="card p-5">
    <h2 class="font-display font-semibold text-lg">Where the revenue came from</h2>
    <dl class="mt-3 text-sm tabular divide-y divide-line">
      @foreach ([
          ['Cabin fares at public prices', $c['public_fare'], ''],
          ['Agent discount (commission)', -$c['agent_discount'], 'Agents buy at the public price less their rate'],
          ['Fees and port taxes', $c['fees'], ''],
          ['Promo codes', -$c['promo'], ''],
          ['Offers', -$c['offers'], 'Time-limited offers'],
      ] as [$label, $amount, $title])
        <div class="flex justify-between gap-4 py-2" @if ($title) title="{{ $title }}" @endif>
          <dt class="{{ $amount < 0 ? 'text-muted' : '' }}">{{ $amount < 0 ? '−' : '+' }} {{ $label }}</dt>
          <dd class="{{ $amount < 0 ? 'text-[#B42318]' : '' }}">{{ Format::money($amount) }}</dd>
        </div>
      @endforeach
      @if (abs($bridgeGap) >= 0.01)
        <div class="flex justify-between gap-4 py-2"><dt class="text-muted">Other adjustments</dt><dd>{{ Format::money($bridgeGap, 2) }}</dd></div>
      @endif
      <div class="flex justify-between gap-4 pt-3 font-bold text-base"><dt>Net revenue</dt><dd>{{ Format::money($c['revenue']) }}</dd></div>
    </dl>
  </section>

  {{-- Channel and currency --}}
  <section class="card p-5">
    <h2 class="font-display font-semibold text-lg">By channel</h2>
    <div class="mt-3 space-y-4 text-sm">
      @foreach (['b2c' => ['Website (B2C)', 'bg-ocean-500'], 'agent' => ['Agents (B2B)', 'bg-lagoon-500']] as $key => [$label, $bar])
        @php($row = $channels[$key] ?? null)
        @php($share = $row ? $row->revenue / $chRevenue * 100 : 0)
        <div>
          <div class="flex justify-between gap-4 tabular">
            <span>{{ $label }} <span class="text-muted">· {{ number_format($row->bookings ?? 0) }} {{ ($row->bookings ?? 0) == 1 ? 'booking' : 'bookings' }}</span></span>
            <span class="font-semibold">{{ Format::money($row->revenue ?? 0) }} <span class="text-muted font-normal">· {{ number_format($share, 0) }}%</span></span>
          </div>
          <div class="h-2 rounded-full bg-page mt-1.5 overflow-hidden"><div class="h-2 rounded-full {{ $bar }}" style="width: {{ round($share, 1) }}%"></div></div>
        </div>
      @endforeach
    </div>

    <h2 class="font-display font-semibold text-lg mt-6">By the currency the guest saw</h2>
    <p class="text-xs text-muted">Amounts are kept in USD; the other currency is at each booking's own rate on the booking day.</p>
    <dl class="mt-2 text-sm tabular divide-y divide-line">
      @forelse ($currencies as $cur)
        <div class="flex justify-between gap-4 py-2">
          <dt>{{ $cur->currency }} <span class="text-muted">· {{ number_format($cur->bookings) }} {{ $cur->bookings == 1 ? 'booking' : 'bookings' }}</span></dt>
          <dd>{{ Format::money($cur->revenue) }}@if ($cur->currency !== config('cruise.currency'))<span class="text-[#5B2A86] font-semibold ms-2">{{ $cur->currency }} {{ number_format($cur->local) }}</span>@endif</dd>
        </div>
      @empty
        <p class="py-2 text-muted">No bookings.</p>
      @endforelse
    </dl>
  </section>
</div>

{{-- Bookings sold in the period --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 flex flex-wrap items-baseline justify-between gap-2">
    <h2 class="font-display font-semibold text-lg">Bookings sold in this period</h2>
    <p class="text-xs text-muted">{{ number_format($bookings->total()) }} including cancelled · newest first</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start font-semibold p-3">Reference</th>
          <th class="text-start font-semibold p-3">Sold</th>
          <th class="text-start font-semibold p-3">Customer</th>
          <th class="text-start font-semibold p-3">Sailing</th>
          <th class="text-start font-semibold p-3">Guests</th>
          <th class="text-end font-semibold p-3">Total</th>
          <th class="text-end font-semibold p-3">Paid</th>
          <th class="text-end font-semibold p-3">Due</th>
          <th class="text-start font-semibold p-3">Status</th>
          <th class="text-start font-semibold p-3">Assigned to</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($bookings as $b)
          @php($cancelled = $b->status === \App\Enums\BookingStatus::Cancelled)
          <tr class="border-t border-line hover:bg-page {{ $cancelled ? 'text-muted' : '' }}">
            <td class="p-3 whitespace-nowrap">
              <a href="{{ route('admin.bookings.show', $b) }}" class="font-bold text-ocean-700 hover:underline tabular">{{ $b->reference }}</a>
              <p class="text-xs">{{ $b->isAgentBooking() ? 'Agent · '.($b->agent?->company_name ?: ($b->agent?->name ?? 'deleted account')) : 'Website' }}</p>
            </td>
            <td class="p-3 whitespace-nowrap">{{ Format::date($b->confirmed_at, 'j M Y') }}</td>
            <td class="p-3">{{ $b->user?->name ?? '—' }}<p class="text-xs text-muted">{{ $b->contact_email }}</p></td>
            <td class="p-3">{{ $b->sailing?->cruise?->name }}<p class="text-xs text-muted">{{ Format::date($b->sailing?->departure_date) }} · {{ $b->cabinSummary() }}</p></td>
            <td class="p-3 whitespace-nowrap">{{ Format::guests($b->adults, $b->children, $b->infants) }}</td>
            <td class="p-3 text-end tabular {{ $cancelled ? 'line-through' : '' }}">{{ Format::money($b->grand_total) }}</td>
            <td class="p-3 text-end tabular">{{ Format::money($b->amount_paid) }}</td>
            <td class="p-3 text-end tabular font-bold">{{ $cancelled ? '—' : Format::money($b->amountDue()) }}</td>
            <td class="p-3"><div class="flex flex-wrap gap-1">@include('admin.partials.badge', ['status' => $b->status])@include('admin.partials.badge', ['status' => $b->payment_status])</div></td>
            <td class="p-3 whitespace-nowrap">{{ $b->seller?->name ?? 'Unassigned' }}</td>
          </tr>
        @empty
          <tr><td colspan="10" class="p-8 text-center text-muted">No bookings were sold in this period.</td></tr>
        @endforelse
      </tbody>
      @if ($c['bookings'])
        <tfoot class="bg-page font-bold tabular">
          <tr class="border-t-2 border-line">
            <td class="p-3" colspan="4">Total, {{ number_format($c['bookings']) }} {{ $c['bookings'] == 1 ? 'booking' : 'bookings' }} <span class="font-normal text-muted">(cancelled not included)</span></td>
            <td class="p-3 whitespace-nowrap">{{ number_format($c['guests']) }} guests</td>
            <td class="p-3 text-end">{{ Format::money($c['revenue']) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['collected']) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['outstanding']) }}</td>
            <td class="p-3" colspan="2"></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</section>
<div class="mt-5 no-print">{{ $bookings->links() }}</div>

@if ($c['bookings'] || ($p['bookings'] ?? 0))
  @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
      (() => {
        const el = document.getElementById('sales-trend');
        if (!el || !window.Chart) return;
        const t = @json($trend);
        const usd = v => '$' + Math.round(v).toLocaleString('en-US');
        const datasets = [{
          type: 'bar', label: 'This period', data: t.revenue, backgroundColor: '#1B72BF', borderRadius: 4, maxBarThickness: 48, order: 2,
        }];
        if (t.previous) datasets.push({
          type: 'line', label: 'Comparison period', data: t.previous.revenue, borderColor: '#F7941D', borderDash: [6, 4],
          borderWidth: 2, pointRadius: 2, pointBackgroundColor: '#F7941D', tension: 0.25, spanGaps: true, order: 1,
        });
        new Chart(el, {
          data: { labels: t.labels, datasets },
          options: {
            maintainAspectRatio: false, animation: false, interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: { display: false },
              tooltip: { callbacks: {
                title: items => items.length ? (t.unit === 'week' ? 'Week of ' : '') + t.labels[items[0].dataIndex] : '',
                label: ctx => ctx.datasetIndex === 0
                  ? `This period: ${usd(ctx.raw)} · ${t.bookings[ctx.dataIndex]} bookings`
                  : `Comparison (${t.previous.labels[ctx.dataIndex]}): ${usd(ctx.raw ?? 0)}`,
              } },
            },
            scales: {
              x: { grid: { display: false }, ticks: { color: '#5B6B7F', maxRotation: 0, autoSkip: true } },
              y: { beginAtZero: true, grid: { color: '#E2EAF2' }, ticks: { color: '#5B6B7F', callback: usd } },
            },
          },
        });
      })();
    </script>
  @endpush
@endif
