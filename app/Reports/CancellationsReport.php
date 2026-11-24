<?php

namespace App\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Report 12 (reports-plan.pdf): bookings cancelled in the period (bookings.cancelled_at), what they were worth, what
 * had been paid on them, what was refunded and what was kept; by cruise, by how long before departure, and the
 * refunds actually paid out in the period (payments of type refund, by the day they were paid, whatever the booking).
 *
 * "Kept" is what was paid less what was refunded (bookings.amount_paid of a cancelled booking): the cancellation
 * penalty, or a refund still to pay. The cancellation rate is about the bookings sold in the period
 * (confirmed_at): how many of them are cancelled now.
 */
class CancellationsReport extends Report
{
    /** Days before departure when cancelled: [label, from, to] (to null: no upper end). */
    public const NOTICE = [
        ['More than 90 days', 91, null],
        ['61 – 90 days', 61, 90],
        ['31 – 60 days', 31, 60],
        ['8 – 30 days', 8, 30],
        ['7 days or less', 0, 7],
        ['After departure', null, -1],
    ];

    public static function key(): string
    {
        return 'cancellations';
    }

    public static function title(): string
    {
        return 'Cancellations & refunds';
    }

    public static function description(): string
    {
        return 'How many bookings were cancelled and for how much, what was refunded and what was kept, by cruise and by how close to departure.';
    }

    public static function icon(): string
    {
        return 'alert';
    }

    public function view(): string
    {
        return 'admin.reports.cancellations';
    }

    public function data(ReportFilters $filters, Request $request): array
    {
        $comparison = $filters->comparison();
        $cancelled = $this->cancelled($filters, $filters->from, $filters->to);

        return [
            'current' => $this->totals($filters, $cancelled, $filters->from, $filters->to),
            'previous' => $comparison ? $this->totals($filters, $this->cancelled($filters, ...$comparison), ...$comparison) : null,
            'comparison' => $comparison,
            'cruises' => $this->byCruise($filters, $cancelled),
            'notice' => $this->notice($cancelled),
            'cancelled' => $cancelled,
            'refunds' => $this->refunds($filters, $filters->from, $filters->to)->with(['booking.user', 'booking.sailing.cruise', 'recordedBy'])
                ->orderByDesc('received_at')->get(),
        ];
    }

    /**
     * The bookings cancelled in a period, each with paid / refunded / kept and its timing.
     *
     * @return Collection<int, array>
     */
    public function cancelled(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $filters->bookings()->where('status', BookingStatus::Cancelled)->whereBetween('cancelled_at', [$from, $to])
            ->with(['sailing.cruise', 'user', 'seller', 'agent', 'payments'])
            ->orderByDesc('cancelled_at')->get()
            ->map(function (Booking $b) {
                $paid = (float) $b->payments->where('type', PaymentType::Payment)->sum('amount');
                $refunded = (float) $b->payments->where('type', PaymentType::Refund)->sum('amount');
                $departure = $b->sailing?->departure_date ? CarbonImmutable::parse($b->sailing->departure_date)->startOfDay() : null;
                $cancelledOn = CarbonImmutable::parse($b->cancelled_at);

                return [
                    'booking' => $b,
                    'cruise_id' => $b->sailing?->cruise_id,
                    'cruise' => $b->sailing?->cruise?->name ?? 'Unknown cruise',
                    'guests' => $b->adults + $b->children + $b->infants,
                    'value' => (float) $b->grand_total,
                    'paid' => $paid,
                    'refunded' => $refunded,
                    'kept' => $paid - $refunded,
                    'cancelled_on' => $cancelledOn,
                    // Whole days, by calendar date: sold → cancelled, cancelled → departure (negative: after it)
                    'held_days' => $b->confirmed_at ? (int) $b->confirmed_at->toImmutable()->startOfDay()->diffInDays($cancelledOn->startOfDay(), false) : null,
                    'notice_days' => $departure ? (int) $cancelledOn->startOfDay()->diffInDays($departure, false) : null,
                ];
            });
    }

