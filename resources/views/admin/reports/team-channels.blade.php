@use('App\Support\Format')
@use('App\Reports\SalesSummaryReport')
{{-- Reports 8, 9 and 10: Sales team & channels (App\Reports\TeamChannelsReport). Inside admin.reports.show. --}}
@php
  $c = $current;
  $p = $previous;
  $hasP = $p !== null;
  $chg = fn (string $k) => $hasP ? SalesSummaryReport::change($c[$k], $p[$k]) : null;
  $prevMoney = fn (string $k) => $hasP ? Format::money($p[$k]) : null;
  // A table cell's change against the comparison period: [text, colour class]
  $delta = function (float $now, ?float $before) {
      if ($before === null) {
          return ['', ''];
      }
      $d = SalesSummaryReport::change($now, $before);
      if ($d === null) {
          return [$now > 0 ? 'new' : '—', 'text-muted'];
      }

      return [($d > 0.05 ? '▲ ' : ($d < -0.05 ? '▼ ' : '')).number_format(abs($d), 0).'%', $d > 0.05 ? 'text-[#12805C]' : ($d < -0.05 ? 'text-[#B42318]' : 'text-muted')];
  };
  $rate = fn (?float $v) => $v === null ? '—' : number_format($v, 0).'%';
  $th = 'font-semibold p-3 whitespace-nowrap';
@endphp

{{-- Headline figures --}}
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-6" data-print-cols style="--cols: 4">
  @include('admin.reports.partials.kpi', ['icon' => 'check', 'label' => 'Net received by the team', 'value' => Format::money($c['net_received']), 'change' => $chg('net_received'), 'previous' => $prevMoney('net_received'),
    'hint' => number_format($c['payments']).' '.($c['payments'] == 1 ? 'entry' : 'entries').($c['refunded'] > 0 ? ' · '.Format::money($c['refunded']).' refunded' : '').' · by the day received'])
  @include('admin.reports.partials.kpi', ['icon' => 'users', 'label' => 'Sold by agents (B2B)', 'value' => Format::money($c['agent_revenue']), 'change' => $chg('agent_revenue'), 'previous' => $prevMoney('agent_revenue'),
    'hint' => number_format($c['agent_bookings']).' '.($c['agent_bookings'] == 1 ? 'booking' : 'bookings').($c['agent_share'] !== null ? ' · '.$rate($c['agent_share']).' of the revenue' : '')])
  @include('admin.reports.partials.kpi', ['icon' => 'tag', 'label' => 'Agent discount given', 'value' => Format::money($c['agent_discount']), 'change' => $chg('agent_discount'), 'previous' => $prevMoney('agent_discount'), 'invert' => true,
    'hint' => $c['agent_discount_rate'] !== null ? $rate($c['agent_discount_rate']).' off the public fare' : null])
  @include('admin.reports.partials.kpi', ['icon' => 'tag', 'label' => 'Promo codes & offers', 'value' => Format::money($c['discounts']), 'change' => $chg('discounts'), 'previous' => $prevMoney('discounts'), 'invert' => true,
    'hint' => 'On '.number_format($c['discounted']).' '.($c['discounted'] == 1 ? 'booking' : 'bookings').' · codes '.Format::money($c['promo']).' · offers '.Format::money($c['offers'])])
</div>

