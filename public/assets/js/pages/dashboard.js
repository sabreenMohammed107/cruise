/* =====================================================================
   Account dashboard — /account  (#overview | #bookings | #favorites | #profile)
   Laravel: profile, bookings and favourites come from the signed-in
   account (SC_DATA.account); changes are saved with SC.api().
   Words: lang/{en,ar}/site.php → account (SC.t / SC.tc).
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;
  const t = (key, r) => SC.t(`account.${key}`, r);

  /* ---------- Profile ---------- */
  const profile = D.account.profile;
  const paintUser = () => {
    $('#u-name').textContent = `${profile.first} ${profile.last}`.trim();
    $('#u-email').textContent = profile.email;
    $('#u-initials').textContent = ((profile.first || '')[0] || '') + ((profile.last || '')[0] || '');
    $('#greeting').textContent = profile.first ? t('welcome_name', { name: profile.first }) : t('welcome');
  };

  /* ---------- Bookings (from the account) ---------- */
  const bookings = D.account.bookings;
  const group = (b) => (b.status === 'cancelled' ? 'cancelled' : SC.daysUntil(b.departure) >= 0 ? 'upcoming' : 'past');
  const imgFor = (b) => SC.getCruise(b.cruiseId)?.image || b.image;

  const STATUS = { upcoming: ['tag-lagoon', t('status_confirmed')], past: ['tag-ocean', t('status_completed')], cancelled: ['tag-sun', t('status_cancelled')] };
  const PAYMENT = { unpaid: ['tag-sun', t('payment_unpaid')], partial: ['tag-sun', t('payment_partial')], paid: ['tag-lagoon', t('payment_paid')], refunded: ['tag-ocean', t('payment_refunded')] };
  const paymentTag = (b) => (PAYMENT[b.paymentStatus] ? `<span class="tag ${PAYMENT[b.paymentStatus][0]}">${PAYMENT[b.paymentStatus][1]}</span>` : '');
  /* Cabins of a booking (step 15): "Balcony Cabin", or "2 × Balcony Cabin, Interior Cabin" */
  const roomsOf = (b) => (b.cabins && b.cabins.length ? b.cabins : [{ position: 1, cabinId: b.cabinId, guests: b.guests }]);
  const cabinNames = (b) => {
    const counts = {};
    roomsOf(b).forEach((r) => { counts[r.cabinId] = (counts[r.cabinId] || 0) + 1; });
    return Object.entries(counts).map(([id, n]) => `${n > 1 ? `${n} × ` : ''}${SC.esc(SC.getCabin(id)?.nameEn || id)}`).join(SC.lang() === 'ar' ? '، ' : ', ');
  };
  const bookingCard = (b) => {
    const g = group(b); const cab = SC.getCabin(b.cabinId); const days = SC.daysUntil(b.departure);
    return `<article class="card overflow-hidden flex flex-col md:flex-row ${g === 'cancelled' ? 'opacity-80' : ''}">
      <div class="relative md:w-56 aspect-[16/9] md:aspect-auto"><img src="${imgFor(b)}" alt="" class="absolute inset-0 w-full h-full object-cover ${g !== 'upcoming' ? 'grayscale-[40%]' : ''}" loading="lazy"></div>
      <div class="flex-1 p-5">
        <div class="flex flex-wrap items-center gap-2"><span class="tag ${STATUS[g][0]}">${STATUS[g][1]}</span>${g === 'upcoming' ? paymentTag(b) : ''}${g === 'upcoming' && days >= 0 ? `<span class="text-xs font-bold text-muted">${days === 0 ? t('sails_today') : SC.tc('account.sails_in', days)}</span>` : ''}</div>
        <h3 class="font-display font-semibold text-lg mt-2">${SC.esc(b.cruiseName)}</h3>
        <p class="text-sm text-muted">${SC.fmtDate(b.departure)} – ${SC.fmtDate(b.returnDate)}${SC.lang() === 'ar' ? '، ' : ', '}${SC.esc(SC.port(b.departurePort))}</p>
        <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
          <div><dt class="text-xs text-muted font-semibold">${t('reference')}</dt><dd class="font-bold tabular" dir="ltr">${b.ref}</dd></div>
          <div><dt class="text-xs text-muted font-semibold">${SC.t('booking.cabin')}</dt><dd class="font-bold">${cabinNames(b)}</dd></div>
          <div><dt class="text-xs text-muted font-semibold">${SC.t('booking.guests')}</dt><dd class="font-bold">${SC.guestText(b.guests)}</dd></div>
          <div><dt class="text-xs text-muted font-semibold">${t('total')}</dt><dd class="font-bold tabular">${SC.money(b.amountDue)}</dd></div>
        </dl>
      </div>
      <div class="p-5 pt-0 md:pt-5 md:w-48 flex md:flex-col gap-2 md:border-s md:border-line md:justify-center">
        <button class="btn btn-primary btn-sm flex-1 md:flex-none" data-booking="${b.ref}">${t('view_details')}</button>
        ${g === 'upcoming' ? `<a class="btn btn-outline btn-sm flex-1 md:flex-none" target="_blank" rel="noopener" href="${SC.whatsapp(t('whatsapp_change', { ref: b.ref }))}">${t('change')}</a>` : ''}
        ${g === 'past' ? `<a class="btn btn-outline btn-sm flex-1 md:flex-none" href="${SC.detailsUrl({ cruiseId: b.cruiseId })}">${t('book_again')}</a>` : ''}
      </div>
    </article>`;
  };
  const empty = (title, text) => `<div class="card p-10 text-center"><span class="inline-grid place-items-center w-14 h-14 rounded-2xl bg-ocean-50 text-ocean-600">${SC.icon('ticket', 'w-7 h-7')}</span><h3 class="font-display font-semibold text-lg mt-4">${title}</h3><p class="text-muted mt-1">${text}</p><a href="${SC.route('catalog')}" class="btn btn-primary mt-5">${SC.t('booking.browse')}</a></div>`;

  const renderBookings = () => {
    const by = { upcoming: [], past: [], cancelled: [] };
    bookings.forEach((b) => by[group(b)].push(b));
    by.upcoming.sort((a, b) => a.departure.localeCompare(b.departure));
    by.past.sort((a, b) => b.departure.localeCompare(a.departure));
    Object.entries(by).forEach(([k, list]) => {
      $(`#n-${k}`).textContent = list.length;
      $(`#list-${k}`).innerHTML = list.length ? list.map(bookingCard).join('') : empty(t(`no_${k}`), k === 'upcoming' ? t('no_upcoming_text') : t('nothing'));
    });
    return by;
  };

  /* A saved price line in the page language: the label was stored in the language of the booking, so use its
     band (single / double / extra / infant, or adult / child on bookings made before tiered pricing) */
  const lineLabel = (l) => (SC.t(`pricing.${l.band}`) !== `pricing.${l.band}` ? SC.t(`pricing.${l.band}`) : D.ageBands[l.band]?.label || l.label);

  /* Booking detail modal */
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-booking]'); if (!btn) return;
    const b = bookings.find((x) => x.ref === btn.dataset.booking); const cab = SC.getCabin(b.cabinId); const q = b.quote;
    const several = roomsOf(b).length > 1;
    const body = SC.ensureModal('booking-modal', { title: t('booking_title', { ref: b.ref }), width: '720px' });
    body.innerHTML = `
      <div class="flex gap-4 items-center"><img src="${cab ? cab.images[0] : imgFor(b)}" alt="" class="w-24 h-20 rounded-xl object-cover"><div><p class="font-display font-semibold text-lg">${SC.esc(b.cruiseName)}</p><p class="text-sm text-muted">${SC.fmtDate(b.departure)} – ${SC.fmtDate(b.returnDate)}</p><p class="text-sm text-muted">${cabinNames(b)}${SC.lang() === 'ar' ? '، ' : ', '}${SC.esc(SC.t('booking.route', { from: SC.port(b.departurePort), to: SC.port(b.arrivalPort) }))}</p></div></div>
      <h3 class="font-bold mt-6 mb-2">${SC.t('booking.travellers')}</h3>
      <ul class="grid gap-2 text-sm">${Object.entries(b.travellers).sort(([, x], [, y]) => (x.cabin || 1) - (y.cabin || 1)).map(([k, t], i, all) => `${several && (all[i - 1]?.[1].cabin || 1) !== (t.cabin || 1) ? `<li class="pt-2 text-xs font-bold text-muted">${SC.t('ui.details.cabin_n', { n: t.cabin || 1 })} · ${SC.esc(SC.getCabin(roomsOf(b)[(t.cabin || 1) - 1]?.cabinId)?.nameEn || '')}</li>` : ''}<li class="flex justify-between rounded-xl bg-page px-3 py-2"><span class="font-semibold">${SC.esc(t.first)} ${SC.esc(t.last)}</span><span class="text-muted">${D.ageBands[k.split('-')[0]].label}</span></li>`).join('')}</ul>
      <h3 class="font-bold mt-6 mb-2">${t('price')}</h3>
      <dl class="grid gap-1.5 text-sm tabular">
        ${q.lines.map((l, i) => `${several && q.lines[i - 1]?.cabin !== l.cabin ? `<div class="pt-1 text-xs font-bold text-muted">${SC.t('ui.details.cabin_n', { n: l.cabin })}</div>` : ''}<div class="flex justify-between"><dt class="text-muted">${lineLabel(l)}${l.qty > 1 ? ` × ${l.qty}` : ''}</dt><dd>${SC.money(l.total)}</dd></div>`).join('')}
        ${q.fees > 0 ? `<div class="flex justify-between"><dt class="text-muted">${SC.t('booking.fees_short')}</dt><dd>${SC.money(q.fees)}</dd></div>` : '' /* bookings made before step 21 only */}
        ${q.discount ? `<div class="flex justify-between text-[#12805C]"><dt>${t('discount')}</dt><dd>− ${SC.money(q.discount)}</dd></div>` : ''}
        <div class="flex justify-between pt-2 mt-1 border-t border-line font-bold text-base"><dt>${t('total')}${q.fees > 0 ? '' : ` <span class="block text-xs font-semibold text-lagoon-600">${SC.t('booking.tax_included')}</span>`}</dt><dd>${SC.money(q.total)}</dd></div>
        <div class="flex justify-between"><dt class="text-muted">${t('paid_so_far')}</dt><dd>${SC.money(b.amountPaid)} ${paymentTag(b)}</dd></div>
      </dl>
      <div class="mt-6 flex flex-wrap justify-end gap-2">
        <button class="btn btn-ghost" data-terms-link>${t('cancellation_policy')}</button>
        <a class="btn btn-outline" href="${b.voucherUrl}">${SC.icon('download', 'w-4 h-4')} ${t('voucher')}</a>
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
          <p class="text-xs font-bold text-lagoon-600">${t('next_cruise')}</p>
          <h2 class="font-display font-semibold text-2xl mt-1">${SC.esc(next.cruiseName)}</h2>
          <div class="mt-5 flex items-center gap-3 text-sm">
            <div><p class="font-bold">${SC.fmtDate(next.departure, { day: 'numeric', month: 'short' })}</p><p class="text-muted">${SC.esc(SC.port(next.departurePort))}</p></div>
            <div class="flex-1 border-t-2 border-dashed border-line relative"><span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-white px-1 text-ocean-500">${SC.icon('ship', 'w-5 h-5')}</span></div>
            <div class="text-end"><p class="font-bold">${SC.fmtDate(next.returnDate, { day: 'numeric', month: 'short' })}</p><p class="text-muted">${SC.esc(SC.port(next.arrivalPort))}</p></div>
          </div>
        </div>
        <div class="md:w-44 p-6 md:border-s-2 md:border-dashed md:border-line flex md:flex-col items-center justify-between md:justify-center gap-3 bg-ocean-50/60">
          <div class="text-center"><p class="font-display font-bold text-4xl text-ocean-700 tabular">${Math.max(0, SC.daysUntil(next.departure))}</p><p class="text-sm text-muted">${t('days_to_go')}</p></div>
          <button class="btn btn-primary btn-sm" data-booking="${next.ref}">${t('details')}</button>
        </div>
      </article>` : empty(t('no_upcoming'), t('countdown'));
    const stat = (icon, n, label) => `<div class="card p-5 flex items-center gap-4"><span class="grid place-items-center w-11 h-11 rounded-xl bg-ocean-50 text-ocean-600">${SC.icon(icon)}</span><div><p class="font-display font-bold text-2xl tabular">${n}</p><p class="text-sm text-muted">${label}</p></div></div>`;
    $('#stats').innerHTML = stat('ticket', by.upcoming.length, t('stat_upcoming')) + stat('anchor', by.past.length, t('stat_completed')) + stat('heart', SC.favs.all().length, t('stat_saved'));
  };

  /* ---------- Favourites ---------- */
  const renderFavs = () => {
    const views = SC.favs.all().map((id) => SC.allSailings().find((s) => s.cruiseId === id)).filter(Boolean);
    $('#fav-grid').innerHTML = views.length ? views.map((v) => SC.ui.sailingCard(v)).join('')
      : `<div class="sm:col-span-2 xl:col-span-3 card p-10 text-center"><span class="inline-grid place-items-center w-14 h-14 rounded-2xl bg-ocean-50 text-rose-500">${SC.icon('heart', 'w-7 h-7')}</span><h3 class="font-display font-semibold text-lg mt-4">${t('no_favs')}</h3><p class="text-muted mt-1">${t('no_favs_text')}</p><a href="${SC.route('catalog')}" class="btn btn-primary mt-5">${SC.t('booking.browse')}</a></div>`;
  };
  document.addEventListener('sc:favs', () => { renderFavs(); renderOverview(by); });

  /* ---------- Profile forms ---------- */
  $('#p-nat').add(new Option(SC.t('booking.select_nationality'), ''));
  D.nationalities.forEach((n) => $('#p-nat').add(new Option(SC.natLabel(n), n)));
  // Laravel (step 25): flag + country code widget, like safer.travel; the chosen code goes to #p-dial
  const phone = SC.phoneWidget($('#p-phone'), profile.dial, (dial) => { $('#p-dial').value = dial; });
  const fill = () => {
    $('#p-first').value = profile.first; $('#p-last').value = profile.last; $('#p-email').value = profile.email;
    $('#p-dial').value = profile.dial || ''; phone?.setDial(profile.dial); $('#p-phone').value = profile.phone;
    $('#p-dob').value = profile.dob; $('#p-nat').value = profile.nationality;
  };
  // Shows/hides the .err line under a field (the phone input sits inside the widget, so its error is further up)
  const flag = (el, bad, msg) => {
    el.classList.toggle('is-invalid', bad);
    let box = el;
    while (box && !box.nextElementSibling?.classList.contains('err') && box.parentElement !== el.form) box = box.parentElement;
    const err = box?.nextElementSibling?.classList.contains('err') ? box.nextElementSibling : null;
    if (err && msg) err.textContent = msg;
    err?.classList.toggle('show', bad);
    return bad;
  };

  $('#profile-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const bad = [
      flag($('#p-first'), !$('#p-first').value.trim()),
      flag($('#p-last'), !$('#p-last').value.trim()),
      flag($('#p-email'), !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test($('#p-email').value.trim())),
      flag($('#p-phone'), !!$('#p-phone').value.trim() && !/^\d{7,12}$/.test($('#p-phone').value.replace(/\s/g, '')))
    ].some(Boolean);
    if (bad) return SC.toast(t('fix_fields'), 'error');
    const next = { first: $('#p-first').value.trim(), last: $('#p-last').value.trim(), email: $('#p-email').value.trim(), dial: $('#p-dial').value, phone: $('#p-phone').value.replace(/\s/g, ''), dob: $('#p-dob').value, nationality: $('#p-nat').value };
    const r = await SC.api('PUT', SC.route('accountProfile'), next);
    if (!r.ok) return SC.toast(SC.apiError(r), 'error');
    Object.assign(profile, r.data.profile); paintUser(); SC.toast(t('saved'));
  });

  const NOTIF = ['reminders', 'whatsapp', 'offers'].map((k) => [k, t(`notif_${k}`), t(`notif_${k}_text`)]);
  $('#notif').innerHTML = NOTIF.map(([k, t, d]) => `
    <li class="py-4 flex items-center justify-between gap-4">
      <div><p class="font-bold">${t}</p><p class="text-sm text-muted">${d}</p></div>
      <button role="switch" aria-checked="${!!profile.notif[k]}" data-notif="${k}" aria-label="${t}" class="relative w-12 h-7 rounded-full transition shrink-0 ${profile.notif[k] ? 'bg-ocean-600' : 'bg-line'}">
        <span class="absolute top-1 start-1 w-5 h-5 rounded-full bg-white shadow transition ${profile.notif[k] ? 'translate-x-5 rtl:-translate-x-5' : ''}"></span>
      </button>
    </li>`).join('');
  const paintSwitch = (s, on) => {
    s.setAttribute('aria-checked', on); s.classList.toggle('bg-ocean-600', on); s.classList.toggle('bg-line', !on);
    s.firstElementChild.classList.toggle('translate-x-5', on); s.firstElementChild.classList.toggle('rtl:-translate-x-5', on);
  };
  $('#notif').addEventListener('click', async (e) => {
    const s = e.target.closest('[data-notif]'); if (!s) return;
    const on = !(s.getAttribute('aria-checked') === 'true');
    profile.notif[s.dataset.notif] = on; paintSwitch(s, on);
    const r = await SC.api('PUT', SC.route('accountNotifications'), profile.notif);
    if (!r.ok) { profile.notif[s.dataset.notif] = !on; paintSwitch(s, !on); return SC.toast(SC.apiError(r), 'error'); }
    SC.toast(t(on ? 'notif_on' : 'notif_off'));
  });

  $('#pw-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const n = $('#pw-new').value;
    const bad = [flag($('#pw-old'), !$('#pw-old').value), flag($('#pw-new'), n.length < 8 || !/\d/.test(n)), flag($('#pw-rep'), $('#pw-rep').value !== n || !n)].some(Boolean);
    if (bad) return;
    const r = await SC.api('PUT', SC.route('accountPassword'), { current_password: $('#pw-old').value, password: n, password_confirmation: $('#pw-rep').value });
    if (!r.ok) {
      if (r.data?.errors?.current_password) return flag($('#pw-old'), true, r.data.errors.current_password[0]);
      return SC.toast(SC.apiError(r), 'error');
    }
    e.target.reset(); SC.toast(t('password_updated'));
  });

  $('#sign-out').addEventListener('click', async () => {
    await SC.api('POST', SC.route('logout'));
    location.href = SC.route('home');
  });

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
