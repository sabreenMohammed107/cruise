{{-- Converted from legacy-html/destinations.html. Heading and closing call to action come from $sections
     (Admin → Cruise pages → Destinations); the ports themselves from Admin → Destinations (destinations.js). --}}
@extends('layouts.app')

@section('title', __('pages.destinations.title'))
@section('description', __('pages.destinations.description'))
@section('nav', 'destinations')

@section('content')

  <main class="flex-1">
    <!-- =========================================================
         PAGE HEAD + COUNTRY FILTER
         ========================================================= -->
    <section class="bg-ocean-700 text-white" @unless ($sections['head']->is_visible) hidden @endunless>
      <div class="max-w-site mx-auto px-4 sm:px-6 py-12 sm:py-16">
        @include('partials.breadcrumb', ['tone' => 'light', 'items' => [[__('pages.breadcrumb.home'), route('home')], [__('pages.breadcrumb.destinations'), null]]])
        <h1 class="font-display font-bold text-4xl sm:text-5xl">{{ $sections['head']->bi('title') }}</h1>
        @if ($sections['head']->filledBi('body'))<p class="mt-3 text-ocean-100 max-w-[60ch]">{{ $sections['head']->bi('body') }}</p>@endif
        <div class="mt-8 flex flex-wrap gap-2" role="tablist" aria-label="{{ __('pages.destinations.filter_country') }}" id="country-filter"></div>
      </div>
    </section>

    <!-- Quick jump list -->
    <div class="sticky top-[72px] z-30 bg-white/95 backdrop-blur border-b border-line">
      <nav class="max-w-site mx-auto px-4 sm:px-6 flex gap-1 overflow-x-auto no-scrollbar" aria-label="{{ __('pages.destinations.ports') }}" id="port-nav"></nav>
    </div>

    <!-- Destination rows -->
    <section class="max-w-site mx-auto px-4 sm:px-6 py-14 sm:py-20">
      <div id="dest-list" class="grid gap-16 sm:gap-24"></div>
    </section>

    <!-- CTA -->
    <section class="max-w-site mx-auto px-4 sm:px-6 pb-20 sm:pb-24" @unless ($sections['cta']->is_visible) hidden @endunless>
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
  <script src="@asset('assets/js/pages/destinations.js')"></script>
@endpush
