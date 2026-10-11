{{-- Breadcrumb trail, translated (lang/{en,ar}/pages.php), plus schema.org BreadcrumbList for search engines.
     $items: list of [label, url|null]; the last item is the current page (no link).
     $tone: 'light' on dark heroes, 'muted' on the white background. --}}
@php
  $tone ??= 'muted';
  $classes = $tone === 'light'
      ? ['nav' => 'text-sm text-ocean-100', 'link' => 'hover:text-white', 'current' => 'text-white']
      : ['nav' => 'text-sm text-muted', 'link' => 'hover:text-ocean-600', 'current' => 'text-ink'];
  $items = array_values($items);
  $schema = [
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => collect($items)->map(fn ($item, $i) => array_filter([
          '@type' => 'ListItem', 'position' => $i + 1, 'name' => (string) $item[0], 'item' => $item[1] ?? url()->current(),
      ]))->all(),
  ];
@endphp
<nav class="{{ $classes['nav'] }} {{ $class ?? 'mb-3' }}" aria-label="{{ __('pages.breadcrumb.label') }}">
  <ol class="flex flex-wrap items-center gap-x-1">
    @foreach ($items as $i => [$label, $url])
      <li class="flex items-center gap-x-1">
        @if ($url && ! $loop->last)
          <a href="{{ $url }}" class="{{ $classes['link'] }}">{{ $label }}</a>
        @elseif ($loop->last)
          <span class="{{ $classes['current'] }}"{!! isset($currentId) ? ' id="'.e($currentId).'"' : '' !!} aria-current="page">{{ $label }}</span>
        @else
          <span>{{ $label }}</span>
        @endif
        @unless ($loop->last)<span class="mx-1" aria-hidden="true">/</span>@endunless
      </li>
    @endforeach
  </ol>
</nav>
@push('head')
  <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush
