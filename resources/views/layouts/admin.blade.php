{{-- Admin layout: the dashboard.html sidebar pattern, with the site's theme. --}}
@php
  // Signed in on the admin guard (routes use auth:admin), independent of the website sign-in
  $user = auth('admin')->user();
  $awaitingCount = \App\Models\Booking::awaitingPayment()->count();
  $link = fn (string $route, ...$match) => request()->routeIs($match ?: [$route]) ? 'is-active' : '';
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex">
  <title>@yield('title') | Safer Cruise admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="{{ asset('assets/js/tailwind.config.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
  <style>
    /* Sidebar menu: visible thin scrollbar so it's clear the list scrolls */
    .admin-scroll { scrollbar-width: thin; scrollbar-color: #B9CBDD transparent; }
    .admin-scroll::-webkit-scrollbar { width: 6px; }
    .admin-scroll::-webkit-scrollbar-thumb { background: #B9CBDD; border-radius: 999px; }
    .admin-scroll::-webkit-scrollbar-track { background: transparent; }
  </style>
</head>
<body class="min-h-screen flex flex-col">
  <header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-line">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 h-[64px] flex items-center gap-4">
      @include('partials.logo')
      <span class="tag tag-ocean">Admin</span>
      <div class="ms-auto flex items-center gap-2">
        <a href="{{ route('home') }}" target="_blank" class="hidden sm:inline-flex btn btn-ghost btn-sm"><i data-icon="external" class="w-4 h-4"></i> View site</a>
        <span class="hidden md:inline text-sm text-muted">{{ $user->name }}</span>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="btn btn-outline btn-sm"><i data-icon="logout" class="w-4 h-4"></i> Sign out</button></form>
        <button class="lg:hidden icon-btn" data-drawer-open="admin-drawer" aria-label="Open menu"><i data-icon="menu" class="w-5 h-5"></i></button>
      </div>
    </div>
  </header>

  @php
    $nav = function () use ($link, $awaitingCount) {
        $groups = ['' => [
            ['Dashboard', route('admin.dashboard'), 'home', $link('admin.dashboard')],
            ['Bookings', route('admin.bookings.index'), 'ticket', $link('admin.bookings.*'), $awaitingCount],
            ['Homepage', route('admin.home-sections.index'), 'edit', $link('admin.home-sections.*')],
            ['Cruise pages', route('admin.pages.index'), 'ship', $link('admin.pages.*')],
        ]];
        foreach (config('admin.resources') as $group => $resources) {
            foreach ($resources as $r) {
                $active = $r::$slug === 'cruises' ? $link('admin.cruises.*', 'admin.sailings.*') : $link('admin.'.$r::$slug.'.*');
                $groups[$group][] = [$r::$label, route($r::routeName('index')), null, $active];
            }
        }
        return $groups;
    };
  @endphp

  <div class="max-w-[1440px] w-full mx-auto px-4 sm:px-6 py-6 grid lg:grid-cols-[240px_1fr] gap-6 items-start flex-1">
    <aside class="hidden lg:block sticky top-[88px] card p-2 max-h-[calc(100vh-110px)] overflow-y-auto overscroll-contain admin-scroll" aria-label="Admin" id="admin-sidebar">
      @include('admin.partials.nav', ['groups' => $nav()])
    </aside>

    <main class="min-w-0">
      @include('admin.partials.flash')
      @yield('content')
    </main>
  </div>

  <div class="drawer" id="admin-drawer" aria-hidden="true">
    <div class="drawer-backdrop" data-drawer-close></div>
    <aside class="drawer-panel p-4 overflow-y-auto" role="dialog" aria-label="Admin menu">
      <div class="flex justify-end mb-2"><button class="icon-btn" data-drawer-close aria-label="Close menu"><i data-icon="x" class="w-5 h-5"></i></button></div>
      @include('admin.partials.nav', ['groups' => $nav()])
    </aside>
  </div>

  <script>window.SC_DATA = @json(app(\App\Support\ScDataPresenter::class)->minimal($user));</script>
  <script src="{{ asset('assets/js/core.js') }}"></script>
  <script>
    // Keep the current page's menu link in view when the sidebar is taller than the screen
    (() => {
      const bar = document.getElementById('admin-sidebar'), active = bar && bar.querySelector('.is-active');
      if (!active) return;
      const top = active.getBoundingClientRect().top - bar.getBoundingClientRect().top + bar.scrollTop;
      if (top + active.offsetHeight > bar.clientHeight) bar.scrollTop = top - bar.clientHeight / 2;
    })();
  </script>
  @stack('scripts')
</body>
</html>
