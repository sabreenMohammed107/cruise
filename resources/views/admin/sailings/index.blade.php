@extends('layouts.admin')
@use('App\Support\Format')

@section('title', 'Sailings: '.$cruise->name)

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb">
    <a href="{{ route('admin.cruises.index') }}" class="hover:text-ocean-600">Cruises</a> <span class="mx-1">/</span>
    <a href="{{ route('admin.cruises.edit', $cruise->id) }}" class="hover:text-ocean-600">{{ $cruise->name }}</a> <span class="mx-1">/</span>
    <span class="text-ink">Sailings</span>
  </nav>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $cruise->name }}: sailings</h1>
      <p class="text-muted mt-1">{{ $cruise->nights }} nights · price factor × {{ $cruise->price_factor }}</p>
    </div>
    <a href="{{ route('admin.cruises.sailings.create', $cruise) }}" class="btn btn-primary btn-sm"><i data-icon="plus" class="w-4 h-4"></i> New sailing</a>
  </div>

  <div class="card mt-6 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          <th class="text-start font-semibold p-3">Code</th>
          <th class="text-start font-semibold p-3">Departs</th>
          <th class="text-start font-semibold p-3">Returns</th>
          <th class="text-end font-semibold p-3">Factor</th>
          <th class="text-start font-semibold p-3">Adult price per cabin</th>
          <th class="text-end font-semibold p-3">Bookings</th>
          <th class="text-start font-semibold p-3">Status</th>
          <th class="p-3"><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($sailings as $s)
          <tr class="border-t border-line align-top {{ $s->departure_date->isPast() ? 'text-muted' : '' }}">
            <td class="p-3 font-bold tabular">{{ $s->code }}</td>
            <td class="p-3 whitespace-nowrap">{{ Format::date($s->departure_date, 'D j M Y') }}</td>
            <td class="p-3 whitespace-nowrap">{{ Format::date($s->return_date, 'D j M Y') }}</td>
            <td class="p-3 text-end tabular">× {{ $s->price_factor }}</td>
            <td class="p-3">
              <div class="flex flex-wrap gap-1.5">
                @foreach ($s->cabinPrices->sortBy('cabinCategory.sort_order') as $p)
                  <span class="tag {{ $p->is_sold_out ? 'tag-sun' : 'tag-white' }}" title="{{ $p->is_sold_out ? 'Sold out' : '' }}">{{ $p->cabinCategory->name_en }}: {{ Format::money($p->price_adult) }}{{ $p->is_sold_out ? ' · sold out' : '' }}</span>
                @endforeach
              </div>
            </td>
            <td class="p-3 text-end tabular">{{ $s->booked_count }}</td>
            <td class="p-3">@include('admin.partials.badge', ['status' => $s->status])</td>
            <td class="p-3 text-end"><a href="{{ route('admin.sailings.edit', $s) }}" class="btn btn-outline btn-sm"><i data-icon="edit" class="w-4 h-4"></i> Edit</a></td>
          </tr>
        @empty
          <tr><td colspan="8" class="p-8 text-center text-muted">No sailings yet. Add the first departure.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
