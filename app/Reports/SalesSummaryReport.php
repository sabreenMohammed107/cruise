<?php

namespace App\Reports;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Format;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Report 1 (reports-plan.pdf): bookings, guests and revenue sold in a period, against a comparison period.
 *
 * - The period is the sale date (bookings.confirmed_at).
 * - Figures leave out cancelled bookings; cancellations are counted on their own.
 * - Net revenue is grand_total. The bridge: public fare (fare_total + agent_discount_total, since fare_total is
 *   already the agent's fare) − agent discount + fees − promo codes − offers = grand_total (PricingService).
 * - "Paid" is amount_paid on these bookings (payments less refunds), so a collection rate for what was sold;
 *   the cash received day by day is the payments ledger (report 4).
 * - All amounts are USD, as stored. The other currency (EGP) uses each booking's own rate of the booking day.
 */
class SalesSummaryReport extends Report
{
    public static function key(): string
    {
        return 'sales-summary';
    }

    public static function title(): string
    {
        return 'Sales summary';
    }

    public static function description(): string
    {
        return 'Bookings, guests and revenue for a period, against the previous period: where the revenue came from, by channel and currency.';
    }

    public function view(): string
    {
        return 'admin.reports.sales-summary';
    }

    public function data(ReportFilters $filters, Request $request): array
    {
        $comparison = $filters->comparison();
        $current = $this->totals($filters, $filters->from, $filters->to);
        $previous = $comparison ? $this->totals($filters, ...$comparison) : null;

        return [
            'current' => $current,
            'previous' => $previous,
            'comparison' => $comparison,
            'trend' => $this->trend($filters, $comparison),
            'channels' => $this->channels($filters),
            'currencies' => $this->currencies($filters),
            'bookings' => $filters->sold()
                ->with(['sailing.cruise', 'cabinCategory', 'cabins.cabinCategory', 'seller', 'agent', 'user'])
                ->orderByDesc('confirmed_at')->orderByDesc('id')
                ->paginate(25)->withQueryString(),
        ];
    }

    /**
     * Figures of one period: confirmed bookings, plus the cancelled ones on their own.
     *
     * @return array<string, float|int>
     */
    public function totals(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = (array) $filters->sold($from, $to)->where('status', BookingStatus::Confirmed)->toBase()->selectRaw('
            COUNT(*) as bookings,
            COALESCE(SUM(adults), 0) as adults,
            COALESCE(SUM(children), 0) as children,
            COALESCE(SUM(infants), 0) as infants,
            COALESCE(SUM(fare_total + agent_discount_total), 0) as public_fare,
            COALESCE(SUM(agent_discount_total), 0) as agent_discount,
            COALESCE(SUM(fees_total), 0) as fees,
            COALESCE(SUM(discount_total), 0) as promo,
            COALESCE(SUM(offer_discount_total), 0) as offers,
            COALESCE(SUM(grand_total), 0) as revenue,
            COALESCE(SUM(amount_paid), 0) as collected,
            COALESCE(SUM(CASE WHEN grand_total > amount_paid THEN grand_total - amount_paid ELSE 0 END), 0) as outstanding
        ')->first();
        $cancelled = (array) $filters->sold($from, $to)->where('status', BookingStatus::Cancelled)->toBase()
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(grand_total), 0) as value')->first();

        $t = array_map('floatval', $row);
        foreach (['bookings', 'adults', 'children', 'infants'] as $count) {
            $t[$count] = (int) $t[$count];
        }
        $t['guests'] = $t['adults'] + $t['children'] + $t['infants'];
        $t['average'] = $t['bookings'] ? $t['revenue'] / $t['bookings'] : 0.0;
        $t['collection_rate'] = $t['revenue'] > 0 ? $t['collected'] / $t['revenue'] * 100 : null;
        $t['cancelled'] = (int) $cancelled['n'];
        $t['cancelled_value'] = (float) $cancelled['value'];

        return $t;
    }

    /** Change in % against the comparison figure; null when there's nothing to compare with. */
    public static function change(float|int $current, float|int|null $previous): ?float
    {
        if ($previous === null || (float) $previous == 0.0) {
            return null;
        }

        return ($current - $previous) / abs($previous) * 100;
    }

    /** Day buckets up to a month, weeks up to about six months, months beyond. */
    public static function granularity(ReportFilters $filters): string
    {
        return match (true) {
            $filters->days() <= 31 => 'day',
            $filters->days() <= 186 => 'week',
            default => 'month',
        };
    }

    /**
     * Revenue and bookings per day / week / month for the chart; the comparison period lined up bucket by bucket.
     * Bucketed in PHP so it runs the same on MySQL and SQLite (tests); a period holds hundreds of bookings, not millions.
     */
    private function trend(ReportFilters $filters, ?array $comparison): array
    {
        $unit = self::granularity($filters);
        $current = $this->series($filters, $filters->from, $filters->to, $unit);
        $previous = $comparison ? $this->series($filters, $comparison[0], $comparison[1], $unit) : null;

        return [
            'unit' => $unit,
            'labels' => array_column($current, 'label'),
            'revenue' => array_column($current, 'revenue'),
            'bookings' => array_column($current, 'bookings'),
            'previous' => $previous ? [
                'labels' => array_slice(array_pad(array_column($previous, 'label'), count($current), ''), 0, count($current)),
                'revenue' => array_slice(array_pad(array_column($previous, 'revenue'), count($current), null), 0, count($current)),
            ] : null,
        ];
    }

    /** @return list<array{label: string, revenue: float, bookings: int}> */
    private function series(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to, string $unit): array
    {
        $key = fn (CarbonImmutable $d) => match ($unit) {
            'day' => $d->format('Y-m-d'),
            'week' => $d->startOfWeek()->format('Y-m-d'),
            default => $d->format('Y-m'),
        };

        // One bucket per day / week / month the period touches, empty ones included
        $buckets = [];
        $d = match ($unit) {
            'day' => $from->startOfDay(),
            'week' => $from->startOfWeek(),
            default => $from->startOfMonth(),
        };
        for (; $d <= $to; $d = match ($unit) { 'day' => $d->addDay(), 'week' => $d->addWeek(), default => $d->addMonthNoOverflow() }) {
            $buckets[$key($d)] = [
                'label' => $d->format($unit === 'month' ? 'M Y' : 'j M'),
                'revenue' => 0.0,
                'bookings' => 0,
            ];
        }

        $filters->sold($from, $to)->where('status', BookingStatus::Confirmed)->toBase()
            ->select(['confirmed_at', 'grand_total'])->orderBy('confirmed_at')
            ->each(function ($b) use (&$buckets, $key) {
                $k = $key(CarbonImmutable::parse($b->confirmed_at));
                if (isset($buckets[$k])) {
                    $buckets[$k]['revenue'] += (float) $b->grand_total;
                    $buckets[$k]['bookings']++;
                }
            });

        return array_values(array_map(fn ($b) => ['revenue' => round($b['revenue'], 2)] + $b, $buckets));
    }

    /** Confirmed bookings of the period by channel: website (b2c) or agent. */
    private function channels(ReportFilters $filters): Collection
    {
        return $filters->sold()->where('status', BookingStatus::Confirmed)->toBase()
            ->selectRaw("CASE WHEN agent_id IS NULL THEN 'b2c' ELSE 'agent' END as channel, COUNT(*) as bookings, COALESCE(SUM(grand_total), 0) as revenue")
            ->groupBy('channel')->get()->keyBy('channel');
    }

    /** Confirmed bookings of the period by the currency the guest saw; "local" is at each booking's own rate. */
    private function currencies(ReportFilters $filters): Collection
    {
        return $filters->sold()->where('status', BookingStatus::Confirmed)->toBase()
            ->selectRaw('display_currency as currency, COUNT(*) as bookings, COALESCE(SUM(grand_total), 0) as revenue, COALESCE(SUM(grand_total * exchange_rate), 0) as local')
            ->groupBy('display_currency')->orderByDesc('revenue')->get();
    }

    /**
     * Two sheets: "Summary" (the page's figures, with the comparison) and "Bookings" (one row per booking sold in the
     * period, cancelled ones included and marked, then a totals row without them).
     */
    public function workbook(ReportFilters $filters): Xlsx
    {
        $comparison = $filters->comparison();
        $c = $this->totals($filters, $filters->from, $filters->to);
        $p = $comparison ? $this->totals($filters, ...$comparison) : null;
        $pct = fn (string $k) => $p && ($d = self::change($c[$k], $p[$k])) !== null ? [$d / 100, 'percent'] : ($p ? ['—', 'muted'] : null);

        $xlsx = (new Xlsx)->sheet('Summary', [36, 18, 18, 14, 20]);
        $this->heading($xlsx, $filters);

        // Key figures, with the comparison period
        $xlsx->row([['Key figures', 'section'], ['', 'section'], ['', 'section'], ['', 'section']])
            ->row([['', 'header'], ['This period', 'header'], [$p ? 'Comparison' : '', 'header'], [$p ? 'Change' : '', 'header']], 22);
        foreach ([
            ['Bookings (cancelled not included)', 'bookings', 'int'],
            ['Guests', 'guests', 'int'],
            ['   Adults', 'adults', 'int'],
            ['   Children', 'children', 'int'],
            ['   Infants', 'infants', 'int'],
            ['Net revenue', 'revenue', 'money'],
            ['Average booking value', 'average', 'money'],
            ['Paid on these bookings', 'collected', 'money'],
            ['Still to collect', 'outstanding', 'money'],
            ['Cancelled bookings', 'cancelled', 'int'],
            ['Value of cancelled bookings', 'cancelled_value', 'money'],
        ] as [$label, $key, $type]) {
            $xlsx->row([$label, [$type === 'int' ? (int) $c[$key] : (float) $c[$key], $type], $p ? [$type === 'int' ? (int) $p[$key] : (float) $p[$key], $type] : null, $pct($key)]);
        }
        $xlsx->row(['Collection rate',
            $c['collection_rate'] !== null ? [$c['collection_rate'] / 100, 'percent'] : ['—', 'muted'],
            $p ? ($p['collection_rate'] !== null ? [$p['collection_rate'] / 100, 'percent'] : ['—', 'muted']) : null,
        ])->row();

        // Revenue bridge
        $xlsx->row([['Where the revenue came from', 'section'], ['', 'section']]);
        foreach ([
            ['Cabin fares at public prices', $c['public_fare']],
            ['− Agent discount (commission)', -$c['agent_discount']],
            ['+ Fees and port taxes', $c['fees']],
            ['− Promo codes', -$c['promo']],
            ['− Offers', -$c['offers']],
        ] as [$label, $amount]) {
            $xlsx->row([$label, [(float) $amount, 'money']]);
        }
        $xlsx->row([['Net revenue', 'total'], [(float) $c['revenue'], 'total_money']])->row();

        // Channels
        $channels = $this->channels($filters);
        $xlsx->row([['By channel', 'section'], ['', 'section'], ['', 'section'], ['', 'section']])
            ->row([['Channel', 'header'], ['Bookings', 'header'], ['Revenue', 'header'], ['Share', 'header']], 22);
        foreach (['b2c' => 'Website (B2C)', 'agent' => 'Agents (B2B)'] as $key => $label) {
            $row = $channels[$key] ?? null;
            $xlsx->row([$label, [(int) ($row->bookings ?? 0), 'int'], [(float) ($row->revenue ?? 0), 'money'],
                [$c['revenue'] > 0 ? (float) ($row->revenue ?? 0) / $c['revenue'] : 0.0, 'percent']]);
        }
        $xlsx->row();

        // Currencies
        $xlsx->row([['By the currency the guest saw', 'section'], ['', 'section'], ['', 'section'], ['', 'section'], ['', 'section']])
            ->row([['Currency', 'header'], ['Bookings', 'header'], ['Revenue (USD)', 'header'], ['', 'header'], ['In that currency', 'header']], 22);
        foreach ($this->currencies($filters) as $cur) {
            $xlsx->row([$cur->currency, [(int) $cur->bookings, 'int'], [(float) $cur->revenue, 'money'], null, [(float) $cur->local, 'number']]);
        }
        $xlsx->row([['The other currency is at each booking\'s own rate on the booking day.', 'subtitle']]);

        // Bookings
        $columns = [
            ['Reference', 15], ['Sold on', 17], ['Customer', 22], ['Email', 28], ['Cruise', 26], ['Departure', 13], ['Cabins', 24],
            ['Channel', 10], ['Agent', 22], ['Assigned to', 18], ['Adults', 8], ['Children', 9], ['Infants', 8],
            ['Public fare', 13], ['Agent discount', 14], ['Fees', 11], ['Promo code', 12], ['Offers', 11], ['Total', 13],
            ['Paid', 13], ['Due', 13], ['Booking', 11], ['Payment', 11], ['Guest currency', 10], ['Rate', 9], ['Total in guest currency', 16],
        ];
        $xlsx->sheet('Bookings', array_column($columns, 1), table: true)->row(array_column($columns, 0), 32);

        $filters->sold()
            ->with(['sailing.cruise', 'cabinCategory', 'cabins.cabinCategory', 'seller', 'agent', 'user'])
            ->orderBy('confirmed_at')->orderBy('id')
            ->chunk(500, function ($bookings) use ($xlsx) {
                foreach ($bookings as $b) {
                    /** @var Booking $b */
                    $cancelled = $b->status === BookingStatus::Cancelled;
                    $xlsx->row([
                        $b->reference,
                        $b->confirmed_at,
                        $b->user?->name,
                        $b->contact_email,
                        $b->sailing?->cruise?->name,
                        $b->sailing?->departure_date ? [$b->sailing->departure_date, 'date'] : null,
                        $b->cabinSummary(),
                        $b->isAgentBooking() ? 'Agent' : 'Website',
                        $b->agent?->company_name ?: $b->agent?->name,
                        $b->seller?->name ?? 'Unassigned',
                        (int) $b->adults, (int) $b->children, (int) $b->infants,
                        [(float) $b->fare_total + (float) $b->agent_discount_total, 'money'],
                        [(float) $b->agent_discount_total, 'money'],
                        [(float) $b->fees_total, 'money'],
                        [(float) $b->discount_total, 'money'],
                        [(float) $b->offer_discount_total, 'money'],
                        [(float) $b->grand_total, 'money'],
                        [(float) $b->amount_paid, 'money'],
                        [$cancelled ? 0.0 : $b->amountDue(), 'money'],
                        Format::badge($b)[1],
                        Format::badge($b->payment_status)[1],
                        $b->display_currency,
                        [(float) $b->exchange_rate, 'rate'],
                        [(float) $b->grand_total * (float) $b->exchange_rate, 'number'],
                    ]);
                }
            });

        // Totals row: the report's figures (cancelled bookings left out)
        $total = fn ($v, $style) => [$v, $style];
        $xlsx->row([
            $total('Total: '.$c['bookings'].' '.Str::plural('booking', $c['bookings']).($c['cancelled'] ? ', '.$c['cancelled'].' cancelled not included' : ''), 'total'),
            ...array_fill(0, 9, ['', 'total']),
            $total($c['adults'], 'total_int'), $total($c['children'], 'total_int'), $total($c['infants'], 'total_int'),
            $total($c['public_fare'], 'total_money'), $total($c['agent_discount'], 'total_money'), $total($c['fees'], 'total_money'),
            $total($c['promo'], 'total_money'), $total($c['offers'], 'total_money'), $total($c['revenue'], 'total_money'),
            $total($c['collected'], 'total_money'), $total($c['outstanding'], 'total_money'),
            ...array_fill(0, 5, ['', 'total']),
        ], 22);

        return $xlsx;
    }
}
