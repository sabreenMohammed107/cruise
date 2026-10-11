{{-- Converted from legacy-html/ship.html. Section headings, texts, buttons and the hero photo come from $sections
     (Admin → Cruise pages → Ship page, config/pages.php); the ship's own data from SC_DATA (ship.js). --}}
@extends('layouts.app')

@php
  $hide = fn (string $key) => $sections[$key]->is_visible ? '' : 'hidden';
  $shipName = \App\Models\Ship::query()->first()?->tr('name') ?? 'AROYA';
@endphp

@section('title', __('pages.ship.title', ['ship' => $shipName]))
@section('description', __('pages.ship.description', ['ship' => $shipName]))
@section('nav', 'ship')

@section('content')

  <main class="flex-1">
    <!-- =========================================================
         HERO
         ========================================================= -->
    <section class="relative isolate text-white" {{ $hide('hero') }}>
      <img id="ship-hero" src="{{ $sections['hero']->imageUrl() }}" alt="{{ $sections['hero']->tr('image_alt') }}" class="absolute inset-0 -z-10 w-full h-full object-cover object-[75%_center]">
      <div class="absolute inset-0 -z-10 hero-shade"></div>
      <div class="max-w-site mx-auto px-4 sm:px-6 pt-24 pb-16 sm:pt-36 sm:pb-24">
        @include('partials.breadcrumb', ['tone' => 'light', 'class' => 'mb-4', 'items' => [[__('pages.breadcrumb.home'), route('home')], [__('pages.breadcrumb.ship'), null]]])
        <p class="font-semibold text-lagoon-400" id="ship-tagline"></p>
        <h1 id="ship-name" class="font-display font-bold text-5xl sm:text-7xl mt-2">{{ $shipName }}</h1>
        <div class="mt-6 flex flex-wrap gap-2" id="ship-chips"></div>
        <div class="mt-8 flex flex-wrap gap-3">
          @if ($sections['hero']->filledBi('primary_label'))<a href="{{ $sections['hero']->primaryHref() }}" class="btn btn-accent btn-lg">{{ $sections['hero']->bi('primary_label') }}</a>@endif
          @if ($sections['hero']->filledBi('secondary_label'))<a href="{{ $sections['hero']->secondaryHref() }}" class="btn btn-lg text-white border border-white/40 hover:bg-white/10">{{ $sections['hero']->bi('secondary_label') }}</a>@endif
        </div>
      </div>
    </section>

    <!-- Section nav -->
    <div class="sticky top-[72px] z-30 bg-white/95 backdrop-blur border-b border-line">
      <nav class="max-w-site mx-auto px-4 sm:px-6 flex gap-1 overflow-x-auto no-scrollbar" aria-label="{{ __('pages.ship.sections') }}" id="ship-subnav">
        <a href="#about" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.ship.nav.about') }}</a>
        <a href="#onboard" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.ship.nav.onboard') }}</a>
        <a href="#cabins" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.ship.nav.cabins') }}</a>
        <a href="#essentials" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.ship.nav.essentials') }}</a>
        <a href="#faq" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.ship.nav.faq') }}</a>
      </nav>
    </div>

    <!-- =========================================================
         ABOUT + SPECS
         ========================================================= -->
    <section id="about" class="scroll-mt-32 max-w-site mx-auto px-4 sm:px-6 pt-16 sm:pt-20 grid lg:grid-cols-2 gap-12 items-start" {{ $hide('about') }}>
      <div>
        <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $sections['about']->bi('title') }}</h2>
        <div id="ship-story" class="mt-5 grid gap-4 text-lg leading-relaxed text-ink/90 max-w-[60ch]"></div>
      </div>
      <div>
        <h3 class="font-display font-semibold text-lg mb-4">{{ $sections['about']->bi('caption_label') }}</h3>
        <dl id="ship-specs" class="grid grid-cols-2 sm:grid-cols-3 border-t border-s border-line rounded-2xl overflow-hidden bg-white"></dl>
        @if ($sections['about']->filledBi('caption_value'))<p class="text-xs text-muted mt-3">{{ $sections['about']->bi('caption_value') }}</p>@endif
      </div>
    </section>

    <!-- =========================================================
         LIFE ON BOARD — alternating image rows
         ========================================================= -->
    <section id="onboard" class="scroll-mt-32 max-w-site mx-auto px-4 sm:px-6 pt-20 sm:pt-28" {{ $hide('onboard') }}>
      <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $sections['onboard']->bi('title') }}</h2>
      @if ($sections['onboard']->filledBi('body'))<p class="mt-2 text-muted max-w-[60ch]">{{ $sections['onboard']->bi('body') }}</p>@endif
      <div id="ship-zones" class="mt-10 grid gap-12 sm:gap-16"></div>

      <h3 class="font-display font-semibold text-2xl mt-20">{{ $sections['onboard']->bi('caption_label') }}</h3>
      <ul id="ship-facilities" class="mt-6 grid gap-px bg-line border border-line rounded-[22px] overflow-hidden sm:grid-cols-2 lg:grid-cols-3"></ul>
    </section>

    <!-- =========================================================
         CABINS
         ========================================================= -->
    <section id="cabins" class="scroll-mt-32 max-w-site mx-auto px-4 sm:px-6 pt-20 sm:pt-28" {{ $hide('cabins') }}>
      <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $sections['cabins']->bi('title') }}</h2>
      @if ($sections['cabins']->filledBi('body'))<p class="mt-2 text-muted">{{ $sections['cabins']->bi('body') }}</p>@endif
      <div id="ship-cabins" class="mt-8 grid gap-6 md:grid-cols-3"></div>
    </section>

    <!-- =========================================================
         ESSENTIALS
         ========================================================= -->
    <section id="essentials" class="scroll-mt-32 mt-20 sm:mt-28 bg-white border-y border-line" {{ $hide('essentials') }}>
      <div class="max-w-site mx-auto px-4 sm:px-6 py-16">
        <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $sections['essentials']->bi('title') }}</h2>
        <ul id="ship-essentials" class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"></ul>
      </div>
    </section>

    <!-- =========================================================
         FAQ
         ========================================================= -->
    <section id="faq" class="scroll-mt-32 max-w-3xl mx-auto px-4 sm:px-6 pt-20 sm:pt-24" {{ $hide('faq') }}>
      <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $sections['faq']->bi('title') }}</h2>
      <div id="ship-faq" class="mt-8 grid gap-3" data-accordion="single"></div>
    </section>

    <!-- CTA -->
    <section class="max-w-site mx-auto px-4 sm:px-6 py-20 sm:py-24" {{ $hide('cta') }}>
      <div class="rounded-[28px] bg-ocean-700 text-white px-6 py-12 sm:px-12 flex flex-wrap items-center justify-between gap-6">
        <div>
          <h2 class="font-display font-bold text-3xl">{{ $sections['cta']->bi('title') }}</h2>
          @if ($sections['cta']->filledBi('body'))<p class="text-ocean-100 mt-2">{{ $sections['cta']->bi('body') }}</p>@endif
        </div>
        @if ($sections['cta']->filledBi('primary_label'))<a href="{{ $sections['cta']->primaryHref() }}" class="btn btn-accent btn-lg">{{ $sections['cta']->bi('primary_label') }}</a>@endif
      </div>
    </section>
  </main>
@endsection

@push('scripts')
  <script src="{{ asset('assets/js/pages/ship.js') }}"></script>
@endpush
