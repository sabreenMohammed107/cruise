@use('App\Support\Format')
@use('App\Reports\SalesSummaryReport')
{{-- Report 14: Interest & potential customers (App\Reports\InterestReport). Inside admin.reports.show. --}}
@php
  $c = $current;
  $p = $previous;
  $chg = fn (string $k) => $p ? SalesSummaryReport::change($c[$k], $p[$k]) : null;
  $prev = fn (string $k) => $p ? number_format($p[$k]) : null;
  $pct = fn (?float $v) => $v === null ? '—' : number_format($v, 0).'%';
  $th = 'font-semibold p-3 whitespace-nowrap';
  $statusTag = fn (string $s) => match ($s) { 'accepted' => 'tag-lagoon', 'rejected' => 'tag-sun', default => 'tag-ocean' };
@endphp

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 mt-6" data-print-cols style="--cols: 3">
  @include('admin.reports.partials.kpi', ['icon' => 'users', 'label' => 'People who showed interest', 'value' => number_format($c['people']), 'change' => $chg('people'), 'previous' => $prev('people'),
    'hint' => number_format($c['converted']).' booked afterwards · '.number_format($c['leads']).' never booked'])
  @include('admin.reports.partials.kpi', ['icon' => 'heart', 'label' => 'Favourites added', 'value' => number_format($c['favourites']), 'change' => $chg('favourites'), 'previous' => $prev('favourites')])
  @include('admin.reports.partials.kpi', ['icon' => 'chat', 'label' => 'Contact messages', 'value' => number_format($c['messages']), 'change' => $chg('messages'), 'previous' => $prev('messages'),
    'hint' => $c['unread'] ? number_format($c['unread']).' still unread' : 'All read'])
  @include('admin.reports.partials.kpi', ['icon' => 'mail', 'label' => 'Newsletter sign-ups', 'value' => number_format($c['subscribers']), 'change' => $chg('subscribers'), 'previous' => $prev('subscribers'),
    'hint' => number_format($c['unsubscribed']).' unsubscribed in this period'])
  @include('admin.reports.partials.kpi', ['icon' => 'award', 'label' => 'Agent applications', 'value' => number_format($c['applications']), 'change' => $chg('applications'), 'previous' => $prev('applications'),
    'hint' => number_format($c['agents_selling']).' of them selling now'])
  @include('admin.reports.partials.kpi', ['icon' => 'star', 'label' => 'Potential customers', 'value' => number_format($c['leads']), 'change' => $chg('leads'), 'previous' => $prev('leads'),
    'hint' => 'Showed interest in this period, never booked'])
</div>

