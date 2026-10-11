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
  const cruise = (found && found.cruise) || SC.getCruise(qs.get('cruise')) || D.cruises[0];
  const upcoming = cruise.sailings.filter((s) => SC.daysUntil(s.departure) > 0);
  let sailing = (found && found.sailing) || upcoming[0] || cruise.sailings[0];

  // Restore previous choices if the guest comes back to the same sailing
  const saved = SC.booking.get();
  const same = saved && saved.cruiseId === cruise.id;
  const state = {
    guests: same ? { ...saved.guests } : SC.guestsPref.get(),
    cabinId: same && saved.sailingId === sailing.id ? saved.cabinId : null
  };
  const maxCapacity = () => Math.max(...SC.cabinsSorted().filter((c) => !(sailing.soldOut || []).includes(c.id)).map((c) => c.maxGuests));
  const totalGuests = () => state.guests.adult + state.guests.child + state.guests.infant;

  document.title = `${cruise.name} | Safer Cruise`;
  $('#wa-fab')?.classList.replace('bottom-5', 'bottom-24');
  $('#wa-fab')?.classList.add('lg:bottom-5');

  /* ---------- Static header content ---------- */
  $('#crumb-name').textContent = cruise.name;
  $('#d-name').textContent = cruise.name;
  $('#d-ship').textContent = SC.getShip(cruise.shipId).name;
  $('#d-ports').textContent = cruise.departurePort === cruise.arrivalPort ? `Round trip from ${cruise.departurePort}` : `${cruise.departurePort} to ${cruise.arrivalPort}`;
  $('#d-rating').innerHTML = SC.ui.stars(cruise.rating, cruise.reviews) + ' <span>guest rating</span>';
  $('#d-tags').innerHTML = [
    cruise.visaFree ? '<span class="tag tag-lagoon">No visa needed</span>' : '',
    cruise.tags.includes('family') ? '<span class="tag tag-ocean">Family friendly</span>' : '',
    cruise.tags.includes('festive') ? '<span class="tag tag-sun">Festive sailing</span>' : ''
  ].join('');
  $('#d-summary').textContent = cruise.summary;
  $('#d-actions').insertAdjacentHTML('afterbegin', SC.ui.favBtn(cruise.id));
  $('#share-btn').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(location.href); SC.toast('Link copied'); }
    catch (e) { SC.toast('Copy the link from your address bar', 'error'); }
  });

  /* ---------- Gallery + lightbox ---------- */
  const imgs = cruise.gallery;
  $('#gallery-grid').innerHTML = imgs.slice(0, 5).map((src, i) => `
    <button class="relative img-zoom ${i === 0 ? 'col-span-2 row-span-2' : ''} ${imgs.length < 5 && i === imgs.length - 1 && i > 0 ? 'col-span-2' : ''}" data-photo="${i}" aria-label="Open photo ${i + 1}">
      <img src="${src}" alt="${cruise.name} photo ${i + 1}" class="absolute inset-0 w-full h-full object-cover" ${i ? 'loading="lazy"' : ''}>
    </button>`).join('');
  $('#gallery-strip').innerHTML = imgs.map((src, i) => `
    <button class="snap-start shrink-0 w-[85%] aspect-[4/3] rounded-2xl overflow-hidden" data-photo="${i}" aria-label="Open photo ${i + 1}"><img src="${src}" alt="" class="w-full h-full object-cover"></button>`).join('');

  let photo = 0;
  const openLightbox = (i) => {
    photo = i;
    const body = SC.ensureModal('gallery-modal', { title: cruise.name, width: '1040px' });
    body.innerHTML = `
      <div class="relative rounded-2xl overflow-hidden bg-ocean-900 aspect-[16/10]">
        <img id="lb-img" class="w-full h-full object-contain" alt="">
        <button class="icon-btn absolute start-3 top-1/2 -translate-y-1/2" id="lb-prev" aria-label="Previous photo">${SC.icon('chevron-left', 'w-5 h-5 rtl:rotate-180')}</button>
        <button class="icon-btn absolute end-3 top-1/2 -translate-y-1/2" id="lb-next" aria-label="Next photo">${SC.icon('chevron-right', 'w-5 h-5 rtl:rotate-180')}</button>
      </div>
      <p class="text-center text-sm text-muted mt-3 tabular" id="lb-count" aria-live="polite"></p>`;
    const show = () => { $('#lb-img').src = imgs[photo]; $('#lb-img').alt = `${cruise.name} photo ${photo + 1}`; $('#lb-count').textContent = `${photo + 1} of ${imgs.length}`; };
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
      fact('Departs', SC.fmtDate(sailing.departure, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }), cruise.departurePort) +
      fact('Returns', SC.fmtDate(ret, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }), cruise.arrivalPort) +
      fact('Duration', `${cruise.nights} nights`, `${cruise.nights + 1} days`) +
      fact('Ship', SC.getShip(cruise.shipId).name, `Sailing ${sailing.id}`);
  };

  /* ---------- Departure date chips ---------- */
  const renderDates = () => {
    $('#d-dates').innerHTML = upcoming.map((s) => {
      const on = s.id === sailing.id;
      const from = SC.sailingView(cruise, s).fromPrice;
      return `<button role="radio" aria-checked="${on}" data-sailing="${s.id}" class="text-start rounded-2xl border-2 px-4 py-3 transition ${on ? 'border-ocean-600 bg-ocean-50' : 'border-line bg-white hover:border-ocean-300'}">
        <span class="block font-bold">${SC.fmtDate(s.departure, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })}</span>
        <span class="block text-sm text-muted">from ${SC.money(from)} pp</span>
      </button>`;
    }).join('');
  };
  $('#d-dates').addEventListener('click', (e) => {
    const b = e.target.closest('[data-sailing]'); if (!b || b.dataset.sailing === sailing.id) return;
    sailing = cruise.sailings.find((s) => s.id === b.dataset.sailing);
    if (state.cabinId && (sailing.soldOut || []).includes(state.cabinId)) {
      SC.toast(`${SC.getCabin(state.cabinId).nameEn} is sold out on this date. Choose another cabin.`, 'error');
      state.cabinId = null;
    }
    history.replaceState(null, '', `details.html?cruise=${cruise.id}&sailing=${sailing.id}`);
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
            <span class="block text-[11px] font-bold">Day</span><span class="block font-display font-bold text-lg leading-none">${i + 1}</span>
          </span>
          <span class="flex-1 min-w-0">
            <span class="block font-bold truncate">${d.port}</span>
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
    e.currentTarget.textContent = openAll ? 'Collapse all' : 'Expand all';
  });

  /* ---------- Travellers (Who's travelling?) ---------- */
  $('#d-counters').innerHTML = ['adult', 'child', 'infant'].map((b) => SC.ui.counterRow(b, state.guests[b])).join('');
  const limits = (band, g) => {
    const cap = maxCapacity(); const others = totalGuests() - g[band];
    if (band === 'adult') return { min: Math.max(1, g.infant), max: cap - (others) };
    if (band === 'infant') return { min: 0, max: Math.min(g.adult, cap - others) };
    return { min: 0, max: cap - others };
  };
  const repaintCounters = SC.ui.bindCounters($('#d-counters'), state.guests, () => {
    SC.guestsPref.set(state.guests);
    checkCapacity(true);
    renderCabins(); renderSummary(); repaintCounters();
  }, limits);

  // If the chosen cabin can't hold the group, warn and list cabins that fit (doc 3)
  const checkCapacity = (fromCounter) => {
    const n = totalGuests(); const alert = $('#cap-alert');
    const cab = state.cabinId && SC.getCabin(state.cabinId);
    if (cab && cab.maxGuests < n) {
      const fits = SC.cabinsSorted().filter((c) => c.maxGuests >= n && !(sailing.soldOut || []).includes(c.id)).map((c) => c.nameEn);
      $('#cap-alert-text').innerHTML = `<b>${cab.nameEn}</b> fits up to ${cab.maxGuests} guests, and you've added ${n}. ${fits.length ? `Choose ${fits.join(' or ')} instead.` : 'Reduce the number of guests.'}`;
      alert.classList.remove('hidden'); alert.classList.add('flex');
      state.cabinId = null;
      if (fromCounter) SC.toast('Your cabin no longer fits everyone. Choose a larger one.', 'error');
    } else {
      alert.classList.add('hidden'); alert.classList.remove('flex');
    }
  };

  /* ---------- Cabin category cards ---------- */
  const renderCabins = () => {
    const n = totalGuests();
    $('#d-cabins').innerHTML = SC.cabinsSorted().map((c) => {
      const p = SC.prices(cruise, sailing, c.id);
      const soldOut = (sailing.soldOut || []).includes(c.id);
      const tooSmall = c.maxGuests < n;
      const selected = state.cabinId === c.id;
      const priceRow = (label, val) => `<div class="flex justify-between"><dt class="text-muted">${label}</dt><dd class="font-bold tabular">${SC.money(val)}</dd></div>`;
      return `<article class="card overflow-hidden flex flex-col transition ${selected ? 'card-selected' : ''} ${soldOut || tooSmall ? 'opacity-70' : ''}" data-cabin-card="${c.id}">
        <div class="relative aspect-[4/3] img-zoom">
          <img src="${c.images[0]}" alt="${c.nameEn}" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
          ${c.badge && !soldOut ? `<span class="absolute top-3 start-3 tag tag-sun">${c.badge}</span>` : ''}
          ${soldOut ? '<span class="absolute inset-0 grid place-items-center bg-ocean-900/55 text-white font-bold">Sold out on this date</span>' : ''}
          ${selected ? `<span class="absolute top-3 end-3 grid place-items-center w-8 h-8 rounded-full bg-ocean-600 text-white">${SC.icon('check', 'w-4 h-4')}</span>` : ''}
        </div>
        <div class="p-5 flex-1 flex flex-col">
          <h3 class="font-display font-semibold text-lg leading-snug">${c.nameEn}</h3>
          <p class="text-sm text-muted" lang="ar" dir="rtl">${c.nameAr}</p>
          <p class="text-sm mt-3 leading-relaxed">${c.short}</p>
          <ul class="mt-4 grid gap-1.5 text-sm text-muted">
            <li class="flex items-center gap-2 ${tooSmall ? 'text-[#C42F2F] font-semibold' : ''}">${SC.icon('users', 'w-4 h-4')} Up to ${c.maxGuests} guests</li>
            <li class="flex items-center gap-2">${SC.icon('size', 'w-4 h-4')} ${c.size}${c.balcony ? ` + ${c.balcony} balcony` : ''}</li>
            <li class="flex items-center gap-2">${SC.icon('bed', 'w-4 h-4')} <span class="truncate">${c.beds}</span></li>
          </ul>
          <button class="self-start mt-3 text-sm font-bold text-ocean-600 hover:underline" data-cabin-info="${c.id}">View details and photos</button>
          <dl class="mt-4 pt-4 border-t border-line grid gap-1.5 text-sm">
            ${priceRow('Adult', p.adult)}${priceRow('Child (2–11)', p.child)}${priceRow('Infant (under 2)', p.infant)}
          </dl>
          <div class="mt-auto pt-5">
            <p class="text-xs text-muted">From, per person</p>
            <p class="font-display font-bold text-2xl text-ocean-800 tabular">${SC.money(p.adult)}</p>
            <button class="btn ${selected ? 'btn-primary' : 'btn-accent'} w-full mt-3" data-select-cabin="${c.id}" ${soldOut || tooSmall ? 'disabled' : ''} aria-pressed="${selected}">
              ${soldOut ? 'Sold out' : tooSmall ? `Fits up to ${c.maxGuests} guests` : selected ? `${SC.icon('check', 'w-4 h-4')} Selected` : c.id === 'suite' ? 'Choose this suite' : 'Choose this cabin'}
            </button>
          </div>
        </div>
      </article>`;
    }).join('');
  };
  const selectCabin = (id) => {
    state.cabinId = id;
    checkCapacity(false);
    renderCabins(); renderSummary();
    SC.toast(`${SC.getCabin(id).nameEn} selected`);
  };
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
  const renderSummary = () => {
    const g = state.guests;
    const ret = SC.addDays(sailing.departure, cruise.nights);
    const cab = state.cabinId && SC.getCabin(state.cabinId);
    const q = cab && SC.quote({ cruise, sailing, cabinId: cab.id, guests: g });
    $('#summary').innerHTML = `
      <div class="bg-ocean-700 text-white p-5">
        <p class="text-xs text-ocean-200 font-semibold">Your sailing</p>
        <p class="font-display font-semibold text-lg mt-0.5">${cruise.name}</p>
        <div class="mt-4 flex items-center justify-between text-sm">
          <div><p class="font-bold">${SC.fmtDate(sailing.departure, { day: 'numeric', month: 'short' })}</p><p class="text-ocean-200">${cruise.departurePort}</p></div>
          <div class="flex-1 mx-3 border-t-2 border-dashed border-ocean-400 relative"><span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-ocean-700 px-1">${SC.icon('ship', 'w-5 h-5 text-lagoon-400')}</span></div>
          <div class="text-end"><p class="font-bold">${SC.fmtDate(ret, { day: 'numeric', month: 'short' })}</p><p class="text-ocean-200">${cruise.arrivalPort}</p></div>
        </div>
      </div>
      <div class="p-5 grid gap-2 text-sm">
        <div class="flex justify-between gap-3"><span class="text-muted">Guests</span><span class="font-bold text-end">${SC.guestText(g)}</span></div>
        <div class="flex justify-between gap-3"><span class="text-muted">Cabin</span><span class="font-bold text-end">${cab ? cab.nameEn : '<a href="#cabins" class="text-ocean-600 underline">Not chosen yet</a>'}</span></div>
      </div>
      <div class="perf-x"></div>
      <div class="p-5">
        ${q ? `<dl class="grid gap-2 text-sm">
            ${q.lines.map((l) => `<div class="flex justify-between"><dt class="text-muted">${l.label} × ${l.qty}</dt><dd class="tabular">${SC.money(l.total)}</dd></div>`).join('')}
            <div class="flex justify-between"><dt class="text-muted">Taxes & port fees</dt><dd class="tabular">${SC.money(q.fees)}</dd></div>
          </dl>
          <div class="flex items-end justify-between mt-4 pt-4 border-t border-line">
            <span class="font-bold">Total</span><span class="font-display font-bold text-2xl text-ocean-800 tabular">${SC.money(q.total)}</span>
          </div>` : '<p class="text-sm text-muted">Choose a cabin to see your total price.</p>'}
        <button class="btn btn-accent w-full mt-5" data-continue ${q ? '' : 'disabled'}>Continue to guest details</button>
      </div>`;
    $('#m-total').textContent = q ? SC.money(q.total) : 'Choose a cabin';
    $('#m-sub').textContent = q ? `${SC.guestText(g)}, ${cab.nameEn}` : SC.guestText(g);
  };

  const proceed = () => {
    if (!state.cabinId) { $('#cabins').scrollIntoView({ behavior: 'smooth' }); SC.toast('Choose a cabin to continue', 'error'); return; }
    const v = SC.sailingView(cruise, sailing);
    // Booking session payload (doc 2): cruise + sailing identifiers and key facts
    SC.booking.set({
      cruiseId: cruise.id, sailingId: sailing.id, cruiseName: cruise.name, ship: v.ship,
      departure: v.departure, returnDate: v.returnDate, nights: cruise.nights,
      departurePort: cruise.departurePort, arrivalPort: cruise.arrivalPort, currency: D.currency,
      fromPrice: v.fromPrice, cabinId: state.cabinId, guests: { ...state.guests },
      travellers: same ? saved.travellers : undefined, contact: same ? saved.contact : undefined
    });
    location.href = 'booking.html';
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
  const renderAllDynamic = () => { renderFacts(); renderDates(); renderItinerary(); checkCapacity(false); renderCabins(); renderSummary(); repaintCounters(); };
  renderAllDynamic();
});
