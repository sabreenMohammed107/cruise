<?php

namespace App\Services\Assistant\Tools;

use App\Models\CabinCategory;
use App\Models\Cruise;
use App\Models\ItineraryDay;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;
use App\Models\User;

/** The cruise page (/cruises/{slug}): summary, day-by-day itinerary, cabins and the price grid of each sailing. */
class GetCruiseDetails extends Tool
{
    use DescribesCruises;

    public function name(): string
    {
        return 'get_cruise_details';
    }

    public function description(): string
    {
        return 'Full details of one cruise by its slug (from search_cruises): summary, day-by-day itinerary, cabin types '
            .'(size, beds, max guests, features) and, for each bookable sailing, the cabin prices for the whole cabin and '
            .'whole cruise: single (1 paying guest), double (2 paying guests), extra (3rd guest); infants are free. '
            .'Also says which cabins are sold out. Each sailing also has the "from" price per person as the website card '
            .'shows it (after offers, agent rate for agents). The cabin prices are list prices, before offers and the '
            .'agent rate: for what a guest would pay, use quote_price.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => ['type' => 'string', 'description' => 'The cruise slug, e.g. "red-sea-discovery".'],
            ],
            'required' => ['slug'],
        ];
    }

    public function handle(array $input, ?User $user): array
    {
        $cruise = Cruise::active()
            ->where('slug', (string) $this->str($input, 'slug'))
            ->with(['ports', 'itinerary', 'ship.cabinCategories',
                'sailings' => fn ($q) => $q->upcoming()->with('cabinPrices.cabinCategory')])
            ->first();

        if (! $cruise) {
            return ['error' => 'No cruise with this slug is on sale. Use search_cruises to find the right slug.'];
        }

        $sailings = $cruise->sailings->take($this->limit())->map(fn (Sailing $s) => $this->sailingSummary($cruise, $s, $user) + [
            'cabins' => $s->cabinPrices->map(fn (SailingCabinPrice $p) => [
                'cabin' => $this->cabinName($p->cabinCategory),
                'single' => $this->money($p->price_single),
                'double' => $this->money($p->price_double),
                'extra_3rd_guest' => $this->money($p->price_extra),
                'sold_out' => (bool) $p->is_sold_out,
            ])->values()->all(),
        ])->values()->all();

        return $this->cruiseSummary($cruise) + [
            'summary' => $cruise->tr('summary'),
            'departure_port' => $cruise->departure_port,
            'arrival_port' => $cruise->arrival_port,
            'itinerary' => $cruise->itinerary->map(fn (ItineraryDay $d) => [
                'day' => (int) $d->day_number,
                'port' => $this->isArabic() && filled($d->port_ar) ? $d->port_ar : $d->port,
                'time' => $d->tr('time_label'),
                'description' => $d->tr('description'),
            ])->all(),
            'cabin_types' => ($cruise->ship?->cabinCategories ?? collect())->map(fn (CabinCategory $c) => [
                'name' => $this->cabinName($c),
                'slug' => $c->slug,
                'max_guests' => $c->capacity(),
                'size' => $c->tr('size'),
                'balcony' => $c->tr('balcony_size'),
                'beds' => $c->tr('beds'),
                'features' => $c->tr('features'),
                'description' => $c->tr('short_description'),
            ])->values()->all(),
            'sailings' => $sailings,
            'prices_note' => 'Prices are for the whole cabin and the whole cruise. Port & departure taxes are added per paying guest (see get_policies). Use quote_price for an exact total.',
        ];
    }

    protected function cabinName(?CabinCategory $cabin): ?string
    {
        if (! $cabin) {
            return null;
        }

        return $this->isArabic() && filled($cabin->name_ar) ? $cabin->name_ar : $cabin->name_en;
    }
}
