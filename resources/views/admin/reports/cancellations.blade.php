@use('App\Support\Format')
@use('App\Reports\SalesSummaryReport')
{{-- Report 12: Cancellations & refunds (App\Reports\CancellationsReport). Inside admin.reports.show. --}}
@php
  $c = $current;
  $p = $previous;
  $chg = fn (string $k) => $p ? SalesSummaryReport::change($c[$k], $p[$k]) : null;
  $prev = fn (string $k, bool $money = false) => $p ? ($money ? Format::money($p[$k]) : number_format($p[$k])) : null;
  $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 0).'%';
  $th = 'font-semibold p-3 whitespace-nowrap';
@endphp

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 mt-6" data-print-cols style="--cols: 3">
  @include('admin.reports.partials.kpi', ['icon' => 'alert', 'label' => 'Bookings cancelled', 'value' => number_format($c['cancelled']), 'change' => $chg('cancelled'), 'previous' => $prev('cancelled'), 'invert' => true,
    'hint' => number_format($c['guests']).' '.($c['guests'] == 1 ? 'guest' : 'guests').' · by the day cancelled'])
  @include('admin.reports.partials.kpi', ['icon' => 'ticket', 'label' => 'Value cancelled', 'value' => Format::money($c['value']), 'change' => $chg('value'), 'previous' => $prev('value', true), 'invert' => true,
    'hint' => 'What these bookings were worth'])
  @include('admin.reports.partials.kpi', ['icon' => 'chart', 'label' => 'Cancellation rate', 'value' => $pct($c['rate']), 'change' => null, 'previous' => $p ? $pct($p['rate']) : null,
    'hint' => number_format($c['sold_cancelled']).' of the '.number_format($c['sold']).' bookings sold in this period are cancelled now'])
  @include('admin.reports.partials.kpi', ['icon' => 'check', 'label' => 'Paid on them', 'value' => Format::money($c['paid']), 'change' => $chg('paid'), 'previous' => $prev('paid', true)])
  @include('admin.reports.partials.kpi', ['icon' => 'card', 'label' => 'Refunded on them', 'value' => Format::money($c['refunded']), 'change' => $chg('refunded'), 'previous' => $prev('refunded', true), 'invert' => true,
    'hint' => 'Kept (paid less refunded): '.Format::money($c['kept'])])
  @include('admin.reports.partials.kpi', ['icon' => 'clock', 'label' => 'Refunds paid out', 'value' => Format::money($c['refunds_paid']), 'change' => $chg('refunds_paid'), 'previous' => $prev('refunds_paid', true), 'invert' => true,
    'hint' => number_format($c['refunds_count']).' '.($c['refunds_count'] == 1 ? 'refund' : 'refunds').' paid in this period, on any booking'])
</div>

