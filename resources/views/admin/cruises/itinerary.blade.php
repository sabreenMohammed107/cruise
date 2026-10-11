{{-- Day-by-day itinerary editor for CruiseResource (saved by CruiseResource::afterSave). --}}
@php
  $days = old('itinerary', $record
      ? $record->itinerary->map(fn ($d) => ['port' => $d->port, 'time' => $d->time_label, 'time_ar' => $d->time_label_ar, 'text' => $d->description, 'text_ar' => $d->description_ar])->all()
      : []);
  $days = array_values($days);
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h2 class="font-display font-semibold text-lg">Day-by-day itinerary</h2>
    <p class="text-sm text-muted">One row per day, in order. Use "At sea" for sea days. Rows without a port are removed. The port name is shown in Arabic from Destinations; times and descriptions have an Arabic box (empty = English shown).</p>
  </div>
  <button type="button" class="btn btn-outline btn-sm" data-add-day><i data-icon="plus" class="w-4 h-4"></i> Add day</button>
</div>

<ol class="mt-4 grid gap-3" id="itinerary-rows">
  @foreach (array_merge($days, [['port' => '', 'time' => '', 'time_ar' => '', 'text' => '', 'text_ar' => '']]) as $i => $day)
    <li class="rounded-2xl border border-line p-4 grid sm:grid-cols-[110px_1fr_160px] gap-3 items-start" data-day>
      <p class="font-bold text-sm text-muted pt-2.5">Day <span data-day-n>{{ $i + 1 }}</span></p>
      <div class="grid gap-2">
        <input name="itinerary[{{ $i }}][port]" value="{{ $day['port'] }}" class="field !py-2" placeholder="Port, e.g. Safaga, Egypt" aria-label="Port">
        <textarea name="itinerary[{{ $i }}][text]" rows="2" class="field !py-2 text-sm" placeholder="What happens this day" aria-label="Description">{{ $day['text'] }}</textarea>
        <textarea name="itinerary[{{ $i }}][text_ar]" rows="2" class="field !py-2 text-sm" dir="rtl" lang="ar" placeholder="وصف اليوم (عربي)" aria-label="Description (Arabic)">{{ $day['text_ar'] ?? '' }}</textarea>
      </div>
      <div class="grid gap-2">
        <input name="itinerary[{{ $i }}][time]" value="{{ $day['time'] }}" class="field !py-2" placeholder="07:00 – 19:00" aria-label="Times">
        <input name="itinerary[{{ $i }}][time_ar]" value="{{ $day['time_ar'] ?? '' }}" class="field !py-2" dir="rtl" lang="ar" placeholder="الوقت (عربي)" aria-label="Times (Arabic)">
      </div>
    </li>
  @endforeach
</ol>

@push('scripts')
<script>
  // Clone the last (empty) row for a new day and renumber the input names
  document.querySelector('[data-add-day]').addEventListener('click', () => {
    const list = document.getElementById('itinerary-rows');
    const row = list.lastElementChild.cloneNode(true);
    const n = list.children.length;
    row.querySelectorAll('input, textarea').forEach((el) => { el.value = ''; el.name = el.name.replace(/itinerary\[\d+\]/, `itinerary[${n}]`); });
    row.querySelector('[data-day-n]').textContent = n + 1;
    list.appendChild(row);
    row.querySelector('input').focus();
  });
</script>
@endpush
