/* =====================================================================
   The ship — ship.html (all content comes from SC_DATA.ships[0])
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;
  const ship = D.ships[0];
  const spec = (k) => ship.specs.find((s) => s.key === k);

  /* Hero */
  // Laravel: the hero photo is server-rendered from Admin → Cruise pages; only fill it if it came empty
  if (!$('#ship-hero').getAttribute('src')) $('#ship-hero').src = D.images.shipHero;
  $('#ship-tagline').textContent = ship.tagline;
  $('#ship-name').textContent = ship.name; // Laravel: from Admin → Ships, in the page language
  $('#ship-chips').innerHTML = ['length', 'decks', 'cabins', 'passengers'].map((k) => {
    const s = spec(k);
    return `<span class="rounded-full bg-white/15 backdrop-blur px-3.5 py-1.5 text-sm font-semibold">${s.value}${s.unit ? ' ' + s.unit : ''} <span class="text-ocean-100 font-medium">${s.label.toLowerCase()}</span></span>`;
  }).join('');

  /* About + specs */
  $('#ship-story').innerHTML = ship.story.map((p) => `<p>${p}</p>`).join('');
  $('#ship-specs').innerHTML = ship.specs.map((s) => `
    <div class="p-4 sm:p-5 border-e border-b border-line">
      <dt class="text-xs font-semibold text-muted">${s.label}</dt>
      <dd class="mt-1 font-display font-bold text-2xl text-ocean-800 tabular">${s.value}<span class="text-sm font-semibold text-muted ms-1">${s.unit}</span></dd>
    </div>`).join('') + `<div class="p-4 sm:p-5 border-e border-b border-line bg-ocean-50 flex items-center"><a href="${SC.route('catalog')}" class="font-bold text-ocean-600 hover:underline">${SC.t('ui.browse_sailings')}</a></div>`;

  /* Zones: alternating image / text */
  $('#ship-zones').innerHTML = ship.zones.map((z, i) => `
    <article class="grid lg:grid-cols-2 gap-8 items-center">
      <div class="rounded-[24px] overflow-hidden aspect-[16/10] ${i % 2 ? 'lg:order-2' : ''}"><img src="${z.image}" alt="${SC.t('ui.ship_zone_alt', { zone: z.title, ship: ship.name })}" loading="lazy" class="w-full h-full object-cover"></div>
      <div>
        <span class="grid place-items-center w-12 h-12 rounded-xl bg-ocean-50 text-ocean-600">${SC.icon(z.icon, 'w-6 h-6')}</span>
        <h3 class="font-display font-semibold text-2xl mt-4">${z.title}</h3>
        <p class="mt-3 text-lg leading-relaxed text-muted max-w-[52ch]">${z.text}</p>
      </div>
    </article>`).join('');

  $('#ship-facilities').innerHTML = D.facilities.map((f) => `
    <li class="bg-white p-6 flex gap-4">
      <span class="grid place-items-center w-12 h-12 rounded-xl bg-ocean-50 text-ocean-600 shrink-0">${SC.icon(f.icon, 'w-6 h-6')}</span>
      <div><p class="font-display font-bold text-xl text-ocean-800">${f.value}</p><h4 class="font-bold">${f.label}</h4><p class="text-sm text-muted mt-1 leading-relaxed">${f.text}</p></div>
    </li>`).join('');

  /* Cabins (real photos) */
  const first = SC.allSailings()[0];
  $('#ship-cabins').innerHTML = SC.cabinsSorted().map((c) => `
    <article class="card overflow-hidden flex flex-col">
      <div class="relative aspect-[4/3] img-zoom"><img src="${c.images[0]}" alt="${c.nameEn}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
        ${c.badge ? `<span class="absolute top-3 start-3 tag tag-sun">${c.badge}</span>` : ''}</div>
      <div class="p-5 flex-1 flex flex-col">
        <h3 class="font-display font-semibold text-lg">${c.nameEn}</h3>
        ${SC.lang() === 'ar' ? '' : `<p class="text-sm text-muted" lang="ar" dir="rtl">${c.nameAr}</p>`}
        <p class="mt-3 text-[0.95rem] leading-relaxed">${c.short}</p>
        <ul class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted">
          <li class="flex items-center gap-1.5">${SC.icon('users', 'w-4 h-4')} ${SC.t('ui.guests_up_to', { count: c.maxGuests })}</li>
          <li class="flex items-center gap-1.5">${SC.icon('size', 'w-4 h-4')} ${c.size}</li>
          ${c.balcony ? `<li class="flex items-center gap-1.5">${SC.icon('sun', 'w-4 h-4')} ${SC.t('ui.balcony_size', { size: c.balcony })}</li>` : ''}
        </ul>
        <div class="mt-auto pt-5 flex items-center justify-between gap-3">
          <p class="text-sm text-muted">${SC.t('ui.from')} <span class="font-bold text-ink">${SC.money(SC.prices(first.cruise, first.sailing, c.id).adult)}</span> ${SC.t('ui.per_person_short')}</p>
          <button class="btn btn-ghost btn-sm" data-cabin-info="${c.id}">${SC.t('ui.cabin.view_details')}</button>
        </div>
      </div>
    </article>`).join('');
  $('#ship-cabins').addEventListener('click', (e) => { const b = e.target.closest('[data-cabin-info]'); if (b) SC.ui.openCabin(b.dataset.cabinInfo); });

  /* Essentials */
  $('#ship-essentials').innerHTML = ship.essentials.map(([icon, title, text]) => `
    <li class="flex gap-4">
      <span class="grid place-items-center w-12 h-12 rounded-xl bg-lagoon-50 text-lagoon-600 shrink-0">${SC.icon(icon, 'w-6 h-6')}</span>
      <div><h3 class="font-bold">${title}</h3><p class="text-muted mt-1 leading-relaxed">${text}</p></div>
    </li>`).join('');

  /* FAQ accordion */
  $('#ship-faq').innerHTML = ship.faqs.map(([q, a], i) => `
    <div class="acc-item card ${i === 0 ? 'open' : ''}">
      <button class="acc-trigger w-full flex items-center justify-between gap-4 p-5 text-start font-bold" aria-expanded="${i === 0}">${q}${SC.icon('chevron-down', 'acc-chevron w-5 h-5 text-muted shrink-0')}</button>
      <div class="acc-body"><div><p class="px-5 pb-5 text-muted leading-relaxed">${a}</p></div></div>
    </div>`).join('');

  /* Section nav highlight */
  const links = $$('#ship-subnav a');
  const io = new IntersectionObserver((entries) => entries.forEach((en) => {
    if (!en.isIntersecting) return;
    links.forEach((l) => { const on = l.getAttribute('href') === '#' + en.target.id; l.classList.toggle('border-ocean-600', on); l.classList.toggle('text-ocean-700', on); l.classList.toggle('border-transparent', !on); });
  }), { rootMargin: '-40% 0px -55% 0px' });
  ['about', 'onboard', 'cabins', 'essentials', 'faq'].forEach((id) => io.observe(document.getElementById(id)));
});