<div class="grid lg:grid-cols-2 gap-6 mt-6 items-start" data-print-group>
  {{-- By cruise --}}
  <section class="card overflow-hidden">
    <div class="p-5 pb-3">
      <h2 class="font-display font-semibold text-lg">By cruise</h2>
      <p class="text-xs text-muted mt-0.5">Rate: of the bookings of that cruise sold in this period, how many are cancelled now.</p>
    </div>
    <div class="overflow-x-auto report-table-wrap">
      <table class="w-full text-sm tabular">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr><th class="text-start {{ $th }}">Cruise</th><th class="text-end {{ $th }}">Cancelled</th><th class="text-end {{ $th }}">Value</th><th class="text-end {{ $th }}">Refunded</th><th class="text-end {{ $th }}">Kept</th><th class="text-end {{ $th }}">Rate</th></tr>
        </thead>
        <tbody>
          @forelse ($cruises as $r)
            <tr class="border-t border-line">
              <td class="p-3 font-semibold">{{ $r['name'] }}</td>
              <td class="p-3 text-end">{{ number_format($r['cancelled']) }}</td>
              <td class="p-3 text-end">{{ Format::money($r['value']) }}</td>
              <td class="p-3 text-end">{{ Format::money($r['refunded']) }}</td>
              <td class="p-3 text-end">{{ Format::money($r['kept']) }}</td>
              <td class="p-3 text-end" title="{{ $r['sold'] }} sold in this period">{{ $pct($r['rate']) }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="p-6 text-center text-muted">No cancellations in this period.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{-- Notice --}}
  <section class="card overflow-hidden">
    <div class="p-5 pb-3">
      <h2 class="font-display font-semibold text-lg">How long before departure</h2>
      <p class="text-xs text-muted mt-0.5">
        When the cancellations came.
        @if ($c['average_notice'] !== null) On average <b class="text-ink">{{ number_format($c['average_notice']) }} days</b> before departure. @endif
      </p>
    </div>
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}">Cancelled</th><th class="text-end {{ $th }}">Bookings</th><th class="text-start {{ $th }} w-1/3">Share</th><th class="text-end {{ $th }}">Refunded</th><th class="text-end {{ $th }}">Kept</th></tr>
      </thead>
      <tbody>
        @foreach ($notice as $r)
          <tr class="border-t border-line {{ $r['cancelled'] ? '' : 'text-muted' }}">
            <td class="p-3 font-semibold whitespace-nowrap">{{ $r['name'] }}</td>
            <td class="p-3 text-end">{{ number_format($r['cancelled']) }}</td>
            <td class="p-3"><div class="flex items-center gap-2"><div class="h-2 flex-1 rounded-full bg-page overflow-hidden"><div class="h-2 rounded-full bg-[#B42318]" style="width: {{ round($r['share'], 1) }}%"></div></div><span class="text-xs w-9 text-end">{{ $pct($r['share']) }}</span></div></td>
            <td class="p-3 text-end">{{ Format::money($r['refunded']) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['kept']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
</div>

{{-- Cancelled bookings --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Bookings cancelled in this period</h2>
    <p class="text-xs text-muted mt-0.5">Newest first. "Kept" is what was paid less what was refunded: the cancellation penalty, or a refund still to pay.</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start {{ $th }}">Reference</th>
          <th class="text-start {{ $th }}">Customer</th>
          <th class="text-start {{ $th }}">Sailing</th>
          <th class="text-start {{ $th }}">Cancelled</th>
          <th class="text-end {{ $th }}">Before departure</th>
          <th class="text-end {{ $th }}">Value</th>
          <th class="text-end {{ $th }}">Paid</th>
          <th class="text-end {{ $th }}">Refunded</th>
          <th class="text-end {{ $th }}">Kept</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($cancelled as $r)
          @php($b = $r['booking'])
          <tr class="border-t border-line hover:bg-page">
            <td class="p-3 whitespace-nowrap">
              <a href="{{ route('admin.bookings.show', $b) }}" class="font-bold text-ocean-700 hover:underline">{{ $b->reference }}</a>
              <p class="text-xs text-muted">{{ $b->isAgentBooking() ? 'Agent · '.($b->agent?->company_name ?: $b->agent?->name) : 'Website' }}</p>
            </td>
            <td class="p-3">{{ $b->user?->name ?? '—' }}<p class="text-xs text-muted">{{ $b->contact_email }}</p></td>
            <td class="p-3">{{ $r['cruise'] }}<p class="text-xs text-muted">{{ Format::date($b->sailing?->departure_date) }} · sold {{ Format::date($b->confirmed_at) }}</p></td>
            <td class="p-3 whitespace-nowrap">{{ Format::date($r['cancelled_on']) }}@if ($r['held_days'] !== null)<p class="text-xs text-muted">after {{ $r['held_days'] }} {{ $r['held_days'] == 1 ? 'day' : 'days' }}</p>@endif</td>
            <td class="p-3 text-end whitespace-nowrap">{{ $r['notice_days'] === null ? '—' : ($r['notice_days'] < 0 ? 'after departure' : $r['notice_days'].' '.($r['notice_days'] == 1 ? 'day' : 'days')) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['value']) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['paid']) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['refunded']) }}</td>
            <td class="p-3 text-end font-semibold">{{ Format::money($r['kept']) }}</td>
          </tr>
        @empty
          <tr><td colspan="9" class="p-8 text-center text-muted">No bookings were cancelled in this period.</td></tr>
        @endforelse
      </tbody>
      @if ($c['cancelled'])
        <tfoot class="bg-page font-bold">
          <tr class="border-t-2 border-line">
            <td class="p-3" colspan="5">Total, {{ number_format($c['cancelled']) }} cancelled</td>
            <td class="p-3 text-end">{{ Format::money($c['value']) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['paid']) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['refunded']) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['kept']) }}</td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</section>

{{-- Refunds paid --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Refunds paid out in this period</h2>
    <p class="text-xs text-muted mt-0.5">By the day the refund was paid, on any booking (cancelled or not, sold at any time).</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start {{ $th }}">Paid on</th>
          <th class="text-start {{ $th }}">Booking</th>
          <th class="text-start {{ $th }}">Customer</th>
          <th class="text-start {{ $th }}">Method</th>
          <th class="text-end {{ $th }}">Amount</th>
          <th class="text-start {{ $th }}">Recorded by</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($refunds as $r)
          <tr class="border-t border-line hover:bg-page">
            <td class="p-3 whitespace-nowrap">{{ Format::date($r->received_at) }}</td>
            <td class="p-3 whitespace-nowrap">
              @if ($r->booking)<a href="{{ route('admin.bookings.show', $r->booking) }}" class="font-bold text-ocean-700 hover:underline">{{ $r->booking->reference }}</a>
                <p class="text-xs text-muted">{{ $r->booking->sailing?->cruise?->name }} · {{ ucfirst($r->booking->status->value) }}</p>@endif
            </td>
            <td class="p-3">{{ $r->booking?->user?->name ?? $r->booking?->contact_email }}</td>
            <td class="p-3">{{ str_replace('_', ' ', ucfirst($r->method->value)) }}@if ($r->reference)<p class="text-xs text-muted">{{ $r->reference }}</p>@endif</td>
            <td class="p-3 text-end font-semibold">{{ Format::money($r->amount, 2) }}</td>
            <td class="p-3">{{ $r->recordedBy?->name ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-8 text-center text-muted">No refunds were paid in this period.</td></tr>
        @endforelse
      </tbody>
      @if ($refunds->isNotEmpty())
        <tfoot class="bg-page font-bold">
          <tr class="border-t-2 border-line">
            <td class="p-3" colspan="4">Total, {{ $refunds->count() }} {{ $refunds->count() == 1 ? 'refund' : 'refunds' }}</td>
            <td class="p-3 text-end">{{ Format::money($refunds->sum('amount'), 2) }}</td>
            <td class="p-3"></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</section>
