{{-- Mobile number with the site's phone widget (flag + country code, like the booking and account forms), for
     server-rendered forms (App\Http\Requests\PublicFormRequest): the code goes in "dial", the number in "phone".
     Page needs @include('partials.phone-widget') and assets/js/phone-fields.js in its scripts.
     Without JavaScript it is a plain number field and the code stays +966 (Saudi Arabia). --}}
@php
  $error = $errors->first('phone') ?: $errors->first('dial');
@endphp
<div class="{{ $span ?? '' }}">
  <label class="label" for="phone">{{ $label }}</label>
  <div dir="ltr">
    <input type="hidden" name="dial" value="{{ old('dial', '+966') }}" data-phone-dial>
    <input id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" autocomplete="tel-national" data-phone-field
           class="field tabular {{ $error ? 'is-invalid' : '' }}" @if ($error) aria-invalid="true" aria-describedby="phone-err" @endif>
  </div>
  @if ($error)
    <p class="err show" id="phone-err">{{ $error }}</p>
  @endif
</div>
