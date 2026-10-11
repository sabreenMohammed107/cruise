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
  SC.fmtDate = (iso, opts = { day: 'numeric', month: 'short', year: 'numeric' }) => SC.parseDate(iso).toLocaleDateString('en-GB', opts);
  SC.fmtMonth = (key) => SC.parseDate(key + '-01').toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
  SC.params = () => new URLSearchParams(location.search);
  SC.plural = (n, one, many) => `${n} ${n === 1 ? one : many || one + 's'}`;
  SC.daysUntil = (iso) => Math.ceil((SC.parseDate(iso) - new Date().setHours(0, 0, 0, 0)) / 864e5);

  /* Age at a reference date (the departure date), in whole years and months. */
  SC.ageAt = (dobISO, refISO) => {
    const b = SC.parseDate(dobISO), r = SC.parseDate(refISO);
    let months = (r.getFullYear() - b.getFullYear()) * 12 + (r.getMonth() - b.getMonth());
    if (r.getDate() < b.getDate()) months -= 1;
    return { years: Math.floor(months / 12), months };
  };
  /* Returns null when the DOB fits the band, else a message explaining the fix. */
  SC.checkBand = (band, dobISO, refISO) => {
    if (!dobISO) return 'Enter a date of birth.';
    if (SC.parseDate(dobISO) > new Date()) return 'Date of birth can\'t be in the future.';
    const { years, months } = SC.ageAt(dobISO, refISO);
    const B = D.ageBands[band];
    if (band === 'adult' && years < B.minYears) return `Adults must be ${B.minYears} or older on the departure date. Change the date or go back and add this guest as a child.`;
    if (band === 'child' && (years < B.minYears || years > B.maxYears)) return `Children must be ${B.minYears}–${B.maxYears} years old on the departure date.`;
    if (band === 'infant' && (months < B.minMonths || months >= B.maxMonthsExclusive)) return 'Infants must be at least 6 months and under 2 years old on the departure date.';
    return null;
  };

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
    const available = SC.cabinsSorted().filter((cab) => !(s.soldOut || []).includes(cab.id));
    const fromPrice = Math.min(...available.map((cab) => SC.prices(c, s, cab.id).adult));
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
     4. PRICING ENGINE
     Each Sailing × Cabin has its own Adult / Child / Infant price.
     Total = Σ(price × qty) + fees − discount. Swap prices() for an API call.
     ------------------------------------------------------------------ */
  const r5 = (n) => Math.round(n / 5) * 5;
  SC.prices = (cruise, sailing, cabinId) => {
    const cab = SC.getCabin(cabinId);
    const k = cruise.nights * cruise.priceFactor * (sailing.factor || 1);
    return { adult: r5(cab.rates.adult * k), child: r5(cab.rates.child * k), infant: r5(cab.rates.infant * cruise.nights) };
  };
  SC.quote = ({ cruise, sailing, cabinId, guests, promoCode }) => {
    const prices = SC.prices(cruise, sailing, cabinId);
    const bands = ['adult', 'child', 'infant'];
    const lines = bands.filter((b) => guests[b] > 0).map((b) => ({
      band: b, label: D.ageBands[b].label, unit: prices[b], qty: guests[b], total: prices[b] * guests[b]
    }));
    const fare = lines.reduce((s, l) => s + l.total, 0);
    const fees = bands.reduce((s, b) => s + (guests[b] || 0) * D.fees.perGuestPerNight[b] * cruise.nights, 0);
    const promo = promoCode && D.promos[promoCode.toUpperCase()];
    const discount = promo ? r5(fare * promo.value / 100) : 0;
    return { prices, lines, fare, fees, discount, promo: promo ? { code: promoCode.toUpperCase(), ...promo } : null, total: fare + fees - discount, count: bands.reduce((s, b) => s + (guests[b] || 0), 0) };
  };
  SC.guestText = (g) => [g.adult && SC.plural(g.adult, 'adult'), g.child && SC.plural(g.child, 'child', 'children'), g.infant && SC.plural(g.infant, 'infant')].filter(Boolean).join(' + ');

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
  SC.favs = {
    all: () => SC.local.get('sc_favs', ['red-sea-discovery']),
    has(id) { return this.all().includes(id); },
    toggle(id) {
      const list = this.all(); const i = list.indexOf(id);
      i >= 0 ? list.splice(i, 1) : list.push(id);
      SC.local.set('sc_favs', list); return i < 0;
    }
  };
  SC.trips = { all: () => SC.local.get('sc_trips', []), add(t) { const l = this.all(); l.unshift(t); SC.local.set('sc_trips', l); } };

  /* ------------------------------------------------------------------
     7. LAYOUT — header, footer, mobile drawer, WhatsApp button
     Pages include <div id="site-header" data-active="home"></div>
     and <div id="site-footer"></div>; core.js fills them.
     ------------------------------------------------------------------ */
  const WA = 'https://wa.me/905016451111?text=' + encodeURIComponent('Hello, I would like to ask about AROYA cruises on Safer Cruise.');
  SC.whatsapp = (msg) => 'https://wa.me/905016451111?text=' + encodeURIComponent(msg || 'Hello, I would like to ask about AROYA cruises on Safer Cruise.');

  const NAV = [
    { id: 'home', label: 'Home', href: 'index.html' },
    { id: 'catalog', label: 'Cruises', href: 'catalog.html' },
    { id: 'ship', label: 'The ship', href: 'ship.html' },
    { id: 'destinations', label: 'Destinations', href: 'destinations.html' }
  ];

  const logo = (light) => `
    <a href="index.html" class="flex items-center gap-2.5 shrink-0" aria-label="Safer Cruise home">
      <span class="grid place-items-center w-10 h-10 rounded-xl ${light ? 'bg-white text-ocean-700' : 'bg-ocean-600 text-white'}">${SC.icon('ship', 'w-6 h-6')}</span>
      <span class="leading-none">
        <span class="font-display font-bold text-[1.15rem] ${light ? 'text-white' : 'text-ocean-800'}">Safer</span><span class="font-display font-bold text-[1.15rem] ${light ? 'text-lagoon-400' : 'text-lagoon-500'}">Cruise</span>
        <span class="block text-[11px] font-semibold mt-1 ${light ? 'text-ocean-200' : 'text-muted'}">by safer.travel</span>
      </span>
    </a>`;

  SC.renderHeader = () => {
    const host = SC.$('#site-header'); if (!host) return;
    const active = host.dataset.active;
    const link = (n) => `<a href="${n.href}" class="px-3.5 py-2 rounded-full text-[0.93rem] font-semibold transition ${n.id === active ? 'text-ocean-700 bg-ocean-50' : 'text-ink hover:text-ocean-600'}" ${n.id === active ? 'aria-current="page"' : ''}>${n.label}</a>`;
    host.outerHTML = `
    <header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-line">
      <div class="max-w-site mx-auto px-4 sm:px-6 h-[72px] flex items-center gap-4">
        ${logo(false)}
        <nav class="hidden lg:flex items-center gap-1 ms-6" aria-label="Main">
          ${NAV.map(link).join('')}
        </nav>
        <div class="ms-auto flex items-center gap-2">
          <button class="hidden sm:inline-flex btn btn-ghost btn-sm" data-dir-toggle title="Preview the right-to-left (Arabic) layout">عربي</button>
          <a href="dashboard.html" class="hidden md:inline-flex btn btn-ghost btn-sm ${active === 'dashboard' ? 'bg-ocean-50' : ''}">${SC.icon('ticket', 'w-4 h-4')} My bookings</a>
          <a href="dashboard.html#profile" class="hidden sm:inline-flex btn btn-primary btn-sm">${SC.icon('user', 'w-4 h-4')} Sign in</a>
          <button class="lg:hidden icon-btn" data-drawer-open="nav-drawer" aria-label="Open menu">${SC.icon('menu')}</button>
        </div>
      </div>
    </header>
    <div class="drawer" id="nav-drawer" aria-hidden="true">
      <div class="drawer-backdrop" data-drawer-close></div>
      <aside class="drawer-panel p-5 flex flex-col" role="dialog" aria-label="Menu">
        <div class="flex items-center justify-between mb-6">${logo(false)}<button class="icon-btn" data-drawer-close aria-label="Close menu">${SC.icon('x')}</button></div>
        <nav class="grid gap-1" aria-label="Mobile">
          ${NAV.map((n) => `<a href="${n.href}" class="side-link ${n.id === active ? 'is-active' : ''}">${n.label}</a>`).join('')}
          <a href="dashboard.html" class="side-link ${active === 'dashboard' ? 'is-active' : ''}">My bookings</a>
        </nav>
        <div class="mt-auto pt-6 grid gap-2">
          <a href="dashboard.html#profile" class="btn btn-primary">Sign in</a>
          <button class="btn btn-outline" data-dir-toggle>عربي (RTL preview)</button>
        </div>
      </aside>
    </div>`;
  };

  SC.renderFooter = () => {
    const host = SC.$('#site-footer'); if (!host) return;
    const col = (title, items) => `<div><h3 class="font-display font-semibold text-white mb-4">${title}</h3><ul class="grid gap-2.5 text-sm">${items.map(([l, h]) => `<li><a class="text-ocean-200 hover:text-white transition" href="${h}">${l}</a></li>`).join('')}</ul></div>`;
    host.outerHTML = `
    <footer class="bg-ocean-900 text-ocean-100 mt-auto">
      <div class="max-w-site mx-auto px-4 sm:px-6 py-14 grid gap-10 md:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
        <div>
          ${logo(true)}
          <p class="text-sm text-ocean-200 mt-5 max-w-sm leading-relaxed">Safer Cruise is the cruise booking site of Safar Tourism, an international travel agency licensed by the Ministry of Tourism (License No. 14079).</p>
          <a href="https://safer.travel" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 mt-4 text-sm font-bold text-lagoon-400 hover:text-white">Visit safer.travel ${SC.icon('external', 'w-4 h-4')}</a>
          <div class="flex gap-2 mt-5">
            ${['facebook', 'youtube', 'instagram'].map((s) => `<a href="https://www.${s}.com/${s === 'youtube' ? '@' : ''}SaferTourismOfficial" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-full border border-ocean-700 text-xs font-semibold text-ocean-100 hover:bg-ocean-800 capitalize">${s}</a>`).join('')}
          </div>
        </div>
        ${col('Explore', [['All cruises', 'catalog.html'], ['The ship', 'ship.html'], ['Destinations', 'destinations.html'], ['My bookings', 'dashboard.html']])}
        ${col('Help', [['Terms & conditions', '#terms'], ['Cancellation policy', '#cancellation']])}
        <div>
          <h3 class="font-display font-semibold text-white mb-4">Contact us</h3>
          <ul class="grid gap-3 text-sm text-ocean-200">
            <li class="flex gap-2.5">${SC.icon('phone', 'w-4 h-4 mt-0.5 shrink-0')}<span>Individuals: <a class="hover:text-white" href="tel:+905521501111">+90 552 150 1111</a></span></li>
            <li class="flex gap-2.5">${SC.icon('phone', 'w-4 h-4 mt-0.5 shrink-0')}<span>Companies: <a class="hover:text-white" href="tel:+905445019185">+90 544 501 9185</a></span></li>
            <li class="flex gap-2.5">${SC.icon('mail', 'w-4 h-4 mt-0.5 shrink-0')}<a class="hover:text-white" href="mailto:Info@Safer.Travel">Info@Safer.Travel</a></li>
            <li class="flex gap-2.5">${SC.icon('pin', 'w-4 h-4 mt-0.5 shrink-0')}<span>Watan Square, Al-Fateh, Istanbul, Turkey</span></li>
          </ul>
        </div>
      </div>
      <div class="border-t border-ocean-800">
        <div class="max-w-site mx-auto px-4 sm:px-6 py-5 flex flex-col sm:flex-row gap-3 items-center justify-between text-xs text-ocean-300">
          <p>© ${new Date().getFullYear()} Safer. All rights reserved.</p>
          <p>All prices in US dollars (USD).</p>
        </div>
      </div>
    </footer>
    <a id="wa-fab" href="${WA}" target="_blank" rel="noopener" class="fixed z-40 bottom-5 end-5 h-14 ps-4 pe-5 rounded-full bg-[#1FAF5A] text-white font-bold shadow-lg shadow-black/20 inline-flex items-center gap-2 hover:bg-[#178A47] transition" aria-label="Chat with us on WhatsApp">${SC.icon('chat', 'w-6 h-6')}<span class="hidden sm:inline">Chat with us</span></a>`;
  };

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
            <button class="icon-btn" data-modal-close aria-label="Close">${SC.icon('x')}</button>
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
      SC.toast(on ? 'Saved to your favourites' : 'Removed from favourites');
      document.dispatchEvent(new CustomEvent('sc:favs'));
      return;
    }

    // RTL preview
    if (t.closest('[data-dir-toggle]')) {
      const rtl = document.documentElement.dir !== 'rtl';
      document.documentElement.dir = rtl ? 'rtl' : 'ltr';
      document.documentElement.lang = rtl ? 'ar' : 'en';
      SC.local.set('sc_dir', rtl ? 'rtl' : 'ltr');
      SC.toast(rtl ? 'Right-to-left layout on (copy stays English in this demo)' : 'Left-to-right layout on');
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
    btn.setAttribute('aria-label', on ? 'Remove from favourites' : 'Save to favourites');
    btn.innerHTML = SC.icon('heart', 'w-5 h-5', on);
    btn.classList.toggle('text-rose-500', on); btn.classList.toggle('text-ocean-700', !on);
  };
  SC.ui.favBtn = (id, extra = '') => {
    const on = SC.favs.has(id);
    return `<button class="icon-btn ${on ? 'text-rose-500' : 'text-ocean-700'} ${extra}" data-fav="${id}" aria-pressed="${on}" aria-label="${on ? 'Remove from favourites' : 'Save to favourites'}">${SC.icon('heart', 'w-5 h-5', on)}</button>`;
  };
  SC.ui.stars = (rating, reviews) =>
    `<span class="inline-flex items-center gap-1 text-sm font-bold">${SC.icon('star', 'w-4 h-4 text-sun-500', true)}${rating.toFixed(1)}${reviews != null ? `<span class="font-medium text-muted">(${reviews})</span>` : ''}</span>`;
  SC.ui.route = (route, cls = '') =>
    `<p class="flex flex-wrap items-center gap-x-1 gap-y-0.5 text-sm text-muted ${cls}">${route.map((p, i) => `<span>${SC.esc(p.split(',')[0])}</span>${i < route.length - 1 ? SC.icon('chevron-right', 'w-3.5 h-3.5 text-ocean-300 rtl:rotate-180') : ''}`).join('')}</p>`;
  SC.ui.tags = (v) => [
    v.visaFree ? '<span class="tag tag-white">No visa needed</span>' : '',
    v.tags.includes('festive') ? '<span class="tag tag-white">Festive sailing</span>' : '',
    v.tags.includes('weekend') ? '<span class="tag tag-white">Weekend</span>' : ''
  ].join('');
  SC.detailsUrl = (v) => `details.html?cruise=${v.cruiseId}&sailing=${v.sailingId}`;

  /* Sailing card — one departure, shaped like a boarding pass.
     layout: 'grid' (stacked) | 'list' (horizontal from md up). perfBg = parent background. */
  SC.ui.sailingCard = (v, { layout = 'grid', perfBg = '#F5F8FB', alsoDates = [] } = {}) => {
    const also = alsoDates.length ? `<p class="mt-3 text-xs text-muted flex items-center gap-1.5">${SC.icon('calendar', 'w-3.5 h-3.5')} Also departs ${alsoDates.slice(0, 2).map((d) => SC.fmtDate(d, { day: 'numeric', month: 'short' })).join(', ')}${alsoDates.length > 2 ? ` and ${alsoDates.length - 2} more` : ''}</p>` : '';
    const dateCol = (label, val) => `<div><dt class="text-xs text-muted font-semibold">${label}</dt><dd class="font-bold text-[0.95rem] mt-0.5 tabular">${val}</dd></div>`;
    const dates = `<dl class="grid grid-cols-3 gap-3 mt-4">
        ${dateCol('Departs', SC.fmtDate(v.departure, { day: 'numeric', month: 'short' }))}
        ${dateCol('Returns', SC.fmtDate(v.returnDate, { day: 'numeric', month: 'short' }))}
        ${dateCol('Length', `${v.nights} nights`)}
      </dl>`;
    const priceBlock = `<div><p class="text-xs text-muted font-semibold">From, per person</p><p class="font-display font-bold text-xl text-ocean-800 tabular">${SC.money(v.fromPrice)}</p></div>`;
    const actions = `<div class="flex items-center gap-2">
        <a href="${SC.whatsapp(`Hello, I'm interested in ${v.name} departing ${SC.fmtDate(v.departure)}.`)}" target="_blank" rel="noopener" class="icon-btn" aria-label="Ask about this sailing on WhatsApp">${SC.icon('chat', 'w-5 h-5')}</a>
        <a href="${SC.detailsUrl(v)}" class="btn btn-accent btn-sm">Select sailing</a>
      </div>`;
    const media = `<div class="relative img-zoom ${layout === 'list' ? 'md:w-[38%] aspect-[16/10] md:aspect-auto' : 'aspect-[16/10]'}">
        <img src="${v.image}" alt="${SC.esc(v.name)} on ${v.ship}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
        <div class="absolute top-3 start-3 flex flex-wrap gap-1.5">${SC.ui.tags(v)}</div>
        <div class="absolute top-3 end-3">${SC.ui.favBtn(v.cruiseId, 'bg-white/95 border-transparent')}</div>
      </div>`;
    const body = `<p class="text-xs font-bold text-lagoon-600 flex items-center gap-1.5">${SC.icon('ship', 'w-4 h-4')} ${v.ship}, from ${v.departurePort}</p>
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
    const B = D.ageBands[band];
    return `<div class="flex items-center justify-between gap-4 py-3" data-counter="${band}">
      <div><p class="font-bold">${B.label}</p><p class="text-xs text-muted">${B.hint}</p></div>
      <div class="counter" role="group" aria-label="${B.label}s">
        <button type="button" data-step="-1" aria-label="Fewer ${B.label.toLowerCase()}s" ${value <= min ? 'disabled' : ''}>${SC.icon('minus', 'w-4 h-4')}</button>
        <output aria-live="polite">${value}</output>
        <button type="button" data-step="1" aria-label="More ${B.label.toLowerCase()}s" ${value >= max ? 'disabled' : ''}>${SC.icon('plus', 'w-4 h-4')}</button>
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
    const body = SC.ensureModal('terms-modal', { title: 'Terms & conditions and cancellation policy', width: '680px' });
    body.innerHTML = `
      <section id="terms-sec" class="scroll-mt-24">
        <h3 class="font-display font-semibold text-lg mb-3">Terms & conditions</h3>
        <div class="grid gap-3 text-[0.95rem] leading-relaxed text-ink/90">${D.terms.text.map((p) => `<p>${p}</p>`).join('')}</div>
      </section>
      <section id="cancel-sec" class="mt-8 scroll-mt-24">
        <h3 class="font-display font-semibold text-lg mb-1">Cancellation policy</h3>
        <p class="text-sm text-muted mb-4">Penalties are calculated from the number of days before departure.</p>
        <div class="overflow-x-auto"><table class="w-full text-sm border border-line rounded-xl overflow-hidden">
          <thead class="bg-ocean-50 text-ocean-800"><tr><th class="text-start p-3">When you cancel</th><th class="text-start p-3">Penalty</th></tr></thead>
          <tbody>${D.terms.cancellation.map(([w, p]) => `<tr class="border-t border-line"><td class="p-3">${w}</td><td class="p-3 font-bold">${p}</td></tr>`).join('')}</tbody>
        </table></div>
      </section>
      <div class="mt-8 flex justify-end"><button class="btn btn-primary" data-modal-close>Back to my booking</button></div>`;
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
        <div class="flex gap-2 overflow-x-auto no-scrollbar" role="tablist" aria-label="Cabin photos">
          ${c.images.map((src, i) => `<button class="shrink-0 w-24 h-16 rounded-xl overflow-hidden border-2 ${i === 0 ? 'border-ocean-500' : 'border-transparent'}" data-cab-thumb="${i}" aria-label="Photo ${i + 1}"><img src="${src}" alt="" class="w-full h-full object-cover"></button>`).join('')}
        </div>
      </div>
      <div class="mt-6 flex flex-wrap items-baseline justify-between gap-2">
        <div><p class="font-display font-semibold text-xl">${c.nameEn}</p><p class="text-muted" lang="ar" dir="rtl">${c.nameAr}</p></div>
        ${c.balcony ? `<span class="tag tag-lagoon">${SC.icon('sun', 'w-4 h-4')} Private balcony</span>` : '<span class="tag tag-ocean">No balcony</span>'}
      </div>
      <p class="mt-3 leading-relaxed text-ink/90 max-w-[70ch]">${c.description}</p>
      <div class="grid sm:grid-cols-2 gap-4 mt-6">
        ${spec('users', 'Maximum guests', `Up to ${c.maxGuests}`)}
        ${spec('size', 'Cabin size', c.size + (c.balcony ? ` + ${c.balcony} balcony` : ''))}
        ${spec('bed', 'Beds', c.beds)}
        ${spec('home', 'Bedroom and lounge', c.bedroom)}
      </div>
      <h4 class="font-display font-semibold mt-7 mb-3">In the cabin</h4>
      <ul class="grid sm:grid-cols-2 gap-2.5">${c.features.map((f) => `<li class="flex items-center gap-2.5 text-sm">${SC.icon('check', 'w-4 h-4 text-lagoon-600')}${f}</li>`).join('')}</ul>
      ${onSelect ? `<div class="mt-8 flex justify-end gap-2"><button class="btn btn-outline" data-modal-close>Close</button><button class="btn btn-accent" id="cab-modal-select">Choose this cabin</button></div>` : ''}`;
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
  if (SC.local.get('sc_dir', 'ltr') === 'rtl') { document.documentElement.dir = 'rtl'; document.documentElement.lang = 'ar'; }
  document.addEventListener('DOMContentLoaded', () => {
    SC.renderHeader();
    SC.renderFooter();
    SC.hydrateIcons();
    document.dispatchEvent(new CustomEvent('sc:ready'));
  });
})();
