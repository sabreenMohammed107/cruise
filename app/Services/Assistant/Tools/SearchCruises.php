<?php

namespace App\Services\Assistant\Tools;

use App\Models\Cruise;
use App\Models\Sailing;
use App\Models\User;

/** The cruises list (/cruises) with its filters: bookable sailings only, cheapest "from" price per person. */
class SearchCruises extends Tool
{
    use DescribesCruises;

    public function name(): string
    {
        return 'search_cruises';
    }

    public function description(): string
    {
        return 'Find cruises with sailings that can be booked now. Each result has the cruise (name, nights, route of ports) '
            .'and its upcoming sailings with departure/return dates, the "from" price per person (two sharing a cabin, '
            .'port taxes not included) exactly as the website card shows it: after the current offers, and at the agent '
            .'rate for a signed-in agent; when an offer lowers it, also the price before the offer and the offer names. '
            .'And a booking link. All filters are optional; call with no filters to list everything on sale.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'port' => ['type' => 'string', 'description' => 'A port the cruise stops at, in English or Arabic, e.g. "Jeddah", "العقبة".'],
                'month' => ['type' => 'string', 'description' => 'Departure month as YYYY-MM, e.g. "2026-12".'],
                'nights' => ['type' => 'integer', 'description' => 'Exact number of nights.'],
                'max_price_per_person' => ['type' => 'number', 'description' => 'Highest "from" price per person, in US dollars.'],
            ],
        ];
    }

    public function handle(array $input, ?User $user): array
    {
        $port = $this->str($input, 'port');
        $nights = $this->int($input, 'nights', 1, 60);
        $maxPrice = is_numeric($input['max_price_per_person'] ?? null) ? (float) $input['max_price_per_person'] : null;
        $month = preg_match('/^(\d{4})-(\d{2})$/', (string) ($input['month'] ?? ''), $m) ? [(int) $m[1], (int) $m[2]] : null;

        $cruises = Cruise::active()
            ->when($nights, fn ($q) => $q->where('nights', $nights))
            ->with(['ports', 'sailings' => fn ($q) => $q->upcoming()
                ->when($month, fn ($s) => $s->departingIn([$month[0]], $month[1]))
                ->with('cabinPrices')])
            ->orderByDesc('popularity')
            ->get();

        $results = [];
        foreach ($cruises as $cruise) {
            if ($port && ! $this->callsAtPort($cruise, $port)) {
                continue;
            }
            $sailings = $cruise->sailings
                ->filter(fn (Sailing $s) => $maxPrice === null || (($from = $this->cardPrice($s, $user)['from']) !== null && $from <= $maxPrice))
                ->take(6)
                ->map(fn (Sailing $s) => $this->sailingSummary($cruise, $s, $user))
                ->values()
                ->all();
            if ($sailings === []) {
                continue;
            }
            $results[] = $this->cruiseSummary($cruise) + ['sailings' => $sailings];
            if (count($results) >= $this->limit()) {
                break;
            }
        }

        return [
            'count' => count($results),
            'cruises' => $results,
            'note' => $results === [] ? 'No bookable sailing matches. Suggest removing a filter, or list all cruises.' : null,
        ];
    }
}
