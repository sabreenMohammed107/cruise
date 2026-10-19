{{-- One stop of the route builder (admin.cruises.route); also its <template> for new stops. --}}
<li class="rounded-xl border border-line bg-white px-3 py-2 flex items-center gap-3" draggable="true" data-stop>
  <span class="cursor-grab select-none text-muted text-lg leading-none" title="Drag to reorder" aria-hidden="true">⠿</span>
  <span class="w-6 text-center font-bold text-sm text-muted" data-stop-n>{{ $n }}</span>
  <span class="flex-1 min-w-0 flex flex-wrap items-baseline gap-x-3">
    <span class="font-semibold" data-en>{{ $en }}</span>
    <span class="text-muted" dir="rtl" lang="ar" data-ar>{{ $ar }}</span>
    <span class="text-xs font-bold text-lagoon-600" data-stop-role></span>
  </span>
  <input type="hidden" name="route_ports[]" value="{{ $id }}">
  <button type="button" class="btn btn-ghost btn-sm !px-2" data-move="-1" aria-label="Move up" title="Move up">↑</button>
  <button type="button" class="btn btn-ghost btn-sm !px-2" data-move="1" aria-label="Move down" title="Move down">↓</button>
  <button type="button" class="btn btn-ghost btn-sm !px-2 text-[#C42F2F]" data-remove aria-label="Remove stop" title="Remove">×</button>
</li>
