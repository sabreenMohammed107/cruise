# Safer Cruise: Laravel 12 conversion log

Running log of steps, commands, schema and architecture decisions for converting the static
Safer Cruise template (`legacy-html/`) into a Laravel 12 app.

> **Template v2 (2026-10-11).** The design in `legacy-html/` was replaced with a new version, so this whole
> document was rewritten against it. Main changes: prices in **US dollars**, **no online payment** (the team arranges
> payment offline), a **22-slot image system**, richer ship and destination content, and three new pages
> (`ship.html`, `destinations.html`, `photos.html`). See step 3b in the log for what changed in code.

---

## 0. Status board

| Phase | Description | Status |
| --- | --- | --- |
| 1 | Project documentation setup | Done |
| 2 | Laravel initialization + `.env` (MySQL `safer_cruise_db`). Started on 11, moved to **12.69.3** | Done |
| 3 | HTML/mock-data analysis â†’ migrations, models, seeders | Done, then realigned to template v2 (3b): 22 migrations, 21 models, 10 seeders, 27 tests passing |
| 4 | Blade layouts + asset management: all 8 public pages served by Laravel from the DB | Done (step 4) |
| 5 | Admin panel + CRUD | Done (step 4) |
| 6 | Customer auth, booking flow, account dashboard | Mostly done (step 4). Open: password reset, email verification |
| 6b | Dynamic homepage content (hero + sections) edited in the admin panel, English + Arabic, with lang/{en,ar} files for fixed words | Done (steps 5–5g; Cruise pages screen, all database content, template words, breadcrumbs, header and footer) |
| 7 | Hardening: Vite/Tailwind build, deployment, Arabic copy (i18n) | Planned |

**Run it locally:**

```powershell
C:\xampp\php\php.exe artisan migrate:fresh --seed     # demo data
C:\xampp\php\php.exe artisan serve                    # http://127.0.0.1:8000
C:\xampp\php\php.exe artisan test                     # 116 tests
```

Demo logins (local only): **admin** `admin@safercruise.test` / `password` at **`/admin/login`**;
**customer** `reem@example.com` / `password` at `/login`. The two sign-ins are independent (D6): both can be active in
the same browser at once.

Accounts registered on the website are always **customers**. To make an admin (also the way to create the first
admin on a real server: register on the site, then run):

```powershell
C:\xampp\php\php.exe artisan user:make-admin someone@example.com            # give admin access
C:\xampp\php\php.exe artisan user:make-admin someone@example.com --seller   # seller (their assigned bookings only)
C:\xampp\php\php.exe artisan user:make-admin someone@example.com --revoke   # back to customer
```

An admin can also change roles under Admin → Customers (Account type) or Admin → Admins & staff (Role).
Demo accounts (password `password`): `admin@safercruise.test`, `seller@safercruise.test`, `reem@example.com`.

---

## 1. Environment

| Tool | Version / path | Notes |
| --- | --- | --- |
| PHP (project) | 8.2.12, `C:\xampp\php\php.exe` | Target runtime. Use this binary for Composer and Artisan. |
| PHP (on PATH) | 8.4.16 (Laravel Herd) | **Not** the target. Plain `php`/`composer` on PATH resolve to this. |
| Composer | 2.7.7, `C:\ProgramData\ComposerSetup\bin\composer.phar` | |
| MySQL | XAMPP MariaDB 10.4.32, `C:\xampp\mysql\bin\mysql.exe` | Local user `root`, no password (XAMPP default). |
| Node / npm | 24.19.0 / 11.17.0 | Needed for Vite later. |
| Missing ext | `intl` | Optional for Laravel 12; enable in `C:\xampp\php\php.ini` (`extension=intl`) if `Number::` helpers are used. |

Always run Artisan with the 8.2 binary so behaviour matches the target:

```powershell
C:\xampp\php\php.exe artisan <command>
C:\xampp\php\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar <command>
```

---

## 2. Template inventory (source of truth for the conversion)

The static template lives untouched in `legacy-html/` (see its `README.md`).

| File | Becomes | Data it needs |
| --- | --- | --- |
| `index.html` | `pages/home.blade.php` | static hero (`assets/img/hero-home.jpg`), search widget, ship summary + specs, upcoming sailings, why Safer, facilities, **cabin photo showcase** (tabs + gallery per cabin), destinations slider (links to `destinations#slug`), testimonials, newsletter |
| `ship.html` *(new)* | `pages/ship.blade.php` | ship hero slot, tagline, spec chips, story, specs, **zones** (life on board, image rows), facilities, cabins, **essentials** (good to know), **FAQs** |
| `destinations.html` *(new)* | `pages/destinations.blade.php` | all ports with country filter, Arabic name, description, highlights, visa note, **cruises that call there** + next departure and from-price, link to catalog `?port=` |
| `catalog.html` | `pages/catalog.blade.php` | all future sailings (flattened), filters: port, month, date, nights, price, cabin, ship, tags |
| `details.html` | `pages/cruise-show.blade.php` | cruise, gallery, sailings, itinerary, cabin categories with per-sailing prices, age bands, facilities. Note under the summary: "No online payment" |
| `booking.html` | `booking/guests.blade.php`, `booking/review.blade.php` | two steps only: guest details â†’ review & **confirm**. Promo code on review |
| `confirmation.html` | `booking/confirmation.blade.php` | confirmed booking, reference, **total due**, travellers, printable voucher, "team will contact you within 24 hours to arrange payment" |
| `dashboard.html` | `account/*.blade.php` (customer) and the visual basis for `admin/*` | profile, bookings (upcoming / past / cancelled), favourites, notifications |
| `photos.html` *(new, internal, noindex)* | **`admin/image-slots`** | the 22 image slots: file name, label, recommended size, filled or showing fallback |

Navigation (header): Home, Cruises, The ship, Destinations. The only link to safer.travel is in the footer.

Shared JS (`legacy-html/assets/js/`):

- `data.js`: mock API (`window.SC_DATA`). Will be **replaced by data from the DB**. New keys in v2:
  `currency: 'USD'`, `currencySymbol: '$'`, `images` (scene images by name), `imageSlots` (for photos.html),
  `imageFallbacks` (URL â†’ next URL in the fallback chain).
- `core.js`: header/footer, UI widgets, `SC.money` (now `$` + rounded amount), pricing engine (unchanged), image
  fallback walker (`onerror` â†’ `imageFallbacks`), booking session.
- `pages/*.js`: one script per page, now including `ship.js` and `destinations.js`.
- `assets/img/`: local SVG illustrations (fallbacks for every slot), `hero-home.jpg`, and `aroya/` for official photos.

### Pricing rules (tiered occupancy since step 9; same in `core.js` and `PricingService`)

The template's rate card (cabin rate × nights × cruise factor × sailing factor, per person) was **replaced in step 9**.
Each Sailing × Cabin now has three admin-set prices for the **whole cabin and the whole cruise**:

```
paying guests  = adults + children            // infants are free (but take a place, see cabin limit)
1 paying guest  → fare = single
2 paying guests → fare = double
3 paying guests → fare = double + extra       // "3rd guest", adult or child
infants         → $0
cabin limit    = adults + children + infants ≤ 3   // config cruise.max_cabin_guests (step 11)
infant rule    = every infant needs ≥ 1 adult in the booking
fees           = Σ qty[band] × fee_per_night[band] × nights   // Age bands: adult 35, child 35, infant 0
discount       = round5(fare × promo.percent / 100)           // applies to fare only (fixed codes: min(value, fare))
total          = fare + fees − discount                       // = amount due
from price     = cheapest double ÷ 2 ("From, per person" on cards, two sharing; display only)
```

`round5(n) = round(n / 5) × 5`. **Currency is USD** (`$`).

### Age bands (checked against the departure date)

| Band | Rule |
| --- | --- |
| adult | 18 years and over (step 14; the template had 12+) |
| child | 2 to 17 years (step 14; the template had 2 to 11) |
| infant | 6 months to under 24 months |

### Booking lifecycle (v2: no online payment)

`details` (pick sailing, cabin, guest counts) â†’ `booking#guests` (traveller forms, primary contact) â†’
`booking#review` (terms, promo) â†’ **Confirm booking** creates the reference `SC{yymm}-{5 chars}` with status
**`confirmed`** and `amountDue = total` â†’ `confirmation`. The team then contacts the guest within 24 hours and payment
is arranged offline. There is no cabin hold, no payment step and no card form any more.

### Image slots (v2)

Every image on the site is a named slot (22 in total, listed in `legacy-html/assets/img/aroya/README.md`). Each slot
resolves in order to: official AROYA photo `assets/img/aroya/{slot}.jpg` â†’ royalty-free stock photo (Pexels/Pixabay)
â†’ local SVG illustration. Data refers to slots, not URLs.

---

## 3. Database design (`safer_cruise_db`)

### Entity relationship overview

```
image_slots  (referenced by key from ships, ship_zones, cabin_categories, cruises, destinations)
ships 1â”€* ship_specs, ship_zones, ship_essentials, ship_faqs, facilities, cabin_categories
ships 1â”€* cruises 1â”€* itinerary_days
          cruises 1â”€* sailings *â”€* cabin_categories   (via sailing_cabin_prices)
sailings 1â”€* bookings 1â”€* booking_travellers
                      1â”€* payments  (offline payments/refunds recorded by staff)
bookings *â”€1 cabin_categories, *â”€1 users (nullable), *â”€1 promo_codes (nullable)
users *â”€* cruises (favorites)
destinations Â·Â·Â· cruises   (no FK: linked by port name in cruises.route)
age_bands, promo_codes, testimonials, newsletter_subscribers: standalone
home_sections 1â”€* home_section_items   (homepage copy, step 5)
```

### Tables

**image_slots**: `id`, `key` (unique, e.g. `hero`, `dest-aqaba`, `cabin-suite-2`), `label`, `recommended_size`,
`path` (null; photo uploaded from admin, on the `public` disk), `stock_url` (null), `fallback_path` (local SVG), `sort_order`.

**ships**: `id`, `slug` (unique), `name`, `tagline`, `flag`, `home_port`, `description`, `story` (json: paragraphs),
`image_slot` (side view, `exterior`), `hero_image_slot` (`hero`), timestamps.

**ship_specs**: `ship_id`, `key`, `label`, `value` (string, e.g. `151,300`), `unit`, `sort_order`. Unique `(ship_id, key)`.

**ship_zones** *(v2)*: `ship_id`, `icon`, `title`, `image_slot`, `text`, `sort_order`. "Life on board" rows.

**ship_essentials** *(v2)*: `ship_id`, `icon`, `title`, `text`, `sort_order`. "Good to know".

**ship_faqs** *(v2)*: `ship_id`, `question`, `answer`, `sort_order`.

**facilities**: `ship_id`, `icon`, `value`, `label`, `description`, `sort_order`.

**cabin_categories**: `ship_id`, `slug` (unique per ship), `name_en`, `name_ar`, `badge`, `short_description`,
`description`, `max_guests`, `size`, `balcony_size`, `beds`, `bedroom`, `features` (json), `image_slots` (json, ordered
slot keys), `sort_order`. `max_guests` (adults + children + infants together) is at most **3**; the per-night `base_rate_adult|child|infant`
columns were dropped in step 9.

**cruises**: `ship_id` (restrict), `slug` (unique), `name`, `name_ar`, `nights`, `departure_port`, `arrival_port`,
`route` (json), `tags` (json), `visa_free`, `rating`, `reviews_count`, `popularity`, `image_slot`,
`gallery_slots` (json), `summary`, `is_active`, timestamps, soft deletes.

**itinerary_days**: `cruise_id`, `day_number`, `port`, `time_label`, `description`. Unique `(cruise_id, day_number)`.

**sailings**: `cruise_id`, `code` (unique, e.g. `RSD-261115`), `departure_date`, `return_date` (stored),
`status` (`open|closed|cancelled`). Index `(departure_date, status)`. (`price_factor` dropped in step 9; so was the
cruises one.)

**sailing_cabin_prices**: `sailing_id`, `cabin_category_id`, `price_single`, `price_double`, `price_extra`
(decimal 10,2, USD, **whole cabin, whole cruise**: 1 paying guest / 2 paying guests / the 3rd guest), `capacity`
(null = unlimited), `is_sold_out`. Unique pair. Infants have no column: they are always free. Admins enter the prices
per sailing (step 9); there is no rate card any more.

**age_bands**: `key` (`adult|child|infant`), `label`, `label_ar`, `hint`, `min_months`, `max_months_exclusive`,
`fee_per_night`, `sort_order`.

**promo_codes**: `code` (unique, upper case), `type` (`percent|fixed`), `value`, `label`, `starts_at`, `ends_at`,
`max_uses`, `used_count`, `is_active`.

