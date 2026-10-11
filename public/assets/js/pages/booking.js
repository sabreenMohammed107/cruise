/* =====================================================================
   Booking flow — booking.html  (#guests → #review → confirmation.html)
   Doc 4: one form per traveller, DOB must match the age band, primary
          guest adds email + mobile with country code.
   Doc 5: review page with detailed price and terms checkbox.
          The site has no online payment: "Confirm booking" creates the
          booking reference and goes straight to the confirmation page.
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;
  let b = SC.booking.get();

  /* ---------- Guard: nothing in the session ---------- */
  const demoBooking = () => {
    const c = D.cruises[0]; const s = c.sailings.find((x) => SC.daysUntil(x.departure) > 0) || c.sailings[0]; const v = SC.sailingView(c, s);
    return { cruiseId: c.id, sailingId: s.id, cruiseName: c.name, ship: v.ship, departure: v.departure, returnDate: v.returnDate, nights: c.nights,
      departurePort: c.departurePort, arrivalPort: c.arrivalPort, currency: D.currency, fromPrice: v.fromPrice, cabinId: 'balcony', guests: { adult: 2, child: 1, infant: 0 } };
  };
  if (!b || !b.cabinId || !SC.findSailing(b.sailingId)) {
    $('#no-booking').classList.remove('hidden');
    $('#stepper').classList.add('invisible');
    $('#back-to-details').href = SC.route('catalog');
    $('#load-demo').addEventListener('click', () => { SC.booking.set(demoBooking()); location.hash = 'guests'; location.reload(); });
    return;
  }
  // Laravel: the session booking is cleared once the server confirms it, so it can't be confirmed twice.
  $('#flow').classList.remove('hidden');

  const { cruise, sailing } = SC.findSailing(b.sailingId);
  const cabin = SC.getCabin(b.cabinId);
  const detailsUrl = SC.detailsUrl({ cruiseId: cruise.id, sailingId: sailing.id }) + '#cabins';
  $('#back-to-details').href = detailsUrl;
  $$('[data-back-details]').forEach((a) => (a.href = detailsUrl));

  b.travellers = b.travellers || {};
  b.contact = b.contact || { email: '', dial: '+966', phone: '' };
  const save = () => SC.booking.set(b);
  D.promos = D.promos || {};
  const quote = () => SC.quote({ cruise, sailing, cabinId: cabin.id, guests: b.guests, promoCode: D.promos[b.promoCode] ? b.promoCode : null });
  const checkPromo = async (code) => {
    const r = await SC.api('POST', SC.route('bookingPromo'), { code, sailing: b.sailingId });
    if (r.ok) D.promos[r.data.code] = r.data;
    return r.ok;
  };

  /* ---------- Traveller slots: Adult 1, Adult 2, Child 1, Infant 1 ... ---------- */
  const slots = [];
  ['adult', 'child', 'infant'].forEach((band) => { for (let i = 1; i <= b.guests[band]; i++) slots.push({ band, n: i, key: `${band}-${i}`, primary: band === 'adult' && i === 1 }); });

  // Date-picker bounds per band, relative to the departure date
  const shift = (iso, { y = 0, m = 0, d = 0 }) => { const t = SC.parseDate(iso); t.setFullYear(t.getFullYear() + y, t.getMonth() + m, t.getDate() + d); return SC.toISO(t); };
  const today = SC.toISO(new Date());
  const minIso = (a, c) => (a < c ? a : c);
  const dobBounds = {
    adult: { min: '1920-01-01', max: minIso(shift(b.departure, { y: -12 }), today) },
    child: { min: shift(b.departure, { y: -12, d: 1 }), max: minIso(shift(b.departure, { y: -2 }), today) },
    infant: { min: shift(b.departure, { y: -2, d: 1 }), max: minIso(shift(b.departure, { m: -6 }), today) }
  };

  /* ---------- Step 1: render traveller forms ---------- */
  const field = (id, label, control, err = '', span = '') => `<div class="${span}"><label class="label" for="${id}">${label}</label>${control}<p class="err" id="${id}-err">${err}</p></div>`;
  const val = (key, f) => SC.esc(b.travellers[key]?.[f] || '');
  $('#traveller-forms').innerHTML = slots.map((s) => {
    const B = D.ageBands[s.band]; const k = s.key; const t = b.travellers[k] || {};
    return `<fieldset class="card p-5 sm:p-6" data-traveller="${k}">
      <legend class="sr-only">${B.label} ${s.n}</legend>
      <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3">
          <span class="grid place-items-center w-11 h-11 rounded-xl ${s.band === 'adult' ? 'bg-ocean-50 text-ocean-600' : s.band === 'child' ? 'bg-lagoon-50 text-lagoon-600' : 'bg-sun-50 text-sun-600'}">${SC.icon(s.band === 'adult' ? 'user' : 'smile', 'w-5 h-5')}</span>
          <div><h2 class="font-display font-semibold text-lg leading-tight">${B.label} ${s.n}</h2><p class="text-xs text-muted">${B.hint} on ${SC.fmtDate(b.departure)}</p></div>
        </div>
        ${s.primary ? '<span class="tag tag-lagoon">Primary guest</span>' : ''}
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        ${field(`${k}-first`, 'First name', `<input id="${k}-first" class="field" data-key="${k}" data-f="first" data-rule="name" autocomplete="${s.primary ? 'given-name' : 'off'}" value="${val(k, 'first')}">`)}
        ${field(`${k}-last`, 'Last name', `<input id="${k}-last" class="field" data-key="${k}" data-f="last" data-rule="name" autocomplete="${s.primary ? 'family-name' : 'off'}" value="${val(k, 'last')}">`)}
        ${field(`${k}-dob`, 'Date of birth', `<input id="${k}-dob" type="date" class="field" data-key="${k}" data-f="dob" data-rule="dob" data-band="${s.band}" min="${dobBounds[s.band].min}" max="${dobBounds[s.band].max}" value="${val(k, 'dob')}">`)}
        ${field(`${k}-gender`, 'Gender', `<select id="${k}-gender" class="field" data-key="${k}" data-f="gender" data-rule="required"><option value="">Select</option>${['Male', 'Female'].map((g) => `<option ${t.gender === g ? 'selected' : ''}>${g}</option>`).join('')}</select>`, 'Select a gender.')}
        ${field(`${k}-nat`, 'Nationality', `<select id="${k}-nat" class="field" data-key="${k}" data-f="nationality" data-rule="required"><option value="">Select nationality</option>${D.nationalities.map((n) => `<option ${t.nationality === n ? 'selected' : ''}>${n}</option>`).join('')}</select>`, 'Select a nationality.', 'sm:col-span-2')}
      </div>
      ${s.primary ? `
      <div class="mt-6 pt-5 border-t border-line">
        <h3 class="font-bold">Contact details</h3>
        <p class="text-sm text-muted mt-0.5 mb-4">We'll send the booking confirmation and any updates here.</p>
        <div class="grid gap-4 sm:grid-cols-2">
          ${field('c-email', 'Email', `<input id="c-email" type="email" class="field" data-contact="email" data-rule="email" autocomplete="email" placeholder="name@example.com" value="${SC.esc(b.contact.email)}">`)}
          ${field('c-phone', 'Mobile number', `<div class="flex gap-2">
              <label for="c-dial" class="sr-only">Country code</label>
              <select id="c-dial" class="field !w-28 shrink-0" data-contact="dial" autocomplete="tel-country-code">${D.dialCodes.map(([c, iso]) => `<option value="${c}" ${b.contact.dial === c ? 'selected' : ''}>${iso} ${c}</option>`).join('')}</select>
              <input id="c-phone" type="tel" inputmode="tel" class="field tabular" data-contact="phone" data-rule="phone" autocomplete="tel-national" placeholder="5X XXX XXXX" value="${SC.esc(b.contact.phone)}">
            </div>`)}
        </div>
      </div>` : ''}
    </fieldset>`;
  }).join('');

  /* ---------- Validation ---------- */
  const NAME = /^[\p{L}][\p{L}\s'’\-]{0,48}$/u;
  const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  const phoneError = (dial, raw) => {
    const n = raw.replace(/[\s\-()]/g, '').replace(/^0+/, '');
    if (!n) return 'Enter a mobile number.';
    if (!/^\d+$/.test(n)) return 'Use digits only.';
    if (dial === '+966' && !/^5\d{8}$/.test(n)) return 'Saudi mobile numbers have 9 digits and start with 5.';
    if (dial === '+20' && !/^1\d{9}$/.test(n)) return 'Egyptian mobile numbers have 10 digits and start with 1.';
    if (n.length < 7 || n.length > 12) return 'Enter a mobile number with 7 to 12 digits.';
    return null;
  };
  const errorFor = (el) => {
    const v = el.value.trim();
    switch (el.dataset.rule) {
      case 'name': return !v ? 'Enter a name.' : NAME.test(v) ? null : 'Use letters only, in Arabic or English.';
      case 'dob': return SC.checkBand(el.dataset.band, v, b.departure);
      case 'required': return v ? null : (el.nextElementSibling?.textContent || 'Required.');
      case 'email': return !v ? 'Enter an email address.' : EMAIL.test(v) ? null : 'Enter an email like name@example.com.';
      case 'phone': return phoneError($('#c-dial').value, v);
      default: return null;
    }
  };
  const showError = (el, msg) => {
    el.classList.toggle('is-invalid', !!msg);
    el.setAttribute('aria-invalid', !!msg);
    const err = $(`#${el.id}-err`);
    if (err) { if (msg) err.textContent = msg; err.classList.toggle('show', !!msg); el.setAttribute('aria-describedby', err.id); }
  };
  const validateAll = (ui = true) => {
    const inputs = $$('#guests-form [data-rule]');
    let firstBad = null, bad = 0;
    inputs.forEach((el) => { const m = errorFor(el); if (m) { bad++; firstBad ||= el; } if (ui) showError(el, m); });
    if (ui) {
      const box = $('#form-error');
      box.classList.toggle('hidden', !bad);
      box.textContent = bad ? `${SC.plural(bad, 'field')} ${bad === 1 ? 'needs' : 'need'} attention. Fix the highlighted ${bad === 1 ? 'field' : 'fields'} to continue.` : '';
      firstBad?.focus();
    }
    return !bad;
  };

  // Persist as the user types; validate on blur (and live once a field has been flagged)
  $('#guests-form').addEventListener('input', (e) => {
    const el = e.target;
    if (el.dataset.key) { (b.travellers[el.dataset.key] ||= {})[el.dataset.f] = el.value.trim(); }
    if (el.dataset.contact) b.contact[el.dataset.contact] = el.value.trim();
    save();
    if (el.classList.contains('is-invalid')) showError(el, errorFor(el));
  });
  $('#guests-form').addEventListener('change', (e) => { if (e.target.id === 'c-dial' && $('#c-phone').value) showError($('#c-phone'), errorFor($('#c-phone'))); });
  $('#guests-form').addEventListener('focusout', (e) => { if (e.target.dataset.rule && e.target.value) showError(e.target, errorFor(e.target)); });
  $('#guests-form').addEventListener('submit', (e) => { e.preventDefault(); if (validateAll(true)) go('review'); });

  /* ---------- Step 2: review ---------- */
  const CAT_CODE = { interior: 'IN', balcony: 'BC', suite: 'SK' };
  const renderReview = () => {
    const q = quote();
    const row = (l, v) => `<div><dt class="text-xs font-semibold text-muted">${l}</dt><dd class="font-bold mt-0.5">${v}</dd></div>`;
    const sectionHead = (title, step) => `<div class="flex items-center justify-between mb-4"><h2 class="font-display font-semibold text-lg">${title}</h2>${step ? `<button class="btn btn-ghost btn-sm" data-goto="${step}">${SC.icon('edit', 'w-4 h-4')} Edit</button>` : `<a class="btn btn-ghost btn-sm" href="${detailsUrl}">${SC.icon('edit', 'w-4 h-4')} Change</a>`}</div>`;
    const bandTag = { adult: 'tag-ocean', child: 'tag-lagoon', infant: 'tag-sun' };

    $('#review').innerHTML = `
      <section class="card p-5 sm:p-6">
        ${sectionHead('Trip details')}
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-5">
          ${row('Cruise', b.cruiseName)}${row('Ship', b.ship)}${row('Duration', `${b.nights} nights, ${b.nights + 1} days`)}
          ${row('Departs', SC.fmtDate(b.departure, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }))}
          ${row('Returns', SC.fmtDate(b.returnDate, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }))}
          ${row('Ports', `${SC.port(b.departurePort)} to ${SC.port(b.arrivalPort)}`)}
        </dl>
      </section>

      <section class="card p-5 sm:p-6">
        ${sectionHead('Cabin')}
        <div class="flex flex-col sm:flex-row gap-5">
          <img src="${cabin.images[0]}" alt="${cabin.nameEn}" class="w-full sm:w-44 aspect-[4/3] object-cover rounded-xl">
          <dl class="flex-1 grid grid-cols-2 gap-4">
            ${row('Cabin type', cabin.nameEn)}${row('Category', `${cabin.nameAr} (${CAT_CODE[cabin.id]})`)}
            ${row('Guests in cabin', `${q.count} of ${cabin.maxGuests}`)}${row('Size', cabin.size + (cabin.balcony ? ` + ${cabin.balcony} balcony` : ''))}
            <div class="col-span-2"><button class="text-sm font-bold text-ocean-600 hover:underline" data-cabin-info>View cabin details and photos</button></div>
          </dl>
        </div>
      </section>

      <section class="card p-5 sm:p-6">
        ${sectionHead('Travellers', 'guests')}
        <ul class="divide-y divide-line">
          ${slots.map((s) => { const t = b.travellers[s.key] || {}; return `
            <li class="py-4 first:pt-0 last:pb-0 flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-bold">${SC.esc(t.first)} ${SC.esc(t.last)} ${s.primary ? '<span class="text-xs font-semibold text-lagoon-600 ms-1">Primary guest</span>' : ''}</p>
                <p class="text-sm text-muted mt-0.5">Born ${t.dob ? SC.fmtDate(t.dob) : '—'}, ${SC.esc(t.gender || '')}, ${SC.esc(t.nationality || '')}</p>
                ${s.primary ? `<p class="text-sm text-muted mt-1 flex flex-wrap gap-x-4 gap-y-1"><span class="inline-flex items-center gap-1.5">${SC.icon('mail', 'w-4 h-4')}${SC.esc(b.contact.email)}</span><span class="inline-flex items-center gap-1.5 tabular" dir="ltr">${SC.icon('phone', 'w-4 h-4')}${SC.esc(b.contact.dial)} ${SC.esc(b.contact.phone)}</span></p>` : ''}
              </div>
              <span class="tag ${bandTag[s.band]}">${D.ageBands[s.band].label}</span>
            </li>`; }).join('')}
        </ul>
      </section>

      <section class="card p-5 sm:p-6">
        <h2 class="font-display font-semibold text-lg mb-4">Price breakdown</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="text-muted text-xs"><th class="text-start font-semibold pb-2">Item</th><th class="text-end font-semibold pb-2">Price</th><th class="text-end font-semibold pb-2">Qty</th><th class="text-end font-semibold pb-2">Total</th></tr></thead>
            <tbody class="tabular">
              ${q.lines.map((l) => `<tr class="border-t border-line"><td class="py-2.5">${l.label} cruise fare</td><td class="text-end">${SC.money(l.unit)}</td><td class="text-end">× ${l.qty}</td><td class="text-end font-bold">${SC.money(l.total)}</td></tr>`).join('')}
              <tr class="border-t border-line"><td class="py-2.5">Cabin (${cabin.nameEn})</td><td class="text-end text-muted" colspan="2">Included in fares</td><td class="text-end font-bold">${SC.money(0)}</td></tr>
              <tr class="border-t border-line"><td class="py-2.5">Taxes, port charges & fees</td><td></td><td></td><td class="text-end font-bold">${SC.money(q.fees)}</td></tr>
              ${q.discount ? `<tr class="border-t border-line text-[#12805C]"><td class="py-2.5">Discount (${q.promo.code})</td><td></td><td></td><td class="text-end font-bold">− ${SC.money(q.discount)}</td></tr>` : ''}
            </tbody>
          </table>
        </div>

        <form id="promo-form" class="mt-5 flex gap-2 max-w-md" novalidate>
          <label for="promo" class="sr-only">Discount code</label>
          <input id="promo" class="field uppercase" placeholder="Discount code (try SAFER10)" value="${SC.esc(b.promoCode || '')}">
          ${b.promoCode ? '<button type="button" class="btn btn-outline shrink-0" id="promo-remove">Remove</button>' : '<button class="btn btn-outline shrink-0">Apply</button>'}
        </form>
        <p class="err" id="promo-err">That code isn't valid for this sailing. Check the spelling and try again.</p>

        <div class="mt-6 rounded-2xl bg-ocean-50 p-5 flex flex-wrap items-end justify-between gap-3">
          <div><p class="font-bold">Total</p><p class="text-xs text-muted">All prices in US dollars, for ${SC.guestText(b.guests)}. Payment is arranged with our team after you confirm.</p></div>
          <p class="font-display font-bold text-3xl sm:text-4xl text-ocean-800 tabular">${SC.money(q.total)}</p>
        </div>
      </section>`;

    $('#promo-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const code = $('#promo').value.trim().toUpperCase();
      // Laravel: codes are checked on the server (they are not published in SC_DATA)
      const ok = code && await checkPromo(code);
      if (!ok) { $('#promo').classList.add('is-invalid'); $('#promo-err').classList.add('show'); return; }
      b.promoCode = code; save(); renderReview(); renderSummary(); SC.toast(`${code} applied: ${D.promos[code].label}`);
    });
    $('#promo-remove')?.addEventListener('click', () => { delete b.promoCode; save(); renderReview(); renderSummary(); SC.toast('Discount code removed'); });
    $('[data-cabin-info]', $('#review')).addEventListener('click', () => SC.ui.openCabin(cabin.id));
  };

  // Terms open in a modal, so nothing typed is lost
  document.addEventListener('click', (e) => { const t = e.target.closest('[data-terms]'); if (t) { e.preventDefault(); SC.ui.openTerms(t.dataset.terms); } });
  $('#agree').checked = !!b.agreed;
  $('#agree').addEventListener('change', (e) => { b.agreed = e.target.checked; save(); $('#agree-err').classList.toggle('show', !e.target.checked && $('#agree-err').classList.contains('show')); });

  $('#confirm-btn').addEventListener('click', async (e) => {
    if (!$('#agree').checked) { $('#agree-err').classList.add('show'); $('#agree').focus(); return; }
    const btn = e.currentTarget; btn.disabled = true; $('span', btn).textContent = 'Confirming…';
    // Laravel: the server re-checks prices, ages and availability, creates the booking and returns its page
    const r = await SC.api('POST', SC.route('bookingConfirm'), {
      sailing: b.sailingId, cabin: b.cabinId, guests: b.guests, travellers: b.travellers,
      contact: b.contact, promoCode: b.promoCode || null, agreed: true
    });
    if (!r.ok) {
      btn.disabled = false; $('span', btn).textContent = 'Confirm booking';
      SC.toast(SC.apiError(r, 'We could not confirm your booking. Please try again.'), 'error');
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
          <p class="font-display font-semibold leading-snug">${b.cruiseName}</p>
          <p class="text-sm text-muted">${SC.fmtDate(b.departure, { day: 'numeric', month: 'short' })} – ${SC.fmtDate(b.returnDate)}</p>
          <p class="text-sm text-muted">${cabin.nameEn}</p>
        </div>
      </div>
      <div class="px-5 pb-4 text-sm flex justify-between"><span class="text-muted">Guests</span><span class="font-bold">${SC.guestText(b.guests)}</span></div>
      <div class="perf-x"></div>
      <dl class="p-5 grid gap-2 text-sm tabular">
        ${q.lines.map((l) => `<div class="flex justify-between"><dt class="text-muted">${l.label} ${SC.money(l.unit)} × ${l.qty}</dt><dd>${SC.money(l.total)}</dd></div>`).join('')}
        <div class="flex justify-between"><dt class="text-muted">Taxes & port fees</dt><dd>${SC.money(q.fees)}</dd></div>
        ${q.discount ? `<div class="flex justify-between text-[#12805C]"><dt>Discount (${q.promo.code})</dt><dd>− ${SC.money(q.discount)}</dd></div>` : ''}
        <div class="flex items-end justify-between pt-3 mt-1 border-t border-line"><dt class="font-bold text-base">Total</dt><dd class="font-display font-bold text-2xl text-ocean-800">${SC.money(q.total)}</dd></div>
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
