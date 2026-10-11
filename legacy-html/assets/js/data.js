/* =====================================================================
   Safer Cruise — mock data layer
   ---------------------------------------------------------------------
   Everything here stands in for API responses from the admin panel.
   Shape follows the project docs:
     • Ships → Cruises → Sailings (departures)
     • Prices per Sailing × Cabin category × Age band (Adult/Child/Infant)
     • Age bands and fees are config, not hard-coded in the UI
   Replace SC_DATA with fetch() calls when the backend is ready.
   All ship facts and prices below are SAMPLE values — verify them.
   Images are local SVG illustrations in assets/img; replace with photos.
   ===================================================================== */
(function () {
  /* ------------------------------------------------------------------
     IMAGES — three layers, tried in order:
       1. Official AROYA photo in assets/img/aroya/<slot>.jpg  (drop files here)
       2. Royalty-free stock photo (cabins only, Pixabay licence)
       3. Local SVG illustration (always available)
     core.js walks this chain automatically when an image fails to load.
     Open photos.html to see which slots are filled.
     ------------------------------------------------------------------ */
  const svg = (name) => `assets/img/${name}.svg`;
  // Real photos — Pexels & Pixabay licences: free for commercial use, no attribution required.
  const pex = (id, w = 1600) => `https://images.pexels.com/photos/${id}/pexels-photo-${id}.jpeg?auto=compress&cs=tinysrgb&w=${w}`;
  const PHOTO = {
    // Cruise ships, decks and pools (Pexels)
    shipSunset:    pex(32609062, 2200), // cruise ship on open ocean at sunset
    shipBlueSky:   pex(35192391),       // cruise ship at sea, clear blue sky
    shipDusk:      pex(24244288),       // illuminated cruise ship at dusk
    shipAerial:    pex(2852124),        // aerial view of a cruise ship and pool deck
    shipDrone:     pex(6746785),        // drone view of a white cruise ship on blue sea
    shipDroneDusk: pex(6746784),        // aerial view of a cruise ship at sunset
    shipIsland:    pex(813036),         // cruise ship near a turquoise island
    deckSunset:    pex(5769604),        // sunset from a cruise ship deck
    deckPool:      pex(32207991),       // pool deck with sunbathers
    poolsAbove:    pex(12530456),       // pools, slides and lounges from above
    deckCouple:    pex(39954521),       // couple relaxing on deck
    deckOcean:     pex(5045674),        // ocean view from the deck
    balconies:     pex(12260932),       // guests on cruise-ship balconies
    blueWater:     pex(34014112),       // boat on turquoise water
    // Destinations (Pexels)
    pyramids:      pex(32636854),       // Pyramids of Giza
    petra:         pex(28617385),       // The Treasury, Petra
    // Real cruise cabins (Pixabay)
    stateroomBed:  'https://cdn.pixabay.com/photo/2019/10/23/21/03/cruise-4572829_1280.jpg',
    stateroomSofa: 'https://cdn.pixabay.com/photo/2019/10/24/22/25/cruise-4575486_1280.jpg',
    cabinRoom:     'https://cdn.pixabay.com/photo/2017/08/16/18/50/cruise-2648839_1280.jpg',
    cabinBalcony:  'https://cdn.pixabay.com/photo/2018/07/09/16/56/balcony-3526535_1280.jpg'
  };
  const FALLBACKS = {};
  const SLOTS = []; // listed on photos.html
  // slot(): AROYA photo (if dropped in assets/img/aroya) → real stock photo → local SVG
  const slot = (name, photo, local, label, size) => {
    const src = `assets/img/aroya/${name}.jpg`;
    FALLBACKS[src] = photo; if (!FALLBACKS[photo]) FALLBACKS[photo] = local;
    SLOTS.push({ name: `${name}.jpg`, label, size, src, fallback: photo });
    return src;
  };

  const IMG = {
    shipHero:  slot('hero', PHOTO.shipSunset, svg('ship-hero'), 'Homepage and ship page hero: AROYA at sea, wide', '2400 × 1350'),
    shipSea:   slot('exterior', PHOTO.shipBlueSky, svg('ship-sea'), 'AROYA side view, daytime', '1600 × 1000'),
    shipDeck:  slot('sunset', PHOTO.deckSunset, svg('ship-deck'), 'Sunset from the deck', '1600 × 1000'),
    shipNight: slot('night', PHOTO.shipDusk, svg('ship-night'), 'AROYA lit up at night', '1600 × 1000'),
    aerial:    slot('aerial', PHOTO.shipAerial, svg('ship-sea'), 'AROYA from above', '1600 × 1000'),
    pool:      slot('pool', PHOTO.poolsAbove, svg('pool'), 'Pools and water park', '1600 × 1000'),
    sundeck:   slot('sundeck', PHOTO.deckPool, svg('pool'), 'Sun deck by the pool', '1600 × 1000'),
    lounge:    slot('lounge', PHOTO.deckCouple, svg('ship-deck'), 'Relaxing on deck', '1600 × 1000'),
    city:      slot('dest-jeddah', PHOTO.shipDrone, svg('city'), 'Destination: Jeddah', '1600 × 1200'),
    coast:     slot('dest-yanbu', PHOTO.shipIsland, svg('coast'), 'Destination: Yanbu', '1600 × 1200'),
    beach:     slot('dest-al-wajh', PHOTO.blueWater, svg('beach'), 'Destination: Al Wajh', '1600 × 1200'),
    reef:      slot('dest-safaga', PHOTO.shipDroneDusk, svg('reef'), 'Destination: Safaga', '1600 × 1200'),
    desert:    slot('dest-sokhna', PHOTO.pyramids, svg('desert'), 'Destination: Sokhna (Pyramids)', '1600 × 1200'),
    petra:     slot('dest-aqaba', PHOTO.petra, svg('petra'), 'Destination: Aqaba (Petra)', '1600 × 1200'),
    interior1: slot('cabin-interior-1', PHOTO.cabinRoom, svg('cabin-interior-1'), 'Interior Cabin: main photo', '1600 × 1000'),
    interior2: slot('cabin-interior-2', PHOTO.stateroomSofa, svg('cabin-interior-2'), 'Interior Cabin: second photo', '1600 × 1000'),
    balcony1:  slot('cabin-balcony-1', PHOTO.stateroomBed, svg('cabin-balcony-1'), 'Balcony Cabin: main photo', '1600 × 1000'),
    balcony2:  slot('cabin-balcony-2', PHOTO.cabinBalcony, svg('cabin-balcony-2'), 'Balcony Cabin: the balcony', '1600 × 1000'),
    balcony3:  slot('cabin-balcony-3', PHOTO.balconies, svg('cabin-balcony-2'), 'Balcony Cabin: balconies from outside', '1600 × 1000'),
    suite1:    slot('cabin-suite-1', PHOTO.stateroomSofa, svg('cabin-suite-1'), 'Superior Suite Khuzama: lounge', '1600 × 1000'),
    suite2:    slot('cabin-suite-2', PHOTO.stateroomBed, svg('cabin-suite-2'), 'Superior Suite Khuzama: bedroom', '1600 × 1000'),
    suite3:    slot('cabin-suite-3', PHOTO.cabinBalcony, svg('cabin-suite-3'), 'Superior Suite Khuzama: balcony', '1600 × 1000')
  };

  /* Exposed so pages can reach scene images directly (e.g. the hero). */
  const _IMG = IMG;

  window.SC_DATA = {
    currency: 'USD',
    currencySymbol: '$',
    images: _IMG,
    imageSlots: SLOTS,
    imageFallbacks: FALLBACKS,

    /* Age bands (doc 3 & 4). Ages are checked against the DEPARTURE date. */
    ageBands: {
      adult:  { label: 'Adult',  labelAr: 'بالغ', hint: '12 years and over', minYears: 12 },
      child:  { label: 'Child',  labelAr: 'طفل',  hint: '2 to 11 years',     minYears: 2, maxYears: 11 },
      infant: { label: 'Infant', labelAr: 'رضيع', hint: '6 months to under 2 years', minMonths: 6, maxMonthsExclusive: 24 }
    },

    /* Port charges, taxes & fees — per guest, per night. Infants exempt. */
    fees: { perGuestPerNight: { adult: 35, child: 35, infant: 0 } },

    /* Sample promo codes (validate server-side in production). */
    promos: { SAFER10: { type: 'percent', value: 10, label: '10% off cruise fare' } },

    ships: [{
      id: 'aroya', name: 'AROYA', image: IMG.shipSea,
      tagline: 'Saudi Arabia\'s first cruise ship',
      story: [
        'AROYA is the first ship of Aroya Cruises, the Saudi cruise line launched to bring ocean holidays to families across the region. She sails from Jeddah and Yanbu on short breaks and week-long voyages around the Red Sea.',
        'Everything on board is designed for Arab guests: all food is halal, there are prayer rooms on several decks, family and adults-only pool areas are separate, and entertainment runs in Arabic and English.'
      ],
      zones: [
        { icon: 'waves', title: 'Pools & water park', image: IMG.pool, text: 'Family pools, a separate adults-only pool deck, and an aqua park with slides and a splash zone for younger children.' },
        { icon: 'sun', title: 'Sun decks', image: IMG.sundeck, text: 'Wide open decks with loungers, shaded cabanas and bars serving fresh juices and Saudi coffee.' },
        { icon: 'utensils', title: 'Dining with a view', image: IMG.shipDeck, text: 'Arabic, Asian, Italian and grill restaurants, all halal. Breakfast, lunch and dinner in the main restaurants are included in your fare.' },
        { icon: 'music', title: 'Evenings on board', image: IMG.shipNight, text: 'Nightly shows in the main theatre, live music lounges and family performances in Arabic and English.' }
      ],
      essentials: [
        ['utensils', 'Halal dining', 'All food served on board is halal.'],
        ['home', 'Prayer rooms', 'Prayer rooms on several decks, with prayer times announced daily.'],
        ['smile', 'Family first', 'Kids clubs by age group and family cabins for up to four guests.'],
        ['card', 'Cashless on board', 'Link a card at check-in and pay with your cruise card.'],
        ['globe', 'Wi-Fi packages', 'Internet packages can be bought on board.'],
        ['pulse', 'Medical centre', 'Doctors and nurses on call 24 hours a day.']
      ],
      faqs: [
        ['What should I bring to check-in?', 'A passport valid for at least six months, or a Saudi national ID for domestic-only sailings, plus your booking reference.'],
        ['Is there a dress code?', 'Smart-casual in the evenings. Modest swimwear is welcome in all pool areas.'],
        ['Can I bring my own food or drinks?', 'Sealed baby food and medical items are allowed. Other food and drinks are provided on board.'],
        ['Is the ship accessible?', 'Yes. Accessible cabins and lifts serve all public decks. Tell us about any needs when you book.']
      ],
      specs: [
        { key: 'tonnage',    value: '151,300', unit: 'GT',      label: 'Gross tonnage' },
        { key: 'cabins',     value: '1,678',   unit: '',        label: 'Cabins' },
        { key: 'length',     value: '335',     unit: 'm',       label: 'Length' },
        { key: 'speed',      value: '23.8',    unit: 'knots',   label: 'Top speed' },
        { key: 'passengers', value: '3,352',   unit: '',        label: 'Guest capacity' },
        { key: 'crew',       value: '2,000',   unit: '',        label: 'Crew members' },
        { key: 'decks',      value: '18',      unit: '',        label: 'Decks' }
      ]
    }],

    /* The three bookable categories (doc 3). Per-night rates feed pricing. */
    cabins: [
      {
        id: 'interior', order: 1, badge: 'Best value',
        nameEn: 'Interior Cabin', nameAr: 'كابينة داخلية',
        short: 'A quiet, well-planned cabin for guests who spend their days on deck.',
        description: 'Interior cabins sit on the inside of the ship, so they stay dark and quiet for a good night\'s sleep. Twin beds convert to a queen, and a sofa bed sleeps a third guest.',
        maxGuests: 3, size: '14–17 m²', balcony: null,
        beds: 'Twin beds convertible to a queen, plus sofa bed',
        features: ['Private bathroom with shower', 'Smart TV', 'In-room safe', 'Air conditioning', 'Daily housekeeping', 'Hairdryer'],
        images: [IMG.interior1, IMG.interior2],
        rates: { adult: 330, child: 170, infant: 45 }
      },
      {
        id: 'balcony', order: 2, badge: '',
        nameEn: 'Balcony Cabin', nameAr: 'كابينة بلكونة',
        short: 'Your own private balcony for sunrise coffee over the Red Sea.',
        description: 'Floor-to-ceiling glass opens onto a private balcony with two chairs and a small table. Room for up to four guests with a queen bed and a sofa bed.',
        maxGuests: 4, size: '20 m²', balcony: '5 m²',
        beds: 'Queen bed (or twins), plus double sofa bed',
        features: ['Private balcony', 'Sitting area', 'Smart TV', 'Mini fridge', 'In-room safe', 'Daily housekeeping'],
        images: [IMG.balcony1, IMG.balcony2, IMG.balcony3],
        rates: { adult: 520, child: 240, infant: 45 }
      },
      {
        id: 'suite', order: 3, badge: 'Most space',
        nameEn: 'Superior Suite Khuzama', nameAr: 'سوبيريور سويت خزامى',
        short: 'A separate bedroom, lounge and wide balcony with priority services.',
        description: 'The Khuzama suite has a separate bedroom and a lounge with a dining table, a full bathroom with bathtub and a wide private balcony. Suite guests get priority embarkation and a dedicated reception desk.',
        maxGuests: 4, size: '38 m²', balcony: '10 m²',
        beds: 'King bed in bedroom, double sofa bed in lounge',
        bedroom: 'Separate bedroom with wardrobe; lounge with sofa bed and dining table for four.',
        features: ['Private balcony', 'Separate bedroom', 'Bathtub and rain shower', 'Priority embarkation', 'Suite reception', 'Coffee machine', 'Welcome fruit and drinks'],
        images: [IMG.suite1, IMG.suite2, IMG.suite3],
        rates: { adult: 1150, child: 380, infant: 45 }
      }
    ],

    /* Cruises with their sailings (departures). Return date = departure + nights. */
    cruises: [
      {
        id: 'red-sea-discovery', shipId: 'aroya', name: 'Red Sea Discovery', nameAr: 'اكتشاف البحر الأحمر',
        nights: 7, departurePort: 'Jeddah', arrivalPort: 'Jeddah',
        route: ['Jeddah', 'Safaga', 'Sokhna', 'Al Wajh', 'Yanbu', 'Jeddah'],
        tags: ['family', 'excursions'], visaFree: false, rating: 4.8, reviews: 412, popularity: 96, priceFactor: 1.0,
        image: IMG.shipHero, gallery: [IMG.shipHero, IMG.pool, IMG.desert, IMG.sundeck, IMG.shipDeck],
        summary: 'A full week across both shores of the Red Sea, with day trips to Luxor and Cairo from the Egyptian ports and two Saudi coastal stops on the way home.',
        itinerary: [
          { port: 'Jeddah', time: 'Departs 20:00', text: 'Check in at Jeddah Islamic Port from 14:00. Settle into your cabin and join the sail-away on the pool deck.' },
          { port: 'At sea', time: 'Full day', text: 'Spa, water park, kids club and the evening show in the main theatre.' },
          { port: 'Safaga, Egypt', time: '07:00 – 19:00', text: 'Optional excursion to Luxor\'s Karnak Temple and the Valley of the Kings, or snorkel the house reef.' },
          { port: 'Sokhna, Egypt', time: '06:00 – 21:00', text: 'Gateway to Cairo. Optional full-day tour to the Giza Pyramids and the Grand Egyptian Museum.' },
          { port: 'Al Wajh, Saudi Arabia', time: '10:00 – 18:00', text: 'Turquoise lagoons and quiet beaches on the northern Saudi coast.' },
          { port: 'Yanbu, Saudi Arabia', time: '08:00 – 17:00', text: 'Walk Yanbu\'s historic old town or spend the day at a beach club.' },
          { port: 'At sea', time: 'Full day', text: 'Captain\'s farewell dinner and the closing night show.' },
          { port: 'Jeddah', time: 'Arrives 07:00', text: 'Disembarkation from 08:00, by deck in a scheduled order.' }
        ],
        sailings: [
          { id: 'RSD-261115', departure: '2026-11-15', factor: 1.0 },
          { id: 'RSD-261213', departure: '2026-12-13', factor: 1.1 },
          { id: 'RSD-270117', departure: '2027-01-17', factor: 1.0, soldOut: ['suite'] },
          { id: 'RSD-270214', departure: '2027-02-14', factor: 1.05 }
        ]
      },
      {
        id: 'saudi-coast-weekend', shipId: 'aroya', name: 'Saudi Coast Weekend', nameAr: 'عطلة الساحل السعودي',
        nights: 3, departurePort: 'Jeddah', arrivalPort: 'Jeddah',
        route: ['Jeddah', 'Yanbu', 'Jeddah'],
        tags: ['weekend', 'family'], visaFree: true, rating: 4.6, reviews: 288, popularity: 92, priceFactor: 1.0,
        image: IMG.aerial, gallery: [IMG.aerial, IMG.sundeck, IMG.lounge, IMG.shipSea],
        summary: 'A short Thursday-to-Sunday escape with one port day in Yanbu. No visa needed for Saudi residents and GCC citizens.',
        itinerary: [
          { port: 'Jeddah', time: 'Departs 20:00', text: 'Embark from 14:00 and welcome dinner on board.' },
          { port: 'Yanbu, Saudi Arabia', time: '09:00 – 18:00', text: 'Beach day, diving or a walk through the old town.' },
          { port: 'At sea', time: 'Full day', text: 'Pools, water park and a family show in the evening.' },
          { port: 'Jeddah', time: 'Arrives 07:00', text: 'Disembarkation from 08:00.' }
        ],
        sailings: [
          { id: 'SCW-261029', departure: '2026-10-29', factor: 0.95, soldOut: ['suite'] },
          { id: 'SCW-261105', departure: '2026-11-05', factor: 1.0 },
          { id: 'SCW-261126', departure: '2026-11-26', factor: 1.0 },
          { id: 'SCW-261203', departure: '2026-12-03', factor: 1.05 },
          { id: 'SCW-270107', departure: '2027-01-07', factor: 0.95 }
        ]
      },
      {
        id: 'egypt-jordan-voyage', shipId: 'aroya', name: 'Egypt & Jordan Voyage', nameAr: 'رحلة مصر والأردن',
        nights: 5, departurePort: 'Jeddah', arrivalPort: 'Jeddah',
        route: ['Jeddah', 'Aqaba', 'Safaga', 'Jeddah'],
        tags: ['excursions'], visaFree: false, rating: 4.7, reviews: 196, popularity: 84, priceFactor: 1.08,
        image: IMG.petra, gallery: [IMG.petra, IMG.desert, IMG.shipDeck, IMG.pool],
        summary: 'Petra and Wadi Rum from Aqaba, then Luxor or the reefs from Safaga. Two countries in five nights.',
        itinerary: [
          { port: 'Jeddah', time: 'Departs 20:00', text: 'Embark from 14:00.' },
          { port: 'At sea', time: 'Full day', text: 'Relax on board as the ship heads north through the Red Sea.' },
          { port: 'Aqaba, Jordan', time: '07:00 – 20:00', text: 'Optional excursion to Petra or a jeep tour in Wadi Rum.' },
          { port: 'Safaga, Egypt', time: '08:00 – 19:00', text: 'Optional Luxor day tour or a glass-bottom boat over the reef.' },
          { port: 'At sea', time: 'Full day', text: 'Farewell gala dinner.' },
          { port: 'Jeddah', time: 'Arrives 07:00', text: 'Disembarkation from 08:00.' }
        ],
        sailings: [
          { id: 'EJV-261120', departure: '2026-11-20', factor: 1.0 },
          { id: 'EJV-261218', departure: '2026-12-18', factor: 1.12 },
          { id: 'EJV-270129', departure: '2027-01-29', factor: 1.0 }
        ]
      },
      {
        id: 'aroya-repositioning', shipId: 'aroya', name: 'AROYA – Repositioning', nameAr: 'أرويا – رحلة إعادة التموضع',
        nights: 5, departurePort: 'Jeddah', arrivalPort: 'Sokhna',
        route: ['Jeddah', 'Safaga', 'Sokhna'],
        tags: ['excursions'], visaFree: false, rating: 4.5, reviews: 74, popularity: 61, priceFactor: 0.82,
        image: IMG.shipSea, gallery: [IMG.shipSea, IMG.shipNight, IMG.desert],
        summary: 'A one-way sailing as AROYA moves north for the season. Lower fares and quieter decks; ends in Sokhna, 90 minutes from Cairo.',
        itinerary: [
          { port: 'Jeddah', time: 'Departs 19:00', text: 'Embark from 13:00.' },
          { port: 'At sea', time: 'Full day', text: 'Sea day with full entertainment programme.' },
          { port: 'Safaga, Egypt', time: '08:00 – 18:00', text: 'Optional Luxor excursion.' },
          { port: 'At sea', time: 'Full day', text: 'Cruise the Gulf of Suez.' },
          { port: 'At sea', time: 'Full day', text: 'Farewell evening on deck.' },
          { port: 'Sokhna, Egypt', time: 'Arrives 07:00', text: 'Disembarkation from 08:00. Transfers to Cairo can be arranged.' }
        ],
        sailings: [
          { id: 'ARP-270404', departure: '2027-04-04', factor: 1.0 }
        ]
      },
      {
        id: 'yanbu-winter-escape', shipId: 'aroya', name: 'Winter Escape from Yanbu', nameAr: 'هروب الشتاء من ينبع',
        nights: 4, departurePort: 'Yanbu', arrivalPort: 'Yanbu',
        route: ['Yanbu', 'Al Wajh', 'Jeddah', 'Yanbu'],
        tags: ['family'], visaFree: true, rating: 4.6, reviews: 131, popularity: 74, priceFactor: 0.95,
        image: IMG.coast, gallery: [IMG.coast, IMG.beach, IMG.pool, IMG.lounge],
        summary: 'Start from Yanbu instead of Jeddah, with a lagoon day in Al Wajh and an evening in Jeddah\'s Al-Balad.',
        itinerary: [
          { port: 'Yanbu', time: 'Departs 19:00', text: 'Embark from 13:00.' },
          { port: 'Al Wajh, Saudi Arabia', time: '09:00 – 18:00', text: 'Lagoon swimming and beach clubs.' },
          { port: 'At sea', time: 'Full day', text: 'Spa day and family entertainment.' },
          { port: 'Jeddah, Saudi Arabia', time: '12:00 – 23:00', text: 'Evening in historic Al-Balad.' },
          { port: 'Yanbu', time: 'Arrives 08:00', text: 'Disembarkation from 09:00.' }
        ],
        sailings: [
          { id: 'YWE-261210', departure: '2026-12-10', factor: 1.0 },
          { id: 'YWE-270121', departure: '2027-01-21', factor: 0.95 },
          { id: 'YWE-270225', departure: '2027-02-25', factor: 1.0 }
        ]
      },
      {
        id: 'new-year-at-sea', shipId: 'aroya', name: 'New Year at Sea', nameAr: 'رأس السنة في البحر',
        nights: 4, departurePort: 'Jeddah', arrivalPort: 'Jeddah',
        route: ['Jeddah', 'Yanbu', 'Al Wajh', 'Jeddah'],
        tags: ['festive', 'family'], visaFree: true, rating: 4.9, reviews: 158, popularity: 99, priceFactor: 1.35,
        image: IMG.shipNight, gallery: [IMG.shipNight, IMG.shipDeck, IMG.pool, IMG.sundeck],
        summary: 'Count down to 2027 on the open deck, with a gala dinner, fireworks and a late-night show.',
        itinerary: [
          { port: 'Jeddah', time: 'Departs 20:00', text: 'Embark from 14:00.' },
          { port: 'Yanbu, Saudi Arabia', time: '09:00 – 18:00', text: 'Beach day ashore.' },
          { port: 'At sea', time: 'Full day', text: 'New Year\'s Eve gala dinner and midnight fireworks.' },
          { port: 'Al Wajh, Saudi Arabia', time: '11:00 – 18:00', text: 'A slow start to the new year in the lagoons.' },
          { port: 'Jeddah', time: 'Arrives 07:00', text: 'Disembarkation from 08:00.' }
        ],
        sailings: [
          { id: 'NYS-261229', departure: '2026-12-29', factor: 1.0, soldOut: ['balcony'] }
        ]
      }
    ],

    destinations: [
      { slug: 'jeddah', name: 'Jeddah', nameAr: 'جدة', country: 'Saudi Arabia', image: IMG.city, blurb: 'Historic Al-Balad and the Corniche.',
        description: 'AROYA\'s home port and the start of most sailings. Arrive a day early to walk the coral-stone houses of Al-Balad, a UNESCO World Heritage Site, and watch the King Fahd Fountain light up the Corniche at night.',
        highlights: ['Al-Balad historic district', 'Jeddah Corniche and King Fahd Fountain', 'Red Sea Mall and waterfront dining'], visa: 'Home port. Saudi residents and GCC citizens sail with a national ID.' },
      { slug: 'yanbu', name: 'Yanbu', nameAr: 'ينبع', country: 'Saudi Arabia', image: IMG.coast, blurb: 'Old town lanes and reef diving.',
        description: 'A quiet coastal town with a restored old quarter and some of the clearest water on the Saudi coast. Most guests split the day between the souq and a beach club.',
        highlights: ['Yanbu Old Town and souq', 'Snorkelling and diving trips', 'Beach clubs on the Yanbu Corniche'], visa: 'Domestic port. No visa needed for Saudi residents and GCC citizens.' },
      { slug: 'al-wajh', name: 'Al Wajh', nameAr: 'الوجه', country: 'Saudi Arabia', image: IMG.beach, blurb: 'Turquoise lagoons and empty beaches.',
        description: 'Gateway to the northern Red Sea coast, with shallow turquoise lagoons, sandbanks and small islands. A slow, beach-first port day.',
        highlights: ['Lagoon swimming and sandbank trips', 'Boat tours to nearby islands', 'Al Wajh old town'], visa: 'Domestic port. No visa needed for Saudi residents and GCC citizens.' },
      { slug: 'safaga', name: 'Safaga', nameAr: 'سفاجا', country: 'Egypt', image: IMG.reef, blurb: 'Gateway to Luxor and the Valley of the Kings.',
        description: 'Safaga is the closest port to Luxor. Take the full-day excursion to Karnak Temple and the Valley of the Kings, or stay by the sea for some of the best reefs in the Red Sea.',
        highlights: ['Luxor: Karnak Temple and Valley of the Kings', 'Glass-bottom boat and snorkelling trips', 'Soma Bay beaches'], visa: 'Egypt visa rules apply. Many nationalities can get a visa on arrival; check before you book.' },
      { slug: 'sokhna', name: 'Sokhna', nameAr: 'السخنة', country: 'Egypt', image: IMG.desert, blurb: 'Gateway to Cairo and the Pyramids.',
        description: 'Ain Sokhna sits about 90 minutes from Cairo. The full-day tour takes you to the Giza Pyramids, the Sphinx and the Grand Egyptian Museum.',
        highlights: ['Giza Pyramids and the Sphinx', 'Grand Egyptian Museum', 'Galala mountain viewpoints'], visa: 'Egypt visa rules apply. Many nationalities can get a visa on arrival; check before you book.' },
      { slug: 'aqaba', name: 'Aqaba', nameAr: 'العقبة', country: 'Jordan', image: IMG.petra, blurb: 'Gateway to Petra and Wadi Rum.',
        description: 'Jordan\'s only port, two hours from Petra. Choose the Petra excursion to see the Treasury and the Monastery, or a jeep tour through the red desert of Wadi Rum.',
        highlights: ['Petra: the Treasury and the Siq', 'Wadi Rum jeep safari', 'Aqaba Marine Park'], visa: 'Jordan visa rules apply. The Jordan Pass covers the visa and Petra entry for many nationalities.' }
    ],

    facilities: [
      { icon: 'utensils', value: '35+', label: 'Restaurants', text: 'Arabic, Asian, Italian and grill venues, all serving halal food.' },
      { icon: 'coffee',   value: '20+', label: 'Cafés and lounges', text: 'Specialty coffee, tea lounges and dessert bars.' },
      { icon: 'music',    value: '1,000', label: 'Theatre seats', text: 'Nightly shows, live music and family performances.' },
      { icon: 'smile',    value: '1,200 m²', label: 'Kids zone', text: 'Supervised clubs by age group, from 3 to 17 years.' },
      { icon: 'droplet',  value: '6', label: 'Water slides', text: 'An aqua park with splash zones for younger children.' },
      { icon: 'waves',    value: '4', label: 'Pools', text: 'Family pools plus a separate adults-only pool deck.' },
      { icon: 'flower',   value: '2,000 m²', label: 'Spa & wellness', text: 'Treatment rooms, hammam, sauna and a full gym.' },
      { icon: 'bag',      value: '25', label: 'Shops', text: 'Duty-free fashion, perfume, jewellery and gifts.' },
      { icon: 'crown',    value: 'VIP', label: 'Private areas', text: 'Suite-only lounges, restaurant and sun deck.' }
    ],

    testimonials: [
      { name: 'Reem A.', from: 'Riyadh', cruise: 'Red Sea Discovery', rating: 5, text: 'Booking took ten minutes and the kids club kept our two busy all week. The Luxor day from Safaga was the highlight.' },
      { name: 'Mahmoud K.', from: 'Cairo', cruise: 'Egypt & Jordan Voyage', rating: 5, text: 'Clear prices for each of us, no surprises at check-in. Petra from Aqaba was worth the early start.' },
      { name: 'Sara & Omar', from: 'Jeddah', cruise: 'Saudi Coast Weekend', rating: 4, text: 'A perfect long weekend without flights. We upgraded to a balcony cabin and would do it again.' }
    ],

    nationalities: ['Saudi Arabia', 'Egypt', 'United Arab Emirates', 'Kuwait', 'Qatar', 'Bahrain', 'Oman', 'Jordan', 'Lebanon', 'Syria', 'Iraq', 'Palestine', 'Yemen', 'Sudan', 'Libya', 'Tunisia', 'Algeria', 'Morocco', 'Turkey', 'Pakistan', 'India', 'Bangladesh', 'Indonesia', 'Malaysia', 'Philippines', 'United Kingdom', 'United States', 'Canada', 'Germany', 'France', 'Italy', 'Spain', 'Netherlands', 'Russia', 'China', 'Other'],

    dialCodes: [
      ['+966', 'SA'], ['+20', 'EG'], ['+971', 'AE'], ['+965', 'KW'], ['+974', 'QA'], ['+973', 'BH'], ['+968', 'OM'],
      ['+962', 'JO'], ['+961', 'LB'], ['+964', 'IQ'], ['+970', 'PS'], ['+967', 'YE'], ['+249', 'SD'], ['+218', 'LY'],
      ['+216', 'TN'], ['+213', 'DZ'], ['+212', 'MA'], ['+90', 'TR'], ['+92', 'PK'], ['+91', 'IN'], ['+44', 'GB'], ['+1', 'US'], ['+49', 'DE'], ['+33', 'FR']
    ],

    /* Terms & cancellation policy (doc 6). Two lines in the source doc were in
       Turkish; translated here: "121 güne kadar cezasız" = no penalty,
       "Toplam depozito bedeli" = full deposit amount. */
    terms: {
      text: [
        'By completing this reservation you agree to the booking terms set out by your travel provider.',
        'Prices, availability and itineraries are subject to change until your booking is confirmed. Payments must be made by the stated due dates; failure to pay on time may result in cancellation of your reservation.',
        'Cancellations and amendments are governed by the cancellation policy applicable to this booking. The full contract terms are provided with your booking confirmation documents.'
      ],
      cancellation: [
        ['121 days or more before departure', 'No penalty'],
        ['61–120 days before departure', 'Full deposit amount'],
        ['15–60 days before departure', '50% of total price'],
        ['14 days or fewer before departure', '100% of total price']
      ]
    }
  };
})();
