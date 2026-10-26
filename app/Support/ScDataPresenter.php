<?php

namespace App\Support;

use App\Enums\TravellerBand;
use App\Models\AgeBand;
use App\Models\Booking;
use App\Models\CabinCategory;
use App\Models\Cruise;
use App\Models\Destination;
use App\Models\Facility;
use App\Models\Highlight;
use App\Models\Port;
use App\Models\Sailing;
use App\Models\SiteSetting;
use App\Models\Ship;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds window.SC_DATA from the database in the exact shape of legacy-html/assets/js/data.js,
 * so the template's core.js and pages/*.js run unchanged (decision D1 in PROJECT_STEPS.md).
 *
 * IDs are the public keys the template uses: ship/cabin/cruise slugs and sailing codes.
 */
class ScDataPresenter
{
    /**
     * @param  array  $page  Page-specific values exposed as SC_DATA.page (e.g. the cruise on the details page).
     */
    public function make(?User $user = null, array $page = [], ?array $account = null): array
    {
        return array_filter([
            'currency' => config('cruise.currency'),
            'currencySymbol' => config('cruise.currency_symbol'),
            'whatsappNumber' => SiteSetting::whatsapp(), // Admin → Footer & contact
            'routes' => $this->routes(),
            'user' => $user ? ['name' => $user->name, 'isAdmin' => $user->isStaff()] : null,
            'favorites' => $user ? $user->favorites()->pluck('slug')->all() : [],
            'images' => $this->scenes(),
            // Broken images fall back to core.js's placeholder drawing
            'imageFallbacks' => (object) [],
            'ageBands' => $this->ageBands(),
            // Guests per cabin, infants included (cabins[].maxGuests is each cabin's own limit, never above this)
            'maxCabinGuests' => (int) config('cruise.max_cabin_guests'),
            // Cabins in one booking (step 15)
            'maxCabinsPerBooking' => (int) config('cruise.max_cabins_per_booking'),
            // Promo codes are checked on the server (POST /booking/promo), never published here.
            'promos' => (object) [],
            'ships' => $this->ships(),
            'cabins' => $this->cabins(),
            'cruises' => $this->cruises(),
            // Admin → Cruises → Highlights: the filter on the cruises list and the tags on cards / cruise pages
            'highlights' => $this->highlights(),
            'destinations' => $this->destinations(),
            'facilities' => $this->facilities(),
            'testimonials' => $this->testimonials(),
            'nationalities' => config('cruise.nationalities'),
            'dialCodes' => config('cruise.dial_codes'),
            'terms' => $this->terms(),
            // Fixed website words in both languages (lang/{en,ar}/site.php), read by SC.t() / SC.tc()
            'i18n' => Bi::scriptStrings(),
            'lang' => app()->getLocale(),
            // Port and country names for display in the current language (routes keep the English names as keys)
            'portNames' => (object) $this->portNames(),
            'page' => (object) $page,
            'account' => $account,
        ], fn ($value) => $value !== null);
    }

    /**
     * What core.js needs on pages that don't run a template page script (sign-in, confirmation):
     * routes, currency, the policy modal and the signed-in state.
     */
    public function minimal(?User $user = null): array
    {
        return [
            'currency' => config('cruise.currency'),
            'currencySymbol' => config('cruise.currency_symbol'),
            'whatsappNumber' => SiteSetting::whatsapp(), // Admin → Footer & contact
            'routes' => $this->routes(),
            'user' => $user ? ['name' => $user->name, 'isAdmin' => $user->isStaff()] : null,
            'favorites' => [],
            'imageFallbacks' => (object) [],
            'terms' => $this->terms(),
            'i18n' => Bi::scriptStrings(),
        ];
    }

