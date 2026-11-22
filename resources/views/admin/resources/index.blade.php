@extends('layouts.admin')
@use('App\Support\Format')

@section('title', $resource::$label)

@section('content')
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $resource::$label }}</h1>
    @if ($resource::canCreate() && $resource::editable())
      <a href="{{ route($resource::routeName('create')) }}" class="btn btn-primary btn-sm"><i data-icon="plus" class="w-4 h-4"></i> New {{ strtolower($resource::$singular) }}</a>
    @endif
  </div>

  @if ($toolbar = $resource::indexToolbar())
    @include($toolbar)
  @elseif ($resource::$search)
    <form method="GET" class="mt-5 flex gap-2 max-w-md">
      <label for="q" class="sr-only">Search</label>
      <input id="q" name="q" value="{{ request('q') }}" class="field !py-2" placeholder="Search…">
      <button class="btn btn-outline btn-sm shrink-0">Search</button>
    </form>
  @endif

  <div class="card mt-5 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-ocean-50 text-ocean-800">
        <tr>
          @foreach ($resource::indexFields() as $field)
            <th class="text-start font-semibold p-3 whitespace-nowrap">{{ $field->label }}</th>
          @endforeach
          <th class="p-3"><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($records as $record)
          <tr class="border-t border-line align-top">
            @foreach ($resource::indexFields() as $field)
              @php($value = $record->getAttribute($field->name))
              <td class="p-3 {{ $loop->first ? 'font-bold' : '' }}">
                @if ($field->inline && ! $field->inlineFor($record))
                  {{-- Not used for this row (e.g. a port of call has no order) --}}
                  <span class="text-muted" title="Not used for this {{ strtolower($resource::$singular) }}">—</span>
                @elseif ($field->inline && $resource::editable())
                  {{-- Saved on its own when changed (Enter, leaving the box, or a short pause while typing) --}}
                  <span class="inline-flex items-center gap-1.5">
                    <input type="number" min="0" step="1" value="{{ $value }}" class="field !py-1 !px-2 !w-20 tabular"
                           aria-label="{{ $field->label }}: {{ $record->getAttribute($resource::$title) }}"
                           data-inline-save="{{ route($resource::routeName('inline'), $record->getKey()) }}" data-field="{{ $field->name }}">
                    <span data-inline-status class="w-4 h-4 shrink-0" aria-live="polite"></span>
                  </span>
                @elseif ($field->type === 'checkbox')
                  <span class="tag {{ $value ? 'tag-lagoon' : 'tag-white' }}">{{ $value ? 'Yes' : 'No' }}</span>
                @elseif ($field->type === 'select')
                  {{ $field->options[$value instanceof \BackedEnum ? $value->value : $value] ?? $value }}
                @elseif ($field->type === 'money')
                  <span class="tabular whitespace-nowrap">{{ Format::money($value ?? 0, 2) }}</span>
                @elseif ($value instanceof \DateTimeInterface)
                  {{ Format::date($value) }}
                @elseif ($field->type === 'date' && $field->indexOnly)
                  {{-- computed date column (e.g. a cruise's next sailing), selected as a plain string --}}
                  @if ($value)
                    @php($day = \Illuminate\Support\Carbon::parse($value))
                    @php($in = (int) today()->diffInDays($day))
                    <span class="whitespace-nowrap">{{ Format::date($day, 'D j M Y') }}</span>
                    <span class="block text-xs text-muted">{{ $in === 0 ? 'Today' : "In {$in} ".Str::plural('day', $in) }}</span>
                  @else
                    <span class="text-muted">None scheduled</span>
                  @endif
                @elseif (is_array($value))
                  {{ implode(', ', $value) }}
                @else
                  {{ Str::limit((string) $value, 60) }}
                @endif
              </td>
            @endforeach
            <td class="p-3">
              <div class="flex flex-wrap justify-end gap-1.5">
                @foreach ($resource::rowActions($record) as $label => $url)
                  <a href="{{ $url }}" class="btn btn-ghost btn-sm">{{ $label }}</a>
                @endforeach
                @if ($resource::editable())
                  <a href="{{ route($resource::routeName('edit'), $record->getKey()) }}" class="btn btn-outline btn-sm"><i data-icon="edit" class="w-4 h-4"></i> Edit</a>
                @endif
                {{-- Deleting: admins only (gate "delete-records") --}}
                @if ($resource::editable() && auth()->user()->can('delete-records') && $resource::canDelete($record))
                  <form method="POST" action="{{ route($resource::routeName('destroy'), $record->getKey()) }}"
                        onsubmit="return confirm('Delete {{ addslashes($resource::$singular) }} “{{ addslashes((string) $record->getAttribute($resource::$title)) }}”? This can\'t be undone.')">
                    @csrf @method('DELETE')
                    <button class="btn btn-ghost btn-sm text-[#C42F2F]"><i data-icon="x" class="w-4 h-4"></i> Delete</button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td class="p-8 text-center text-muted" colspan="{{ count($resource::indexFields()) + 1 }}">Nothing here yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-5">{{ $records->links() }}</div>
@endsection

@push('scripts')
  <script>
    // Number boxes in the table ([data-inline-save]) save themselves: PATCH {field, value}, a green tick when saved,
    // the server's message (and the last saved number back) when not.
    (() => {
      const boxes = document.querySelectorAll('[data-inline-save]');
      if (!boxes.length) return;
      const token = document.querySelector('meta[name="csrf-token"]').content;

      boxes.forEach((box) => {
        const status = box.parentElement.querySelector('[data-inline-status]');
        let saved = box.value, timer = null, busy = null;
        const show = (icon, cls, title) => { status.className = `w-4 h-4 shrink-0 ${cls}`; status.innerHTML = icon ? SC.icon(icon, 'w-4 h-4') : ''; status.title = title || ''; };

        const save = () => {
          clearTimeout(timer);
          const value = box.value.trim();
          if (value === saved || busy) return;
          show('', '');
          // A plain form POST with _method=PATCH (like the panel's forms): some hosts/firewalls refuse a real PATCH
          busy = fetch(box.dataset.inlineSave, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ _method: 'PATCH', _token: token, field: box.dataset.field, value }),
          })
            .then(async (r) => {
              const data = await r.json().catch(() => ({}));
              if (!r.ok) {
                const why = r.status === 419 ? 'Your session expired. Reload the page and try again.'
                  : (data.message || `Could not save (error ${r.status}). Reload the page and try again.`);
                throw new Error(why);
              }
              saved = data.value == null ? '' : String(data.value);
              if (box.value.trim() === value) box.value = saved; // not if more was typed meanwhile (saved next)
              box.classList.remove('is-invalid');
              show('check', 'text-lagoon-600', 'Saved');
              setTimeout(() => { if (status.title === 'Saved') show('', ''); }, 2500);
            })
            .catch((e) => {
              if (box.value.trim() === value) box.value = saved;
              box.classList.add('is-invalid');
              show('alert', 'text-[#C42F2F]', e.message);
              SC.toast(e.message, 'error');
            })
            .finally(() => { busy = null; if (box.value.trim() !== saved) save(); });
        };

        box.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(save, 800); });
        box.addEventListener('change', save);
        box.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); save(); } });
      });
    })();
  </script>
@endpush
