{{-- Ported from SC.renderFooter() in legacy-html/assets/js/core.js. The only links to safer.travel live here.
     Words come from lang/{en,ar}/site.php; brand names, phone numbers and the email stay as they are. --}}
@php
  $explore = [
      ['site.footer.all_cruises', route('cruises.index')],
      ['site.nav.ship', route('ship')],
      ['site.nav.destinations', route('destinations.index')],
      ['site.nav.my_bookings', route('account').'#bookings'],
  ];
  // #terms / #cancellation open the policy modal (core.js), so no page is left
  $help = [['site.footer.terms', '#terms'], ['site.footer.cancellation', '#cancellation']];
  $wa = 'https://wa.me/'.config('cruise.whatsapp_number').'?text='.rawurlencode(__('site.footer.whatsapp_message'));
@endphp
<footer class="bg-ocean-900 text-ocean-100 mt-auto">
  <div class="max-w-site mx-auto px-4 sm:px-6 py-14 grid gap-10 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
    <div>
      @include('partials.logo', ['light' => true])
      <p class="text-sm text-ocean-200 mt-5 max-w-sm leading-relaxed">@bi('site.footer.about')</p>
      <a href="https://safer.travel" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 mt-4 text-sm font-bold text-lagoon-400 hover:text-white">@bi('site.footer.visit') <i data-icon="external" class="w-4 h-4"></i></a>
      <div class="flex gap-2 mt-5">
        @foreach (['facebook', 'youtube', 'instagram'] as $s)
          <a href="https://www.{{ $s }}.com/{{ $s === 'youtube' ? '@' : '' }}SaferTourismOfficial" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-full border border-ocean-700 text-xs font-semibold text-ocean-100 hover:bg-ocean-800 capitalize">{{ $s }}</a>
        @endforeach
      </div>
    </div>
    @foreach (['site.footer.explore' => $explore, 'site.footer.help' => $help] as $title => $items)
      <div>
        <h3 class="font-display font-semibold text-white mb-4">@bi($title)</h3>
        <ul class="grid gap-2.5 text-sm">
          @foreach ($items as [$label, $href])
            <li><a class="text-ocean-200 hover:text-white transition" href="{{ $href }}">@bi($label)</a></li>
          @endforeach
        </ul>
      </div>
    @endforeach
    <div>
      <h3 class="font-display font-semibold text-white mb-4">@bi('site.footer.contact')</h3>
      <ul class="grid gap-3 text-sm text-ocean-200">
        {{-- dir="ltr" keeps "+90 …" in the right order on the Arabic layout --}}
        <li class="flex gap-2.5"><i data-icon="phone" class="w-4 h-4 mt-0.5 shrink-0"></i><span>@bi('site.footer.individuals') <a class="hover:text-white inline-block" href="tel:+905521501111" dir="ltr">+90 552 150 1111</a></span></li>
        <li class="flex gap-2.5"><i data-icon="phone" class="w-4 h-4 mt-0.5 shrink-0"></i><span>@bi('site.footer.companies') <a class="hover:text-white inline-block" href="tel:+905445019185" dir="ltr">+90 544 501 9185</a></span></li>
        <li class="flex gap-2.5"><i data-icon="mail" class="w-4 h-4 mt-0.5 shrink-0"></i><a class="hover:text-white" href="mailto:Info@Safer.Travel">Info@Safer.Travel</a></li>
        <li class="flex gap-2.5"><i data-icon="pin" class="w-4 h-4 mt-0.5 shrink-0"></i><span>@bi('site.footer.address')</span></li>
      </ul>
    </div>
  </div>
  <div class="border-t border-ocean-800">
    <div class="max-w-site mx-auto px-4 sm:px-6 py-5 flex flex-col sm:flex-row gap-3 items-center justify-between text-xs text-ocean-300">
      <p>@bi('site.footer.rights', ['year' => now()->year])</p>
      <p>@bi('site.footer.prices_usd')</p>
    </div>
  </div>
</footer>
<a id="wa-fab" href="{{ $wa }}" target="_blank" rel="noopener" class="fixed z-40 bottom-5 end-5 h-14 ps-4 pe-5 rounded-full bg-[#1FAF5A] text-white font-bold shadow-lg shadow-black/20 inline-flex items-center gap-2 hover:bg-[#178A47] transition" aria-label="{{ __('site.footer.chat_label') }}"><i data-icon="chat" class="w-6 h-6"></i><span class="hidden sm:inline">@bi('site.footer.chat')</span></a>
