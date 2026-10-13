{{--
    Photo field with two sources: an upload from the device, or a photo picked from the shared media library
    (admin.partials.media-library). Used by the image / images field types.

    The picked photos post as "{name}" / "{name}[]" (paths under public/ or photo URLs, see MediaLibrary);
    uploads post as "{name}_upload" / "{name}_upload[]" and are saved to the library by Resource::fill().
    A gallery is ordered by dragging its photos; uploads are added after them.

    @include('admin.partials.media-picker', ['name' => 'gallery', 'label' => 'Gallery', 'value' => [...], 'multiple' => true])
--}}
@php
  use App\Support\MediaLibrary;
  $multiple = $multiple ?? false;
  $values = array_values(array_filter((array) $value));
  $picked = array_map(fn ($v) => ['value' => $v, 'url' => MediaLibrary::url($v)], $values);
  $uploadError = $errors->first($name.'_upload') ?: $errors->first($name.'_upload.*');
@endphp

<div data-media-picker data-multiple="{{ $multiple ? '1' : '0' }}" data-name="{{ $multiple ? $name.'[]' : $name }}" data-label="{{ $label }}"
     data-max-bytes="{{ MediaLibrary::maxUploadBytes() }}" data-max-label="{{ MediaLibrary::maxUploadLabel() }}">
  @if ($multiple)
    {{-- Current gallery, in order: drag to reorder, × to remove --}}
    <ol data-role="list" class="flex flex-wrap gap-3 empty:hidden" aria-label="{{ $label }}: chosen photos">
      @foreach ($picked as $i => $photo)
        <li data-role="item" draggable="true" class="group relative w-32 cursor-move">
          <input type="hidden" name="{{ $name }}[]" value="{{ $photo['value'] }}">
          <div class="w-32 h-24 rounded-xl overflow-hidden bg-ocean-50 ring-1 ring-line">
            @if ($photo['url'])<img src="{{ $photo['url'] }}" alt="" class="w-full h-full object-cover pointer-events-none">@endif
          </div>
          <span data-role="number" class="absolute top-1.5 start-1.5 rounded-full bg-ocean-900/75 text-white text-[11px] font-bold px-2 py-0.5">{{ $i + 1 }}</span>
          <button type="button" data-role="remove" class="absolute top-1.5 end-1.5 w-6 h-6 rounded-full bg-white/95 text-red-600 shadow grid place-items-center hover:bg-red-600 hover:text-white" aria-label="Remove photo {{ $i + 1 }}">
            <i data-icon="x" class="w-3.5 h-3.5"></i>
          </button>
        </li>
      @endforeach
    </ol>
    <p data-role="empty" class="text-sm text-muted rounded-xl border border-dashed border-line p-4 text-center {{ $picked ? 'hidden' : '' }}">No photos yet. Choose from the library or upload new ones.</p>

    {{-- New uploads waiting for "Save": shown after the gallery --}}
    <div data-role="uploads" class="flex flex-wrap gap-3 mt-3 empty:hidden"></div>

    <div class="mt-3 rounded-xl border border-dashed border-ocean-300 bg-ocean-50/40 p-3">
      <div class="flex flex-wrap items-center gap-3">
        <button type="button" data-role="open-library" class="btn btn-outline btn-sm"><i data-icon="image" class="w-4 h-4"></i> Choose from library</button>
        <label class="btn btn-ghost btn-sm cursor-pointer" for="f-{{ $name }}-upload"><i data-icon="plus" class="w-4 h-4"></i> Upload new photos</label>
        <input id="f-{{ $name }}-upload" data-role="file" type="file" name="{{ $name }}_upload[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only">
      </div>
      <p class="text-xs text-muted mt-2">Drag the photos to change their order. Uploads (JPG, PNG or WebP, up to {{ MediaLibrary::maxUploadLabel() }} each) are added at the end when you save, and are kept in the library so you can reuse them.</p>
    </div>
  @else
    @php($current = $picked[0] ?? null)
    <div class="flex flex-wrap gap-4 items-start">
      <div class="w-40 h-28 shrink-0 rounded-xl overflow-hidden bg-ocean-50 ring-1 ring-line grid place-items-center">
        <img data-role="preview" src="{{ $current['url'] ?? '' }}" alt="" class="w-full h-full object-cover {{ ($current['url'] ?? null) ? '' : 'hidden' }}">
        <span data-role="placeholder" class="text-xs text-muted {{ ($current['url'] ?? null) ? 'hidden' : '' }}">No photo</span>
      </div>
      <div class="flex flex-col gap-2 min-w-0">
        <input type="hidden" id="f-{{ $name }}" name="{{ $name }}" data-role="value" value="{{ $current['value'] ?? '' }}">
        <button type="button" data-role="open-library" class="btn btn-outline btn-sm"><i data-icon="image" class="w-4 h-4"></i> Choose from library</button>
        <label class="btn btn-ghost btn-sm cursor-pointer" for="f-{{ $name }}-upload"><i data-icon="plus" class="w-4 h-4"></i> Upload a photo</label>
        <input id="f-{{ $name }}-upload" data-role="file" type="file" name="{{ $name }}_upload" accept="image/jpeg,image/png,image/webp" class="sr-only">
        <button type="button" data-role="clear" class="text-xs font-semibold text-red-600 hover:underline text-start {{ $current ? '' : 'hidden' }}">Remove photo</button>
      </div>
    </div>
    <p data-role="source" class="text-xs text-muted mt-2 break-all"></p>
    <p class="text-xs text-muted mt-1">JPG, PNG or WebP, up to {{ MediaLibrary::maxUploadLabel() }}.</p>
  @endif

  <p data-role="upload-error" class="err {{ $uploadError ? 'show' : '' }}">{{ $uploadError }}</p>
</div>

@once
  @push('scripts')
    @include('admin.partials.media-library')
  @endpush
@endonce
