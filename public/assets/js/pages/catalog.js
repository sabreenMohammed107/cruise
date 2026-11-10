/* =====================================================================
   Catalog — catalog.html
   One state object drives filters, chips, sorting, view and pagination.
   State is mirrored to the URL so results are shareable.
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;
  const ALL = SC.allSailings();
  const PER_PAGE = 6;
  // Soonest departure first unless the link asks for another order (?sort=popular, price-asc, …)
  const DEFAULT_SORT = 'date';
  // Laravel (step 22): the active highlights of Admin → Cruises → Highlights, in their order
  const HIGHLIGHTS = (D.highlights || []).map((h) => [h.key, h.label]);
  // Fully sold-out sailings have fromPrice = Infinity: kept out of the slider range
  const priced = ALL.filter((s) => Number.isFinite(s.fromPrice));
  const priceMin = Math.floor(Math.min(...priced.map((s) => s.fromPrice)) / 50) * 50 || 0;
  // Laravel: top of the price slider from the cabins each sailing really has (a fixed 'suite' id crashed the page when that cabin was renamed or removed)
  const topPrice = (s) => Math.max(s.fromPrice, ...Object.keys(s.sailing.prices || {}).map((id) => SC.prices(s.cruise, s.sailing, id).perPerson));
  const priceMax = Math.ceil(Math.max(...priced.map(topPrice)) / 50) * 50 || 0;

  /* ---------- State (initialised from URL) ---------- */
  const qs = SC.params();
  const listParam = (k) => (qs.get(k) ? qs.get(k).split(',') : []);
  const state = {
    q: qs.get('q') || '',
    depart: listParam('depart'),
    visit: qs.get('port') || '',
    month: qs.get('month') || '',
    date: qs.get('date') || '',
    nights: listParam('nights').map(Number),
    maxPrice: Number(qs.get('max')) || priceMax,
    cabins: listParam('cabins'),
    ships: listParam('ships'),
    // codes of highlights deleted or hidden since the link was made are ignored
    highlights: listParam('hl').filter((k) => HIGHLIGHTS.some(([key]) => key === k)),
    sort: qs.get('sort') || DEFAULT_SORT,
    view: SC.local.get('sc_view', 'grid'),
    page: Number(qs.get('page')) || 1
  };

  /* ---------- Build filter controls ---------- */
  const checkbox = (group, value, label, count) => `
    <label class="flex items-center gap-3 text-sm cursor-pointer">
      <input type="checkbox" class="check" data-group="${group}" value="${value}" ${state[group].includes(value) ? 'checked' : ''}>
      <span class="flex-1">${label}</span>${count != null ? `<span class="text-xs text-muted tabular">${count}</span>` : ''}
    </label>`;

  const departPorts = [...new Set(ALL.map((s) => s.departurePort))];
  $('#f-ports').innerHTML = departPorts.map((p) => checkbox('depart', p, SC.port(p), ALL.filter((s) => s.departurePort === p).length)).join('') + `
    <div class="pt-2"><label class="label" for="f-visit">${SC.t('ui.catalog.visiting')}</label>
      <select id="f-visit" class="field"><option value="">${SC.t('ui.catalog.any_port')}</option>${SC.allPorts().sort().map((p) => `<option value="${p}" ${p === state.visit ? 'selected' : ''}>${SC.port(p)}</option>`).join('')}</select></div>`;

  [...new Set(ALL.map((s) => s.departure.slice(0, 7)))].forEach((m) => $('#f-month').add(new Option(SC.fmtMonth(m), m)));
  $('#f-month').value = state.month;
  $('#f-date').value = state.date;
  $('#f-date').min = SC.toISO(new Date());

  $('#f-nights').innerHTML = [...new Set(ALL.map((s) => s.nights))].sort((a, b) => a - b)
    .map((n) => `<button type="button" class="chip" data-night="${n}" aria-pressed="${state.nights.includes(n)}">${SC.tc('nights', n)}</button>`).join('');

  const range = $('#f-price');
  Object.assign(range, { min: priceMin, max: priceMax, value: state.maxPrice });
  $('#f-price-min').textContent = SC.money(priceMin); $('#f-price-max').textContent = SC.money(priceMax);

  $('#f-cabins').innerHTML = SC.cabinsSorted().map((c) => checkbox('cabins', c.id, c.nameEn)).join('');
  $('#f-ships').innerHTML = D.ships.map((s) => checkbox('ships', s.id, s.name, ALL.filter((x) => x.cruise.shipId === s.id).length)).join('');
  $('#f-highlights').innerHTML = HIGHLIGHTS.map(([k, l]) => checkbox('highlights', k, l)).join('');
  $('#sort').value = state.sort;

  /* ---------- Filtering ---------- */
  // Effective price: "From, per person" (lowest Double ÷ 2, SC.fromPrice) among the selected (and available) cabin types
  // (all cabins sold out → their prices, as on the card)
  const priceFor = (s) => {
    const ids = s.allSoldOut ? Object.keys(s.sailing.prices || {}) : s.availableCabins;
    return SC.fromPrice(s.cruise, s.sailing, state.cabins.length ? ids.filter((c) => state.cabins.includes(c)) : ids);
  };
  const hasHighlight = (s, h) => s.tags.includes(h);

  const filtered = () => {
    const q = state.q.trim().toLowerCase();
    let list = ALL.filter((s) =>
      (!q || s.name.toLowerCase().includes(q) || (s.cruise.nameAr || '').includes(q)) &&
      (!state.depart.length || state.depart.includes(s.departurePort)) &&
      (!state.visit || s.route.some((p) => p.startsWith(state.visit))) &&
      (!state.month || s.departure.startsWith(state.month)) &&
      (!state.date || s.departure >= state.date) &&
      (!state.nights.length || state.nights.includes(s.nights)) &&
      (!state.ships.length || state.ships.includes(s.cruise.shipId)) &&
      (!state.highlights.length || state.highlights.every((h) => hasHighlight(s, h))) &&
      // sold out (no price) only shows while the price slider is at its top
      (Number.isFinite(priceFor(s)) ? priceFor(s) <= state.maxPrice : state.maxPrice >= priceMax));
    list = list.map((s) => ({ ...s, fromPrice: priceFor(s) }));
    // Sold out (Infinity) goes last in both price orders
    const byPrice = (dir) => (a, b) => {
      const fa = Number.isFinite(a.fromPrice), fb = Number.isFinite(b.fromPrice);
      return fa && fb ? dir * (a.fromPrice - b.fromPrice) : fb - fa;
    };
    const sorters = {
      popular: (a, b) => b.popularity - a.popularity || a.departure.localeCompare(b.departure),
      date: (a, b) => a.departure.localeCompare(b.departure),
      'price-asc': byPrice(1),
      'price-desc': byPrice(-1),
      rating: (a, b) => b.rating - a.rating || b.reviews - a.reviews
    };
    return list.sort(sorters[state.sort]);
  };

  /* ---------- Active filter chips ---------- */
  const chips = () => {
    const out = [];
    if (state.q) out.push(['q', null, `“${state.q}”`]);
    state.depart.forEach((p) => out.push(['depart', p, SC.t('ui.catalog.chip_from_port', { port: SC.port(p) })]));
    if (state.visit) out.push(['visit', null, SC.t('ui.catalog.chip_visiting', { port: SC.port(state.visit) })]);
    if (state.month) out.push(['month', null, SC.fmtMonth(state.month)]);
    if (state.date) out.push(['date', null, SC.t('ui.catalog.chip_from_date', { date: SC.fmtDate(state.date) })]);
    state.nights.forEach((n) => out.push(['nights', n, SC.tc('nights', n)]));
    if (state.maxPrice < priceMax) out.push(['maxPrice', null, SC.t('ui.catalog.chip_up_to', { price: SC.money(state.maxPrice) })]);
    state.cabins.forEach((c) => out.push(['cabins', c, SC.getCabin(c).nameEn]));
    state.ships.forEach((s) => out.push(['ships', s, SC.getShip(s).name]));
    state.highlights.forEach((h) => out.push(['highlights', h, HIGHLIGHTS.find((x) => x[0] === h)[1]]));
    return out;
  };

  /* ---------- Render ---------- */
  const render = () => {
    const list = filtered();
    const pages = Math.max(1, Math.ceil(list.length / PER_PAGE));
    state.page = Math.min(state.page, pages);
    const slice = list.slice((state.page - 1) * PER_PAGE, state.page * PER_PAGE);

    $('#r-count').textContent = list.length;
    const results = $('#results');
    results.className = 'mt-6 grid gap-6 ' + (state.view === 'grid' ? 'sm:grid-cols-2 xl:grid-cols-3' : '');
    results.innerHTML = slice.map((v) => SC.ui.sailingCard(v, { layout: state.view })).join('');
    $('#empty').classList.toggle('hidden', list.length > 0);

    // view toggle buttons
    $$('[data-view]').forEach((b) => {
      const on = b.dataset.view === state.view;
      b.setAttribute('aria-pressed', on); b.classList.toggle('bg-ocean-600', on); b.classList.toggle('text-white', on);
    });

    // chips + mobile badge
    const c = chips();
    $('#active-chips').innerHTML = c.map(([k, v, label]) => `<button class="chip !py-1.5 !text-xs" data-remove="${k}" data-value="${v ?? ''}">${SC.esc(label)} ${SC.icon('x', 'w-3.5 h-3.5')}</button>`).join('');
    const badge = $('#f-badge'); badge.textContent = c.length; badge.classList.toggle('hidden', !c.length); badge.classList.toggle('grid', !!c.length);
    $('#f-price-out').textContent = SC.money(state.maxPrice);

    lastPages = pages;
    renderPager(pages);
    syncUrl();
  };

  /* ---------- Pagination: numbered on every screen, as many numbers as the row has room for ----------
     Wide rows:  ‹ 1 … 4 5 [6] 7 8 … 46 ›   (current ± 2, first and last; ± 1 when a little narrower)
     Phones:     ‹ 4 5 [6] 7 8 ›            (a window of 5 pages around the current one that slides with it;
                                              3 on very narrow screens)
     The layout is chosen from the row's width, not from the page, so it doesn't jump around while paging.
     In Arabic the row runs right to left (the page is dir="rtl"): ‹ previous › stays at the start (right), page 1
     next to it, and the arrows are mirrored. Buttons are 40 px for comfortable taps. */
  const BTN = 40, GAP = 6; // w-10 buttons, gap-1.5 on #pagination (pages/catalog.blade.php)
  const LAYOUTS = [
    { edges: true, near: 2 },  // 9 slots
    { edges: true, near: 1 },  // 7 slots
    { edges: false, size: 5 },
    { edges: false, size: 3 },
    { edges: false, size: 1 }
  ];
  const slots = (l) => (l.edges ? l.near * 2 + 5 : l.size);
  // Widest the row can get with this layout (every slot counted as a button, plus ‹ and ›)
  const widthOf = (l) => (slots(l) + 2) * BTN + (slots(l) + 1) * GAP;

  /** Page numbers to show, with null for a "…" gap; always the same number of slots for a given layout. */
  const pageItems = (page, pages, l) => {
    const n = slots(l);
    const range = (a, b) => Array.from({ length: b - a + 1 }, (_, i) => a + i);
    if (pages <= n) return range(1, pages);
    if (!l.edges) {
      const start = Math.min(Math.max(1, page - Math.floor(n / 2)), pages - n + 1);
      return range(start, start + n - 1);
    }
    // Near either end, one block runs from the edge; the current page always keeps its `near` neighbours
    const inner = n - 2;
    if (page <= inner - l.near) return [...range(1, inner), null, pages];                         // 1 2 3 [4] 5 6 7 … 46
    if (page >= pages - inner + l.near + 1) return [1, null, ...range(pages - inner + 1, pages)]; // 1 … 40 41 [42] … 46
    return [1, null, ...range(page - l.near, page + l.near), null, pages];                       // 1 … 4 5 [6] 7 8 … 46
  };

  let lastPages = 1, lastLayout = null;
  const renderPager = (pages) => {
    const pg = $('#pagination');
    if (pages <= 1) { pg.innerHTML = ''; lastLayout = null; return; }
    const room = pg.clientWidth || window.innerWidth - 32;
    const layout = LAYOUTS.find((l) => widthOf(l) <= room) || LAYOUTS[LAYOUTS.length - 1];
    lastLayout = layout;

    const base = 'w-10 h-10 shrink-0 rounded-full grid place-items-center font-bold tabular-nums border touch-manipulation transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ocean-500 focus-visible:ring-offset-2';
    const num = (p) => p === state.page
      ? `<button type="button" class="${base} bg-ocean-600 text-white border-ocean-600" data-page="${p}" aria-current="page">${p}</button>`
      : `<button type="button" class="${base} bg-white border-line hover:border-ocean-500 active:bg-ocean-50" data-page="${p}">${p}</button>`;
    const arrow = (p, icon, label, disabled) => `<button type="button" class="${base} bg-white border-line ${disabled ? 'opacity-40 pointer-events-none' : 'hover:border-ocean-500 active:bg-ocean-50'}" data-page="${p}" aria-label="${SC.esc(label)}" ${disabled ? 'disabled aria-disabled="true"' : ''}>${SC.icon(icon, 'w-4 h-4 rtl:rotate-180')}</button>`;
    const gap = '<span class="w-5 shrink-0 text-center text-muted select-none" aria-hidden="true">…</span>';

    pg.innerHTML = arrow(state.page - 1, 'chevron-left', SC.t('ui.catalog.previous_page'), state.page === 1) +
      pageItems(state.page, pages, layout).map((p) => (p === null ? gap : num(p))).join('') +
      arrow(state.page + 1, 'chevron-right', SC.t('ui.catalog.next_page'), state.page === pages);
  };

  // Turning a phone or resizing the window: redraw the numbers only when another layout fits
  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      const room = $('#pagination').clientWidth;
      const fits = LAYOUTS.find((l) => widthOf(l) <= room) || LAYOUTS[LAYOUTS.length - 1];
      if (lastLayout && fits !== lastLayout) renderPager(lastPages);
    }, 120);
  });

  const syncUrl = () => {
    const p = new URLSearchParams();
    if (state.q) p.set('q', state.q);
    if (state.depart.length) p.set('depart', state.depart.join(','));
    if (state.visit) p.set('port', state.visit);
    if (state.month) p.set('month', state.month);
    if (state.date) p.set('date', state.date);
    if (state.nights.length) p.set('nights', state.nights.join(','));
    if (state.maxPrice < priceMax) p.set('max', state.maxPrice);
    if (state.cabins.length) p.set('cabins', state.cabins.join(','));
    if (state.ships.length) p.set('ships', state.ships.join(','));
    if (state.highlights.length) p.set('hl', state.highlights.join(','));
    if (state.sort !== DEFAULT_SORT) p.set('sort', state.sort);
    if (state.page > 1) p.set('page', state.page);
    history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : ''));
  };

  const update = (patch) => { Object.assign(state, patch, patch.page ? {} : { page: 1 }); render(); };

  /* ---------- Events ---------- */
  let qTimer;
  $('#q').value = state.q;
  $('#q').addEventListener('input', (e) => { clearTimeout(qTimer); qTimer = setTimeout(() => update({ q: e.target.value }), 200); });

  $('#filter-form').addEventListener('change', (e) => {
    const t = e.target;
    if (t.dataset.group) update({ [t.dataset.group]: $$(`[data-group="${t.dataset.group}"]:checked`).map((x) => x.value) });
    if (t.id === 'f-visit') update({ visit: t.value });
    if (t.id === 'f-month') update({ month: t.value });
    if (t.id === 'f-date') update({ date: t.value });
  });
  range.addEventListener('input', () => update({ maxPrice: Number(range.value) }));
  $('#f-nights').addEventListener('click', (e) => {
    const b = e.target.closest('[data-night]'); if (!b) return;
    const n = Number(b.dataset.night); const on = !state.nights.includes(n);
    b.setAttribute('aria-pressed', on);
    update({ nights: on ? [...state.nights, n] : state.nights.filter((x) => x !== n) });
  });
  $('#sort').addEventListener('change', (e) => update({ sort: e.target.value }));
  $$('[data-view]').forEach((b) => b.addEventListener('click', () => { SC.local.set('sc_view', b.dataset.view); update({ view: b.dataset.view, page: state.page }); }));

  $('#pagination').addEventListener('click', (e) => {
    const b = e.target.closest('[data-page]'); if (!b) return;
    update({ page: Number(b.dataset.page) });
    $('#results-title').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // Remove a single chip → also reflect in the controls
  $('#active-chips').addEventListener('click', (e) => {
    const b = e.target.closest('[data-remove]'); if (!b) return;
    const k = b.dataset.remove, v = b.dataset.value;
    const resets = { q: () => { $('#q').value = ''; return { q: '' }; }, visit: () => { $('#f-visit').value = ''; return { visit: '' }; }, month: () => { $('#f-month').value = ''; return { month: '' }; }, date: () => { $('#f-date').value = ''; return { date: '' }; }, maxPrice: () => { range.value = priceMax; return { maxPrice: priceMax }; } };
    if (resets[k]) return update(resets[k]());
    if (k === 'nights') { $(`[data-night="${v}"]`).setAttribute('aria-pressed', 'false'); return update({ nights: state.nights.filter((x) => x !== Number(v)) }); }
    const box = $(`[data-group="${k}"][value="${v}"]`); if (box) box.checked = false;
    update({ [k]: state[k].filter((x) => x !== v) });
  });

  const clearAll = () => {
    $$('#filter-form input[type=checkbox]').forEach((c) => (c.checked = false));
    $$('[data-night]').forEach((c) => c.setAttribute('aria-pressed', 'false'));
    ['#f-visit', '#f-month', '#f-date', '#q'].forEach((s) => ($(s).value = ''));
    range.value = priceMax;
    update({ q: '', depart: [], visit: '', month: '', date: '', nights: [], maxPrice: priceMax, cabins: [], ships: [], highlights: [] });
  };
  $('#clear-all').addEventListener('click', clearAll);
  document.addEventListener('click', (e) => { if (e.target.closest('[data-clear-all]')) clearAll(); });

  /* ---------- Mobile off-canvas filters ---------- */
  const panel = $('#filters'), backdrop = $('#filter-backdrop');
  const openFilters = (open) => {
    panel.classList.toggle('!translate-x-0', open);
    backdrop.classList.toggle('hidden', !open);
    document.body.classList.toggle('no-scroll', open);
    if (open) $('#filters-close').focus();
  };
  $('#filters-open').addEventListener('click', () => openFilters(true));
  [$('#filters-close'), $('#filters-apply'), backdrop].forEach((el) => el.addEventListener('click', () => openFilters(false)));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') openFilters(false); });

  render();
});
