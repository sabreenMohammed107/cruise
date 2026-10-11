@extends('layouts.admin')

@section('title', 'Bookings')

@section('content')
  <h1 class="font-display font-bold text-2xl sm:text-3xl">Bookings</h1>

  <form method="GET" class="mt-5 flex flex-wrap gap-2 items-end">
    <div class="w-full sm:w-72">
      <label for="q" class="label">Search</label>
      <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="field !py-2" placeholder="Reference, email, phone or name">
    </div>
    <div>
      <label for="status" class="label">Booking</label>
      <select id="status" name="status" class="field !py-2 !w-auto">
        <option value="">All</option>
        @foreach (\App\Enums\BookingStatus::cases() as $s)
          <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ ucfirst($s->value) }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="payment" class="label">Payment</label>
      <select id="payment" name="payment" class="field !py-2 !w-auto">
        <option value="">All</option>
        @foreach (\App\Enums\PaymentStatus::cases() as $s)
          <option value="{{ $s->value }}" @selected(($filters['payment'] ?? '') === $s->value)>{{ \App\Support\Format::badge($s)[1] }}</option>
        @endforeach
      </select>
    </div>
    <button class="btn btn-primary btn-sm">Filter</button>
    @if (array_filter($filters))
      <a href="{{ route('admin.bookings.index') }}" class="btn btn-ghost btn-sm">Clear</a>
    @endif
  </form>

  <div class="card overflow-hidden mt-5">
    @include('admin.bookings.table', ['bookings' => $bookings])
  </div>
  <div class="mt-5">{{ $bookings->links() }}</div>
@endsection
