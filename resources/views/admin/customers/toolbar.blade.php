{{-- Customers list: search, filters and the export of exactly what is listed (CustomerResource::filter()). --}}
@php($active = array_filter(request()->only(['q', 'nationality', 'bookings', 'from', 'to'])))
<form method="GET" class="mt-5 flex flex-wrap gap-2 items-end">
  <div class="w-full sm:w-64">
    <label for="q" class="label">Search</label>
    <input id="q" name="q" value="{{ request('q') }}" class="field !py-2" placeholder="Name, email or phone">
  </div>
  <div>
    <label for="nationality" class="label">Nationality</label>
    <select id="nationality" name="nationality" class="field !py-2 !w-auto">
      <option value="">All</option>
      @foreach (config('cruise.nationalities') as $n)
        <option value="{{ $n }}" @selected(request('nationality') === $n)>{{ $n }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label for="bookings" class="label">Bookings</label>
    <select id="bookings" name="bookings" class="field !py-2 !w-auto">
      <option value="">All</option>
      <option value="with" @selected(request('bookings') === 'with')>Has bookings</option>
      <option value="without" @selected(request('bookings') === 'without')>No bookings yet</option>
    </select>
  </div>
  <div>
    <label for="from" class="label">Registered from</label>
    <input id="from" type="date" name="from" value="{{ request('from') }}" class="field !py-2 !w-auto">
  </div>
  <div>
    <label for="to" class="label">to</label>
    <input id="to" type="date" name="to" value="{{ request('to') }}" class="field !py-2 !w-auto">
  </div>
  <button class="btn btn-primary btn-sm">Filter</button>
  @if ($active)
    <a href="{{ route('admin.customers.index') }}" class="btn btn-ghost btn-sm">Clear</a>
  @endif
  <a href="{{ route('admin.customers.export', $active) }}" class="btn btn-outline btn-sm sm:ms-auto">
    <i data-icon="download" class="w-4 h-4"></i> Export to Excel
  </a>
</form>
@if ($errors->any())<p class="err show">{{ $errors->first() }}</p>@endif
