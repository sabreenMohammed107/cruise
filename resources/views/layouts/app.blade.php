<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Safer Cruise')</title>
  @hasSection('description')
  <meta name="description" content="@yield('description')">
  @endif
  @stack('head')
  <!-- Tailwind (CDN) + brand theme, as in the template. Moves to a Vite build in phase 7. -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="{{ asset('assets/js/tailwind.config.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
</head>
<body class="min-h-screen flex flex-col @yield('body_class')">

  @include('partials.header', ['active' => trim($__env->yieldContent('nav'))])

  @if (session('status'))
    <div class="max-w-site w-full mx-auto px-4 sm:px-6 pt-4">
      <p class="rounded-2xl border border-ocean-100 bg-ocean-50 p-4 text-sm font-semibold text-ocean-800" role="status">{{ session('status') }}</p>
    </div>
  @endif

  @yield('content')

  @include('partials.footer')

  <!-- Data for the template scripts, built from the database (App\Support\ScDataPresenter) -->
  <script>window.SC_DATA = @json($scData);</script>
  <script src="{{ asset('assets/js/core.js') }}"></script>
  @stack('scripts')
</body>
</html>
