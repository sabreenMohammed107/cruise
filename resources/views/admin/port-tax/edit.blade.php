{{-- Admin → Pricing & offers → Port taxes (Admin\PortTaxSettingController, App\Services\PricingService::portTaxPerGuest()). --}}
@extends('layouts.admin')

@php
  $amount = rtrim(rtrim(number_format($portTax, 2, '.', ''), '0'), '.');
@endphp

@section('title', 'Port & departure taxes')

@section('content')
  <h1 class="font-display font-bold text-2xl sm:text-3xl">Port &amp; departure taxes</h1>
  <p class="text-muted mt-2 max-w-[75ch]">Added to every booking on top of the cabin fare, per paying guest: adults and
    children pay it, infants don't. It is never discounted (agent rates, offers and promo codes only apply to the fare).
    Guests see it as its own line on the cruise page, the booking page, their email and "My bookings"; visitors who
    show prices in EGP see it at the current rate. A change applies to new bookings only: existing bookings keep the
    amount of their booking day.</p>

  <div class="card p-5 sm:p-6 mt-6">
    <h2 class="font-display font-semibold text-lg">In use</h2>
    <p class="mt-3 font-display font-bold text-3xl text-ocean-800 tabular">${{ number_format($portTax, 2) }} <span class="text-base font-semibold text-muted">per guest</span></p>
    @if ($portTax > 0)
      <p class="text-sm text-muted mt-1 tabular">e.g. 2 adults + 1 child + 1 infant pay ${{ number_format($portTax * 3, 2) }}
        (EGP {{ number_format($portTax * 3 * $egpRate) }} at today's rate of {{ number_format($egpRate, 2) }}).</p>
    @else
      <p class="text-sm text-muted mt-1">Port taxes are off: bookings show the cabin fare only.</p>
    @endif
  </div>

  <form method="POST" action="{{ route('admin.port-tax.update') }}" class="card p-5 sm:p-6 mt-6" novalidate>
    @csrf @method('PUT')

    <div class="max-w-xs">
      <label class="label" for="f-port-tax">Amount per adult or child (USD)</label>
      <input id="f-port-tax" name="port_tax_per_guest" type="number" step="0.01" min="0" inputmode="decimal" dir="ltr"
             class="field tabular {{ $errors->has('port_tax_per_guest') ? 'is-invalid' : '' }}"
             value="{{ old('port_tax_per_guest', $amount) }}">
      @if ($e = $errors->first('port_tax_per_guest'))
        <p class="err show">{{ $e }}</p>
      @else
        <p class="text-xs text-muted mt-1.5">0 turns port taxes off. Default: ${{ number_format((float) config('cruise.port_tax_per_guest'), 2) }}.</p>
      @endif
    </div>

    <div class="mt-8 flex flex-wrap justify-end gap-3">
      <a href="{{ route('cruises.index') }}" target="_blank" class="btn btn-outline"><i data-icon="external" class="w-4 h-4"></i> View site</a>
      <button class="btn btn-primary">Save changes</button>
    </div>
  </form>
@endsection
