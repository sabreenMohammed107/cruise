{{-- Admin sidebar from App\Admin\Menu: links with an icon, and groups that open and close (the current one starts open). --}}
<nav class="grid gap-1 text-sm" aria-label="Admin menu">
  @foreach ($menu as $node)
    @if (isset($node['children']))
      <details class="admin-group group/menu" @if ($node['active']) open @endif>
        <summary class="side-link !py-2.5 cursor-pointer list-none select-none {{ $node['active'] ? '!text-ocean-700' : '' }}">
          <i data-icon="{{ $node['icon'] }}" class="w-5 h-5 shrink-0"></i>
          <span class="flex-1">{{ $node['label'] }}</span>
          <svg class="admin-arrow w-4 h-4 shrink-0 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
        </summary>
        <div class="grid gap-0.5 mt-0.5 mb-1 ms-[1.35rem] ps-3 border-s border-line">
          @foreach ($node['children'] as $child)
            <a href="{{ $child['href'] }}" @if ($child['active']) aria-current="page" @endif
               class="flex items-center gap-2.5 rounded-lg px-3 py-2 font-semibold transition {{ $child['active'] ? 'bg-ocean-50 text-ocean-700' : 'text-muted hover:text-ocean-700 hover:bg-ocean-50/60' }}">
              <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $child['active'] ? 'bg-ocean-600' : 'bg-current opacity-40' }}"></span>
              <span class="flex-1">{{ $child['label'] }}</span>
              @if (! empty($child['badge']))<span class="min-w-5 h-5 px-1.5 rounded-full bg-sun-500 text-white text-xs grid place-items-center tabular" title="{{ $child['badge_title'] ?? '' }}">{{ $child['badge'] }}</span>@endif
            </a>
          @endforeach
        </div>
      </details>
    @else
      <a href="{{ $node['href'] }}" class="side-link !py-2.5 {{ $node['active'] ? 'is-active' : '' }}" @if ($node['active']) aria-current="page" @endif>
        <i data-icon="{{ $node['icon'] }}" class="w-5 h-5 shrink-0"></i>
        <span class="flex-1">{{ $node['label'] }}</span>
        @if (! empty($node['badge']))<span class="min-w-5 h-5 px-1.5 rounded-full bg-sun-500 text-white text-xs grid place-items-center tabular" title="{{ $node['badge_title'] ?? 'Awaiting payment' }}">{{ $node['badge'] }}</span>@endif
      </a>
    @endif
  @endforeach
</nav>
