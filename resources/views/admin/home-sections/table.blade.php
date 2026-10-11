{{-- Sections of one page in page order: photo, name, heading in both languages, shown/hidden, edit link. --}}
<div class="card overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-start text-muted border-b border-line">
      <tr>
        <th class="text-start font-semibold p-4 w-24">Photo</th>
        <th class="text-start font-semibold p-4">Section</th>
        <th class="text-start font-semibold p-4">Heading</th>
        <th class="text-start font-semibold p-4">Shown</th>
        <th class="p-4"><span class="sr-only">Actions</span></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-line">
      @foreach ($sections as $section)
        @php
          // Sections without an editable heading (e.g. the ship hero) are listed by their main button
          $field = $section->supports('title') ? 'title' : 'primary_label';
        @endphp
        <tr>
          <td class="p-4">
            @if ($section->supports('image') && ($url = $section->imageUrl()))
              <img src="{{ $url }}" alt="" class="w-20 h-12 rounded-lg object-cover bg-ocean-50">
            @else
              <span class="text-muted">—</span>
            @endif
          </td>
          <td class="p-4 font-bold">{{ $section->name() }}</td>
          <td class="p-4">
            {{ $section->getAttribute($field) }}
            @if ($section->getAttribute($field.'_ar'))
              <span class="block text-muted" lang="ar" dir="rtl">{{ $section->getAttribute($field.'_ar') }}</span>
            @elseif ($section->getAttribute($field))
              <span class="block text-xs text-sun-600">No Arabic yet</span>
            @endif
            @if ($section->supports('items'))
              <span class="block text-xs text-muted">{{ $section->items->count() }} {{ $section->items->count() === 1 ? 'item' : 'items' }}</span>
            @endif
          </td>
          <td class="p-4">
            @if ($section->is_visible)
              <span class="tag tag-lagoon">Shown</span>
            @else
              <span class="tag tag-sun">Hidden</span>
            @endif
          </td>
          <td class="p-4 text-end"><a href="{{ $section->editUrl() }}" class="btn btn-outline btn-sm">Edit</a></td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
