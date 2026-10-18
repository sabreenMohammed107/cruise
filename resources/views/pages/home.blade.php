{{-- Converted from legacy-html/index.html. Section copy and photos come from $home (Admin → Homepage, config/home.php);
     hidden sections stay in the markup with the `hidden` attribute because home.js looks up their elements. --}}
@extends('layouts.app')

@php
  $hero = $home['hero']; $search = $home['search']; $ship = $home['ship']; $sailingsSec = $home['sailings'];
  $why = $home['why']; $facilitiesSec = $home['facilities']; $cabins = $home['cabins'];
  $destinationsSec = $home['destinations']; $testimonialsSec = $home['testimonials']; $newsletter = $home['newsletter'];
@endphp

@section('title', __('pages.home.title'))
@section('description', __('pages.home.description'))
@section('nav', 'home')

@section('content')

  <main>
    <!-- =========================================================
         HERO + SEARCH (boarding-pass search widget)
         ========================================================= -->
    <section class="relative isolate" @unless ($hero->is_visible) hidden @endunless>
      <div class="absolute inset-0 -z-10">
        <img id="hero-img" src="{{ $hero->imageUrl() }}" alt="{{ $hero->tr('image_alt') }}" class="w-full h-full object-cover object-[65%_center]" fetchpriority="high">
        <!-- Left-to-right shade keeps the headline readable over the bright sky -->
        <div class="absolute inset-0 bg-gradient-to-r from-ocean-900/75 via-ocean-900/35 to-transparent rtl:bg-gradient-to-l"></div>
        <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-ocean-900/70 to-transparent"></div>
      </div>

      <div class="max-w-site mx-auto px-4 sm:px-6 pt-20 sm:pt-28 pb-44 sm:pb-40">
        <div class="max-w-2xl text-white">
          @if ($hero->filledBi('eyebrow'))
            <p class="inline-flex items-center gap-2 text-sm font-semibold bg-white/15 backdrop-blur px-3 py-1.5 rounded-full">
              <i data-icon="anchor" class="w-4 h-4"></i> {{ $hero->bi('eyebrow') }}
            </p>
          @endif
          <h1 class="font-display font-bold text-[2.4rem] leading-[1.08] sm:text-6xl mt-5">{{ $hero->bi('title') }}</h1>
          @if ($hero->filledBi('body'))
            <p class="mt-5 text-lg text-ocean-100 max-w-xl leading-relaxed">{{ $hero->bi('body') }}</p>
          @endif
          <div class="mt-8 flex flex-wrap gap-3">
            @if ($hero->filledBi('primary_label'))
              <a href="{{ $hero->primaryHref() }}" class="btn btn-accent btn-lg">{{ $hero->bi('primary_label') }}</a>
            @endif
            @if ($hero->filledBi('secondary_label'))
              <a href="{{ $hero->secondaryHref() }}" class="btn btn-lg text-white border border-white/40 hover:bg-white/10">{{ $hero->bi('secondary_label') }}</a>
            @endif
          </div>
        </div>
      </div>
    </section>

    <!-- Search widget overlaps the hero bottom (no overlap when the hero is hidden) -->
    <section class="relative z-10 {{ $hero->is_visible ? '-mt-32 sm:-mt-24' : 'pt-10' }}" aria-labelledby="search-title" @unless ($search->is_visible) hidden @endunless>
      <div class="max-w-site mx-auto px-4 sm:px-6">
        <form id="search-form" class="card pass shadow-[0_30px_60px_-30px_rgba(12,45,78,.45)] flex flex-col lg:flex-row" style="--perf-bg:transparent" novalidate>
          <div class="flex-1 p-5 sm:p-6">
            <h2 id="search-title" class="font-display font-semibold text-lg mb-4">{{ $search->bi('title') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {{-- Fixed words: lang/{en,ar}/site.php. Option texts are set by home.js in the current language. --}}
              <div>
                <label class="label" for="s-dest">@bi('site.search.destination')</label>
                <select id="s-dest" name="port" class="field"><option value="">{{ __('site.search.any_port') }}</option></select>
              </div>
              <div>
                <label class="label" for="s-month">@bi('site.search.month')</label>
                <select id="s-month" name="month" class="field"><option value="">{{ __('site.search.any_month') }}</option></select>
              </div>
              <div>
                <label class="label" for="s-nights">@bi('site.search.nights')</label>
                <select id="s-nights" name="nights" class="field"><option value="">{{ __('site.search.any_length') }}</option></select>
              </div>
              <!-- Guests popover -->
              <div class="relative" data-dropdown>
                <span class="label" id="s-guests-label">@bi('site.search.guests')</span>
                <button type="button" class="field text-start flex items-center justify-between" data-dropdown-trigger aria-expanded="false" aria-labelledby="s-guests-label s-guests-text">
                  <span id="s-guests-text" class="truncate">{{ trans_choice('site.guests.adult', 2) }}</span><i data-icon="users" class="w-4 h-4 text-muted"></i>
                </button>
                <div class="dropdown-panel mt-2 end-0 w-[min(92vw,320px)] p-4" id="s-guests-panel">
                  <div class="divide-y divide-line" id="s-counters"></div>
                  <button type="button" class="btn btn-primary btn-sm w-full mt-3" data-dropdown-close>@bi('site.search.done')</button>
                </div>
              </div>
            </div>
          </div>
          <!-- Stub with tear line -->
          <div class="perf-x lg:hidden"></div>
          <div class="lg:w-64 p-5 sm:p-6 lg:border-s-2 lg:border-dashed lg:border-line flex flex-col justify-center gap-3 bg-ocean-50/50 rounded-b-[20px] lg:rounded-b-none lg:rounded-e-[20px]">
            <p class="text-sm text-muted"><span id="s-count" class="font-bold text-ink">0</span> @bi('site.search.match')</p>
            <button type="submit" class="btn btn-accent btn-lg w-full"><i data-icon="search" class="w-5 h-5"></i> {{ $search->filledBi('primary_label') ? $search->bi('primary_label') : __('pages.home.search_button') }}</button>
          </div>
        </form>
      </div>
    </section>

    <!-- =========================================================
         THE SHIP — spec sheet
         ========================================================= -->
    <section id="ship" class="scroll-mt-24 max-w-site mx-auto px-4 sm:px-6 pt-20 sm:pt-24" @unless ($ship->is_visible) hidden @endunless>
      <div class="grid lg:grid-cols-[1.1fr_1fr] gap-10 items-center">
        <div class="relative rounded-[28px] overflow-hidden aspect-[4/3] bg-ocean-100">
          <img id="ship-img" src="{{ $ship->imageUrl() }}" alt="{{ $ship->tr('image_alt') }}" class="w-full h-full object-cover" loading="lazy">
          @if ($ship->filledBi('caption_label') || $ship->filledBi('caption_value'))
            <div class="absolute bottom-4 start-4 bg-white/95 rounded-2xl px-4 py-3">
              <p class="text-xs font-semibold text-muted">{{ $ship->bi('caption_label') }}</p>
              <p class="font-bold">{{ $ship->bi('caption_value') }}</p>
            </div>
          @endif
        </div>
        <div>
          <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $ship->bi('title') }}</h2>
          @if ($ship->filledBi('body'))
            <p class="mt-4 text-muted text-lg leading-relaxed max-w-[56ch]">{{ $ship->bi('body') }}</p>
          @endif
          <dl id="ship-specs" class="mt-8 grid grid-cols-2 sm:grid-cols-3 border-t border-s border-line rounded-2xl overflow-hidden bg-white"></dl>
          @if ($ship->filledBi('primary_label'))
            <a href="{{ $ship->primaryHref() }}" class="btn btn-primary mt-6">{{ $ship->bi('primary_label') }}</a>
          @endif
        </div>
      </div>
    </section>

    <!-- =========================================================
         FEATURED SAILINGS
         ========================================================= -->
    <section id="sailings" class="max-w-site mx-auto px-4 sm:px-6 pt-20 sm:pt-24" @unless ($sailingsSec->is_visible) hidden @endunless>
      <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
          <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $sailingsSec->bi('title') }}</h2>
          @if ($sailingsSec->filledBi('body'))
            <p class="mt-2 text-muted">{{ $sailingsSec->bi('body') }}</p>
          @endif
        </div>
        @if ($sailingsSec->filledBi('primary_label'))
          <a href="{{ $sailingsSec->primaryHref() }}" class="btn btn-outline">{{ $sailingsSec->bi('primary_label') }}</a>
        @endif
      </div>
      <div id="featured-grid" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"></div>
    </section>

    <!-- =========================================================
         WHY SAFER CRUISE
         ========================================================= -->
    <section class="mt-20 sm:mt-24 bg-white border-y border-line" @unless ($why->is_visible) hidden @endunless>
      <div class="max-w-site mx-auto px-4 sm:px-6 py-16 sm:py-20 grid lg:grid-cols-[1fr_1.3fr] gap-12 items-start">
        <div class="lg:sticky lg:top-28">
          <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $why->bi('title') }}</h2>
          @if ($why->filledBi('body'))
            <p class="mt-4 text-muted text-lg leading-relaxed max-w-[50ch]">{{ $why->bi('body') }}</p>
          @endif
        </div>
        <div class="grid sm:grid-cols-2 gap-5">
          @foreach ($why->items as $item)
            {{-- Every second card is offset, as in the template --}}
            <div class="rounded-2xl bg-{{ $item->tone }}-50 p-6 {{ $loop->even ? 'sm:translate-y-8' : '' }}">
              <span class="grid place-items-center w-12 h-12 rounded-xl bg-white text-{{ $item->tone }}-600"><i data-icon="{{ $item->icon ?: 'check' }}" class="w-6 h-6"></i></span>
              <h3 class="font-display font-semibold text-lg mt-4">{{ $item->bi('title') }}</h3>
              @if ($item->filledBi('text'))
                <p class="text-muted mt-2 leading-relaxed">{{ $item->bi('text') }}</p>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <!-- =========================================================
         ONBOARD FACILITIES
         ========================================================= -->
    <section class="max-w-site mx-auto px-4 sm:px-6 pt-20 sm:pt-28" @unless ($facilitiesSec->is_visible) hidden @endunless>
      <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $facilitiesSec->bi('title') }}</h2>
      @if ($facilitiesSec->filledBi('body'))
        <p class="mt-2 text-muted max-w-[60ch]">{{ $facilitiesSec->bi('body') }}</p>
      @endif
      <ul id="facilities" class="mt-8 grid gap-px bg-line border border-line rounded-[22px] overflow-hidden sm:grid-cols-2 lg:grid-cols-3"></ul>
    </section>

    <!-- =========================================================
         CABIN SHOWCASE — large real photos, one category at a time
         ========================================================= -->
    <section class="max-w-site mx-auto px-4 sm:px-6 pt-20 sm:pt-28" aria-labelledby="cabins-title" @unless ($cabins->is_visible) hidden @endunless>
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 id="cabins-title" class="font-display font-bold text-3xl sm:text-4xl">{{ $cabins->bi('title') }}</h2>
          @if ($cabins->filledBi('body'))
            <p class="mt-2 text-muted">{{ $cabins->bi('body') }}</p>
          @endif
        </div>
        <div class="flex gap-2 overflow-x-auto no-scrollbar max-w-full" role="tablist" aria-label="{{ __('pages.home.cabin_categories') }}" id="cabin-tabs"></div>
      </div>

      <div class="mt-8 grid lg:grid-cols-[1.65fr_1fr] gap-6 items-stretch">
        <!-- Big photo stage -->
        <div class="relative rounded-[28px] overflow-hidden bg-ocean-900 aspect-[4/3] sm:aspect-[16/10] lg:aspect-auto lg:min-h-[580px]" id="cs-stage">
          <img id="cs-main" src="" alt="" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-300">
          <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-ocean-900/80 to-transparent pointer-events-none"></div>
          <span id="cs-badge" class="absolute top-4 start-4 tag tag-sun hidden"></span>
          <span id="cs-count" class="absolute top-4 end-4 rounded-full bg-ocean-900/60 text-white text-xs font-bold px-3 py-1.5 tabular"></span>
          <button class="icon-btn absolute start-4 top-1/2 -translate-y-1/2 !bg-white/90 !border-transparent" id="cs-prev" aria-label="{{ __('pages.home.previous_photo') }}"><i data-icon="chevron-left" class="w-5 h-5 rtl:rotate-180"></i></button>
          <button class="icon-btn absolute end-4 top-1/2 -translate-y-1/2 !bg-white/90 !border-transparent" id="cs-next" aria-label="{{ __('pages.home.next_photo') }}"><i data-icon="chevron-right" class="w-5 h-5 rtl:rotate-180"></i></button>
          <div id="cs-thumbs" class="absolute bottom-4 start-4 end-4 flex gap-2 overflow-x-auto no-scrollbar"></div>
        </div>
        <!-- Details -->
        <div class="card p-6 sm:p-8 flex flex-col" id="cs-info" aria-live="polite"></div>
      </div>
    </section>

    <!-- =========================================================
         DESTINATIONS SLIDER
         ========================================================= -->
    <section id="destinations" class="scroll-mt-24 pt-20 sm:pt-28" @unless ($destinationsSec->is_visible) hidden @endunless>
      <div class="max-w-site mx-auto px-4 sm:px-6 flex flex-wrap items-end justify-between gap-4 mb-8">
        <div>
          <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $destinationsSec->bi('title') }}</h2>
          @if ($destinationsSec->filledBi('body'))
            <p class="mt-2 text-muted">{{ $destinationsSec->bi('body') }}</p>
          @endif
        </div>
        <div class="flex items-center gap-2">
          @if ($destinationsSec->filledBi('primary_label'))
            <a href="{{ $destinationsSec->primaryHref() }}" class="btn btn-outline btn-sm me-2">{{ $destinationsSec->bi('primary_label') }}</a>
          @endif
          <button class="icon-btn" data-slide="-1" aria-label="{{ __('pages.home.previous_destinations') }}"><i data-icon="chevron-left" class="w-5 h-5 rtl:rotate-180"></i></button>
          <button class="icon-btn" data-slide="1" aria-label="{{ __('pages.home.next_destinations') }}"><i data-icon="chevron-right" class="w-5 h-5 rtl:rotate-180"></i></button>
        </div>
      </div>
      <div id="dest-track" class="flex gap-5 overflow-x-auto snap-x snap-mandatory no-scrollbar px-4 sm:px-6 xl:px-[calc((100vw-1240px)/2+24px)] pb-2" tabindex="0" aria-label="{{ __('pages.home.destinations') }}"></div>
    </section>

    <!-- =========================================================
         TESTIMONIALS
         ========================================================= -->
    <section class="max-w-site mx-auto px-4 sm:px-6 pt-20 sm:pt-28" @unless ($testimonialsSec->is_visible) hidden @endunless>
      <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $testimonialsSec->bi('title') }}</h2>
      <div id="testimonials" class="mt-8 grid gap-6 md:grid-cols-3"></div>
    </section>

    <!-- =========================================================
         NEWSLETTER CTA
         ========================================================= -->
    <section class="max-w-site mx-auto px-4 sm:px-6 py-20 sm:py-28" @unless ($newsletter->is_visible) hidden @endunless>
      <div class="relative overflow-hidden rounded-[28px] bg-ocean-700 text-white px-6 py-12 sm:px-12 sm:py-14">
        <svg class="absolute inset-x-0 bottom-0 w-full h-24 text-ocean-600" viewBox="0 0 1200 100" preserveAspectRatio="none" aria-hidden="true"><path fill="currentColor" d="M0 60 Q150 20 300 60 T600 60 T900 60 T1200 60 V100 H0Z"/></svg>
        <div class="relative grid lg:grid-cols-2 gap-8 items-center">
          <div>
            <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $newsletter->bi('title') }}</h2>
            @if ($newsletter->filledBi('body'))
              <p class="mt-3 text-ocean-100 max-w-[48ch]">{{ $newsletter->bi('body') }}</p>
            @endif
          </div>
          <form id="newsletter" class="flex flex-col sm:flex-row gap-3" novalidate>
            <label for="nl-email" class="sr-only">{{ __('pages.home.email') }}</label>
            <input id="nl-email" type="email" class="field !border-transparent !py-3.5" placeholder="{{ __('pages.home.email_placeholder') }}" autocomplete="email" required>
            <button class="btn btn-accent btn-lg shrink-0" type="submit">{{ $newsletter->filledBi('primary_label') ? $newsletter->bi('primary_label') : __('pages.home.subscribe') }}</button>
          </form>
        </div>
      </div>
    </section>
  </main>
@endsection

@push('scripts')
  <script src="@asset('assets/js/pages/home.js')"></script>
@endpush