    /** @return array<string, float|int|null> */
    private function totals(ReportFilters $filters, Collection $cancelled, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sold = $filters->sold($from, $to)->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as cancelled', [BookingStatus::Cancelled->value])->first();
        $refunds = $this->refunds($filters, $from, $to)->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as amount')->first();
        $notice = $cancelled->pluck('notice_days')->filter(fn ($d) => $d !== null);

        return [
            'cancelled' => $cancelled->count(),
            'guests' => (int) $cancelled->sum('guests'),
            'value' => (float) $cancelled->sum('value'),
            'paid' => (float) $cancelled->sum('paid'),
            'refunded' => (float) $cancelled->sum('refunded'),
            'kept' => (float) $cancelled->sum('kept'),
            'refunds_paid' => (float) $refunds->amount,
            'refunds_count' => (int) $refunds->n,
            'sold' => (int) $sold->n,
            'sold_cancelled' => (int) $sold->cancelled,
            'rate' => $sold->n ? $sold->cancelled / $sold->n * 100 : null,
            'average_notice' => $notice->isNotEmpty() ? $notice->avg() : null,
        ];
    }

    /** Cancellations per cruise, with that cruise's cancellation rate of the bookings sold in the period. */
    private function byCruise(ReportFilters $filters, Collection $cancelled): Collection
    {
        $sold = $filters->sold()->join('sailings', 'sailings.id', '=', 'bookings.sailing_id')->toBase()
            ->selectRaw('sailings.cruise_id, COUNT(*) as n, COALESCE(SUM(CASE WHEN bookings.status = ? THEN 1 ELSE 0 END), 0) as cancelled', [BookingStatus::Cancelled->value])
            ->groupBy('sailings.cruise_id')->get()->keyBy('cruise_id');

        return $cancelled->groupBy('cruise_id')->map(function (Collection $g, $cruiseId) use ($sold) {
            $s = $sold[$cruiseId] ?? null;

            return [
                'name' => $g->first()['cruise'],
                'cancelled' => $g->count(),
                'guests' => (int) $g->sum('guests'),
                'value' => (float) $g->sum('value'),
                'paid' => (float) $g->sum('paid'),
                'refunded' => (float) $g->sum('refunded'),
                'kept' => (float) $g->sum('kept'),
                'sold' => (int) ($s->n ?? 0),
                'rate' => ($s->n ?? 0) ? $s->cancelled / $s->n * 100 : null,
            ];
        })->sortByDesc('value')->values();
    }

    /** Cancellations by how long before departure they came. */
    private function notice(Collection $cancelled): Collection
    {
        $total = max(1, $cancelled->count());

        return collect(self::NOTICE)->map(function (array $band) use ($cancelled, $total) {
            [$label, $min, $max] = $band;
            $g = $cancelled->filter(fn ($c) => $c['notice_days'] !== null
                && ($min === null || $c['notice_days'] >= $min) && ($max === null || $c['notice_days'] <= $max));

            return [
                'name' => $label,
                'cancelled' => $g->count(),
                'value' => (float) $g->sum('value'),
                'refunded' => (float) $g->sum('refunded'),
                'kept' => (float) $g->sum('kept'),
                'share' => $g->count() / $total * 100,
            ];
        });
    }

    /** Refunds paid out in a period on the bookings the filters keep (cancelled or not, sold at any time). */
    private function refunds(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to)
    {
        return Payment::query()->where('type', PaymentType::Refund)
            ->whereIn('booking_id', $filters->bookings()->select('bookings.id'))
            ->whereBetween('received_at', [$from, $to]);
    }

