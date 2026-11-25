<?php

namespace App\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Report 3 (reports-plan.pdf): who still owes money, how much, and how close their sailing is.
 *
 * A balance is taken "as at" a moment: the end of the period (or now, if the period ends later). It covers the
 * bookings sold by then and not cancelled by then, and what had been paid on them by then (payments less refunds,
 * by received_at), so a past period shows the balances as they were, and the comparison period the balances at its
 * own end. Due = grand_total − paid, bookings fully paid left out. Cancelled bookings owe nothing.
 *
 * Two ages: days until departure (how urgent: a balance is due before sailing) and days since the booking was sold
 * (how old the balance is: 7 / 30 / 60 days).
 */
class ReceivablesReport extends Report
{
    /** Days until departure: [label, from, to] (null: no end). */
    public const DEPARTURE = [
        ['Already departed', null, -1],
        ['0 – 7 days', 0, 7],
        ['8 – 30 days', 8, 30],
        ['31 – 60 days', 31, 60],
        ['More than 60 days', 61, null],
    ];

    /** Days since the booking was sold. */
    public const AGE = [
        ['7 days or less', 0, 7],
        ['8 – 30 days', 8, 30],
        ['31 – 60 days', 31, 60],
        ['More than 60 days', 61, null],
    ];

    public static function key(): string
    {
        return 'receivables';
    }

    public static function title(): string
    {
        return 'Receivables';
    }

    public static function description(): string
    {
        return 'Who has not paid in full yet, how much is left, how many days until they sail, and how old each balance is.';
    }

    public static function icon(): string
    {
        return 'clock';
    }

    public function view(): string
    {
        return 'admin.reports.receivables';
    }

    /** The moment balances are taken at: the end of a period, never later than now. */
    public static function asAt(CarbonImmutable $to): CarbonImmutable
    {
        return $to->min(CarbonImmutable::now());
    }

    public function data(ReportFilters $filters, Request $request): array
    {
        $comparison = $filters->comparison();
        $asAt = self::asAt($filters->to);
        $open = $this->open($filters, $asAt);

        return [
            'asAt' => $asAt,
            'previousAsAt' => $comparison ? self::asAt($comparison[1]) : null,
            'current' => $this->totals($filters, $open, $filters->from, $asAt),
            'previous' => $comparison ? $this->totals($filters, $this->open($filters, self::asAt($comparison[1])), $comparison[0], self::asAt($comparison[1])) : null,
            'comparison' => $comparison,
            'departure' => $this->bands($open, self::DEPARTURE, 'days_to_departure'),
            'age' => $this->bands($open, self::AGE, 'age_days'),
            'cruises' => $this->group($open, 'cruise_id', 'cruise'),
            'sellers' => $this->group($open, 'seller_id', 'seller'),
            'open' => $open,
        ];
    }

    /**
     * The bookings with something still to pay at $asAt, soonest departure first.
     *
     * @return Collection<int, array>
     */
    public function open(ReportFilters $filters, CarbonImmutable $asAt): Collection
    {
        $paid = fn (PaymentType $type) => fn (Builder $q) => $q->where('type', $type)->where('received_at', '<=', $asAt);
        $day = $asAt->startOfDay();

        return $filters->bookings()
            ->where('confirmed_at', '<=', $asAt)
            ->where(fn (Builder $q) => $q->where('status', BookingStatus::Confirmed)
                ->orWhere(fn (Builder $c) => $c->where('status', BookingStatus::Cancelled)->where('cancelled_at', '>', $asAt)))
            ->withSum(['payments as paid_in' => $paid(PaymentType::Payment)], 'amount')
            ->withSum(['payments as refunded_out' => $paid(PaymentType::Refund)], 'amount')
            ->withMax(['payments as last_paid_at' => $paid(PaymentType::Payment)], 'received_at')
            ->with(['sailing.cruise', 'user', 'seller', 'agent'])
            ->get()
            ->map(function (Booking $b) use ($day) {
                $total = (float) $b->grand_total;
                $paid = (float) $b->paid_in - (float) $b->refunded_out;
                $departure = $b->sailing?->departure_date ? CarbonImmutable::parse($b->sailing->departure_date)->startOfDay() : null;

                return [
                    'booking' => $b,
                    'cruise_id' => $b->sailing?->cruise_id ?? 0,
                    'cruise' => $b->sailing?->cruise?->name ?? 'Unknown cruise',
                    'seller_id' => $b->seller_id ?? 0,
                    'seller' => $b->seller?->name ?? 'Unassigned',
                    'departure' => $departure,
                    'total' => $total,
                    'paid' => $paid,
                    'due' => round($total - $paid, 2),
                    'paid_share' => $total > 0 ? max(0, $paid) / $total * 100 : 0,
                    'last_paid_at' => $b->last_paid_at ? CarbonImmutable::parse($b->last_paid_at) : null,
                    'days_to_departure' => $departure ? (int) $day->diffInDays($departure, false) : null,
                    'age_days' => (int) $b->confirmed_at->toImmutable()->startOfDay()->diffInDays($day, false),
                ];
            })
            ->filter(fn (array $r) => $r['due'] > 0.004)
            ->sortBy([fn ($a, $b) => ($a['departure']?->getTimestamp() ?? PHP_INT_MAX) <=> ($b['departure']?->getTimestamp() ?? PHP_INT_MAX), ['due', 'desc']])
            ->values();
    }

