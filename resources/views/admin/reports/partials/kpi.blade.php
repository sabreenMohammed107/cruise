{{-- One figure with its change against the comparison period. $invert: a rise is bad news (shown red). --}}
@php
  $up = $change !== null && $change > 0.05;
  $down = $change !== null && $change < -0.05;
  $good = ($invert ?? false) ? $down : $up;
  $bad = ($invert ?? false) ? $up : $down;
@endphp
<div class="card p-5">
  <div class="flex items-center gap-2 text-sm text-muted">
    <i data-icon="{{ $icon }}" class="w-4 h-4 text-ocean-600"></i> {{ $label }}
  </div>
  <p class="font-display font-bold text-2xl tabular mt-2">{{ $value }}</p>
  <p class="text-xs mt-1 tabular">
    @if ($change !== null)
      <span class="font-bold {{ $good ? 'text-[#12805C]' : ($bad ? 'text-[#B42318]' : 'text-muted') }}">{{ $up ? '▲' : ($down ? '▼' : '■') }} {{ number_format(abs($change), 1) }}%</span>
    @endif
    @isset($previous)<span class="text-muted">{{ $change === null ? 'Before:' : 'vs' }} {{ $previous }}</span>@endisset
    @isset($hint)<span class="block text-muted mt-0.5">{{ $hint }}</span>@endisset
  </p>
</div>