    /**
     * SC_DATA.account for the dashboard: profile and bookings in the template's booking shape.
     */
    public function account(User $user): array
    {
        $prefs = $user->notification_prefs ?? [];

        return [
            'profile' => [
                'first' => $user->first_name ?? '',
                'last' => $user->last_name ?? '',
                'email' => $user->email,
                'dial' => $user->phone_dial ?? '+966',
                'phone' => $user->phone ?? '',
                'dob' => $user->date_of_birth?->toDateString() ?? '',
                'nationality' => $user->nationality ?? '',
                'notif' => [
                    'offers' => (bool) ($prefs['offers'] ?? false),
                    'whatsapp' => (bool) ($prefs['whatsapp'] ?? false),
                    'reminders' => (bool) ($prefs['reminders'] ?? true),
                ],
            ],
            'bookings' => $user->bookings()
                ->with(['sailing.cruise.ship', 'cabinCategory', 'cabins.cabinCategory', 'travellers.cabin'])
                ->latest()
                ->get()
                ->map(fn (Booking $booking) => $this->booking($booking))
                ->all(),
        ];
    }

    /**
     * One booking in the shape booking.js stores and dashboard.js reads.
     */
    public function booking(Booking $booking): array
    {
        $sailing = $booking->sailing;
        $cruise = $sailing->cruise;

        return [
            'ref' => $booking->reference,
            'status' => $booking->status->value,
            'paymentStatus' => $booking->payment_status->value,
            'cruiseId' => $cruise->slug,
            'sailingId' => $sailing->code,
            'cruiseName' => $cruise->tr('name'),
            'ship' => $cruise->ship->tr('name'),
            'image' => MediaLibrary::url($cruise->image),
            'departure' => $sailing->departure_date->toDateString(),
            'returnDate' => $sailing->return_date->toDateString(),
            'nights' => $cruise->nights,
            'departurePort' => $cruise->departure_port,
            'arrivalPort' => $cruise->arrival_port,
            'cabinId' => $booking->cabinCategory->slug,
            'guests' => $booking->guestCounts(),
            // Every cabin of the booking (step 15); travellers below carry the position of their cabin
            'cabins' => $booking->cabins->map(fn ($c) => [
                'position' => $c->position, 'cabinId' => $c->cabinCategory->slug, 'guests' => $c->guestCounts(), 'fare' => (float) $c->fare_total,
            ])->values()->all(),
            'amountDue' => (float) $booking->grand_total,
            'amountPaid' => (float) $booking->amount_paid,
            'quote' => $booking->price_snapshot,
            'travellers' => $booking->travellers->mapWithKeys(fn ($t) => [
                "{$t->band->value}-{$t->slot}" => [
                    'first' => $t->first_name,
                    'last' => $t->last_name,
                    'dob' => $t->date_of_birth->toDateString(),
                    'gender' => ucfirst($t->gender->value),
                    'nationality' => $t->nationality,
                    'cabin' => $t->cabin?->position ?? 1,
                ],
            ])->all(),
            'voucherUrl' => route('booking.confirmation', $booking),
        ];
    }

    private function routes(): array
    {
        return [
            'home' => route('home'),
            'catalog' => route('cruises.index'),
            'ship' => route('ship'),
            'destinations' => route('destinations.index'),
            'details' => route('cruises.show', ['cruise' => '__cruise__']),
            'booking' => route('booking.show'),
            'bookingPromo' => route('booking.promo'),
            'bookingConfirm' => route('booking.confirm'),
            'newsletter' => route('newsletter.store'),
            'login' => route('login'),
            'logout' => route('logout'),
            'account' => route('account'),
            'accountProfile' => route('account.profile'),
            'accountNotifications' => route('account.notifications'),
            'accountPassword' => route('account.password'),
            'favorite' => route('account.favorites.store', ['cruise' => '__cruise__']),
        ];
    }

    /** The ship photos the page scripts read from SC_DATA.images when the page has none (home.js, ship.js). */
    private function scenes(): array
    {
        $ship = Ship::first();

        return array_filter([
            'shipHero' => MediaLibrary::url($ship?->hero_image),
            'shipSea' => MediaLibrary::url($ship?->image),
        ]);
    }

