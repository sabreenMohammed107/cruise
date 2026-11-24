@use('App\Reports\ReportFilters')
{{-- Report filters (ReportFilters): quick periods, then dates and the other filters. A GET form, so the URL is the
     filtered report. Dates changed by hand turn the period into a custom one. --}}
@php
  $route = fn (array $q) => route('admin.reports.show', [$class::key()] + $q);
  $keep = \Illuminate\Support\Arr::except($filters->query(), ['preset', 'from', 'to']);
  $uses = fn (string $filter) => in_array($filter, $class::filters(), true);
@endphp
<div class="card p-4 mt-5 no-print">
  <nav class="flex flex-wrap gap-2 text-sm" aria-label="Period">
    @foreach (ReportFilters::PRESETS as $value => $label)
      @continue($value === 'custom' && $filters->preset !== 'custom')
      @php($current = $filters->preset === $value)
      <a href="{{ $value === 'custom' ? '#report-from' : $route(['preset' => $value] + $keep) }}"
         @if ($current) aria-current="page" @endif
         class="rounded-full px-4 py-1.5 font-semibold border {{ $current ? 'bg-ocean-600 border-ocean-600 text-white' : 'border-line bg-white text-ink hover:border-ocean-600' }}">{{ $label }}</a>
    @endforeach
  </nav>

  <form method="GET" action="{{ $route([]) }}" class="mt-4 flex flex-wrap gap-2 items-end">
    <input type="hidden" name="preset" value="{{ $filters->preset }}">
    <div>
      <label for="report-from" class="label">From</label>
      <input id="report-from" type="date" name="from" value="{{ $filters->from->toDateString() }}" class="field !py-2 !w-auto">
    </div>
    <div>
      <label for="report-to" class="label">To</label>
      <input id="report-to" type="date" name="to" value="{{ $filters->to->toDateString() }}" class="field !py-2 !w-auto">
    </div>
    <div>
      <label for="report-compare" class="label">Compare with</label>
      <select id="report-compare" name="compare" class="field !py-2 !w-auto">
        @foreach (ReportFilters::COMPARE as $value => $label)
          <option value="{{ $value }}" @selected($filters->compare === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    @if ($uses('channel'))
    <div>
      <label for="report-channel" class="label">Channel</label>
      <select id="report-channel" name="channel" class="field !py-2 !w-auto">
        <option value="">All</option>
        <option value="b2c" @selected($filters->channel === 'b2c')>Website (B2C)</option>
        <option value="agent" @selected($filters->channel === 'agent')>Agents (B2B)</option>
      </select>
    </div>
    @endif
    @if ($uses('cruise'))
    <div>
      <label for="report-cruise" class="label">Cruise</label>
      <select id="report-cruise" name="cruise" class="field !py-2 !w-auto max-w-56">
        <option value="">All</option>
        @foreach ($options['cruises'] as $c)
          <option value="{{ $c->id }}" @selected($filters->cruise === $c->id)>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    @endif
    @if ($uses('sailing'))
    <div>
      <label for="report-sailing" class="label">Sailing</label>
      <select id="report-sailing" name="sailing" class="field !py-2 !w-auto max-w-64">
        <option value="">All</option>
        @foreach ($options['sailings'] as $s)
          <option value="{{ $s->id }}" @selected($filters->sailing === $s->id)>{{ $s->departure_date?->format('j M Y') }} · {{ $s->cruise?->name ?? $s->code }}</option>
        @endforeach
      </select>
    </div>
    @endif
    @if ($uses('seller') && $options['staff']->isNotEmpty())
      <div>
        <label for="report-seller" class="label">Assigned to</label>
        <select id="report-seller" name="seller" class="field !py-2 !w-auto max-w-56">
          <option value="">All</option>
          <option value="none" @selected($filters->seller === 'none')>Unassigned</option>
          @foreach ($options['staff'] as $u)
            <option value="{{ $u->id }}" @selected($filters->seller === (string) $u->id)>{{ $u->name }} ({{ ucfirst($u->role->value) }})</option>
          @endforeach
        </select>
      </div>
    @endif
    @if ($uses('agent') && $options['agents']->isNotEmpty())
      <div>
        <label for="report-agent" class="label">Agent</label>
        <select id="report-agent" name="agent" class="field !py-2 !w-auto max-w-56">
          <option value="">All</option>
          @foreach ($options['agents'] as $a)
            <option value="{{ $a->id }}" @selected($filters->agent === $a->id)>{{ $a->company_name ?: $a->name }}</option>
          @endforeach
        </select>
      </div>
    @endif
    <button class="btn btn-primary btn-sm">Apply</button>
    @if (array_diff_key($filters->query(), ['preset' => 1]) || $filters->preset !== $class::defaultPreset())
      <a href="{{ $route([]) }}" class="btn btn-ghost btn-sm">Reset</a>
    @endif
  </form>
  @if ($errors->any())<p class="err show">{{ $errors->first() }}</p>@endif
</div>