**destinations** *(extended in v2)*: `slug` (unique, used for `destinations#slug`), `name` (**must match the port name in
cruise routes**), `name_ar`, `country`, `image_slot`, `blurb`, `description`, `highlights` (json), `visa_note`,
`sort_order`, `is_active`.

**testimonials**: `name`, `from_city`, `cruise_id` (null on delete), `rating`, `text`, `is_published`, `sort_order`.

**newsletter_subscribers**: `email` (unique), `subscribed_at`, `unsubscribed_at`.

**users** (extended): `first_name`, `last_name`, `phone_dial`, `phone`, `date_of_birth`, `nationality`,
`role` (`customer|admin`), `notification_prefs` (json). `name` is kept in step with first + last name.

**favorites**: `user_id`, `cruise_id`, timestamps. Primary key `(user_id, cruise_id)`.

**bookings** *(changed in v2)*: `reference` (unique), `user_id` (null), `sailing_id` (restrict), `cabin_category_id`
(restrict), `promo_code_id` (null), **`status`** (`confirmed|cancelled`, default `confirmed`), **`payment_status`**
(`unpaid|partial|paid|refunded`, default `unpaid`), `adults`, `children`, `infants`, `contact_email`, `contact_dial`,
`contact_phone`, `fare_total`, `fees_total`, `discount_total`, `grand_total` (= amount due), `amount_paid`
(net of refunds), `currency` (default `USD`), `price_snapshot` (json), `confirmed_at`, `paid_at`, `cancelled_at`,
`admin_notes`. Index `(status, payment_status)`. Removed in v2: `held_until`, pending/expired statuses.

**booking_travellers**: `booking_id`, `band`, `slot` (1..n within band), `is_primary`, `first_name`, `last_name`,
`date_of_birth`, `gender` (`male|female`), `nationality`. Unique `(booking_id, band, slot)`.

**payments** *(rewritten in v2)*: offline money movements recorded by staff. `booking_id`, `type` (`payment|refund`),
`method` (`bank_transfer|cash|card|payment_link`), `amount`, `currency`, `reference` (transfer/receipt number),
`notes`, `received_at`, `recorded_by` (user, null on delete). No card data is ever stored.

**home_sections** *(step 5)*: `key` (unique: `hero`, `search`, `ship`, `sailings`, `why`, `facilities`, `cabins`,
`destinations`, `testimonials`, `newsletter`), `eyebrow`, `title`, `body`, `primary_label`, `primary_url`,
`secondary_label`, `secondary_url`, `image_path` (null; upload relative to `public/`), `image_alt`, `caption_label`,
`caption_value`, `is_visible`, timestamps. Every text column has an Arabic twin `{column}_ar` (step 5b); links and photo are shared.

**home_section_items** *(step 5)*: `home_section_id` (cascade), `icon`, `tone` (`ocean|lagoon|sun`), `title`, `text`,
`sort_order`, plus `title_ar`, `text_ar` (step 5b). The "Why Safer Cruise" cards.

Static reference data stays in **`config/cruise.php`**: currency (`USD`) and symbol (`$`), payment contact window
(24 h), WhatsApp number, nationalities, dial codes, terms text, cancellation policy table.

### Eloquent models (`app/Models`)

`User`, `ImageSlot`, `Ship`, `ShipSpec`, `ShipZone`, `ShipEssential`, `ShipFaq`, `Facility`, `CabinCategory`, `Cruise`,
`ItineraryDay`, `Sailing`, `SailingCabinPrice`, `AgeBand`, `PromoCode`, `Destination`, `Testimonial`,
`NewsletterSubscriber`, `Booking`, `BookingTraveller`, `Payment`.

Key behaviour:

- `ImageSlot::url()` / `candidates()` / `isFilled()` / `ImageSlot::urls($keys)`: resolution order is uploaded photo â†’
  `public/assets/img/aroya/{key}.jpg` â†’ stock photo â†’ SVG. `candidates()` feeds `SC_DATA.imageFallbacks`.
- `Cruise::callsAt($port)` (prefix match like `destinations.js`) and `Destination::cruises()`.
- `Sailing::upcoming()` scope and `fromPrice()`.
- `Booking` scopes `upcoming()`, `past()`, `cancelled()` (dashboard tabs) and `awaitingPayment()` (admin follow-up);
  `amountDue()`; `syncPaymentTotals()` recomputes `amount_paid`, `payment_status` and `paid_at` from payments.
- `App\Services\PricingService` and `App\Services\AgeBandValidator`: server side of `SC.prices` / `SC.fareLines` /
  `SC.quote` (tiered occupancy, step 9) and `SC.checkBand`.
- Enums (`app/Enums`): `TravellerBand`, `BookingStatus`, `PaymentStatus`, `PaymentMethod`, `PaymentType`,
  `SailingStatus`, `UserRole`, `PromoType`, `Gender`.

### Seeders (from `legacy-html/assets/js/data.js`)

| Seeder | Source | Rows |
| --- | --- | --- |
| `ImageSlotSeeder` | `PHOTO` + `slot()` calls | 22 slots (stock URL + SVG fallback each) |
| `AgeBandSeeder` | `ageBands` + `fees` | 3 |
| `ShipSeeder` | `ships[0]` + `facilities` | 1 ship, 7 specs, 4 zones, 6 essentials, 4 FAQs, 9 facilities |
| `CabinCategorySeeder` | `cabins` | 3 (balcony now has 3 photos) |
| `CruiseSeeder` | `cruises` | 6 cruises, 34 itinerary days, 17 sailings, 51 price rows (3 sold out) |
| `DestinationSeeder` | `destinations` | 6 (with slug, Arabic name, description, highlights, visa note) |
| `TestimonialSeeder` | `testimonials` | 3 |
| `PromoCodeSeeder` | `promos` | 1 (`SAFER10`) |
| `HomeSectionSeeder` *(step 5)* | `index.html` copy, kept in `config/home.php` | 10 sections, 4 cards |
| `UserSeeder` *(local/tests only)* | `dashboard.js` profile | `reem@example.com` (customer), `admin@safercruise.test` (admin) |
| `DemoBookingSeeder` *(local/tests only)* | `dashboard.js` sample trips | 3 bookings, 9 travellers, 3 payments, 2 closed past sailings |

---

## 4. Front-end architecture decisions

**D1. Keep the template's JS and serve its data from Laravel (phase 1).** The layout injects the `SC_DATA` shape from
the database (`App\Support\ScDataPresenter`), so `core.js` + `pages/*.js` keep working unchanged:

```blade
<script>window.SC_DATA = @json($scData);</script>
```

The presenter produces every key the template reads: `currency`, `currencySymbol`, `routes` (named routes, see
below), `user`/`favorites`, `images` (scene name â†’ URL), `imageFallbacks` (from `ImageSlot::candidates()`), `ageBands`,
`fees`, `ships` (with tagline, story, zones, essentials, FAQs), `cabins`, `cruises` (only active cruises with upcoming
open sailings; each sailing carries its **stored prices** and sold-out cabins), `destinations`, `facilities`,
`testimonials`, `nationalities`, `dialCodes`, `terms`, plus `page` (per-page values, e.g. the cruise slug) and
`account` (dashboard only). `promos` is always empty: codes are checked on the server.
Pages that don't run a template script get `ScDataPresenter::minimal()` through a view composer.

**D2. Assets copied to `public/assets/`** (CSS, JS, SVGs, `hero-home.jpg`, empty `img/aroya/` for official photos);
`data.js` is **not** copied. The public copies of `core.js` and the page scripts carry small, commented
"Laravel:" edits (see step 4). Phase 7 moves Tailwind from the CDN to a Vite build.

**D3. Server owns prices and bookings.** `POST /booking/confirm` (`ConfirmBookingRequest` + `BookingService`)
re-checks the sailing (upcoming, open), cabin (on the ship, not sold out, capacity), guest limits (at least 1 adult,
every infant with an adult, adults + children + infants ≤ cabin max: step 11), the exact traveller slots, names, every date of birth against its age band on the departure
date, contact email/dial/phone (same rules as `booking.js`), the promo code and the terms box; then prices the
booking with `PricingService` inside a transaction that locks the sailing Ã— cabin price row.

**D4. Booking session stays in the browser** (`sessionStorage` `sc_booking`, as in the template) until the guest
confirms. Nothing is trusted from it: the server rebuilds everything at confirm time, so moving it to the Laravel
session (the original plan) is no longer needed. The session entry is cleared once the booking is saved.

**D5. No online payment (v2).** Staff record each bank transfer, cash, card or payment-link payment and every refund
on the booking in the admin panel; `Booking::syncPaymentTotals()` updates `amount_paid`, `payment_status` and
`paid_at`. On confirm the guest gets a `BookingConfirmed` email (with a 60-day signed link to the voucher) and the team
a `NewBookingForTeam` email (`CRUISE_TEAM_EMAIL`, default `Info@Safer.Travel`). Mail failures are logged, never shown
to the guest. Locally `MAIL_MAILER=log` writes them to `storage/logs/laravel.log`.

**D6. Auth: hand-written, not Breeze.** Breeze would add its own layouts plus a Vite/Tailwind setup that clashes with
the template's CDN Tailwind and theme. The app needs three screens (sign in, register, sign out), so they are written
directly with Laravel's `Auth` in the template's style (`Auth\LoginController`, `Auth\RegisterController`), with
login/register throttled to 5 per minute. **Password reset is not built yet** (phase 6). `role` is never
mass-assignable.

**Two guards (step 4c).** The website uses guard `web` (`/login`, `/register`, `/logout`, `/account`); the admin
panel uses guard `admin` (`/admin/login`, `/admin/logout`, everything under `/admin` with `auth:admin` + `can:admin`).
Each guard keeps its own user in the session, so an admin can be signed in to the panel while a different customer
account is signed in on the site, in the same browser. Rules that keep them apart:
- sign-out never calls `session()->invalidate()` (that would sign out both); it logs out its own guard and
  regenerates the CSRF token. Sign-in uses `session()->migrate(true)` for a new session id without dropping the other
  guard;
- `/admin/login` only accepts admin accounts; `/login` never sends anyone to `/admin` and ignores an `/admin` page
  left in `url.intended`;
- not signed in: `/admin*` â†’ `/admin/login`, other pages â†’ `/login` (`bootstrap/app.php`);
- website code names the guard explicitly (`auth:web`, `guest:web`, `$request->user('web')`, `auth('web')`), so
  it never picks up the admin user;
- the site header shows an **Admin** link when the panel is signed in, next to the customer's own menu.

**D7. Images by slot (v2).** Models store slot keys; the presenter and admin views resolve them with `ImageSlot`.
Admins upload a photo once on **Photo slots** and it changes everywhere. Official photos can also be dropped into
`public/assets/img/aroya/` with the template's file names.

**D8. Uploads live in `public/uploads/image-slots/`**, not `storage/app/public`: `php artisan storage:link` needs a
symlink, which on Windows/XAMPP requires admin rights. `image_slots.path` is relative to `public/`. The folder is in
`.gitignore`; back it up with the database.

**D9. Generic admin CRUD.** Simple entities are described by a small class (`App\Admin\Resources\*`: fields, rules,
index columns, optional hooks) and served by one `Admin\ResourceController` + one set of views. The list is in
`config/admin.php`; routes are generated in `routes/admin.php`. Screens with their own workflow have dedicated
controllers: dashboard, bookings (payments, cancel, notes), sailings (tiered price grid) and photo slots.
Field types: text, email, number, textarea, select, checkbox, checkboxes, date, lines (JSON list, one per line),
image_slot, image_slots (ordered gallery), password.

**D10. Confirmation page privacy.** `/booking/{reference}/confirmation` shows personal data, so it opens only for the
browser session that made the booking, the account owner, an admin, or a valid signed link (the email). Anyone else
gets 404. Guest bookings made while signed out are **not** linked to an account later by email match, since the
email address isn't verified.

### Blade structure (as built)

```
resources/views/
  layouts/app.blade.php        <head>, CDN Tailwind + theme.css, header/footer partials, SC_DATA, core.js, @stack('scripts')
  layouts/admin.blade.php      dashboard.html-style sidebar (groups from config/admin.php), flash messages
  partials/                    header, footer (+ WhatsApp button), logo (ported from core.js renderHeader/renderFooter)
  pages/                       home, ship, destinations, catalog, cruise-show   (converted from the template HTML)
  booking/show.blade.php       booking.html (guests â†’ review â†’ confirm), rendered by booking.js
  booking/confirmation.blade.php  server-rendered voucher from the saved booking
  account/dashboard.blade.php  dashboard.html, rendered by dashboard.js from SC_DATA.account
  auth/                        login, register, partials/field
  mail/                        booking-confirmed, new-booking-for-team (markdown mail)
  admin/dashboard.blade.php
  admin/bookings/              index, show (payments, refunds, cancel, notes), table
  admin/sailings/              index, form (price grid)
  admin/image-slots/index      Photo slots (replaces photos.html)
  admin/home-sections/         index, edit, new-item (homepage content, step 5)
  admin/resources/             index, form, field (generic CRUD)
  admin/cruises/itinerary      day-by-day editor used inside the cruise form
  admin/partials/              nav, flash, badge
```

