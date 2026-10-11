{{-- Converted from legacy-html/details.html. Section headings, texts and lists come from $sections
     (Admin → Cruise pages → Cruise details); the cruise itself from SC_DATA (details.js). --}}
@extends('layouts.app')

@php($s = $sections)
@php($hide = fn (string $key) => $s[$key]->is_visible ? '' : 'hidden')

@section('title', __('pages.cruise.title', ['cruise' => $cruise->tr('name')]))
@section('description', __('pages.cruise.description', ['cruise' => $cruise->tr('name'), 'nights' => $cruise->nights, 'ship' => $cruise->ship->tr('name')]))
@section('body_class', 'pb-24 lg:pb-0')
@section('nav', 'catalog')

@section('content')

  <main class="flex-1">
    <!-- =========================================================
         TITLE + GALLERY
         ========================================================= -->
    <section class="max-w-site mx-auto px-4 sm:px-6 pt-6">
      @include('partials.breadcrumb', ['class' => 'mb-4', 'currentId' => 'crumb-name', 'items' => [
          [__('pages.breadcrumb.home'), route('home')], [__('pages.breadcrumb.cruises'), route('cruises.index')], [$cruise->tr('name'), null],
      ]])

      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div id="d-tags" class="flex flex-wrap gap-2 mb-3"></div>
          <h1 id="d-name" class="font-display font-bold text-3xl sm:text-[2.6rem] leading-tight"></h1>
          <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-muted">
            <span id="d-rating"></span>
            <span class="flex items-center gap-1.5"><i data-icon="ship" class="w-4 h-4"></i><span id="d-ship"></span></span>
            <span class="flex items-center gap-1.5"><i data-icon="route" class="w-4 h-4"></i><span id="d-ports"></span></span>
          </div>
        </div>
        <div class="flex gap-2" id="d-actions">
          <button class="icon-btn" id="share-btn" aria-label="{{ __('pages.cruise.copy_link') }}"><i data-icon="external" class="w-5 h-5"></i></button>
        </div>
      </div>

      <!-- Gallery: mosaic on desktop, swipe strip on mobile -->
      <div id="gallery" class="mt-6 relative">
        <div id="gallery-grid" class="hidden md:grid grid-cols-4 grid-rows-2 gap-3 h-[440px] rounded-[24px] overflow-hidden"></div>
        <div id="gallery-strip" class="md:hidden flex gap-3 overflow-x-auto snap-x snap-mandatory no-scrollbar -mx-4 px-4"></div>
        <button class="btn btn-white btn-sm absolute bottom-4 end-4 shadow" id="gallery-all"><i data-icon="image" class="w-4 h-4"></i> {{ __('pages.cruise.all_photos') }}</button>
      </div>
    </section>

    <!-- =========================================================
         IN-PAGE NAV
         ========================================================= -->
    <div class="sticky top-[72px] z-30 bg-white/95 backdrop-blur border-y border-line mt-8">
      <nav class="max-w-site mx-auto px-4 sm:px-6 flex gap-1 overflow-x-auto no-scrollbar" aria-label="{{ __('pages.cruise.sections') }}" id="subnav">
        <a href="#overview" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.cruise.nav.overview') }}</a>
        <a href="#itinerary" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.cruise.nav.itinerary') }}</a>
        <a href="#cabins" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.cruise.nav.cabins') }}</a>
        <a href="#amenities" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.cruise.nav.amenities') }}</a>
        <a href="#safety" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __('pages.cruise.nav.safety') }}</a>
      </nav>
    </div>

    <div class="max-w-site mx-auto px-4 sm:px-6 py-10 grid lg:grid-cols-[1fr_360px] gap-10 items-start">
      <div class="min-w-0 grid gap-16">

        <!-- ================= OVERVIEW ================= -->
        <section id="overview" class="scroll-mt-36">
          <!-- Sailing facts as a boarding pass -->
          <div class="card pass flex flex-col sm:flex-row" style="--perf-bg:#F5F8FB">
            <div class="flex-1 p-5 sm:p-6 grid grid-cols-2 sm:grid-cols-4 gap-5" id="d-facts"></div>
          </div>

          <h2 class="font-display font-semibold text-2xl mt-10">{{ $s['dates']->bi('title') }}</h2>
          @if ($s['dates']->filledBi('body'))<p class="text-muted mt-1">{{ $s['dates']->bi('body') }}</p>@endif
          <div id="d-dates" class="mt-4 flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('pages.cruise.departure_dates') }}"></div>

          <h2 class="font-display font-semibold text-2xl mt-10" {{ $hide('about') }}>{{ $s['about']->bi('title') }}</h2>
          <p id="d-summary" class="mt-3 text-[1.05rem] leading-relaxed max-w-[68ch]" {{ $hide('about') }}></p>
          <div class="mt-6 grid sm:grid-cols-2 gap-5">
            @foreach (['included' => ['check', 'text-lagoon-600', ''], 'not_included' => ['minus', '', 'text-muted']] as $key => [$icon, $iconCls, $listCls])
              <div class="rounded-2xl bg-white border border-line p-5" {{ $hide($key) }}>
                <h3 class="font-bold mb-3">{{ $s[$key]->bi('title') }}</h3>
                <ul class="grid gap-2 text-sm {{ $listCls }}">
                  @foreach ($s[$key]->items as $item)
                    <li class="flex gap-2"><i data-icon="{{ $item->icon ?: $icon }}" class="w-4 h-4 {{ $iconCls }} mt-0.5 shrink-0"></i>{{ $item->bi('title') }}</li>
                  @endforeach
                </ul>
              </div>
            @endforeach
          </div>
        </section>

        <!-- ================= ITINERARY ================= -->
        <section id="itinerary" class="scroll-mt-36" {{ $hide('itinerary') }}>
          <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 class="font-display font-semibold text-2xl">{{ $s['itinerary']->bi('title') }}</h2>
              @if ($s['itinerary']->filledBi('body'))<p class="text-muted mt-1">{{ $s['itinerary']->bi('body') }}</p>@endif
            </div>
            <button class="btn btn-ghost btn-sm" id="itin-toggle">{{ __('pages.cruise.expand_all') }}</button>
          </div>
          <ol id="d-itinerary" class="mt-6 grid gap-3" data-accordion></ol>
        </section>

        <!-- ================= CABINS & TRAVELLERS ================= -->
        <section id="cabins" class="scroll-mt-36">
          <h2 class="font-display font-semibold text-2xl">{{ $s['travellers']->bi('title') }}</h2>
          @if ($s['travellers']->filledBi('body'))<p class="text-muted mt-1">{{ $s['travellers']->bi('body') }}</p>@endif
          <div class="card mt-4 p-2 sm:p-4">
            <div id="d-counters" class="divide-y divide-line px-3"></div>
          </div>
          <div id="cap-alert" class="hidden mt-4 rounded-2xl border border-sun-400 bg-sun-50 p-4 flex gap-3 text-sm" role="alert">
            <i data-icon="alert" class="w-5 h-5 text-sun-600 shrink-0"></i><p id="cap-alert-text"></p>
          </div>

          <h2 class="font-display font-semibold text-2xl mt-12">{{ $s['cabins']->bi('title') }}</h2>
          @if ($s['cabins']->filledBi('body'))<p class="text-muted mt-1">{{ $s['cabins']->bi('body') }}</p>@endif
          <div id="d-cabins" class="mt-6 grid gap-6 md:grid-cols-3"></div>
        </section>

        <!-- ================= AMENITIES ================= -->
        <section id="amenities" class="scroll-mt-36" {{ $hide('amenities') }}>
          <h2 class="font-display font-semibold text-2xl">{{ $s['amenities']->bi('title') }}</h2>
          <ul id="d-amenities" class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"></ul>
        </section>

        <!-- ================= HEALTH & SAFETY ================= -->
        <section id="safety" class="scroll-mt-36" {{ $hide('safety') }}>
          <h2 class="font-display font-semibold text-2xl">{{ $s['safety']->bi('title') }}</h2>
          @if ($s['safety']->filledBi('body'))<p class="text-muted mt-1 max-w-[62ch]">{{ $s['safety']->bi('body') }}</p>@endif
          <div class="mt-6 grid gap-3" data-accordion="single">
            @foreach ($s['safety']->items as $item)
              <div class="acc-item card {{ $loop->first ? 'open' : '' }}">
                <button class="acc-trigger w-full flex items-center gap-4 p-5 text-start" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                  <span class="grid place-items-center w-10 h-10 rounded-xl bg-ocean-50 text-ocean-600 shrink-0"><i data-icon="{{ $item->icon ?: 'info' }}" class="w-5 h-5"></i></span>
                  <span class="flex-1 font-bold">{{ $item->bi('title') }}</span><i data-icon="chevron-down" class="acc-chevron w-5 h-5 text-muted"></i>
                </button>
                <div class="acc-body"><div><p class="px-5 pb-5 ps-[4.75rem] text-muted leading-relaxed">{{ $item->bi('text') }}</p></div></div>
              </div>
            @endforeach
          </div>
        </section>
      </div>

      <!-- =========================================================
           BOOKING SUMMARY (sticky sidebar)
           ========================================================= -->
      <aside class="hidden lg:block sticky top-[140px]" aria-label="{{ __('pages.cruise.booking_summary') }}">
        <div class="card pass overflow-hidden" style="--perf-bg:#F5F8FB" id="summary"></div>
        <p class="text-xs text-muted mt-3 flex items-center gap-1.5" {{ $hide('note') }}><i data-icon="check" class="w-3.5 h-3.5"></i> {{ $s['note']->bi('title') }}</p>
      </aside>
    </div>
  </main>

  <!-- Mobile sticky booking bar -->
  <div class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-line px-4 py-3 flex items-center justify-between gap-3">
    <div>
      <p class="text-xs text-muted" id="m-sub">{{ __('pages.cruise.choose_cabin') }}</p>
      <p class="font-display font-bold text-lg tabular" id="m-total">—</p>
    </div>
    <button class="btn btn-accent" id="m-continue">{{ __('pages.cruise.continue') }}</button>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('assets/js/pages/details.js') }}"></script>
@endpush
