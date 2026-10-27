{{-- Honeypot for public forms (App\Http\Requests\PublicFormRequest): hidden from people and screen readers,
     so only bots fill it; their submissions are answered as usual but not saved. --}}
@use('App\Http\Requests\PublicFormRequest')
<div class="sr-only" aria-hidden="true">
  <label for="{{ PublicFormRequest::HONEYPOT }}">{{ __('pages.honeypot') }}</label>
  <input id="{{ PublicFormRequest::HONEYPOT }}" name="{{ PublicFormRequest::HONEYPOT }}" type="text" value="" tabindex="-1" autocomplete="off">
</div>
