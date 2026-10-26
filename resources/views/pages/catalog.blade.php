{{-- Converted from legacy-html/catalog.html. Heading, search placeholder and the no-results text come from $sections
     (Admin → Cruise pages → Cruises list); filters and results are built by catalog.js. --}}
@extends('layouts.app')

@section('title', __('pages.catalog.title'))
@section('description', __('pages.catalog.description'))
@section('nav', 'catalog')

@section('content')

  <main class="flex-1">
    <!-- =========================================================
         PAGE HEAD + NAME SEARCH
         ========================================================= -->
    <section class="bg-ocean-700 text-white" @unless ($sections['head']->is_visible) hidden @endunless>
      <div class="max-w-site mx-auto px-4 sm:px-6 py-10 sm:py-14">
        @include('partials.breadcrumb', ['tone' => 'light', 'items' => [[__('pages.breadcrumb.home'), route('home')], [__('pages.breadcrumb.cruises'), null]]])
        <h1 class="font-display font-bold text-3xl sm:text-5xl">{{ $sections['head']->bi('title') }}</h1>
        @if ($sections['head']->filledBi('body'))<p class="mt-3 text-ocean-100 max-w-[60ch]">{{ $sections['head']->bi('body') }}</p>@endif
        <div class="mt-6 max-w-xl relative">
          <label for="q" class="sr-only">{{ __('pages.catalog.search_label') }}</label>
          <i data-icon="search" class="w-5 h-5 absolute start-4 top-1/2 -translate-y-1/2 text-muted pointer-events-none"></i>
          <input id="q" type="search" class="field !py-3.5 !ps-12 !border-transparent" placeholder="{{ $sections['head']->tr('caption_label') }}">
        </div>
      </div>
    </section>

    <div class="max-w-site mx-auto px-4 sm:px-6 py-8 grid lg:grid-cols-[280px_1fr] gap-8 items-start">

      <!-- =========================================================
           FILTERS — sidebar on desktop, off-canvas panel on mobile
           ========================================================= -->
      <div id="filter-backdrop" class="fixed inset-0 z-[64] bg-ocean-900/50 hidden lg:hidden"></div>
      <aside id="filters" class="fixed inset-y-0 start-0 z-[65] w-[88vw] max-w-sm bg-white overflow-y-auto p-5 -translate-x-full rtl:translate-x-full transition-transform duration-300
                                 lg:static lg:z-auto lg:w-auto lg:max-w-none lg:translate-x-0 rtl:lg:translate-x-0 lg:p-0 lg:bg-transparent lg:overflow-visible lg:sticky lg:top-24"
             aria-label="{{ __('pages.catalog.filters') }}">
        <div class="flex items-center justify-between mb-5">
          <h2 class="font-display font-semibold text-lg">{{ __('pages.catalog.filters') }}</h2>
          <div class="flex items-center gap-2">
            <button id="clear-all" class="btn btn-ghost btn-sm">{{ __('pages.catalog.clear_all') }}</button>
            <button class="icon-btn lg:hidden" id="filters-close" aria-label="{{ __('pages.catalog.close_filters') }}"><i data-icon="x" class="w-5 h-5"></i></button>
          </div>
        </div>

        <form id="filter-form" class="grid gap-3" onsubmit="return false">
          <!-- Each group is a collapsible accordion item, open by default -->
          <div data-accordion class="grid gap-3">
            <div class="acc-item open card p-4">
              <button type="button" class="acc-trigger w-full flex items-center justify-between font-bold" aria-expanded="true">{{ __('pages.catalog.departure_port') }} <i data-icon="chevron-down" class="acc-chevron w-4 h-4"></i></button>
              <div class="acc-body"><div><div id="f-ports" class="grid gap-2.5 pt-3"></div></div></div>
            </div>

            <div class="acc-item open card p-4">
              <button type="button" class="acc-trigger w-full flex items-center justify-between font-bold" aria-expanded="true">{{ __('pages.catalog.dates') }} <i data-icon="chevron-down" class="acc-chevron w-4 h-4"></i></button>
              <div class="acc-body"><div class="grid gap-3 pt-3">
                <div><label class="label" for="f-month">{{ __('pages.catalog.month') }}</label><select id="f-month" class="field"><option value="">{{ __('pages.catalog.any_month') }}</option></select></div>
                <div><label class="label" for="f-date">{{ __('pages.catalog.departing_after') }}</label><input id="f-date" type="date" class="field"></div>
              </div></div>
            </div>

            <div class="acc-item open card p-4">
              <button type="button" class="acc-trigger w-full flex items-center justify-between font-bold" aria-expanded="true">{{ __('pages.catalog.nights') }} <i data-icon="chevron-down" class="acc-chevron w-4 h-4"></i></button>
              <div class="acc-body"><div><div id="f-nights" class="flex flex-wrap gap-2 pt-3"></div></div></div>
            </div>

            <div class="acc-item open card p-4">
              <button type="button" class="acc-trigger w-full flex items-center justify-between font-bold" aria-expanded="true">{{ __('pages.catalog.price') }} <i data-icon="chevron-down" class="acc-chevron w-4 h-4"></i></button>
              <div class="acc-body"><div class="pt-3">
                <label for="f-price" class="text-sm">{{ __('pages.catalog.up_to') }} <output id="f-price-out" class="font-bold tabular"></output></label>
                <input id="f-price" type="range" step="50" class="mt-2">
                <div class="flex justify-between text-xs text-muted tabular"><span id="f-price-min"></span><span id="f-price-max"></span></div>
              </div></div>
            </div>

            <div class="acc-item open card p-4">
              <button type="button" class="acc-trigger w-full flex items-center justify-between font-bold" aria-expanded="true">{{ __('pages.catalog.cabin_type') }} <i data-icon="chevron-down" class="acc-chevron w-4 h-4"></i></button>
              <div class="acc-body"><div><div id="f-cabins" class="grid gap-2.5 pt-3"></div></div></div>
            </div>

            <div class="acc-item card p-4">
              <button type="button" class="acc-trigger w-full flex items-center justify-between font-bold" aria-expanded="false">{{ __('pages.catalog.ship') }} <i data-icon="chevron-down" class="acc-chevron w-4 h-4"></i></button>
              <div class="acc-body"><div><div id="f-ships" class="grid gap-2.5 pt-3"></div></div></div>
            </div>

            <div class="acc-item card p-4">
              <button type="button" class="acc-trigger w-full flex items-center justify-between font-bold" aria-expanded="false">{{ __('pages.catalog.highlights') }} <i data-icon="chevron-down" class="acc-chevron w-4 h-4"></i></button>
              <div class="acc-body"><div><div id="f-highlights" class="grid gap-2.5 pt-3"></div></div></div>
            </div>
          </div>
        </form>

        <button id="filters-apply" class="btn btn-primary w-full mt-5 lg:hidden">{{ __('pages.catalog.show_results') }}</button>
      </aside>

      <!-- =========================================================
           RESULTS
           ========================================================= -->
      {{-- min-w-0: a grid item is otherwise as wide as its widest unbreakable content, which scrolled phones sideways --}}
      <section aria-labelledby="results-title" class="min-w-0">
        <div class="flex flex-wrap items-center gap-3 justify-between">
          <h2 id="results-title" class="font-display font-semibold text-lg"><span id="r-count">0</span> {{ __('pages.catalog.sailings') }}</h2>
          <div class="flex items-center gap-2">
            <button id="filters-open" class="btn btn-outline btn-sm lg:hidden"><i data-icon="filter" class="w-4 h-4"></i> {{ __('pages.catalog.filters') }} <span id="f-badge" class="hidden min-w-5 h-5 px-1.5 rounded-full bg-ocean-600 text-white text-xs grid place-items-center"></span></button>
            <label for="sort" class="sr-only">{{ __('pages.catalog.sort_by') }}</label>
            <select id="sort" class="field !py-2 !rounded-full !w-auto text-sm font-semibold">
              <option value="popular">{{ __('pages.catalog.sort.popular') }}</option>
              <option value="date">{{ __('pages.catalog.sort.date') }}</option>
              <option value="price-asc">{{ __('pages.catalog.sort.price_asc') }}</option>
              <option value="price-desc">{{ __('pages.catalog.sort.price_desc') }}</option>
              <option value="rating">{{ __('pages.catalog.sort.rating') }}</option>
            </select>
            <div class="hidden sm:flex rounded-full border border-line bg-white p-1" role="group" aria-label="{{ __('pages.catalog.layout') }}">
              <button class="w-9 h-9 grid place-items-center rounded-full" data-view="grid" aria-label="{{ __('pages.catalog.grid_view') }}" aria-pressed="true"><i data-icon="grid" class="w-4 h-4"></i></button>
              <button class="w-9 h-9 grid place-items-center rounded-full" data-view="list" aria-label="{{ __('pages.catalog.list_view') }}" aria-pressed="false"><i data-icon="list" class="w-4 h-4"></i></button>
            </div>
          </div>
        </div>

        <!-- Active filter chips -->
        <div id="active-chips" class="flex flex-wrap gap-2 mt-4"></div>

        <div id="results" class="mt-6 grid gap-6"></div>

        <!-- Empty state -->
        <div id="empty" class="hidden card p-10 text-center mt-6">
          <span class="inline-grid place-items-center w-14 h-14 rounded-2xl bg-ocean-50 text-ocean-600"><i data-icon="search" class="w-7 h-7"></i></span>
          <h3 class="font-display font-semibold text-xl mt-4">{{ $sections['empty']->bi('title') }}</h3>
          @if ($sections['empty']->filledBi('body'))<p class="text-muted mt-2">{{ $sections['empty']->bi('body') }}</p>@endif
          <button class="btn btn-primary mt-6" data-clear-all>{{ $sections['empty']->filledBi('primary_label') ? $sections['empty']->bi('primary_label') : 'Clear all filters' }}</button>
        </div>

        <nav id="pagination" class="mt-10 flex flex-wrap items-center justify-center gap-1.5 sm:gap-2" aria-label="{{ __('pages.catalog.pagination') }}"></nav>
      </section>
    </div>
  </main>
@endsection

@push('scripts')
  <script src="@asset('assets/js/pages/catalog.js')"></script>
@endpush
