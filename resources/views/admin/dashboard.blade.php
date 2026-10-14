@extends('layouts.admin')
@use('App\Support\Format')

@section('title', 'Dashboard')

@section('content')
  <h1 class="font-display font-bold text-2xl sm:text-3xl">Dashboard</h1>
  @if (auth()->user()->isSeller())
    <p class="mt-2"><span class="tag tag-ocean">Seller</span> <span class="text-muted">These figures cover the bookings assigned to you.</span></p>
  @endif
  <p class="text-muted mt-1">Bookings are confirmed online; payment is arranged by the team within {{ config('cruise.payment_contact_hours') }} hours.</p>

  <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-6">
    @foreach ([
        ['ticket', $stats['newThisWeek'], 'New bookings, last 7 days'],
        ['alert', $stats['awaitingCount'], 'Awaiting payment'],
        ['card', Format::money($stats['outstanding']), 'Outstanding'],
        ['check', Format::money($stats['collectedThisMonth']), 'Fully paid this month'],
    ] as [$icon, $value, $label])
      <div class="card p-5 flex items-center gap-4">
        <span class="grid place-items-center w-11 h-11 rounded-xl bg-ocean-50 text-ocean-600"><i data-icon="{{ $icon }}" class="w-5 h-5"></i></span>
        <div><p class="font-display font-bold text-2xl tabular">{{ $value }}</p><p class="text-sm text-muted">{{ $label }}</p></div>
      </div>
    @endforeach
  </div>

  <div class="grid {{ $sailings ? 'xl:grid-cols-[1.4fr_1fr]' : '' }} gap-6 mt-8 items-start">
    <section class="card overflow-hidden">
      <div class="p-5 flex items-center justify-between gap-3">
        <h2 class="font-display font-semibold text-lg">Awaiting payment</h2>
        <a href="{{ route('admin.bookings.index', ['status' => 'confirmed', 'payment' => 'unpaid']) }}" class="text-sm font-bold text-ocean-600 hover:underline">All unpaid</a>
      </div>
      @include('admin.bookings.table', ['bookings' => $awaiting, 'empty' => 'Every confirmed booking is paid.'])
    </section>

    @if ($sailings)
    <section class="card overflow-hidden">
      <h2 class="font-display font-semibold text-lg p-5">Next departures</h2>
      <ul class="divide-y divide-line">
        @forelse ($sailings as $s)
          <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
            <div>
              <a href="{{ route('admin.sailings.edit', $s) }}" class="font-bold hover:text-ocean-600">{{ $s->cruise->name }}</a>
              <p class="text-muted">{{ Format::date($s->departure_date, 'D j M Y') }} · {{ $s->code }}</p>
            </div>
            <div class="text-end">
              <p class="font-bold tabular">{{ $s->booked_count }} {{ Str::plural('booking', $s->booked_count) }}</p>
              <p class="text-muted tabular">{{ (int) $s->adults_sum + (int) $s->children_sum + (int) $s->infants_sum }} guests</p>
            </div>
          </li>
        @empty
          <li class="p-5 text-muted">No upcoming sailings.</li>
        @endforelse
      </ul>
    </section>
    @endif
  </div>

  <section class="card overflow-hidden mt-6">
    <div class="p-5 flex items-center justify-between gap-3">
      <h2 class="font-display font-semibold text-lg">Latest bookings</h2>
      <a href="{{ route('admin.bookings.index') }}" class="text-sm font-bold text-ocean-600 hover:underline">All bookings</a>
    </div>
    @include('admin.bookings.table', ['bookings' => $recent, 'empty' => 'No bookings yet.'])
  </section>
@endsection