{{-- 8. Staff --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Staff performance</h2>
    <p class="text-xs text-muted mt-0.5">Bookings assigned to each staff member and sold in this period (cancelled not counted). "Payments recorded" are the entries each one made in this period, by the day the money came in, on bookings sold at any time.</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start {{ $th }}">Staff member</th>
          <th class="text-end {{ $th }}">Bookings</th>
          <th class="text-end {{ $th }}">Guests</th>
          <th class="text-end {{ $th }}">Net revenue</th>
          @if ($hasP)<th class="text-end {{ $th }}">vs comparison</th>@endif
          <th class="text-end {{ $th }}">Paid</th>
          <th class="text-end {{ $th }}">Still to collect</th>
          <th class="text-start {{ $th }} min-w-[9rem]">Collection rate</th>
          <th class="text-end {{ $th }}">Cancelled</th>
          <th class="text-end {{ $th }} border-s border-line">Payments recorded</th>
          <th class="text-end {{ $th }}">Net received</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($staff['rows'] as $r)
          @php([$dText, $dClass] = $delta($r['revenue'], $r['previous']))
          <tr class="border-t border-line hover:bg-page {{ ! $r['bookings'] && ! $r['payments'] ? 'text-muted' : '' }}">
            <td class="p-3">
              <span class="font-semibold">{{ $r['name'] }}</span>
              @if ($r['role'])<p class="text-xs text-muted">{{ $r['role'] }}</p>@endif
              @if (! empty($r['others']))<p class="text-xs text-muted">Payments on these bookings entered by someone not listed</p>@endif
            </td>
            <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
            <td class="p-3 text-end">{{ number_format($r['guests']) }}</td>
            <td class="p-3 text-end font-semibold">{{ Format::money($r['revenue']) }}</td>
            @if ($hasP)<td class="p-3 text-end text-xs font-bold {{ $dClass }}" title="Comparison: {{ $r['previous'] === null ? '—' : Format::money($r['previous']) }}">{{ $dText }}</td>@endif
            <td class="p-3 text-end">{{ Format::money($r['collected']) }}</td>
            <td class="p-3 text-end">{{ Format::money($r['outstanding']) }}</td>
            <td class="p-3">
              @if ($r['collection_rate'] !== null)
                <div class="flex items-center gap-2">
                  <div class="h-2 flex-1 rounded-full bg-page overflow-hidden"><div class="h-2 rounded-full {{ $r['collection_rate'] >= 80 ? 'bg-[#12805C]' : ($r['collection_rate'] >= 40 ? 'bg-sun-500' : 'bg-[#B42318]') }}" style="width: {{ min(100, round($r['collection_rate'], 1)) }}%"></div></div>
                  <span class="text-xs w-9 text-end">{{ $rate($r['collection_rate']) }}</span>
                </div>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td class="p-3 text-end">{{ $r['cancelled'] ?: '—' }}</td>
            <td class="p-3 text-end border-s border-line">{{ $r['payments'] ?: '—' }}</td>
            <td class="p-3 text-end">{{ $r['payments'] ? Format::money($r['net_received']) : '—' }}@if ($r['refunded'] > 0)<p class="text-xs text-muted">{{ Format::money($r['refunded']) }} refunded</p>@endif</td>
          </tr>
        @empty
          <tr><td colspan="11" class="p-8 text-center text-muted">No staff to show.</td></tr>
        @endforelse
      </tbody>
      @php($t = $staff['total'])
      @php([$dText, $dClass] = $delta($t['revenue'], $t['previous']))
      <tfoot class="bg-page font-bold">
        <tr class="border-t-2 border-line">
          <td class="p-3">Total</td>
          <td class="p-3 text-end">{{ number_format($t['bookings']) }}</td>
          <td class="p-3 text-end">{{ number_format($t['guests']) }}</td>
          <td class="p-3 text-end">{{ Format::money($t['revenue']) }}</td>
          @if ($hasP)<td class="p-3 text-end text-xs {{ $dClass }}">{{ $dText }}</td>@endif
          <td class="p-3 text-end">{{ Format::money($t['collected']) }}</td>
          <td class="p-3 text-end">{{ Format::money($t['outstanding']) }}</td>
          <td class="p-3">{{ $rate($t['collection_rate']) }}</td>
          <td class="p-3 text-end">{{ $t['cancelled'] ?: '—' }}</td>
          <td class="p-3 text-end border-s border-line">{{ $t['payments'] ?: '—' }}</td>
          <td class="p-3 text-end">{{ Format::money($t['net_received']) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>
</section>

{{-- 9. Agents --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Agents (B2B)</h2>
    <p class="text-xs text-muted mt-0.5">Agent bookings sold in this period. The agent discount is what the agent rate took off the public fare.</p>
  </div>

  @if ($tiers->isEmpty() && $agents->isEmpty())
    <p class="px-5 pb-8 pt-4 text-center text-muted">No agent bookings in this period.</p>
  @else
    {{-- By agent type --}}
    <h3 class="px-5 pt-2 pb-2 text-sm font-semibold text-muted uppercase tracking-wide">By agent type <span class="normal-case font-normal">(the type when they booked)</span></h3>
    <div class="overflow-x-auto report-table-wrap">
      <table class="w-full text-sm tabular">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr>
            <th class="text-start {{ $th }}">Agent type</th>
            <th class="text-end {{ $th }}">Agents</th>
            <th class="text-end {{ $th }}">Bookings</th>
            <th class="text-end {{ $th }}">Public fare</th>
            <th class="text-end {{ $th }}">Agent discount</th>
            <th class="text-end {{ $th }}">Discount %</th>
            <th class="text-end {{ $th }}">Net revenue</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($tiers as $r)
            <tr class="border-t border-line hover:bg-page">
              <td class="p-3 font-semibold">{{ $r['name'] }}</td>
              <td class="p-3 text-end">{{ number_format($r['agents']) }}</td>
              <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
              <td class="p-3 text-end">{{ Format::money($r['public_fare']) }}</td>
              <td class="p-3 text-end text-[#B42318]">{{ Format::money(-$r['agent_discount']) }}</td>
              <td class="p-3 text-end">{{ $rate($r['discount_rate']) }}</td>
              <td class="p-3 text-end font-semibold">{{ Format::money($r['revenue']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- By agent --}}
    <h3 class="px-5 pt-6 pb-2 text-sm font-semibold text-muted uppercase tracking-wide">By agent</h3>
    <div class="overflow-x-auto report-table-wrap">
      <table class="w-full text-sm tabular">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr>
            <th class="text-start {{ $th }}">Agent</th>
            <th class="text-end {{ $th }}">Bookings</th>
            <th class="text-end {{ $th }}">Guests</th>
            <th class="text-end {{ $th }}">Agent discount</th>
            <th class="text-end {{ $th }}">Net revenue</th>
            @if ($hasP)<th class="text-end {{ $th }}">vs comparison</th>@endif
            <th class="text-end {{ $th }}">Paid</th>
            <th class="text-end {{ $th }}">Still to collect</th>
            <th class="text-start {{ $th }}">Last sale</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($agents as $r)
            @php([$dText, $dClass] = $delta($r['revenue'], $r['previous']))
            <tr class="border-t border-line hover:bg-page {{ $r['bookings'] ? '' : 'text-muted' }}">
              <td class="p-3">
                <span class="font-semibold">{{ $r['name'] }}</span>
                <p class="text-xs text-muted">{{ collect([$r['contact'], $r['tier']])->filter()->implode(' · ') ?: '—' }}</p>
              </td>
              <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
              <td class="p-3 text-end">{{ number_format($r['guests']) }}</td>
              <td class="p-3 text-end">{{ Format::money($r['agent_discount']) }}@if ($r['discount_rate'] !== null)<span class="text-xs text-muted ms-1">{{ $rate($r['discount_rate']) }}</span>@endif</td>
              <td class="p-3 text-end font-semibold">{{ Format::money($r['revenue']) }}</td>
              @if ($hasP)<td class="p-3 text-end text-xs font-bold {{ $dClass }}" title="Comparison: {{ Format::money($r['previous']) }}">{{ $r['bookings'] ? $dText : 'none this period' }}</td>@endif
              <td class="p-3 text-end">{{ Format::money($r['collected']) }}</td>
              <td class="p-3 text-end">{{ Format::money($r['outstanding']) }}</td>
              <td class="p-3 whitespace-nowrap">{{ Format::date($r['last_sale']) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="bg-page font-bold">
          <tr class="border-t-2 border-line">
            <td class="p-3">Total, {{ number_format($agents->where('bookings', '>', 0)->count()) }} {{ $agents->where('bookings', '>', 0)->count() == 1 ? 'agent' : 'agents' }}</td>
            <td class="p-3 text-end">{{ number_format($agents->sum('bookings')) }}</td>
            <td class="p-3 text-end">{{ number_format($agents->sum('guests')) }}</td>
            <td class="p-3 text-end">{{ Format::money($agents->sum('agent_discount')) }}</td>
            <td class="p-3 text-end">{{ Format::money($agents->sum('revenue')) }}</td>
            @if ($hasP)@php([$dText, $dClass] = $delta($agents->sum('revenue'), $agents->sum('previous')))<td class="p-3 text-end text-xs {{ $dClass }}">{{ $dText }}</td>@endif
            <td class="p-3 text-end">{{ Format::money($agents->sum('collected')) }}</td>
            <td class="p-3 text-end">{{ Format::money($agents->sum('outstanding')) }}</td>
            <td class="p-3"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  @endif
</section>

{{-- 10. Offers and promo codes --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Offers & promo codes: are they worth it?</h2>
    <p class="text-xs text-muted mt-0.5">
      For the bookings sold in this period: what each one took off and the revenue those bookings brought.
      <b>Discount share</b> is the part of the price given away; <b>revenue per $1 off</b> is what came in for each dollar of discount.
      The average booking this period was <b class="text-ink">{{ Format::money($c['bookings'] ? $c['revenue'] / $c['bookings'] : 0) }}</b>.
    </p>
  </div>

  @foreach (['Offers' => [$offers, 'Offer', 'A booking with two offers counts under each.'], 'Promo codes' => [$promos, 'Code', null]] as $heading => [$rows, $first, $note])
    <h3 class="px-5 pt-4 pb-2 text-sm font-semibold text-muted uppercase tracking-wide">{{ $heading }} @if ($note)<span class="normal-case font-normal">· {{ $note }}</span>@endif</h3>
    @if ($rows->isEmpty())
      <p class="px-5 pb-6 text-sm text-muted">No {{ strtolower($heading) }} used in this period.</p>
    @else
      <div class="overflow-x-auto report-table-wrap">
        <table class="w-full text-sm tabular">
          <thead class="bg-ocean-50 text-ocean-800">
            <tr>
              <th class="text-start {{ $th }}">{{ $first }}</th>
              <th class="text-end {{ $th }}">Bookings</th>
              <th class="text-end {{ $th }}">Discount given</th>
              <th class="text-end {{ $th }}">Net revenue</th>
              <th class="text-end {{ $th }}">Average booking</th>
              <th class="text-end {{ $th }}">Discount share</th>
              <th class="text-end {{ $th }}">Revenue per $1 off</th>
              <th class="text-end {{ $th }}">Used, all time</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $r)
              <tr class="border-t border-line hover:bg-page">
                <td class="p-3">
                  <span class="font-semibold">{{ $r['name'] }}</span>
                  @if ($r['status'])<span class="tag {{ $r['status'] === 'Live' ? 'tag-lagoon' : 'tag-sun' }} ms-1">{{ $r['status'] }}</span>@endif
                  @if ($r['detail'])<p class="text-xs text-muted">{{ $r['detail'] }}</p>@endif
                </td>
                <td class="p-3 text-end">{{ number_format($r['bookings']) }}</td>
                <td class="p-3 text-end text-[#B42318]">{{ Format::money(-$r['discount']) }}</td>
                <td class="p-3 text-end font-semibold">{{ Format::money($r['revenue']) }}</td>
                <td class="p-3 text-end">{{ Format::money($r['average']) }}</td>
                <td class="p-3 text-end">{{ $rate($r['share']) }}</td>
                <td class="p-3 text-end font-semibold">{{ $r['per_dollar'] === null ? '—' : Format::money($r['per_dollar'], 2) }}</td>
                <td class="p-3 text-end whitespace-nowrap">
                  @if ($r['used_count'] !== null){{ number_format($r['used_count']) }}<span class="text-muted"> / {{ $r['max_uses'] ?? '∞' }}</span>@else — @endif
                </td>
              </tr>
            @endforeach
          </tbody>
          @php($dSum = $rows->sum('discount'))
          @php($rSum = $rows->sum('revenue'))
          <tfoot class="bg-page font-bold">
            <tr class="border-t-2 border-line">
              <td class="p-3">Total</td>
              <td class="p-3 text-end">{{ number_format($rows->sum('bookings')) }}</td>
              <td class="p-3 text-end">{{ Format::money(-$dSum) }}</td>
              <td class="p-3 text-end">{{ Format::money($rSum) }}</td>
              <td class="p-3"></td>
              <td class="p-3 text-end">{{ $rate($rSum + $dSum > 0 ? $dSum / ($rSum + $dSum) * 100 : null) }}</td>
              <td class="p-3 text-end">{{ $dSum > 0 ? Format::money($rSum / $dSum, 2) : '—' }}</td>
              <td class="p-3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    @endif
  @endforeach
  <div class="pb-2"></div>
</section>
