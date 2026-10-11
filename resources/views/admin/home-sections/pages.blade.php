{{-- Cruise pages: the sections of the ship page, cruises list, cruise details and destinations (config/pages.php). --}}
@extends('layouts.admin')

@section('title', 'Cruise pages')

@section('content')
  <h1 class="font-display font-bold text-2xl sm:text-3xl">Cruise pages</h1>
  <p class="text-muted mt-2 max-w-[75ch]">Text, buttons and photos of each ship section, category section, cruise section and destination section, in page order. Sailings, facilities, cabins, destinations and testimonials themselves come from their own screens; here you edit the headings around them. Every text has an English and an Arabic version.</p>

  {{-- Jump to a page --}}
  <nav class="mt-5 flex flex-wrap gap-2" aria-label="Pages">
    @foreach ($pages as $key => $page)
      <a href="#page-{{ $key }}" class="chip">{{ $page['config']['name'] }}</a>
    @endforeach
  </nav>

  @foreach ($pages as $key => $page)
    <section id="page-{{ $key }}" class="scroll-mt-24 mt-10">
      <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <h2 class="font-display font-semibold text-xl">{{ $page['config']['name'] }}</h2>
        @if ($page['config']['route'])
          <a href="{{ route($page['config']['route']) }}" target="_blank" class="btn btn-outline btn-sm"><i data-icon="external" class="w-4 h-4"></i> View page</a>
        @else
          <span class="text-xs text-muted">Used on every cruise's page</span>
        @endif
      </div>
      @include('admin.home-sections.table', ['sections' => $page['sections']])
    </section>
  @endforeach
@endsection
