<?php

namespace App\Reports;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Report 13 (reports-plan.pdf): how far ahead people book and which months sell, to plan offers and prices.
 *
 * Lead time = days from the sale (bookings.confirmed_at) to departure (sailings.departure_date), by calendar date,
 * for the bookings sold in the period (cancelled left out). "Sales by month" is when they were sold; "departures by
 * month" is when those same bookings sail. Opens on this year so far: a month is too short to see a season.
 */
class SeasonalityReport extends Report
{
    /** Lead time bands: [label, from, to] in days (to null: no upper end). */
    public const BANDS = [
        ['Last week (0 – 7 days)', 0, 7],
        ['8 – 14 days', 8, 14],
        ['15 – 30 days', 15, 30],
        ['1 – 2 months (31 – 60 days)', 31, 60],
        ['2 – 3 months (61 – 90 days)', 61, 90],
        ['3 – 6 months (91 – 180 days)', 91, 180],
        ['6 – 12 months (181 – 365 days)', 181, 365],
        ['More than a year', 366, null],
    ];

    public static function key(): string
    {
        return 'seasonality';
    }

    public static function title(): string
    {
        return 'Seasonality & lead time';
    }

    public static function description(): string
    {
        return 'How many days before departure people book, which months sell and which months sail: for planning offers and prices.';
    }

    public static function icon(): string
    {
        return 'calendar';
    }

    public static function defaultPreset(): string
    {
        return 'year';
    }

    public function view(): string
    {
        return 'admin.reports.seasonality';
    }

    public function data(ReportFilters $filters, Request $request): array
    {
        $comparison = $filters->comparison();
        $sales = $this->sales($filters, $filters->from, $filters->to);

        return [
            'current' => $this->totals($sales),
            'previous' => $comparison ? $this->totals($this->sales($filters, ...$comparison)) : null,
            'comparison' => $comparison,
            'bands' => $this->bands($sales),
            'channels' => collect(['Website (B2C)' => $sales->where('agent', false), 'Agents (B2B)' => $sales->where('agent', true)])
                ->map(fn (Collection $g, string $name) => ['name' => $name] + $this->totals($g))->values(),
            'cruises' => $sales->groupBy('cruise')->map(fn (Collection $g, string $name) => ['name' => $name] + $this->totals($g))
                ->sortByDesc('bookings')->values(),
            'soldByMonth' => $this->months($sales, 'sold_on', $filters->from, $filters->to),
            'sailingByMonth' => $sales->isEmpty() ? collect()
                : $this->months($sales, 'departure', $sales->min('departure'), $sales->max('departure')),
        ];
    }

    /**
     * The confirmed bookings sold in a period with their lead time.
     *
     * @return Collection<int, array>
     */
    public function sales(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $filters->sold($from, $to)->where('status', BookingStatus::Confirmed)
            ->with(['sailing:id,cruise_id,departure_date', 'sailing.cruise:id,name'])
            ->get(['id', 'sailing_id', 'agent_id', 'confirmed_at', 'grand_total', 'adults', 'children', 'infants'])
            ->filter(fn (Booking $b) => $b->sailing?->departure_date)
            ->map(function (Booking $b) {
                $sold = $b->confirmed_at->toImmutable()->startOfDay();
                $departure = CarbonImmutable::parse($b->sailing->departure_date)->startOfDay();

                return [
                    'sold_on' => $sold,
                    'departure' => $departure,
                    'lead' => (int) $sold->diffInDays($departure, false),
                    'cruise' => $b->sailing->cruise?->name ?? 'Unknown cruise',
                    'agent' => $b->agent_id !== null,
                    'guests' => $b->adults + $b->children + $b->infants,
                    'revenue' => (float) $b->grand_total,
                ];
            })->values();
    }

    /** @return array<string, float|int|null> */
    private function totals(Collection $sales): array
    {
        $leads = $sales->pluck('lead')->sort()->values();
        $n = $sales->count();

        return [
            'bookings' => $n,
            'guests' => (int) $sales->sum('guests'),
            'revenue' => (float) $sales->sum('revenue'),
            'average_value' => $n ? (float) $sales->sum('revenue') / $n : null,
            'average_lead' => $n ? $leads->avg() : null,
            'median_lead' => $n ? ($n % 2 ? $leads[intdiv($n, 2)] : ($leads[$n / 2 - 1] + $leads[$n / 2]) / 2) : null,
            'last_minute' => $n ? $sales->where('lead', '<=', 30)->count() / $n * 100 : null,
            'early' => $n ? $sales->where('lead', '>', 90)->count() / $n * 100 : null,
        ];
    }

    private function bands(Collection $sales): Collection
    {
        $total = max(1, $sales->count());

        return collect(self::BANDS)->map(function (array $band) use ($sales, $total) {
            [$label, $min, $max] = $band;
            $g = $sales->filter(fn ($s) => $s['lead'] >= $min && ($max === null || $s['lead'] <= $max));

            return [
                'name' => $label,
                'bookings' => $g->count(),
                'guests' => (int) $g->sum('guests'),
                'revenue' => (float) $g->sum('revenue'),
                'average_value' => $g->count() ? (float) $g->sum('revenue') / $g->count() : null,
                'share' => $g->count() / $total * 100,
            ];
        });
    }