<div class="grid lg:grid-cols-2 gap-6 mt-6 items-start" data-print-group>
  {{-- Sources --}}
  <section class="card overflow-hidden">
    <div class="p-5 pb-3">
      <h2 class="font-display font-semibold text-lg">Did they book?</h2>
      <p class="text-xs text-muted mt-0.5">People who showed interest in this period, by email, against their confirmed bookings at any time.</p>
    </div>
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}">Source</th><th class="text-end {{ $th }}">People</th><th class="text-end {{ $th }}">Booked after</th><th class="text-end {{ $th }}">Had booked</th><th class="text-end {{ $th }}">Never booked</th></tr>
      </thead>
      <tbody>
        @foreach ($sources as $r)
          <tr class="border-t border-line {{ $r['people'] ? '' : 'text-muted' }}">
            <td class="p-3 font-semibold">{{ $r['name'] }}@if ($r['entries'] > $r['people'])<span class="text-xs text-muted font-normal"> · {{ $r['entries'] }} entries</span>@endif</td>
            <td class="p-3 text-end">{{ number_format($r['people']) }}</td>
            <td class="p-3 text-end text-[#12805C] font-semibold">{{ number_format($r['converted']) }}</td>
            <td class="p-3 text-end">{{ number_format($r['had_booked']) }}</td>
            <td class="p-3 text-end font-semibold">{{ number_format($r['not_booked']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>

  {{-- Favourites per cruise --}}
  <section class="card overflow-hidden">
    <div class="p-5 pb-3">
      <h2 class="font-display font-semibold text-lg">Favourites per cruise</h2>
      <p class="text-xs text-muted mt-0.5">Every saved cruise, and how many of the people who saved it booked it.</p>
    </div>
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr><th class="text-start {{ $th }}">Cruise</th><th class="text-end {{ $th }}">All time</th><th class="text-end {{ $th }}">This period</th><th class="text-end {{ $th }}">Booked it</th><th class="text-end {{ $th }}">Not yet</th></tr>
      </thead>
      <tbody>
        @forelse ($cruises as $r)
          <tr class="border-t border-line">
            <td class="p-3 font-semibold">{{ $r['name'] }}</td>
            <td class="p-3 text-end">{{ number_format($r['total']) }}</td>
            <td class="p-3 text-end">{{ number_format($r['added']) }}</td>
            <td class="p-3 text-end">{{ number_format($r['booked']) }} <span class="text-xs text-muted">{{ $pct($r['rate']) }}</span></td>
            <td class="p-3 text-end font-semibold">{{ number_format($r['not_booked']) }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="p-6 text-center text-muted">No cruise has been saved as a favourite.</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>
</div>

{{-- Potential customers --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Potential customers to follow up</h2>
    <p class="text-xs text-muted mt-0.5">Showed interest in this period and never booked (rejected agent applications left out). Newest first.</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start {{ $th }}">Person</th>
          <th class="text-start {{ $th }}">How they showed interest</th>
          <th class="text-start {{ $th }}">Interested in</th>
          <th class="text-start {{ $th }}">Last</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($leads as $l)
          <tr class="border-t border-line hover:bg-page">
            <td class="p-3">
              <span class="font-semibold">{{ $l['name'] ?? '—' }}</span>
              <p class="text-xs text-muted"><a href="mailto:{{ $l['email'] }}" class="hover:underline">{{ $l['email'] }}</a>@if ($l['phone']) · <span dir="ltr">{{ $l['phone'] }}</span>@endif</p>
            </td>
            <td class="p-3"><div class="flex flex-wrap gap-1">@foreach ($l['sources'] as $s)<span class="tag tag-ocean">{{ $s }}</span>@endforeach</div></td>
            <td class="p-3">
              {{ implode(', ', $l['interests']) ?: '' }}
              @if ($l['note'])<p class="text-xs text-muted">{{ $l['note'] }}</p>@endif
              @if (! $l['interests'] && ! $l['note'])<span class="text-muted">—</span>@endif
            </td>
            <td class="p-3 whitespace-nowrap tabular">{{ Format::date($l['last']) }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="p-8 text-center text-muted">Nobody to follow up in this period.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

{{-- Agent applications --}}
<section class="card overflow-hidden mt-6">
  <div class="p-5 pb-3">
    <h2 class="font-display font-semibold text-lg">Agent applications</h2>
    <p class="text-xs text-muted mt-0.5">Applications made in this period, and what the accepted ones have sold since.</p>
  </div>
  <div class="overflow-x-auto report-table-wrap">
    <table class="w-full text-sm tabular">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start {{ $th }}">Company</th>
          <th class="text-start {{ $th }}">Applied</th>
          <th class="text-start {{ $th }}">Status</th>
          <th class="text-end {{ $th }}">Bookings</th>
          <th class="text-end {{ $th }}">Net revenue</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($applications as $r)
          @php($a = $r['application'])
          <tr class="border-t border-line hover:bg-page">
            <td class="p-3"><span class="font-semibold">{{ $a->company_name }}</span><p class="text-xs text-muted">{{ $a->contact_name }} · {{ $a->email }}</p></td>
            <td class="p-3 whitespace-nowrap">{{ Format::date($a->created_at) }}</td>
            <td class="p-3"><span class="tag {{ $statusTag($a->status->value) }}">{{ ucfirst($a->status->value) }}</span></td>
            <td class="p-3 text-end">{{ $r['bookings'] ?: '—' }}</td>
            <td class="p-3 text-end">{{ $r['bookings'] ? Format::money($r['revenue']) : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="p-8 text-center text-muted">No agent applications in this period.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