    /** @return array<string, float|int|null> */
    private function totals(ReportFilters $filters, Collection $open, CarbonImmutable $from, CarbonImmutable $asAt): array
    {
        $in = fn (?int $min, ?int $max) => $open->filter(fn ($r) => $r['days_to_departure'] !== null
            && ($min === null || $r['days_to_departure'] >= $min) && ($max === null || $r['days_to_departure'] <= $max));
        $departed = $in(null, -1);
        $soon = $in(0, 30);
        $unpaid = $open->filter(fn ($r) => $r['paid'] <= 0.004);
        $newer = $open->filter(fn ($r) => $r['booking']->confirmed_at->gte($from));
        $collected = Payment::query()->whereIn('booking_id', $filters->bookings()->select('bookings.id'))->whereBetween('received_at', [$from, $asAt])->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) as net', [PaymentType::Payment->value])->value('net');
        $value = (float) $open->sum('total');

        return [
            'bookings' => $open->count(),
            'due' => (float) $open->sum('due'),
            'value' => $value,
            'paid' => (float) $open->sum('paid'),
            'paid_share' => $value > 0 ? $open->sum('paid') / $value * 100 : null,
            'unpaid' => $unpaid->count(),
            'unpaid_due' => (float) $unpaid->sum('due'),
            'partial' => $open->count() - $unpaid->count(),
            'departed' => $departed->count(),
            'departed_due' => (float) $departed->sum('due'),
            'soon' => $soon->count(),
            'soon_due' => (float) $soon->sum('due'),
            'new' => $newer->count(),
            'new_due' => (float) $newer->sum('due'),
            'collected' => (float) $collected,
        ];
    }

    /** The balances in bands of days (until departure, or since sold). */
    private function bands(Collection $open, array $bands, string $field): Collection
    {
        $total = max(0.01, (float) $open->sum('due'));

        return collect($bands)->map(function (array $band) use ($open, $field, $total) {
            [$label, $min, $max] = $band;
            $g = $open->filter(fn ($r) => $r[$field] !== null && ($min === null || $r[$field] >= $min) && ($max === null || $r[$field] <= $max));

            return ['name' => $label, 'bookings' => $g->count(), 'value' => (float) $g->sum('total'), 'paid' => (float) $g->sum('paid'),
                'due' => (float) $g->sum('due'), 'share' => $g->sum('due') / $total * 100];
        });
    }

    /** The balances per cruise or per assigned seller, largest first. */
    private function group(Collection $open, string $key, string $name): Collection
    {
        return $open->groupBy($key)->map(fn (Collection $g) => [
            'name' => $g->first()[$name],
            'bookings' => $g->count(),
            'value' => (float) $g->sum('total'),
            'paid' => (float) $g->sum('paid'),
            'due' => (float) $g->sum('due'),
            'next' => $g->pluck('departure')->filter()->min(),
        ])->sortByDesc('due')->values();
    }

    /** Sheets: Summary (key figures, bands, by cruise, by seller), Balances (one row per booking). */
    public function workbook(ReportFilters $filters): Xlsx
    {
        $data = $this->data($filters, request());
        $c = $data['current'];
        $money = fn ($v) => [$v, 'money'];

        $xlsx = (new Xlsx)->sheet('Summary', [34, 12, 14, 14, 14, 12]);
        $this->heading($xlsx, $filters);
        $xlsx->row([['Balances as at '.$data['asAt']->format('j M Y, H:i')
            .($data['previousAsAt'] ? ', compared with '.$data['previousAsAt']->format('j M Y, H:i') : ''), 'subtitle']])->row();
        self::keyFigures($xlsx, $c, $data['previous'], [
            ['Still to pay', 'due', 'money'],
            ['Bookings with a balance', 'bookings', 'int'],
            ['   not paid at all', 'unpaid', 'int'],
            ['   part paid', 'partial', 'int'],
            ['Value of these bookings', 'value', 'money'],
            ['Paid on them', 'paid', 'money'],
            ['Share paid', 'paid_share', 'pct'],
            ['Already departed, still owing', 'departed_due', 'money'],
            ['Departing within 30 days, still owing', 'soon_due', 'money'],
            ['Sold in this period, still owing', 'new_due', 'money'],
            ['Collected in this period (payments less refunds)', 'collected', 'money'],
        ]);
        $bandRows = fn (Collection $rows) => $rows->map(fn ($r) => [$r['name'], [$r['bookings'], 'int'], $money($r['value']), $money($r['paid']), $money($r['due']), [$r['share'] / 100, 'percent']]);
        self::table($xlsx, 'Days until departure', ['Departure', 'Bookings', 'Value', 'Paid', 'Still to pay', 'Share'], $bandRows($data['departure']));
        self::table($xlsx, 'Age of the balance (days since sold)', ['Sold', 'Bookings', 'Value', 'Paid', 'Still to pay', 'Share'], $bandRows($data['age']));
        $groupRows = fn (Collection $rows) => $rows->map(fn ($r) => [$r['name'], [$r['bookings'], 'int'], $money($r['value']), $money($r['paid']), $money($r['due']), $r['next'] ? [$r['next'], 'date'] : null]);
        self::table($xlsx, 'By cruise', ['Cruise', 'Bookings', 'Value', 'Paid', 'Still to pay', 'Next departure'], $groupRows($data['cruises']));
        self::table($xlsx, 'By assigned seller', ['Assigned to', 'Bookings', 'Value', 'Paid', 'Still to pay', 'Next departure'], $groupRows($data['sellers']));

        $xlsx->sheet('Balances', [15, 22, 26, 16, 26, 13, 11, 13, 10, 13, 13, 13, 9, 13, 18, 22], table: true)
            ->row(['Reference', 'Customer', 'Email', 'Phone', 'Cruise', 'Departure', 'Days to departure', 'Sold on', 'Days since sold', 'Total', 'Paid', 'Still to pay', 'Paid %',
                'Last payment', 'Assigned to', 'Channel'], 32);
        foreach ($data['open'] as $r) {
            $b = $r['booking'];
            $xlsx->row([$b->reference, $b->user?->name ?? '—', $b->contact_email, trim(($b->contact_dial ?? '').' '.($b->contact_phone ?? '')) ?: null, $r['cruise'],
                $r['departure'] ? [$r['departure'], 'date'] : null, $r['days_to_departure'] === null ? null : [$r['days_to_departure'], 'int'],
                [$b->confirmed_at, 'date'], [$r['age_days'], 'int'], $money($r['total']), $money($r['paid']), $money($r['due']), [$r['paid_share'] / 100, 'percent'],
                $r['last_paid_at'] ? [$r['last_paid_at'], 'date'] : null, $r['seller'],
                $b->isAgentBooking() ? 'Agent · '.($b->agent?->company_name ?: $b->agent?->name) : 'Website']);
        }
        $xlsx->row([['Total: '.$c['bookings'].' bookings', 'total'], ...array_fill(0, 8, ['', 'total']), [$c['value'], 'total_money'], [$c['paid'], 'total_money'],
            [$c['due'], 'total_money'], $c['paid_share'] === null ? ['', 'total'] : [$c['paid_share'] / 100, 'total_percent'], ...array_fill(0, 3, ['', 'total'])], 22);

        return $xlsx;
    }
}
