{{-- USD / EGP switch of the header and mobile menu (CurrencyController, App\Services\CurrencyService). A plain form,
     so it works before core.js loads; the page reloads with prices in the chosen currency. --}}
@php($currency = app(\App\Services\CurrencyService::class))
<form method="POST" action="{{ route('currency.update') }}" class="{{ $class }} items-center gap-0.5 rounded-full border border-line p-0.5" dir="ltr" role="group" aria-label="{{ __('site.nav.currency') }}">
  @csrf
  @foreach ($currency->currencies() as $code)
    @php($on = $code === $currency->active())
    <button name="currency" value="{{ $code }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
            class="px-2.5 py-1 rounded-full text-xs font-bold transition {{ $on ? 'bg-ocean-700 text-white' : 'text-ink hover:text-ocean-600' }}">{{ $code }}</button>
  @endforeach
</form>
