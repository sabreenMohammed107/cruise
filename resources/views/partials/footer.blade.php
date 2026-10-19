{{-- Ported from SC.renderFooter() in legacy-html/assets/js/core.js. The only links to safer.travel live here.
     Description, website link, phones, email, address, WhatsApp and social links: Admin → Footer & contact
     (App\Models\SiteSetting, config/site.php). Headings and fixed words: lang/{en,ar}/site.php. --}}
@use('App\Models\SiteSetting')
@php
  $phones = collect([1, 2])->map(fn ($n) => ['label' => SiteSetting::text("phone_{$n}_label"), 'number' => SiteSetting::value("phone_{$n}")])
      ->filter(fn ($p) => $p['number'] !== '');
  $email = SiteSetting::value('email');
  $address = SiteSetting::text('address');
  $website = SiteSetting::value('website_url');
  $social = SiteSetting::socialLinks();
  $explore = [
      ['site.footer.all_cruises', route('cruises.index')],
      ['site.nav.ship', route('ship')],
      ['site.nav.destinations', route('destinations.index')],
      ['site.nav.my_bookings', route('account').'#bookings'],
  ];
  // #terms / #cancellation open the policy modal (core.js), so no page is left
  $help = [['site.footer.terms', '#terms'], ['site.footer.cancellation', '#cancellation']];
  $wa = 'https://wa.me/'.SiteSetting::whatsapp().'?text='.rawurlencode(__('site.footer.whatsapp_message'));
@endphp
<footer class="bg-ocean-900 text-ocean-100 mt-auto">
  <div class="max-w-site mx-auto px-4 sm:px-6 py-14 grid gap-10 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
    <div>
      @include('partials.logo', ['light' => true])
      @if (filled((string) SiteSetting::text('about')))
        <p class="text-sm text-ocean-200 mt-5 max-w-sm leading-relaxed">{{ SiteSetting::text('about') }}</p>
      @endif
      @if ($website !== '')
        <a href="{{ $website }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 mt-4 text-sm font-bold text-lagoon-400 hover:text-white">{{ SiteSetting::text('website_label') }} <i data-icon="external" class="w-4 h-4"></i></a>
      @endif
      @if ($social)
        <div class="flex flex-wrap gap-2 mt-5">
          @foreach ($social as $link)
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-full border border-ocean-700 text-xs font-semibold text-ocean-100 hover:bg-ocean-800">{{ $link['label'] }}</a>
          @endforeach
        </div>
      @endif
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
        @foreach ($phones as $phone)
          <li class="flex gap-2.5"><i data-icon="phone" class="w-4 h-4 mt-0.5 shrink-0"></i><span>{{ $phone['label'] }} <a class="hover:text-white inline-block" href="{{ SiteSetting::tel($phone['number']) }}" dir="ltr">{{ $phone['number'] }}</a></span></li>
        @endforeach
        @if ($email !== '')
          <li class="flex gap-2.5"><i data-icon="mail" class="w-4 h-4 mt-0.5 shrink-0"></i><a class="hover:text-white break-all" href="mailto:{{ $email }}">{{ $email }}</a></li>
        @endif
        @if (filled((string) $address))
          <li class="flex gap-2.5"><i data-icon="pin" class="w-4 h-4 mt-0.5 shrink-0"></i><span>{{ $address }}</span></li>
        @endif
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