    /** Sheets: Summary (key figures, by cruise, by notice), Cancelled bookings, Refunds paid. */
    public function workbook(ReportFilters $filters): Xlsx
    {
        $data = $this->data($filters, request());

        $xlsx = (new Xlsx)->sheet('Summary', [34, 14, 12, 14, 14, 14, 14, 12, 12]);
        $this->heading($xlsx, $filters);
        self::keyFigures($xlsx, $data['current'], $data['previous'], [
            ['Bookings cancelled', 'cancelled', 'int'],
            ['Guests on them', 'guests', 'int'],
            ['Value of the cancelled bookings', 'value', 'money'],
            ['Paid on them', 'paid', 'money'],
            ['Refunded on them', 'refunded', 'money'],
            ['Kept (paid less refunded)', 'kept', 'money'],
            ['Refunds paid out in the period (any booking)', 'refunds_paid', 'money'],
            ['Bookings sold in the period', 'sold', 'int'],
            ['   of them cancelled now', 'sold_cancelled', 'int'],
            ['Cancellation rate', 'rate', 'pct'],
            ['Average days before departure when cancelled', 'average_notice', 'number'],
        ]);
        self::table($xlsx, 'By cruise', ['Cruise', 'Cancelled', 'Guests', 'Value', 'Paid', 'Refunded', 'Kept', 'Sold in period', 'Cancellation rate'],
            $data['cruises']->map(fn ($r) => [$r['name'], [$r['cancelled'], 'int'], [$r['guests'], 'int'], [$r['value'], 'money'], [$r['paid'], 'money'],
                [$r['refunded'], 'money'], [$r['kept'], 'money'], [$r['sold'], 'int'], $r['rate'] === null ? ['—', 'muted'] : [$r['rate'] / 100, 'percent']]));
        self::table($xlsx, 'How long before departure', ['Cancelled', 'Bookings', 'Share', 'Value', 'Refunded', 'Kept'],
            $data['notice']->map(fn ($r) => [$r['name'], [$r['cancelled'], 'int'], [$r['share'] / 100, 'percent'], [$r['value'], 'money'], [$r['refunded'], 'money'], [$r['kept'], 'money']]));

        $xlsx->sheet('Cancelled bookings', [15, 22, 26, 13, 14, 14, 13, 13, 8, 13, 13, 13, 13, 18], table: true)
            ->row(['Reference', 'Customer', 'Cruise', 'Departure', 'Sold on', 'Cancelled on', 'Days held', 'Days before departure', 'Guests', 'Value', 'Paid', 'Refunded', 'Kept', 'Assigned to'], 32);
        foreach ($data['cancelled'] as $r) {
            $b = $r['booking'];
            $xlsx->row([$b->reference, $b->user?->name ?? $b->contact_email, $r['cruise'], $b->sailing?->departure_date ? [$b->sailing->departure_date, 'date'] : null,
                $b->confirmed_at, $r['cancelled_on'], $r['held_days'] === null ? null : [$r['held_days'], 'int'], $r['notice_days'] === null ? null : [$r['notice_days'], 'int'],
                [$r['guests'], 'int'], [$r['value'], 'money'], [$r['paid'], 'money'], [$r['refunded'], 'money'], [$r['kept'], 'money'], $b->seller?->name ?? 'Unassigned']);
        }
        $c = $data['current'];
        $xlsx->row([['Total: '.$c['cancelled'].' cancelled', 'total'], ...array_fill(0, 7, ['', 'total']), [$c['guests'], 'total_int'],
            [$c['value'], 'total_money'], [$c['paid'], 'total_money'], [$c['refunded'], 'total_money'], [$c['kept'], 'total_money'], ['', 'total']], 22);

        $xlsx->sheet('Refunds paid', [17, 15, 22, 26, 14, 13, 12, 18, 30], table: true)
            ->row(['Paid on', 'Booking', 'Customer', 'Cruise', 'Method', 'Amount', 'Booking status', 'Recorded by', 'Reference / notes'], 32);
        foreach ($data['refunds'] as $r) {
            $xlsx->row([$r->received_at, $r->booking?->reference, $r->booking?->user?->name ?? $r->booking?->contact_email, $r->booking?->sailing?->cruise?->name,
                str_replace('_', ' ', ucfirst($r->method->value)), [(float) $r->amount, 'money'], $r->booking ? ucfirst($r->booking->status->value) : null,
                $r->recordedBy?->name, trim(($r->reference ?? '').' '.($r->notes ?? '')) ?: null]);
        }
        $xlsx->row([['Total: '.$data['refunds']->count().' refunds', 'total'], ...array_fill(0, 4, ['', 'total']), [(float) $data['refunds']->sum('amount'), 'total_money'],
            ...array_fill(0, 3, ['', 'total'])], 22);

        return $xlsx;
    }
}
