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
      @case('image_slots')
        {{-- Media library: upload from the device or pick a photo already on the server --}}
        @include('admin.partials.media-picker', ['name' => $name, 'label' => $field->label, 'value' => $value, 'multiple' => $field->type === 'image_slots', 'slotOptions' => $slotOptions])
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
