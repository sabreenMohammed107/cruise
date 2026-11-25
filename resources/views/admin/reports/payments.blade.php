@use('App\Support\Format')
@use('App\Enums\PaymentType')
@use('App\Reports\SalesSummaryReport')
@use('App\Reports\PaymentsLedgerReport')
{{-- Report 4: Payments ledger (App\Reports\PaymentsLedgerReport). Inside admin.reports.show. --}}
@php
  $c = $current;
  $p = $previous;
  $chg = fn (string $k) => $p ? SalesSummaryReport::change($c[$k], $p[$k]) : null;
  $prev = fn (string $k, bool $money = true) => $p ? ($p[$k] === null ? '—' : ($money ? Format::money($p[$k]) : number_format($p[$k]))) : null;
  $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 0).'%';
  $th = 'font-semibold p-3 whitespace-nowrap';
  $plural = fn (int $n, string $one, string $many) => number_format($n).' '.($n == 1 ? $one : $many);
@endphp

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 mt-6" data-print-cols style="--cols: 3">
  @include('admin.reports.partials.kpi', ['icon' => 'check', 'label' => 'Received', 'value' => Format::money($c['received'], 2), 'change' => $chg('received'), 'previous' => $prev('received'),
    'hint' => $plural($c['payments'], 'payment', 'payments').' on '.$plural($c['bookings'], 'booking', 'bookings')])
  @include('admin.reports.partials.kpi', ['icon' => 'arrow-left', 'label' => 'Refunded', 'value' => Format::money($c['refunded'], 2), 'change' => $chg('refunded'), 'previous' => $prev('refunded'), 'invert' => true,
    'hint' => $plural($c['refunds'], 'refund', 'refunds')])
  @include('admin.reports.partials.kpi', ['icon' => 'card', 'label' => 'Net in', 'value' => Format::money($c['net'], 2), 'change' => $chg('net'), 'previous' => $prev('net'),
    'hint' => 'Received less refunded, over '.$plural($c['days'], 'day', 'days').' with entries'])
  @include('admin.reports.partials.kpi', ['icon' => 'chart', 'label' => 'Average payment', 'value' => $c['average'] === null ? '—' : Format::money($c['average']), 'change' => null, 'previous' => $prev('average'),
    'hint' => $c['largest'] === null ? 'No payments in this period' : 'Largest: '.Format::money($c['largest'], 2)])
  @include('admin.reports.partials.kpi', ['icon' => 'image', 'label' => 'Without a receipt', 'value' => number_format($c['without_receipt']), 'change' => null, 'previous' => $prev('without_receipt', false),
    'hint' => $c['entries'] ? 'of '.$plural($c['entries'], 'entry', 'entries').' · attach them on the booking' : 'No entries in this period'])
  @include('admin.reports.partials.kpi', ['icon' => 'users', 'label' => 'Recorded by', 'value' => $plural($staff->count(), 'person', 'people'), 'change' => null, 'previous' => null,
    'hint' => $staff->isNotEmpty() ? 'Most: '.$staff->first()['name'] : 'No entries in this period'])
</div>

