{{-- Converted from legacy-html/confirmation.html. Rendered on the server from the saved booking
     (the template filled #ref, #trip and #people from sessionStorage). --}}
@extends('layouts.app')

@section('title', 'Booking confirmed | Safer Cruise')

@php
  $sailing = $booking->sailing;
  $cruise = $sailing->cruise;
  $money = fn ($n) => config('cruise.currency_symbol').number_format((float) $n);
  $guestText = collect([
      $booking->adults ? $booking->adults.' '.Str::plural('adult', $booking->adults) : null,
      $booking->children ? $booking->children.' '.($booking->children === 1 ? 'child' : 'children') : null,
      $booking->infants ? $booking->infants.' '.Str::plural('infant', $booking->infants) : null,
  ])->filter()->join(' + ');
  $cancelled = $booking->status === \App\Enums\BookingStatus::Cancelled;
@endphp

@section('content')
  <!-- Shown after the guest confirms. The site has no online payment; payment is arranged offline. -->
  <main class="flex-1 max-w-3xl w-full mx-auto px-4 sm:px-6 py-10 sm:py-14">
    <div id="success">
      <div class="text-center">
        <span class="inline-grid place-items-center w-16 h-16 rounded-full bg-[#E3F5EE] text-[#12805C]"><i data-icon="check" class="w-8 h-8"></i></span>
        <h1 class="font-display font-bold text-3xl sm:text-4xl mt-5">{{ $cancelled ? 'This booking was cancelled' : 'Your cruise is booked' }}</h1>
        @unless ($cancelled)
          <p class="text-muted mt-3 max-w-[52ch] mx-auto">We've sent your booking details to <b class="text-ink">{{ $booking->contact_email }}</b> and <b class="text-ink" dir="ltr">{{ $booking->contact_dial }} {{ $booking->contact_phone }}</b>. Our team will contact you within {{ config('cruise.payment_contact_hours') }} hours to arrange payment.</p>
        @endunless
      </div>

      <!-- Voucher: also what prints -->
      <article class="card pass overflow-hidden mt-10" style="--perf-bg:#F5F8FB" id="voucher">
        <div class="bg-ocean-700 text-white p-6 sm:p-7 flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-xs text-ocean-200 font-semibold">Booking reference</p>
            <p class="font-display font-bold text-3xl tabular tracking-wide">{{ $booking->reference }}</p>
          </div>
          <div class="text-end">
            @if ($cancelled)
              <span class="inline-flex items-center gap-1.5 rounded-full bg-sun-50 text-sun-600 px-3 py-1 text-sm font-bold">Cancelled</span>
            @else
              <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E3F5EE] text-[#0E6B4C] px-3 py-1 text-sm font-bold"><i data-icon="check" class="w-4 h-4"></i> Confirmed</span>
            @endif
            <p class="text-sm text-ocean-100 mt-2">Total <b class="text-white tabular">{{ $money($booking->grand_total) }}</b></p>
          </div>
        </div>
        <div class="p-6 sm:p-7">
          <h2 class="font-display font-semibold text-xl">{{ $cruise->tr('name') }}</h2>
          <dl class="grid grid-cols-2 sm:grid-cols-3 gap-5 mt-5">
            @foreach ([
                'Ship' => $cruise->ship->tr('name'),
                'Departs' => $sailing->departure_date->format('j M Y').', '.$cruise->departure_port,
                'Returns' => $sailing->return_date->format('j M Y').', '.$cruise->arrival_port,
                'Duration' => $cruise->nights.' nights',
                'Cabin' => app()->getLocale() === 'ar' ? ($booking->cabinCategory->name_ar ?: $booking->cabinCategory->name_en) : $booking->cabinCategory->name_en,
                'Guests' => $guestText,
            ] as $label => $value)
              <div><dt class="text-xs font-semibold text-muted">{{ $label }}</dt><dd class="font-bold mt-0.5">{{ $value }}</dd></div>
            @endforeach
          </dl>
        </div>
        <div class="perf-x"></div>
        <div class="p-6 sm:p-7">
          <h3 class="font-bold mb-3">Travellers</h3>
          <ul class="grid sm:grid-cols-2 gap-3">
            @foreach ($booking->travellers as $t)
              <li class="rounded-xl bg-page p-3 flex items-center justify-between gap-3">
                <span><span class="block font-bold">{{ $t->first_name }} {{ $t->last_name }}</span><span class="text-xs text-muted">{{ $t->nationality }}</span></span>
                <span class="tag tag-ocean">{{ ucfirst($t->band->value) }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </article>

      <div class="mt-8 flex flex-wrap justify-center gap-3 no-print">
        <button class="btn btn-accent" onclick="window.print()"><i data-icon="download" class="w-5 h-5"></i> Download voucher</button>
        <a href="{{ route('account') }}#bookings" class="btn btn-outline">View in my bookings</a>
        <a href="{{ route('home') }}" class="btn btn-ghost">Back to home</a>
      </div>
    </div>
  </main>
@endsection