The template pages were converted with a script (kept in the session scratchpad, not the repo): it takes the markup
between the `#site-header` and `#site-footer` placeholders verbatim and turns `*.html` links into `route()` calls.
So the page markup in `resources/views/pages/*` is the template's own.

---

## 5. Routes and controllers

All routes are in `routes/web.php` (site, account) and `routes/admin.php` (registered in `bootstrap/app.php` under
`/admin` with `web`, `auth`, `can:admin`). `php artisan route:list --except-vendor` lists 122 routes.

### Public (as built)

| Method | URI | Controller@action | Name |
| --- | --- | --- | --- |
| GET | `/` | `PageController@home` | `home` |
| GET | `/ship` | `PageController@ship` | `ship` |
| GET | `/destinations` | `PageController@destinations` (`#slug` anchors) | `destinations.index` |
| GET | `/cruises` | `PageController@catalog` (`?port=`, `?cabins=`, filters in JS) | `cruises.index` |
| GET | `/cruises/{cruise:slug}` | `CruiseController@show` (`?sailing=CODE`; 404 if hidden or no upcoming sailing) | `cruises.show` |
| GET | `/booking` | `BookingController@show` (booking.js reads the browser session) | `booking.show` |
| POST | `/booking/promo` | `BookingController@promo` (JSON, throttled 20/min) | `booking.promo` |
| POST | `/booking/confirm` | `BookingController@confirm` (JSON, throttled 10/min) | `booking.confirm` |
| GET | `/booking/{booking:reference}/confirmation` | `BookingController@confirmation` (private, D10) | `booking.confirmation` |
| POST | `/newsletter` | `NewsletterController@store` (JSON, throttled) | `newsletter.store` |
| GET/POST | `/login`, `/register` | `Auth\LoginController`, `Auth\RegisterController` (guests only) | `login`, `register` |
| POST | `/logout` | `Auth\LoginController@destroy` (form or JSON) | `logout` |

The planned `/booking/start`, `/booking/guests`, `/booking/review` steps and the
`/api/sailings/{sailing}/cabins/{cabin}/prices` endpoint were not needed: the template keeps those steps in the
browser, and stored prices reach the page in `SC_DATA` (D4).

### Customer account (`auth`, `AccountController`)

| Method | URI | Action | Name |
| --- | --- | --- | --- |
| GET | `/account` | dashboard (`#overview`, `#bookings`, `#favorites`, `#profile`) | `account` |
| PUT | `/account/profile` | name, email, phone, date of birth, nationality (JSON) | `account.profile` |
| PUT | `/account/notifications` | the three switches (JSON) | `account.notifications` |
| PUT | `/account/password` | requires the current password (JSON) | `account.password` |
| POST / DELETE | `/account/favorites/{cruise:slug}` | save / remove a favourite (JSON) | `account.favorites.store` / `.destroy` |

### Admin (as built)

| Screen | Routes | What it does |
| --- | --- | --- |
| Dashboard | `GET /admin` | New bookings (7 days), awaiting payment count and list, amount outstanding, fully paid this month, next departures with bookings and guests, latest bookings |
| Bookings | `GET bookings`, `GET/PATCH bookings/{reference}`, `POST â€¦/cancel`, `POST â€¦/payments`, `DELETE â€¦/payments/{payment}` | Search (reference, email, phone, traveller name), filter by booking and payment status; full booking view; record payment or refund; remove an entry; cancel; team notes; cancellation-policy line that applies today; WhatsApp and voucher links |
| Sailings | `cruises/{cruise}/sailings` (index, create, store), `sailings/{sailing}` (edit, update, destroy) | Departures of a cruise; price grid per cabin on create and edit (Single, Double, 3rd guest, infant free, cabins available, sold out), prefilled on create from the cruise's latest sailing; delete blocked when bookings exist |
| Homepage *(step 5)* | `GET home-sections`, `GET home-sections/{key}/edit`, `PUT home-sections/{key}` | Text, buttons, photo and visibility of each homepage section; add/edit/delete the "Why Safer Cruise" cards |
| Photo slots | `GET image-slots`, `POST/DELETE image-slots/{key}` | The 22 slots with filled / fallback state; upload (jpg/png/webp â‰¤ 5 MB) or remove |
| Generic CRUD | `{slug}` index, create, store, `{id}/edit`, update, destroy | Cruises (with itinerary editor and links to sailings), Cabin categories, Destinations, Ships, Ship facts, Life on board, Good to know, Ship FAQs, Facilities, Promo codes, Age bands & fees (edit only), Testimonials, Newsletter (list/delete), Users |

Guard rails: admins can't change their own role or delete themselves; cabin categories with prices, ships with
cruises and age bands can't be deleted; cruises are soft-deleted; slugs, codes and emails are unique-validated.

---

## 6. Progress log

### Step 1: Documentation (2026-10-10)
- Created `PROJECT_STEPS.md`. Analysed the six original HTML pages, `data.js`, the pricing engine and booking
  session in `core.js`, and `booking.js`.

### Step 2: Laravel 11 initialization (2026-10-10)

1. Moved the static template into `legacy-html/`. Removed an empty stray folder `assets/{css,js/pages}` left by a
   failed brace-expansion `mkdir`.
2. Created Laravel 11 in a subfolder and moved it up (Composer refuses a non-empty directory).
3. Configured `.env` for MySQL `safer_cruise_db`; created the database
   (`CREATE DATABASE safer_cruise_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`); ran default migrations.
4. `composer audit` found 4 advisories on `laravel/framework` 11.x that are fixed only in 12.60+ (Laravel 11 no longer
   gets security fixes). Decision (owner): move to Laravel 12.

### Step 2b: Reinstall on Laravel 12 (2026-10-11)

1. `create-project laravel/laravel:^12.0 _laravel12 --prefer-dist --no-scripts --no-install` (starter v12.12.2),
   replaced the Laravel 11 files (kept `legacy-html/`, `PROJECT_STEPS.md`), moved up.
2. `.env.example` / `.env`: `APP_NAME="Safer Cruise"`, `APP_URL=http://127.0.0.1:8000`, MySQL settings as above.
   Laravel 12 no longer reads `APP_TIMEZONE`; set `'timezone' => 'Asia/Riyadh'` in `config/app.php`.
3. `composer install`, `artisan key:generate`, `artisan migrate:fresh`. Result: `laravel/framework` **v12.69.3**,
   PHP 8.2.12, mysql; `composer audit`: no advisories.

GitHub downloads timed out several times on this machine; Composer recovered by cloning. If `composer install` hangs,
rerun it or set `composer config --global process-timeout 900`.

### Step 3: Database layer (2026-10-11)

Built against the first template: config, enums, 18 migrations, 17 models, `PricingService`, `AgeBandValidator`,
9 seeders, 19 tests. Key decisions that still hold:

- **Status/type columns are `string`, not MySQL `ENUM`**, cast to PHP enums. New values need no `ALTER TABLE`, and
  the same migrations run on SQLite for tests.
- **`round5` uses `floor(n / 5 + 0.5) * 5`** to copy JS `Math.round` exactly (PHP 8.2 `round()` pre-rounds), and the
  rate-card factors use the JS operand order, so results are bit-identical.
- **Age bands are stored in whole months** (adult `[144, âˆž)`, child `[24, 144)`, infant `[6, 24)`); error messages
  are built from them.
- **`role` is not mass-assignable**; seeders use `forceFill`.
- **`User::name` is filled by a `saving` hook**, so `DatabaseSeeder` does not use `WithoutModelEvents`.
- **Traveller ordering uses `CASE`, not `FIELD()`**, so it works on MySQL and SQLite.
- **Demo-only seeders** run only when `APP_ENV=local` or in tests. Demo logins: `admin@safercruise.test` / `password`,
  `reem@example.com` / `password`. Reem has 2 favourites (New Year at Sea, Egypt & Jordan Voyage).
- **Demo bookings:** the sample March and May 2026 trips have no sailing in `data.js`, so the seeder creates
  `RSD-260315` and `EJV-260522` as `closed` sailings priced with factor 1, as `dashboard.js` does. The seeder adds the
  rest of each party (the template stores only the primary guest).

### Step 3b: Realign to template v2 (2026-10-11)

The design in `legacy-html/` was replaced. Compared every page and `data.js`, then updated the database layer.
The migrations were **edited in place** (no production data exists yet) and the DB rebuilt with `migrate:fresh --seed`.

| Change in template | Change in code |
| --- | --- |
| Prices in US dollars (`currency: 'USD'`, `$`) | `config/cruise.php`: `currency => USD`, `currency_symbol => $`; `bookings.currency` and `payments.currency` default `USD`. Rate-card numbers unchanged |
| No online payment; booking is confirmed on submit, team arranges payment within 24 h | `BookingStatus` = `confirmed|cancelled`; new `bookings.payment_status` (`PaymentStatus` = `unpaid|partial|paid|refunded`) and `admin_notes`; removed `held_until`, `hold_minutes`; `payments` rewritten as offline records (`type`, `method` = bank transfer / cash / card / payment link, `reference`, `notes`, `received_at`, `recorded_by`); new `PaymentType` enum; `Booking::syncPaymentTotals()`, `amountDue()`, `awaitingPayment()` scope |
| 22 image slots with official â†’ stock â†’ SVG fallback; `photos.html` | New `image_slots` table, `ImageSlot` model, `ImageSlotSeeder`. All image columns now hold slot keys: `ships.image_slot`/`hero_image_slot`, `cabin_categories.image_slots`, `cruises.image_slot`/`gallery_slots`, `destinations.image_slot`, `ship_zones.image_slot`. Unsplash helper trait removed |
| Ship page: tagline, story, zones, essentials, FAQs | `ships.tagline`, `ships.story`; new tables `ship_zones`, `ship_essentials`, `ship_faqs` with models and seed data |
| Destinations page: slug, Arabic name, description, highlights, visa, cruises that call | `destinations` gains `slug`, `name_ar`, `description`, `highlights`, `visa_note`; `Destination::cruises()` + `Cruise::callsAt()` |
| Balcony cabin has a third photo; cruise images and galleries changed | Seed data updated to the new slot keys |
| New pages `ship.html`, `destinations.html`, `photos.html` | Added to inventory, routes (`/ship`, `/destinations`) and admin (Photo slots) in sections 2, 4, 5 |

Demo bookings follow the new flow:

| Reference | Status | Payment | Notes |
| --- | --- | --- | --- |
| `SC2611-K4P2Q` (upcoming) | confirmed | **unpaid**, $4,155 due | Fills the admin "awaiting payment" list |
| `SC2603-H7M1Z` (past) | confirmed | **paid** $5,110 by bank transfer | |
| `SC2605-T9R3B` | cancelled | **refunded**: $17,220 paid, $8,610 refunded | Cancelled 35 days before departure â†’ 50% penalty per the template's policy (15â€“60 days) |

**Verification:**

- `migrate:fresh --seed` on MySQL: 22 image slots, 1 ship (7 specs, 4 zones, 6 essentials, 4 FAQs, 9 facilities),
  3 cabins, 6 cruises, 34 itinerary days, 19 sailings, 57 price rows, 6 destinations, 3 bookings, 3 payments.
- **Price parity with the new `data.js`:** the Node script running the template's own `SC.prices` matched the DB on
  **51/51** rows, including sold-out flags.
- `migrate:rollback --step=22` then `migrate:fresh --seed`: clean.
- `artisan test`: **27 passed (69 assertions)**. New tests: `ImageSlotTest` (resolution order), image slot keys all
  exist, destinations â†” cruises, USD and payment states of the demo bookings, `awaitingPayment()`.

Housekeeping: the new `legacy-html/assets/` again contains an empty stray folder `assets/{css,js/pages}`. It is harmless
and won't be copied to `public/`.

### Step 4: Website pages, booking, accounts and admin panel (2026-10-11)

Built phases 4 and 5 and most of 6.

**Assets and template scripts** (`public/assets/`): copied from `legacy-html/assets` without `data.js`. Edits to the
public copies, each marked with a `Laravel:` comment:

| File | Change |
| --- | --- |
| `core.js` | `SC.route()` (named routes from `SC_DATA.routes`), `SC.api()` (JSON + CSRF), `SC.apiError()`; `SC.prices()` uses the sailing's stored prices first; favourites sync to the account when signed in; header/footer rendering replaced by no-ops (Blade renders them); `SC.detailsUrl()` â†’ `/cruises/{slug}?sailing=CODE`; WhatsApp number from config |
| `pages/home.js`, `ship.js`, `destinations.js` | `*.html` links â†’ `SC.route(...)`; newsletter form posts to `/newsletter` |
| `pages/details.js` | cruise read from `SC_DATA.page.cruise`; URL updates and "Continue" use routes |
| `pages/booking.js` | promo codes checked with `POST /booking/promo`; "Confirm booking" posts to `/booking/confirm` and opens the returned confirmation page; session cleared after confirm |
| `pages/dashboard.js` | rewritten data layer: profile and bookings from `SC_DATA.account`; profile, notifications, password and sign-out call the account endpoints; payment status tag on upcoming trips. Markup unchanged |