    private function ageBands(): array
    {
        return AgeBand::orderBy('sort_order')->get()->mapWithKeys(function (AgeBand $band) {
            $base = ['label' => $band->tr('label'), 'labelAr' => $band->label_ar, 'hint' => $band->tr('hint')];
            $max = $band->max_months_exclusive;

            return [$band->key->value => match ($band->key) {
                TravellerBand::Adult => $base + ['minYears' => intdiv($band->min_months, 12)],
                TravellerBand::Child => $base + ['minYears' => intdiv($band->min_months, 12), 'maxYears' => intdiv($max, 12) - 1],
                TravellerBand::Infant => $base + ['minMonths' => $band->min_months, 'maxMonthsExclusive' => $max],
            }];
        })->all();
    }

    private function ships(): array
    {
        return Ship::with(['specs', 'zones', 'essentials', 'faqs'])->get()->map(fn (Ship $ship) => [
            'id' => $ship->slug,
            'name' => $ship->tr('name'),
            'image' => MediaLibrary::url($ship->image),
            'tagline' => $ship->tr('tagline'),
            'story' => $ship->tr('story') ?? [],
            'zones' => $ship->zones->map(fn ($z) => [
                'icon' => $z->icon, 'title' => $z->tr('title'), 'image' => MediaLibrary::url($z->image), 'text' => $z->tr('text'),
            ])->all(),
            'essentials' => $ship->essentials->map(fn ($e) => [$e->icon, $e->tr('title'), $e->tr('text')])->all(),
            'faqs' => $ship->faqs->map(fn ($f) => [$f->tr('question'), $f->tr('answer')])->all(),
            'specs' => $ship->specs->map(fn ($s) => [
                'key' => $s->key, 'value' => $s->tr('value'), 'unit' => $s->tr('unit') ?? '', 'label' => $s->tr('label'),
            ])->all(),
        ])->all();
    }

    private function cabins(): array
    {
        return CabinCategory::orderBy('sort_order')->get()->map(fn (CabinCategory $cabin) => [
            'id' => $cabin->slug,
            'order' => $cabin->sort_order,
            'badge' => $cabin->tr('badge') ?? '',
            // In Arabic the Arabic name is the main one (nameEn is what the page scripts print large)
            'nameEn' => Bi::isArabic() ? ($cabin->name_ar ?: $cabin->name_en) : $cabin->name_en,
            'nameAr' => Bi::isArabic() ? $cabin->name_en : $cabin->name_ar,
            'short' => $cabin->tr('short_description'),
            'description' => $cabin->tr('description'),
            'maxGuests' => $cabin->capacity(),
            'size' => $cabin->tr('size'),
            'balcony' => $cabin->tr('balcony_size'),
            'beds' => $cabin->tr('beds'),
            'bedroom' => $cabin->tr('bedroom'),
            'features' => $cabin->tr('features') ?? [],
            'images' => MediaLibrary::urls($cabin->images),
        ])->all();
    }

