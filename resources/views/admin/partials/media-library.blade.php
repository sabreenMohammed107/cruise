{{--
  Shared media library modal + the behaviour of every admin.partials.media-picker on the page. Pushed once
  to the end of <body> (outside the form). Images come from admin.media-library in pages of 24, newest
  first, loaded on first open, on "Load more" / scrolling down, and on search.
--}}
<style>
  #mediaLibrary .media-item.is-selected { outline: 3px solid #0E6BA8; outline-offset: -3px; }
  #mediaLibrary .media-item .media-check { display: none; }
  #mediaLibrary .media-item.is-selected .media-check { display: grid; }
  [data-media-picker] [data-role="item"].is-dragging { opacity: .4; }
</style>

<div id="mediaLibrary" class="fixed inset-0 z-[70] hidden" role="dialog" aria-modal="true" aria-labelledby="mediaLibraryTitle" data-url="{{ route('admin.media-library') }}">
  <div class="absolute inset-0 bg-ocean-900/60" data-role="close"></div>
  <div class="relative mx-auto my-6 w-[calc(100%-2rem)] max-w-5xl h-[calc(100vh-3rem)] card flex flex-col overflow-hidden">
    <div class="flex items-center gap-3 px-5 py-4 border-b border-line">
      <h2 id="mediaLibraryTitle" class="font-display font-bold text-lg">Media library <span data-role="total" class="text-sm font-normal text-muted"></span></h2>
      <button type="button" class="icon-btn ms-auto" data-role="close" aria-label="Close"><i data-icon="x" class="w-5 h-5"></i></button>
    </div>
    <div class="px-5 py-3 border-b border-line">
      <input type="search" data-role="search" class="field !py-2" placeholder="Search photos…" aria-label="Search photos">
    </div>
    <div class="flex-1 overflow-y-auto p-5" data-role="body">
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3" data-role="grid"></div>
      <div data-role="status" class="text-center text-muted py-10"></div>
      <div class="text-center pt-5 hidden" data-role="more-wrap"><button type="button" class="btn btn-outline btn-sm" data-role="more">Load more</button></div>
    </div>
    <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-line hidden" data-role="multi-footer">
      <button type="button" class="btn btn-outline" data-role="close">Cancel</button>
      <button type="button" class="btn btn-primary" data-role="done">Use selected (<span data-role="count">0</span>)</button>
    </div>
  </div>
</div>

