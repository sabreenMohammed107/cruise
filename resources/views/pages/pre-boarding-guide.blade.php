{{-- Pre-boarding guide (PreBoardingGuideController). Every heading, text, card and link comes from $sections
     (Admin → Cruise pages → Pre-boarding guide, config/pages.php → guide); the questions are the Ship FAQs ($faqs).
     Fixed words (section nav, badges, search) from lang/{en,ar}/pages.php → guide. --}}
@extends('layouts.app')

@section('title', __('pages.guide.title'))
@section('description', __('pages.guide.description'))
@section('nav', 'guide')

@php
  $s = $sections;
  $show = fn (string $key) => $s[$key]->is_visible;
  $faqVisible = $show('faq') && $faqs->isNotEmpty();
  // Anchors of the sections on the page (a nav link only for those that are shown)
  $nav = collect([
      'visas' => $show('visas') || $show('passport'),
      'steps' => $show('steps') && $s['steps']->items->isNotEmpty(),
      'checkin' => $show('checkin') || $show('payment'),
      'terminal' => $show('terminal'),
      'prohibited' => $show('prohibited') && $s['prohibited']->items->isNotEmpty(),
      'kids' => $show('kids') && $s['kids']->items->isNotEmpty(),
      'faq' => $faqVisible,
  ])->filter()->keys();
@endphp

