{{-- One text field in English and Arabic, side by side.
     $name (input name, Arabic gets "_ar"), $label, $model (record or null), optional $oldKey (dot path for old()),
     $rows (textarea when set), $required (English only). --}}
@php
  $oldKey ??= $name;
  $rows ??= null;
  $required ??= false;
  $id = 'f-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
  // "items[5][title]" → "items[5][title_ar]"; "title" → "title_ar"
  $nameAr = str_ends_with($name, ']') ? substr($name, 0, -1).'_ar]' : $name.'_ar';
  $attr = preg_replace('/^.*\[(\w+)\]$/', '$1', $name);
  $langs = [
      'en' => ['name' => $name, 'key' => $oldKey, 'value' => $model?->getAttribute($attr), 'label' => $label.' (English)'],
      'ar' => ['name' => $nameAr, 'key' => $oldKey.'_ar', 'value' => $model?->getAttribute($attr.'_ar'), 'label' => $label.' (عربي)'],
  ];
@endphp
<div class="sm:col-span-2 grid sm:grid-cols-2 gap-3">
  @foreach ($langs as $lang => $f)
    <div>
      <label class="label" for="{{ $id }}-{{ $lang }}">{{ $f['label'] }}</label>
      @if ($rows)
        <textarea id="{{ $id }}-{{ $lang }}" name="{{ $f['name'] }}" rows="{{ $rows }}" class="field {{ $errors->has($f['key']) ? 'is-invalid' : '' }}" @if ($lang === 'ar') dir="rtl" lang="ar" @endif>{{ old($f['key'], $f['value']) }}</textarea>
      @else
        <input id="{{ $id }}-{{ $lang }}" name="{{ $f['name'] }}" class="field {{ $errors->has($f['key']) ? 'is-invalid' : '' }}" value="{{ old($f['key'], $f['value']) }}" @if ($lang === 'ar') dir="rtl" lang="ar" @endif @if ($required && $lang === 'en') required @endif>
      @endif
      @if ($e = $errors->first($f['key']))<p class="err show">{{ $e }}</p>@endif
    </div>
  @endforeach
</div>
