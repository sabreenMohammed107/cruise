<?php

namespace App\Admin;

use App\Models\Cruise;
use App\Models\Highlight;
use App\Models\Offer;
use App\Models\Port;
use App\Models\Sailing;
use App\Models\Ship;

/**
 * Select options shared by admin resources.
 */
final class Options
{
    public static function ships(): array
    {
        return Ship::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Country codes for the admin phone fields: "+20 Egypt", the countries of the website's phone widget first
     * (config cruise.phone_preferred_countries), then by country name. Countries sharing a code share one choice,
     * main country first ("+1 United States, Canada, Dominican Republic, …").
     */
    public static function dialCodes(): array
    {
        $preferred = array_map('strtoupper', config('cruise.phone_preferred_countries'));
        $codes = collect(config('cruise.dial_codes'));
        // Each code is placed by its main country (order 0)
        $main = $codes->sortBy(3)->unique(0)->keyBy(0);
        $rank = fn (string $code) => [($i = array_search($main[$code][1], $preferred, true)) === false ? 99 : $i, $main[$code][2]];

        return $codes->sortBy(3)->groupBy(0)
            ->sortBy(fn ($same, $code) => $rank($code))
            ->map(fn ($same, $code) => $code.' '.$same->pluck(2)->take(3)->join(', ').($same->count() > 3 ? ', …' : ''))
            ->all();
    }

    /** Cruise highlights for the cruise form (Admin → Cruises → Highlights): key => name. */
    public static function highlights(): array
    {
        return Highlight::ordered()->get()
            ->mapWithKeys(fn (Highlight $h) => [$h->key => $h->name_en.($h->is_active ? '' : ' (hidden)')])
            ->all();
    }

    /**
     * Departure & arrival ports (Admin → Route ports), by English name as cruises store them. A port used by a
     * cruise can't be made "port of call only" (PortResource), and renaming one renames it on the cruises.
     *
     * @return array<string, string>
     */
    public static function homePorts(): array
    {
        $names = Port::home()->ordered()->pluck('name_en')->all();

        return $names ? array_combine($names, $names) : [];
    }

    public static function cruises(): array
    {
        return Cruise::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Sailings for the offer form: "Red Sea Discovery · RSD-261213 · 13 Dec 2026", the ones still to depart, plus any
     * sailing an offer already points at (so editing an older offer doesn't lose its sailing).
     */
    public static function sailings(): array
    {
        return Sailing::with('cruise')
            ->where(fn ($q) => $q->current()->orWhereIn('id', Offer::whereNotNull('sailing_id')->select('sailing_id')))
            ->orderBy('departure_date')
            ->get()
            ->mapWithKeys(fn (Sailing $s) => [$s->id => ($s->cruise?->name ?? '?').' · '.$s->code.' · '.$s->departure_date->format('j M Y')])
            ->all();
    }

    /** Icon names drawn by core.js (SC.icon). */
    public static function icons(): array
    {
        $icons = ['anchor', 'award', 'bag', 'bed', 'buoy', 'calendar', 'card', 'chat', 'check', 'coffee', 'crown', 'droplet',
            'flower', 'globe', 'heart', 'home', 'info', 'lock', 'moon', 'music', 'pin', 'pulse', 'shield', 'ship', 'smile',
            'star', 'sun', 'tag', 'ticket', 'users', 'utensils', 'waves'];

        return array_combine($icons, $icons);
    }
}