    /** One row per calendar month from $from to $to (empty months included), by the sale or the departure date. */
    private function months(Collection $sales, string $date, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = [];
        for ($m = $from->startOfMonth(); $m <= $to; $m = $m->addMonthNoOverflow()) {
            $rows[$m->format('Y-m')] = ['month' => $m, 'bookings' => 0, 'guests' => 0, 'revenue' => 0.0, 'leads' => []];
        }
        foreach ($sales as $s) {
            $k = $s[$date]->format('Y-m');
            if (isset($rows[$k])) {
                $rows[$k]['bookings']++;
                $rows[$k]['guests'] += $s['guests'];
                $rows[$k]['revenue'] += $s['revenue'];
                $rows[$k]['leads'][] = $s['lead'];
            }
        }

        return collect($rows)->map(fn ($r) => [
            'name' => $r['month']->format('M Y'),
            'month' => $r['month'],
            'bookings' => $r['bookings'],
            'guests' => $r['guests'],
            'revenue' => $r['revenue'],
            'average_value' => $r['bookings'] ? $r['revenue'] / $r['bookings'] : null,
            'average_lead' => $r['leads'] ? array_sum($r['leads']) / count($r['leads']) : null,
        ])->values();
    }

    /** Sheets: Summary (key figures, lead time bands, channels, cruises), By month, Bookings. */
    public function workbook(ReportFilters $filters): Xlsx
    {
        $data = $this->data($filters, request());
        $num = fn ($v) => $v === null ? ['—', 'muted'] : [(float) $v, 'number'];
        $pct = fn ($v) => $v === null ? ['—', 'muted'] : [$v / 100, 'percent'];

        $xlsx = (new Xlsx)->sheet('Summary', [34, 14, 14, 14, 14, 14, 14]);
        $this->heading($xlsx, $filters);
        self::keyFigures($xlsx, $data['current'], $data['previous'], [
            ['Bookings (cancelled not included)', 'bookings', 'int'],
            ['Average days from sale to departure', 'average_lead', 'number'],
            ['Median days from sale to departure', 'median_lead', 'number'],
            ['Booked 30 days or less before', 'last_minute', 'pct'],
            ['Booked more than 90 days before', 'early', 'pct'],
            ['Average booking value', 'average_value', 'money'],
        ]);
        self::table($xlsx, 'How far ahead they book', ['Days before departure', 'Bookings', 'Share', 'Guests', 'Net revenue', 'Average booking'],
            $data['bands']->map(fn ($r) => [$r['name'], [$r['bookings'], 'int'], $pct($r['share']), [$r['guests'], 'int'], [$r['revenue'], 'money'],
                $r['average_value'] === null ? ['—', 'muted'] : [$r['average_value'], 'money']]));
        foreach (['By channel' => $data['channels'], 'By cruise' => $data['cruises']] as $title => $rows) {
            self::table($xlsx, $title, [$title === 'By channel' ? 'Channel' : 'Cruise', 'Bookings', 'Average days', 'Median days', '30 days or less', 'More than 90 days', 'Average booking'],
                $rows->map(fn ($r) => [$r['name'], [$r['bookings'], 'int'], $num($r['average_lead']), $num($r['median_lead']), $pct($r['last_minute']), $pct($r['early']),
                    $r['average_value'] === null ? ['—', 'muted'] : [$r['average_value'], 'money']]));
        }

        $xlsx->sheet('By month', [14, 12, 12, 15, 15, 14, 3, 14, 12, 12, 15, 15, 14]);
        $xlsx->row([['Sold in the month', 'section'], ...array_fill(0, 5, ['', 'section']), null, ['Sailing in the month', 'section'], ...array_fill(0, 5, ['', 'section'])])
            ->row([...array_map(fn ($h) => [$h, 'header'], $h = ['Month', 'Bookings', 'Guests', 'Net revenue', 'Average booking', 'Average days']), null,
                ...array_map(fn ($h) => [$h, 'header'], $h)], 22);
        $sold = $data['soldByMonth']->values();
        $sailing = $data['sailingByMonth']->values();
        $line = fn (?array $r) => $r ? [$r['name'], [$r['bookings'], 'int'], [$r['guests'], 'int'], [$r['revenue'], 'money'],
            $r['average_value'] === null ? null : [$r['average_value'], 'money'], $num($r['average_lead'])] : array_fill(0, 6, null);
        for ($i = 0; $i < max($sold->count(), $sailing->count()); $i++) {
            $xlsx->row([...$line($sold[$i] ?? null), null, ...$line($sailing[$i] ?? null)]);
        }

        $xlsx->sheet('Bookings', [14, 14, 10, 26, 12, 9, 14], table: true)
            ->row(['Sold on', 'Departure', 'Days ahead', 'Cruise', 'Channel', 'Guests', 'Net revenue'], 32);
        foreach ($this->sales($filters, $filters->from, $filters->to)->sortBy('sold_on') as $s) {
            $xlsx->row([[$s['sold_on'], 'date'], [$s['departure'], 'date'], [$s['lead'], 'int'], $s['cruise'], $s['agent'] ? 'Agent' : 'Website', [$s['guests'], 'int'], [$s['revenue'], 'money']]);
        }
        $c = $data['current'];
        $xlsx->row([['Total: '.$c['bookings'].' bookings (days ahead: the average)', 'total'], ['', 'total'],
            $c['average_lead'] === null ? ['', 'total'] : [(int) round($c['average_lead']), 'total_int'], ['', 'total'], ['', 'total'], [$c['guests'], 'total_int'], [$c['revenue'], 'total_money']], 22);

        return $xlsx;
    }
}