<script>
(() => {
  const modal = document.getElementById('mediaLibrary');
  const $ = (role, root = modal) => root.querySelector(`[data-role="${role}"]`);
  const grid = $('grid'), statusEl = $('status'), moreWrap = $('more-wrap'), search = $('search'), body = $('body');
  const icon = (name, cls) => (window.SC && SC.icon ? SC.icon(name, cls) : '×');

  let picker = null, selected = [], term = '', offset = 0, hasMore = true, busy = false, requestId = 0, loadedOnce = false;
  const multiple = (p) => p.dataset.multiple === '1';
  // value → {url, name} of everything seen in the library, for the picker thumbnails
  const known = {};

  /* ---------- library modal ---------- */

  function load(reset) {
    if (reset) { grid.innerHTML = ''; offset = 0; hasMore = true; }
    if (!hasMore || (busy && !reset)) return;
    busy = true;
    const mine = ++requestId;
    statusEl.textContent = 'Loading…';
    statusEl.classList.remove('hidden');
    moreWrap.classList.add('hidden');

    fetch(`${modal.dataset.url}?offset=${offset}&q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then((r) => { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then((data) => {
        if (mine !== requestId) return;
        loadedOnce = true;
        data.items.forEach(addItem);
        offset += data.items.length;
        hasMore = data.has_more;
        $('total').textContent = `(${data.total})`;
        statusEl.textContent = data.total ? '' : (term ? 'No matching photos.' : 'The library is empty. Upload a photo first.');
        statusEl.classList.toggle('hidden', !!data.total);
        moreWrap.classList.toggle('hidden', !hasMore);
        markSelected();
      })
      .catch(() => {
        if (mine !== requestId) return;
        statusEl.innerHTML = 'Could not load the photos. <button type="button" class="btn btn-ghost btn-sm ms-2" data-role="retry">Retry</button>';
      })
      .finally(() => { if (mine === requestId) busy = false; });
  }

  function addItem(item) {
    known[item.value] = item;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'media-item relative block w-full rounded-xl overflow-hidden ring-1 ring-line bg-ocean-50 text-start hover:ring-ocean-400';
    btn.dataset.value = item.value;
    btn.title = item.name;
    btn.innerHTML = `<span class="media-check absolute top-2 end-2 w-6 h-6 rounded-full bg-ocean-600 text-white place-items-center">${icon('check', 'w-4 h-4')}</span>
      <img src="" alt="" loading="lazy" class="w-full h-28 object-cover">
      <span class="block px-2 py-1.5 text-[11px] font-semibold truncate"></span>`;
    btn.querySelector('img').src = item.url;
    btn.querySelector('span:last-child').textContent = item.name;
    grid.appendChild(btn);
  }

  function markSelected() {
    grid.querySelectorAll('.media-item').forEach((el) => el.classList.toggle('is-selected', selected.includes(el.dataset.value)));
    $('count').textContent = selected.length;
  }

  function open(p) {
    picker = p;
    selected = multiple(p) ? values(p) : [];
    $('multi-footer').classList.toggle('hidden', !multiple(p));
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    markSelected();
    if (!loadedOnce && !busy) load(true);
    search.focus();
  }

  function close() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    picker = null;
  }

  let searchTimer;
  search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { term = search.value.trim(); load(true); }, 300);
  });
  search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
  $('more').addEventListener('click', () => load(false));
  body.addEventListener('scroll', () => { if (body.scrollTop + body.clientHeight >= body.scrollHeight - 150) load(false); }, { passive: true });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

  modal.addEventListener('click', (e) => {
    if (e.target.closest('[data-role="close"]')) return close();
    if (e.target.closest('[data-role="retry"]')) return load(!loadedOnce);
    if (e.target.closest('[data-role="done"]')) { setGallery(picker, selected); return close(); }

    const item = e.target.closest('.media-item');
    if (!item || !picker) return;
    const value = item.dataset.value;
    if (multiple(picker)) {
      const i = selected.indexOf(value);
      i === -1 ? selected.push(value) : selected.splice(i, 1);
      markSelected();
    } else {
      setSingle(picker, value, known[value].url, `From the library: ${known[value].name}`);
      close();
    }
  });

  /* ---------- pickers ---------- */

  function values(p) {
    return Array.from(p.querySelectorAll(`input[type="hidden"][name="${p.dataset.name}"]`)).map((i) => i.value).filter(Boolean);
  }

  function setSingle(p, value, url, source) {
    $('value', p).value = value;
    if (value) $('file', p).value = '';
    showSingle(p, url, source);
  }

  function showSingle(p, url, source) {
    const img = $('preview', p);
    img.src = url || '';
    img.classList.toggle('hidden', !url);
    $('placeholder', p).classList.toggle('hidden', !!url);
    $('clear', p).classList.toggle('hidden', !url);
    $('source', p).textContent = source || '';
  }

  // Rebuild the gallery from the library selection, keeping the photos' current thumbnails.
  function setGallery(p, list) {
    const ol = $('list', p);
    const thumbs = {};
    ol.querySelectorAll('[data-role="item"]').forEach((li) => { thumbs[li.querySelector('input').value] = li.querySelector('img')?.src; });
    ol.innerHTML = '';
    list.forEach((value) => ol.appendChild(galleryItem(p, value, known[value]?.url || thumbs[value])));
    renumber(p);
  }

  function galleryItem(p, value, url) {
    const li = document.createElement('li');
    li.dataset.role = 'item';
    li.draggable = true;
    li.className = 'group relative w-32 cursor-move';
    li.innerHTML = `<input type="hidden">
      <div class="w-32 h-24 rounded-xl overflow-hidden bg-ocean-50 ring-1 ring-line"><img alt="" class="w-full h-full object-cover pointer-events-none"></div>
      <span data-role="number" class="absolute top-1.5 start-1.5 rounded-full bg-ocean-900/75 text-white text-[11px] font-bold px-2 py-0.5"></span>
      <button type="button" data-role="remove" class="absolute top-1.5 end-1.5 w-6 h-6 rounded-full bg-white/95 text-red-600 shadow grid place-items-center hover:bg-red-600 hover:text-white">${icon('x', 'w-3.5 h-3.5')}</button>`;
    const input = li.querySelector('input');
    input.name = p.dataset.name;
    input.value = value;
    if (url) li.querySelector('img').src = url; else li.querySelector('img').remove();
    return li;
  }

  function renumber(p) {
    const items = p.querySelectorAll('[data-role="item"]');
    items.forEach((li, i) => {
      $('number', li).textContent = i + 1;
      $('remove', li).setAttribute('aria-label', `Remove photo ${i + 1}`);
    });
    $('empty', p).classList.toggle('hidden', items.length > 0 || $('uploads', p).children.length > 0);
  }

  // New uploads of a gallery: thumbnails with their own remove button (rebuilds the input's file list).
  function showUploads(p) {
    const input = $('file', p), box = $('uploads', p);
    box.innerHTML = '';
    Array.from(input.files).forEach((file, i) => {
      const fig = document.createElement('figure');
      fig.className = 'relative w-32';
      fig.innerHTML = `<div class="w-32 h-24 rounded-xl overflow-hidden border-2 border-dashed border-ocean-300"><img alt="" class="w-full h-full object-cover"></div>
        <span class="absolute top-1.5 start-1.5 rounded-full bg-emerald-600 text-white text-[11px] font-bold px-2 py-0.5">New</span>
        <button type="button" class="absolute top-1.5 end-1.5 w-6 h-6 rounded-full bg-white/95 text-red-600 shadow grid place-items-center hover:bg-red-600 hover:text-white" aria-label="Don't upload this photo">${icon('x', 'w-3.5 h-3.5')}</button>
        <figcaption class="text-[11px] text-muted truncate mt-1"></figcaption>`;
      fig.querySelector('img').src = URL.createObjectURL(file);
      fig.querySelector('figcaption').textContent = file.name;
      fig.querySelector('button').addEventListener('click', () => {
        const dt = new DataTransfer();
        Array.from(input.files).forEach((f, j) => { if (j !== i) dt.items.add(f); });
        input.files = dt.files;
        showUploads(p);
      });
      box.appendChild(fig);
    });
    renumber(p);
  }

  document.addEventListener('click', (e) => {
    const p = e.target.closest('[data-media-picker]');
    if (!p) return;
    if (e.target.closest('[data-role="open-library"]')) return open(p);
    if (e.target.closest('[data-role="clear"]')) { $('file', p).value = ''; return setSingle(p, '', '', ''); }
    const remove = e.target.closest('[data-role="remove"]');
    if (remove) { remove.closest('[data-role="item"]').remove(); renumber(p); }
  });

  document.addEventListener('change', (e) => {
    const p = e.target.closest('[data-media-picker]');
    if (!p || e.target.dataset.role !== 'file') return;
    if (multiple(p)) return showUploads(p);
    const file = e.target.files[0];
    if (!file) return;
    // A new upload replaces the library pick (the server would prefer the upload anyway).
    $('value', p).value = '';
    showSingle(p, URL.createObjectURL(file), `New upload: ${file.name} (saved when you save the form)`);
  });

  // Drag to reorder gallery photos
  let dragged = null;
  document.addEventListener('dragstart', (e) => {
    dragged = e.target.closest?.('[data-media-picker] [data-role="item"]');
    if (!dragged) return;
    dragged.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', ''); // Firefox only drags with data set
  });
  document.addEventListener('dragover', (e) => {
    if (!dragged) return;
    e.preventDefault();
    const over = e.target.closest('[data-role="item"]');
    if (!over || over === dragged || over.parentNode !== dragged.parentNode) return;
    const box = over.getBoundingClientRect();
    over.parentNode.insertBefore(dragged, e.clientX > box.left + box.width / 2 ? over.nextSibling : over);
  });
  document.addEventListener('drop', (e) => { if (dragged) e.preventDefault(); });
  document.addEventListener('dragend', () => {
    if (!dragged) return;
    dragged.classList.remove('is-dragging');
    renumber(dragged.closest('[data-media-picker]'));
    dragged = null;
  });
})();
</script>
