{{-- Homepage content: the sections of pages/home.blade.php in page order. --}}
@extends('layouts.admin')

@section('title', 'Homepage')

@section('content')
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">Homepage</h1>
      <p class="text-muted mt-2 max-w-[75ch]">Text, buttons and photos of each homepage section, in page order. Sailings, facilities, cabins, destinations and testimonials themselves come from their own screens; here you edit the headings around them.</p>
    </div>
    <a href="{{ route('home') }}" target="_blank" class="btn btn-outline btn-sm"><i data-icon="external" class="w-4 h-4"></i> View homepage</a>
  </div>

  <div class="mt-6">@include('admin.home-sections.table', ['sections' => $sections])</div>
@endsection
