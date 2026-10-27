{{-- A card that will be added on save. $n is the row index ("__N__" in the template), $values the old input. --}}
<div class="rounded-2xl border border-dashed border-ocean-300 bg-ocean-50/40 p-4 grid sm:grid-cols-[1fr_1fr_110px_90px] gap-3 items-start">
  @include('admin.home-sections.bi-field', ['name' => "new_items[$n][title]", 'oldKey' => "new_items.$n.title", 'label' => 'New card: title', 'model' => null])
  <div>
    <label class="label">Icon</label>
    <select name="new_items[{{ $n }}][icon]" class="field">
      @foreach ($icons as $icon)<option value="{{ $icon }}" @selected(($values['icon'] ?? null) === $icon)>{{ $icon }}</option>@endforeach
    </select>
  </div>
  <div>
    <label class="label">Order</label>
    <input type="number" min="0" name="new_items[{{ $n }}][sort_order]" class="field" value="{{ $values['sort_order'] ?? '' }}">
  </div>
  @include('admin.home-sections.bi-field', ['name' => "new_items[$n][text]", 'oldKey' => "new_items.$n.text", 'label' => 'Text', 'model' => null, 'rows' => 2])
  <div>
    <label class="label">Colour</label>
    <select name="new_items[{{ $n }}][tone]" class="field">
      @foreach ($tones as $tone => $toneLabel)<option value="{{ $tone }}" @selected(($values['tone'] ?? null) === $tone)>{{ $toneLabel }}</option>@endforeach
    </select>
  </div>
  @if ($section->config('item_links'))
    <div class="sm:col-span-4">
      <label class="label">Link (optional)</label>
      <input name="new_items[{{ $n }}][url]" class="field {{ $errors->has("new_items.$n.url") ? 'is-invalid' : '' }}" value="{{ $values['url'] ?? '' }}" placeholder="https://">
      @if ($e = $errors->first("new_items.$n.url"))<p class="err show">{{ $e }}</p>@endif
    </div>
  @endif
</div>
