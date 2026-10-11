/* =====================================================================
   Destinations — destinations.html  (#slug jumps to a port)
   ===================================================================== */
document.addEventListener('sc:ready', () => {
  const D = window.SC_DATA, { $, $$ } = SC;
  const sailings = SC.allSailings();
  const countries = ['All', ...new Set(D.destinations.map((d) => d.country))];
  let country = 'All';

  // Cruises whose route includes the port, each with its next departure
  const cruisesFor = (d) => D.cruises
    .filter((c) => c.route.some((p) => p.startsWith(d.name)))
    .map((c) => ({ c, next: sailings.find((s) => s.cruiseId === c.id) }))
    .filter((x) => x.next);

  const chipCls = (on) => `chip !border-white/30 ${on ? '!bg-white !text-ocean-800' : '!bg-white/10 !text-white'}`;
  $('#country-filter').innerHTML = countries.map((c) => `<button class="${chipCls(c === 'All')}" role="tab" aria-selected="${c === 'All'}" data-country="${c}">${c === 'All' ? SC.t('ui.destinations.all_ports') : c}</button>`).join('');

  const render = () => {
    const list = D.destinations.filter((d) => country === 'All' || d.country === country);
    $('#port-nav').innerHTML = list.map((d) => `<a href="#${d.slug}" class="shrink-0 px-4 py-3.5 font-semibold text-sm hover:text-ocean-600">${SC.port(d.name)}</a>`).join('');
    $('#dest-list').innerHTML = list.map((d, i) => {
      const cruises = cruisesFor(d);
      return `<article id="${d.slug}" class="scroll-mt-36 grid lg:grid-cols-2 gap-8 lg:gap-12 items-start">
        <div class="relative rounded-[26px] overflow-hidden aspect-[4/3] ${i % 2 ? 'lg:order-2' : ''}">
          <img src="${d.image}" alt="${SC.port(d.name)}, ${d.country}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
          <span class="absolute top-4 start-4 tag tag-white">${SC.icon('pin', 'w-3.5 h-3.5')} ${d.country}</span>
        </div>
        <div>
          ${SC.lang() === 'ar' ? '' : `<p class="text-muted" lang="ar" dir="rtl">${d.nameAr}</p>`}
          <h2 class="font-display font-bold text-3xl sm:text-4xl">${SC.port(d.name)}</h2>
          <p class="mt-4 text-lg leading-relaxed text-ink/90 max-w-[58ch]">${d.description}</p>

          <h3 class="font-bold mt-6 mb-2">${SC.t('ui.destinations.things_to_do')}</h3>
          <ul class="grid gap-2">${d.highlights.map((h) => `<li class="flex gap-2.5">${SC.icon('check', 'w-5 h-5 text-lagoon-600 shrink-0')}${h}</li>`).join('')}</ul>

          <div class="mt-6 rounded-2xl bg-ocean-50 p-4 flex gap-3 text-sm">${SC.icon('info', 'w-5 h-5 text-ocean-600 shrink-0')}<p>${d.visa}</p></div>

          <h3 class="font-bold mt-6 mb-3">${SC.t('ui.destinations.cruises_here')}</h3>
          ${cruises.length ? `<ul class="grid gap-2">${cruises.map(({ c, next }) => `
            <li><a href="${SC.detailsUrl(next)}" class="flex items-center justify-between gap-3 rounded-xl border border-line bg-white px-4 py-3 hover:border-ocean-400 transition">
              <span><span class="block font-bold">${c.name}</span><span class="text-sm text-muted">${SC.t('ui.destinations.next_departure', { nights: SC.tc('nights', c.nights), date: SC.fmtDate(next.departure) })}</span></span>
              <span class="text-end shrink-0"><span class="block text-xs text-muted">${SC.t('ui.from')}</span><span class="font-bold text-ocean-800 tabular">${SC.money(next.fromPrice)}</span></span>
            </a></li>`).join('')}</ul>` : `<p class="text-muted">${SC.t('ui.destinations.none')}</p>`}
          <a href="${SC.route('catalog')}?port=${encodeURIComponent(d.name)}" class="btn btn-outline mt-4">${SC.t('ui.destinations.all_sailings_to', { port: SC.port(d.name) })}</a>
        </div>
      </article>`;
    }).join('');
  };

  $('#country-filter').addEventListener('click', (e) => {
    const b = e.target.closest('[data-country]'); if (!b) return;
    country = b.dataset.country;
    $$('#country-filter [data-country]').forEach((x) => { const on = x === b; x.className = chipCls(on); x.setAttribute('aria-selected', on); });
    render();
  });

  render();
  // Honour #slug links from the homepage slider
  if (location.hash) setTimeout(() => document.getElementById(location.hash.slice(1))?.scrollIntoView(), 50);
});
