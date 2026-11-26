{{-- Guest voucher (step 36): Menatours / Safer banners (public/images/voucher), total in the booked currency only,
     travellers table, day-by-day itinerary; prints on A4 (theme.css "Print").
     Converted from legacy-html/confirmation.html. Rendered on the server from the saved booking
     (the template filled #ref, #trip and #people from sessionStorage). Words: lang/{en,ar}/site.php → confirmation, booking. --}}
@extends('layouts.app')
@use('App\Support\Bi')

@section('title', __('site.confirmation.title'))

@php
  $sailing = $booking->sailing;
  $cruise = $sailing->cruise;
  // In the currency the guest booked in, at that day's rate (bookings.display_currency / exchange_rate)
  $money = fn ($n) => $booking->money($n);
  // Dates with month names in the page language (Carbon follows the app locale), Western digits as on the rest of the site
  $date = fn ($d) => $d->translatedFormat('j M Y');
  $guestText = collect(['adult' => $booking->adults, 'child' => $booking->children, 'infant' => $booking->infants])
      ->filter()
      ->map(fn ($n, $band) => trans_choice("site.guests.$band", $n, ['count' => $n]))
      ->join(__('site.guests.join'));
  $bandLabels = \App\Models\AgeBand::all()->mapWithKeys(fn ($b) => [$b->key->value => $b->tr('label')]);
  $cabin = $booking->cabinCategory;
  $several = $booking->cabins->count() > 1;
  // [heading or null, travellers] per cabin: "Cabin 2 · Balcony Cabin (2 adults)"
  $groups = $several
      ? $booking->cabins->map(fn ($c) => [
          __('ui.details.cabin_n', ['n' => $c->position]).' · '.$c->name().' ('.collect($c->guestCounts())->filter()->map(fn ($n, $band) => trans_choice("site.guests.$band", $n, ['count' => $n]))->join(__('site.guests.join')).')',
          $c->travellers,
      ])->all()
      : [[null, $booking->travellers]];
  $cancelled = $booking->status === \App\Enums\BookingStatus::Cancelled;
  // Confirmed once a payment is recorded (part or in full), "Pending payment" until then, as the admin (Booking::stage())
  $paid = $booking->stage() === 'confirmed';
@endphp

@section('content')
  <!-- Shown after the guest confirms. The site has no online payment; payment is arranged offline. -->
  <main class="flex-1 max-w-3xl w-full mx-auto px-4 sm:px-6 py-10 sm:py-14">
    <div id="success">
      <div class="text-center no-print">
        <span class="inline-grid place-items-center w-16 h-16 rounded-full bg-[#E3F5EE] text-[#12805C]"><i data-icon="check" class="w-8 h-8"></i></span>
        <h1 class="font-display font-bold text-3xl sm:text-4xl mt-5">{{ $cancelled ? __('site.confirmation.cancelled_title') : __('site.confirmation.booked') }}</h1>
        @unless ($cancelled)
          <p class="text-muted mt-3 max-w-[52ch] mx-auto">{!! __('site.confirmation.sent', [
              'email' => '<b class="text-ink" dir="ltr">'.e($booking->contact_email).'</b>',
              'phone' => '<b class="text-ink" dir="ltr">'.e($booking->contact_dial.' '.$booking->contact_phone).'</b>',
              'hours' => (int) config('cruise.payment_contact_hours'),
          ]) !!}</p>
        @endunless
      </div>

      <!-- Voucher: also what prints (A4, theme.css "Print"), between the Menatours / Safer header and footer banners -->
      <article class="card pass overflow-hidden mt-10" style="--perf-bg:#F5F8FB" id="voucher">
        {{-- Print frame: the banners are this table's header and footer rows, which browsers repeat at the top and
             bottom of every printed page (theme.css "Print"). On screen it is plain blocks: header banner, voucher,
             footer banner. tfoot comes after tbody so the screen order is right. --}}
        <table class="voucher-frame">
          <thead><tr><td><img src="{{ asset('images/voucher/voucher-footer.jpg') }}" alt="Menatours · Safer" class="voucher-banner voucher-banner-top" width="1600" height="255"></td></tr></thead>
          <tbody><tr><td>
        <div class="voucher-bar bg-ocean-700 text-white p-6 sm:p-7 flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-xs text-ocean-200 font-semibold">{{ __('site.confirmation.reference') }}</p>
            <p class="font-display font-bold text-3xl tabular tracking-wide" dir="ltr">{{ $booking->reference }}</p>
          </div>
          <div class="text-end">
            @if ($cancelled)
              <span class="inline-flex items-center gap-1.5 rounded-full bg-sun-50 text-sun-600 px-3 py-1 text-sm font-bold">{{ __('site.confirmation.cancelled') }}</span>
            @elseif ($paid)
              <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E3F5EE] text-[#0E6B4C] px-3 py-1 text-sm font-bold"><i data-icon="check" class="w-4 h-4"></i> {{ __('site.confirmation.confirmed') }}</span>
            @else
              <span class="inline-flex items-center gap-1.5 rounded-full bg-sun-50 text-sun-600 px-3 py-1 text-sm font-bold"><i data-icon="clock" class="w-4 h-4"></i> {{ __('site.confirmation.pending') }}</span>
            @endif
            {{-- Only the currency the guest booked in (EGP or USD); no USD equivalent or rate (step 36) --}}
            <p class="text-sm text-ocean-100 mt-2">{{ __('site.confirmation.total') }} <b class="text-white tabular" dir="ltr">{{ $money($booking->grand_total) }}</b></p>
          </div>
        </div>
        {{-- Held without payment: cancelled automatically at hold_until (App\Services\UnpaidBookings) --}}
        @if (! $cancelled && ($hold = $booking->holdForDisplay()))
          <div class="voucher-hold-wrap px-6 sm:px-7 pt-6 sm:pt-7">
            <p class="flex gap-2 items-start rounded-xl bg-sun-50 text-sun-600 px-4 py-3 text-sm font-semibold">
              <i data-icon="clock" class="w-4 h-4 mt-0.5 shrink-0"></i>
              <span>{{ __('site.confirmation.hold', ['hours' => $hold['hours'], 'date' => $date($hold['until']), 'time' => $hold['until']->format('H:i')]) }}</span>
            </p>
          </div>
        @endif
        <div class="p-6 sm:p-7">
          <h2 class="font-display font-semibold text-xl">{{ $cruise->tr('name') }}</h2>
          <dl class="grid grid-cols-2 sm:grid-cols-3 gap-5 mt-5">
            @foreach ([
                __('site.booking.ship') => $cruise->ship->tr('name'),
                __('site.booking.departs') => $date($sailing->departure_date).(app()->getLocale() === 'ar' ? '، ' : ', ').Bi::port($cruise->departure_port),
                __('site.booking.returns') => $date($sailing->return_date).(app()->getLocale() === 'ar' ? '، ' : ', ').Bi::port($cruise->arrival_port),
                __('site.booking.duration') => trans_choice('site.nights', $cruise->nights, ['count' => $cruise->nights]),
                // one cabin: its name; several (step 15): how many, each listed below
                ($several ? __('site.booking.cabins') : __('site.booking.cabin')) => $several
                    ? trans_choice('ui.details.cabins_count', $booking->cabins->count(), ['count' => $booking->cabins->count()])
                    : (app()->getLocale() === 'ar' ? ($cabin->name_ar ?: $cabin->name_en) : $cabin->name_en),
                __('site.booking.guests') => $guestText,
            ] as $label => $value)
              <div><dt class="text-xs font-semibold text-muted">{{ $label }}</dt><dd class="font-bold mt-0.5">{{ $value }}</dd></div>
            @endforeach
          </dl>
        </div>
        {{-- What the fare includes and doesn't: the cruise page's lists (Admin → Cruise pages → Cruise details) --}}
        @if ($fare->isNotEmpty())
          <div class="perf-x"></div>
          <div class="p-6 sm:p-7 voucher-fare grid sm:grid-cols-2 gap-6">
            @foreach ($fare as $key => $section)
              <div>
                <h3 class="font-bold mb-3">{{ $section->bi('title') }}</h3>
                <ul class="grid gap-2 text-sm {{ $key === 'not_included' ? 'text-muted' : '' }}">
                  @foreach ($section->items as $item)
                    <li class="flex gap-2"><i data-icon="{{ $item->icon ?: ($key === 'included' ? 'check' : 'minus') }}" class="w-4 h-4 mt-0.5 shrink-0 {{ $key === 'included' ? 'text-lagoon-600' : '' }}"></i><span>{{ $item->bi('title') }}</span></li>
                  @endforeach
                </ul>
              </div>
            @endforeach
          </div>
        @endif
        <div class="perf-x"></div>
        <div class="p-6 sm:p-7" data-print-split="travellers">
          <h3 class="font-bold mb-3">{{ __('site.booking.travellers') }}</h3>
          {{-- One table per cabin when the booking has several (step 15). A table, not cards: each name has the full row
               width, in its own direction (dir="auto", Arabic or Latin), and breaks only between words. --}}
          @foreach ($groups as [$heading, $travellers])
            @if ($heading)<p class="text-sm font-bold text-muted {{ $loop->first ? '' : 'mt-5' }} mb-2">{{ $heading }}</p>@endif
            <table class="voucher-table w-full text-sm">
              <thead>
                <tr class="text-xs text-muted text-start">
                  <th class="w-8">#</th>
                  <th>{{ __('site.confirmation.traveller_name') }}</th>
                  <th class="w-px">{{ __('site.confirmation.traveller_type') }}</th>
                  <th class="w-px">{{ __('site.confirmation.traveller_nationality') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($travellers as $t)
                  <tr>
                    <td class="text-muted tabular">{{ $loop->iteration }}</td>
                    <td class="font-bold"><bdi dir="auto">{{ trim($t->first_name.' '.$t->last_name) }}</bdi></td>
                    <td class="whitespace-nowrap"><span class="tag tag-ocean">{{ $bandLabels[$t->band->value] ?? ucfirst($t->band->value) }}</span></td>
                    <td class="whitespace-nowrap">{{ Bi::value('nationalities', $t->nationality) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @endforeach
        </div>
        {{-- Day-by-day itinerary of the booked sailing: day N is departure + N − 1 days (Admin → Cruises → Itinerary) --}}
        @if ($cruise->itinerary->isNotEmpty())
          <div class="perf-x"></div>
          <div class="p-6 sm:p-7" data-print-split="itinerary">
            <h3 class="font-bold mb-3">{{ __('site.confirmation.itinerary') }}</h3>
            <ol class="voucher-itinerary">
              @foreach ($cruise->itinerary as $day)
                <li>
                  <div class="text-xs font-bold text-ocean-700 whitespace-nowrap">
                    {{ __('site.confirmation.day_n', ['n' => $day->day_number]) }}
                    <span class="block text-muted font-semibold">{{ $date($sailing->departure_date->copy()->addDays(max(0, $day->day_number - 1))) }}</span>
                  </div>
                  <div class="min-w-0">
                    <p class="font-bold">{{ Bi::isArabic() && filled($day->port_ar) ? $day->port_ar : ($day->isAtSea() ? __('site.at_sea') : Bi::port($day->port)) }}@if (filled($day->tr('time_label')))<span class="text-muted font-normal text-sm"> · <bdi>{{ $day->tr('time_label') }}</bdi></span>@endif</p>
                    @if (filled($day->tr('description')))<p class="text-sm text-muted mt-0.5">{{ $day->tr('description') }}</p>@endif
                  </div>
                </li>
              @endforeach
            </ol>
          </div>
        @endif
          </td></tr></tbody>
          <tfoot><tr><td><img src="{{ asset('images/voucher/voucher-header.jpg') }}" alt="Cruises@menatours.com.eg · +20 105 562 4433 · 35 Abour Buildings, Salah Salem St, Heliopolis, Cairo" class="voucher-banner voucher-banner-bottom" width="1600" height="255"></td></tr></tfoot>
        </table>
      </article>

      {{-- Printed voucher: A4 sheets built from #voucher just before printing (script below). Each sheet: header banner,
           a small gap, its share of the content, the footer banner at the very bottom. Without the script, print
           falls back to #voucher with its repeating table header / footer (theme.css "Print"). --}}
      <div id="voucher-print" aria-hidden="true"></div>
      <script>
        (() => {
          const voucher = document.getElementById('voucher');
          const out = document.getElementById('voucher-print');
          // A direct child of <body>, so printing leaves out everything else on the page (theme.css "Print")
          document.body.append(out);
          const content = voucher.querySelector('.voucher-frame > tbody > tr > td');
          const head = voucher.querySelector('.voucher-banner-top').src;
          const foot = voucher.querySelector('.voucher-banner-bottom').src;

          const sheet = () => {
            const s = document.createElement('section');
            s.className = 'voucher-sheet';
            // Sized (1600 × 255), so a banner holds its place even before its picture is drawn
            s.innerHTML = `<img class="voucher-sheet-banner" src="${head}" alt="" width="1600" height="255"><div class="voucher-sheet-body"></div><img class="voucher-sheet-banner voucher-sheet-foot" src="${foot}" alt="" width="1600" height="255">`;
            return s;
          };
          const box = (...nodes) => {
            const d = document.createElement('div');
            d.className = 'voucher-unit';
            nodes.forEach((n) => d.append(n));
            return d;
          };
          // A shell of a section (its wrapper, without children) for the pieces of a split section
          const shell = (section, first) => {
            const s = section.cloneNode(false);
            s.removeAttribute('data-print-split');
            if (!first) s.classList.add('voucher-cont');
            return s;
          };

          // The voucher in pieces that never break across sheets: the blue bar, the trip details, each traveller
          // table (or rows of it, with its header, when it is taller than a sheet), each itinerary day. A dashed
          // separator and a section title stay with the piece after them.
          const pieces = (body, room) => {
            const list = [];
            let lead = null;
            const push = (el) => {
              const unit = lead ? box(lead.cloneNode(true), el) : box(el);
              lead = null;
              body.append(unit);
              list.push(unit);
            };
            for (const section of content.children) {
              if (section.classList.contains('perf-x')) { lead = section; continue; }
              const kind = section.dataset.printSplit;
              if (kind === 'itinerary') {
                const title = section.querySelector('h3');
                [...section.querySelectorAll(':scope > ol > li')].forEach((li, i) => {
                  const s = shell(section, i === 0);
                  if (i === 0) s.append(title.cloneNode(true));
                  const ol = document.createElement('ol');
                  ol.className = 'voucher-itinerary';
                  ol.append(li.cloneNode(true));
                  s.append(ol);
                  push(s);
                });
              } else if (kind === 'travellers') {
                const title = section.querySelector('h3');
                let first = true;
                let groupHeading = null;
                for (const el of section.children) {
                  if (el === title) continue;
                  if (el.tagName !== 'TABLE') { groupHeading = el; continue; }
                  // Rows of this table, as many per piece as fit a sheet
                  const rows = [...el.tBodies[0].rows];
                  while (rows.length) {
                    const s = shell(section, first);
                    if (first) s.append(title.cloneNode(true));
                    if (groupHeading) { s.append(groupHeading.cloneNode(true)); groupHeading = null; }
                    const t = el.cloneNode(false);
                    t.append(el.tHead.cloneNode(true));
                    const tb = document.createElement('tbody');
                    t.append(tb);
                    s.append(t);
                    const unit = lead ? box(lead.cloneNode(true), s) : box(s);
                    body.append(unit);
                    do { tb.append(rows.shift().cloneNode(true)); } while (rows.length && unit.offsetHeight < room);
                    if (unit.offsetHeight > room && tb.rows.length > 1) { rows.unshift(tb.lastElementChild); tb.lastElementChild.remove(); }
                    lead = null;
                    list.push(unit);
                    first = false;
                  }
                }
              } else {
                push(section.cloneNode(true));
              }
            }
            return list;
          };

          const build = () => {
            // Measure in a hidden sheet of the real size, then fill sheets: a piece that doesn't fit starts the next
            const probe = sheet();
            probe.classList.add('voucher-measure');
            document.body.append(probe);
            const body = probe.querySelector('.voucher-sheet-body');
            const cs = getComputedStyle(body);
            const room = body.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom);
            const units = pieces(body, room).map((el) => ({ el, h: el.getBoundingClientRect().height }));
            out.replaceChildren();
            let page = null;
            let used = 0;
            for (const { el, h } of units) {
              if (!page || (used > 0 && used + h > room)) {
                page = sheet();
                out.append(page);
                used = 0;
              }
              page.querySelector('.voucher-sheet-body').append(el);
              used += h;
            }
            probe.remove();
            document.documentElement.classList.add('voucher-sheets');
          };

          // Built once, in advance, after the fonts and both banners have loaded: built at the moment of printing, the
          // new banner pictures were not drawn yet when the browser took its print snapshot, and the PDF came out
          // without them. Printing before it is ready (the button straight away) builds it then.
          let built = false;
          const once = () => { if (!built) { build(); built = true; } };
          const loaded = (src) => new Promise((done) => { const i = new Image(); i.onload = i.onerror = done; i.src = src; if (i.complete) done(); });
          // (never waiting more than 4 seconds for them)
          const ready = (...waits) => Promise.race([Promise.all(waits), new Promise((done) => setTimeout(done, 4000))]);
          ready(document.fonts ? document.fonts.ready : null, loaded(head), loaded(foot)).then(once);
          window.addEventListener('beforeprint', once);
        })();
      </script>

      <div class="mt-8 flex flex-wrap justify-center gap-3 no-print">
        <button class="btn btn-accent" onclick="window.print()"><i data-icon="download" class="w-5 h-5"></i> {{ __('site.confirmation.download') }}</button>
        <a href="{{ route('account') }}#bookings" class="btn btn-outline">{{ __('site.confirmation.view_in_account') }}</a>
        <a href="{{ route('home') }}" class="btn btn-ghost">{{ __('site.confirmation.home') }}</a>
      </div>
    </div>
  </main>
@endsection