**Server side** (new files):

- `app/Support/ScDataPresenter.php` (D1), `app/Support/Format.php` (money/date/badges for admin views)
- Controllers: `PageController`, `CruiseController`, `BookingController`, `NewsletterController`, `AccountController`,
  `Auth\LoginController`, `Auth\RegisterController`, `Admin\DashboardController`, `Admin\BookingController`,
  `Admin\SailingController`, `Admin\ImageSlotController`, `Admin\ResourceController`
- `app/Http/Requests/ConfirmBookingRequest.php`, `app/Services/BookingService.php`
- `app/Mail/BookingConfirmed.php`, `app/Mail/NewBookingForTeam.php`
- `app/Admin/{Field,Resource,Options}.php` + 15 resources in `app/Admin/Resources/`, `config/admin.php`
- `routes/web.php`, `routes/admin.php`, `bootstrap/app.php` (admin group, guest redirect), `AppServiceProvider`
  (admin gate, SC_DATA composer, Tailwind pagination)
- Views listed under "Blade structure (as built)"; `welcome.blade.php` removed
- `config/cruise.php`: `team_email` (`CRUISE_TEAM_EMAIL`); `PricingService` quote lines now carry the band `label`
- `ImageSlot` uploads moved to `public/uploads/image-slots/` (D8)

**Bugs found and fixed while testing:**

- `ship.js`: my link edit put a template expression inside a single-quoted string, a syntax error that stopped the
  whole ship page from rendering. Found in the browser check, fixed, and every public JS file now passes `node --check`.
- `PricingService::quote()` read age bands with `$collection[$key]`, which throws on a missing key; now `->get()`.
- Admin form: empty "Order" fields would have inserted NULL into NOT NULL `sort_order` columns; empty inputs now
  fall back to the field default (0).
- Admin dashboard counted bookings by row creation time; now uses `confirmed_at`.

**Verification:**

- `artisan test`: **57 passed (267 assertions)**. New: `PublicSiteTest`, `BookingFlowTest` (server pricing with
  promo, ownership, age band at departure, guest limits, traveller slots, sold out, admin capacity, past sailings,
  phone and terms rules, promo endpoint, confirmation privacy), `AccountTest` (sign in/up/out, role can't be chosen,
  dashboard data, profile/notifications/password/favourites), `AdminPanelTest` (access control, every admin screen
  loads, payments â†’ status, cancel, generic CRUD, itinerary rewrite, self-demotion blocked, sailing prices +
  recalculate, delete blocked with bookings, photo upload/clear).
- Headless-browser checks (Playwright) against `artisan serve` with MySQL:
  - all public pages render their template sections with no JS errors;
  - full guest booking: balcony cabin on RSD-261213 for 2 adults, wrong promo rejected, `SAFER10` applied
    â†’ $7,700, terms required, confirmed â†’ `/booking/SC2612-â€¦/confirmation`. Saved with server totals
    (fare $8,010, fees $490, discount $800), promo use count 1, both emails in the log;
  - customer account: tabs 1/1/1, payment tag, favourite removal persisted, profile saved, `/admin` â†’ 403;
  - admin: 34 screens return 200, payment recorded â†’ Paid, cruise and sailing saves.
- Database reset with `migrate:fresh --seed` after testing.

### Step 4b: Admin access for new accounts (2026-10-11)

Reported: a newly registered account (`admin@system.com`) got a bare "403 THIS ACTION IS UNAUTHORIZED" on `/admin`.
Expected, since self-registered accounts are customers (D6). But the page was a dead end: while signed in, `/login`
redirects to `/account`, so there was no obvious way to switch accounts.

- `resources/views/errors/403.blade.php`: branded page that names the signed-in account and offers **"Sign out and use
  another account"**. That posts `/logout` with `next=admin`, which signs out and opens `/login`; after sign-in the
  user lands back on `/admin`.
- `app/Console/Commands/MakeAdmin.php`: `php artisan user:make-admin {email} [--revoke]`.
- Ran `user:make-admin admin@system.com`, so that account is now an admin.
- Tests: `AdminPanelTest::test_customer_on_admin_gets_a_way_to_switch_accounts`, `test_make_admin_command`.
  `artisan test`: **59 passed (279 assertions)**.

### Step 4c: Separate admin and customer sign-in (2026-10-11)

Request: being signed in to the admin panel must not make you a customer on the website (and the reverse); it should
be possible to have the admin panel open and a different customer account signed in on the site at the same time.

- `config/auth.php`: new `admin` guard (session driver, same `users` provider).
- `Admin\AuthController` + `resources/views/admin/login.blade.php`: `/admin/login` (admins only, throttled) and
  `/admin/logout`. Routes in `bootstrap/app.php`: `guest:admin` for the sign-in, `auth:admin` + `can:admin` for the panel.
- Website `LoginController` / `RegisterController`: guard `web` only; no redirect to `/admin`; sign-out no longer
  invalidates the whole session. `routes/web.php` uses `auth:web` / `guest:web`; site controllers read `user('web')`.
- Admin layout signs out with `admin.logout`; site header shows the customer from `web` and an Admin link when the
  `admin` guard is signed in; the guest voucher opens for an admin signed in to the panel.
- `errors/403`: now only reachable in `/admin` by an account that lost its admin role while signed in; offers
  "Sign out of the admin panel". (The step 4b "sign out and use another account" flow is replaced by `/admin/login`.)
- Tests: `test_admin_panel_has_its_own_sign_in`, `test_admin_and_customer_can_be_signed_in_at_the_same_time`,
  `test_admin_who_lost_the_role_gets_a_way_out`; all admin tests sign in on the `admin` guard.
  `artisan test`: **60 passed (303 assertions)**.
- Browser check on `artisan serve`: customer signed in â†’ `/admin` sends to `/admin/login`; admin signs in; `/account` (Reem)
  and `/admin` (Safer Admin) open side by side in two tabs; signing out of the panel leaves Reem signed in on the site.

### Step 5: Dynamic homepage content (2026-10-11)

Request: make the homepage hero and the sections below it editable from the admin panel (titles, text, buttons,
photos), stored in the database.

**Approach.** The homepage keeps its template layout and its JS-rendered lists (sailings, facilities, cabins,
destinations, testimonials still come from their own tables via `SC_DATA`). What becomes dynamic is the copy and
imagery **around** those lists: each section's heading, text, buttons, photo and visibility, plus the "Why Safer Cruise"
cards. These are now **server-rendered** Blade, so they are also visible to search engines.

**D11. One row per section, fields described in config.** A single `home_sections` table (rather than a table per
section) with a fixed set of nullable columns. `config/home.php` lists the sections in page order and says, for each,
which field groups the admin may edit (`eyebrow`, `title`, `body`, `primary`, `secondary`, `image`, `caption`, `items`),
its default photo (an asset path or an image slot), and its default copy from `legacy-html/index.html`. The same defaults
feed the seeder **and** a runtime fallback, so the homepage renders even if the table is empty. Sections are fixed by the
layout, so admins edit and show/hide them; they don't create or delete them. Cards (`home_section_items`) have full CRUD.

**D12. Hidden sections stay in the markup.** A hidden section is rendered with the HTML `hidden` attribute instead of
being left out, because `home.js` looks up elements such as `#search-form` and `#featured-grid` and would throw if they
were missing. When the hero is hidden, the search widget drops its negative top margin.

**D13. Safe links and uploads.**
- Button links must start with `/`, `#`, `http(s)://`, `mailto:` or `tel:`, so `javascript:` and protocol-relative `//host`
  are rejected. Site paths are stored as `/cruises` and turned into full URLs with `url()` when rendered.
- All text is printed with `{{ }}` (escaped); there is no raw HTML in section copy.
- Photos: `image` rule + `jpg/jpeg/png/webp`, max 5 MB, random file name `{key}-{6 chars}.{ext}` in
  **`public/uploads/home/`** (same reason as D8: no storage symlink needed; `/public/uploads` is already git-ignored).
  The previous upload is deleted on replace or "remove photo", and only if its path is inside that folder.
- Icon and colour of cards are checked against the lists in `config/home.php`.

**Files**

| File | Purpose |
| --- | --- |
| `database/migrations/2026_10_11_000023_create_home_sections_table.php` | `home_sections` + `home_section_items` |
| `config/home.php` | section list, editable fields, default photos and copy, icon and colour lists, upload folder |
| `app/Models/HomeSection.php` | `forHome()` (all sections keyed by key, config fallback for missing rows), `supports()`, `imageUrl()` (upload â†’ config asset/slot), `primaryHref()` / `secondaryHref()` |
| `app/Models/HomeSectionItem.php` | a card |
| `database/seeders/HomeSectionSeeder.php` | writes the config defaults (re-running resets the homepage copy); added to `DatabaseSeeder` |
| `app/Http/Controllers/Admin/HomeSectionController.php` | `index`, `edit`, `update`; validation built from the section's fields; upload handling; card add/edit/delete in one transaction. Creates a missing row from defaults on first edit |
| `routes/admin.php` | `admin.home-sections.index`, `.edit`, `.update` (`{key}` limited to `[a-z-]+`) |
| `resources/views/admin/home-sections/index.blade.php` | list in page order with photo thumbnail, heading, card count, shown/hidden |
| `resources/views/admin/home-sections/edit.blade.php`, `new-item.blade.php` | form with only that section's fields, photo preview + upload + remove, cards editor with "Add a card" |
| `resources/views/layouts/admin.blade.php` | **Homepage** link in the sidebar, under Bookings |
| `app/Http/Controllers/PageController.php` | `home()` passes `$home = HomeSection::forHome()` |
| `resources/views/pages/home.blade.php` | hero (eyebrow, title, text, two buttons, photo + alt), search (heading, button), ship (title, text, button, photo, caption), sailings / destinations (title, text, button), why (title, text, cards), facilities / cabins (title, text), testimonials (title), newsletter (title, text, button) all read from `$home`; optional parts are only printed when filled |
| `public/assets/js/pages/home.js` | `Laravel:` edit: the ship photo is no longer overwritten with `SC_DATA.images.shipSea` when the server already set one |
| `tests/Feature/HomeContentTest.php` | 10 tests, below |

The request said Laravel 11, `HomeController` and `index.blade.php`; the project is Laravel 12, and the homepage is
`PageController@home` rendering `pages/home.blade.php`, so the feature was built on those rather than adding a second
controller and view.

**How to use it:** Admin â†’ **Homepage** â†’ *Edit* on a section â†’ change text, links or photo â†’ *Save changes* â†’ reload
`/`. To go back to the template copy: `php artisan db:seed --class=HomeSectionSeeder` (this also clears uploaded photo
paths; the files stay in `public/uploads/home/`).

**Commands run**

```powershell
C:\xampp\php\php.exe artisan migrate                                 # adds the two tables, keeps existing data
C:\xampp\php\php.exe artisan db:seed --class=HomeSectionSeeder       # 10 sections, 4 cards
C:\xampp\php\php.exe artisan route:list --path=admin/home-sections   # 3 routes
C:\xampp\php\php.exe artisan test                                    # 70 passed (383 assertions)
node --check public/assets/js/pages/home.js
```

**Verification (`tests/Feature/HomeContentTest.php`, all passing):**

1. Seeder writes the 10 sections in config order; hero title and 4 cards match the template.
2. `/` renders the seeded copy: hero title, eyebrow, `hero-home.jpg`, button links (`/cruises` â†’ full URL, `#ship`),
   ship caption, a card, newsletter heading.
3. With the table emptied, `/` still renders from the config defaults.
4. Admin screens: guest and signed-in customer are sent to `/admin/login`; admin can open the list and every section's
   edit page; an unknown key is a 404.
5. **DB â†’ admin â†’ frontend:** updating the hero (title, text with HTML, external link, blank second button) shows the new
   title, the text escaped, the new link, and no second button on `/`.
6. Empty title, `javascript:` and `//host` links are rejected and nothing is saved.
7. Photo upload: stored as `uploads/home/hero-â€¦.jpg`, shown on `/` instead of the default; a PDF is rejected; "remove"
   deletes the file and the default photo comes back.
8. Cards: edit one (icon, colour, order), delete one, add one, ignore a blank new row; `/` reflects all of it; a bad
   icon or colour is rejected.
9. A hidden section keeps its markup with the `hidden` attribute (and `#featured-grid` stays in the page); other
   sections are not hidden.
