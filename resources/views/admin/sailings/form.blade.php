@extends('layouts.admin')
@use('App\Support\Format')

@php
  $title = $sailing ? 'Sailing '.$sailing->code : 'New sailing';
  $cabins = $cruise->ship->cabinCategories;
@endphp

@section('title', $title)

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb">
    <a href="{{ route('admin.cruises.index') }}" class="hover:text-ocean-600">Cruises</a> <span class="mx-1">/</span>
    <a href="{{ route('admin.cruises.sailings.index', $cruise) }}" class="hover:text-ocean-600">{{ $cruise->name }}</a> <span class="mx-1">/</span>
    <span class="text-ink">{{ $sailing?->code ?? 'New' }}</span>
  </nav>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $title }}</h1>
    @if ($sailing)
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('cruises.show', $cruise) }}?sailing={{ $sailing->code }}" target="_blank" class="btn btn-ghost btn-sm"><i data-icon="external" class="w-4 h-4"></i> View on site</a>
        <form method="POST" action="{{ route('admin.sailings.recalculate', $sailing) }}" onsubmit="return confirm('Replace all prices below with the rate card? Manual changes are lost.')">
          @csrf<button class="btn btn-outline btn-sm">Recalculate prices</button>
        </form>
        <form method="POST" action="{{ route('admin.sailings.destroy', $sailing) }}" onsubmit="return confirm('Delete sailing {{ $sailing->code }}?')">
          @csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-[#C42F2F]"><i data-icon="x" class="w-4 h-4"></i> Delete</button>
        </form>
      </div>
    @endif
  </div>

  <form method="POST" action="{{ $sailing ? route('admin.sailings.update', $sailing) : route('admin.cruises.sailings.store', $cruise) }}" class="mt-6 grid gap-6" novalidate>
    @csrf
    @if ($sailing) @method('PUT') @endif

    <section class="card p-5 sm:p-6 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div>
        <label class="label" for="code">Code</label>
        <input id="code" name="code" class="field tabular uppercase" value="{{ old('code', $sailing?->code) }}" placeholder="e.g. RSD-270320">
        <p class="text-xs text-muted mt-1.5">Shown to guests in the address (?sailing=).</p>
      </div>
      <div>
        <label class="label" for="departure_date">Departure date</label>
        <input id="departure_date" name="departure_date" type="date" class="field" value="{{ old('departure_date', $sailing?->departure_date?->toDateString()) }}">
        <p class="text-xs text-muted mt-1.5">Returns {{ $cruise->nights }} nights later.</p>
      </div>
      <div>
        <label class="label" for="price_factor">Price factor</label>
        <input id="price_factor" name="price_factor" type="number" step="0.01" class="field tabular" value="{{ old('price_factor', $sailing?->price_factor ?? '1.00') }}">
        <p class="text-xs text-muted mt-1.5">Season multiplier, e.g. 1.10 for peak dates.</p>
      </div>
      <div>
        <label class="label" for="status">Status</label>
        <select id="status" name="status" class="field">
          @foreach (\App\Enums\SailingStatus::cases() as $s)
            <option value="{{ $s->value }}" @selected(old('status', $sailing?->status?->value ?? 'open') === $s->value)>{{ ucfirst($s->value) }}</option>
          @endforeach
        </select>
        <p class="text-xs text-muted mt-1.5">Only open sailings can be booked.</p>
      </div>
    </section>

    @if ($sailing)
      <section class="card overflow-x-auto">
        <div class="p-5 sm:p-6 pb-0">
          <h2 class="font-display font-semibold text-lg">Prices</h2>
          <p class="text-sm text-muted">Per person, for the whole cruise, in US dollars. Rate card = cabin rate × {{ $cruise->nights }} nights × cruise factor × sailing factor (infants: rate × nights).</p>
        </div>
        <table class="w-full text-sm mt-4">
          <thead class="bg-ocean-50 text-ocean-800">
            <tr>
              <th class="text-start font-semibold p-3">Cabin</th>
              <th class="text-start font-semibold p-3">Adult</th>
              <th class="text-start font-semibold p-3">Child</th>
              <th class="text-start font-semibold p-3">Infant</th>
              <th class="text-start font-semibold p-3">Cabins available</th>
              <th class="text-start font-semibold p-3">Sold out</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($cabins as $cabin)
              @php($p = $prices->get($cabin->id))
              <tr class="border-t border-line">
                <td class="p-3 font-bold">{{ $cabin->name_en }}<p class="text-xs text-muted font-normal">Up to {{ $cabin->max_guests }} guests</p></td>
                @foreach (['adult', 'child', 'infant'] as $band)
                  <td class="p-3"><input name="prices[{{ $cabin->id }}][{{ $band }}]" type="number" step="0.01" min="0" class="field !py-2 tabular !w-28" aria-label="{{ $cabin->name_en }} {{ $band }} price"
                        value="{{ old("prices.{$cabin->id}.{$band}", $p ? (float) $p->{'price_'.$band} : '') }}"></td>
                @endforeach
                <td class="p-3"><input name="prices[{{ $cabin->id }}][capacity]" type="number" min="0" class="field !py-2 tabular !w-28" placeholder="No limit" aria-label="{{ $cabin->name_en }} cabins available"
                      value="{{ old("prices.{$cabin->id}.capacity", $p?->capacity) }}"></td>
                <td class="p-3">
                  <input type="hidden" name="prices[{{ $cabin->id }}][sold_out]" value="0">
                  <input type="checkbox" name="prices[{{ $cabin->id }}][sold_out]" value="1" class="check" aria-label="{{ $cabin->name_en }} sold out" @checked(old("prices.{$cabin->id}.sold_out", $p?->is_sold_out))>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <p class="p-5 text-xs text-muted">"Cabins available" stops online bookings once that many confirmed bookings exist for the cabin on this date. Leave empty for no limit.</p>
      </section>
    @else
      <p class="text-sm text-muted">Prices are filled from the cabin rate card when you create the sailing. You can adjust them on the next screen.</p>
    @endif

    <div class="flex flex-wrap justify-between gap-3">
      <a href="{{ route('admin.cruises.sailings.index', $cruise) }}" class="btn btn-outline">Back to sailings</a>
      <button class="btn btn-primary">{{ $sailing ? 'Save sailing' : 'Create sailing' }}</button>
    </div>
  </form>
@endsection
