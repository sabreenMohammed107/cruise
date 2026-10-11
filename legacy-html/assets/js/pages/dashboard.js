/* =====================================================================
   Dashboard — dashboard.html  (#overview | #bookings | #favorites | #profile)
   Bookings = trips completed in this browser (booking.js) + sample data.
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;

  /* ---------- Profile (persisted locally in this demo) ---------- */
  const profile = SC.local.get('sc_profile', { first: 'Reem', last: 'Alharbi', email: 'reem@example.com', dial: '+966', phone: '501234567', dob: '1990-04-12', nationality: 'Saudi Arabia', notif: { offers: true, whatsapp: true, reminders: true } });
  const paintUser = () => {
    $('#u-name').textContent = `${profile.first} ${profile.last}`;
    $('#u-email').textContent = profile.email;
    $('#u-initials').textContent = (profile.first[0] || '') + (profile.last[0] || '');
    $('#greeting').textContent = `Welcome back, ${profile.first}`;
  };

  /* ---------- Bookings: sample + real ---------- */
  const sample = (cruiseId, departure, cabinId, guests, status, ref) => {
    const c = SC.getCruise(cruiseId);
    const q = SC.quote({ cruise: c, sailing: { factor: 1 }, cabinId, guests });
    return { ref, cruiseId, cruiseName: c.name, ship: 'AROYA', departure, returnDate: SC.addDays(departure, c.nights), nights: c.nights,
      departurePort: c.departurePort, arrivalPort: c.arrivalPort, cabinId, guests, amountDue: q.total, quote: q, status,
      travellers: { 'adult-1': { first: profile.first, last: profile.last, nationality: profile.nationality, dob: profile.dob } }, contact: { email: profile.email, dial: profile.dial, phone: profile.phone } };
  };
  const bookings = [
    ...SC.trips.all(),
    sample('saudi-coast-weekend', '2026-11-05', 'balcony', { adult: 2, child: 1, infant: 0 }, 'confirmed', 'SC2611-K4P2Q'),
    sample('red-sea-discovery', '2026-03-15', 'interior', { adult: 2, child: 0, infant: 0 }, 'confirmed', 'SC2603-H7M1Z'),
    sample('egypt-jordan-voyage', '2026-05-22', 'suite', { adult: 2, child: 2, infant: 0 }, 'cancelled', 'SC2605-T9R3B')
  ];
  const group = (b) => (b.status === 'cancelled' ? 'cancelled' : SC.daysUntil(b.departure) >= 0 ? 'upcoming' : 'past');
  const imgFor = (b) => SC.getCruise(b.cruiseId)?.image;

  const STATUS = { upcoming: ['tag-lagoon', 'Confirmed'], past: ['tag-ocean', 'Completed'], cancelled: ['tag-sun', 'Cancelled'] };
  const bookingCard = (b) => {
    const g = group(b); const cab = SC.getCabin(b.cabinId); const days = SC.daysUntil(b.departure);
    return `<article class="card overflow-hidden flex flex-col md:flex-row ${g === 'cancelled' ? 'opacity-80' : ''}">
      <div class="relative md:w-56 aspect-[16/9] md:aspect-auto"><img src="${imgFor(b)}" alt="" class="absolute inset-0 w-full h-full object-cover ${g !== 'upcoming' ? 'grayscale-[40%]' : ''}" loading="lazy"></div>
      <div class="flex-1 p-5">
        <div class="flex flex-wrap items-center gap-2"><span class="tag ${STATUS[g][0]}">${STATUS[g][1]}</span>${g === 'upcoming' && days >= 0 ? `<span class="text-xs font-bold text-muted">${days === 0 ? 'Sails today' : `Sails in ${SC.plural(days, 'day')}`}</span>` : ''}</div>
        <h3 class="font-display font-semibold text-lg mt-2">${SC.esc(b.cruiseName)}</h3>
        <p class="text-sm text-muted">${SC.fmtDate(b.departure)} – ${SC.fmtDate(b.returnDate)}, ${b.departurePort}</p>
        <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
          <div><dt class="text-xs text-muted font-semibold">Reference</dt><dd class="font-bold tabular">${b.ref}</dd></div>
          <div><dt class="text-xs text-muted font-semibold">Cabin</dt><dd class="font-bold">${cab.nameEn}</dd></div>
          <div><dt class="text-xs text-muted font-semibold">Guests</dt><dd class="font-bold">${SC.guestText(b.guests)}</dd></div>
          <div><dt class="text-xs text-muted font-semibold">Total</dt><dd class="font-bold tabular">${SC.money(b.amountDue ?? b.amountPaid)}</dd></div>
        </dl>
      </div>
      <div class="p-5 pt-0 md:pt-5 md:w-48 flex md:flex-col gap-2 md:border-s md:border-line md:justify-center">
        <button class="btn btn-primary btn-sm flex-1 md:flex-none" data-booking="${b.ref}">View details</button>
        ${g === 'upcoming' ? `<a class="btn btn-outline btn-sm flex-1 md:flex-none" target="_blank" rel="noopener" href="${SC.whatsapp(`Hello, I'd like to change booking ${b.ref}.`)}">Change booking</a>` : ''}
        ${g === 'past' ? `<a class="btn btn-outline btn-sm flex-1 md:flex-none" href="details.html?cruise=${b.cruiseId}">Book again</a>` : ''}
      </div>
    </article>`;
  };
  const empty = (title, text) => `<div class="card p-10 text-center"><span class="inline-grid place-items-center w-14 h-14 rounded-2xl bg-ocean-50 text-ocean-600">${SC.icon('ticket', 'w-7 h-7')}</span><h3 class="font-display font-semibold text-lg mt-4">${title}</h3><p class="text-muted mt-1">${text}</p><a href="catalog.html" class="btn btn-primary mt-5">Browse cruises</a></div>`;

  const renderBookings = () => {
    const by = { upcoming: [], past: [], cancelled: [] };
    bookings.forEach((b) => by[group(b)].push(b));
    by.upcoming.sort((a, b) => a.departure.localeCompare(b.departure));
    by.past.sort((a, b) => b.departure.localeCompare(a.departure));
    Object.entries(by).forEach(([k, list]) => {
      $(`#n-${k}`).textContent = list.length;
      $(`#list-${k}`).innerHTML = list.length ? list.map(bookingCard).join('') : empty(k === 'upcoming' ? 'No upcoming cruises' : `No ${k} bookings`, k === 'upcoming' ? 'Your next sailing will appear here once it is booked.' : 'Nothing here yet.');
    });
    return by;
  };

  /* Booking detail modal */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-booking]'); if (!btn) return;
    const b = bookings.find((x) => x.ref === btn.dataset.booking); const cab = SC.getCabin(b.cabinId); const q = b.quote;
    const body = SC.ensureModal('booking-modal', { title: `Booking ${b.ref}`, width: '720px' });
    body.innerHTML = `
      <div class="flex gap-4 items-center"><img src="${cab.images[0]}" alt="" class="w-24 h-20 rounded-xl object-cover"><div><p class="font-display font-semibold text-lg">${SC.esc(b.cruiseName)}</p><p class="text-sm text-muted">${SC.fmtDate(b.departure)} – ${SC.fmtDate(b.returnDate)}</p><p class="text-sm text-muted">${cab.nameEn}, ${b.departurePort} to ${b.arrivalPort}</p></div></div>
      <h3 class="font-bold mt-6 mb-2">Travellers</h3>
      <ul class="grid gap-2 text-sm">${Object.entries(b.travellers).map(([k, t]) => `<li class="flex justify-between rounded-xl bg-page px-3 py-2"><span class="font-semibold">${SC.esc(t.first)} ${SC.esc(t.last)}</span><span class="text-muted">${D.ageBands[k.split('-')[0]].label}</span></li>`).join('')}</ul>
      <h3 class="font-bold mt-6 mb-2">Price</h3>
      <dl class="grid gap-1.5 text-sm tabular">
        ${q.lines.map((l) => `<div class="flex justify-between"><dt class="text-muted">${l.label} ${SC.money(l.unit)} × ${l.qty}</dt><dd>${SC.money(l.total)}</dd></div>`).join('')}
        <div class="flex justify-between"><dt class="text-muted">Taxes & port fees</dt><dd>${SC.money(q.fees)}</dd></div>
        ${q.discount ? `<div class="flex justify-between text-[#12805C]"><dt>Discount</dt><dd>− ${SC.money(q.discount)}</dd></div>` : ''}
        <div class="flex justify-between pt-2 mt-1 border-t border-line font-bold text-base"><dt>Total</dt><dd>${SC.money(q.total)}</dd></div>
      </dl>
      <div class="mt-6 flex flex-wrap justify-end gap-2">
        <button class="btn btn-ghost" data-terms-link>Cancellation policy</button>
        <button class="btn btn-outline" onclick="window.print()">${SC.icon('download', 'w-4 h-4')} Download voucher</button>
      </div>`;
    body.querySelector('[data-terms-link]').onclick = () => { SC.modal.close('booking-modal'); SC.ui.openTerms('cancellation'); };
    SC.modal.open('booking-modal');
  });

  /* ---------- Overview ---------- */
  const renderOverview = (by) => {
    const next = by.upcoming[0];
    $('#next-trip').innerHTML = next ? `
      <article class="card pass overflow-hidden flex flex-col md:flex-row" style="--perf-bg:#F5F8FB">
        <div class="relative md:w-2/5 aspect-[16/9] md:aspect-auto"><img src="${imgFor(next)}" alt="" class="absolute inset-0 w-full h-full object-cover"></div>
        <div class="flex-1 p-6">
          <p class="text-xs font-bold text-lagoon-600">Your next cruise</p>
          <h2 class="font-display font-semibold text-2xl mt-1">${SC.esc(next.cruiseName)}</h2>
          <div class="mt-5 flex items-center gap-3 text-sm">
            <div><p class="font-bold">${SC.fmtDate(next.departure, { day: 'numeric', month: 'short' })}</p><p class="text-muted">${next.departurePort}</p></div>
            <div class="flex-1 border-t-2 border-dashed border-line relative"><span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white px-1 text-ocean-500">${SC.icon('ship', 'w-5 h-5')}</span></div>
            <div class="text-end"><p class="font-bold">${SC.fmtDate(next.returnDate, { day: 'numeric', month: 'short' })}</p><p class="text-muted">${next.arrivalPort}</p></div>
          </div>
        </div>
        <div class="md:w-44 p-6 md:border-s-2 md:border-dashed md:border-line flex md:flex-col items-center justify-between md:justify-center gap-3 bg-ocean-50/60">
          <div class="text-center"><p class="font-display font-bold text-4xl text-ocean-700 tabular">${Math.max(0, SC.daysUntil(next.departure))}</p><p class="text-sm text-muted">days to go</p></div>
          <button class="btn btn-primary btn-sm" data-booking="${next.ref}">Details</button>
        </div>
      </article>` : empty('No upcoming cruises', 'Pick a sailing and your countdown starts here.');
    const stat = (icon, n, label) => `<div class="card p-5 flex items-center gap-4"><span class="grid place-items-center w-11 h-11 rounded-xl bg-ocean-50 text-ocean-600">${SC.icon(icon)}</span><div><p class="font-display font-bold text-2xl tabular">${n}</p><p class="text-sm text-muted">${label}</p></div></div>`;
    $('#stats').innerHTML = stat('ticket', by.upcoming.length, 'Upcoming cruises') + stat('anchor', by.past.length, 'Cruises completed') + stat('heart', SC.favs.all().length, 'Saved cruises');
  };

  /* ---------- Favourites ---------- */
  const renderFavs = () => {
    const views = SC.favs.all().map((id) => SC.allSailings().find((s) => s.cruiseId === id)).filter(Boolean);
    $('#fav-grid').innerHTML = views.length ? views.map((v) => SC.ui.sailingCard(v)).join('')
      : `<div class="sm:col-span-2 xl:col-span-3 card p-10 text-center"><span class="inline-grid place-items-center w-14 h-14 rounded-2xl bg-ocean-50 text-rose-500">${SC.icon('heart', 'w-7 h-7')}</span><h3 class="font-display font-semibold text-lg mt-4">No saved cruises yet</h3><p class="text-muted mt-1">Tap the heart on any cruise to keep it here.</p><a href="catalog.html" class="btn btn-primary mt-5">Browse cruises</a></div>`;
  };
  document.addEventListener('sc:favs', () => { renderFavs(); renderOverview(by); });

  /* ---------- Profile forms ---------- */
  D.dialCodes.forEach(([c, iso]) => $('#p-dial').add(new Option(`${iso} ${c}`, c)));
  D.nationalities.forEach((n) => $('#p-nat').add(new Option(n, n)));
  const fill = () => { $('#p-first').value = profile.first; $('#p-last').value = profile.last; $('#p-email').value = profile.email; $('#p-dial').value = profile.dial; $('#p-phone').value = profile.phone; $('#p-dob').value = profile.dob; $('#p-nat').value = profile.nationality; };
  // Shows/hides the .err line under a field (phone input sits inside a .flex row, so its error is one level up)
  const flag = (el, bad) => {
    el.classList.toggle('is-invalid', bad);
    const err = el.parentElement.classList.contains('flex') ? el.parentElement.nextElementSibling : el.nextElementSibling;
    err?.classList.toggle('show', bad);
    return bad;
  };

  $('#profile-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const bad = [
      flag($('#p-first'), !$('#p-first').value.trim()),
      flag($('#p-last'), !$('#p-last').value.trim()),
      flag($('#p-email'), !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test($('#p-email').value.trim())),
      flag($('#p-phone'), !/^\d{7,12}$/.test($('#p-phone').value.replace(/\s/g, '')))
    ].some(Boolean);
    if (bad) return SC.toast('Fix the highlighted fields to save', 'error');
    Object.assign(profile, { first: $('#p-first').value.trim(), last: $('#p-last').value.trim(), email: $('#p-email').value.trim(), dial: $('#p-dial').value, phone: $('#p-phone').value.trim(), dob: $('#p-dob').value, nationality: $('#p-nat').value });
    SC.local.set('sc_profile', profile); paintUser(); SC.toast('Changes saved');
  });

  const NOTIF = [['reminders', 'Trip reminders', 'Check-in times and what to bring, a week before you sail.'], ['whatsapp', 'WhatsApp updates', 'Booking changes and port updates by WhatsApp.'], ['offers', 'New sailings and offers', 'At most two emails a month.']];
  $('#notif').innerHTML = NOTIF.map(([k, t, d]) => `
    <li class="py-4 flex items-center justify-between gap-4">
      <div><p class="font-bold">${t}</p><p class="text-sm text-muted">${d}</p></div>
      <button role="switch" aria-checked="${!!profile.notif[k]}" data-notif="${k}" aria-label="${t}" class="relative w-12 h-7 rounded-full transition shrink-0 ${profile.notif[k] ? 'bg-ocean-600' : 'bg-line'}">
        <span class="absolute top-1 start-1 w-5 h-5 rounded-full bg-white shadow transition ${profile.notif[k] ? 'translate-x-5 rtl:-translate-x-5' : ''}"></span>
      </button>
    </li>`).join('');
  $('#notif').addEventListener('click', (e) => {
    const s = e.target.closest('[data-notif]'); if (!s) return;
    const on = !(s.getAttribute('aria-checked') === 'true'); profile.notif[s.dataset.notif] = on; SC.local.set('sc_profile', profile);
    s.setAttribute('aria-checked', on); s.classList.toggle('bg-ocean-600', on); s.classList.toggle('bg-line', !on);
    s.firstElementChild.classList.toggle('translate-x-5', on); s.firstElementChild.classList.toggle('rtl:-translate-x-5', on);
    SC.toast(on ? 'Notifications on' : 'Notifications off');
  });

  $('#pw-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const n = $('#pw-new').value;
    const bad = [flag($('#pw-old'), !$('#pw-old').value), flag($('#pw-new'), n.length < 8 || !/\d/.test(n)), flag($('#pw-rep'), $('#pw-rep').value !== n || !n)].some(Boolean);
    if (bad) return;
    e.target.reset(); SC.toast('Password updated');
  });

  $('#sign-out').addEventListener('click', () => { SC.toast('Signed out'); setTimeout(() => (location.href = 'index.html'), 700); });

  /* ---------- Section routing by hash ---------- */
  const SECTIONS = ['overview', 'bookings', 'favorites', 'profile'];
  const show = () => {
    const key = SECTIONS.includes(location.hash.slice(1)) ? location.hash.slice(1) : 'overview';
    $$('[data-dash]').forEach((s) => (s.hidden = s.dataset.dash !== key));
    $$('#dash-nav [data-section]').forEach((a) => { const on = a.dataset.section === key; a.classList.toggle('is-active', on); on ? a.setAttribute('aria-current', 'page') : a.removeAttribute('aria-current'); });
    window.scrollTo({ top: 0 });
  };
  window.addEventListener('hashchange', show);

  paintUser(); fill();
  const by = renderBookings();
  renderOverview(by); renderFavs(); show();
});
