{{-- Ported from SC.renderHeader() in legacy-html/assets/js/core.js; links are routes and the sign-in state is real.
     Words come from lang/{en,ar}/site.php (@bi prints both; theme.css shows the current language). The logo is not translated. --}}
@php
  $nav = [
      ['id' => 'home', 'href' => route('home')],
      ['id' => 'catalog', 'href' => route('cruises.index')],
      ['id' => 'ship', 'href' => route('ship')],
      ['id' => 'destinations', 'href' => route('destinations.index')],
      ['id' => 'contact', 'href' => route('contact')],
  ];
  // Website customer (guard "web"); the admin panel has its own sign-in (guard "admin") and no link from the site
  $user = auth('web')->user();
@endphp
<header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-line">
  <div class="max-w-site mx-auto px-4 sm:px-6 h-[72px] flex items-center gap-4">
    @include('partials.logo')
    <nav class="hidden lg:flex items-center gap-1 ms-6" aria-label="{{ __('site.nav.main') }}">
      @foreach ($nav as $n)
        <a href="{{ $n['href'] }}" class="px-3.5 py-2 rounded-full text-[0.93rem] font-semibold transition {{ $n['id'] === $active ? 'text-ocean-700 bg-ocean-50' : 'text-ink hover:text-ocean-600' }}" @if ($n['id'] === $active) aria-current="page" @endif>@bi('site.nav.'.$n['id'])</a>
      @endforeach
    </nav>
    <div class="ms-auto flex items-center gap-2">
      <button class="hidden sm:inline-flex btn btn-ghost btn-sm" data-dir-toggle title="عربي / English">@bi('site.nav.switch_language')</button>
      <a href="{{ route('account') }}#bookings" class="hidden md:inline-flex btn btn-ghost btn-sm {{ $active === 'dashboard' ? 'bg-ocean-50' : '' }}"><i data-icon="ticket" class="w-4 h-4"></i> @bi('site.nav.my_bookings')</a>
      @auth('web')
        <a href="{{ route('account') }}#profile" class="hidden sm:inline-flex btn btn-primary btn-sm"><i data-icon="user" class="w-4 h-4"></i> @if ($user->first_name){{ $user->first_name }}@else @bi('site.nav.my_account') @endif</a>
      @else
        <a href="{{ route('login') }}" class="hidden sm:inline-flex btn btn-primary btn-sm"><i data-icon="user" class="w-4 h-4"></i> @bi('site.nav.sign_in')</a>
      @endauth
      <button class="lg:hidden icon-btn" data-drawer-open="nav-drawer" aria-label="{{ __('site.nav.open_menu') }}"><i data-icon="menu" class="w-5 h-5"></i></button>
    </div>
  </div>
</header>
<div class="drawer" id="nav-drawer" aria-hidden="true">
  <div class="drawer-backdrop" data-drawer-close></div>
  <aside class="drawer-panel p-5 flex flex-col" role="dialog" aria-label="{{ __('site.nav.menu') }}">
    <div class="flex items-center justify-between mb-6">@include('partials.logo')<button class="icon-btn" data-drawer-close aria-label="{{ __('site.nav.close_menu') }}"><i data-icon="x" class="w-5 h-5"></i></button></div>
    <nav class="grid gap-1" aria-label="{{ __('site.nav.mobile') }}">
      @foreach ($nav as $n)
        <a href="{{ $n['href'] }}" class="side-link {{ $n['id'] === $active ? 'is-active' : '' }}">@bi('site.nav.'.$n['id'])</a>
      @endforeach
      <a href="{{ route('account') }}#bookings" class="side-link {{ $active === 'dashboard' ? 'is-active' : '' }}">@bi('site.nav.my_bookings')</a>
    </nav>
    <div class="mt-auto pt-6 grid gap-2">
      @auth('web')
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline w-full">@bi('site.nav.sign_out')</button></form>
      @else
        <a href="{{ route('login') }}" class="btn btn-primary">@bi('site.nav.sign_in')</a>
      @endauth
      <button class="btn btn-outline" data-dir-toggle>@bi('site.nav.switch_language_long')</button>
    </div>
  </aside>
</div>
