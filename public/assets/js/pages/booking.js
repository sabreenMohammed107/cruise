/* =====================================================================
   Booking flow — booking.html  (#guests → #review → confirmation.html)
   Doc 4: one form per traveller, DOB must match the age band, primary
          guest adds email + mobile with country code.
   Doc 5: review page with detailed price and terms checkbox.
          The site has no online payment: "Confirm booking" creates the
          booking reference and goes straight to the confirmation page.
   Laravel: all words come from lang/{en,ar}/site.php → booking (SC.t / SC.tc),
   so the page follows the language of the header's عربي button.
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;
  const t = (key, r) => SC.t(`booking.${key}`, r);
  const err = (key, r) => SC.t(`booking.errors.${key}`, r);
  const comma = SC.lang() === 'ar' ? '، ' : ', ';
  let b = SC.booking.get();

  /* ---------- Guard: nothing in the session ---------- */
  const demoBooking = () => {
    const c = D.cruises[0]; const s = c.sailings.find((x) => SC.daysUntil(x.departure) > 0) || c.sailings[0]; const v = SC.sailingView(c, s);
    // Laravel: the first cabin type on sale on that date (cabin ids come from the admin, e.g. "balcony" or "BC")
    const rooms = [{ cabinId: v.availableCabins[0], guests: { adult: 2, child: 1, infant: 0 } }];
    return { cruiseId: c.id, sailingId: s.id, cruiseName: c.name, ship: v.ship, departure: v.departure, returnDate: v.returnDate, nights: c.nights,
      departurePort: c.departurePort, arrivalPort: c.arrivalPort, currency: D.currency, fromPrice: v.fromPrice, rooms, cabinId: rooms[0].cabinId, guests: rooms[0].guests };
  };
  /* Laravel (step 15): b.rooms = [{ cabinId, guests }, …] — one entry per cabin. A session saved before (one cabin,
     travellers keyed "adult-1") becomes one room with travellers keyed "r1-adult-1". */
  if (b && !b.rooms && b.cabinId) {
    b.rooms = [{ cabinId: b.cabinId, guests: b.guests }];
    b.travellers = Object.fromEntries(Object.entries(b.travellers || {}).map(([k, v]) => [/^r\d+-/.test(k) ? k : `r1-${k}`, v]));
  }
  if (!b || !b.rooms || !b.rooms.length || !SC.findSailing(b.sailingId) || b.rooms.some((r) => !SC.getCabin(r.cabinId))) {
    $('#no-booking').classList.remove('hidden');
    $('#stepper').classList.add('invisible');
    $('#back-to-details').href = SC.route('catalog');
    $('#load-demo').addEventListener('click', () => { SC.booking.set(demoBooking()); location.hash = 'guests'; location.reload(); });
    return;
  }
  // Laravel: the session booking is cleared once the server confirms it, so it can't be confirmed twice.
  $('#flow').classList.remove('hidden');

  const { cruise, sailing } = SC.findSailing(b.sailingId);
  const rooms = b.rooms.map((r, i) => ({ ...r, i, cabin: SC.getCabin(r.cabinId), label: SC.t('ui.details.cabin_n', { n: i + 1 }) }));
  const several = rooms.length > 1;
  const cabin = rooms[0].cabin;
  const guestsTotal = rooms.reduce((t, r) => ({ adult: t.adult + r.guests.adult, child: t.child + r.guests.child, infant: t.infant + r.guests.infant }), { adult: 0, child: 0, infant: 0 });
  const detailsUrl = SC.detailsUrl({ cruiseId: cruise.id, sailingId: sailing.id }) + '#cabins';
  $('#back-to-details').href = detailsUrl;
  $$('[data-back-details]').forEach((a) => (a.href = detailsUrl));
  // Names in the page language (the session keeps what was saved in the language of the details page)
  const cruiseName = cruise.name;
  const shipName = SC.getShip(cruise.shipId).name;

  b.travellers = b.travellers || {};
  b.contact = b.contact || { email: '', dial: '+966', phone: '' };
  const save = () => SC.booking.set(b);
  D.promos = D.promos || {};
  const quote = () => SC.quoteRooms({ cruise, sailing, rooms: b.rooms, promoCode: D.promos[b.promoCode] ? b.promoCode : null });
  const checkPromo = async (code) => {
    const r = await SC.api('POST', SC.route('bookingPromo'), { code, sailing: b.sailingId });
    if (r.ok) D.promos[r.data.code] = r.data;
    return r.ok;
  };

  /* ---------- Traveller slots per cabin: Adult 1, Adult 2, Child 1, Infant 1 ... ---------- */
  // key "r2-adult-1" = cabin 2, adult 1 (the server receives "adult-1" inside cabin 2). The primary guest is
  // cabin 1's first adult, who also gives the contact details.
  const slots = [];
  rooms.forEach((r) => ['adult', 'child', 'infant'].forEach((band) => {
    for (let n = 1; n <= r.guests[band]; n++) slots.push({ room: r.i, band, n, key: `r${r.i + 1}-${band}-${n}`, primary: r.i === 0 && band === 'adult' && n === 1 });
  }));

  // Date-picker bounds per band, relative to the departure date
  const shift = (iso, { y = 0, m = 0, d = 0 }) => { const t = SC.parseDate(iso); t.setFullYear(t.getFullYear() + y, t.getMonth() + m, t.getDate() + d); return SC.toISO(t); };
  const today = SC.toISO(new Date());
  const minIso = (a, c) => (a < c ? a : c);
  // Laravel: from Admin → Age bands (SC_DATA.ageBands), e.g. adults 18+, children 2–17, infants 6 months–under 2
  const AB = D.ageBands;
  const dobBounds = {
    adult: { min: '1920-01-01', max: minIso(shift(b.departure, { y: -AB.adult.minYears }), today) },
    child: { min: shift(b.departure, { y: -(AB.child.maxYears + 1), d: 1 }), max: minIso(shift(b.departure, { y: -AB.child.minYears }), today) },
    infant: { min: shift(b.departure, { m: -AB.infant.maxMonthsExclusive, d: 1 }), max: minIso(shift(b.departure, { m: -AB.infant.minMonths }), today) }
  };

  /* ---------- Step 1: render traveller forms ---------- */
  const field = (id, label, control, errText = '', span = '') => `<div class="${span}"><label class="label" for="${id}">${label}</label>${control}<p class="err" id="${id}-err">${errText}</p></div>`;
  const val = (key, f) => SC.esc(b.travellers[key]?.[f] || '');
  $('#traveller-forms').innerHTML = slots.map((s, idx) => {
    const B = D.ageBands[s.band]; const k = s.key; const tr = b.travellers[k] || {};
    const r = rooms[s.room];
    const roomHead = several && slots[idx - 1]?.room !== s.room
      ? `<h2 class="font-display font-semibold text-xl ${idx ? 'mt-4' : ''}">${r.label} · ${SC.esc(r.cabin.nameEn)} <span class="text-sm font-normal text-muted">(${SC.guestText(r.guests)})</span></h2>` : '';
    return `${roomHead}<fieldset class="card p-5 sm:p-6" data-traveller="${k}">
      <legend class="sr-only">${B.label} ${s.n}</legend>
      <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3">
          <span class="grid place-items-center w-11 h-11 rounded-xl ${s.band === 'adult' ? 'bg-ocean-50 text-ocean-600' : s.band === 'child' ? 'bg-lagoon-50 text-lagoon-600' : 'bg-sun-50 text-sun-600'}">${SC.icon(s.band === 'adult' ? 'user' : 'smile', 'w-5 h-5')}</span>
          <div><h2 class="font-display font-semibold text-lg leading-tight">${B.label} ${s.n}</h2><p class="text-xs text-muted">${t('band_hint_on', { hint: B.hint, date: SC.fmtDate(b.departure) })}</p></div>
        </div>
        ${s.primary ? `<span class="tag tag-lagoon">${t('primary_guest')}</span>` : ''}
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        ${field(`${k}-first`, t('first_name'), `<input id="${k}-first" class="field" data-key="${k}" data-f="first" data-rule="name" autocomplete="${s.primary ? 'given-name' : 'off'}" value="${val(k, 'first')}">`)}
        ${field(`${k}-last`, t('last_name'), `<input id="${k}-last" class="field" data-key="${k}" data-f="last" data-rule="name" autocomplete="${s.primary ? 'family-name' : 'off'}" value="${val(k, 'last')}">`)}
        ${field(`${k}-dob`, t('dob'), `<input id="${k}-dob" type="date" class="field" data-key="${k}" data-f="dob" data-rule="dob" data-band="${s.band}" min="${dobBounds[s.band].min}" max="${dobBounds[s.band].max}" value="${val(k, 'dob')}">`)}
        ${field(`${k}-gender`, t('gender'), `<select id="${k}-gender" class="field" data-key="${k}" data-f="gender" data-rule="required"><option value="">${t('select')}</option>${['Male', 'Female'].map((g) => `<option value="${g}" ${tr.gender === g ? 'selected' : ''}>${SC.genderLabel(g)}</option>`).join('')}</select>`, err('gender'))}
        ${field(`${k}-nat`, t('nationality'), `<select id="${k}-nat" class="field" data-key="${k}" data-f="nationality" data-rule="required"><option value="">${t('select_nationality')}</option>${D.nationalities.map((n) => `<option value="${SC.esc(n)}" ${tr.nationality === n ? 'selected' : ''}>${SC.esc(SC.natLabel(n))}</option>`).join('')}</select>`, err('nationality'), 'sm:col-span-2')}
      </div>
      ${s.primary ? `
      <div class="mt-6 pt-5 border-t border-line">
        <h3 class="font-bold">${t('contact')}</h3>
        <p class="text-sm text-muted mt-0.5 mb-4">${t('contact_intro')}</p>
        <div class="grid gap-4 sm:grid-cols-2">
          ${field('c-email', t('email'), `<input id="c-email" type="email" class="field" dir="ltr" data-contact="email" data-rule="email" autocomplete="email" placeholder="name@example.com" value="${SC.esc(b.contact.email)}">`)}
          ${field('c-phone', t('mobile'), `<div class="flex gap-2" dir="ltr">
              <label for="c-dial" class="sr-only">${t('country_code')}</label>
              <select id="c-dial" class="field !w-28 shrink-0" data-contact="dial" autocomplete="tel-country-code">${D.dialCodes.map(([c, iso]) => `<option value="${c}" ${b.contact.dial === c ? 'selected' : ''}>${iso} ${c}</option>`).join('')}</select>
              <input id="c-phone" type="tel" inputmode="tel" class="field tabular" data-contact="phone" data-rule="phone" autocomplete="tel-national" placeholder="5X XXX XXXX" value="${SC.esc(b.contact.phone)}">
            </div>`)}
        </div>
      </div>` : ''}
    </fieldset>`;
  }).join('');

  /* ---------- Validation (same rules and words as App\Http\Requests\ConfirmBookingRequest) ---------- */
  const NAME = /^[\p{L}][\p{L}\s'’\-]{0,48}$/u;
  const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  const phoneError = (dial, raw) => {
    const n = raw.replace(/[\s\-()]/g, '').replace(/^0+/, '');
    if (!n) return err('phone_required');
    if (!/^\d+$/.test(n)) return err('phone_digits');
    if (dial === '+966' && !/^5\d{8}$/.test(n)) return err('phone_sa');
    if (dial === '+20' && !/^1\d{9}$/.test(n)) return err('phone_eg');
    if (n.length < 7 || n.length > 12) return err('phone_length');
    return null;
  };
  const errorFor = (el) => {
    const v = el.value.trim();
    switch (el.dataset.rule) {
      case 'name': return !v ? err('name_required') : NAME.test(v) ? null : err('name_letters');
      case 'dob': return SC.checkBand(el.dataset.band, v, b.departure);
      case 'required': return v ? null : (el.nextElementSibling?.textContent || err('required'));
      case 'email': return !v ? err('email_required') : EMAIL.test(v) ? null : err('email_invalid');
      case 'phone': return phoneError($('#c-dial').value, v);
      default: return null;
    }
  };
  const showError = (el, msg) => {
    el.classList.toggle('is-invalid', !!msg);
    el.setAttribute('aria-invalid', !!msg);
    const box = $(`#${el.id}-err`);
    if (box) { if (msg) box.textContent = msg; box.classList.toggle('show', !!msg); el.setAttribute('aria-describedby', box.id); }
  };
  // Guest mix from the details page, cabin by cabin: an adult in every cabin (with any infant), everyone (infants
  // included) within that cabin's limit, and no more cabins than allowed
  const guestErr = () => {
    if (rooms.length > SC.maxRooms()) return err('too_many_cabins', { max: SC.maxRooms() });
    for (const r of rooms) {
      const e = SC.guestError(r.guests, r.cabin.maxGuests);
      if (e) return several ? err('in_cabin', { n: r.i + 1, error: e }) : e;
    }
    return null;
  };
  const validateAll = (ui = true) => {
    const inputs = $$('#guests-form [data-rule]');
    let firstBad = null, bad = 0;
    inputs.forEach((el) => { const m = errorFor(el); if (m) { bad++; firstBad ||= el; } if (ui) showError(el, m); });
    const mix = guestErr();
    if (ui) {
      const box = $('#form-error');
      box.classList.toggle('hidden', !bad && !mix);
      box.innerHTML = mix ? `${SC.esc(mix)} <a href="${detailsUrl}" class="underline">${t('change')}</a>` : bad ? SC.esc(SC.tc('booking.errors.fields', bad)) : '';
      firstBad?.focus();
    }
    return !bad && !mix;
  };
  // Shown straight away (without flagging the empty traveller fields) so the guest can go back and fix it
  if (guestErr()) { $('#form-error').innerHTML = `${SC.esc(guestErr())} <a href="${detailsUrl}" class="underline">${t('change')}</a>`; $('#form-error').classList.remove('hidden'); }

  // Persist as the user types; validate on blur (and live once a field has been flagged)
  $('#guests-form').addEventListener('input', (e) => {
    const el = e.target;
    if (el.dataset.key) { (b.travellers[el.dataset.key] ||= {})[el.dataset.f] = el.value.trim(); }
    if (el.dataset.contact) b.contact[el.dataset.contact] = el.value.trim();
    save();
    if (el.classList.contains('is-invalid')) showError(el, errorFor(el));
  });
  // <select> changes don't always fire "input"
  $('#guests-form').addEventListener('change', (e) => {
    const el = e.target;
    if (el.tagName === 'SELECT' && el.dataset.key) { (b.travellers[el.dataset.key] ||= {})[el.dataset.f] = el.value; save(); }
    if (el.id === 'c-dial' && $('#c-phone').value) showError($('#c-phone'), errorFor($('#c-phone')));
  });
  // Pressing "Review booking" blurs the field first; showing its error then would push the button down before the
  // mouse is released and the click would be lost. The submit check validates every field anyway.
  let pressingSubmit = false;
  $('#guests-form button[type=submit]').addEventListener('pointerdown', () => { pressingSubmit = true; setTimeout(() => (pressingSubmit = false), 1000); });
  $('#guests-form').addEventListener('focusout', (e) => { if (!pressingSubmit && e.target.dataset.rule && e.target.value) showError(e.target, errorFor(e.target)); });
  $('#guests-form').addEventListener('submit', (e) => { e.preventDefault(); if (validateAll(true)) go('review'); });

  /* ---------- Step 2: review ---------- */
  const CAT_CODE = { interior: 'IN', balcony: 'BC', suite: 'SK' };
  const renderReview = () => {
    const q = quote();
    const row = (l, v) => `<div><dt class="text-xs font-semibold text-muted">${l}</dt><dd class="font-bold mt-0.5">${v}</dd></div>`;
    const sectionHead = (title, step) => `<div class="flex items-center justify-between mb-4"><h2 class="font-display font-semibold text-lg">${title}</h2>${step ? `<button class="btn btn-ghost btn-sm" data-goto="${step}">${SC.icon('edit', 'w-4 h-4')} ${t('edit')}</button>` : `<a class="btn btn-ghost btn-sm" href="${detailsUrl}">${SC.icon('edit', 'w-4 h-4')} ${t('change')}</a>`}</div>`;
    const bandTag = { adult: 'tag-ocean', child: 'tag-lagoon', infant: 'tag-sun' };
    const longDate = (iso) => SC.fmtDate(iso, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

    $('#review').innerHTML = `
      <section class="card p-5 sm:p-6">
        ${sectionHead(t('trip_details'))}
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-5">
          ${row(t('cruise'), SC.esc(cruiseName))}${row(t('ship'), SC.esc(shipName))}${row(t('duration'), `${SC.tc('nights', b.nights)}${comma}${SC.tc('ui.details.days', b.nights + 1)}`)}
          ${row(t('departs'), longDate(b.departure))}
          ${row(t('returns'), longDate(b.returnDate))}
          ${row(t('ports'), t('route', { from: SC.port(b.departurePort), to: SC.port(b.arrivalPort) }))}
        </dl>
      </section>

      <section class="card p-5 sm:p-6">
        ${sectionHead(several ? `${t('cabins')} (${rooms.length})` : t('cabin'))}
        <div class="grid gap-5">
          ${rooms.map((r) => { const c = r.cabin; return `
          <div class="flex flex-col sm:flex-row gap-5 ${r.i ? 'pt-5 border-t border-line' : ''}">
            <img src="${c.images[0]}" alt="${c.nameEn}" class="w-full sm:w-44 aspect-[4/3] object-cover rounded-xl">
            <dl class="flex-1 grid grid-cols-2 gap-4">
              ${several ? `<p class="col-span-2 font-display font-semibold">${r.label}</p>` : ''}
              ${row(t('cabin_type'), c.nameEn)}${row(t('category'), `${c.nameAr} (${CAT_CODE[c.id] || c.id})`)}
              ${row(t('guests_in_cabin'), t('guests_of', { count: SC.guestTotal(r.guests), max: c.maxGuests }))}${row(t('size'), c.size + (c.balcony ? ` + ${SC.t('ui.balcony_size', { size: c.balcony })}` : ''))}
              <div class="col-span-2"><button class="text-sm font-bold text-ocean-600 hover:underline" data-cabin-info="${c.id}">${t('cabin_info')}</button></div>
            </dl>
          </div>`; }).join('')}
        </div>
      </section>

      <section class="card p-5 sm:p-6">
        ${sectionHead(t('travellers'), 'guests')}
        <ul class="divide-y divide-line">
          ${slots.map((s, idx) => { const tr = b.travellers[s.key] || {}; return `
            ${several && slots[idx - 1]?.room !== s.room ? `<li class="pt-4 first:pt-0 pb-1 text-xs font-bold text-muted">${rooms[s.room].label} · ${SC.esc(rooms[s.room].cabin.nameEn)}</li>` : ''}
            <li class="py-4 first:pt-0 last:pb-0 flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-bold">${SC.esc(tr.first)} ${SC.esc(tr.last)} ${s.primary ? `<span class="text-xs font-semibold text-lagoon-600 ms-1">${t('primary_guest')}</span>` : ''}</p>
                <p class="text-sm text-muted mt-0.5">${[t('born', { date: tr.dob ? SC.fmtDate(tr.dob) : '—' }), tr.gender ? SC.genderLabel(tr.gender) : '', SC.esc(SC.natLabel(tr.nationality || ''))].filter(Boolean).join(comma)}</p>
                ${s.primary ? `<p class="text-sm text-muted mt-1 flex flex-wrap gap-x-4 gap-y-1"><span class="inline-flex items-center gap-1.5" dir="ltr">${SC.icon('mail', 'w-4 h-4')}${SC.esc(b.contact.email)}</span><span class="inline-flex items-center gap-1.5 tabular" dir="ltr">${SC.icon('phone', 'w-4 h-4')}${SC.esc(b.contact.dial)} ${SC.esc(b.contact.phone)}</span></p>` : ''}
              </div>
              <span class="tag ${bandTag[s.band]}">${D.ageBands[s.band].label}</span>
            </li>`; }).join('')}
        </ul>
      </section>

      <section class="card p-5 sm:p-6">
        <h2 class="font-display font-semibold text-lg mb-4">${t('price_breakdown')}</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="text-muted text-xs"><th class="text-start font-semibold pb-2">${t('item')}</th><th class="text-end font-semibold pb-2">${t('price')}</th><th class="text-end font-semibold pb-2">${t('qty')}</th><th class="text-end font-semibold pb-2">${t('total')}</th></tr></thead>
            <tbody class="tabular">
              ${q.rooms.map((p) => `
                ${several ? `<tr class="border-t border-line bg-page"><td class="py-2 font-bold" colspan="4">${rooms[p.position - 1].label} · ${SC.esc(rooms[p.position - 1].cabin.nameEn)} <span class="font-normal text-muted">(${SC.guestText(p.guests)})</span></td></tr>` : ''}
                ${p.lines.map((l) => `<tr class="border-t border-line"><td class="py-2.5">${l.label}</td><td class="text-end">${SC.money(l.unit)}</td><td class="text-end">× ${l.qty}</td><td class="text-end font-bold">${SC.money(l.total)}</td></tr>`).join('')}
                <tr class="border-t border-line"><td class="py-2.5">${t('cabin_line', { cabin: rooms[p.position - 1].cabin.nameEn })}</td><td class="text-end text-muted" colspan="2">${t('included')}</td><td class="text-end font-bold">${SC.money(0)}</td></tr>`).join('')}
              ${q.discount ? `<tr class="border-t border-line text-[#12805C]"><td class="py-2.5">${t('discount', { code: q.promo.code })}</td><td></td><td></td><td class="text-end font-bold">− ${SC.money(q.discount)}</td></tr>` : ''}
            </tbody>
          </table>
        </div>

        <form id="promo-form" class="mt-5 flex gap-2 max-w-md" novalidate>
          <label for="promo" class="sr-only">${t('promo_label')}</label>
          <input id="promo" class="field uppercase" placeholder="${t('promo_placeholder')}" value="${SC.esc(b.promoCode || '')}">
          ${b.promoCode ? `<button type="button" class="btn btn-outline shrink-0" id="promo-remove">${t('remove')}</button>` : `<button class="btn btn-outline shrink-0">${t('apply')}</button>`}
        </form>
        <p class="err" id="promo-err">${t('promo_error')}</p>

        <div class="mt-6 rounded-2xl bg-ocean-50 p-5 flex flex-wrap items-end justify-between gap-3">
          <div><p class="font-bold">${t('net_total')} <span class="tag tag-lagoon ms-1 align-middle">${t('tax_included')}</span></p><p class="text-xs text-muted mt-1">${t('total_note', { guests: several ? `${SC.tc('ui.details.cabins_count', rooms.length)}${comma}${SC.guestText(guestsTotal)}` : SC.guestText(guestsTotal) })}</p></div>
          <p class="font-display font-bold text-3xl sm:text-4xl text-ocean-800 tabular">${SC.money(q.total)}</p>
        </div>
      </section>`;

    $('#promo-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const code = $('#promo').value.trim().toUpperCase();
      // Laravel: codes are checked on the server (they are not published in SC_DATA)
      const ok = code && await checkPromo(code);
      if (!ok) { $('#promo').classList.add('is-invalid'); $('#promo-err').classList.add('show'); return; }
      b.promoCode = code; save(); renderReview(); renderSummary(); SC.toast(t('promo_applied', { code, label: D.promos[code].label }));
    });
    $('#promo-remove')?.addEventListener('click', () => { delete b.promoCode; save(); renderReview(); renderSummary(); SC.toast(t('promo_removed')); });
    $$('[data-cabin-info]', $('#review')).forEach((btn) => btn.addEventListener('click', () => SC.ui.openCabin(btn.dataset.cabinInfo)));
  };

  // Terms open in a modal, so nothing typed is lost
  document.addEventListener('click', (e) => { const el = e.target.closest('[data-terms]'); if (el) { e.preventDefault(); SC.ui.openTerms(el.dataset.terms); } });
  $('#agree').checked = !!b.agreed;
  $('#agree').addEventListener('change', (e) => { b.agreed = e.target.checked; save(); $('#agree-err').classList.toggle('show', !e.target.checked && $('#agree-err').classList.contains('show')); });

  $('#confirm-btn').addEventListener('click', async (e) => {
    if (!$('#agree').checked) { $('#agree-err').classList.add('show'); $('#agree').focus(); return; }
    const btn = e.currentTarget; btn.disabled = true; $('span', btn).textContent = t('confirming');
    // Laravel: the server re-checks prices, ages and availability, creates the booking and returns its page
    // Step 15: one entry per cabin, each with its guests and its travellers keyed "adult-1", "child-1", …
    const cabins = rooms.map((r) => ({
      cabin: r.cabinId, guests: r.guests,
      travellers: Object.fromEntries(slots.filter((s) => s.room === r.i).map((s) => [`${s.band}-${s.n}`, b.travellers[s.key] || {}]))
    }));
    const r = await SC.api('POST', SC.route('bookingConfirm'), {
      sailing: b.sailingId, cabins, contact: b.contact, promoCode: b.promoCode || null, agreed: true
    });
    if (!r.ok) {
      btn.disabled = false; $('span', btn).textContent = t('confirm');
      SC.toast(SC.apiError(r, t('confirm_failed')), 'error');
      return;
    }
    SC.booking.clear();
    location.href = r.data.redirect;
  });

  /* ---------- Summary (sidebar + mobile collapsible) ---------- */
  const renderSummary = () => {
    const q = quote();
    const html = `
      <div class="p-5 flex gap-4">
        <img src="${cabin.images[0]}" alt="" class="w-20 h-20 rounded-xl object-cover">
        <div class="min-w-0">
          <p class="font-display font-semibold leading-snug">${SC.esc(cruiseName)}</p>
          <p class="text-sm text-muted">${SC.fmtDate(b.departure, { day: 'numeric', month: 'short' })} – ${SC.fmtDate(b.returnDate)}</p>
          <p class="text-sm text-muted">${several ? SC.tc('ui.details.cabins_count', rooms.length) : cabin.nameEn}</p>
        </div>
      </div>
      <div class="px-5 pb-4 text-sm flex justify-between gap-3"><span class="text-muted">${t('guests')}</span><span class="font-bold text-end">${SC.guestText(guestsTotal)}</span></div>
      <div class="perf-x"></div>
      <dl class="p-5 grid gap-2 text-sm tabular">
        ${several
          ? q.rooms.map((p) => `<div class="flex justify-between gap-3"><dt class="text-muted">${rooms[p.position - 1].label} · ${SC.esc(rooms[p.position - 1].cabin.nameEn)}<span class="block text-xs">${p.lines.map((l) => l.label).join(' + ')}</span></dt><dd>${SC.money(p.fare)}</dd></div>`).join('')
          : q.lines.map((l) => `<div class="flex justify-between gap-3"><dt class="text-muted">${l.label}${l.qty > 1 ? ` × ${l.qty}` : ''}</dt><dd>${SC.money(l.total)}</dd></div>`).join('')}
        ${q.discount ? `<div class="flex justify-between gap-3 text-[#12805C]"><dt>${t('discount', { code: q.promo.code })}</dt><dd>− ${SC.money(q.discount)}</dd></div>` : ''}
        <div class="flex items-end justify-between gap-3 pt-3 mt-1 border-t border-line"><dt><span class="block font-bold text-base">${t('net_total')}</span><span class="text-xs font-semibold text-lagoon-600">${t('tax_included')}</span></dt><dd class="font-display font-bold text-2xl text-ocean-800">${SC.money(q.total)}</dd></div>
      </dl>`;
    $('#summary').innerHTML = html;
    $('#summary-mobile').innerHTML = html;
    $('#m-total').textContent = SC.money(q.total);
  };

  /* ---------- Step routing via URL hash (browser back works) ---------- */
  const ORDER = ['guests', 'review'];
  function go(step) { if (location.hash !== '#' + step) location.hash = step; else show(step); }
  function show(step) {
    if (!ORDER.includes(step)) step = 'guests';
    if (step !== 'guests' && !validateAll(false)) step = 'guests';
    if (location.hash !== '#' + step) history.replaceState(null, '', '#' + step);

    $$('[data-view]').forEach((v) => (v.hidden = v.dataset.view !== step));
    const idx = ORDER.indexOf(step);
    $$('#stepper .step').forEach((s) => {
      const i = ORDER.indexOf(s.dataset.step);
      s.classList.toggle('is-current', i === idx); s.classList.toggle('is-done', i < idx);
      s.querySelector('.step-dot').innerHTML = i < idx ? SC.icon('check', 'w-4 h-4') : i + 1;
      if (i === idx) s.setAttribute('aria-current', 'step'); else s.removeAttribute('aria-current');
    });
    if (step === 'review') renderReview();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  window.addEventListener('hashchange', () => show(location.hash.slice(1)));
  document.addEventListener('click', (e) => { const g = e.target.closest('[data-goto]'); if (g) { e.preventDefault(); go(g.dataset.goto); } });

  renderSummary();
  show(location.hash.slice(1) || 'guests');
  // A code applied in an earlier visit is re-checked, since codes are not published in SC_DATA
  if (b.promoCode && !D.promos[b.promoCode]) checkPromo(b.promoCode).then((ok) => {
    if (!ok) { delete b.promoCode; save(); }
    renderSummary(); if (location.hash === '#review') renderReview();
  });
});
