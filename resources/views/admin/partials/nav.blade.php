<nav class="grid gap-0.5">
  @foreach ($groups as $group => $items)
    @if ($group)
      <p class="px-3 pt-4 pb-1 text-[11px] font-bold uppercase tracking-wide text-muted">{{ $group }}</p>
    @endif
    @foreach ($items as $item)
      <a href="{{ $item[1] }}" class="side-link {{ $item[3] }} !py-2 text-sm">
        @if ($item[2])<i data-icon="{{ $item[2] }}" class="w-4 h-4"></i>@endif
        <span class="flex-1">{{ $item[0] }}</span>
        @if (! empty($item[4]))<span class="min-w-5 h-5 px-1.5 rounded-full bg-sun-500 text-white text-xs grid place-items-center tabular" title="Awaiting payment">{{ $item[4] }}</span>@endif
      </a>
    @endforeach
  @endforeach
</nav>
