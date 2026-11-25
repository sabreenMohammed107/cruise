@use('App\Support\Format')
@use('App\Reports\SalesSummaryReport')
{{-- Report 3: Receivables (App\Reports\ReceivablesReport). Inside admin.reports.show. --}}
@php
  $c = $current;
  $p = $previous;
  $chg = fn (string $k) => $p ? SalesSummaryReport::change($c[$k], $p[$k]) : null;
  $prev = fn (string $k, bool $money = true) => $p ? ($money ? Format::money($p[$k]) : number_format($p[$k])) : null;
  $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 0).'%';
  $th = 'font-semibold p-3 whitespace-nowrap';
  $days = fn (?int $d) => $d === null ? '—' : ($d < 0 ? 'Departed '.abs($d).' '.(abs($d) == 1 ? 'day' : 'days').' ago' : ($d === 0 ? 'Today' : $d.' '.($d == 1 ? 'day' : 'days')));
  $urgency = fn (?int $d) => match (true) { $d === null => '', $d < 0 => 'tag-sun', $d <= 7 => 'tag-sun', $d <= 30 => 'tag-ocean', default => 'tag-lagoon' };
@endphp

<p class="mt-4 text-sm">
  <span class="tag tag-ocean">Balances as at {{ $asAt->format('j M Y, H:i') }}</span>
  @if ($previousAsAt)<span class="text-muted">compared with the balances at {{ $previousAsAt->format('j M Y, H:i') }}</span>@endif
</p>

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 mt-4" data-print-cols style="--cols: 3">
  @include('admin.reports.partials.kpi', ['icon' => 'clock', 'label' => 'Still to pay', 'value' => Format::money($c['due']), 'change' => $chg('due'), 'previous' => $prev('due'), 'invert' => true,
    'hint' => $c['paid_share'] === null ? 'Nothing owed' : $pct($c['paid_share']).' of these bookings\' value is paid'])
  @include('admin.reports.partials.kpi', ['icon' => 'ticket', 'label' => 'Bookings with a balance', 'value' => number_format($c['bookings']), 'change' => $chg('bookings'), 'previous' => $prev('bookings', false), 'invert' => true,
    'hint' => number_format($c['unpaid']).' not paid at all ('.Format::money($c['unpaid_due']).') · '.number_format($c['partial']).' part paid'])
  @include('admin.reports.partials.kpi', ['icon' => 'alert', 'label' => 'Departed, still owing', 'value' => Format::money($c['departed_due']), 'change' => $chg('departed_due'), 'previous' => $prev('departed_due'), 'invert' => true,
    'hint' => number_format($c['departed']).' '.($c['departed'] == 1 ? 'booking has' : 'bookings have').' sailed without paying in full'])
  @include('admin.reports.partials.kpi', ['icon' => 'calendar', 'label' => 'Departing within 30 days', 'value' => Format::money($c['soon_due']), 'change' => $chg('soon_due'), 'previous' => $prev('soon_due'), 'invert' => true,
    'hint' => number_format($c['soon']).' '.($c['soon'] == 1 ? 'booking' : 'bookings').' to collect before sailing'])
  @include('admin.reports.partials.kpi', ['icon' => 'plus', 'label' => 'Sold in this period, still owing', 'value' => Format::money($c['new_due']), 'change' => $chg('new_due'), 'previous' => $prev('new_due'), 'invert' => true,
    'hint' => number_format($c['new']).' of the bookings above were sold in this period'])
  @include('admin.reports.partials.kpi', ['icon' => 'check', 'label' => 'Collected in this period', 'value' => Format::money($c['collected']), 'change' => $chg('collected'), 'previous' => $prev('collected'),
    'hint' => 'Payments less refunds received in this period, on any booking'])
</div>

