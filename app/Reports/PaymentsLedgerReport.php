<?php

namespace App\Reports;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Report 4 (reports-plan.pdf): the money in and out day by day, as the team recorded it (payments, by received_at),
 * by method and by who recorded it, with the receipts attached, to reconcile with the accounts and the bank.
 *
 * Every payment and refund received in the period on the bookings the filters keep (sold at any time, cancelled or
 * not). Net = payments − refunds. Amounts are in the booking's currency, USD.
 */
class PaymentsLedgerReport extends Report
{
    public static function key(): string
    {
        return 'payments';
    }

    public static function title(): string
    {
        return 'Payments ledger';
    }

    public static function description(): string
    {
        return 'The money received and refunded day by day, by method and by who recorded it, with the receipts, to reconcile with the accounts and the bank.';
    }

    public static function icon(): string
    {
        return 'card';
    }

    public function view(): string
    {
        return 'admin.reports.payments';
    }

    public static function method(PaymentMethod $method): string
    {
        return str_replace('_', ' ', ucfirst($method->value));
    }

    public function data(ReportFilters $filters, Request $request): array
    {
        $comparison = $filters->comparison();
        $entries = $this->entries($filters, $filters->from, $filters->to)
            ->with(['booking.user', 'booking.sailing.cruise', 'recordedBy', 'receipts'])->withCount('receipts')
            ->orderBy('received_at')->orderBy('id')->get();

        return [
            'current' => $this->totals($entries),
            'previous' => $comparison ? $this->totals($this->entries($filters, ...$comparison)->withCount('receipts')->get()) : null,
            'comparison' => $comparison,
            'days' => $this->days($entries),
            'methods' => $this->methods($entries),
            'staff' => $this->staff($entries),
            'chart' => $this->chart($filters, $entries),
            'entries' => $entries,
        ];
    }

    /** @return Builder<Payment> payments and refunds received in a period on the bookings the filters keep */
    public function entries(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Payment::query()
            ->whereIn('booking_id', $filters->bookings()->select('bookings.id'))
            ->whereBetween('received_at', [$from, $to]);
    }

    /** Signed amount: a refund counts against. */
    public static function signed(Payment $p): float
    {
        return $p->type === PaymentType::Refund ? -(float) $p->amount : (float) $p->amount;
    }

    /** @return array<string, float|int|null> */
    private function totals(Collection $entries): array
    {
        $in = $entries->where('type', PaymentType::Payment);
        $out = $entries->where('type', PaymentType::Refund);
        $largest = $in->sortByDesc(fn ($p) => (float) $p->amount)->first();

        return [
            'entries' => $entries->count(),
            'received' => (float) $in->sum('amount'),
            'payments' => $in->count(),
            'refunded' => (float) $out->sum('amount'),
            'refunds' => $out->count(),
            'net' => (float) $in->sum('amount') - (float) $out->sum('amount'),
            'bookings' => $in->pluck('booking_id')->unique()->count(),
            'average' => $in->count() ? (float) $in->sum('amount') / $in->count() : null,
            'largest' => $largest ? (float) $largest->amount : null,
            'without_receipt' => $entries->where('receipts_count', 0)->count(),
            'days' => $entries->map(fn ($p) => $p->received_at->toDateString())->unique()->count(),
        ];
    }

    /** One row per day with an entry, and the running net over the period. */
    private function days(Collection $entries): Collection
    {
        $running = 0.0;

        return $entries->groupBy(fn (Payment $p) => $p->received_at->toDateString())->map(function (Collection $g, string $day) use (&$running) {
            $received = (float) $g->where('type', PaymentType::Payment)->sum('amount');
            $refunded = (float) $g->where('type', PaymentType::Refund)->sum('amount');
            $running += $received - $refunded;

            return ['day' => CarbonImmutable::parse($day), 'entries' => $g->count(), 'received' => $received, 'refunded' => $refunded,
                'net' => $received - $refunded, 'running' => $running, 'without_receipt' => $g->where('receipts_count', 0)->count()];
        })->values();
    }

    /** Every method (even unused ones), with its share of the money received. */
    private function methods(Collection $entries): Collection
    {
        $total = max(0.01, (float) $entries->where('type', PaymentType::Payment)->sum('amount'));

        return collect(PaymentMethod::cases())->map(function (PaymentMethod $m) use ($entries, $total) {
            $g = $entries->where('method', $m);
            $received = (float) $g->where('type', PaymentType::Payment)->sum('amount');
            $refunded = (float) $g->where('type', PaymentType::Refund)->sum('amount');

            return ['name' => self::method($m), 'entries' => $g->count(), 'received' => $received, 'refunded' => $refunded,
                'net' => $received - $refunded, 'share' => $received / $total * 100];
        })->sortByDesc('received')->values();
    }

    /** Per team member who recorded the entries. */
    private function staff(Collection $entries): Collection
    {
        return $entries->groupBy(fn (Payment $p) => $p->recorded_by ?? 0)->map(function (Collection $g) {
            $received = (float) $g->where('type', PaymentType::Payment)->sum('amount');
            $refunded = (float) $g->where('type', PaymentType::Refund)->sum('amount');

            return ['name' => $g->first()->recordedBy?->name ?? 'Not recorded', 'entries' => $g->count(), 'received' => $received, 'refunded' => $refunded,
                'net' => $received - $refunded, 'without_receipt' => $g->where('receipts_count', 0)->count()];
        })->sortByDesc('received')->values();
    }

