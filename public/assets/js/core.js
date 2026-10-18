/* =====================================================================
   Safer Cruise — core.js (load on every page, after data.js)
   ---------------------------------------------------------------------
   1. Icons            6. Booking session + favourites
   2. Helpers          7. Layout: header, footer, drawer, WhatsApp
   3. Data access      8. Behaviours: dropdown, modal, accordion, tabs, toast
   4. Pricing engine   9. Shared components: sailing card, stars, counter, terms
   5. Storage         10. Boot
   ===================================================================== */
(function () {
  'use strict';
  const D = window.SC_DATA;
  const SC = (window.SC = {});

  /* ------------------------------------------------------------------
     1. ICONS — inline SVG (Lucide-style). Use in HTML as:
        <i data-icon="ship" class="w-5 h-5"></i>   or   SC.icon('ship')
     ------------------------------------------------------------------ */
  const P = {
    menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
    anchor: '<circle cx="12" cy="5" r="3"/><path d="M12 22V8M5 12H2a10 10 0 0 0 20 0h-3"/>',
    ship: '<path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1s1.2 1 2.5 1c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.4 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.9 5.3 2.8 7.8"/><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6M12 10v4M12 2v3"/>',
    'chevron-down': '<path d="m6 9 6 6 6-6"/>',
    'chevron-left': '<path d="m15 18-6-6 6-6"/>',
    'chevron-right': '<path d="m9 18 6-6-6-6"/>',
    star: '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
    heart: '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    headset: '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>',
    award: '<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>',
    filter: '<path d="M22 3H2l8 9.5V19l4 2v-8.5z"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    list: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    bed: '<path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9"/>',
    size: '<path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
    mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    chat: '<path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.6A8.4 8.4 0 1 1 21 11.5z"/>',
    utensils: '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"/>',
    coffee: '<path d="M17 8h1a4 4 0 1 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4zM6 2v2M10 2v2M14 2v2"/>',
    music: '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    smile: '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/>',
    droplet: '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5S5 13 5 15a7 7 0 0 0 7 7z"/>',
    waves: '<path d="M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5s2.4 2 5 2 2.5-2 5-2c1.3 0 1.9.5 2.5 1M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2 2.5-2 5-2c1.3 0 1.9.5 2.5 1M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2 2.5-2 5-2c1.3 0 1.9.5 2.5 1"/>',
    flower: '<path d="M12 22c4-2 7-5.5 7-10-3 0-5.5 1.5-7 4-1.5-2.5-4-4-7-4 0 4.5 3 8 7 10zM12 16V9"/><circle cx="12" cy="6" r="3"/>',
    bag: '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0"/>',
    crown: '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zM5 20h14"/>',
    globe: '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
    'arrow-left': '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    'arrow-right': '<path d="M5 12h14M12 5l7 7-7 7"/>',
    card: '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    home: '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    ticket: '<path d="M3 7v2a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2zM13 5v2M13 17v2M13 11v2"/>',
    sliders: '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    minus: '<path d="M5 12h14"/>',
    alert: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
    external: '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    tag: '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><path d="M7 7h.01"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    pulse: '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/><path d="M3.2 12h4.3l1.5-3 3 6 1.5-3h7.3"/>',
    buoy: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><path d="m4.9 4.9 4.3 4.3M14.8 14.8l4.3 4.3M14.8 9.2l4.3-4.3M4.9 19.1l4.3-4.3"/>',
    edit: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
    route: '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
    sort: '<path d="m3 16 4 4 4-4M7 20V4M21 8l-4-4-4 4M17 4v16"/>'
  };
  SC.icon = (name, cls = 'w-5 h-5', filled = false) =>
    `<svg class="${cls}" viewBox="0 0 24 24" fill="${filled ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="${filled ? 0 : 1.8}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${P[name] || ''}</svg>`;
  SC.hydrateIcons = (root = document) => {
    root.querySelectorAll('i[data-icon]').forEach((el) => {
      el.outerHTML = SC.icon(el.dataset.icon, el.getAttribute('class') || 'w-5 h-5', el.hasAttribute('data-filled'));
    });
  };

  /* ------------------------------------------------------------------
     2. HELPERS
     ------------------------------------------------------------------ */
  SC.$ = (s, r = document) => r.querySelector(s);
  SC.$$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  SC.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  // All prices are shown in US dollars, e.g. $2,310
  SC.money = (n) => `${n < 0 ? '−' : ''}${D.currencySymbol}${Math.abs(Math.round(n)).toLocaleString('en-US')}`;
  SC.parseDate = (iso) => { const [y, m, d] = iso.split('-').map(Number); return new Date(y, m - 1, d); };
  SC.toISO = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  SC.addDays = (iso, n) => { const d = SC.parseDate(iso); d.setDate(d.getDate() + n); return SC.toISO(d); };
  // Laravel: dates in the page language (lang/*/site.php date_locale: en-GB, or Arabic months with Western digits)
  SC.fmtDate = (iso, opts = { day: 'numeric', month: 'short', year: 'numeric' }) => SC.parseDate(iso).toLocaleDateString(SC.t('date_locale'), opts);
  SC.fmtMonth = (key) => SC.parseDate(key + '-01').toLocaleDateString(SC.t('date_locale'), { month: 'long', year: 'numeric' });
  SC.params = () => new URLSearchParams(location.search);
  SC.plural = (n, one, many) => `${n} ${n === 1 ? one : many || one + 's'}`;
  SC.daysUntil = (iso) => Math.ceil((SC.parseDate(iso) - new Date().setHours(0, 0, 0, 0)) / 864e5);

  /* Laravel: fixed website words from lang/{en,ar}/site.php (SC_DATA.i18n). The language is <html lang>,
     switched by the header's عربي button; listen for 'sc:lang' to redraw script-built text. */
  SC.lang = () => (document.documentElement.lang === 'ar' ? 'ar' : 'en');
  /* Port / country for display: "Safaga, Egypt" → "سفاجا، مصر" in Arabic (SC_DATA.portNames). Routes,
     filters and URLs keep the English names, which are the keys. */
  SC.port = (p) => String(p ?? '').split(', ').map((x) => (D.portNames || {})[x] || x).join(SC.lang() === 'ar' ? '، ' : ', ');
  SC.t = (key, replace = {}) => {
    const get = (lang) => key.split('.').reduce((o, k) => (o == null ? undefined : o[k]), (D.i18n || {})[lang]);
    const s = get(SC.lang()) ?? get('en') ?? key;
    return typeof s === 'string' ? Object.entries(replace).reduce((t, [k, v]) => t.replaceAll(`:${k}`, v), s) : s;
  };
  /* Plural form in Laravel's trans_choice format: "{1} one|[2,10] few|[11,*] many" or "one|many" */
  SC.tc = (key, n, replace = {}) => {
    const parts = String(SC.t(key)).split('|');
    let text = parts.length === 2 && !/^\s*[[{]/.test(parts[0]) ? parts[n === 1 ? 0 : 1] : parts[parts.length - 1];
    for (const p of parts) {
      const m = p.match(/^\s*(?:\{(\d+)\}|\[(\d+),(\d+|\*)\])\s*([\s\S]*)$/);
      if (m && (m[1] !== undefined ? n === +m[1] : n >= +m[2] && (m[3] === '*' || n <= +m[3]))) { text = m[4]; break; }
    }
    return Object.entries({ count: n, ...replace }).reduce((t, [k, v]) => t.replaceAll(`:${k}`, v), text.trim());
  };

  /* Laravel: named routes injected by the layout (SC_DATA.routes), e.g. SC.route('details', { cruise: 'red-sea-discovery' }) */
  SC.route = (name, params = {}) => Object.entries(params).reduce(
    (url, [k, v]) => url.replace(`__${k}__`, encodeURIComponent(v)), (D.routes || {})[name] || '#');
  /* JSON request with the CSRF token. Resolves to { ok, status, data }. */
  SC.api = async (method, url, body) => {
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
      body: body === undefined ? undefined : JSON.stringify(body),
      credentials: 'same-origin'
    });
    let data = null; try { data = await res.json(); } catch (e) { /* empty body */ }
    return { ok: res.ok, status: res.status, data };
  };
  /* First validation message from a Laravel 422 response */
  SC.apiError = (r, fallback = SC.t('ui.error')) =>
    (r.data && r.data.errors && Object.values(r.data.errors)[0]?.[0]) || (r.data && r.data.message) || fallback;

  /* Age at a reference date (the departure date), in whole years and months. */
  SC.ageAt = (dobISO, refISO) => {
    const b = SC.parseDate(dobISO), r = SC.parseDate(refISO);
    let months = (r.getFullYear() - b.getFullYear()) * 12 + (r.getMonth() - b.getMonth());
    if (r.getDate() < b.getDate()) months -= 1;
    return { years: Math.floor(months / 12), months };
  };
  /* Returns null when the DOB fits the band, else a message explaining the fix.
     Laravel: words from lang/{en,ar}/site.php → booking.errors (App\Services\AgeBandValidator uses the same keys). */
  SC.checkBand = (band, dobISO, refISO) => {
    const e = (key, r) => SC.t(`booking.errors.${key}`, r);
    if (!dobISO) return e('dob_required');
    if (SC.parseDate(dobISO) > new Date()) return e('dob_future');
    const { years, months } = SC.ageAt(dobISO, refISO);
    const B = D.ageBands[band];
    if (band === 'adult' && years < B.minYears) return e('dob_adult', { min: B.minYears });
    if (band === 'child' && (years < B.minYears || years > B.maxYears)) return e('dob_child', { min: B.minYears, max: B.maxYears });
    if (band === 'infant' && (months < B.minMonths || months >= B.maxMonthsExclusive)) return e('dob_infant', { min: B.minMonths, max: B.maxMonthsExclusive / 12 });
    return null;
  };
  /* Traveller-form values are stored in English (the server checks them); shown in the page language */
  SC.genderLabel = (g) => SC.t(`genders.${g}`);
  SC.natLabel = (n) => ((SC.t('nationalities') || {})[n]) || n;

  /* ------------------------------------------------------------------
     3. DATA ACCESS
     ------------------------------------------------------------------ */
  SC.getCruise = (id) => D.cruises.find((c) => c.id === id);
  SC.getCabin = (id) => D.cabins.find((c) => c.id === id);
  SC.getShip = (id) => D.ships.find((s) => s.id === id);
  SC.cabinsSorted = () => [...D.cabins].sort((a, b) => a.order - b.order);
  SC.findSailing = (sid) => {
    for (const c of D.cruises) { const s = c.sailings.find((x) => x.id === sid); if (s) return { cruise: c, sailing: s }; }
    return null;
  };
  /* Flattened "view model" for one departure — what cards and filters use. */
  SC.sailingView = (c, s) => {
    const available = SC.cabinsSorted().filter((cab) => !(s.soldOut || []).includes(cab.id) && (s.prices || {})[cab.id]);
    const fromPrice = Math.min(...available.map((cab) => SC.prices(c, s, cab.id).perPerson));
    return {
      key: s.id, cruiseId: c.id, sailingId: s.id, name: c.name, ship: SC.getShip(c.shipId).name,
      nights: c.nights, days: c.nights + 1, departure: s.departure, returnDate: SC.addDays(s.departure, c.nights),
      departurePort: c.departurePort, arrivalPort: c.arrivalPort, route: c.route, tags: c.tags, visaFree: c.visaFree,
      rating: c.rating, reviews: c.reviews, popularity: c.popularity, image: c.image,
      soldOut: s.soldOut || [], availableCabins: available.map((x) => x.id), fromPrice, cruise: c, sailing: s
    };
  };
  SC.allSailings = () => D.cruises.flatMap((c) => c.sailings.map((s) => SC.sailingView(c, s)))
    .filter((v) => SC.daysUntil(v.departure) > 0)
    .sort((a, b) => a.departure.localeCompare(b.departure));
  SC.allPorts = () => [...new Set(D.cruises.flatMap((c) => c.route.map((p) => p.split(',')[0])))].filter((p) => p !== 'At sea');

  /* ------------------------------------------------------------------
     4. PRICING ENGINE — tiered occupancy (same rules as App\Services\PricingService)
     Each Sailing × Cabin has three cabin prices for the whole cruise, set in Admin → Sailings:
       single = 1 paying guest · double = 2 paying guests · extra = the 3rd guest (adult or child)
     Infants are free, but they count towards the cabin limit (see SC.guestLimits below).
     Total = fare lines + fees − discount. The server recalculates it when the booking is confirmed.
     ------------------------------------------------------------------ */
  const r5 = (n) => Math.round(n / 5) * 5;
  SC.prices = (cruise, sailing, cabinId) => {
    const p = (sailing.prices || {})[cabinId] || { single: 0, double: 0, extra: 0 };
    // perPerson: the "From, per person" figure on cards, for two guests sharing
    return { single: p.single, double: p.double, extra: p.extra, infant: 0, perPerson: p.double / 2 };
  };
  /* 1 paying guest → single; 2 → double; 3 → double + extra; infants free */
  SC.fareLines = (prices, guests) => {
    const paying = (guests.adult || 0) + (guests.child || 0);
    const line = (band, unit, qty) => ({ band, label: SC.t(`pricing.${band}`), unit, qty, total: unit * qty });
    return [
      paying === 1 && line('single', prices.single, 1),
      paying >= 2 && line('double', prices.double, 1),
      paying > 2 && line('extra', prices.extra, paying - 2),
      guests.infant > 0 && line('infant', 0, guests.infant)
    ].filter(Boolean);
  };
  SC.quote = ({ cruise, sailing, cabinId, guests, promoCode }) => {
    const prices = SC.prices(cruise, sailing, cabinId);
    const bands = ['adult', 'child', 'infant'];
    const lines = SC.fareLines(prices, guests);
    const fare = lines.reduce((s, l) => s + l.total, 0);
    const fees = bands.reduce((s, b) => s + (guests[b] || 0) * D.fees.perGuestPerNight[b] * cruise.nights, 0);
    const promo = promoCode && D.promos[promoCode.toUpperCase()];
    const discount = promo ? r5(fare * promo.value / 100) : 0;
    return { prices, lines, fare, fees, discount, promo: promo ? { code: promoCode.toUpperCase(), ...promo } : null, total: fare + fees - discount, count: bands.reduce((s, b) => s + (guests[b] || 0), 0) };
  };
  /* Guest rules (same as App\Http\Requests\ConfirmBookingRequest):
     - adults + children + infants together ≤ cap (the cabin's maxGuests, at most SC_DATA.maxCabinGuests = 3)
     - at least one adult; every infant travels with an adult
     guestLimits(band, guests, cap) → { min, max } for a +/- counter; guestError(guests, cap) → message or null. */
  SC.maxCabinGuests = () => D.maxCabinGuests || 3;
  SC.guestTotal = (g) => (g.adult || 0) + (g.child || 0) + (g.infant || 0);
  SC.guestLimits = (band, g, cap = SC.maxCabinGuests()) => {
    const room = Math.max(0, cap - (SC.guestTotal(g) - (g[band] || 0)));
    if (band === 'adult') return { min: 1, max: Math.max(1, room) };
    if (band === 'infant') return { min: 0, max: g.adult >= 1 ? room : 0 };
    return { min: 0, max: room };
  };
  // Counts saved under older rules are trimmed to fit: infants first, then children, then adults (never below 1)
  SC.fitGuests = (g, cap = SC.maxCabinGuests()) => {
    g.adult = Math.max(1, g.adult || 0); g.child = g.child || 0; g.infant = g.infant || 0;
    for (const band of ['infant', 'child', 'adult']) while (SC.guestTotal(g) > cap && g[band] > (band === 'adult' ? 1 : 0)) g[band]--;
    return g;
  };
  SC.guestError = (g, cap = SC.maxCabinGuests()) => {
    if ((g.infant || 0) > 0 && (g.adult || 0) < 1) return SC.t('booking.errors.infant_needs_adult');
    if ((g.adult || 0) < 1) return SC.t('booking.errors.adult_required');
    if (SC.guestTotal(g) > cap) return SC.t('booking.errors.cabin_too_small', { cabin: '', max: cap, count: SC.guestTotal(g) }).trim();
    return null;
  };
  // Laravel: wording from lang/{en,ar}/site.php (English output unchanged)
  SC.guestText =(g) => ['adult', 'child', 'infant'].filter((b) => g[b]).map((b) => SC.tc(`guests.${b}`, g[b])).join(SC.t('guests.join'));

  /* ------------------------------------------------------------------
     5. STORAGE (wrapped: private mode or blocked storage must not break pages)
     ------------------------------------------------------------------ */
  const store = (type) => ({
    get(k, fb) { try { const v = window[type].getItem(k); return v ? JSON.parse(v) : fb; } catch (e) { return fb; } },
    set(k, v) { try { window[type].setItem(k, JSON.stringify(v)); } catch (e) { /* ignore */ } },
    remove(k) { try { window[type].removeItem(k); } catch (e) { /* ignore */ } }
  });
  SC.session = store('sessionStorage');
  SC.local = store('localStorage');

  /* ------------------------------------------------------------------
     6. BOOKING SESSION + FAVOURITES
     The booking object travels details → booking → confirmation.
     ------------------------------------------------------------------ */
  SC.booking = {
    get: () => SC.session.get('sc_booking', null),
    set: (b) => SC.session.set('sc_booking', b),
    patch(p) { const b = { ...(this.get() || {}), ...p }; this.set(b); return b; },
    clear: () => SC.session.remove('sc_booking')
  };
  SC.guestsPref = {
    get: () => SC.session.get('sc_guests', { adult: 2, child: 0, infant: 0 }),
    set: (g) => SC.session.set('sc_guests', g)
  };
  // Laravel: signed-in guests keep favourites on their account (SC_DATA.favorites); others in this browser
  SC.favs = {
    all: () => (D.user ? D.favorites || [] : SC.local.get('sc_favs', [])),
    has(id) { return this.all().includes(id); },
    toggle(id) {
      const list = this.all(); const i = list.indexOf(id);
      i >= 0 ? list.splice(i, 1) : list.push(id);
      if (D.user) {
        D.favorites = list;
        SC.api(i < 0 ? 'POST' : 'DELETE', SC.route('favorite', { cruise: id }))
          .then((r) => { if (!r.ok) SC.toast(SC.t('ui.favorites.error'), 'error'); });
      } else SC.local.set('sc_favs', list);
      return i < 0;
    }
  };
  SC.trips = { all: () => SC.local.get('sc_trips', []), add(t) { const l = this.all(); l.unshift(t); SC.local.set('sc_trips', l); } };

  /* ------------------------------------------------------------------
     7. LAYOUT — header, footer, mobile drawer, WhatsApp button
     Pages include <div id="site-header" data-active="home"></div>
     and <div id="site-footer"></div>; core.js fills them.
     ------------------------------------------------------------------ */
  const WA_NUMBER = D.whatsappNumber || '905016451111';
  SC.whatsapp = (msg) => `https://wa.me/${WA_NUMBER}?text=` + encodeURIComponent(msg || 'Hello, I would like to ask about AROYA cruises on Safer Cruise.');

  /* Laravel: header, mobile drawer, footer and WhatsApp button are rendered by Blade
     (resources/views/partials/header.blade.php and footer.blade.php), so routes and
     the signed-in state come from the server. These stay as no-ops for the boot sequence. */
  SC.renderHeader = () => {};
  SC.renderFooter = () => {};

  /* ------------------------------------------------------------------
     8. BEHAVIOURS (event delegation so dynamically rendered markup works)
     ------------------------------------------------------------------ */
  // --- Dropdowns: [data-dropdown] > [data-dropdown-trigger] + .dropdown-panel
  SC.closeDropdowns = (except) => SC.$$('[data-dropdown]').forEach((dd) => {
    if (dd === except) return;
    dd.querySelector('.dropdown-panel')?.classList.remove('open');
    dd.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
  });

  // --- Modal: SC.modal.open('id') or [data-modal-open="id"] / [data-modal-close]
  let lastFocus = null;
  SC.modal = {
    open(id) {
      const m = document.getElementById(id); if (!m) return;
      lastFocus = document.activeElement;
      m.classList.add('open'); m.setAttribute('aria-hidden', 'false'); document.body.classList.add('no-scroll');
      setTimeout(() => m.querySelector('[autofocus], button, a, input, select')?.focus(), 30);
    },
    close(id) {
      const m = id ? document.getElementById(id) : SC.$$('.modal.open').pop(); if (!m) return;
      m.classList.remove('open'); m.setAttribute('aria-hidden', 'true');
      if (!SC.$('.modal.open') && !SC.$('.drawer.open')) document.body.classList.remove('no-scroll');
      lastFocus?.focus?.();
    }
  };
  // Builds a modal shell once; returns the body element to fill.
  SC.ensureModal = (id, { title = '', width = '760px' } = {}) => {
    let m = document.getElementById(id);
    if (!m) {
      m = document.createElement('div');
      m.className = 'modal'; m.id = id; m.setAttribute('aria-hidden', 'true');
      m.innerHTML = `<div class="modal-backdrop" data-modal-close></div>
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="${id}-title" style="--modal-w:${width}">
          <div class="sticky top-0 z-10 bg-white/95 backdrop-blur flex items-center justify-between gap-4 px-5 sm:px-7 py-4 border-b border-line">
            <h2 id="${id}-title" class="font-display font-semibold text-lg"></h2>
            <button class="icon-btn" data-modal-close aria-label="${SC.t('ui.close')}">${SC.icon('x')}</button>
          </div>
          <div class="modal-body p-5 sm:p-7"></div>
        </div>`;
      document.body.appendChild(m);
    }
    m.querySelector(`#${id}-title`).textContent = title;
    return m.querySelector('.modal-body');
  };

  // --- Drawer
  SC.drawer = {
    open(id) { const d = document.getElementById(id); if (!d) return; d.classList.add('open'); d.setAttribute('aria-hidden', 'false'); document.body.classList.add('no-scroll'); },
    close(d) { (d || SC.$('.drawer.open'))?.classList.remove('open'); if (!SC.$('.modal.open')) document.body.classList.remove('no-scroll'); }
  };

  // --- Toast
  SC.toast = (msg, type = 'ok') => {
    let root = SC.$('#toast-root');
    if (!root) { root = document.createElement('div'); root.id = 'toast-root'; root.setAttribute('aria-live', 'polite'); document.body.appendChild(root); }
    const t = document.createElement('div');
    t.className = 'toast' + (type === 'error' ? ' error' : '');
    t.innerHTML = SC.icon(type === 'error' ? 'alert' : 'check', 'w-5 h-5') + `<span>${SC.esc(msg)}</span>`;
    root.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 2800);
  };

  // --- Tabs: [data-tabs] containing [data-tab="x"] buttons and [data-panel="x"] panels
  SC.activateTab = (container, key) => {
    SC.$$('[data-tab]', container).forEach((t) => { const on = t.dataset.tab === key; t.setAttribute('aria-selected', on); t.classList.toggle('is-active', on); });
    SC.$$('[data-panel]', container).forEach((p) => { p.hidden = p.dataset.panel !== key; });
  };

  // Global click delegation
  document.addEventListener('click', (e) => {
    const t = e.target;
    // dropdown
    const trig = t.closest('[data-dropdown-trigger]');
    if (trig) {
      const dd = trig.closest('[data-dropdown]'); const panel = dd.querySelector('.dropdown-panel');
      const willOpen = !panel.classList.contains('open');
      SC.closeDropdowns(dd); panel.classList.toggle('open', willOpen); trig.setAttribute('aria-expanded', willOpen);
      return;
    }
    if (!t.closest('.dropdown-panel')) SC.closeDropdowns();
    if (t.closest('[data-dropdown-close]')) SC.closeDropdowns();

    // modal
    const mo = t.closest('[data-modal-open]'); if (mo) { e.preventDefault(); SC.modal.open(mo.dataset.modalOpen); return; }
    if (t.closest('[data-modal-close]')) { SC.modal.close(t.closest('.modal')?.id); return; }

    // drawer
    const dO = t.closest('[data-drawer-open]'); if (dO) { SC.drawer.open(dO.dataset.drawerOpen); return; }
    if (t.closest('[data-drawer-close]')) { SC.drawer.close(t.closest('.drawer')); return; }

    // accordion
    const accT = t.closest('.acc-trigger');
    if (accT) {
      const item = accT.closest('.acc-item'); const group = accT.closest('[data-accordion]');
      const open = !item.classList.contains('open');
      if (group?.dataset.accordion === 'single') SC.$$('.acc-item', group).forEach((i) => { i.classList.remove('open'); i.querySelector('.acc-trigger')?.setAttribute('aria-expanded', 'false'); });
      item.classList.toggle('open', open); accT.setAttribute('aria-expanded', open);
      return;
    }

    // tabs
    const tab = t.closest('[data-tab]');
    if (tab && tab.closest('[data-tabs]')) { SC.activateTab(tab.closest('[data-tabs]'), tab.dataset.tab); return; }

    // favourite heart
    const fav = t.closest('[data-fav]');
    if (fav) {
      e.preventDefault();
      const on = SC.favs.toggle(fav.dataset.fav);
      SC.$$(`[data-fav="${fav.dataset.fav}"]`).forEach((b) => SC.paintFav(b, on));
      SC.toast(SC.t(on ? 'ui.favorites.saved' : 'ui.favorites.removed'));
      document.dispatchEvent(new CustomEvent('sc:favs'));
      return;
    }

    // Laravel: language switch. The server renders the page and SC_DATA in the language of the sc_lang
    // cookie (App\Http\Middleware\SetLocale), so set it and reload.
    if (t.closest('[data-dir-toggle]')) {
      const next = SC.lang() === 'ar' ? 'en' : 'ar';
      document.cookie = `sc_lang=${next}; path=/; max-age=31536000; samesite=lax`;
      location.reload();
      return;
    }

    // footer policy links
    const a = t.closest('a[href="#terms"], a[href="#cancellation"]');
    if (a) { e.preventDefault(); SC.ui.openTerms(a.getAttribute('href').slice(1)); }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (SC.$('.modal.open')) SC.modal.close();
    else if (SC.$('.drawer.open')) SC.drawer.close();
    SC.closeDropdowns();
  });

  // Broken image → branded placeholder (keeps layouts intact while real media is pending)
  const PLACEHOLDER = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500"><defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7FB8E4"/><stop offset="1" stop-color="#155C9E"/></linearGradient></defs><rect width="800" height="500" fill="url(#g)"/><path d="M0 380 Q100 350 200 380 T400 380 T600 380 T800 380 V500 H0Z" fill="#124A80" opacity=".7"/><path d="M0 420 Q100 395 200 420 T400 420 T600 420 T800 420 V500 H0Z" fill="#0C2D4E" opacity=".8"/><path d="M250 330 h300 l-30 45 h-240z M300 290 h190 v40 h-190z M350 260 h90 v30 h-90z" fill="#fff" opacity=".9"/></svg>');
  document.addEventListener('error', (e) => {
    const img = e.target;
    if (img.tagName !== 'IMG') return;
    // Walk the fallback chain: AROYA slot → stock photo → local SVG → placeholder
    const tries = Number(img.dataset.fb || 0);
    const next = D.imageFallbacks && D.imageFallbacks[img.getAttribute('src')];
    if (next && tries < 4) { img.dataset.fb = tries + 1; img.src = next; }
    else if (tries < 5) { img.dataset.fb = 5; img.src = PLACEHOLDER; }
  }, true);

  /* ------------------------------------------------------------------
     9. SHARED COMPONENTS
     ------------------------------------------------------------------ */
  SC.ui = {};
  SC.paintFav = (btn, on) => {
    btn.setAttribute('aria-pressed', on);
    btn.setAttribute('aria-label', SC.t(on ? 'ui.favorites.remove' : 'ui.favorites.save'));
    btn.innerHTML = SC.icon('heart', 'w-5 h-5', on);
    btn.classList.toggle('text-rose-500', on); btn.classList.toggle('text-ocean-700', !on);
  };
  SC.ui.favBtn = (id, extra = '') => {
    const on = SC.favs.has(id);
    return `<button class="icon-btn ${on ? 'text-rose-500' : 'text-ocean-700'} ${extra}" data-fav="${id}" aria-pressed="${on}" aria-label="${SC.t(on ? 'ui.favorites.remove' : 'ui.favorites.save')}">${SC.icon('heart', 'w-5 h-5', on)}</button>`;
  };
  SC.ui.stars = (rating, reviews) =>
    `<span class="inline-flex items-center gap-1 text-sm font-bold">${SC.icon('star', 'w-4 h-4 text-sun-500', true)}${rating.toFixed(1)}${reviews != null ? `<span class="font-medium text-muted">(${reviews})</span>` : ''}</span>`;
  SC.ui.route = (route, cls = '') =>
    `<p class="flex flex-wrap items-center gap-x-1 gap-y-0.5 text-sm text-muted ${cls}">${route.map((p, i) => `<span>${SC.esc(SC.port(p.split(',')[0]))}</span>${i < route.length - 1 ? SC.icon('chevron-right', 'w-3.5 h-3.5 text-ocean-300 rtl:rotate-180') : ''}`).join('')}</p>`;
  SC.ui.tags = (v) => [
    v.visaFree ? `<span class="tag tag-white">${SC.t('ui.tags.visaFree')}</span>` : '',
    v.tags.includes('festive') ? `<span class="tag tag-white">${SC.t('ui.tags.festive')}</span>` : '',
    v.tags.includes('weekend') ? `<span class="tag tag-white">${SC.t('ui.tags.weekend')}</span>` : ''
  ].join('');
  SC.detailsUrl = (v) => SC.route('details', { cruise: v.cruiseId }) + (v.sailingId ? `?sailing=${encodeURIComponent(v.sailingId)}` : '');

  /* Sailing card — one departure, shaped like a boarding pass.
     layout: 'grid' (stacked) | 'list' (horizontal from md up). perfBg = parent background. */
  SC.ui.sailingCard = (v, { layout = 'grid', perfBg = '#F5F8FB', alsoDates = [] } = {}) => {
    const also = alsoDates.length ? `<p class="mt-3 text-xs text-muted flex items-center gap-1.5">${SC.icon('calendar', 'w-3.5 h-3.5')} ${SC.t('ui.card.also_departs', { dates: alsoDates.slice(0, 2).map((d) => SC.fmtDate(d, { day: 'numeric', month: 'short' })).join(SC.lang() === 'ar' ? '، ' : ', ') })}${alsoDates.length > 2 ? SC.t('ui.card.and_more', { count: alsoDates.length - 2 }) : ''}</p>` : '';
    const dateCol = (label, val) => `<div><dt class="text-xs text-muted font-semibold">${label}</dt><dd class="font-bold text-[0.95rem] mt-0.5 tabular">${val}</dd></div>`;
    const dates = `<dl class="grid grid-cols-3 gap-3 mt-4">
        ${dateCol(SC.t('ui.card.departs'), SC.fmtDate(v.departure, { day: 'numeric', month: 'short' }))}
        ${dateCol(SC.t('ui.card.returns'), SC.fmtDate(v.returnDate, { day: 'numeric', month: 'short' }))}
        ${dateCol(SC.t('ui.card.length'), SC.tc('nights', v.nights))}
      </dl>`;
    const priceBlock = `<div><p class="text-xs text-muted font-semibold">${SC.t('ui.from_per_person')}</p><p class="font-display font-bold text-xl text-ocean-800 tabular">${SC.money(v.fromPrice)}</p></div>`;
    const actions = `<div class="flex items-center gap-2">
        <a href="${SC.whatsapp(SC.t('ui.card.whatsapp_message', { cruise: v.name, date: SC.fmtDate(v.departure) }))}" target="_blank" rel="noopener" class="icon-btn" aria-label="${SC.t('ui.card.ask_whatsapp')}">${SC.icon('chat', 'w-5 h-5')}</a>
        <a href="${SC.detailsUrl(v)}" class="btn btn-accent btn-sm">${SC.t('ui.card.select')}</a>
      </div>`;
    const media = `<div class="relative img-zoom ${layout === 'list' ? 'md:w-[38%] aspect-[16/10] md:aspect-auto' : 'aspect-[16/10]'}">
        <img src="${v.image}" alt="${SC.esc(SC.t('ui.card.image_alt', { cruise: v.name, ship: v.ship }))}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute top-3 start-3 flex flex-wrap gap-1.5">${SC.ui.tags(v)}</div>
        <div class="absolute top-3 end-3">${SC.ui.favBtn(v.cruiseId, 'bg-white/95 border-transparent')}</div>
      </div>`;
    const body = `<p class="text-xs font-bold text-lagoon-600 flex items-center gap-1.5">${SC.icon('ship', 'w-4 h-4')} ${SC.t('ui.card.ship_from', { ship: v.ship, port: SC.port(v.departurePort) })}</p>
        <div class="flex items-start justify-between gap-3 mt-1.5">
          <h3 class="font-display font-semibold text-lg leading-snug"><a href="${SC.detailsUrl(v)}" class="hover:text-ocean-600">${SC.esc(v.name)}</a></h3>
          ${SC.ui.stars(v.rating)}
        </div>
        ${SC.ui.route(v.route, 'mt-2')}
        ${dates}${also}`;

    if (layout === 'list') {
      return `<article class="card card-hover overflow-hidden flex flex-col md:flex-row" style="--perf-bg:${perfBg}">
        ${media}
        <div class="flex-1 p-5 md:p-6">${body}</div>
        <div class="perf-x md:hidden"></div>
        <div class="md:perf-y md:w-56 p-5 md:p-6 flex md:flex-col items-center md:items-start justify-between gap-4 md:border-s-2 md:border-dashed md:border-line">${priceBlock}${actions}</div>
      </article>`;
    }
    return `<article class="card card-hover overflow-hidden flex flex-col" style="--perf-bg:${perfBg}">
      ${media}
      <div class="p-5 flex-1">${body}</div>
      <div class="perf-x"></div>
      <div class="px-5 py-4 flex items-end justify-between gap-3">${priceBlock}${actions}</div>
    </article>`;
  };

  /* Guest counter row (Adult / Child / Infant) */
  SC.ui.counterRow = (band, value, { min = 0, max = 9 } = {}) => {
    // Laravel: label and hint come from Admin → Age bands, already in the page language (SC_DATA)
    const B = D.ageBands[band] || { label: SC.t(`bands.${band}.label`), hint: SC.t(`bands.${band}.hint`) };
    const group = SC.lang() === 'ar' ? B.label : `${B.label}s`;
    return `<div class="flex items-center justify-between gap-4 py-3" data-counter="${band}">
      <div><p class="font-bold">${B.label}</p><p class="text-xs text-muted">${B.hint}</p></div>
      <div class="counter" role="group" aria-label="${group}">
        <button type="button" data-step="-1" aria-label="${SC.t('bands.fewer')} ${group.toLowerCase()}" ${value <= min ? 'disabled' : ''}>${SC.icon('minus', 'w-4 h-4')}</button>
        <output aria-live="polite">${value}</output>
        <button type="button" data-step="1" aria-label="${SC.t('bands.more')} ${group.toLowerCase()}" ${value >= max ? 'disabled' : ''}>${SC.icon('plus', 'w-4 h-4')}</button>
      </div>
    </div>`;
  };
  /* Wires counters inside root. getLimits(band, guests) → {min,max}. onChange(guests). */
  SC.ui.bindCounters = (root, guests, onChange, getLimits = () => ({ min: 0, max: 9 })) => {
    const paint = () => SC.$$('[data-counter]', root).forEach((row) => {
      const b = row.dataset.counter; const { min, max } = getLimits(b, guests);
      row.querySelector('output').textContent = guests[b];
      row.querySelector('[data-step="-1"]').disabled = guests[b] <= min;
      row.querySelector('[data-step="1"]').disabled = guests[b] >= max;
    });
    root.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-step]'); if (!btn) return;
      const b = btn.closest('[data-counter]').dataset.counter; const { min, max } = getLimits(b, guests);
      guests[b] = Math.min(max, Math.max(min, guests[b] + Number(btn.dataset.step)));
      paint(); onChange && onChange(guests);
    });
    paint();
    return paint;
  };

  /* Terms & Cancellation modal — opens without leaving the page, so booking data is kept */
  SC.ui.openTerms = (focus = 'terms') => {
    const body = SC.ensureModal('terms-modal', { title: SC.t('ui.terms.title'), width: '680px' });
    body.innerHTML = `
      <section id="terms-sec" class="scroll-mt-24">
        <h3 class="font-display font-semibold text-lg mb-3">${SC.t('ui.terms.terms')}</h3>
        <div class="grid gap-3 text-[0.95rem] leading-relaxed text-ink/90">${D.terms.text.map((p) => `<p>${p}</p>`).join('')}</div>
      </section>
      <section id="cancel-sec" class="mt-8 scroll-mt-24">
        <h3 class="font-display font-semibold text-lg mb-1">${SC.t('ui.terms.cancellation')}</h3>
        <p class="text-sm text-muted mb-4">${SC.t('ui.terms.intro')}</p>
        <div class="overflow-x-auto"><table class="w-full text-sm border border-line rounded-xl overflow-hidden">
          <thead class="bg-ocean-50 text-ocean-800"><tr><th class="text-start p-3">${SC.t('ui.terms.when')}</th><th class="text-start p-3">${SC.t('ui.terms.penalty')}</th></tr></thead>
          <tbody>${D.terms.cancellation.map(([w, p]) => `<tr class="border-t border-line"><td class="p-3">${w}</td><td class="p-3 font-bold">${p}</td></tr>`).join('')}</tbody>
        </table></div>
      </section>
      <div class="mt-8 flex justify-end"><button class="btn btn-primary" data-modal-close>${SC.t('ui.terms.back')}</button></div>`;
    SC.modal.open('terms-modal');
    setTimeout(() => SC.$(focus === 'cancellation' ? '#cancel-sec' : '#terms-sec')?.scrollIntoView({ block: 'start' }), 60);
  };

  /* Cabin details modal with gallery (used on details page and booking review) */
  SC.ui.openCabin = (cabinId, onSelect) => {
    const c = SC.getCabin(cabinId);
    const body = SC.ensureModal('cabin-modal', { title: `${c.nameEn}`, width: '880px' });
    const spec = (icon, label, val) => val ? `<div class="flex gap-3"><span class="grid place-items-center w-10 h-10 rounded-xl bg-ocean-50 text-ocean-600 shrink-0">${SC.icon(icon)}</span><div><p class="text-xs font-semibold text-muted">${label}</p><p class="font-bold text-sm">${val}</p></div></div>` : '';
    body.innerHTML = `
      <div class="grid gap-3">
        <div class="relative rounded-2xl overflow-hidden aspect-[16/9] bg-ocean-50"><img id="cab-main" src="${c.images[0]}" alt="${c.nameEn}" class="w-full h-full object-cover"></div>
        <div class="flex gap-2 overflow-x-auto no-scrollbar" role="tablist" aria-label="${SC.t('ui.cabin.photos')}">
          ${c.images.map((src, i) => `<button class="shrink-0 w-24 h-16 rounded-xl overflow-hidden border-2 ${i === 0 ? 'border-ocean-500' : 'border-transparent'}" data-cab-thumb="${i}" aria-label="${SC.t('ui.photo_n', { n: i + 1 })}"><img src="${src}" alt="" class="w-full h-full object-cover"></button>`).join('')}
        </div>
      </div>
      <div class="mt-6 flex flex-wrap items-baseline justify-between gap-2">
        <div><p class="font-display font-semibold text-xl">${c.nameEn}</p>${SC.lang() === 'ar' ? '' : `<p class="text-muted" lang="ar" dir="rtl">${c.nameAr}</p>`}</div>
        ${c.balcony ? `<span class="tag tag-lagoon">${SC.icon('sun', 'w-4 h-4')} ${SC.t('ui.cabin.private_balcony')}</span>` : `<span class="tag tag-ocean">${SC.t('ui.cabin.no_balcony')}</span>`}
      </div>
      <p class="mt-3 leading-relaxed text-ink/90 max-w-[70ch]">${c.description}</p>
      <div class="grid sm:grid-cols-2 gap-4 mt-6">
        ${spec('users', SC.t('ui.cabin.max_guests'), SC.t('ui.guests_up_to', { count: c.maxGuests }))}
        ${spec('size', SC.t('ui.cabin.cabin_size'), c.size + (c.balcony ? ` + ${SC.t('ui.balcony_size', { size: c.balcony })}` : ''))}
        ${spec('bed', SC.t('ui.cabin.beds'), c.beds)}
        ${spec('home', SC.t('ui.cabin.bedroom'), c.bedroom)}
      </div>
      <h4 class="font-display font-semibold mt-7 mb-3">${SC.t('ui.cabin.in_cabin')}</h4>
      <ul class="grid sm:grid-cols-2 gap-2.5">${c.features.map((f) => `<li class="flex items-center gap-2.5 text-sm">${SC.icon('check', 'w-4 h-4 text-lagoon-600')}${f}</li>`).join('')}</ul>
      ${onSelect ? `<div class="mt-8 flex justify-end gap-2"><button class="btn btn-outline" data-modal-close>${SC.t('ui.close')}</button><button class="btn btn-accent" id="cab-modal-select">${SC.t('ui.cabin.choose')}</button></div>` : ''}`;
    body.addEventListener('click', (e) => {
      const th = e.target.closest('[data-cab-thumb]'); if (!th) return;
      SC.$('#cab-main', body).src = c.images[th.dataset.cabThumb];
      SC.$$('[data-cab-thumb]', body).forEach((b) => b.classList.toggle('border-ocean-500', b === th));
      SC.$$('[data-cab-thumb]', body).forEach((b) => b.classList.toggle('border-transparent', b !== th));
    });
    SC.$('#cab-modal-select', body)?.addEventListener('click', () => { SC.modal.close('cabin-modal'); onSelect(cabinId); });
    SC.modal.open('cabin-modal');
  };

  /* ------------------------------------------------------------------
     10. BOOT
     ------------------------------------------------------------------ */
  // Laravel: <html lang/dir> come from the server (sc_lang cookie); the old localStorage "sc_dir" preview is gone
  document.addEventListener('DOMContentLoaded', () => {
    SC.renderHeader();
    SC.renderFooter();
    SC.hydrateIcons();
    document.dispatchEvent(new CustomEvent('sc:ready'));
  });
})();
