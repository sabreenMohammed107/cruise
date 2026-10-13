{{-- Edit one page section (homepage: config/home.php, other pages: config/pages.php). Only the section's fields are shown. --}}
@extends('layouts.admin')

@php
  $err = fn (string $name) => $errors->first($name);
  $invalid = fn (string $name) => $errors->has($name) ? 'is-invalid' : '';
  $imageUrl = $section->imageUrl();
  $isHome = $section->page === 'home';
  $pageName = $isHome ? 'Homepage' : 'Cruise pages';
  $backUrl = $isHome ? route('admin.home-sections.index') : route('admin.pages.index').'#page-'.$section->page;
  $saveUrl = $isHome ? route('admin.home-sections.update', $section->key) : route('admin.pages.update', [$section->page, $section->key]);
  $pageLabel = \App\Models\HomeSection::pageConfig($section->page)['name'];
@endphp

@section('title', $pageLabel.': '.$section->name())

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb">
    <a href="{{ $backUrl }}" class="hover:text-ocean-600">{{ $pageName }}</a> <span class="mx-1">/</span>
    @unless ($isHome)<span>{{ $pageLabel }}</span> <span class="mx-1">/</span>@endunless
    <span class="text-ink">{{ $section->name() }}</span>
  </nav>
  <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $section->name() }}</h1>
  @if ($help = $section->config('help'))<p class="text-muted mt-2 max-w-[75ch]">{{ $help }}</p>@endif

  <form method="POST" action="{{ $saveUrl }}" enctype="multipart/form-data" class="card p-5 sm:p-6 mt-6" novalidate>
    @csrf @method('PUT')

    <div class="grid sm:grid-cols-2 gap-5">
      @if ($section->supports('eyebrow'))
        @include('admin.home-sections.bi-field', ['name' => 'eyebrow', 'label' => $section->label('eyebrow', 'Small label above the heading'), 'model' => $section])
      @endif

      @if ($section->supports('title'))
        @include('admin.home-sections.bi-field', ['name' => 'title', 'label' => $section->label('title', 'Heading'), 'model' => $section, 'required' => true])
      @endif

      @if ($section->supports('body'))
        @include('admin.home-sections.bi-field', ['name' => 'body', 'label' => $section->label('body', 'Text under the heading'), 'model' => $section, 'rows' => 3])
      @endif

      @foreach (['primary' => 'Main button', 'secondary' => 'Second button'] as $button => $buttonLabel)
        @continue(! $section->supports($button))
        @include('admin.home-sections.bi-field', ['name' => $button.'_label', 'label' => $buttonLabel.': text', 'model' => $section])
        @if ($section->hasLinks())
          <div class="sm:col-span-2">
            <label class="label" for="f-{{ $button }}-url">{{ $buttonLabel }}: link</label>
            <input id="f-{{ $button }}-url" name="{{ $button }}_url" class="field {{ $invalid($button.'_url') }}" value="{{ old($button.'_url', $section->{$button.'_url'}) }}" placeholder="/cruises">
            @if ($e = $err($button.'_url'))
              <p class="err show">{{ $e }}</p>
            @else
              <p class="text-xs text-muted mt-1.5">Same link for both languages. A page on this site (/cruises, /ship), a section (#ship) or a full https:// address.</p>
            @endif
          </div>
        @endif
      @endforeach

      @if ($section->supports('caption'))
        @include('admin.home-sections.bi-field', ['name' => 'caption_label', 'label' => $section->label('caption_label', 'Photo caption: small label'), 'model' => $section])
        @include('admin.home-sections.bi-field', ['name' => 'caption_value', 'label' => $section->label('caption_value', 'Photo caption: text'), 'model' => $section])
      @endif

      @if ($section->supports('subheading'))
        @include('admin.home-sections.bi-field', ['name' => 'caption_label', 'label' => $section->label('caption_label', 'Second heading'), 'model' => $section])
      @endif
    </div>

    @if ($section->supports('image'))
      <fieldset class="mt-8 pt-6 border-t border-line">
        <legend class="font-display font-semibold text-lg">Photo</legend>
        <div class="mt-3 grid md:grid-cols-[280px_1fr] gap-5 items-start">
          <div class="aspect-[16/10] rounded-2xl overflow-hidden bg-ocean-50">
            @if ($imageUrl)<img src="{{ $imageUrl }}" alt="" class="w-full h-full object-cover">@endif
          </div>
          <div class="grid gap-4">
            <p class="text-sm font-bold">
              @if ($section->hasUpload())
                <span class="text-[#12805C]">✓ Uploaded photo in use</span>
              @else
                <span class="text-muted">Showing the default photo{{ isset($section->config('image')['ship']) ? ' (the ship’s '.($section->config('image')['ship'] === 'hero_image' ? 'hero' : 'side view').' photo from Admin → Ships)' : '' }}</span>
              @endif
            </p>
            <div>
              <label class="label" for="f-image">Upload a new photo</label>
              <input id="f-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="text-sm file:me-3 file:rounded-full file:border-0 file:bg-ocean-50 file:px-3 file:py-1.5 file:font-semibold file:text-ocean-700">
              @if ($e = $err('image'))
                <p class="err show">{{ $e }}</p>
              @else
                <p class="text-xs text-muted mt-1.5">JPG, PNG or WebP, up to 5 MB. Landscape, at least 1600 px wide for the hero.</p>
              @endif
            </div>
            <div class="grid sm:grid-cols-2 gap-5">
              @include('admin.home-sections.bi-field', ['name' => 'image_alt', 'label' => 'Photo description', 'model' => $section])
            </div>
            <p class="text-xs text-muted -mt-2">Read out by screen readers and used by search engines. The photo itself is the same in both languages.</p>
            @if ($section->hasUpload())
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="remove_image" value="1" class="check">
                <span class="font-semibold">Remove the uploaded photo and use the default again</span>
              </label>
            @endif
          </div>
        </div>
      </fieldset>
    @endif

    @if ($section->supports('items'))
      <fieldset class="mt-8 pt-6 border-t border-line">
        <legend class="font-display font-semibold text-lg">Cards</legend>
        <p class="text-sm text-muted mt-1">Shown in order (lowest number first). Tick “Delete” to remove a card when you save.</p>

        <div class="mt-4 grid gap-4" id="items">
          @foreach ($section->items as $item)
            @php($p = "items.{$item->id}")
            <div class="rounded-2xl border border-line p-4 grid sm:grid-cols-[1fr_1fr_110px_90px] gap-3 items-start">
              @include('admin.home-sections.bi-field', ['name' => "items[{$item->id}][title]", 'oldKey' => $p.'.title', 'label' => 'Title', 'model' => $item])
              <div>
                <label class="label" for="i-{{ $item->id }}-icon">Icon</label>
                <select id="i-{{ $item->id }}-icon" name="items[{{ $item->id }}][icon]" class="field">
                  @foreach ($icons as $icon)
                    <option value="{{ $icon }}" @selected(old($p.'.icon', $item->icon) === $icon)>{{ $icon }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="label" for="i-{{ $item->id }}-order">Order</label>
                <input id="i-{{ $item->id }}-order" type="number" min="0" name="items[{{ $item->id }}][sort_order]" class="field" value="{{ old($p.'.sort_order', $item->sort_order) }}">
              </div>
              @include('admin.home-sections.bi-field', ['name' => "items[{$item->id}][text]", 'oldKey' => $p.'.text', 'label' => 'Text', 'model' => $item, 'rows' => 2])
              <div>
                <label class="label" for="i-{{ $item->id }}-tone">Colour</label>
                <select id="i-{{ $item->id }}-tone" name="items[{{ $item->id }}][tone]" class="field">
                  @foreach ($tones as $tone => $toneLabel)
                    <option value="{{ $tone }}" @selected(old($p.'.tone', $item->tone) === $tone)>{{ $toneLabel }}</option>
                  @endforeach
                </select>
              </div>
              <label class="flex items-center gap-2 cursor-pointer mt-8 text-sm text-[#C42F2F] font-semibold">
                <input type="hidden" name="items[{{ $item->id }}][delete]" value="0">
                <input type="checkbox" name="items[{{ $item->id }}][delete]" value="1" class="check" @checked(old($p.'.delete'))> Delete
              </label>
            </div>
          @endforeach
          @foreach (old('new_items', []) as $n => $values)
            @include('admin.home-sections.new-item', ['n' => $n, 'values' => $values])
          @endforeach
        </div>

        <template id="new-item">@include('admin.home-sections.new-item', ['n' => '__N__', 'values' => []])</template>
        <button type="button" class="btn btn-outline btn-sm mt-4" id="add-item"><i data-icon="plus" class="w-4 h-4"></i> Add a card</button>
      </fieldset>
    @endif

    @if ($section->config('can_hide', true))
    <div class="mt-8 pt-6 border-t border-line">
      <input type="hidden" name="is_visible" value="0">
      <label class="flex items-center gap-3 cursor-pointer">
        <input type="checkbox" name="is_visible" value="1" class="check" @checked(old('is_visible', $section->is_visible))>
        <span class="font-semibold">Show this section on the {{ $isHome ? 'homepage' : 'page' }}</span>
      </label>
    </div>
    @endif

    <div class="mt-8 flex flex-wrap justify-between gap-3">
      <a href="{{ $backUrl }}" class="btn btn-outline">Back to {{ strtolower($pageName) }}</a>
      <button class="btn btn-primary">Save changes</button>
    </div>
  </form>
@endsection

@if ($section->supports('items'))
  @push('scripts')
    <script>
      (() => {
        let n = {{ (int) collect(old('new_items', []))->keys()->map(fn ($k) => (int) $k)->max() + 1 }};
        document.getElementById('add-item').addEventListener('click', () => {
          const html = document.getElementById('new-item').innerHTML.replaceAll('__N__', n++);
          document.getElementById('items').insertAdjacentHTML('beforeend', html);
        });
      })();
    </script>
  @endpush
@endif