    /**
     * Active cruises with their bookable (upcoming, open) sailings. Cruises with no upcoming
     * sailing are left out, since every template page expects at least one departure.
     */
    private function cruises(): array
    {
        return Cruise::active()
            ->with([
                'ship', 'itinerary', 'ports',
                'sailings' => fn ($q) => $q->upcoming()->with('cabinPrices.cabinCategory'),
            ])
            ->get()
            ->filter(fn (Cruise $cruise) => $cruise->sailings->isNotEmpty())
            ->map(fn (Cruise $cruise) => [
                'id' => $cruise->slug,
                'shipId' => $cruise->ship->slug,
                'name' => $cruise->tr('name'),
                'nameAr' => $cruise->name_ar,
                'nights' => $cruise->nights,
                'departurePort' => $cruise->departure_port,
                'arrivalPort' => $cruise->arrival_port,
                // English port names (the keys core.js filters on); SC.port() shows them in Arabic via portNames
                'route' => $cruise->routeNames(),
                'tags' => $cruise->tags ?? [],
                'visaFree' => $cruise->visa_free,
                'rating' => (float) $cruise->rating,
                'reviews' => $cruise->reviews_count,
                'popularity' => $cruise->popularity,
                'image' => MediaLibrary::url($cruise->image),
                'gallery' => MediaLibrary::urls($cruise->gallery),
                'summary' => $cruise->tr('summary'),
                'itinerary' => $cruise->itinerary->map(fn ($day) => [
                    'port' => $day->port, 'time' => $day->tr('time_label'), 'text' => $day->tr('description'),
                ])->all(),
                'sailings' => $cruise->sailings->map(fn (Sailing $sailing) => [
                    'id' => $sailing->code,
                    'departure' => $sailing->departure_date->toDateString(),
                    'soldOut' => $sailing->cabinPrices->where('is_sold_out', true)->map(fn ($p) => $p->cabinCategory->slug)->values()->all(),
                    // Tiered cabin prices for the whole cruise (SC.prices / SC.quote in core.js)
                    'prices' => $sailing->cabinPrices->mapWithKeys(fn ($p) => [$p->cabinCategory->slug => [
                        'single' => (float) $p->price_single,
                        'double' => (float) $p->price_double,
                        'extra' => (float) $p->price_extra,
                    ]])->all(),
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /** Active highlights in order; cruises[].tags hold their keys. */
    private function highlights(): array
    {
        return Highlight::where('is_active', true)->ordered()->get()->map(fn (Highlight $h) => [
            'key' => $h->key,
            'label' => $h->label(),
            'onCards' => $h->show_on_cards,
            'onPage' => $h->show_on_cruise_page,
            'tone' => $h->tone,
        ])->all();
    }

    private function destinations(): array
    {
        return Destination::active()->orderBy('sort_order')->get()->map(fn (Destination $d) => [
            'slug' => $d->slug,
            'name' => $d->name, // port name: also the key that links destinations and cruise routes (shown with SC.port)
            'nameAr' => $d->name_ar,
            'country' => $d->tr('country'),
            'image' => MediaLibrary::url($d->image),
            'blurb' => $d->tr('blurb'),
            'description' => $d->tr('description'),
            'highlights' => $d->tr('highlights') ?? [],
            'visa' => $d->tr('visa_note'),
        ])->all();
    }

    /** Terms text and cancellation table in the page language (config/cruise.php text_ar / cancellation_ar). */
    private function terms(): array
    {
        $terms = config('cruise.terms');
        $ar = Bi::isArabic();

        return [
            'text' => $ar ? ($terms['text_ar'] ?? $terms['text']) : $terms['text'],
            'cancellation' => $ar ? ($terms['cancellation_ar'] ?? $terms['cancellation']) : $terms['cancellation'],
        ];
    }

    /**
     * English port / country name → Arabic, from the destinations and the route ports (e.g. "Safaga" → "سفاجا",
     * "Egypt" → "مصر"). Route ports come last, so their Arabic name wins. Empty in English, so SC.port() leaves
     * names unchanged.
     *
     * @return array<string, string>
     */
    private function portNames(): array
    {
        if (! Bi::isArabic()) {
            return [];
        }
        $names = ['At sea' => __('site.at_sea')];
        foreach (Destination::all() as $d) {
            if ($d->name_ar) {
                $names[$d->name] = $d->name_ar;
            }
            if ($d->country_ar) {
                $names[$d->country] = $d->country_ar;
            }
        }
        foreach (Port::whereNotNull('name_ar')->where('name_ar', '!=', '')->get() as $port) {
            $names[$port->name_en] = $port->name_ar;
        }

        return $names;
    }

    private function facilities(): array
    {
        return Facility::orderBy('sort_order')->get()->map(fn (Facility $f) => [
            'icon' => $f->icon, 'value' => $f->tr('value'), 'label' => $f->tr('label'), 'text' => $f->tr('description'),
        ])->all();
    }

    private function testimonials(): array
    {
        return Testimonial::where('is_published', true)->with('cruise')->orderBy('sort_order')->get()
            ->map(fn (Testimonial $t) => [
                'name' => $t->tr('name'), 'from' => $t->tr('from_city'), 'cruise' => $t->cruise?->tr('name'), 'rating' => $t->rating, 'text' => $t->tr('text'),
            ])->all();
    }
}
