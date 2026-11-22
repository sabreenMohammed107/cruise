{{-- Route ports list: name search + "Port type" filter (PortResource::filter()). --}}
@use('App\Models\Port')
<form method="GET" class="mt-5 flex flex-wrap gap-2 items-end">
  <div class="w-full sm:w-64">
    <label for="q" class="label">Search</label>
    <input id="q" name="q" value="{{ request('q') }}" class="field !py-2" placeholder="Search…">
  </div>
  <div>
    <label for="role" class="label">Port type</label>
    <select id="role" name="role" class="field !py-2 !w-auto" onchange="this.form.submit()">
      <option value="">All</option>
      @foreach (Port::ROLES as $value => $label)
        <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </div>
  <button class="btn btn-outline btn-sm">Search</button>
  @if (array_filter(request()->only(['q', 'role'])))
    <a href="{{ route('admin.routes.index') }}" class="btn btn-ghost btn-sm">Clear</a>
  @endif
</form>
