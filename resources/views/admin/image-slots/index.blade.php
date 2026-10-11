{{-- Replaces legacy-html/photos.html: see which slots have an official photo, upload one, or add your own slots. --}}
@extends('layouts.admin')

@section('title', 'Photo slots')

@php($fileInput = 'text-sm file:me-3 file:rounded-full file:border-0 file:bg-ocean-50 file:px-3 file:py-1.5 file:font-semibold file:text-ocean-700')

@section('content')
  <h1 class="font-display font-bold text-2xl sm:text-3xl">Photo slots</h1>
  <p class="text-muted mt-2 max-w-[75ch]">Every image on the site uses one of these slots. Upload the official AROYA photo for a slot and it is used everywhere that slot appears. Empty slots show a stock photo, then a drawing.</p>
  <p class="mt-3 font-bold">{{ $filled }} of {{ $slots->count() }} slots have an official photo.</p>

  {{-- Add a slot --}}
  <details class="card mt-6 p-5 sm:p-6" @if ($errors->hasAny(['label', 'key', 'recommended_size']) || ($errors->has('photo') && old('label'))) open @endif>
    <summary class="cursor-pointer font-display font-semibold text-lg flex items-center gap-2"><i data-icon="plus" class="w-5 h-5"></i> Add a photo slot</summary>
    <p class="text-sm text-muted mt-2 max-w-[75ch]">A new slot with its own photo. After adding it, choose it in any photo or gallery field (cruises, cabins, destinations, ship). You can also upload photos straight from a gallery field; they are added here automatically.</p>
    <form method="POST" action="{{ route('admin.image-slots.store') }}" enctype="multipart/form-data" class="mt-5 grid sm:grid-cols-2 gap-5" novalidate>
      @csrf
      <div>
        <label class="label" for="new-label">Name</label>
        <input id="new-label" name="label" class="field {{ $errors->has('label') ? 'is-invalid' : '' }}" value="{{ old('label') }}" placeholder="e.g. Kids club, morning" required>
        @if ($e = $errors->first('label'))<p class="err show">{{ $e }}</p>@endif
      </div>
      <div>
        <label class="label" for="new-key">Key (optional)</label>
        <input id="new-key" name="key" class="field {{ $errors->has('key') ? 'is-invalid' : '' }}" value="{{ old('key') }}" placeholder="kids-club">
        @if ($e = $errors->first('key'))<p class="err show">{{ $e }}</p>@else<p class="text-xs text-muted mt-1.5">Letters, numbers and dashes. Made from the name if left empty.</p>@endif
      </div>
      <div>
        <label class="label" for="new-size">Recommended size (optional)</label>
        <input id="new-size" name="recommended_size" class="field" value="{{ old('recommended_size') }}" placeholder="1600×1000">
      </div>
      <div>
        <label class="label" for="new-photo">Photo</label>
        <input id="new-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required class="{{ $fileInput }}">
        @if (old('label') && ($e = $errors->first('photo')))<p class="err show">{{ $e }}</p>@else<p class="text-xs text-muted mt-1.5">JPG, PNG or WebP, up to 5 MB.</p>@endif
      </div>
      <div class="sm:col-span-2 flex justify-end"><button class="btn btn-primary">Add slot</button></div>
    </form>
  </details>

  <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($slots as $slot)
      @php($uses = $usage[$slot->key] ?? [])
      <article class="card overflow-hidden flex flex-col scroll-mt-24" id="slot-{{ $slot->key }}">
        <div class="aspect-[16/10] bg-ocean-50">@if ($url = $slot->url())<img src="{{ $url }}" alt="" class="w-full h-full object-cover" loading="lazy">@endif</div>
        <div class="p-4 flex-1 flex flex-col">
          <p class="font-bold tabular flex items-center gap-2">{{ $slot->key }} @if ($slot->is_custom)<span class="tag tag-ocean !text-[11px]">Added by you</span>@endif</p>
          <p class="text-sm text-muted">{{ $slot->label }}</p>
          @if ($slot->recommended_size)<p class="text-xs text-muted mt-1">Recommended: {{ $slot->recommended_size }} px or larger</p>@endif
          <p class="mt-3 text-sm font-bold">
            @if ($slot->isFilled())
              <span class="text-[#12805C]">✓ {{ $slot->is_custom ? 'Photo in use' : 'Official photo in use' }}</span>
            @else
              <span class="text-sun-600">Empty: showing fallback</span>
            @endif
          </p>
          <p class="mt-1 text-xs text-muted">
            @if ($uses)
              Used by: {{ implode(', ', $uses) }}
            @else
              Not used on any page yet
            @endif
          </p>
          <form method="POST" action="{{ route('admin.image-slots.update', $slot) }}" enctype="multipart/form-data" class="mt-3 grid gap-2">
            @csrf
            <label class="sr-only" for="photo-{{ $slot->key }}">Photo for {{ $slot->label }}</label>
            <input id="photo-{{ $slot->key }}" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required class="{{ $fileInput }}">
            <button class="btn btn-primary btn-sm">Replace photo</button>
          </form>
          <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
            {{-- Any slot can be deleted once nothing uses it; the server explains where it is still used --}}
            <form method="POST" action="{{ route('admin.image-slots.remove', $slot) }}" onsubmit="return confirm('{{ $uses ? 'This slot is still used, so it will not be deleted until you choose another photo there. Continue?' : 'Delete this photo slot?' }}')">
              @csrf @method('DELETE')
              <button class="text-xs text-muted hover:text-[#C42F2F] underline">Delete slot</button>
            </form>
            @if ($slot->path && ! $slot->is_custom)
              <form method="POST" action="{{ route('admin.image-slots.destroy', $slot) }}" onsubmit="return confirm('Remove the uploaded photo? The slot shows its stock photo again.')">
                @csrf @method('DELETE')
                <button class="text-xs text-muted hover:text-[#C42F2F] underline">Remove uploaded photo</button>
              </form>
            @endif
          </div>
        </div>
      </article>
    @endforeach
  </div>
@endsection