10. Editing a section whose row is missing recreates it, with its cards, from the defaults.

Manual check on `artisan serve` against MySQL after migrating: `/` returns 200 with the seeded hero, ship, card and
newsletter copy.

### Step 5b: Homepage content in English and Arabic (2026-10-11)

Request: every homepage field except the photos must exist in both Arabic and English.

**D14. Arabic twin columns, both languages in the page.**
- Storage follows the existing `name_ar` / `label_ar` pattern: each text column gets `{column}_ar` (new migration
  `2026_10_11_000024_add_arabic_to_home_sections.php`, so the already-applied step 5 migration is untouched).
  Bilingual: eyebrow, title, body, both button labels, photo description (alt), caption label and value, card title
  and text. **Shared by both languages:** button links, the photo, visibility, card icon/colour/order.
- The site has no server-side locale yet; the header's **Ø¹Ø±Ø¨ÙŠ** button (core.js) switches `<html dir/lang>` and
  remembers it in `localStorage`. So the homepage prints both languages and CSS shows the one matching `<html lang>`:
  `HasArabicCopy::bi('title')` outputs `<span data-lang="en">â€¦</span><span data-lang="ar" lang="ar">â€¦</span>` (escaped),
  and `theme.css` hides the inactive `data-lang`. Photo `alt` can't be switched with CSS, so images carry
  `data-alt-ar` and `SC.applyLang()` in core.js swaps the alt on load and on toggle.
- **Arabic is optional per field:** an empty Arabic box shows the English text in both modes (and an English-only
  empty field shows the Arabic). Arabic fields have the same length limits as English. A button whose label is set in
  either language needs a link.
- When a real i18n layer arrives (open item 2), `bi()` can switch to returning just the current locale's text; the
  data and admin need no change.

**Files changed:** `app/Models/Concerns/HasArabicCopy.php` (new: `bi()`, `filledBi()`), `HomeSection` and
`HomeSectionItem` (trait + fillable), `config/home.php` (Arabic defaults for all copy and cards; `text_fields` map used
by the controller and tests), `Admin\HomeSectionController` (Arabic rules and columns generated from `text_fields`;
card `title_ar` / `text_ar`), admin views (new `bi-field.blade.php` partial: English and Arabic inputs side by side,
Arabic `dir="rtl"`; the index shows the Arabic heading or "No Arabic heading yet"), `pages/home.blade.php` (all text
through `bi()`), `public/assets/css/theme.css` and `public/assets/js/core.js` (`Laravel:` edits; toggle toast text
updated).

**Existing database:** `artisan migrate`, then a one-off script filled only the **empty** Arabic columns from
`config/home.php` (38 fields), so any admin edits were kept. A fresh install gets them from `HomeSectionSeeder`.

**Verification:**
- `artisan test`: **73 passed (437 assertions)**. New/updated in `HomeContentTest`: every seeded text has an Arabic
  version; seeded Arabic rendered next to the English (hero, alt, card, newsletter button); admin edit page shows the
  `_ar` inputs; saving English + Arabic, empty Arabic falling back to English, Arabic escaped, Arabic length limit;
  Arabic-only button label still requires a link; cards save `title_ar` / `text_ar`.
- Headless browser on `artisan serve` (MySQL): English by default â†’ click **Ø¹Ø±Ø¨ÙŠ** â†’ `lang=ar`, `dir=rtl`, hero heading
  "Ø£Ø¨Ø­Ø± ÙÙŠ Ø§Ù„Ø¨Ø­Ø± Ø§Ù„Ø£Ø­Ù…Ø± Ø¹Ù„Ù‰ Ù…ØªÙ† Ø£Ø±ÙˆÙŠØ§", hero alt, ship heading and main button in Arabic; still Arabic after reload;
  0 console errors, 0 failed requests.

### Step 5c: Language files for fixed words (2026-10-11)

Request: the fixed words in the search widget (labels, "Any port", "2 adults", "sailings match", â€¦) need `en` / `ar`
translation files.

**D15. `lang/{en,ar}/site.php` is the single source for fixed website words**, used by Blade and by the page scripts.
- `lang/en/site.php`, `lang/ar/site.php` (same keys): `search.*` (labels, placeholders, Done, "sailings match"),
  `nights` and `guests.adult|child|infant` as plural strings in Laravel's `trans_choice` format (Arabic has the
  1 / 2 / 3â€“10 / 11+ forms, e.g. "Ù„ÙŠÙ„Ø© ÙˆØ§Ø­Ø¯Ø©", "Ù„ÙŠÙ„ØªØ§Ù†", "3 Ù„ÙŠØ§Ù„Ù"), `guests.join`, `bands.*` (counter row label and
  hint), `date_locale` (`en-GB` / `ar-u-nu-latn`: Arabic month names, Western digits like the prices).
- **Blade:** `@bi('site.search.guests')` (directive in `AppServiceProvider`, class `App\Support\Bi`) prints both
  languages in `data-lang` spans like the admin-edited copy. `HasArabicCopy::bi()` now uses the same `Bi::html()`.
- **JS:** `SC_DATA.i18n = ['en' => â€¦, 'ar' => â€¦]` (`ScDataPresenter`, both `make()` and `minimal()`). core.js adds
  `SC.lang()`, `SC.t(key, replace)` and `SC.tc(key, count)` (parses the same `{1} â€¦|[2,10] â€¦|[11,*] â€¦` format), and fires
  `sc:lang` when the Ø¹Ø±Ø¨ÙŠ button is pressed. `SC.guestText()` and `SC.ui.counterRow()` use them (English output is
  unchanged; in Arabic the counter label comes from the age band's `label_ar`, the hint from the lang file).
- **home.js:** a `paintSearch()` sets the placeholder options, port names (Arabic from `destinations.name_ar`), month
  names, "N nights", the guest text and the counter rows in the current language, and runs again on `sc:lang`.
- `<option>` can't hold spans, so placeholders are printed in the app locale by Blade and relabelled by the script.

To add a word: put the key in **both** files (a test fails if the keys differ), then use `@bi('site.â€¦')` in Blade or
`SC.t('â€¦')` in a page script.

**Verification:** `artisan test` **74 passed (447 assertions)**; new `test_fixed_search_words_come_from_the_language_files`
(key parity en/ar, bilingual labels on `/`, `SC_DATA.i18n`). Headless browser: English labels, ports, months
("October 2026"), "3 nights", "2 adults", "17 sailings match", Done â†’ after Ø¹Ø±Ø¨ÙŠ: "Ø§Ù„ÙˆØ¬Ù‡Ø©", "Ø§Ù„ÙˆØ¬Ù‡", "Ø£ÙƒØªÙˆØ¨Ø± 2026",
"3 Ù„ÙŠØ§Ù„Ù", "Ø¨Ø§Ù„ØºØ§Ù†", "17 Ø±Ø­Ù„Ø© Ù…Ø·Ø§Ø¨Ù‚Ø©", "ØªÙ…"; adding a child in Arabic gives "Ø¨Ø§Ù„ØºØ§Ù† ÙˆØ·ÙÙ„ ÙˆØ§Ø­Ø¯"; 0 console errors.

### Step 5d: Header and footer in English and Arabic (2026-10-11)

Request: translate the header, footer and their links; keep the logo text ("SaferCruise / by safer.travel") as it is.

- `lang/{en,ar}/site.php`: new groups `nav.*` (Home, Cruises, The ship, Destinations, My bookings, My account, Admin,
  Sign in/out, language button) and `footer.*` (about text, Visit safer.travel, Explore, All cruises, Help, Terms,
  Cancellation policy, Contact us, Individuals/Companies, address, `© :year Safer. All rights reserved.`, prices in USD,
  Chat with us).
- `partials/header.blade.php`, `partials/footer.blade.php`: every word through `@bi(...)`, so it switches on **every
  page** with the عربي button. `partials/logo.blade.php` is unchanged.
- **Not translated on purpose:** the logo, brand names (Safer Cruise, safer.travel, Facebook/YouTube/Instagram), phone
  numbers, email. Phone links are `dir="ltr"` + `inline-block` so "+90 …" keeps its order on the Arabic layout.
- The language button now names the *other* language: "عربي" on the English page, "English" on the Arabic page.
  The toast says "تم التبديل إلى العربية" / "Switched to English".
- Still English: `aria-label` / `title` attributes (can't hold the two-span markup) and the WhatsApp pre-filled message.

**Verification:** `artisan test` **74 passed (479 assertions)**; the language-file test now checks the bilingual
header and footer on `/`, `/cruises`, `/ship` and `/login`, and that the logo is unchanged. Headless browser: nav
"Home, Cruises, The ship, Destinations" → "الرئيسية، الرحلات، السفينة، الوجهات"; "My bookings / Sign in" → "حجوزاتي /
تسجيل الدخول"; footer headings and bottom line in Arabic; logo text identical in both modes; 0 console errors.

### Step 5e: All database content in English and Arabic (2026-10-11)

Request: ship data and ship facts, and every database table, in two languages.

**Audit.** Every table was classified:

| Table | Arabic columns added (`{column}_ar`) |
| --- | --- |
| `ships` | name, tagline, flag, home_port, description, story (json) |
| `ship_specs` (ship facts) | label, value, unit |
| `ship_zones`, `ship_essentials` | title, text |
| `ship_faqs` | question, answer |
| `facilities` | value, label, description |
| `cabin_categories` | badge, short_description, description, size, balcony_size, beds, bedroom, features (json); `name_ar` already existed |
| `cruises` | summary; `name_ar` already existed |
| `itinerary_days` | time_label, description |
| `destinations` | country, blurb, description, highlights (json), visa_note; `name_ar` already existed |
| `testimonials` | name, from_city, text |
| `age_bands` | hint; `label_ar` already existed |
| `promo_codes` | label |
| `home_sections`, `home_section_items` | done in step 5b |

**Not translated, by design:** `users`, `bookings`, `booking_travellers`, `payments`, `favorites`,
`newsletter_subscribers` (personal or operational data), `sailings`, `sailing_cabin_prices` (codes, dates, prices),
`image_slots` (admin-only labels; photos have no language), framework tables. Identifiers stay English: slugs, keys,
promo codes, and **port names in routes** (`cruises.route`, `departure_port`, `itinerary_days.port`), because they are
the keys behind `?port=` filters and "cruises that call here"; they are *shown* in Arabic via the destinations' `name_ar`.

**D16. Server-side language (replaces the client-only switch of 5b–5d).** Most content reaches the page through
`SC_DATA`, so the server must know the language:
- `App\Http\Middleware\SetLocale` (web group) sets the app locale from the **`sc_lang` cookie** (`ar` / `en`, not
  encrypted: `encryptCookies(except:)` in `bootstrap/app.php`). The admin panel always stays English.
- The header's عربي / English button (core.js) writes the cookie and **reloads**. `layouts/app` renders
  `<html lang dir>` from the locale; the old `localStorage` "sc_dir" preview, the `[data-lang]` CSS and the alt-text
  swapping were removed.
- `Bi::html()` / `@bi()` / `HasArabicCopy::bi()` now print only the current language (fallback to the other one when
  empty) instead of two spans.
- `HasArabicCopy` (all content models): `TRANSLATABLE` lists the columns; `initializeHasArabicCopy()` makes the
  `_ar` twins fillable and casts json ones as arrays; `tr($column)` returns the current-language value.
- `ScDataPresenter` uses `tr()` for every translated field; in Arabic, cabins' `nameEn` carries the Arabic name (what
  the scripts print large) and `nameAr` the English one. New `SC_DATA.lang` and `SC_DATA.portNames` (English port /
  country → Arabic, plus "At sea" → "في البحر"; empty in English).
- core.js `SC.port()` shows port names in the page language; used in route lines, sailing cards, catalog filters,
  cruise details (ports, itinerary), booking review, dashboard, destination titles and the homepage slider.
- Also localized: promo label (`POST /booking/promo`), booking confirmation (cruise, ship, cabin), ship page title
  (`#ship-name`, from the database).

**Admin.** `Field::arabic()` adds an Arabic twin (`{name}_ar`, label "(عربي)") right after the field: same type and
length limit, optional, right-to-left input, help "Leave empty to show the English text". `Resource::allFields()`
expands the twins for rules, fill and the form (index columns unchanged). 37 fields marked across Ships, Ship facts,
Life on board, Good to know, Ship FAQs, Facilities, Cabin categories, Cruises, Destinations, Testimonials, Age bands,
Promo codes. The itinerary editor has Arabic time and description boxes per day.

**Data.** Migration `2026_10_11_000025_add_arabic_to_content_tables.php`; `ArabicContentSeeder` (run by
`DatabaseSeeder`) holds the Arabic for all template content and fills **only empty** Arabic columns, so admin edits
are kept. Applied to MySQL with `artisan migrate` + `artisan db:seed --class=ArabicContentSeeder`: **268 texts, 0
without Arabic**.

**Verification:** `artisan test` **80 passed (1014 assertions)**. New `BilingualContentTest`: every DB table is
either a content table with `_ar` columns or listed as not content (a new table fails until classified); every
seeded text has Arabic; `SC_DATA` in Arabic (ship, facts, zones, essentials, FAQs, facilities, cabins, cruises,
itinerary, destinations, port names, testimonials, age bands) with English keys kept; promo label; admin shows
and saves the Arabic boxes on every content screen, English required / Arabic optional, same length limits;
itinerary editor saves Arabic. `HomeContentTest` now checks pages per language via the cookie and that the admin
stays English. Headless browser: عربي → reload → `lang=ar dir=rtl`; ship story, facts, facilities, cabin tabs,
destinations, testimonials, cruise name and itinerary ("سفاجا، مصر", "في البحر") in Arabic; 0 console errors.

### Step 5f: Admin → Cruise pages (2026-10-11)

Request: a menu entry like Homepage for the other pages: "Text, buttons and photos of each ship section, category
section, cruise section, destination section, in page order. Sailings, facilities, cabins, destinations and
testimonials themselves come from their own screens; here you edit the headings around them", in both languages.

Read as the four other public pages: **Ship page**, **Cruises list** (catalog with its categories / filters),
**Cruise details** (shared by every cruise), **Destinations**.

**D17. One section system for every page.** `home_sections` gains a `page` column (migration
`2026_10_11_000026_add_page_to_home_sections.php`; unique `(page, key)` instead of `key`). The homepage keeps
`config/home.php`; the other pages are described in the new **`config/pages.php`** (name, public route, sections
with fields, default photo, per-section admin `labels`, `help`, `can_hide`, English + Arabic content and items).
`HomeSection::pageConfig()`, `pages()`, `forPage()` (`forHome()` = `forPage('home')`), `label()`, `editUrl()`.
New field group `subheading` (one short text, stored in `caption_label`).

| Page | Sections (page order) |
| --- | --- |
| Ship page | Hero (2 buttons + photo; name/tagline/chips stay from Ships), About the ship and facts (heading, facts heading, note), Life on board (heading, text, facilities heading), Cabins, Good to know, Questions, Closing call to action |
| Cruises list | Page heading and search (heading, text, search placeholder), No results (heading, text, button; can't be hidden, catalog.js shows it) |
| Cruise details | Departure dates*, About this cruise, Included in your fare (list), Not included (list), Day-by-day itinerary, Who's travelling*, Choose your cabin*, On board, Health & safety (text + 5 accordion items), Note under the booking summary (* needed to book: can't be hidden) |
| Destinations | Page heading, Closing call to action |

**Admin.** Sidebar **Cruise pages** (under Homepage) → `GET /admin/pages`: the description above, a jump list and one
table per page (photo, section, heading in English and Arabic, shown/hidden, Edit). `GET|PUT
/admin/pages/{page}/{key}` reuse the Homepage editor (`HomeSectionController::editPage/updatePage`), which is now
page-aware (breadcrumb, save URL, labels, help, subheading, no visibility box when `can_hide` is false). The shared
table is `admin/home-sections/table.blade.php`. Uploads are named `{page}-{key}-xxxxxx` in `public/uploads/home/`.

**Frontend.** `PageController::ship/catalog/destinations` and `CruiseController::show` pass `$sections`; the four
templates print every heading, text, button, list row and the ship hero photo from them (`bi()` / `tr()`), with the
`hidden` attribute for hidden sections. `ship.js` no longer overwrites an uploaded hero photo.

**Data.** `HomeSectionSeeder` writes all pages. On MySQL: `artisan migrate`, then a one-off script created only the
missing sections (21 new, the 10 homepage rows untouched).

**Verification:** `artisan test` **87 passed (1193 assertions)**. New `PageSectionsTest`: seeded pages and items;
screen access, order, description, every edit page, per-section labels, 404s; ship page edits in English and Arabic
(cookie); hero photo upload/remove and hidden section; cruise details list edit, Arabic render, locked sections;
catalog placeholder and destinations in both languages; pages render with no rows. `BilingualContentTest` now also
covers the new rows (all have Arabic). Browser: sidebar "Cruise pages", tables and editor render; `/ship` in Arabic
shows "شاهد الرحلات", "صُممت للبحر الأحمر", "حقائق عن السفينة", "كل ما على السطح"; 0 console errors.

### Step 5g: Breadcrumbs and fixed words of the page templates (2026-10-11)

Request: add breadcrumbs, and translate every static word in the page Blade files through the `lang` folder.

- **`lang/{en,ar}/pages.php`** (new, same keys): `breadcrumb.*`, and per page (`home`, `ship`, `catalog`, `cruise`,
  `destinations`) the browser title and meta description, in-page navigation, catalog filter / sort / layout labels,
  buttons ("View all photos", "Expand all", "Continue", "Show results"…), placeholders and screen-reader labels
  (`aria-label`, `sr-only`). Titles take the ship or cruise name (`:ship`, `:cruise`, `:nights`).
- **`partials/breadcrumb.blade.php`** (new): one translated trail (`$items` = [label, url|null], `tone` light/muted,
  optional `currentId`), an `<ol>` with `aria-current="page"` on the current page, and a schema.org
  `BreadcrumbList` JSON-LD pushed to `<head>`. Used on the ship page, cruises list, destinations and cruise details;
  the cruise page's last crumb and `<title>` are now server-rendered from the cruise name (`#crumb-name` kept for
  details.js).
