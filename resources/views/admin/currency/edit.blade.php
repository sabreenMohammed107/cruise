{{-- Admin → Pricing & offers → Currency (Admin\CurrencySettingController, App\Services\CurrencyService). --}}
@extends('layouts.admin')

@php
  $sources = [
      'api' => ['tag-lagoon', 'Live from the API'],
      'last_known' => ['tag-sun', 'API unavailable: last rate fetched'],
      'manual' => ['tag-sun', 'API unavailable: your rate below'],
  ];
  [$tagClass, $tagLabel] = $sources[$live['source']];
  $mode = old('currency_mode', $mode);
@endphp

@section('title', 'Currency')

@section('content')
  <h1 class="font-display font-bold text-2xl sm:text-3xl">Currency</h1>
  <p class="text-muted mt-2 max-w-[75ch]">Visitors can show the website's prices in US dollars or Egyptian pounds (the
    USD / EGP switch in the header). Prices are entered, stored and booked in USD; EGP prices are the USD price × the
    rate below, for display only. A booking made in EGP keeps the rate of its booking day: the guest's voucher, email
    and "My bookings" show EGP at that rate. The admin panel and the team email always show USD.</p>

  <div class="card p-5 sm:p-6 mt-6">
    <h2 class="font-display font-semibold text-lg">Rate in use</h2>
    <p class="mt-3 font-display font-bold text-3xl text-ocean-800 tabular">1 USD = {{ number_format($activeRate, 2) }} EGP</p>
    <p class="text-sm text-muted mt-1 tabular">e.g. $1,000 shows as EGP {{ number_format(1000 * $activeRate) }}</p>

    <div class="mt-5 pt-5 border-t border-line flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="font-semibold">Live rate <span class="tag {{ $tagClass }} ms-1">{{ $tagLabel }}</span></p>
        <p class="tabular mt-1">1 USD = {{ number_format($live['rate'], 4) }} EGP</p>
        <p class="text-xs text-muted mt-1">
          @if ($live['fetched_at'])
            Fetched {{ \Illuminate\Support\Carbon::parse($live['fetched_at'])->diffForHumans() }}; kept for {{ config('services.exchange_rate.cache_hours') }} hours.
          @else
            Never fetched successfully.
          @endif
          Source: ExchangeRate-API{{ config('services.exchange_rate.key') ? '' : ' (free open access)' }}.
        </p>
        @if ($live['error'])
          <p class="text-xs text-[#8E1F1F] mt-1">Last error: {{ $live['error'] }}</p>
        @endif
      </div>
      <form method="POST" action="{{ route('admin.currency.refresh') }}">
        @csrf
        <button class="btn btn-outline btn-sm">Fetch live rate now</button>
      </form>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.currency.update') }}" class="card p-5 sm:p-6 mt-6" novalidate>
    @csrf @method('PUT')

    <fieldset>
      <legend class="font-display font-semibold text-lg">Where the EGP rate comes from</legend>
      <div class="mt-4 grid sm:grid-cols-2 gap-3">
        @foreach (['api' => ['Automatic (live rate)', 'Updated from ExchangeRate-API about every '.config('services.exchange_rate.cache_hours').' hours.'], 'manual' => ['Manual (my rate)', 'Always the rate you enter below.']] as $value => [$label, $hint])
          <label class="flex gap-3 rounded-2xl border p-4 cursor-pointer {{ $mode === $value ? 'border-ocean-500 bg-ocean-50' : 'border-line' }}">
            <input type="radio" name="currency_mode" value="{{ $value }}" class="mt-1" @checked($mode === $value)>
            <span><span class="font-semibold block">{{ $label }}</span><span class="text-sm text-muted">{{ $hint }}</span></span>
          </label>
        @endforeach
      </div>
      @if ($e = $errors->first('currency_mode'))<p class="err show">{{ $e }}</p>@endif
    </fieldset>

    <div class="mt-6 max-w-xs">
      <label class="label" for="f-rate">Manual rate (EGP per 1 USD)</label>
      <input id="f-rate" name="manual_exchange_rate" type="number" step="0.0001" min="0.0001" inputmode="decimal" dir="ltr"
             class="field tabular {{ $errors->has('manual_exchange_rate') ? 'is-invalid' : '' }}"
             value="{{ old('manual_exchange_rate', rtrim(rtrim(number_format($manualRate, 4, '.', ''), '0'), '.')) }}">
      @if ($e = $errors->first('manual_exchange_rate'))
        <p class="err show">{{ $e }}</p>
      @else
        <p class="text-xs text-muted mt-1.5">Used in manual mode, and in automatic mode whenever the live rate has never been fetched.</p>
      @endif
    </div>

    <div class="mt-8 flex flex-wrap justify-end gap-3">
      <a href="{{ route('home') }}" target="_blank" class="btn btn-outline"><i data-icon="external" class="w-4 h-4"></i> View site</a>
      <button class="btn btn-primary">Save changes</button>
    </div>
  </form>
@endsection
