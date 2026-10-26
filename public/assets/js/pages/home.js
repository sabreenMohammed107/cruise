/* =====================================================================
   Homepage — index.html
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;
  const sailings = SC.allSailings();

  /* ---------- Hero + ship imagery ---------- */
  // Laravel: the ship photo is server-rendered from Admin → Homepage; only fill it if it came empty
  if (!$('#ship-img').getAttribute('src')) $('#ship-img').src = D.images.shipSea;

  /* ---------- Search widget ---------- */
  const portSel = $('#s-dest'), monthSel = $('#s-month'), nightSel = $('#s-nights');
  SC.allPorts().sort().forEach((p) => portSel.add(new Option(p, p)));
  [...new Set(sailings.map((s) => s.departure.slice(0, 7)))].forEach((m) => monthSel.add(new Option(m, m)));
  [...new Set(sailings.map((s) => s.nights))].sort((a, b) => a - b).forEach((n) => nightSel.add(new Option(n, n)));

  const guests = SC.fitGuests(SC.guestsPref.get()); // counts saved under older rules
  // Laravel: one cabin per booking — at most SC_DATA.maxCabinGuests (3) guests, infants included, infants with an adult
  const limits = (band, g) => SC.guestLimits(band, g);
  const counters = () => { $('#s-counters').innerHTML = ['adult', 'child', 'infant'].map((b) => SC.ui.counterRow(b, guests[b], limits(b, guests))).join(''); };
  counters();
  SC.ui.bindCounters($('#s-counters'), guests, (g) => {
    $('#s-guests-text').textContent = SC.guestText(g); SC.guestsPref.set(g);
  }, limits);

  // Laravel: option and guest wording in the current language (lang/{en,ar}/site.php); redrawn on the عربي toggle
  const monthFmt = () => new Intl.DateTimeFormat(SC.t('date_locale'), { month: 'long', year: 'numeric' });
  const paintSearch = () => {
    const fmt = monthFmt();
    portSel.options[0].text = SC.t('search.any_port');
    monthSel.options[0].text = SC.t('search.any_month');
    nightSel.options[0].text = SC.t('search.any_length');
    [...portSel.options].slice(1).forEach((o) => { o.text = SC.port(o.value); });
    [...monthSel.options].slice(1).forEach((o) => { o.text = fmt.format(SC.parseDate(o.value + '-01')); });
    [...nightSel.options].slice(1).forEach((o) => { o.text = SC.tc('nights', Number(o.value)); });
    $('#s-guests-text').textContent = SC.guestText(guests);
    counters();
  };
  paintSearch();
  document.addEventListener('sc:lang', paintSearch);

  // Live count of matching sailings
  const matches = () => sailings.filter((s) =>
    (!portSel.value || s.route.some((p) => p.startsWith(portSel.value))) &&
    (!monthSel.value || s.departure.startsWith(monthSel.value)) &&
    (!nightSel.value || s.nights === Number(nightSel.value)));
  const updateCount = () => { $('#s-count').textContent = matches().length; };
  [portSel, monthSel, nightSel].forEach((el) => el.addEventListener('change', updateCount));
  updateCount();

  $('#search-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const q = new URLSearchParams();
    if (portSel.value) q.set('port', portSel.value);
    if (monthSel.value) q.set('month', monthSel.value);
    if (nightSel.value) q.set('nights', nightSel.value);
    location.href = SC.route('catalog') + (q.toString() ? '?' + q : '');
  });

  /* ---------- Ship spec sheet ---------- */
  $('#ship-specs').innerHTML = D.ships[0].specs.map((s) => `
    <div class="p-4 sm:p-5 border-e border-b border-line">
      <dt class="text-xs font-semibold text-muted">${s.label}</dt>
      <dd class="mt-1 font-display font-bold text-2xl text-ocean-800 tabular">${s.value}<span class="text-sm font-semibold text-muted ms-1">${s.unit}</span></dd>
    </div>`).join('') +
    `<div class="p-4 sm:p-5 border-e border-b border-line bg-ocean-50 flex items-center"><a href="${SC.route('catalog')}" class="font-bold text-ocean-600 hover:underline">${SC.t('ui.browse_sailings')}</a></div>`;

  /* ---------- Featured: next departure of each cruise, most popular first ---------- */
  const byCruise = {};
  sailings.forEach((s) => { (byCruise[s.cruiseId] ||= []).push(s); });
  const featured = Object.values(byCruise).map((list) => ({ v: list[0], also: list.slice(1).map((x) => x.departure) }))
    .sort((a, b) => b.v.popularity - a.v.popularity).slice(0, 6);
  $('#featured-grid').innerHTML = featured.map(({ v, also }) => SC.ui.sailingCard(v, { alsoDates: also })).join('');

  /* ---------- Facilities ---------- */
  $('#facilities').innerHTML = D.facilities.map((f) => `
    <li class="bg-white p-6 flex gap-4">
      <span class="grid place-items-center w-12 h-12 rounded-xl bg-ocean-50 text-ocean-600 shrink-0">${SC.icon(f.icon, 'w-6 h-6')}</span>
      <div>
        <p class="font-display font-bold text-xl text-ocean-800">${f.value}</p>
        <h3 class="font-bold">${f.label}</h3>
        <p class="text-sm text-muted mt-1 leading-relaxed">${f.text}</p>
      </div>
    </li>`).join('');

  /* ---------- Cabin showcase (large photos, tabs per category) ---------- */
  const firstSailing = sailings[0];
  const cabins = SC.cabinsSorted();
  let cab = cabins[0], photo = 0;
  $('#cabin-tabs').innerHTML = cabins.map((c, i) => `<button class="chip shrink-0 ${i === 0 ? 'is-active' : ''}" role="tab" aria-selected="${i === 0}" data-cabin-tab="${c.id}">${c.nameEn}</button>`).join('');
  const showPhoto = () => {
    const img = $('#cs-main');
    img.style.opacity = 0;
    setTimeout(() => { img.dataset.fb = ''; img.src = cab.images[photo]; img.alt = SC.t('ui.cabin.photo_alt', { cabin: cab.nameEn, n: photo + 1 }); img.style.opacity = 1; }, 150);
    $('#cs-count').textContent = `${photo + 1} / ${cab.images.length}`;
    $$('#cs-thumbs button').forEach((b, i) => { b.classList.toggle('ring-2', i === photo); b.classList.toggle('opacity-60', i !== photo); });
  };
  const renderCabin = () => {
    const from = SC.prices(firstSailing.cruise, firstSailing.sailing, cab.id).perPerson;
    const badge = $('#cs-badge'); badge.textContent = cab.badge; badge.classList.toggle('hidden', !cab.badge);
    $('#cs-thumbs').innerHTML = cab.images.map((src, i) => `<button class="shrink-0 w-20 h-14 sm:w-24 sm:h-16 rounded-xl overflow-hidden ring-white transition" data-cs-thumb="${i}" aria-label="${SC.t('ui.cabin.show_photo', { n: i + 1 })}"><img src="${src}" alt="" class="w-full h-full object-cover"></button>`).join('');
    const spec = (icon, label, val) => `<div class="flex gap-3"><span class="grid place-items-center w-10 h-10 rounded-xl bg-ocean-50 text-ocean-600 shrink-0">${SC.icon(icon)}</span><div><p class="text-xs font-semibold text-muted">${label}</p><p class="font-bold text-sm">${val}</p></div></div>`;
    $('#cs-info').innerHTML = `
      ${SC.lang() === 'ar' ? '' : `<p class="text-sm text-muted" lang="ar" dir="rtl">${cab.nameAr}</p>`}
      <h3 class="font-display font-bold text-2xl sm:text-3xl mt-1">${cab.nameEn}</h3>
      <p class="mt-4 leading-relaxed text-ink/90">${cab.description}</p>
      <div class="grid grid-cols-2 gap-4 mt-6">
        ${spec('users', SC.t('ui.cabin.guests'), SC.t('ui.guests_up_to', { count: cab.maxGuests }))}
        ${spec('size', SC.t('ui.cabin.size'), cab.size)}
        ${spec('sun', SC.t('ui.cabin.balcony'), cab.balcony ? cab.balcony : SC.t('ui.cabin.no_balcony'))}
        ${spec('bed', SC.t('ui.cabin.beds'), cab.beds.split(/[,،]/)[0])}
      </div>
      <ul class="mt-6 grid gap-2 text-sm">${cab.features.slice(0, 4).map((f) => `<li class="flex items-center gap-2.5">${SC.icon('check', 'w-4 h-4 text-lagoon-600')}${f}</li>`).join('')}</ul>
      <div class="mt-auto pt-6 border-t border-line flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-xs text-muted font-semibold">${SC.t('ui.from_per_person')}</p><p class="font-display font-bold text-2xl text-ocean-800 tabular">${SC.money(from)}</p></div>
        <div class="flex gap-2">
          <button class="btn btn-outline btn-sm" data-cabin-info="${cab.id}">${SC.t('ui.cabin.all_details')}</button>
          <a href="${SC.route('catalog')}?cabins=${cab.id}" class="btn btn-accent btn-sm">${SC.t('ui.cabin.find_sailings')}</a>
        </div>
      </div>`;
    photo = 0; showPhoto();
  };
  $('#cabin-tabs').addEventListener('click', (e) => {
    const b = e.target.closest('[data-cabin-tab]'); if (!b) return;
    cab = SC.getCabin(b.dataset.cabinTab);
    $$('#cabin-tabs [data-cabin-tab]').forEach((x) => { const on = x === b; x.classList.toggle('is-active', on); x.setAttribute('aria-selected', on); });
    renderCabin();
  });
  $('#cs-prev').addEventListener('click', () => { photo = (photo - 1 + cab.images.length) % cab.images.length; showPhoto(); });
  $('#cs-next').addEventListener('click', () => { photo = (photo + 1) % cab.images.length; showPhoto(); });
  $('#cs-thumbs').addEventListener('click', (e) => { const b = e.target.closest('[data-cs-thumb]'); if (b) { photo = Number(b.dataset.csThumb); showPhoto(); } });
  $('#cs-info').addEventListener('click', (e) => { const b = e.target.closest('[data-cabin-info]'); if (b) SC.ui.openCabin(b.dataset.cabinInfo); });
  renderCabin();

  /* ---------- Destinations slider ---------- */
  const track = $('#dest-track');
  track.innerHTML = D.destinations.map((d) => {
    const n = D.cruises.filter((c) => c.route.some((p) => p.startsWith(d.name))).length;
    return `<a href="${SC.route('destinations')}#${d.slug}" class="group snap-start shrink-0 w-[78%] sm:w-[46%] lg:w-[31%] xl:w-[23.5%] relative rounded-[22px] overflow-hidden aspect-[3/4]">
      <img src="${d.image}" alt="${SC.port(d.name)}, ${d.country}" loading="lazy" class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-105">
      <div class="absolute inset-0 bg-gradient-to-t from-ocean-900/90 via-ocean-900/20 to-transparent"></div>
      <div class="absolute bottom-0 inset-x-0 p-5 text-white">
        <p class="text-sm text-ocean-100">${d.country}</p>
        <h3 class="font-display font-semibold text-2xl">${SC.port(d.name)}</h3>
        <p class="text-sm text-ocean-100 mt-1">${d.blurb}</p>
        <p class="mt-3 inline-flex text-xs font-bold bg-white/20 backdrop-blur px-2.5 py-1 rounded-full">${SC.tc('ui.cruises', n)}</p>
      </div>
    </a>`;
  }).join('');
  $$('[data-slide]').forEach((b) => b.addEventListener('click', () => {
    const dir = document.documentElement.dir === 'rtl' ? -1 : 1;
    track.scrollBy({ left: Number(b.dataset.slide) * dir * track.clientWidth * 0.8, behavior: 'smooth' });
  }));

  /* ---------- Testimonials slider ---------- */
  const reviews = $('#testimonials');
  const stars = (rating, size) => Array.from({ length: 5 }, (_, i) => SC.icon('star', `${size} ${i < Math.round(rating) ? '' : 'opacity-25'}`, true)).join('');
  reviews.innerHTML = D.testimonials.map((t) => `
    <figure class="card p-6 flex flex-col snap-start shrink-0 w-[86%] sm:w-[calc((100%-1.5rem)/2)] lg:w-[calc((100%-3rem)/3)]">
      <div class="flex gap-0.5 text-sun-500" aria-label="${SC.t('ui.stars', { rating: t.rating })}">${stars(t.rating, 'w-4 h-4')}</div>
      <blockquote class="mt-4 text-[1.02rem] leading-relaxed line-clamp-6" data-review-text>“${SC.esc(t.text)}”</blockquote>
      <button type="button" class="hidden self-start mt-2 text-sm font-bold text-ocean-600 hover:underline" data-review-more aria-expanded="false">${SC.t('ui.read_more')}</button>
      <figcaption class="mt-auto pt-6 flex items-center gap-3">
        <span class="grid place-items-center shrink-0 w-10 h-10 rounded-full bg-ocean-100 text-ocean-700 font-bold">${SC.esc(t.name[0])}</span>
        <span class="min-w-0"><span class="block font-bold">${SC.esc([t.name, t.from].filter(Boolean).join(SC.lang() === 'ar' ? '، ' : ', '))}</span>${t.cruise ? `<span class="block text-sm text-muted truncate" title="${SC.esc(t.cruise)}">${SC.esc(t.cruise)}</span>` : ''}</span>
      </figcaption>
    </figure>`).join('');
  // Summary next to the title: average stars and how many reviews
  if (D.testimonials.length) {
    const avg = D.testimonials.reduce((s, t) => s + Number(t.rating), 0) / D.testimonials.length;
    $('#testimonials-score').innerHTML = `<span class="flex gap-0.5 text-sun-500">${stars(avg, 'w-4 h-4')}</span><span class="font-bold text-ink tabular">${avg.toFixed(1)}</span><span>· ${SC.tc('ui.reviews_count', D.testimonials.length)}</span>`;
  }
  // "Read more" only on reviews longer than the 6 lines shown
  const markLongReviews = () => $$('[data-review-text].line-clamp-6', reviews).forEach((q) => q.nextElementSibling.classList.toggle('hidden', q.scrollHeight <= q.clientHeight + 1));
  reviews.addEventListener('click', (e) => {
    const b = e.target.closest('[data-review-more]'); if (!b) return;
    const open = b.previousElementSibling.classList.toggle('line-clamp-6') === false;
    b.setAttribute('aria-expanded', open); b.textContent = SC.t(open ? 'ui.read_less' : 'ui.read_more');
  });
  // Arrows move one page of cards; they and the progress bar hide when every card already fits
  const reviewArrows = $$('[data-review-slide]');
  const paintReviews = () => {
    const max = reviews.scrollWidth - reviews.clientWidth, at = Math.abs(reviews.scrollLeft); // scrollLeft is negative in RTL
    const fits = max <= 1;
    $('#testimonials-arrows').classList.toggle('hidden', fits);
    $('#testimonials-progress').classList.toggle('hidden', fits);
    reviewArrows[0].disabled = at <= 1; reviewArrows[1].disabled = at >= max - 1;
    const bar = $('#testimonials-progress > div'), shown = reviews.clientWidth / reviews.scrollWidth;
    bar.style.width = `${shown * 100}%`;
    bar.style.marginInlineStart = `${fits ? 0 : (at / max) * (1 - shown) * 100}%`;
  };
  reviewArrows.forEach((b) => b.addEventListener('click', () => {
    const dir = document.documentElement.dir === 'rtl' ? -1 : 1;
    reviews.scrollBy({ left: Number(b.dataset.reviewSlide) * dir * reviews.clientWidth, behavior: 'smooth' });
  }));
  reviews.addEventListener('scroll', paintReviews, { passive: true });
  // Re-measured whenever the row or a card changes size: the Tailwind CDN styles the new cards a moment after
  // they are added (before that they are much wider), and fonts or "Read more" change heights and widths too
  const reviewsResize = new ResizeObserver(() => { markLongReviews(); paintReviews(); });
  [reviews, ...reviews.children].forEach((el) => reviewsResize.observe(el));
  markLongReviews(); paintReviews();

  /* ---------- Newsletter ---------- */
  $('#newsletter').addEventListener('submit', (e) => {
    e.preventDefault();
    const input = $('#nl-email');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim())) {
      input.classList.add('is-invalid'); SC.toast(SC.t('ui.home.email_invalid'), 'error'); input.focus(); return;
    }
    input.classList.remove('is-invalid');
    // Laravel: store the subscriber
    SC.api('POST', SC.route('newsletter'), { email: input.value.trim() }).then((r) => {
      if (!r.ok) { input.classList.add('is-invalid'); SC.toast(SC.apiError(r), 'error'); return; }
      input.value = '';
      SC.toast(SC.t('ui.home.subscribed'));
    });
  });
});