- `pages/*.blade.php`: every remaining fixed word now uses `__('pages.…')` (or `@bi('site.…')` from 5c). Since 5e the
  server knows the language, so `__()` also works inside attributes. The ship page `<h1>` shows the ship name from the
  database instead of the hard-coded "AROYA". A scan of the five templates finds no English text left between tags or
  in `aria-label` / `placeholder` / `alt`.
- **Still English** (generated by the page scripts, not Blade): sailing-card words ("Departs", "Returns", "Length",
  "nights", "from", "From, per person", "Select sailing"), cruise tags, catalog chips and "Visiting / Any port", the
  details booking summary and itinerary toggle text, booking and account screens.

**Verification:** `artisan test` **90 passed (1306 assertions)**. New `PageTranslationTest`: `pages` and `site`
language files have identical keys in both languages; breadcrumbs on the four pages in English and Arabic (labels,
order, `aria-current`, JSON-LD); titles and fixed words per page in both languages, and none of the English ones on
the Arabic page. Browser: Arabic cruises list shows breadcrumb, filters, sort and result count in Arabic; cruise page
breadcrumb "الرئيسية / الرحلات / اكتشاف البحر الأحمر", section nav and title in Arabic; 0 console errors.

### Step 5h: Words built by the page scripts (2026-10-11)

Reported: on the Arabic cruises list the highlights, nights, buttons and many other words were still English (they are
built in the browser by catalog.js and core.js, not by Blade).

- **`lang/{en,ar}/ui.php`** (new, same keys): sailing card (Departs / Returns / Length, "From, per person", "Select
  sailing", "Also departs …", ship and port line, WhatsApp text and label), tags and catalog highlights, favourites
  labels and toasts, catalog "Visiting / Any port", filter chips, pagination, cabin pop-up, terms pop-up, ship page,
  destinations, cruise details (facts, dates, itinerary, capacity warning, cabin cards, price rows, booking summary,
  mobile bar, toasts), homepage cabin showcase, slider, testimonials and newsletter toasts. Plurals use the
  `trans_choice` format with the Arabic 1 / 2 / 3–10 / 11+ forms (nights, days, cruises).
- `Bi::scriptStrings()`: `SC_DATA.i18n.{en,ar}` = `site.php` + `ui.php` (under `ui`); scripts call `SC.t('ui.…')`
  / `SC.tc(…)`. `SC.fmtDate` / `SC.fmtMonth` use `site.date_locale`, so dates read "15 نوفمبر" in Arabic.
- Edited: `core.js` (cards, tags, favourites, error fallback, modal close label, terms and cabin pop-ups),
  `pages/catalog.js`, `home.js`, `ship.js`, `destinations.js`, `details.js` — 92 literal strings replaced, all files
  pass `node --check`.
- In Arabic the small second-language line under cabin and port names is no longer shown (it showed the English
  name); it stays on the English pages, where it shows the Arabic name.
- Terms and cancellation table: Arabic versions in `config/cruise.php` (`text_ar`, `cancellation_ar`), picked by
  `ScDataPresenter::terms()`.
- Header / footer screen-reader labels (menu, logo, WhatsApp button) and the WhatsApp pre-filled message now come
  from `site.php`.

**Verification:** `artisan test` **91 passed**; `PageTranslationTest` now also checks `ui.php` key parity, the
`ui` words in `SC_DATA` and the Arabic terms. Browser scan of every visible word and `aria-label` / `alt` /
`placeholder` / `title` on the home, cruises list, ship, destinations and cruise details pages in Arabic (cabin pop-up
open): the only Latin text left is the brand "Safer" and sailing codes (e.g. RSD-261115). 0 console errors. The
`mm/dd/yyyy` hint in the date filter is the browser's own date control and follows the visitor's browser language.

### Step 6: Add photo slots, and upload photos straight from gallery fields (2026-10-11)

Request: a button to add photo slots, and direct uploads inside a cruise's gallery (the gallery only let you pick
from the 22 fixed slots).

**How galleries and slots relate (unchanged):** every photo on the site is a named slot (`image_slots`); records
store slot keys (`cruises.image_slot` card photo, `cruises.gallery_slots` ordered list, cabins `image_slots`, ship,
zones, destinations). Changing a slot's photo changes it everywhere that slot is used.

- Migration `2026_10_11_000027_add_is_custom_to_image_slots.php`: `image_slots.is_custom` (false for the template's
  22 slots).
- `ImageSlot`: `UPLOAD_DIR`, `fromUpload()` (new custom slot with a unique key made from the name), `storeUpload()`,
  `deleteUpload()` (only inside `public/uploads/image-slots/`), `usageMap()` (slot key → where it is used: ships,
  zones, destinations, cruises incl. soft-deleted, cabins, page-section default photos); `url()` is safe for a slot
  with no photo.
- **Photo slots screen:** "Add a photo slot" (name, optional key, optional recommended size, photo; `POST
  /admin/image-slots`). Each card shows **Used by: …** or "Not used on any page yet", an "Added by you" tag for custom
  slots, Replace photo, and **Delete slot** (`DELETE /admin/image-slots/{key}/slot`) for custom slots that nothing
  uses. Built-in slots can't be deleted; a custom slot's photo can't be removed on its own (it has no fallback).
