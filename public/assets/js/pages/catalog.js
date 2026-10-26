/* =====================================================================
   Catalog — catalog.html
   One state object drives filters, chips, sorting, view and pagination.
   State is mirrored to the URL so results are shareable.
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;
  const ALL = SC.allSailings();
  const PER_PAGE = 6;
  // Laravel (step 22): "No visa needed" (a cruise setting), then the active highlights of Admin → Cruises → Highlights
  const HIGHLIGHTS = [['visaFree', SC.t('ui.catalog.highlights.visaFree')], ...(D.highlights || []).map((h) => [h.key, h.label])];
  const priceMin = Math.floor(Math.min(...ALL.map((s) => s.fromPrice)) / 50) * 50;
  // Laravel: top of the price slider from the cabins each sailing really has (a fixed 'suite' id crashed the page when that cabin was renamed or removed)
  const topPrice = (s) => Math.max(s.fromPrice, ...Object.keys(s.sailing.prices || {}).map((id) => SC.prices(s.cruise, s.sailing, id).perPerson));
  const priceMax = Math.ceil(Math.max(...ALL.map(topPrice)) / 50) * 50;

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
    sort: qs.get('sort') || 'popular',
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
  const priceFor = (s) => SC.fromPrice(s.cruise, s.sailing,
    state.cabins.length ? s.availableCabins.filter((c) => state.cabins.includes(c)) : s.availableCabins);
  const hasHighlight = (s, h) => (h === 'visaFree' ? s.visaFree : s.tags.includes(h));

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
      priceFor(s) <= state.maxPrice);
    list = list.map((s) => ({ ...s, fromPrice: priceFor(s) }));
    const sorters = {
      popular: (a, b) => b.popularity - a.popularity || a.departure.localeCompare(b.departure),
      date: (a, b) => a.departure.localeCompare(b.departure),
      'price-asc': (a, b) => a.fromPrice - b.fromPrice,
      'price-desc': (a, b) => b.fromPrice - a.fromPrice,
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

    // pagination
    const pg = $('#pagination');
    if (pages <= 1) { pg.innerHTML = ''; } else {
      const btn = (p, label, aria, disabled) => `<button class="w-9 h-9 sm:w-10 sm:h-10 shrink-0 rounded-full grid place-items-center font-bold border ${p === state.page && !aria ? 'bg-ocean-600 text-white border-ocean-600' : 'bg-white border-line hover:border-ocean-500'} ${disabled ? 'opacity-40 pointer-events-none' : ''}" data-page="${p}" ${aria ? `aria-label="${aria}"` : ''} ${p === state.page && !aria ? 'aria-current="page"' : ''}>${label}</button>`;
      // Laravel: with many sailings a button per page was wider than a phone (61 sailings = 11 pages = 616 px).
      // Show the first, the last and the current page (± 1 from sm up), "…" for the gaps.
      const near = window.matchMedia('(min-width: 640px)').matches ? 1 : 0;
      const shown = [...new Set([1, pages, ...Array.from({ length: near * 2 + 1 }, (_, i) => state.page - near + i)])]
        .filter((p) => p >= 1 && p <= pages).sort((a, b) => a - b);
      const gap = '<span class="w-6 text-center text-muted shrink-0" aria-hidden="true">…</span>';
      pg.innerHTML = btn(state.page - 1, SC.icon('chevron-left', 'w-4 h-4 rtl:rotate-180'), SC.t('ui.catalog.previous_page'), state.page === 1) +
        shown.map((p, i) => (i && p - shown[i - 1] > 1 ? gap : '') + btn(p, p)).join('') +
        btn(state.page + 1, SC.icon('chevron-right', 'w-4 h-4 rtl:rotate-180'), SC.t('ui.catalog.next_page'), state.page === pages);
    }
    syncUrl();
  };

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
    if (state.sort !== 'popular') p.set('sort', state.sort);
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
