{{-- Form field in the template's style (.label / .field / .err). Server errors use the same .err.show line. --}}
@php
  $type = $type ?? 'text';
  $error = $errors->first($name);
@endphp
<div class="{{ $span ?? '' }}">
  <label class="label" for="{{ $name }}">{{ $label }}</label>
  {{-- Email addresses and passwords are typed left to right, also on the Arabic page --}}
  <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="field {{ $error ? 'is-invalid' : '' }}" @if (in_array($type, ['email', 'password'], true)) dir="ltr" @endif
         @if ($type !== 'password') value="{{ old($name, $value ?? '') }}" @endif
         @if (! empty($placeholder)) placeholder="{{ $placeholder }}" @endif
         autocomplete="{{ $autocomplete ?? 'off' }}" @if ($error) aria-invalid="true" aria-describedby="{{ $name }}-err" @endif>
  @if ($error)
    <p class="err show" id="{{ $name }}-err">{{ $error }}</p>
  @elseif (! empty($hint))
    <p class="text-xs text-muted mt-1.5">{{ $hint }}</p>
  @endif
</div>