@section('content')
  <main class="flex-1">
    <!-- =========================================================
         HERO
         ========================================================= -->
    @php($head = $s['head'])
    <section class="relative isolate text-white bg-ocean-800">
      @if ($image = $head->imageUrl())
        <img src="{{ $image }}" alt="{{ $head->tr('image_alt') }}" class="absolute inset-0 -z-10 w-full h-full object-cover">
        <div class="absolute inset-0 -z-10 hero-shade"></div>
      @endif
      <div class="max-w-site mx-auto px-4 sm:px-6 pt-20 pb-14 sm:pt-32 sm:pb-20">
        @include('partials.breadcrumb', ['tone' => 'light', 'class' => 'mb-4', 'items' => [[__('pages.breadcrumb.home'), route('home')], [__('pages.breadcrumb.guide'), null]]])
        @if ($head->filledBi('eyebrow'))<p class="font-semibold text-lagoon-400 uppercase tracking-wide text-sm">{{ $head->bi('eyebrow') }}</p>@endif
        <h1 class="font-display font-bold text-4xl sm:text-6xl mt-2 max-w-[18ch]">{{ $head->bi('title') }}</h1>
        @if ($head->filledBi('body'))<p class="mt-4 text-lg text-ocean-100 max-w-[60ch] leading-relaxed">{{ $head->bi('body') }}</p>@endif
        <div class="mt-8 flex flex-wrap gap-3">
          @if ($head->filledBi('primary_label'))<a href="{{ $head->primaryHref() }}" class="btn btn-accent btn-lg">{{ $head->bi('primary_label') }}</a>@endif
          @if ($head->filledBi('secondary_label'))<a href="{{ $head->secondaryHref() }}" class="btn btn-lg text-white border border-white/40 hover:bg-white/10">{{ $head->bi('secondary_label') }}</a>@endif
        </div>
      </div>
    </section>

    <!-- Section nav -->
    @if ($nav->isNotEmpty())
      <div class="sticky top-[72px] z-30 bg-white/95 backdrop-blur border-b border-line">
        <nav class="max-w-site mx-auto px-4 sm:px-6 flex gap-1 overflow-x-auto no-scrollbar" aria-label="{{ __('pages.guide.sections') }}">
          @foreach ($nav as $key)
            <a href="#{{ $key }}" class="shrink-0 px-4 py-3.5 font-semibold text-sm border-b-2 border-transparent hover:text-ocean-600">{{ __("pages.guide.nav.$key") }}</a>
          @endforeach
        </nav>
      </div>
    @endif

    <!-- =========================================================
         TRAVEL & VISA REQUIREMENTS
         ========================================================= -->
    @if ($show('visas') || $show('passport'))
      <section id="visas" class="scroll-mt-32 max-w-site mx-auto px-4 sm:px-6 pt-14 sm:pt-20">
        @if ($show('visas'))
          <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $s['visas']->bi('title') }}</h2>
          @if ($s['visas']->filledBi('body'))<p class="mt-3 text-muted max-w-[70ch] leading-relaxed">{{ $s['visas']->bi('body') }}</p>@endif
        @endif

        @if ($show('passport'))
          <div class="mt-6 rounded-2xl border border-sun-300 bg-sun-50 p-5 sm:p-6 flex gap-4 items-start" role="note">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-white text-sun-600 shrink-0"><i data-icon="ticket" class="w-6 h-6"></i></span>
            <div>
              <p class="flex flex-wrap items-center gap-2">
                <span class="font-display font-bold text-lg">{{ $s['passport']->bi('title') }}</span>
                <span class="inline-flex items-center gap-1 rounded-full bg-sun-600 text-white text-xs font-bold px-2.5 py-0.5"><i data-icon="alert" class="w-3.5 h-3.5"></i> {{ __('pages.guide.mandatory') }}</span>
              </p>
              @if ($s['passport']->filledBi('body'))<p class="text-ink/80 mt-1 leading-relaxed">{{ $s['passport']->bi('body') }}</p>@endif
            </div>
          </div>
        @endif

        @if ($show('visas') && $s['visas']->items->isNotEmpty())
          <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3 items-start">
            @foreach ($s['visas']->items as $item)
              <article class="card p-6 h-full flex flex-col">
                <span class="grid place-items-center w-12 h-12 rounded-xl bg-{{ $item->tone }}-50 text-{{ $item->tone }}-600"><i data-icon="{{ $item->icon ?: 'globe' }}" class="w-6 h-6"></i></span>
                <h3 class="font-display font-semibold text-xl mt-4">{{ $item->bi('title') }}</h3>
                @if ($item->filledBi('text'))<p class="text-muted mt-2 leading-relaxed whitespace-pre-line">{{ $item->bi('text') }}</p>@endif
                @if ($href = $item->href())
                  <a href="{{ $href }}" @if ($item->isExternal()) target="_blank" rel="noopener" @endif class="mt-auto pt-4 inline-flex items-center gap-1.5 font-bold text-ocean-600 hover:text-ocean-700">{{ __('pages.guide.open_link') }} <i data-icon="external" class="w-4 h-4"></i></a>
                @endif
              </article>
            @endforeach
          </div>
        @endif

        @if ($show('turkey_links') && $s['turkey_links']->items->isNotEmpty())
          <div class="mt-6 card p-6">
            <h3 class="font-display font-semibold text-lg">{{ $s['turkey_links']->bi('title') }}</h3>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
              @foreach ($s['turkey_links']->items as $item)
                <li>
                  @if ($href = $item->href())
                    <a href="{{ $href }}" @if ($item->isExternal()) target="_blank" rel="noopener" @endif class="flex items-start gap-2.5 font-semibold text-ocean-600 hover:text-ocean-700">
                      <i data-icon="{{ $item->icon ?: 'external' }}" class="w-4 h-4 mt-1 shrink-0"></i><span>{{ $item->bi('title') }}</span>
                    </a>
                  @else
                    <span class="flex items-start gap-2.5 font-semibold"><i data-icon="{{ $item->icon ?: 'info' }}" class="w-4 h-4 mt-1 shrink-0"></i><span>{{ $item->bi('title') }}</span></span>
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        @endif

        @if ($show('visa_help'))
          <div class="mt-6 rounded-2xl bg-lagoon-50 p-6 flex flex-wrap items-center justify-between gap-4">
            <div>
              <h3 class="font-display font-semibold text-lg">{{ $s['visa_help']->bi('title') }}</h3>
              @if ($s['visa_help']->filledBi('body'))<p class="text-muted mt-1">{{ $s['visa_help']->bi('body') }}</p>@endif
            </div>
            @if ($s['visa_help']->filledBi('primary_label'))
              <a href="{{ $s['visa_help']->primaryHref() }}" target="_blank" rel="noopener" class="btn btn-primary">{{ $s['visa_help']->bi('primary_label') }} <i data-icon="external" class="w-4 h-4"></i></a>
            @endif
          </div>
        @endif
      </section>
    @endif

    <!-- =========================================================
         PRE-BOARDING STEPS
         ========================================================= -->
    @if ($show('steps') && $s['steps']->items->isNotEmpty())
      <section id="steps" class="scroll-mt-32 max-w-site mx-auto px-4 sm:px-6 pt-16 sm:pt-24">
        <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $s['steps']->bi('title') }}</h2>
        @if ($s['steps']->filledBi('body'))<p class="mt-3 text-muted max-w-[70ch] leading-relaxed">{{ $s['steps']->bi('body') }}</p>@endif
        <ol class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          @foreach ($s['steps']->items as $item)
            <li class="card p-5 relative">
              <span class="text-xs font-bold uppercase tracking-wide text-muted">{{ __('pages.guide.step', ['number' => $loop->iteration]) }}</span>
              <span class="mt-3 grid place-items-center w-12 h-12 rounded-xl bg-{{ $item->tone }}-50 text-{{ $item->tone }}-600"><i data-icon="{{ $item->icon ?: 'check' }}" class="w-6 h-6"></i></span>
              <h3 class="font-display font-semibold text-lg mt-4">{{ $item->bi('title') }}</h3>
              @if ($item->filledBi('text'))<p class="text-muted text-sm mt-2 leading-relaxed">{{ $item->bi('text') }}</p>@endif
            </li>
          @endforeach
        </ol>
        @if ($s['steps']->filledBi('primary_label') || $s['steps']->filledBi('secondary_label'))
          <div class="mt-6 flex flex-wrap gap-3">
            @if ($s['steps']->filledBi('primary_label'))<a href="{{ $s['steps']->primaryHref() }}" target="_blank" rel="noopener" class="btn btn-outline"><i data-icon="download" class="w-4 h-4"></i> {{ $s['steps']->bi('primary_label') }}</a>@endif
            @if ($s['steps']->filledBi('secondary_label'))<a href="{{ $s['steps']->secondaryHref() }}" target="_blank" rel="noopener" class="btn btn-outline"><i data-icon="download" class="w-4 h-4"></i> {{ $s['steps']->bi('secondary_label') }}</a>@endif
          </div>
        @endif
      </section>
    @endif

    <!-- =========================================================
         CHECK-IN TIMES + PAYMENT ON BOARD
         ========================================================= -->
    @if ($show('checkin') || $show('payment'))
      <section id="checkin" class="scroll-mt-32 max-w-site mx-auto px-4 sm:px-6 pt-16 sm:pt-24">
        <div class="rounded-[28px] bg-ocean-700 text-white p-6 sm:p-10 grid gap-10 lg:grid-cols-2">
          @if ($show('checkin'))
            <div>
              <h2 class="font-display font-bold text-2xl sm:text-3xl">{{ $s['checkin']->bi('title') }}</h2>
              <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ($s['checkin']->items as $item)
                  <div class="rounded-2xl bg-white/10 p-5">
                    <dt class="flex items-center gap-2 text-ocean-100 text-sm font-semibold"><i data-icon="{{ $item->icon ?: 'clock' }}" class="w-4 h-4"></i> {{ $item->bi('title') }}</dt>
                    <dd class="font-display font-bold text-3xl mt-2 tabular">{{ $item->bi('text') }}</dd>
                  </div>
                @endforeach
              </dl>
            </div>
          @endif
          @if ($show('payment'))
            <div>
              <h2 class="font-display font-bold text-2xl sm:text-3xl">{{ $s['payment']->bi('title') }}</h2>
              <ul class="mt-6 grid gap-3">
                @foreach ($s['payment']->items as $item)
                  <li class="flex items-start gap-3">
                    <span class="grid place-items-center w-9 h-9 rounded-lg bg-white/10 shrink-0"><i data-icon="{{ $item->icon ?: 'check' }}" class="w-5 h-5"></i></span>
                    <span class="pt-1.5">{{ $item->bi('title') }}@if ($item->filledBi('text')) <span class="text-ocean-100">— {{ $item->bi('text') }}</span>@endif</span>
                  </li>
                @endforeach
              </ul>
            </div>
          @endif
        </div>
      </section>
    @endif

    <!-- =========================================================
         TERMINAL
         ========================================================= -->
    @if ($show('terminal'))
      @php($terminal = $s['terminal'])
      <section id="terminal" class="scroll-mt-32 max-w-site mx-auto px-4 sm:px-6 pt-16 sm:pt-24 grid gap-8 items-center {{ $terminal->imageUrl() ? 'lg:grid-cols-2' : '' }}">
        <div>
          <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $terminal->bi('title') }}</h2>
          @if ($terminal->filledBi('body'))<p class="mt-4 text-ink/85 leading-relaxed whitespace-pre-line max-w-[65ch]">{{ $terminal->bi('body') }}</p>@endif
          @if ($terminal->filledBi('primary_label'))
            <a href="{{ $terminal->primaryHref() }}" target="_blank" rel="noopener" class="btn btn-primary mt-6"><i data-icon="pin" class="w-4 h-4"></i> {{ $terminal->bi('primary_label') }}</a>
          @endif
        </div>
        @if ($terminalImage = $terminal->imageUrl())
          <img src="{{ $terminalImage }}" alt="{{ $terminal->tr('image_alt') }}" class="w-full aspect-[16/10] object-cover rounded-[22px]" loading="lazy">
        @endif
      </section>
    @endif

    <!-- =========================================================
         ONBOARD PACKAGES
         ========================================================= -->
    @if ($show('packages') && $s['packages']->items->isNotEmpty())
      <section class="max-w-site mx-auto px-4 sm:px-6 pt-16 sm:pt-24">
        <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $s['packages']->bi('title') }}</h2>
        @if ($s['packages']->filledBi('body'))<p class="mt-3 text-muted">{{ $s['packages']->bi('body') }}</p>@endif
        <div class="mt-8 grid gap-4 grid-cols-2 lg:grid-cols-4">
          @foreach ($s['packages']->items as $item)
            <div class="rounded-2xl bg-{{ $item->tone }}-50 p-5 text-center">
              <span class="mx-auto grid place-items-center w-12 h-12 rounded-xl bg-white text-{{ $item->tone }}-600"><i data-icon="{{ $item->icon ?: 'star' }}" class="w-6 h-6"></i></span>
              <h3 class="font-display font-semibold mt-3">{{ $item->bi('title') }}</h3>
              @if ($item->filledBi('text'))<p class="text-muted text-sm mt-1">{{ $item->bi('text') }}</p>@endif
            </div>
          @endforeach
        </div>
      </section>
    @endif

    <!-- =========================================================
         PROHIBITED ITEMS
         ========================================================= -->
    @if ($show('prohibited') && $s['prohibited']->items->isNotEmpty())
      <section id="prohibited" class="scroll-mt-32 mt-16 sm:mt-24 bg-white border-y border-line">
        <div class="max-w-site mx-auto px-4 sm:px-6 py-14 sm:py-16">
          <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $s['prohibited']->bi('title') }}</h2>
          @if ($s['prohibited']->filledBi('body'))<p class="mt-3 text-muted max-w-[70ch]">{{ $s['prohibited']->bi('body') }}</p>@endif
          <ul class="mt-8 grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($s['prohibited']->items as $item)
              <li class="flex items-center gap-3 rounded-xl border border-line p-3">
                <span class="grid place-items-center w-10 h-10 rounded-full bg-[#FDF0F0] text-[#C42F2F] shrink-0"><i data-icon="{{ $item->icon ?: 'x' }}" class="w-5 h-5"></i></span>
                <span class="font-semibold">{{ $item->bi('title') }}@if ($item->filledBi('text'))<span class="block text-sm text-muted font-normal">{{ $item->bi('text') }}</span>@endif</span>
              </li>
            @endforeach
          </ul>
        </div>
      </section>
    @endif

    <!-- =========================================================
         KIDS & TEENS
         ========================================================= -->
    @if ($show('kids') && $s['kids']->items->isNotEmpty())
      <section id="kids" class="scroll-mt-32 max-w-site mx-auto px-4 sm:px-6 pt-16 sm:pt-24">
        <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $s['kids']->bi('title') }}</h2>
        <div class="mt-8 grid gap-5 md:grid-cols-2">
          @foreach ($s['kids']->items as $item)
            <article class="card p-6 flex gap-4 items-start">
              <span class="grid place-items-center w-12 h-12 rounded-xl bg-{{ $item->tone }}-50 text-{{ $item->tone }}-600 shrink-0"><i data-icon="{{ $item->icon ?: 'smile' }}" class="w-6 h-6"></i></span>
              <div class="min-w-0">
                <h3 class="font-display font-semibold text-lg">{{ $item->bi('title') }}</h3>
                @if ($item->filledBi('text'))<p class="text-muted mt-2 leading-relaxed whitespace-pre-line">{{ $item->bi('text') }}</p>@endif
                @if ($href = $item->href())
                  <a href="{{ $href }}" @if ($item->isExternal()) target="_blank" rel="noopener" @endif class="btn btn-outline btn-sm mt-4"><i data-icon="download" class="w-4 h-4"></i> {{ __('pages.guide.download_form') }}</a>
                @endif
              </div>
            </article>
          @endforeach
        </div>
      </section>
    @endif

    <!-- =========================================================
         FAQ (Ship FAQs) with search
         ========================================================= -->
    @if ($faqVisible)
      <section id="faq" class="scroll-mt-32 max-w-3xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24">
        <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $s['faq']->bi('title') }}</h2>
        @if ($s['faq']->filledBi('body'))<p class="mt-3 text-muted">{{ $s['faq']->bi('body') }}</p>@endif
        <div class="relative mt-6">
          <i data-icon="search" class="w-5 h-5 text-muted absolute top-1/2 -translate-y-1/2 start-4 pointer-events-none"></i>
          <input type="search" class="field ps-12" aria-label="{{ __('pages.guide.search_label') }}" placeholder="{{ $s['faq']->tr('caption_label') ?: __('pages.guide.search_label') }}"
                 data-faq-search="#guide-faq" data-faq-empty="#guide-faq-empty" autocomplete="off">
        </div>
        <div id="guide-faq" class="mt-6 grid gap-3" data-accordion="single">
          @foreach ($faqs as $faq)
            <div class="acc-item card {{ $loop->first ? 'open' : '' }}">
              <button class="acc-trigger w-full flex items-center justify-between gap-4 p-5 text-start font-bold" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">{{ $faq->bi('question') }}<i data-icon="chevron-down" class="acc-chevron w-5 h-5 text-muted shrink-0"></i></button>
              <div class="acc-body"><div><p class="px-5 pb-5 text-muted leading-relaxed whitespace-pre-line">{{ $faq->bi('answer') }}</p></div></div>
            </div>
          @endforeach
        </div>
        <p id="guide-faq-empty" class="hidden mt-6 text-center text-muted" role="status">{{ __('pages.guide.no_results') }}</p>
      </section>
    @endif

    <!-- =========================================================
         ADDITIONAL INFORMATION
         ========================================================= -->
    @if ($show('more'))
      @php($more = $s['more'])
      <section class="max-w-site mx-auto px-4 sm:px-6 py-16 sm:py-24">
        <div class="rounded-[28px] bg-ocean-700 text-white px-6 py-10 sm:px-12 flex flex-wrap items-center justify-between gap-6">
          <div class="max-w-[70ch]">
            <h2 class="font-display font-bold text-3xl">{{ $more->bi('title') }}</h2>
            @if ($more->filledBi('body'))<p class="text-ocean-100 mt-3 leading-relaxed whitespace-pre-line">{{ $more->bi('body') }}</p>@endif
          </div>
          <div class="flex flex-wrap gap-3">
            @if ($more->filledBi('primary_label'))<a href="{{ $more->primaryHref() }}" class="btn btn-accent btn-lg">{{ $more->bi('primary_label') }}</a>@endif
            @if ($more->filledBi('secondary_label'))<a href="{{ $more->secondaryHref() }}" target="_blank" rel="noopener" class="btn btn-lg text-white border border-white/40 hover:bg-white/10">{{ $more->bi('secondary_label') }}</a>@endif
          </div>
        </div>
      </section>
    @else
      <div class="pb-16 sm:pb-24"></div>
    @endif
  </main>
@endsection

@push('scripts')
  <script src="@asset('assets/js/faq-search.js')"></script>
@endpush
