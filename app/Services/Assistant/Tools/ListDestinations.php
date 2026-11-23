<?php

namespace App\Services\Assistant\Tools;

use App\Models\Cruise;
use App\Models\Destination;
use App\Models\User;

/** The destinations page: ports, what to see, visa notes and the cruises that call there. */
class ListDestinations extends Tool
{
    public function name(): string
    {
        return 'list_destinations';
    }

    public function description(): string
    {
        return 'Destinations (ports) the cruises visit: country, short description, highlights, visa note and which cruises '
            .'call there. Give "query" (port or country, English or Arabic) for one destination with full details.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Optional port or country name.'],
            ],
        ];
    }

    public function handle(array $input, ?User $user): array
    {
        $query = $this->str($input, 'query');
        $q = $query !== null ? mb_strtolower($query) : null;

        $destinations = Destination::active()->orderBy('sort_order')->get()
            ->filter(fn (Destination $d) => $q === null || collect([$d->slug, $d->name, $d->name_ar, $d->country, $d->country_ar])
                ->contains(fn ($v) => $v && str_contains(mb_strtolower((string) $v), $q)))
            ->take($this->limit())
            ->map(fn (Destination $d) => [
                'name' => $d->tr('name'),
                'country' => $d->tr('country'),
                'about' => $q !== null ? $d->tr('description') : $d->tr('blurb'),
                'highlights' => $q !== null ? $d->tr('highlights') : null,
                'visa_note' => $d->tr('visa_note'),
                'cruises_calling' => $d->cruises()->map(fn (Cruise $c) => ['name' => $c->tr('name'), 'slug' => $c->slug])->values()->all(),
                'url' => route('destinations.index').'#'.$d->slug,
            ])
            ->values()
            ->all();

        return ['count' => count($destinations), 'destinations' => $destinations];
    }
}