    /** Chart: money in and out per day (per month over a period longer than about two months), every bucket shown. */
    private function chart(ReportFilters $filters, Collection $entries): array
    {
        $monthly = $filters->days() > 62;
        $key = fn ($d) => $monthly ? $d->format('Y-m') : $d->format('Y-m-d');
        $buckets = [];
        for ($d = $filters->from->startOfDay(); $d->lte($filters->to); $d = $monthly ? $d->startOfMonth()->addMonth() : $d->addDay()) {
            $buckets[$key($d)] = ['label' => $d->format($monthly ? 'M Y' : 'j M'), 'in' => 0.0, 'out' => 0.0];
        }
        foreach ($entries as $p) {
            $k = $key($p->received_at);
            if (isset($buckets[$k])) {
                $buckets[$k][$p->type === PaymentType::Refund ? 'out' : 'in'] += (float) $p->amount;
            }
        }

        return ['unit' => $monthly ? 'month' : 'day', 'buckets' => array_values($buckets)];
    }

    /** Sheets: Summary (key figures, by method, by staff), By day, Ledger (every entry). */
    public function workbook(ReportFilters $filters): Xlsx
    {
        $data = $this->data($filters, request());
        $c = $data['current'];
        $money = fn ($v) => [$v, 'money'];

        $xlsx = (new Xlsx)->sheet('Summary', [34, 12, 14, 14, 14, 12]);
        $this->heading($xlsx, $filters);
        self::keyFigures($xlsx, $c, $data['previous'], [
            ['Received (payments)', 'received', 'money'],
            ['   number of payments', 'payments', 'int'],
            ['Refunded', 'refunded', 'money'],
            ['   number of refunds', 'refunds', 'int'],
            ['Net (received less refunded)', 'net', 'money'],
            ['Bookings paid on', 'bookings', 'int'],
            ['Average payment', 'average', 'money'],
            ['Largest payment', 'largest', 'money'],
            ['Entries without a receipt', 'without_receipt', 'int'],
        ]);
        self::table($xlsx, 'By method', ['Method', 'Entries', 'Received', 'Refunded', 'Net', 'Share received'],
            $data['methods']->map(fn ($r) => [$r['name'], [$r['entries'], 'int'], $money($r['received']), $money($r['refunded']), $money($r['net']), [$r['share'] / 100, 'percent']]));
        self::table($xlsx, 'By who recorded it', ['Recorded by', 'Entries', 'Received', 'Refunded', 'Net', 'No receipt'],
            $data['staff']->map(fn ($r) => [$r['name'], [$r['entries'], 'int'], $money($r['received']), $money($r['refunded']), $money($r['net']), [$r['without_receipt'], 'int']]));

        $xlsx->sheet('By day', [14, 10, 14, 14, 14, 16, 12], table: true)
            ->row(['Day', 'Entries', 'Received', 'Refunded', 'Net', 'Running total', 'No receipt'], 32);
        foreach ($data['days'] as $r) {
            $xlsx->row([[$r['day'], 'date'], [$r['entries'], 'int'], $money($r['received']), $money($r['refunded']), $money($r['net']), $money($r['running']), [$r['without_receipt'], 'int']]);
        }
        $xlsx->row([['Total: '.$c['days'].' days', 'total'], [$c['entries'], 'total_int'], [$c['received'], 'total_money'], [$c['refunded'], 'total_money'],
            [$c['net'], 'total_money'], ['', 'total'], [$c['without_receipt'], 'total_int']], 22);

        $xlsx->sheet('Ledger', [17, 15, 22, 26, 10, 15, 13, 8, 22, 30, 18, 17, 10, 12, 12], table: true)
            ->row(['Received on', 'Booking', 'Customer', 'Cruise', 'Type', 'Method', 'Amount', 'Currency', 'Reference', 'Notes', 'Recorded by', 'Recorded on', 'Receipts',
                'Booking status', 'Payment status'], 32);
        foreach ($data['entries'] as $p) {
            $b = $p->booking;
            $xlsx->row([$p->received_at, $b?->reference, $b?->user?->name ?? $b?->contact_email, $b?->sailing?->cruise?->name, ucfirst($p->type->value), self::method($p->method),
                $money(self::signed($p)), $p->currency, $p->reference, $p->notes, $p->recordedBy?->name, $p->created_at, [$p->receipts_count, 'int'],
                $b ? ucfirst($b->status->value) : null, $b?->payment_status ? ucfirst($b->payment_status->value) : null]);
        }
        $xlsx->row([['Net: '.$c['entries'].' entries', 'total'], ...array_fill(0, 5, ['', 'total']), [$c['net'], 'total_money'], ...array_fill(0, 5, ['', 'total']),
            [(int) $data['entries']->sum('receipts_count'), 'total_int'], ['', 'total'], ['', 'total']], 22);

        return $xlsx;
    }
}