{{-- Money in and out over the period --}}
<section class="card mt-6">
  <div class="p-5 pb-2 flex flex-wrap items-end justify-between gap-2">
    <div>
      <h2 class="font-display font-semibold text-lg">Money in and out, per {{ $chart['unit'] }}</h2>
      <p class="text-xs text-muted mt-0.5">By the day the money was received or paid back.</p>
    </div>
    <p class="text-xs text-muted flex gap-4">
      <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-[#1B72BF]"></span> Received</span>
      <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-[#F7941D]"></span> Refunded</span>
    </p>
  </div>
  <div class="relative h-56 px-5 pb-4"><canvas id="ledger-chart" role="img" aria-label="Money received and refunded per {{ $chart['unit'] }}"></canvas></div>
</section>

<div class="grid lg:grid-cols-2 gap-6 mt-6 items-start" data-print-group>
  {{-- By method --}}
  <section class="card overflow-hidden">
    <div class="p-5 pb-3"><h2 class="font-display font-semibold text-lg">By method</h2></div>
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}">Method</th><th class="text-end {{ $th }}">Received</th><th class="text-start {{ $th }} w-1/4">Share</th><th class="text-end {{ $th }}">Refunded</th><th class="text-end {{ $th }}">Net</th></tr>
      </thead>
      <tbody>
        @foreach ($methods as $r)
          <tr class="border-t border-line {{ $r['entries'] ? '' : 'text-muted' }}">
            <td class="p-3 font-semibold whitespace-nowrap">{{ $r['name'] }}<span class="text-xs text-muted font-normal"> · {{ $r['entries'] }}</span></td>
            <td class="p-3 text-end">{{ Format::money($r['received'], 2) }}</td>
            <td class="p-3"><div class="flex items-center gap-2"><div class="h-2 flex-1 rounded-full bg-page overflow-hidden"><div class="h-2 rounded-full bg-[#1B72BF]" style="width: {{ round($r['share'], 1) }}%"></div></div><span class="text-xs w-9 text-end">{{ $pct($r['share']) }}</span></div></td>
            <td class="p-3 text-end">{{ Format::money($r['refunded'], 2) }}</td>
            <td class="p-3 text-end font-semibold">{{ Format::money($r['net'], 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>

  {{-- By staff --}}
  <section class="card overflow-hidden">
    <div class="p-5 pb-3"><h2 class="font-display font-semibold text-lg">By who recorded it</h2></div>
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}">Recorded by</th><th class="text-end {{ $th }}">Entries</th><th class="text-end {{ $th }}">Received</th><th class="text-end {{ $th }}">Refunded</th><th class="text-end {{ $th }}">No receipt</th></tr>
      </thead>
      <tbody>
        @forelse ($staff as $r)
          <tr class="border-t border-line">
            <td class="p-3 font-semibold">{{ $r['name'] }}</td>
            <td class="p-3 text-end">{{ number_format($r['entries']) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['received'], 2) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['refunded'], 2) }}</td>
            <td class="p-3 text-end {{ $r['without_receipt'] ? 'text-[#B42318] font-semibold' : '' }}">{{ number_format($r['without_receipt']) }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="p-6 text-center text-muted">No payments recorded in this period.</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>
</div>

{{-- By day --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Day by day</h2>
    <p class="text-xs text-muted mt-0.5">Each day with an entry, and the running net since the start of the period: to tick off against the bank statement.</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}">Day</th><th class="text-end {{ $th }}">Entries</th><th class="text-end {{ $th }}">Received</th><th class="text-end {{ $th }}">Refunded</th><th class="text-end {{ $th }}">Net</th><th class="text-end {{ $th }}">Running total</th></tr>
      </thead>
      <tbody>
        @forelse ($days as $r)
          <tr class="border-t border-line">
            <td class="p-3 font-semibold whitespace-nowrap">{{ $r['day']->format('D j M Y') }}</td>
            <td class="p-3 text-end">{{ number_format($r['entries']) }}</td>
            <td class="p-3 text-end">{{ $r['received'] ? Format::money($r['received'], 2) : '—' }}</td>
            <td class="p-3 text-end">{{ $r['refunded'] ? Format::money($r['refunded'], 2) : '—' }}</td>
            <td class="p-3 text-end font-semibold">{{ Format::money($r['net'], 2) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['running'], 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-8 text-center text-muted">No money was received or refunded in this period.</td></tr>
        @endforelse
      </tbody>
      @if ($days->isNotEmpty())
        <tfoot class="bg-page font-bold">
          <tr class="border-t-2 border-line">
            <td class="p-3">Total, {{ $plural($c['days'], 'day', 'days') }}</td>
            <td class="p-3 text-end">{{ number_format($c['entries']) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['received'], 2) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['refunded'], 2) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['net'], 2) }}</td>
            <td class="p-3"></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</section>

{{-- Every entry --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Every payment and refund</h2>
    <p class="text-xs text-muted mt-0.5">In the order received. Refunds are shown as minus amounts.</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start {{ $th }}">Received</th>
          <th class="text-start {{ $th }}">Booking</th>
          <th class="text-start {{ $th }}">Customer</th>
          <th class="text-start {{ $th }}">Method</th>
          <th class="text-end {{ $th }}">Amount</th>
          <th class="text-start {{ $th }}">Recorded by</th>
          <th class="text-start {{ $th }}">Receipts</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($entries as $e)
          @php($b = $e->booking)
          @php($refund = $e->type === PaymentType::Refund)
          <tr class="border-t border-line hover:bg-page">
            <td class="p-3 whitespace-nowrap">{{ Format::date($e->received_at) }}<p class="text-xs text-muted">{{ $e->received_at->format('H:i') }}</p></td>
            <td class="p-3 whitespace-nowrap">
              @if ($b)<a href="{{ route('admin.bookings.show', $b) }}" class="font-bold text-ocean-700 hover:underline">{{ $b->reference }}</a>
                <p class="text-xs text-muted">{{ $b->sailing?->cruise?->name }}</p>@endif
            </td>
            <td class="p-3">{{ $b?->user?->name ?? $b?->contact_email }}</td>
            <td class="p-3">
              @if ($refund)<span class="tag tag-sun">Refund</span>@endif {{ PaymentsLedgerReport::method($e->method) }}
              @if ($e->reference || $e->notes)<p class="text-xs text-muted">{{ trim($e->reference.' '.($e->reference && $e->notes ? '· ' : '').$e->notes) }}</p>@endif
            </td>
            <td class="p-3 text-end font-semibold whitespace-nowrap {{ $refund ? 'text-[#B42318]' : '' }}">{{ $refund ? '−' : '' }}{{ Format::money($e->amount, 2) }}</td>
            <td class="p-3">{{ $e->recordedBy?->name ?? '—' }}</td>
            <td class="p-3">
              @forelse ($e->receipts as $r)
                @if ($b)<a href="{{ route('admin.bookings.payments.receipt', [$b, $e, $r]) }}" target="_blank" rel="noopener" class="block text-xs text-ocean-700 hover:underline truncate max-w-[12rem]">{{ $r->name }}</a>@endif
              @empty
                <span class="text-xs font-semibold text-[#B42318]">None</span>
              @endforelse
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="p-8 text-center text-muted">No money was received or refunded in this period.</td></tr>
        @endforelse
      </tbody>
      @if ($entries->isNotEmpty())
        <tfoot class="bg-page font-bold">
          <tr class="border-t-2 border-line">
            <td class="p-3" colspan="4">Net, {{ $plural($c['entries'], 'entry', 'entries') }}</td>
            <td class="p-3 text-end whitespace-nowrap">{{ Format::money($c['net'], 2) }}</td>
            <td class="p-3" colspan="2"></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</section>

@push('scripts')
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <script>
    (() => {
      if (!window.Chart) return;
      const buckets = @json($chart['buckets']);
      const usd = (v) => '$' + Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 });
      new Chart(document.getElementById('ledger-chart'), {
        type: 'bar',
        data: { labels: buckets.map((b) => b.label), datasets: [
          { label: 'Received', data: buckets.map((b) => b.in), backgroundColor: '#1B72BF', borderRadius: 3, maxBarThickness: 28 },
          { label: 'Refunded', data: buckets.map((b) => b.out), backgroundColor: '#F7941D', borderRadius: 3, maxBarThickness: 28 },
        ] },
        options: {
          maintainAspectRatio: false, animation: false, interaction: { mode: 'index', intersect: false },
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${usd(ctx.raw)}` } } },
          scales: { x: { grid: { display: false }, ticks: { color: '#5B6B7F', maxRotation: 0, autoSkip: true } },
            y: { beginAtZero: true, grid: { color: '#E2EAF2' }, ticks: { color: '#5B6B7F', callback: usd } } },
        },
      });
    })();
  </script>
@endpush
