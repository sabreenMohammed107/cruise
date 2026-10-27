/* =====================================================================
   Cruise details — details.html?cruise=ID&sailing=SAILING_ID
   Steps covered here (docs 2 + 3): departure date → travellers by age
   band → cabin category → live price → save to booking session.
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;

  /* ---------- Resolve cruise + sailing from URL (with safe fallbacks) ---------- */
  const qs = SC.params();
  const found = qs.get('sailing') && SC.findSailing(qs.get('sailing'));
  // Laravel: the cruise comes from the URL path (/cruises/{slug}), passed in as SC_DATA.page.cruise
  const cruise = (found && found.cruise) || SC.getCruise(qs.get('cruise') || (D.page && D.page.cruise)) || D.cruises[0];
  const upcoming = cruise.sailings.filter((s) => SC.daysUntil(s.departure) > 0);
  let sailing = (found && found.sailing) || upcoming[0] || cruise.sailings[0];

  // Restore previous choices if the guest comes back to the same sailing
  const saved = SC.booking.get();
  const same = saved && saved.cruiseId === cruise.id;
  /* Laravel (step 15): a booking can hold several cabins. state.rooms = [{ cabinId, guests }, …]; the counters and
     cabin cards edit the active one (state.active). state.guests / state.cabinId are that room, so the code below
     reads like the one-cabin template. */
  const keepCabin = same && saved.sailingId === sailing.id;
  const savedRooms = same && saved.rooms ? saved.rooms : same ? [{ cabinId: saved.cabinId, guests: saved.guests }] : [{ cabinId: null, guests: SC.guestsPref.get() }];
  const state = {
    rooms: savedRooms.map((r) => ({ cabinId: keepCabin ? r.cabinId || null : null, guests: { ...r.guests } })),
    active: 0,
    get room() { return this.rooms[this.active]; },
    get guests() { return this.room.guests; },
    get cabinId() { return this.room.cabinId; },
    set cabinId(v) { this.room.cabinId = v; }
  };
  const maxCapacity = () => Math.max(...SC.cabinsSorted().filter((c) => !(sailing.soldOut || []).includes(c.id)).map((c) => c.maxGuests));
  // Laravel: adults, children and infants all count towards the cabin limit (SC.guestLimits in core.js)
  const totalGuests = () => SC.guestTotal(state.guests);
  state.rooms.forEach((r) => SC.fitGuests(r.guests, maxCapacity())); // counts saved under older rules
  // The counters keep one object; it reads and writes whichever cabin is active
  const activeGuests = new Proxy({}, {
    get: (_, k) => state.guests[k],
    set: (_, k, v) => { state.guests[k] = v; return true; }
  });
  const cabinLabel = (i) => SC.t('ui.details.cabin_n', { n: i + 1 });


  document.title = `${cruise.name} | Safer Cruise`;
  $('#wa-fab')?.classList.replace('bottom-5', 'bottom-24');
  $('#wa-fab')?.classList.add('lg:bottom-5');

  /* ---------- Static header content ---------- */
  $('#crumb-name').textContent = cruise.name;
  $('#d-name').textContent = cruise.name;
  $('#d-ship').textContent = SC.getShip(cruise.shipId).name;
  // Laravel: wording from lang/*/ui.php (details.*)
  $('#d-ports').textContent = cruise.departurePort === cruise.arrivalPort
    ? SC.t('ui.details.round_trip', { port: SC.port(cruise.departurePort) })
    : SC.t('ui.details.route', { from: SC.port(cruise.departurePort), to: SC.port(cruise.arrivalPort) });
  $('#d-rating').innerHTML = SC.ui.stars(cruise.rating, cruise.reviews) + ` <span>${SC.t('ui.guest_rating')}</span>`;
  // Laravel (step 22): highlights marked "on the cruise page" in Admin → Cruises → Highlights, in their colour
  $('#d-tags').innerHTML = SC.highlights(cruise.tags, 'onPage').map((h) => `<span class="tag tag-${h.tone}">${SC.esc(h.label)}</span>`).join('');
  $('#d-summary').textContent = cruise.summary;
  $('#d-actions').insertAdjacentHTML('afterbegin', SC.ui.favBtn(cruise.id));
  $('#share-btn').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(location.href); SC.toast(SC.t('ui.details.link_copied')); }
    catch (e) { SC.toast(SC.t('ui.details.copy_failed'), 'error'); }
  });

  /* ---------- Gallery + lightbox ---------- */
  const imgs = cruise.gallery;
  $('#gallery-grid').innerHTML = imgs.slice(0, 5).map((src, i) => `
    <button class="relative img-zoom ${i === 0 ? 'col-span-2 row-span-2' : ''} ${imgs.length < 5 && i === imgs.length - 1 && i > 0 ? 'col-span-2' : ''}" data-photo="${i}" aria-label="${SC.t('ui.details.open_photo', { n: i + 1 })}">
      <img src="${src}" alt="${SC.t('ui.details.photo_alt', { cruise: cruise.name, n: i + 1 })}" class="absolute inset-0 w-full h-full object-cover" ${i ? 'loading="lazy"' : ''}>
    </button>`).join('');
  $('#gallery-strip').innerHTML = imgs.map((src, i) => `
    <button class="snap-start shrink-0 w-[85%] aspect-[4/3] rounded-2xl overflow-hidden" data-photo="${i}" aria-label="${SC.t('ui.details.open_photo', { n: i + 1 })}"><img src="${src}" alt="" class="w-full h-full object-cover"></button>`).join('');

  let photo = 0;
  const openLightbox = (i) => {
    photo = i;
    const body = SC.ensureModal('gallery-modal', { title: cruise.name, width: '1040px' });
    body.innerHTML = `
      <div class="relative rounded-2xl overflow-hidden bg-ocean-900 aspect-[16/10]">
        <img id="lb-img" class="w-full h-full object-contain" alt="">
        <button class="icon-btn absolute start-3 top-1/2 -translate-y-1/2" id="lb-prev" aria-label="${SC.t('ui.previous_photo')}">${SC.icon('chevron-left', 'w-5 h-5 rtl:rotate-180')}</button>
        <button class="icon-btn absolute end-3 top-1/2 -translate-y-1/2" id="lb-next" aria-label="${SC.t('ui.next_photo')}">${SC.icon('chevron-right', 'w-5 h-5 rtl:rotate-180')}</button>
      </div>
      <p class="text-center text-sm text-muted mt-3 tabular" id="lb-count" aria-live="polite"></p>`;
    const show = () => { $('#lb-img').src = imgs[photo]; $('#lb-img').alt = SC.t('ui.details.photo_alt', { cruise: cruise.name, n: photo + 1 }); $('#lb-count').textContent = SC.t('ui.photo_of', { n: photo + 1, total: imgs.length }); };
    $('#lb-prev').onclick = () => { photo = (photo - 1 + imgs.length) % imgs.length; show(); };
    $('#lb-next').onclick = () => { photo = (photo + 1) % imgs.length; show(); };
    show(); SC.modal.open('gallery-modal');
  };
  $('#gallery').addEventListener('click', (e) => { const b = e.target.closest('[data-photo]'); if (b) openLightbox(Number(b.dataset.photo)); });
  $('#gallery-all').addEventListener('click', () => openLightbox(0));
  document.addEventListener('keydown', (e) => {
    if (!$('#gallery-modal.open')) return;
    const rtl = document.documentElement.dir === 'rtl';
    if (e.key === 'ArrowRight') $(rtl ? '#lb-prev' : '#lb-next').click();
    if (e.key === 'ArrowLeft') $(rtl ? '#lb-next' : '#lb-prev').click();
  });

  /* ---------- Sailing facts (depend on chosen date) ---------- */
  const renderFacts = () => {
    const ret = SC.addDays(sailing.departure, cruise.nights);
    const fact = (label, val, sub) => `<div><p class="text-xs font-semibold text-muted">${label}</p><p class="font-bold mt-0.5">${val}</p><p class="text-sm text-muted">${sub}</p></div>`;
    $('#d-facts').innerHTML =
      fact(SC.t('ui.card.departs'), SC.fmtDate(sailing.departure, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }), SC.port(cruise.departurePort)) +
      fact(SC.t('ui.card.returns'), SC.fmtDate(ret, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }), SC.port(cruise.arrivalPort)) +
      fact(SC.t('ui.details.duration'), SC.tc('nights', cruise.nights), SC.tc('ui.details.days', cruise.nights + 1)) +
      fact(SC.t('ui.details.ship'), SC.getShip(cruise.shipId).name, SC.t('ui.details.sailing_code', { code: sailing.id }));
  };

  /* ---------- Departure date chips ---------- */
  const renderDates = () => {
    $('#d-dates').innerHTML = upcoming.map((s) => {
      const on = s.id === sailing.id;
      const from = SC.sailingView(cruise, s).fromPrice;
      return `<button role="radio" aria-checked="${on}" data-sailing="${s.id}" class="text-start rounded-2xl border-2 px-4 py-3 transition ${on ? 'border-ocean-600 bg-ocean-50' : 'border-line bg-white hover:border-ocean-300'}">
        <span class="block font-bold">${SC.fmtDate(s.departure, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })}</span>
        <span class="block text-sm text-muted">${SC.t('ui.details.from_pp', { price: SC.money(from) })}</span>
      </button>`;
    }).join('');
  };
  $('#d-dates').addEventListener('click', (e) => {
    const b = e.target.closest('[data-sailing]'); if (!b || b.dataset.sailing === sailing.id) return;
    sailing = cruise.sailings.find((s) => s.id === b.dataset.sailing);
    state.rooms.forEach((r) => {
      if (r.cabinId && (sailing.soldOut || []).includes(r.cabinId)) {
        SC.toast(SC.t('ui.details.sold_out_change', { cabin: SC.getCabin(r.cabinId).nameEn }), 'error');
        r.cabinId = null;
      }
    });
    history.replaceState(null, '', SC.detailsUrl({ cruiseId: cruise.id, sailingId: sailing.id }));
    renderAllDynamic();
  });

  /* ---------- Itinerary accordion ---------- */
  const renderItinerary = () => {
    $('#d-itinerary').innerHTML = cruise.itinerary.map((d, i) => {
      const date = SC.addDays(sailing.departure, i);
      const atSea = d.port === 'At sea';
      return `<li class="acc-item card ${i === 0 ? 'open' : ''}">
        <button class="acc-trigger w-full flex items-center gap-4 p-4 sm:p-5 text-start" aria-expanded="${i === 0}">
          <span class="shrink-0 w-14 text-center rounded-xl py-1.5 ${atSea ? 'bg-lagoon-50 text-lagoon-600' : 'bg-ocean-50 text-ocean-700'}">
            <span class="block text-[11px] font-bold">${SC.t('ui.details.day')}</span><span class="block font-display font-bold text-lg leading-none">${i + 1}</span>
          </span>
          <span class="flex-1 min-w-0">
            <span class="block font-bold truncate">${SC.port(d.port)}</span>
            <span class="block text-sm text-muted">${SC.fmtDate(date, { weekday: 'long', day: 'numeric', month: 'short' })}, ${d.time}</span>
          </span>
          ${SC.icon(atSea ? 'waves' : 'anchor', 'w-5 h-5 text-ocean-300 hidden sm:block')}
          ${SC.icon('chevron-down', 'acc-chevron w-5 h-5 text-muted')}
        </button>
        <div class="acc-body"><div><p class="px-4 sm:px-5 pb-5 sm:ps-[6.25rem] text-muted leading-relaxed">${d.text}</p></div></div>
      </li>`;
    }).join('');
  };
  $('#itin-toggle').addEventListener('click', (e) => {
    const items = $$('#d-itinerary .acc-item'); const openAll = items.some((i) => !i.classList.contains('open'));
    items.forEach((i) => { i.classList.toggle('open', openAll); i.querySelector('.acc-trigger').setAttribute('aria-expanded', openAll); });
    e.currentTarget.textContent = SC.t(openAll ? 'ui.details.collapse_all' : 'ui.details.expand_all');
  });

  /* ---------- Travellers (Who's travelling?) ---------- */
  $('#d-counters').innerHTML = ['adult', 'child', 'infant'].map((b) => SC.ui.counterRow(b, state.guests[b])).join('');
  // Up to the largest cabin still available on this date; infants need an adult
  const limits = (band, g) => SC.guestLimits(band, g, maxCapacity());
  const repaintCounters = SC.ui.bindCounters($('#d-counters'), activeGuests, () => {
    if (state.active === 0) SC.guestsPref.set({ ...state.guests });
    checkCapacity(true);
    renderCabins(); renderSummary(); repaintCounters(); renderRoomBanner();
  }, limits);

  // If the chosen cabin can't hold the group, warn and list cabins that fit (doc 3)
  const checkCapacity = (fromCounter) => {
    const n = totalGuests(); const alert = $('#cap-alert');
    const cab = state.cabinId && SC.getCabin(state.cabinId);
    if (cab && cab.maxGuests < n) {
      const fits = SC.cabinsSorted().filter((c) => c.maxGuests >= n && !(sailing.soldOut || []).includes(c.id)).map((c) => c.nameEn);
      $('#cap-alert-text').innerHTML = `${SC.t('ui.details.too_small', { cabin: SC.esc(cab.nameEn), max: cab.maxGuests, count: n })} ${fits.length ? SC.t('ui.details.choose_instead', { cabins: fits.map(SC.esc).join(SC.t('ui.list_or')) }) : SC.t('ui.details.reduce_guests')}`;
      alert.classList.remove('hidden'); alert.classList.add('flex');
      state.cabinId = null;
      if (fromCounter) SC.toast(SC.t('ui.details.no_longer_fits'), 'error');
    } else {
      alert.classList.add('hidden'); alert.classList.remove('flex');
    }
  };

  /* ---------- Cabin category cards ---------- */
  const renderCabins = () => {
    const n = totalGuests();
    $('#d-cabins').innerHTML = SC.cabinsSorted().map((c) => {
      const p = SC.prices(cruise, sailing, c.id);
      // Laravel: a cabin without prices on this sailing can't be booked
      const soldOut = (sailing.soldOut || []).includes(c.id) || !(sailing.prices || {})[c.id];
      const tooSmall = c.maxGuests < n;
      const selected = state.cabinId === c.id;
      const priceRow = (label, val) => `<div class="flex justify-between gap-3"><dt class="text-muted">${label}</dt><dd class="font-bold tabular whitespace-nowrap">${val}</dd></div>`;
      // Lines wrap (no truncate): long bed / size texts were cut off by the card edge on narrow desktop cards
      const fact = (icon, text, cls = '') => `<li class="flex items-start gap-2 ${cls}">${SC.icon(icon, 'w-4 h-4 shrink-0 mt-0.5')}<span class="min-w-0">${text}</span></li>`;
      return `<article class="card overflow-hidden flex flex-col transition ${selected ? 'card-selected' : ''} ${soldOut || tooSmall ? 'opacity-70' : ''}" data-cabin-card="${c.id}">
        <div class="relative aspect-[4/3] img-zoom">
          <img src="${c.images[0]}" alt="${c.nameEn}" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
          ${c.badge && !soldOut ? `<span class="absolute top-3 start-3 tag tag-sun">${c.badge}</span>` : ''}
          ${soldOut ? `<span class="absolute inset-0 grid place-items-center bg-ocean-900/55 text-white font-bold">${SC.t('ui.details.sold_out_date')}</span>` : ''}
          ${selected ? `<span class="absolute top-3 end-3 grid place-items-center w-8 h-8 rounded-full bg-ocean-600 text-white">${SC.icon('check', 'w-4 h-4')}</span>` : ''}
        </div>
        <div class="p-5 flex-1 flex flex-col">
          <h3 class="font-display font-semibold text-lg leading-snug">${c.nameEn}</h3>
          ${SC.lang() === 'ar' ? '' : `<p class="text-sm text-muted" lang="ar" dir="rtl">${c.nameAr}</p>`}
          <p class="text-sm mt-3 leading-relaxed">${c.short}</p>
          <ul class="mt-4 grid gap-1.5 text-sm text-muted">
            ${fact('users', SC.t('ui.guests_up_to_long', { count: c.maxGuests }), tooSmall ? 'text-[#C42F2F] font-semibold' : '')}
            ${fact('size', `${c.size}${c.balcony ? ` + ${SC.t('ui.balcony_size', { size: c.balcony })}` : ''}`)}
            ${fact('bed', c.beds)}
          </ul>
          <button class="self-start mt-3 text-sm font-bold text-ocean-600 hover:underline" data-cabin-info="${c.id}">${SC.t('ui.cabin.details_photos')}</button>
          <dl class="mt-4 pt-4 border-t border-line grid gap-1.5 text-sm">
            <p class="text-xs text-muted">${SC.t('ui.details.per_cabin')}</p>
            ${priceRow(SC.t('ui.details.single'), SC.money(p.single))}${priceRow(SC.t('ui.details.double'), SC.money(p.double))}
            ${c.maxGuests > 2 ? priceRow(SC.t('ui.details.extra'), `+ ${SC.money(p.extra)}`) : ''}${priceRow(SC.t('ui.details.infant'), SC.t('ui.details.free'))}
          </dl>
          <div class="mt-auto pt-5">
            <p class="text-xs text-muted">${SC.t('ui.from_per_person')}</p>
            <p class="font-display font-bold text-2xl text-ocean-800 tabular">${SC.money(p.perPerson)}</p>
            <button class="btn ${selected ? 'btn-primary' : 'btn-accent'} w-full mt-3" data-select-cabin="${c.id}" ${soldOut || tooSmall ? 'disabled' : ''} aria-pressed="${selected}">
              ${soldOut ? SC.t('ui.details.sold_out') : tooSmall ? SC.t('ui.details.fits_up_to', { count: c.maxGuests }) : selected ? `${SC.icon('check', 'w-4 h-4')} ${SC.t('ui.details.selected')}` : SC.t(c.id === 'suite' ? 'ui.cabin.choose_suite' : 'ui.cabin.choose')}
            </button>
          </div>
        </div>
      </article>`;
    }).join('');
  };
  const selectCabin = (id) => {
    state.cabinId = id;
    checkCapacity(false);
    renderCabins(); renderSummary(); renderRoomBanner();
    SC.toast(SC.t('ui.details.cabin_selected', { cabin: SC.getCabin(id).nameEn }));
  };

  /* ---------- Several cabins: which one the counters and cards are editing ---------- */
  // Cabin switcher above the guest counters (all screen sizes): one chip per cabin + "Add another cabin"
  $('#d-counters').parentElement.insertAdjacentHTML('beforebegin', '<div id="room-bar" class="mt-4 flex flex-wrap items-center gap-2" aria-label="' + SC.esc(SC.t('ui.details.your_cabins')) + '"></div>');
  $('#d-cabins').insertAdjacentHTML('beforebegin', '<p id="room-banner" class="hidden mt-4 rounded-2xl border border-ocean-100 bg-ocean-50 px-4 py-3 text-sm font-semibold text-ocean-800"></p>');
  const renderRoomBanner = () => {
    const several = state.rooms.length > 1;
    $('#room-bar').innerHTML = state.rooms.map((r, i) => {
      const cab = r.cabinId && SC.getCabin(r.cabinId);
      const on = i === state.active;
      return `<span class="inline-flex items-center rounded-full border-2 ${on ? 'border-ocean-600 bg-ocean-50' : 'border-line bg-white'}">
        <button type="button" class="ps-3 ${several ? 'pe-1' : 'pe-3'} py-1.5 text-sm font-bold" data-room-edit="${i}" aria-pressed="${on}">${cabinLabel(i)}<span class="font-normal text-muted"> · ${cab ? SC.esc(cab.nameEn) : SC.t('ui.details.not_chosen')} · ${SC.guestTotal(r.guests)}</span></button>
        ${several ? `<button type="button" class="pe-2 ps-1 py-1.5 text-muted hover:text-[#C42F2F]" data-room-remove="${i}" aria-label="${SC.esc(SC.t('ui.details.remove_cabin', { n: i + 1 }))}">${SC.icon('x', 'w-4 h-4')}</button>` : ''}
      </span>`;
    }).join('') + (state.rooms.length < SC.maxRooms() ? `<button type="button" class="btn btn-ghost btn-sm" data-room-add>${SC.icon('plus', 'w-4 h-4')} ${SC.t('ui.details.add_cabin')}</button>` : '');
    $('#room-banner').classList.toggle('hidden', !several);
    $('#room-banner').textContent = several ? SC.t('ui.details.editing_cabin', { n: state.active + 1, total: state.rooms.length }) : '';
  };
  const editRoom = (i) => {
    state.active = i;
    checkCapacity(false); renderCabins(); renderSummary(); repaintCounters(); renderRoomBanner();
  };
  const addRoom = () => {
    if (state.rooms.length >= SC.maxRooms()) return SC.toast(SC.t('booking.errors.too_many_cabins', { max: SC.maxRooms() }), 'error');
    state.rooms.push({ cabinId: null, guests: { adult: 1, child: 0, infant: 0 } });
    editRoom(state.rooms.length - 1);
    SC.toast(SC.t('ui.details.cabin_added', { n: state.rooms.length }));
  };
  const removeRoom = (i) => {
    if (state.rooms.length < 2) return;
    state.rooms.splice(i, 1);
    editRoom(Math.min(state.active > i ? state.active - 1 : state.active, state.rooms.length - 1));
  };
  // Switcher and summary both carry these buttons
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-room-edit], [data-room-remove], [data-room-add]'); if (!t) return;
    if (t.dataset.roomAdd !== undefined) addRoom();
    else if (t.dataset.roomRemove !== undefined) removeRoom(Number(t.dataset.roomRemove));
    else { editRoom(Number(t.dataset.roomEdit)); if (t.closest('#summary')) $('#cabins').scrollIntoView({ behavior: 'smooth' }); }
  });
  $('#d-cabins').addEventListener('click', (e) => {
    const sel = e.target.closest('[data-select-cabin]'); if (sel) return selectCabin(sel.dataset.selectCabin);
    const info = e.target.closest('[data-cabin-info]');
    if (info) {
      const c = SC.getCabin(info.dataset.cabinInfo);
      const ok = !(sailing.soldOut || []).includes(c.id) && c.maxGuests >= totalGuests();
      SC.ui.openCabin(c.id, ok ? selectCabin : null);
    }
  });

  /* ---------- Booking summary (sidebar + mobile bar) ---------- */
  const allGuests = () => state.rooms.reduce((t, r) => ({ adult: t.adult + r.guests.adult, child: t.child + r.guests.child, infant: t.infant + r.guests.infant }), { adult: 0, child: 0, infant: 0 });
  const missingRoom = () => state.rooms.findIndex((r) => !r.cabinId);
  const renderSummary = () => {
    if (state.rooms.length > 1) return renderRoomsSummary();
    const g = state.guests;
    const ret = SC.addDays(sailing.departure, cruise.nights);
    const cab = state.cabinId && SC.getCabin(state.cabinId);
    const q = cab && SC.quote({ cruise, sailing, cabinId: cab.id, guests: g });
    $('#summary').innerHTML = `
      <div class="bg-ocean-700 text-white p-5">
        <p class="text-xs text-ocean-200 font-semibold">${SC.t('ui.details.your_sailing')}</p>
        <p class="font-display font-semibold text-lg mt-0.5">${cruise.name}</p>
        <div class="mt-4 flex items-center justify-between text-sm">
          <div><p class="font-bold">${SC.fmtDate(sailing.departure, { day: 'numeric', month: 'short' })}</p><p class="text-ocean-200">${SC.port(cruise.departurePort)}</p></div>
          <div class="flex-1 mx-3 border-t-2 border-dashed border-ocean-400 relative"><span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-ocean-700 px-1">${SC.icon('ship', 'w-5 h-5 text-lagoon-400')}</span></div>
          <div class="text-end"><p class="font-bold">${SC.fmtDate(ret, { day: 'numeric', month: 'short' })}</p><p class="text-ocean-200">${SC.port(cruise.arrivalPort)}</p></div>
        </div>
      </div>
      <div class="p-5 grid gap-2 text-sm">
        <div class="flex justify-between gap-3"><span class="text-muted">${SC.t('ui.details.guests')}</span><span class="font-bold text-end">${SC.guestText(g)}</span></div>
        <div class="flex justify-between gap-3"><span class="text-muted">${SC.t('ui.details.cabin')}</span><span class="font-bold text-end">${cab ? cab.nameEn : `<a href="#cabins" class="text-ocean-600 underline">${SC.t('ui.details.not_chosen')}</a>`}</span></div>
      </div>
      <div class="perf-x"></div>
      <div class="p-5">
        ${q ? `<dl class="grid gap-2 text-sm">
            ${q.lines.map((l) => `<div class="flex justify-between gap-3"><dt class="text-muted">${l.label}${l.qty > 1 ? ` × ${l.qty}` : ''}</dt><dd class="tabular">${SC.money(l.total)}</dd></div>`).join('')}
          </dl>
          ${totalRow(q.total)}` : `<p class="text-sm text-muted">${SC.t('ui.details.choose_to_see_total')}</p>`}
        <button class="btn btn-accent w-full mt-5" data-continue ${q ? '' : 'disabled'}>${SC.t('ui.details.continue_guests')}</button>
        ${q ? `<button type="button" class="btn btn-ghost btn-sm w-full mt-2" data-room-add>${SC.icon('plus', 'w-4 h-4')} ${SC.t('ui.details.add_cabin')}</button>` : ''}
      </div>`;
    $('#m-total').textContent = q ? SC.money(q.total) : SC.t('ui.details.choose_cabin');
    $('#m-sub').textContent = q ? `${SC.guestText(g)}${SC.lang() === 'ar' ? '، ' : ', '}${cab.nameEn}` : SC.guestText(g);
  };

  /* Total of the summary. Prices include tax: no separate taxes or port fees (step 21). */
  function totalRow(total) {
    return `<div class="flex items-end justify-between gap-3 mt-4 pt-4 border-t border-line">
            <span><span class="block font-bold">${SC.t('ui.details.total')}</span><span class="text-xs font-semibold text-lagoon-600">${SC.t('ui.details.tax_included')}</span></span>
            <span class="font-display font-bold text-2xl text-ocean-800 tabular">${SC.money(total)}</span>
          </div>`;
  }

  /* Summary with several cabins: each cabin (type, guests, price, edit / remove), then the total */
  const renderRoomsSummary = () => {
    const ret = SC.addDays(sailing.departure, cruise.nights);
    const ready = missingRoom() < 0;
    const q = ready && SC.quoteRooms({ cruise, sailing, rooms: state.rooms });
    $('#summary').innerHTML = `
      <div class="bg-ocean-700 text-white p-5">
        <p class="text-xs text-ocean-200 font-semibold">${SC.t('ui.details.your_sailing')}</p>
        <p class="font-display font-semibold text-lg mt-0.5">${cruise.name}</p>
        <p class="text-sm text-ocean-200 mt-1">${SC.fmtDate(sailing.departure, { day: 'numeric', month: 'short' })} – ${SC.fmtDate(ret, { day: 'numeric', month: 'short' })} · ${SC.tc('ui.details.cabins_count', state.rooms.length)}</p>
      </div>
      <ul class="divide-y divide-line">
        ${state.rooms.map((r, i) => {
          const cab = r.cabinId && SC.getCabin(r.cabinId);
          const rq = cab && SC.quote({ cruise, sailing, cabinId: cab.id, guests: r.guests });
          return `<li class="p-4 text-sm ${i === state.active ? 'bg-ocean-50/60' : ''}">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0"><p class="font-bold">${cabinLabel(i)} · ${cab ? SC.esc(cab.nameEn) : `<span class="text-[#C42F2F]">${SC.t('ui.details.not_chosen')}</span>`}</p><p class="text-muted">${SC.guestText(r.guests)}</p></div>
              <div class="flex shrink-0 gap-1">
                <button type="button" class="text-ocean-600 font-bold hover:underline" data-room-edit="${i}">${SC.t('booking.edit')}</button>
                <button type="button" class="text-muted hover:text-[#C42F2F] ms-2" data-room-remove="${i}" aria-label="${SC.esc(SC.t('ui.details.remove_cabin', { n: i + 1 }))}">${SC.icon('x', 'w-4 h-4')}</button>
              </div>
            </div>
            ${rq ? `<p class="mt-1 flex justify-between"><span class="text-muted">${rq.lines.map((l) => l.label).join(' + ')}</span><span class="tabular font-bold">${SC.money(rq.fare)}</span></p>` : ''}
          </li>`;
        }).join('')}
      </ul>
      <div class="px-4 pb-2">${state.rooms.length < SC.maxRooms() ? `<button type="button" class="btn btn-ghost btn-sm w-full" data-room-add>${SC.icon('plus', 'w-4 h-4')} ${SC.t('ui.details.add_cabin')}</button>` : `<p class="text-xs text-muted text-center py-2">${SC.t('booking.errors.too_many_cabins', { max: SC.maxRooms() })}</p>`}</div>
      <div class="perf-x"></div>
      <div class="p-5">
        ${q ? `<dl class="grid gap-2 text-sm">
            <div class="flex justify-between"><dt class="text-muted">${SC.t('ui.details.guests')}</dt><dd class="font-bold text-end">${SC.guestText(allGuests())}</dd></div>
          </dl>
          ${totalRow(q.total)}` : `<p class="text-sm text-muted">${SC.t('ui.details.choose_cabin_n', { n: missingRoom() + 1 })}</p>`}
        <button class="btn btn-accent w-full mt-5" data-continue ${q ? '' : 'disabled'}>${SC.t('ui.details.continue_guests')}</button>
      </div>`;
    $('#m-total').textContent = q ? SC.money(q.total) : SC.t('ui.details.choose_cabin');
    $('#m-sub').textContent = `${SC.tc('ui.details.cabins_count', state.rooms.length)}${SC.lang() === 'ar' ? '، ' : ', '}${SC.guestText(allGuests())}`;
  };

  const proceed = () => {
    const missing = missingRoom();
    if (missing >= 0) {
      if (missing !== state.active) editRoom(missing);
      $('#cabins').scrollIntoView({ behavior: 'smooth' });
      SC.toast(state.rooms.length > 1 ? SC.t('ui.details.choose_cabin_n', { n: missing + 1 }) : SC.t('ui.details.choose_to_continue'), 'error');
      return;
    }
    const v = SC.sailingView(cruise, sailing);
    const rooms = state.rooms.map((r) => ({ cabinId: r.cabinId, guests: { ...r.guests } }));
    // Booking session payload (doc 2): cruise + sailing identifiers and key facts. rooms (step 15) holds every
    // cabin; cabinId / guests keep the first cabin and the guest totals for anything that reads the old shape.
    SC.booking.set({
      cruiseId: cruise.id, sailingId: sailing.id, cruiseName: cruise.name, ship: v.ship,
      departure: v.departure, returnDate: v.returnDate, nights: cruise.nights,
      departurePort: cruise.departurePort, arrivalPort: cruise.arrivalPort, currency: D.currency,
      fromPrice: v.fromPrice, rooms, cabinId: rooms[0].cabinId, guests: allGuests(),
      travellers: same ? saved.travellers : undefined, contact: same ? saved.contact : undefined
    });
    location.href = SC.route('booking');
  };
  document.addEventListener('click', (e) => { if (e.target.closest('[data-continue]')) proceed(); });
  $('#m-continue').addEventListener('click', proceed);

  /* ---------- Sub-nav active state ---------- */
  const links = $$('#subnav a');
  const io = new IntersectionObserver((entries) => entries.forEach((en) => {
    if (!en.isIntersecting) return;
    links.forEach((l) => { const on = l.getAttribute('href') === '#' + en.target.id; l.classList.toggle('border-ocean-600', on); l.classList.toggle('text-ocean-700', on); l.classList.toggle('border-transparent', !on); });
  }), { rootMargin: '-45% 0px -50% 0px' });
  ['overview', 'itinerary', 'cabins', 'amenities', 'safety'].forEach((id) => io.observe(document.getElementById(id)));

  /* ---------- Amenities ---------- */
  $('#d-amenities').innerHTML = D.facilities.map((f) => `
    <li class="flex gap-3 items-start rounded-2xl bg-white border border-line p-4">
      <span class="grid place-items-center w-10 h-10 rounded-xl bg-ocean-50 text-ocean-600 shrink-0">${SC.icon(f.icon)}</span>
      <div><p class="font-bold">${f.label} <span class="text-ocean-600">${f.value}</span></p><p class="text-sm text-muted mt-0.5">${f.text}</p></div>
    </li>`).join('');

  /* ---------- Initial render ---------- */
  const renderAllDynamic = () => { renderFacts(); renderDates(); renderItinerary(); checkCapacity(false); renderCabins(); renderSummary(); repaintCounters(); renderRoomBanner(); };
  renderAllDynamic();
});
