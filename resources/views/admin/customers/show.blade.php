@extends('layouts.admin')
@use('App\Support\Format')

@section('title', 'Customer: '.$customer->name)

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb"><a href="{{ route('admin.customers.index') }}" class="hover:text-ocean-600">Customers</a> <span class="mx-1">/</span> <span class="text-ink">{{ $customer->name }}</span></nav>

  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $customer->name }}</h1>
      <p class="text-muted mt-1">{{ $customer->email }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
      @if ($customer->phone)
        <a href="https://wa.me/{{ ltrim((string) $customer->phone_dial, '+') }}{{ $customer->phone }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><i data-icon="chat" class="w-4 h-4"></i> WhatsApp</a>
      @endif
      <a href="mailto:{{ $customer->email }}" class="btn btn-outline btn-sm"><i data-icon="mail" class="w-4 h-4"></i> Email</a>
      @can('manage-users')
        <a href="{{ route('admin.customers.edit', $customer->id) }}" class="btn btn-primary btn-sm"><i data-icon="edit" class="w-4 h-4"></i> Edit</a>
      @endcan
    </div>
  </div>

  <div class="grid sm:grid-cols-3 gap-4 mt-6">
    @foreach ([
        'Bookings' => $customer->bookings_count,
        'Paid on confirmed bookings' => Format::money($spent),
        'Saved cruises' => $customer->favorites_count,
    ] as $label => $value)
      <div class="card p-5"><p class="text-xs font-semibold text-muted">{{ $label }}</p><p class="font-display font-bold text-2xl mt-1 tabular">{{ $value }}</p></div>
    @endforeach
  </div>

  <section class="card p-5 sm:p-6 mt-6">
    <h2 class="font-display font-semibold text-lg mb-4">Profile</h2>
    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-5 text-sm">
      @foreach ([
          'First name' => $customer->first_name,
          'Last name' => $customer->last_name,
          'Phone' => $customer->full_phone,
          'Nationality' => $customer->nationality,
          'Date of birth' => $customer->date_of_birth ? Format::date($customer->date_of_birth) : null,
          'Registered' => Format::date($customer->created_at, 'j M Y, H:i'),
          'Email verified' => $customer->email_verified_at ? 'Yes' : 'No',
      ] as $label => $value)
        <div><dt class="text-xs font-semibold text-muted">{{ $label }}</dt><dd class="font-bold mt-0.5 break-words">{{ $value ?: '—' }}</dd></div>
      @endforeach
    </dl>
  </section>

  <section class="mt-6">
    <h2 class="font-display font-semibold text-lg mb-3">Bookings</h2>
    <div class="card overflow-hidden">
      @include('admin.bookings.table', ['bookings' => $bookings, 'empty' => 'No bookings yet.'])
    </div>
  </section>
@endsection
