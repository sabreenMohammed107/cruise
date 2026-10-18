{{-- Converted from legacy-html/confirmation.html. Rendered on the server from the saved booking
     (the template filled #ref, #trip and #people from sessionStorage). Words: lang/{en,ar}/site.php → confirmation, booking. --}}
@extends('layouts.app')
@use('App\Support\Bi')

@section('title', __('site.confirmation.title'))

@php
  $sailing = $booking->sailing;
  $cruise = $sailing->cruise;
  $money = fn ($n) => config('cruise.currency_symbol').number_format((float) $n);
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
@endphp

@section('content')
  <!-- Shown after the guest confirms. The site has no online payment; payment is arranged offline. -->
  <main class="flex-1 max-w-3xl w-full mx-auto px-4 sm:px-6 py-10 sm:py-14">
    <div id="success">
      <div class="text-center">
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

      <!-- Voucher: also what prints -->
      <article class="card pass overflow-hidden mt-10" style="--perf-bg:#F5F8FB" id="voucher">
        <div class="bg-ocean-700 text-white p-6 sm:p-7 flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-xs text-ocean-200 font-semibold">{{ __('site.confirmation.reference') }}</p>
            <p class="font-display font-bold text-3xl tabular tracking-wide" dir="ltr">{{ $booking->reference }}</p>
          </div>
          <div class="text-end">
            @if ($cancelled)
              <span class="inline-flex items-center gap-1.5 rounded-full bg-sun-50 text-sun-600 px-3 py-1 text-sm font-bold">{{ __('site.confirmation.cancelled') }}</span>
            @else
              <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E3F5EE] text-[#0E6B4C] px-3 py-1 text-sm font-bold"><i data-icon="check" class="w-4 h-4"></i> {{ __('site.confirmation.confirmed') }}</span>
            @endif
            <p class="text-sm text-ocean-100 mt-2">{{ __('site.confirmation.total') }} <b class="text-white tabular">{{ $money($booking->grand_total) }}</b></p>
          </div>
        </div>
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
        <div class="perf-x"></div>
        <div class="p-6 sm:p-7">
          <h3 class="font-bold mb-3">{{ __('site.booking.travellers') }}</h3>
          {{-- One group per cabin when the booking has several (step 15) --}}
          @foreach ($groups as [$heading, $travellers])
            @if ($heading)<p class="text-sm font-bold text-muted {{ $loop->first ? '' : 'mt-5' }} mb-2">{{ $heading }}</p>@endif
            <ul class="grid sm:grid-cols-2 gap-3">
              @foreach ($travellers as $t)
                <li class="rounded-xl bg-page p-3 flex items-center justify-between gap-3">
                  <span><span class="block font-bold">{{ $t->first_name }} {{ $t->last_name }}</span><span class="text-xs text-muted">{{ Bi::value('nationalities', $t->nationality) }}</span></span>
                  <span class="tag tag-ocean">{{ $bandLabels[$t->band->value] ?? ucfirst($t->band->value) }}</span>
                </li>
              @endforeach
            </ul>
          @endforeach
        </div>
      </article>

      <div class="mt-8 flex flex-wrap justify-center gap-3 no-print">
        <button class="btn btn-accent" onclick="window.print()"><i data-icon="download" class="w-5 h-5"></i> {{ __('site.confirmation.download') }}</button>
        <a href="{{ route('account') }}#bookings" class="btn btn-outline">{{ __('site.confirmation.view_in_account') }}</a>
        <a href="{{ route('home') }}" class="btn btn-ghost">{{ __('site.confirmation.home') }}</a>
      </div>
    </div>
  </main>
@endsection
