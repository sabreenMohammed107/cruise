{{-- Year / month / departure port / nights (App\Admin\CruiseFilters), laid out like the bookings filters.
     Used by the cruises list (toolbar) and the archive. $years: the years offered; $yearRequired: the archive
     always shows one year, so it has no "All". $search: show the name search too. --}}
@use('App\Admin\CruiseFilters')
@php
  $yearRequired ??= false;
  $search ??= false;
  $filters ??= []; // the archive passes its defaulted year
  $current = request()->only(['q', 'year', 'month', 'port', 'nights']);
  $selected = fn (string $key, $value) => (string) ($filters[$key] ?? request($key)) === (string) $value;
@endphp
<form method="GET" class="mt-5 flex flex-wrap gap-2 items-end">
  @if ($search)
    <div class="w-full sm:w-64">
      <label for="q" class="label">Search</label>
      <input id="q" name="q" value="{{ request('q') }}" class="field !py-2" placeholder="Cruise name or URL slug">
    </div>
  @endif
  <div>
    <label for="year" class="label">Year</label>
    <select id="year" name="year" class="field !py-2 !w-auto">
      @unless ($yearRequired)<option value="">All</option>@endunless
      @foreach ($years as $y)
        <option value="{{ $y }}" @selected($selected('year', $y))>{{ $y }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label for="month" class="label">Month</label>
    <select id="month" name="month" class="field !py-2 !w-auto">
      <option value="">All</option>
      @foreach (CruiseFilters::months() as $m => $name)
        <option value="{{ $m }}" @selected($selected('month', $m))>{{ $name }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label for="port" class="label">Departure port</label>
    <select id="port" name="port" class="field !py-2 !w-auto">
      <option value="">All</option>
      @foreach (CruiseFilters::ports() as $p)
        <option value="{{ $p }}" @selected($selected('port', $p))>{{ $p }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label for="nights" class="label">Nights</label>
    <select id="nights" name="nights" class="field !py-2 !w-auto">
      <option value="">All</option>
      @foreach (CruiseFilters::nights() as $n)
        <option value="{{ $n }}" @selected($selected('nights', $n))>{{ $n }} {{ Str::plural('night', $n) }}</option>
      @endforeach
    </select>
  </div>
  <button class="btn btn-primary btn-sm">Filter</button>
  @if (array_filter($current))
    <a href="{{ url()->current() }}" class="btn btn-ghost btn-sm">Clear</a>
  @endif
</form>
@if ($errors->any())<p class="err show">{{ $errors->first() }}</p>@endif
