@extends('layouts.admin')
@use('App\Support\Format')

@section('title', 'Cruises archive')

@section('content')
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">Archive</h1>
      <p class="text-muted mt-1 max-w-[75ch]">Sailings that are already back (return date before today), one year at a time. They move here by themselves the day after they return; the cruises list keeps everything still to come.</p>
    </div>
    <a href="{{ route('admin.cruises.index') }}" class="btn btn-outline btn-sm"><i data-icon="ship" class="w-4 h-4"></i> Cruises list</a>
  </div>

  @if ($years)
    @include('admin.cruises.filters', ['years' => $years, 'yearRequired' => true])
  @endif

  @if ($finishedCruises)
    <p class="mt-3 text-sm text-muted">{{ $finishedCruises }} {{ Str::plural('cruise', $finishedCruises) }} {{ $finishedCruises === 1 ? 'has' : 'have' }} no sailing left and {{ $finishedCruises === 1 ? 'is' : 'are' }} not in the cruises list. To run one again, open its sailings and add a date.</p>
  @endif

  <div class="card overflow-hidden mt-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr>
            <th class="text-start font-semibold p-3">Sailing</th>
            <th class="text-start font-semibold p-3">Cruise</th>
            <th class="text-start font-semibold p-3">Dates</th>
            <th class="text-start font-semibold p-3">Departure port</th>
            <th class="text-start font-semibold p-3">Nights</th>
            <th class="text-end font-semibold p-3">Bookings</th>
            <th class="text-start font-semibold p-3">Status</th>
            <th class="p-3"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($sailings as $s)
            <tr class="border-t border-line hover:bg-page">
              <td class="p-3 font-bold tabular">{{ $s->code }}</td>
              <td class="p-3">
                {{ $s->cruise->name }}
                @if ($s->cruise->trashed())<span class="tag tag-white ms-1">Deleted</span>@endif
              </td>
              <td class="p-3 whitespace-nowrap">{{ Format::date($s->departure_date, 'j M') }} – {{ Format::date($s->return_date) }}</td>
              <td class="p-3">{{ $s->cruise->departure_port }}</td>
              <td class="p-3 tabular">{{ $s->cruise->nights }}</td>
              <td class="p-3 text-end tabular">{{ $s->confirmed_bookings_count }}</td>
              <td class="p-3">@include('admin.partials.badge', ['status' => $s->status])</td>
              <td class="p-3 text-end whitespace-nowrap">
                @unless ($s->cruise->trashed())
                  <a href="{{ route('admin.cruises.sailings.index', $s->cruise) }}" class="btn btn-ghost btn-sm">Sailings</a>
                @endunless
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="p-8 text-center text-muted">{{ $years ? 'No finished sailings match these filters.' : 'No sailing has finished yet.' }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-5">{{ $sailings->links() }}</div>
@endsection