- **Direct uploads in every photo field** (generic, so cruises, cabins, destinations, ships and life on board all get
  it): gallery fields (`image_slots`) have an "Upload new photos" box (several files, up to 12, 5 MB each); single
  photo fields (`image_slot`, e.g. the cruise card image) have "…or upload a new photo". `Resource::fill()` →
  `storePhotoUploads()` turns each file into a custom slot named after the record ("AROYA – Repositioning – Gallery
  4") and appends it to the gallery in the order picked, or switches the single field to it. Field rules validate
  `{name}_upload`. The form now posts `multipart/form-data`, and photo fields show thumbnails of the chosen photos
  in order.

**Verification:** `artisan test` **95 passed**; new `PhotoSlotsTest`: add a slot (key from name, suffix on clash,
typed key unique, photo required and must be an image), choose it elsewhere; only unused custom slots can be
deleted (usage named in the error, file removed), built-in slots protected; two photos uploaded into a cruise
gallery are appended in order as custom slots, reach the cruise page and show as used; a PDF is rejected with
nothing saved; an upload replaces a destination's single photo and the old slot stays. MySQL migrated. Browser:
Photo slots add form and gallery upload box render; 0 console errors.

### Step 6b: Replace and delete on every photo slot (2026-10-11)

- Every slot card (built-in and custom) has **Replace photo** and **Delete slot**; built-in slots with an uploaded
  photo also keep "Remove uploaded photo" (back to the stock photo).
- Delete works for any slot that nothing uses; otherwise it is refused with the list of places using it. Besides
  records and page-section defaults, `usageMap()` now counts the two slots the page scripts read directly
  (`ScDataPresenter::SCRIPT_SCENES`: `hero`, `exterior`) as "Website template (fallback photo)".
- Fixed: `usageMap()` merged homepage and page sections with `+`, so the ship page's `hero` section was skipped.
- Direct gallery uploads now get short keys (`aroya-repositioning-4`; single photo fields `yanbu-photo`) instead of a
  key cut at 30 characters (an earlier upload on the local DB keeps its key `aroya-repositioning-gallery-sl`).
- Tests: **96 passed**; `PhotoSlotsTest::test_every_slot_has_replace_and_delete` (buttons on every card, replace a
  built-in photo, delete a built-in slot once unused, template slots protected), new key format checked.

### Step 7: Media library replaces photo slots (2026-10-13)

Request: pick photos like the media library in the safeer project (upload, or choose from the server), with no
slot dropdowns; then remove photo slots completely. **This supersedes D7 and Steps 6 / 6b.**

- **Storage:** records hold the image itself, a path under `public/` or a photo URL. Columns: `ships.image` /
  `hero_image`, `ship_zones.image`, `destinations.image`, `cruises.image` / `gallery` (json), `cabin_categories.images`
  (json). Migration `2026_10_13_000001_replace_image_slots_with_direct_images` converts each slot key to what the
  slot showed (upload → `assets/img/aroya/{key}.jpg` → stock URL → SVG) and drops `image_slots`. Uploaded files stay
  where they were (`public/uploads/image-slots/`).
- **Removed:** `ImageSlot`, `Admin\ImageSlotController`, `admin/image-slots` routes and view, the sidebar link,
  `ImageSlotSeeder`, `SC_DATA.imageFallbacks` (now `{}`: broken images show core.js's placeholder). Seeders use
  `Database\Seeders\StockPhotos` (the template's stock URLs by name). `SC_DATA.images` only has `shipHero` /
  `shipSea`, from the ship's photos; page-section defaults use `['ship' => 'image' | 'hero_image']`.
- **`App\Support\MediaLibrary`:** `url()` / `urls()` for the site, `store()` for uploads (`public/uploads/media/
  {record}-{n}-xxxxxx.jpg`), `items()` for the library (images under `public/uploads` and `public/assets/img/aroya`,
  newest first, plus the photo URLs records use), `isValid()` (library file or http(s) URL),
  `maxUploadBytes()` (5 MB or PHP's lower `upload_max_filesize` / `post_max_size`).
- **Admin:** field types `image` / `images` (`Field::image()`, `Field::images()`) render
  `admin.partials.media-picker`; the shared modal `admin.partials.media-library` loads `GET admin/media-library`
  (24 per page, search, load more on scroll). Galleries: drag to reorder, × to remove, multi-select from the library,
  new uploads previewed (removable) and appended on save. Oversized files are refused in the browser and get a clear
  message on the server (PHP's "failed to upload" happened when a photo was bigger than the server's limit).
- Tests: **89 passed**; new `MediaLibraryTest` (slots gone, library listing/search, pick + reorder + remove, uploads,
  limits, single photo upload / clear). Local MySQL migrated (backup taken first); cruise 4 kept its 4 photos.

### Step 8: Customers vs staff, customer export, Seller role, bookings assigned to sellers (2026-10-14)

- **Roles** (`App\Enums\UserRole`): `customer`, `seller`, `admin`. `User::isStaff()` (admin or seller), scopes
  `customers()`, `staff()`, `sellers()`. Gates: `admin-panel` (staff; the whole `/admin` group), `admin` (admins;
  every admin route except the dashboard and bookings, see `routes/admin.php`), `view-booking` (admins, or the
  seller the booking is assigned to; also opens the guest voucher from the panel). The admin sign-in accepts staff.
- **Sellers** see a short menu (Dashboard, My bookings) and only their assigned bookings: list, details, notes,
  record payments. Dashboard figures cover their bookings; the sailings box is hidden. Cancelling, removing payment
  entries and (re)assigning are admin-only.
- **Customers** (`/admin/customers`, `CustomerResource`): only `role = customer`; search, filters (nationality,
  has / no bookings, registration dates), details page (`customers/{id}`: profile, totals, bookings), edit
  (Account type promotes to Seller / Admin), delete only without bookings. **Export to Excel**
  (`customers/export`): the filtered list as UTF-8 CSV with BOM (Arabic shows in Excel), columns ID, names, email,
  phone `(+966) 5…`, nationality, registered, email verified, bookings, confirmed bookings, last booking, status;
  cells starting with `= + - @` are prefixed with `'`. No Excel package added (composer.json has none).
- **Admins & staff** (`/admin/users`, `UserResource`): only admins and sellers; Role select (Admin / Seller /
  Customer), assigned bookings count with a link to them. Each screen 404s for people of the other kind
  (`Resource::scope()`).
- **Bookings:** migration `2026_10_14_000001_add_seller_id_to_bookings_table` (`seller_id` → users, null on delete);
  `Booking::seller()`, `User::assignedBookings()`. Admins pick the seller on each row of the bookings tables and on the
  booking page (`PATCH bookings/{ref}/seller`, sellers only), and filter the list by seller or Unassigned.
- Generic resources gained `scope()`, `filter()`, `indexQuery()` and `indexToolbar()`. Seeder adds the demo seller.
- Tests: **98 passed**; new `StaffCustomersSellersTest` (separation and 404s, filters and details, admin-only
  screens, CSV export incl. BOM / Arabic / formula guard / filters, creating sellers and seller sign-in,
  assign / reassign / unassign / filter / seller deleted, seller limited to own bookings). Local MySQL migrated
  (backup first), Apache smoke test as seller and admin.

### Step 9: Tiered cabin pricing, factors removed, next sailing on the cruises list (2026-10-18)

New business rules: a cabin is priced by how many people share it, not by multiplying a per-person rate.

**Pricing rules** (section 2 has the full formula; `PricingService::fareLines()` and `SC.fareLines()` are the same):

| Party (adults + children, infants) | Fare |
| --- | --- |
| 1 paying guest (± infants) | Single |
| 2 paying guests (± infant) | Double |
| 3 paying guests | Double + 3rd guest (extra) |
| Infants | Free ($0); see step 11 for how they count towards the cabin limit |

> **Superseded by step 11:** the capacity rule below (3 adults and children + up to 4 infants on top) was replaced
> the same day. Infants now count towards the 3-guest limit and each needs an adult. Pricing is unchanged.

- A cabin holds **at most 3 adults and children** (`config('cruise.max_cabin_guests')`, `CabinCategory::capacity()`),
  plus **up to 4 infants in every cabin** (`config('cruise.max_infants_per_cabin')`), who are free and take no paid
  place. The old "one infant per adult" rule was removed so the 4 infants are reachable; at least 1 adult is still
  required. A child as 2nd guest shares the double price; the extra price is for the 3rd guest, adult or child.
- Fees (Age bands, per guest per night), promo codes and the booking total are unchanged.
- Quote lines are now `single` / `double` / `extra` / `infant` (labels in `lang/{en,ar}/site.php` → `pricing`), stored in
  `bookings.price_snapshot` as before. Old bookings keep their old adult/child lines and still display.
- "From, per person" on cards and date chips = cheapest double price ÷ 2 (two sharing), display only.

**Database** (migration `2026_10_18_000001_switch_to_tiered_cabin_pricing`, reversible):

| Table | Change |
| --- | --- |
| `sailing_cabin_prices` | + `price_single`, `price_double`, `price_extra` (decimal 10,2, whole cabin, whole cruise); − `price_adult`, `price_child`, `price_infant`. Existing rows backfilled: single = old adult, double = 2 × old adult, extra = old child, so 1–2 adult bookings cost the same as before |
| `sailings` | − `price_factor` |
| `cruises` | − `price_factor` |
| `cabin_categories` | − `base_rate_adult`, `base_rate_child`, `base_rate_infant` (the rate card); `max_guests` above 3 set to 3 |

**Backend:** `PricingService` lost `rateCardPrices()` / `syncSailingPrices()`; it has `fareLines()`, `quote()` and
`latestPrices($cruise)` (starting prices for a new sailing). `SailingCabinPrice::tiers()`. `Sailing::fromPrice()` uses
the double price. `ConfirmBookingRequest` checks adults + children against the cabin (max 3) and infants against 4. `ScDataPresenter`
sends `prices: {single, double, extra}` per sailing and cabin plus `maxCabinGuests` and `maxInfants`; `rates`, `priceFactor` and `factor` are
gone.

**Front end:** `SC.prices()` reads the stored tiers only (no formula fallback); new `SC.fareLines()`; `SC.quote()`
builds its lines from it. Details page cabin cards show "Cabin price for the whole cruise: 1 guest / 2 guests /
3rd guest / Infant Free"; summary, review and catalog follow. A cabin with no prices on a sailing can't be chosen.

**Admin panel:**

- **Sailing form** (create and edit): price grid per cabin with **Single (1 guest)**, **Double (2 guests)**,
  **3rd guest (adult or child)**, Infant "Free", cabins available, sold out. All three prices are required. On create
  the grid is filled from the cruise's latest sailing. The note explains the tiers and the 3-guest limit.
- **Removed:** the cruise "Price factor" field, the sailing "Price factor" field, the "Recalculate prices" button and
  its route (`POST sailings/{sailing}/recalculate`), the cabin per-night rates, the "Factor" column and the
  "price factor ×" subtitle on the sailings list, and the old note "Rate card = cabin rate × nights × cruise factor ×
  sailing factor…".
- **Sailings list:** "Cabin prices (single / double / 3rd guest)" per cabin.
- **Cabin categories:** "Maximum guests" defaults to 3 and accepts 1–3.
- **Cruises list:** new **Next sailing** column: the closest departure from today that isn't cancelled
  (`withMin` on `sailings.departure_date`), with "In N days", or "None scheduled". Generic index views now format
  index-only date fields.

**Seed data:** cruises carry explicit `[single, double, extra]` prices per cabin, used for all their sailings; all
cabins hold 3; "up to four guests" copy changed to three (English and Arabic). Demo past sailings copy the cruise's
prices.

- Tests: **106 passed**. `PricingServiceTest` rewritten (single, double, child as 2nd guest, 3rd guest, infants free,
  family with promo, from price, latest prices); `AdminPanelTest` (tier grid required and saved, prefilled create
  form, no factor / recalculation, cabins of another ship rejected, next sailing column skips cancelled, cabin
  limit 3); `BookingFlowTest` (server total 8,800 for 2 adults + 1 child with SAFER10; full cabin + 4 free infants
  accepted, 5 infants rejected); `PublicSiteTest` (tier prices, limits 3 and 4 in `SC_DATA`). Migration checked up
  and down on SQLite.
- **To apply locally:** `C:\xampp\php\php.exe artisan migrate` (back up `safer_cruise_db` first), then review the
  prices of each sailing in Admin → Cruises → Sailings.
- Applied 2026-10-18: backup `C:\Users\admin\Downloads\files\db-backups\safer_cruise_db-2026-10-18-before-tiered-pricing.sql`,
  migration run on MySQL, XAMPP copy synced.

### Step 10: Sign in, register, booking, confirmation and account in Arabic and English (2026-10-18)

The browsing pages were already bilingual (steps 5b–5h). The remaining pages and every message the visitor can see
now follow the header's عربي button too.

**How it works** (unchanged mechanism, now covering every public page):

| Layer | Reads | Notes |
| --- | --- | --- |
| Language | `sc_lang` cookie → `App\Http\Middleware\SetLocale` → `app()->getLocale()` | Admin panel always English |
| Direction | `layouts/app.blade.php`: `<html lang dir="rtl|ltr">` | Email, password, phone and booking reference stay `dir="ltr"` inside Arabic pages |
| Blade | `{{ __('site.…') }}`; links inside sentences as `:placeholders` with `{!! !!}` and escaped values | |
| Page scripts | `SC.t('booking.…')`, `SC.tc('account.sails_in', n)` from `SC_DATA.i18n` (`Bi::scriptStrings()`: all of `site.php`, plus `ui.php` under `ui`) | Nothing to register: new `site.php` keys reach the scripts automatically |
| Server messages | `__('site.…')` in controllers, `ConfirmBookingRequest`, `AgeBandValidator`, `BookingService`; Laravel's own rules from the new `lang/ar/validation.php` | Same words as the browser checks |
| Dates | Blade: `translatedFormat('j M Y')` (Carbon follows the locale: "13 ديسمبر 2026"); scripts: `SC.fmtDate` | Western digits everywhere, as for prices |
| Form values | Gender and nationality are stored in English (the server validates them); shown via `site.genders` / `site.nationalities` (`SC.genderLabel`, `SC.natLabel`, `Bi::value()`) | Ports via `Bi::port()` (server) / `SC.port()` (scripts) |

**New keys in `lang/{en,ar}/site.php`** (same keys in both files; `PageTranslationTest` checks it):

| Group | Used by | Contents |
| --- | --- | --- |
| `auth.*` (20) | `auth/login`, `auth/register`, `Auth\LoginController`, `Auth\RegisterController` | titles, intros, labels (first/last name, email, password, repeat), hint, remember me, buttons, toggle links, wrong-password and name/email errors |
| `booking.*` (~65) | `booking/show`, `booking.js`, `confirmation` labels | stepper, empty state, traveller form labels, contact block, review sections (trip, cabin, travellers), price table, promo code, total note, terms sentence, confirm / confirming / failed |
| `booking.errors.*` (37) | `booking.js`, `SC.checkBand`, `ConfirmBookingRequest`, `AgeBandValidator`, `BookingService`, `BookingController::promo` | field errors (name, email, phone per country, gender, nationality), the "N fields need attention" plural, date-of-birth rules per band, sold out, cabin too small, infant needs an adult (step 11), promo invalid |
| `genders`, `nationalities` | traveller forms, review, voucher, profile | English value → label; the English `nationalities` list is built from `config('cruise.nationalities')` |
| `confirmation.*` (11) | `booking/confirmation` | headings, "details sent to…" sentence, reference, status badges, total, voucher buttons |
| `account.*` (~75) | `account/dashboard`, `dashboard.js`, `AccountController` | menu, overview, stats, booking tabs and cards, status and payment tags, "sails in N days" plural, booking modal, favourites, profile and password forms, notifications, toasts |
| `errors.*` (4) | `errors/403` (website branch) | |

Plus `lang/ar/validation.php`: Arabic for Laravel's rules used on the website (required, email, confirmed, unique,
min/max, password numbers…) and attribute names (البريد الإلكتروني، كلمة المرور…).

**Pages changed:** `auth/login`, `auth/register`, `auth/partials/field` (ltr for email/password), `booking/show`,
`booking/confirmation`, `account/dashboard`, `errors/403`. **Scripts:** `core.js` (`SC.checkBand` messages,
`SC.genderLabel`, `SC.natLabel`), `booking.js` and `dashboard.js` (no English left; option values stay English, the
text is translated; saved price lines are labelled from their band so old bookings show in the page language).

- Tests: **112 passed**; new `LocalizedPagesTest` (sign in / register in both languages with `dir`, Arabic errors
  for wrong password, Laravel rules and duplicate email, booking page and its `SC_DATA.i18n` words, Arabic server
  errors for date of birth / phone / promo, Arabic voucher with Arabic month and nationality, account page and
  password error). Checked in a headless browser on `localhost/cruise/public` in Arabic: booking with a sample
  booking (form, plural error summary, age message, review, summary), sign in, account overview, bookings, booking
  modal, notifications. No console errors.

### Step 11: Cabin capacity counts infants; every infant needs an adult (2026-10-18)

Replaces the capacity rule of step 9 ("3 adults and children, plus up to 4 free infants"). Tiered pricing is unchanged.

**Rules** (one cabin per booking):

| Rule | Detail |
| --- | --- |
| Cabin limit | **adults + children + infants ≤ 3** (`config('cruise.max_cabin_guests')`, `CabinCategory::capacity()` = the cabin's own `max_guests`, never above 3) |
| Infant with adult | A party with infants must include **at least one adult**. Any number of infants is fine within the limit (1 adult + 2 infants is allowed) |
| At least one adult | Unchanged: every booking needs an adult |
| Price | Unchanged: adults + children pick the tier (single / double / double + extra); infants are $0 and pay no fees |

Allowed: 1A, 2A, 3A, 1A+1C, 1A+2C, 2A+1C, 1A+1I, 1A+2I, 2A+1I, 1A+1C+1I. Blocked: anything above 3 people,
and any party without an adult (e.g. 1 child + 1 infant).

**Removed:** `config('cruise.max_infants_per_cabin')` (4), `SC_DATA.maxInfants`, the "too many infants" message, the
separate infant counter limit in `details.js` and `home.js`, and the home search widget's old limit of 6 adults /
6 children (now the same 3-guest rule).

**Backend:** `ConfirmBookingRequest` checks `infant > 0 && adult < 1` (`booking.errors.infant_needs_adult`) and
`adult + child + infant > capacity` (`booking.errors.cabin_too_small`, now "fits up to :max guests including
infants"); `guests.*` are capped at 3; with infants in the party, the "at least one adult" error explains that infants
need an adult.

**Frontend** (`core.js`, shared by the pages):
- `SC.guestLimits(band, guests, cap)`: min/max for each +/- counter: adults 1…room, children 0…room, infants 0…room
  and **0 when there is no adult**; room = cap − the other guests.
- `SC.guestError(guests, cap)`: same checks as the server, in the page language.
- `SC.fitGuests(guests, cap)`: trims guest counts saved under older rules (infants first, then children, then adults).
- `SC.guestTotal(guests)`.
- `details.js`: counters use `SC.guestLimits` with the largest cabin still on sale; the "cabin too small" warning and
  disabled cabins count infants. `home.js`: search counters use the same rule. `booking.js`: blocks the review step
  with `SC.guestError` and a link back to the cabins if the saved party breaks the rules; "Guests in cabin" shows
  everyone, e.g. "3 of 3".

**Admin:** sailing form and cabin category help say "at most 3 guests, infants included; infants with an adult".

- Tests: **112 passed**. `BookingFlowTest`: 2A+1C+1I, 1A+3I and 2A+2C rejected; 1C+1I without an adult rejected with
  the infant message; 1A+2I booked at the single price ($5,460 + fees for 1 adult) and 2A+1I at the double price.
  `PublicSiteTest`: `maxCabinGuests` 3 and no `maxInfants`. Checked in a headless browser on the details page: a
  saved 2A+1C+4I party is trimmed to 2A+1C, counters stop at 3 people, infant "+" is only enabled with an adult and
  room, adults never go below 1. No console errors.

### Step 12: Fix empty cruise list and $NaN prices after the tiered-pricing deploy (2026-10-18)

Reported on `cruise.safer.travel`: the cruise list showed "0 sailings", and the cruise page showed `$NaN` and raw keys
(`ui.details.adult`).

- **Empty list (bug from step 9):** `catalog.js` `priceFor()` still read `SC.prices(…).adult`, which tiered pricing
  removed. Every price became `NaN`, so the "price ≤ maximum" filter dropped every sailing. Now uses `.perPerson`
  (cheapest double ÷ 2), like the cards. The same leftover in `ship.js` ("from $… pp" on cabins) is fixed too.
- **`$NaN` and `ui.details.adult` on the cruise page:** the server was already sending the new files, but the browser
  ran its cached copy of the old `details.js` / `core.js` against the new data and words. IIS sends no
  `Cache-Control` for static files and the script URLs never changed. New `App\Support\Asset::url()` and Blade
  `@asset('assets/js/core.js')` add `?v=<file modification time>` to every script and stylesheet in
  `layouts/app`, `layouts/admin`, `admin/login` and the page views, so each deploy forces a fresh download.
- Checked in a headless browser: every public page in English and Arabic has no `NaN` and no raw keys, and the local
  catalog lists its sailings. The live catalog with the fixed `catalog.js` substituted lists its sailings again.
  Tests: **113 passed** (new: versioned script and style URLs).
- **Deploy:** upload `public/assets/js/pages/catalog.js`, `public/assets/js/pages/ship.js`, `app/Support/Asset.php`,
  `app/Providers/AppServiceProvider.php` and the views above, then run `php artisan view:clear` on the server.

### Step 13: Infant age rule checked on the booking page and server (2026-10-18)

**Rule:** an infant must be **at least 6 months and under 24 months (2 years)** old **on the departure date** of the
sailing (not today). Age is counted in whole months: `(departure year − birth year) × 12 + (departure month − birth
month)`, minus 1 if the departure day is before the birthday day. So for a 15 Nov 2026 departure, a baby born
15 May 2026 (6 months) is accepted and one born 16 May 2026 (5 months) is not. The limits come from Admin → Age bands
(`age_bands`: infant `min_months` 6, `max_months_exclusive` 24, seeded by `AgeBandSeeder`).

**Message** (`booking.errors.dob_infant`, plural on the upper limit in years so the Arabic stays correct if the band
changes):
- EN: "Infants must be at least 6 months and under 2 years old on the departure date."
- AR: "يجب أن يكون عمر الرضيع 6 أشهر على الأقل وأقل من سنتين في تاريخ المغادرة." (was "وأقل من 2 سنة")

**Where it is enforced:**
- Browser: `SC.checkBand('infant', dob, departure)` (`core.js`) for every `[data-rule="dob"]` field in `booking.js`.
  The infant's date field turns red with the message under it, the summary box says "1 field needs attention", focus
  moves to the field, and the guest can't go on to the review / confirm step, also not by typing `#review` in the
  address bar. There is no online payment step; confirming happens on the review step. The date picker's
  `min`/`max` also limit infants to 2 years minus a day … 6 months before departure.
- Server: `ConfirmBookingRequest::checkTravellers()` → `App\Services\AgeBandValidator::check()` for every traveller,
  error on `travellers.infant-N.dob` as "Infant 1: …" / "رضيع 1: …"; the booking is not created.
- Fixed on the way: pressing "Review booking" right after typing a date blurred the field, its error line pushed the
  button down 16 px and the click was lost (a second click was needed). The blur check now waits when the button is
  being pressed.

- Tests: **116 passed**. `AgeBandValidatorTest`: 5 months, a few weeks and newborn rejected with the exact English
  message; Arabic message; 6 months exactly accepted; age counted on the departure date (same baby fine on 15 Nov,
  rejected on 1 Nov). `BookingFlowTest`: an infant 1 day short of 6 months blocks `POST /booking/confirm` (English and
  Arabic messages, no booking saved); 6 months exactly is booked. Checked in a headless browser in English and Arabic.

### Step 14: Adults are 18+, children 2–17 (2026-10-18)

Business rule: anyone under 18 on the departure date books as a child. Infants unchanged.

| Band | Before | Now | `min_months` | `max_months_exclusive` | Fee / night |
| --- | --- | --- | --- | --- | --- |
| Adult | 12+ | **18+** | 144 → **216** | none | $35 (unchanged) |
| Child | 2–11 | **2–17** | 24 | 144 → **216** | $35 (unchanged) |
| Infant | 6 months – under 2 | unchanged | 6 | 24 | $0 |

**Where the limits live:** only in the `age_bands` table (Admin → Pricing & offers → Age bands & fees). Everything
else reads it: `AgeBandValidator` and `ConfirmBookingRequest` on the server, `ScDataPresenter` →
`SC_DATA.ageBands` (`minYears` 18, child `maxYears` 17) → `SC.checkBand`, the guest counter hints, the traveller form
hints and the error messages ("Adults must be :min or older", "Children must be :min–:max years old"). No column or
code change was needed for the check itself.

**Changes:**
- Migration `2026_10_18_000002_adults_from_eighteen` (data only, reversible): adult `min_months` 216, child
  `max_months_exclusive` 216; hints (EN and AR) replaced only where they still have the old seeded text, so wording an
  admin wrote is kept.
- `AgeBandSeeder` / `ArabicContentSeeder`: same values and hints ("18 years and over" / "18 سنة فأكثر",
  "2 to 17 years" / "من 2 إلى 17 سنة") for fresh installs. Fallback hints in `lang/{en,ar}/site.php` → `bands`.
- `booking.js`: the date pickers' `min`/`max` were hard-coded to 12 and 2 years; now built from `SC_DATA.ageBands`, so
  they follow the admin values (adult up to 18 years before departure, child 18 years minus a day … 2 years, infant
  2 years minus a day … 6 months).
- Admin → Age bands: help text gives 18 years = 216 months and says the bands must touch (Adult minimum = Child
  "Under", Child minimum = Infant "Under").
- **Pricing:** unchanged. Adults and children are both paying guests for the Single / Double / 3rd-guest tiers, and
  their fees are the same, so moving 12–17 year-olds from adult to child does not change any total.
- **Existing bookings:** untouched. Travellers keep the band they were booked in; the new ages only apply to new
  bookings.
- `config/cruise.php`: `max_cabin_guests` is now 10 (set by the owner); its comment no longer says 3, and the tests
  read the value from the config instead of assuming 3.

- Tests: **116 passed**. `AgeBandValidatorTest`: 12 and 17 are children, 18 on departure day must be an adult, a
  16-year-old adult is rejected (English and Arabic). `BookingFlowTest`: a 12-year-old child books; 18 as a child and
  17 as an adult are rejected by `POST /booking/confirm`. Applied locally (backup
  `db-backups\safer_cruise_db-2026-10-18-before-adults-18.sql`, migration run) and checked in a headless browser in
  English and Arabic: counter and form hints, date-picker limits, the 17-year-old-adult message.
- **Deploy:** upload the files above, run `php artisan migrate` (back up first) and `php artisan view:clear`.

**Open items (next):**

1. Password reset (and optionally email verification) for customer accounts.
2. Arabic copy: the whole public website is bilingual since step 10. Still English: the booking emails
   (`resources/views/mail/*`), promo-code labels (database column without an Arabic twin) and the admin panel
   (English by design).
3. Phase 7: Tailwind via Vite instead of the CDN; production `.env` (`APP_DEBUG=false`, real mailer,
   `CRUISE_TEAM_EMAIL`); back up `public/uploads` with the database.
4. Optional: move page sections from JS rendering to server-rendered Blade for SEO (cruise details first).
