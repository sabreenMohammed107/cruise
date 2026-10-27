{{-- Cruises list (CruiseResource::filter()): only cruises still running; year and month choices are the years
     of sailings not back yet. Cruises whose sailings are all over are counted here and listed in the archive. --}}
@use('App\Admin\CruiseFilters')
@use('App\Models\Cruise')
@include('admin.cruises.filters', ['years' => CruiseFilters::currentYears(), 'search' => true])
@if ($finished = Cruise::finished()->count())
  <p class="mt-3 text-sm text-muted">
    {{ $finished }} {{ Str::plural('cruise', $finished) }} with every sailing over {{ $finished === 1 ? 'is' : 'are' }} not listed here.
    <a href="{{ route('admin.cruises.archive') }}" class="font-semibold text-ocean-700 hover:underline">See the archive</a>
  </p>
@endif
