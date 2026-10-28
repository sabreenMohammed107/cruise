{{-- Converted from legacy-html/destinations.html. Heading and closing call to action come from $sections
     (Admin → Cruise pages → Destinations); the ports from Admin → Destinations.
     Step 21: rendered on the server (App\Support\DestinationsPage) so the page shows at once; each port lists its
     nearest sailings (DestinationsPage::PREVIEW) and links to the cruises list for the rest.
     destinations.js only filters by country. --}}
@extends('layouts.app')

@section('title', __('pages.destinations.title'))
@section('description', __('pages.destinations.description'))
@section('nav', 'destinations')

@php
  $arabic = app()->getLocale() === 'ar';
  // English country = filter key; label in the page language
  $countries = $ports->mapWithKeys(fn ($p) => [$p['destination']->country => $p['country']])->unique();
  $chip = 'chip !border-white/30';
@endphp

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
        @if ($countries->count() > 1)
          <div class="mt-8 flex flex-wrap gap-2" role="tablist" aria-label="{{ __('pages.destinations.filter_country') }}" id="country-filter">
            <button class="{{ $chip }} !bg-white !text-ocean-800" role="tab" aria-selected="true" data-country="">{{ __('ui.destinations.all_ports') }}</button>
            @foreach ($countries as $key => $label)
              <button class="{{ $chip }} !bg-white/10 !text-white" role="tab" aria-selected="false" data-country="{{ $key }}">{{ $label }}</button>
            @endforeach
          </div>
        @endif
      </div>
    </section>

    <!-- Quick jump list -->
    <div class="sticky top-[72px] z-30 bg-white/95 backdrop-blur border-b border-line">
      <nav class="max-w-site mx-auto px-4 sm:px-6 flex gap-1 overflow-x-auto no-scrollbar" aria-label="{{ __('pages.destinations.ports') }}" id="port-nav">
        @foreach ($ports as $port)
          <a href="#{{ $port['destination']->slug }}" data-country="{{ $port['destination']->country }}" class="shrink-0 px-4 py-3.5 font-semibold text-sm hover:text-ocean-600">{{ $port['name'] }}</a>
        @endforeach
      </nav>
    </div>

    <!-- Destination rows -->
    <section class="max-w-site mx-auto px-4 sm:px-6 py-12 sm:py-16">
      <div id="dest-list" class="grid gap-14 sm:gap-20">
        @foreach ($ports as $port)
          @php($d = $port['destination'])
          @php($highlights = $d->tr('highlights') ?? [])
          <article id="{{ $d->slug }}" data-country="{{ $d->country }}" class="scroll-mt-36">
            <div class="grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] gap-6 lg:gap-10 items-start">
              <div class="relative rounded-[26px] overflow-hidden aspect-[4/3] bg-ocean-50">
                @if ($image = \App\Support\MediaLibrary::url($d->image))
                  <img src="{{ $image }}" alt="{{ $port['name'] }}{{ $arabic ? '، ' : ', ' }}{{ $port['country'] }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" class="absolute inset-0 w-full h-full object-cover">
                @endif
                <span class="absolute top-4 start-4 tag tag-white"><i data-icon="pin" class="w-3.5 h-3.5"></i> {{ $port['country'] }}</span>
              </div>
              <div class="min-w-0">
                @if (! $arabic && $d->name_ar)<p class="text-muted" lang="ar" dir="rtl">{{ $d->name_ar }}</p>@endif
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                  <h2 class="font-display font-bold text-3xl sm:text-4xl">{{ $port['name'] }}</h2>
                  @if ($port['total'])
                    <span class="tag tag-ocean">{{ trans_choice('ui.destinations.sailings_count', $port['total'], ['count' => $port['total']]) }}</span>
                  @endif
                </div>
                @if ($d->filledBi('description'))<p class="mt-4 text-lg leading-relaxed text-ink/90 max-w-[62ch]">{{ $d->tr('description') }}</p>@endif

                @if ($highlights)
                  <h3 class="font-bold mt-6 mb-2">{{ __('ui.destinations.things_to_do') }}</h3>
                  <ul class="grid sm:grid-cols-2 gap-x-6 gap-y-2">
                    @foreach ($highlights as $h)
                      <li class="flex gap-2.5"><i data-icon="check" class="w-5 h-5 text-lagoon-600 shrink-0"></i><span>{{ $h }}</span></li>
                    @endforeach
                  </ul>
                @endif

                @if ($d->filledBi('visa_note'))
                  <div class="mt-6 rounded-2xl bg-ocean-50 p-4 flex gap-3 text-sm"><i data-icon="info" class="w-5 h-5 text-ocean-600 shrink-0"></i><p>{{ $d->tr('visa_note') }}</p></div>
                @endif
              </div>
            </div>

            {{-- Nearest sailings that call here; the rest are on the cruises list filtered by this port --}}
            <div class="mt-8">
              <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
                <h3 class="font-display font-semibold text-xl">{{ __('ui.destinations.cruises_here') }}</h3>
                @if ($port['total'] > $port['sailings']->count())
                  <p class="text-sm text-muted">{{ __('ui.destinations.showing_next', ['shown' => $port['sailings']->count(), 'total' => $port['total']]) }}</p>
                @endif
              </div>
              @if ($port['sailings']->isEmpty())
                <p class="text-muted">{{ __('ui.destinations.none') }}</p>
              @else
                <ul class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                  @foreach ($port['sailings'] as $s)
                    <li>
                      <a href="{{ $s['url'] }}" class="card card-hover h-full flex items-center gap-3 p-3 hover:border-ocean-400 transition">
                        @if ($s['image'])<img src="{{ $s['image'] }}" alt="" loading="lazy" class="w-24 h-20 sm:w-28 sm:h-24 rounded-xl object-cover shrink-0">@endif
                        <span class="min-w-0 flex-1">
                          <span class="block font-bold text-sm leading-snug line-clamp-2">{{ $s['name'] }}</span>
                          <span class="mt-1 flex items-center gap-1.5 text-xs text-muted"><i data-icon="calendar" class="w-3.5 h-3.5 shrink-0"></i>{{ $s['date']->translatedFormat('j M Y') }} · {{ trans_choice('site.nights', $s['nights'], ['count' => $s['nights']]) }}</span>
                          @if ($s['from'])
                            <span class="mt-1.5 block text-xs text-muted">{{ __('ui.destinations.from_pp') }} <b class="text-sm text-ocean-800 tabular" dir="ltr">{{ $s['from'] }}</b></span>
                          @endif
                          @if ($s['soldOut'])
                            <span class="mt-0.5 block text-xs font-bold text-rose-600">{{ __('ui.details.sold_out') }}</span>
                          @endif
                        </span>
                      </a>
                    </li>
                  @endforeach
                </ul>
                <a href="{{ route('cruises.index') }}?port={{ rawurlencode($d->name) }}" class="btn {{ $port['total'] > $port['sailings']->count() ? 'btn-primary' : 'btn-outline' }} mt-5">
                  {{ $port['total'] > $port['sailings']->count()
                      ? __('ui.destinations.see_all', ['count' => $port['total'], 'port' => $port['name']])
                      : __('ui.destinations.all_sailings_to', ['port' => $port['name']]) }}
                  <i data-icon="arrow-right" class="w-4 h-4 rtl:rotate-180"></i>
                </a>
              @endif
            </div>
          </article>
        @endforeach
      </div>
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
