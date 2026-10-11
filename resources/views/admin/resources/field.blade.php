{{-- One App\Admin\Field in the template's form style. --}}
@php
  $name = $field->name;
  $value = $field->display($record);
  $error = $errors->first($name) ?: $errors->first($name.'.*');
  $readonly = $record && $field->readonlyOnEdit;
  $invalid = $error ? 'is-invalid' : '';
@endphp
<div class="{{ $field->wide ? 'sm:col-span-2' : '' }}">
  @if ($field->type === 'checkbox')
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="flex items-center gap-3 cursor-pointer mt-6">
      <input type="checkbox" name="{{ $name }}" value="1" class="check" @checked($value)>
      <span class="font-semibold">{{ $field->label }}</span>
    </label>
  @else
    <label class="label" for="f-{{ $name }}">{{ $field->label }}</label>

    @switch($field->type)
      @case('textarea')
      @case('lines')
        <textarea id="f-{{ $name }}" name="{{ $name }}" rows="{{ $field->type === 'lines' ? 5 : 4 }}" class="field {{ $invalid }}" @readonly($readonly) @if ($field->rtl) dir="rtl" lang="ar" @endif>{{ $value }}</textarea>
        @break

      @case('select')
        <select id="f-{{ $name }}" name="{{ $name }}" class="field {{ $invalid }}" @disabled($readonly)>
          @foreach ($field->options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
          @endforeach
        </select>
        @break

      @case('checkboxes')
        <div class="flex flex-wrap gap-x-5 gap-y-2 mt-1">
          @foreach ($field->options as $optValue => $optLabel)
            <label class="flex items-center gap-2 cursor-pointer text-sm">
              <input type="checkbox" name="{{ $name }}[]" value="{{ $optValue }}" class="check" @checked(in_array($optValue, (array) $value, true))> {{ $optLabel }}
            </label>
          @endforeach
        </div>
        @break

      @case('image_slot')
        @php($current = $slotOptions->firstWhere('key', $value))
        <div class="flex gap-3 items-start">
          <div class="w-20 h-14 shrink-0 rounded-lg overflow-hidden bg-ocean-50">@if ($current && $current->url())<img src="{{ $current->url() }}" alt="" class="w-full h-full object-cover">@endif</div>
          <select id="f-{{ $name }}" name="{{ $name }}" class="field {{ $invalid }}">
            <option value="">— No image —</option>
            @foreach ($slotOptions as $slot)
              <option value="{{ $slot->key }}" @selected($value === $slot->key)>{{ $slot->label }} ({{ $slot->key }})</option>
            @endforeach
          </select>
        </div>
        {{-- Direct upload: saved as a new photo slot and used here instead of the choice above --}}
        <label class="block text-xs font-semibold text-muted mt-3" for="f-{{ $name }}-upload">…or upload a new photo</label>
        <input id="f-{{ $name }}-upload" type="file" name="{{ $name }}_upload" accept="image/jpeg,image/png,image/webp" class="mt-1 text-sm file:me-3 file:rounded-full file:border-0 file:bg-ocean-50 file:px-3 file:py-1.5 file:font-semibold file:text-ocean-700">
        @if ($e = $errors->first($name.'_upload'))<p class="err show">{{ $e }}</p>@endif
        @break

      @case('image_slots')
        @php($chosen = array_values(array_filter((array) $value)))
        {{-- Current photos in order --}}
        @if ($chosen)
          <div class="flex flex-wrap gap-2 mb-3" aria-label="{{ $field->label }}: chosen photos">
            @foreach ($chosen as $i => $key)
              @php($slot = $slotOptions->firstWhere('key', $key))
              <figure class="w-24">
                <div class="w-24 h-16 rounded-lg overflow-hidden bg-ocean-50 relative">
                  @if ($slot && $slot->url())<img src="{{ $slot->url() }}" alt="" class="w-full h-full object-cover">@endif
                  <span class="absolute top-1 start-1 rounded-full bg-ocean-900/70 text-white text-[10px] font-bold px-1.5">{{ $i + 1 }}</span>
                </div>
              </figure>
            @endforeach
          </div>
        @endif
        <div class="grid sm:grid-cols-2 gap-2">
          @for ($i = 0; $i < max($field->slots, count($chosen)); $i++)
            <select name="{{ $name }}[]" class="field !py-2 text-sm {{ $invalid }}" aria-label="{{ $field->label }} photo {{ $i + 1 }}">
              <option value="">{{ $i + 1 }}. — none —</option>
              @foreach ($slotOptions as $slot)
                <option value="{{ $slot->key }}" @selected(($chosen[$i] ?? null) === $slot->key)>{{ $i + 1 }}. {{ $slot->label }}</option>
              @endforeach
            </select>
          @endfor
        </div>
        {{-- Direct upload: each file becomes a new photo slot, added after the photos chosen above --}}
        <div class="mt-3 rounded-xl border border-dashed border-ocean-300 bg-ocean-50/40 p-3">
          <label class="block text-sm font-semibold" for="f-{{ $name }}-upload">Upload new photos</label>
          <input id="f-{{ $name }}-upload" type="file" name="{{ $name }}_upload[]" multiple accept="image/jpeg,image/png,image/webp" class="mt-2 text-sm file:me-3 file:rounded-full file:border-0 file:bg-white file:px-3 file:py-1.5 file:font-semibold file:text-ocean-700">
          <p class="text-xs text-muted mt-1.5">You can pick several at once (JPG, PNG or WebP, up to 5 MB each). They are added at the end, in the order picked, and saved under Photo slots so you can reuse them.</p>
          @if ($e = $errors->first($name.'_upload') ?: $errors->first($name.'_upload.*'))<p class="err show">{{ $e }}</p>@endif
        </div>
        @break

      @default
        <input id="f-{{ $name }}" name="{{ $name }}" type="{{ $field->type }}" class="field {{ $invalid }}"
               @if ($field->type === 'number') step="{{ $field->options['step'] ?? '1' }}" @endif
               @if ($field->type !== 'password') value="{{ $value }}" @endif
               @if ($field->type === 'password') autocomplete="new-password" @endif
               @if ($field->rtl) dir="rtl" lang="ar" @endif
               @readonly($readonly)>
    @endswitch
  @endif

  @if ($error)
    <p class="err show">{{ $error }}</p>
  @elseif ($field->help)
    <p class="text-xs text-muted mt-1.5">{{ $field->help }}</p>
  @endif
</div>
