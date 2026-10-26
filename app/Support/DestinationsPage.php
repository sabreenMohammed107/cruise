<?php

namespace App\Support;

use App\Models\Cruise;
use App\Models\Destination;
use App\Models\Sailing;
use Illuminate\Support\Collection;

/**
 * The destinations page, rendered on the server (step 21) instead of by destinations.js from the full SC_DATA:
 * every active port with its upcoming sailings, nearest first. The page shows the first PREVIEW of them and links
 * to the cruises list filtered by the port for the rest, so a port with dozens of sailings stays short.
 */
final class DestinationsPage
{
    public const PREVIEW = 6;

    /**
     * @return Collection<int, array{destination: Destination, name: string, country: string, sailings: Collection, total: int}>
     */
    public static function ports(): Collection
    {
        // One query for all cruises, with their upcoming open sailings and prices
        $cruises = Cruise::active()
            ->with(['sailings' => fn ($q) => $q->upcoming()->with('cabinPrices')])
            ->get()
            ->filter(fn (Cruise $c) => $c->sailings->isNotEmpty());

        return Destination::active()->orderBy('sort_order')->get()->map(function (Destination $d) use ($cruises) {
            // Each sailing of a cruise that calls here (several dates of one cruise are separate entries)
            $sailings = $cruises->filter(fn (Cruise $c) => $c->callsAt($d->name))
                ->flatMap(fn (Cruise $c) => $c->sailings->map(fn (Sailing $s) => static::sailing($c, $s)))
                ->sortBy('date')
                ->values();

            return [
                'destination' => $d,
                'name' => Bi::port($d->name),
                'country' => (string) $d->tr('country'),
                'sailings' => $sailings->take(self::PREVIEW),
                'total' => $sailings->count(),
            ];
        });
    }

    private static function sailing(Cruise $cruise, Sailing $sailing): array
    {
        $from = $sailing->fromPrice();

        return [
            'name' => (string) $cruise->tr('name'),
            'image' => MediaLibrary::url($cruise->image),
            'nights' => $cruise->nights,
            'date' => $sailing->departure_date,
            'url' => route('cruises.show', $cruise).'?sailing='.rawurlencode($sailing->code),
            // A sailing whose cabins have no prices yet shows no price, never "$0"
            'from' => $from > 0 ? config('cruise.currency_symbol').number_format($from) : null,
        ];
    }
}
