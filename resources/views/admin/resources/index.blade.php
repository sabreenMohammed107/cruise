@extends('layouts.admin')
@use('App\Support\Format')

@section('title', $resource::$label)

@section('content')
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $resource::$label }}</h1>
    @if ($resource::canCreate())
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
                @if ($field->type === 'checkbox')
                  <span class="tag {{ $value ? 'tag-lagoon' : 'tag-white' }}">{{ $value ? 'Yes' : 'No' }}</span>
                @elseif ($field->type === 'select')
                  {{ $field->options[$value instanceof \BackedEnum ? $value->value : $value] ?? $value }}
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
                <a href="{{ route($resource::routeName('edit'), $record->getKey()) }}" class="btn btn-outline btn-sm"><i data-icon="edit" class="w-4 h-4"></i> Edit</a>
                @if ($resource::canDelete($record))
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
