{{-- Ported from logo() in legacy-html/assets/js/core.js --}}
@php($light = $light ?? false)
<a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0" aria-label="{{ __('site.nav.logo') }}">
  <span class="grid place-items-center w-10 h-10 rounded-xl {{ $light ? 'bg-white text-ocean-700' : 'bg-ocean-600 text-white' }}"><i data-icon="ship" class="w-6 h-6"></i></span>
  <span class="leading-none">
    <span class="font-display font-bold text-[1.15rem] {{ $light ? 'text-white' : 'text-ocean-800' }}">Safer</span><span class="font-display font-bold text-[1.15rem] {{ $light ? 'text-lagoon-400' : 'text-lagoon-500' }}">Cruise</span>
    <span class="block text-[11px] font-semibold mt-1 {{ $light ? 'text-ocean-200' : 'text-muted' }}">by safer.travel</span>
  </span>
</a>
