{{-- Route builder for CruiseResource: ordered stops picked from Route ports (saved by CruiseResource::afterSave). --}}
@php
  $ports = \App\Models\Port::ordered()->get();
  $byId = $ports->keyBy('id');
  $stops = collect(old('route_ports', $record ? $record->ports->pluck('id')->all() : []))
      ->map(fn ($id) => $byId->get((int) $id))->filter()->values();
  $error = $errors->first('route_ports') ?: $errors->first('route_ports.*');
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h2 class="font-display font-semibold text-lg">Route</h2>
    <p class="text-sm text-muted">The ports in sailing order, from the first to the last (a round trip starts and ends at the same port).
      Drag a stop or use the arrows to change the order. English and Arabic names come from
      <a href="{{ route('admin.routes.index') }}" class="text-ocean-600 font-semibold hover:underline">Route ports</a>.</p>
  </div>
</div>

<div class="mt-4 flex flex-wrap gap-2">
  <label for="route-port-pick" class="sr-only">Port to add</label>
  <select id="route-port-pick" class="field !py-2 sm:max-w-xs">
    <option value="">Choose a port…</option>
    @foreach ($ports->where('is_active', true) as $port)
      <option value="{{ $port->id }}" data-en="{{ $port->name_en }}" data-ar="{{ $port->name_ar }}">{{ $port->name_en }}{{ $port->name_ar ? ' · '.$port->name_ar : '' }}</option>
    @endforeach
  </select>
  <button type="button" class="btn btn-outline btn-sm" data-add-stop><i data-icon="plus" class="w-4 h-4"></i> Add stop</button>
</div>

<ol class="mt-4 grid gap-2" id="route-stops" aria-label="Route stops">
  @foreach ($stops as $port)
    @include('admin.cruises.route-stop', ['id' => $port->id, 'en' => $port->name_en, 'ar' => $port->name_ar, 'n' => $loop->iteration])
  @endforeach
</ol>
<p class="text-sm text-muted mt-2 {{ $stops->isEmpty() ? '' : 'hidden' }}" id="route-empty">No stops yet. Choose a port and press “Add stop”.</p>
@if ($error)
  <p class="err show">{{ $error }}</p>
@endif

<template id="route-stop-template">
  @include('admin.cruises.route-stop', ['id' => '', 'en' => '', 'ar' => '', 'n' => ''])
</template>

@push('scripts')
<script>
  (() => {
    const list = document.getElementById('route-stops');
    const pick = document.getElementById('route-port-pick');
    const empty = document.getElementById('route-empty');

    // Number the stops and mark where the cruise starts and ends
    const renumber = () => {
      const rows = [...list.children];
      rows.forEach((row, i) => {
        row.querySelector('[data-stop-n]').textContent = i + 1;
        row.querySelector('[data-stop-role]').textContent = i === 0 ? 'Departs' : (i === rows.length - 1 ? 'Arrives' : '');
        row.querySelector('[data-move="-1"]').disabled = i === 0;
        row.querySelector('[data-move="1"]').disabled = i === rows.length - 1;
      });
      empty.classList.toggle('hidden', rows.length > 0);
    };

    document.querySelector('[data-add-stop]').addEventListener('click', () => {
      const opt = pick.selectedOptions[0];
      if (!opt || !opt.value) { pick.focus(); return; }
      const row = document.getElementById('route-stop-template').content.firstElementChild.cloneNode(true);
      row.querySelector('input').value = opt.value;
      row.querySelector('[data-en]').textContent = opt.dataset.en;
      row.querySelector('[data-ar]').textContent = opt.dataset.ar || '';
      list.appendChild(row);
      pick.value = '';
      renumber();
    });

    list.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;
      const row = btn.closest('[data-stop]');
      if (btn.hasAttribute('data-remove')) row.remove();
      else if (btn.dataset.move === '-1' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
      else if (btn.dataset.move === '1' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
      renumber();
      if (row.isConnected) btn.disabled ? row.querySelector('[data-remove]').focus() : btn.focus();
    });

    // Drag and drop reordering
    let dragged = null;
    list.addEventListener('dragstart', (e) => {
      dragged = e.target.closest('[data-stop]');
      e.dataTransfer.effectAllowed = 'move';
      requestAnimationFrame(() => dragged && dragged.classList.add('opacity-40'));
    });
    list.addEventListener('dragover', (e) => {
      const over = e.target.closest('[data-stop]');
      if (!dragged || !over || over === dragged) return;
      e.preventDefault();
      const after = e.clientY > over.getBoundingClientRect().top + over.offsetHeight / 2;
      list.insertBefore(dragged, after ? over.nextElementSibling : over);
    });
    list.addEventListener('dragend', () => {
      dragged && dragged.classList.remove('opacity-40');
      dragged = null;
      renumber();
    });

    renumber();
  })();
</script>
@endpush