<div class="grid lg:grid-cols-2 gap-6 mt-6 items-start" data-print-group>
  @foreach ([['Days until departure', 'A balance is due before sailing: the closer the departure, the more urgent.', 'Departure in', $departure, '#B42318'],
             ['Age of the balance', 'How long ago these bookings were sold.', 'Sold how long ago', $age, '#1B72BF']] as [$title, $note, $col, $rows, $color])
    <section class="card overflow-hidden">
      <div class="p-5 pb-3">
        <h2 class="font-display font-semibold text-lg">{{ $title }}</h2>
        <p class="text-xs text-muted mt-0.5">{{ $note }}</p>
      </div>
      <table class="w-full text-sm tabular">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr><th class="text-start {{ $th }}">{{ $col }}</th><th class="text-end {{ $th }}">Bookings</th><th class="text-end {{ $th }}">Still to pay</th><th class="text-start {{ $th }} w-1/3">Share</th></tr>
        </thead>
        <tbody>
          @foreach ($rows as $r)
            <tr class="border-t border-line {{ $r['bookings'] ? '' : 'text-muted' }}">
              <td class="p-3 font-semibold whitespace-nowrap">{{ $r['name'] }}</td>
              <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
              <td class="p-3 text-end font-semibold">{{ Format::money($r['due']) }}</td>
              <td class="p-3"><div class="flex items-center gap-2"><div class="h-2 flex-1 rounded-full bg-page overflow-hidden"><div class="h-2 rounded-full" style="background: {{ $color }}; width: {{ round($r['share'], 1) }}%"></div></div><span class="text-xs w-9 text-end">{{ $pct($r['share']) }}</span></div></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>
  @endforeach

  @foreach ([['By cruise', 'Cruise', $cruises], ['By assigned seller', 'Assigned to', $sellers]] as [$title, $col, $rows])
    <section class="card overflow-hidden">
      <div class="p-5 pb-3"><h2 class="font-display font-semibold text-lg">{{ $title }}</h2></div>
      <div class="overflow-x-auto report-table-wrap">
      <table class="w-full text-sm tabular">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr><th class="text-start {{ $th }}">{{ $col }}</th><th class="text-end {{ $th }}">Bookings</th><th class="text-end {{ $th }}">Paid</th><th class="text-end {{ $th }}">Still to pay</th><th class="text-end {{ $th }}">Next departure</th></tr>
        </thead>
        <tbody>
          @forelse ($rows as $r)
            <tr class="border-t border-line">
              <td class="p-3 font-semibold">{{ $r['name'] }}</td>
              <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
              <td class="p-3 text-end">{{ Format::money($r['paid']) }}</td>
              <td class="p-3 text-end font-semibold">{{ Format::money($r['due']) }}</td>
              <td class="p-3 text-end whitespace-nowrap">{{ Format::date($r['next']) }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="p-6 text-center text-muted">Nothing is owed.</td></tr>
          @endforelse
        </tbody>
      </table>
      </div>
    </section>
  @endforeach
</div>

{{-- Balances --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Bookings still to pay</h2>
    <p class="text-xs text-muted mt-0.5">Soonest departure first, to follow up in that order.</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start {{ $th }}">Reference</th>
          <th class="text-start {{ $th }}">Customer</th>
          <th class="text-start {{ $th }}">Sailing</th>
          <th class="text-start {{ $th }}">Departure in</th>
          <th class="text-end {{ $th }}">Total</th>
          <th class="text-end {{ $th }}">Paid</th>
          <th class="text-end {{ $th }}">Still to pay</th>
          <th class="text-start {{ $th }}">Last payment</th>
          <th class="text-start {{ $th }}">Assigned to</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($open as $r)
          @php($b = $r['booking'])
          <tr class="border-t border-line hover:bg-page">
            <td class="p-3 whitespace-nowrap">
              <a href="{{ route('admin.bookings.show', $b) }}" class="font-bold text-ocean-700 hover:underline">{{ $b->reference }}</a>
              <p class="text-xs text-muted">{{ $b->isAgentBooking() ? 'Agent · '.($b->agent?->company_name ?: $b->agent?->name) : 'Website' }}</p>
            </td>
            <td class="p-3">{{ $b->user?->name ?? '—' }}<p class="text-xs text-muted">{{ $b->contact_email }}@if ($b->contact_phone) · <span dir="ltr">{{ trim($b->contact_dial.' '.$b->contact_phone) }}</span>@endif</p></td>
            <td class="p-3">{{ $r['cruise'] }}<p class="text-xs text-muted">{{ Format::date($r['departure']) }} · sold {{ Format::date($b->confirmed_at) }} ({{ $r['age_days'] }} {{ $r['age_days'] == 1 ? 'day' : 'days' }} ago)</p></td>
            <td class="p-3 whitespace-nowrap"><span class="tag {{ $urgency($r['days_to_departure']) }}">{{ $days($r['days_to_departure']) }}</span></td>
            <td class="p-3 text-end">{{ Format::money($r['total']) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['paid']) }}<p class="text-xs text-muted">{{ $pct($r['paid_share']) }}</p></td>
            <td class="p-3 text-end font-semibold">{{ Format::money($r['due']) }}</td>
            <td class="p-3 whitespace-nowrap">{{ $r['last_paid_at'] ? Format::date($r['last_paid_at']) : 'Nothing paid' }}</td>
            <td class="p-3">{{ $r['seller'] }}</td>
          </tr>
        @empty
          <tr><td colspan="9" class="p-8 text-center text-muted">Every booking is paid in full.</td></tr>
        @endforelse
      </tbody>
      @if ($c['bookings'])
        <tfoot class="bg-page font-bold">
          <tr class="border-t-2 border-line">
            <td class="p-3" colspan="4">Total, {{ number_format($c['bookings']) }} {{ $c['bookings'] == 1 ? 'booking' : 'bookings' }}</td>
            <td class="p-3 text-end">{{ Format::money($c['value']) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['paid']) }}</td>
            <td class="p-3 text-end">{{ Format::money($c['due']) }}</td>
            <td class="p-3" colspan="2"></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</section>
