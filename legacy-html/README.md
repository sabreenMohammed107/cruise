# Safer Cruise — frontend

Static HTML + Tailwind (CDN) + vanilla JS. Open `index.html` in a browser, or serve the folder (`npx serve .`) for clean URLs.

## Pages
| File | Purpose |
| --- | --- |
| `index.html` | Hero + search, ship summary, upcoming sailings, why Safer, facilities, large cabin photo showcase, destinations slider, testimonials, newsletter |
| `ship.html` | The ship: hero, story, specs, life on board, cabins, good to know, FAQ |
| `destinations.html` | All six ports, country filter, things to do, visa notes, cruises that call there |
| `catalog.html` | Every departure as a card. Filters (port, visiting, month, date, nights, price, cabin, ship, highlights), sort, grid/list, pagination, URL sync |
| `details.html` | Gallery + lightbox, date picker, itinerary accordion, **Who's travelling** (Adult/Child/Infant), cabin selection, live price, capacity check |
| `booking.html` | `#guests` → `#review` → Confirm booking. No online payment: confirming creates the booking reference and opens the confirmation page |
| `confirmation.html` | Booking confirmed page with reference, total and printable voucher; tells the guest the team will arrange payment |
| `dashboard.html` | `#overview`, `#bookings`, `#favorites`, `#profile` |

## Shared files
- `assets/js/tailwind.config.js` and `assets/css/theme.css` — brand tokens. Swap hex values in both to match safer.travel exactly.
- `assets/img/` — local SVG illustrations for every image slot (ship, ports, cabins, onboard). Replace with real photos by changing the paths in `data.js`.
- `assets/js/data.js` — mock API data (ships, cruises, sailings, cabins, rates, age bands, fees, terms). **All facts and prices are samples.**
- `assets/js/core.js` — header/footer partials, icons, modal, drawer, dropdown, accordion, tabs, toast, pricing engine, booking session.
- `assets/js/pages/*.js` — one script per page.

## Backend hand-off
- `SC.prices(cruise, sailing, cabinId)` → replace with `GET /sailings/{id}/cabins/{cabinId}/prices` returning `{adult, child, infant}`.
- Age bands, fees and promo codes come from config (`SC_DATA.ageBands`, `.fees`, `.promos`), never hard-coded in markup. Re-validate everything server-side.
- `#confirm-btn` creates the provisional reference client-side for the demo; in production `POST /bookings` returns it.
- No online payment. All prices are shown in US dollars (`currencySymbol` in `data.js`).
- Booking state lives in `sessionStorage` (`sc_booking`); favourites, profile and demo trips in `localStorage`.
- One cabin per booking. Multi-cabin bookings would need the guests/cabin model extended.

## Arabic / RTL
Layout uses logical utilities (`ms-`, `me-`, `ps-`, `start-`, `end-`), so `dir="rtl"` works. The **عربي** button previews RTL; translated copy still needs an i18n layer.

## Navigation
Header: Home, Cruises, The ship, Destinations. The only link to safer.travel is in the footer.

## Photos (AROYA slots)
Every image on the site has a named slot in `assets/img/aroya/` (see the README there).
Drop official AROYA photos in with those file names and they are used everywhere automatically.
Empty slots fall back to royalty-free stock photos (cabins) and then to local illustrations.
Open `photos.html` to see which slots are filled.
