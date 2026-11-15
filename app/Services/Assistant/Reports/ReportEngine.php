<?php

namespace App\Services\Assistant\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;
use App\Models\User;
use App\Services\AgentPricing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reports for the signed-in website user (customer or agent), built from a spec the chat assistant fills in:
 *
 *   dataset     bookings | payments | sailings
 *   from, to    YYYY-MM-DD, on date_field (bookings: departure | booked; payments: received; sailings: departure)
 *   columns     keys from columns(); empty = the dataset's defaults
 *   filters     status, payment, cruise, type, cabin, available_only (see normalize())
 *   sort        {by: column key, dir: asc | desc}
 *
 * Only the user's own bookings and payments (bookings they made, or made for them as an agent), never anyone else's;
 * sailings are the public prices (the agent rate for agents). Every input is checked here: the spec comes from the
 * model, so nothing in it is trusted. Amounts are US dollars, the currency bookings are stored in.
 */
class ReportEngine
{
    public const DATASETS = ['bookings', 'payments', 'sailings'];

    /** Rows at most: the chat shows the first ones, downloads up to this. */
    public const MAX_ROWS = 2000;

    public function __construct(private AgentPricing $agents) {}

    /**
     * Columns of a dataset for this user: key => [label en, label ar, type, default?].
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3?: bool}>
     */
    public function columns(string $dataset, User $user): array
    {
        $agent = $user->isAgent();

        return match ($dataset) {
            'bookings' => array_filter([
                'reference' => ['Booking no.', 'رقم الحجز', 'text', true],
                'customer' => ['Customer', 'اسم العميل', 'text', true],
                'cruise' => ['Cruise', 'الرحلة', 'text', true],
                'sailing' => ['Sailing code', 'كود الإبحار', 'text'],
                'departure' => ['Departure', 'تاريخ الإبحار', 'date', true],
                'return' => ['Return', 'تاريخ العودة', 'date'],
                'cabins' => ['Cabins', 'الكبائن', 'text'],
                'guests' => ['Guests', 'عدد الضيوف', 'int'],
                'status' => ['Status', 'الحالة', 'text'],
                'payment_status' => ['Payment', 'حالة الدفع', 'text'],
                'original' => $agent ? ['Price before commission', 'السعر قبل العمولة', 'money'] : null,
                'commission' => $agent ? ['Commission', 'العمولة', 'money'] : null,
                'total' => ['Total', 'الإجمالي', 'money', true],
                'paid' => ['Paid', 'المدفوع', 'money', true],
                'due' => ['Due', 'المتبقي', 'money', true],
                'booked_at' => ['Booked on', 'تاريخ الحجز', 'date'],
            ]),
            'payments' => [
                'date' => ['Date', 'التاريخ', 'date', true],
                'reference' => ['Booking no.', 'رقم الحجز', 'text', true],
                'customer' => ['Customer', 'اسم العميل', 'text', true],
                'cruise' => ['Cruise', 'الرحلة', 'text'],
                'type' => ['Type', 'النوع', 'text', true],
                'method' => ['Method', 'طريقة الدفع', 'text', true],
                'amount' => ['Amount', 'المبلغ', 'money', true],
                'receipt' => ['Receipt / transfer no.', 'رقم الإيصال / التحويل', 'text'],
            ],
            'sailings' => [
                'cruise' => ['Cruise', 'الرحلة', 'text', true],
                'sailing' => ['Sailing code', 'كود الإبحار', 'text'],
                'departure' => ['Departure', 'تاريخ الإبحار', 'date', true],
                'return' => ['Return', 'تاريخ العودة', 'date'],
                'nights' => ['Nights', 'الليالي', 'int', true],
                'cabin' => ['Cabin', 'الكابينة', 'text', true],
                'single' => ['1 guest', 'فرد واحد', 'money'],
                'double' => ['2 guests', 'فردين', 'money', true],
                'extra' => ['3rd guest', 'الفرد الثالث', 'money'],
                'per_person' => ['From, per person', 'للفرد من', 'money', true],
                'available' => ['Available', 'متاح', 'text', true],
            ],
            default => [],
        };
    }

    /**
     * The spec, cleaned: unknown datasets, columns, filters and dates dropped or replaced by defaults.
     *
     * @return array{dataset: string, from: ?string, to: ?string, date_field: string, columns: list<string>, filters: array, sort: array{by: string, dir: string}}
     */
    public function normalize(array $spec, User $user): array
    {
        $dataset = in_array($spec['dataset'] ?? null, self::DATASETS, true) ? $spec['dataset'] : 'bookings';
        $columns = $this->columns($dataset, $user);

        $wanted = array_values(array_unique(array_filter((array) ($spec['columns'] ?? []), fn ($c) => is_string($c) && isset($columns[$c]))));
        if ($wanted === []) {
            $wanted = array_keys(array_filter($columns, fn ($c) => $c[3] ?? false));
        }

        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
        [$from, $to] = [$date($spec['from'] ?? null), $date($spec['to'] ?? null)];
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $dateFields = ['bookings' => ['departure', 'booked'], 'payments' => ['received'], 'sailings' => ['departure']][$dataset];
        $dateField = in_array($spec['date_field'] ?? null, $dateFields, true) ? $spec['date_field'] : $dateFields[0];

        $f = (array) ($spec['filters'] ?? []);
        $pick = fn (string $key, array $allowed) => in_array($f[$key] ?? null, $allowed, true) ? $f[$key] : null;
        $text = fn (string $key) => is_string($f[$key] ?? null) && trim($f[$key]) !== '' ? mb_substr(trim($f[$key]), 0, 60) : null;
        $filters = array_filter([
            'status' => $dataset === 'bookings' ? $pick('status', ['confirmed', 'cancelled', 'all']) : null,
            'payment' => $dataset === 'bookings' ? $pick('payment', ['due', 'unpaid', 'partial', 'paid', 'refunded']) : null,
            'type' => $dataset === 'payments' ? $pick('type', ['payment', 'refund']) : null,
            'cruise' => $text('cruise'),
            'cabin' => $dataset === 'sailings' ? $text('cabin') : null,
            'available_only' => $dataset === 'sailings' && ! empty($f['available_only']) ? true : null,
        ], fn ($v) => $v !== null);

        $sortBy = is_string($spec['sort']['by'] ?? null) && isset($columns[$spec['sort']['by']]) ? $spec['sort']['by'] : null;
        $sortBy ??= isset($columns['departure']) ? 'departure' : (isset($columns['date']) ? 'date' : array_key_first($columns));
        $dir = ($spec['sort']['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return ['dataset' => $dataset, 'from' => $from, 'to' => $to, 'date_field' => $dateField, 'columns' => $wanted, 'filters' => $filters, 'sort' => ['by' => $sortBy, 'dir' => $dir]];
    }

    /**
     * Runs a normalized spec.
     *
     * @return array{columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, float>, count: int, truncated: bool}
     */
    public function run(array $spec, User $user): array
    {
        $ar = app()->getLocale() === 'ar';
        $all = $this->columns($spec['dataset'], $user);

        $rows = match ($spec['dataset']) {
            'bookings' => $this->bookings($spec, $user),
            'payments' => $this->payments($spec, $user),
            'sailings' => $this->sailings($spec, $user),
        };

        $by = $spec['sort']['by'];
        $rows = $rows->sortBy(fn ($r) => $r[$by] ?? null, SORT_NATURAL | SORT_FLAG_CASE, $spec['sort']['dir'] === 'desc')->values();
        $truncated = $rows->count() > self::MAX_ROWS;
        $rows = $rows->take(self::MAX_ROWS);

        $columns = array_map(fn ($key) => ['key' => $key, 'label' => $all[$key][$ar ? 1 : 0], 'type' => $all[$key][2]], $spec['columns']);
        $totals = [];
        foreach ($columns as $c) {
            if ($c['type'] === 'money' && $spec['dataset'] !== 'sailings') {
                $totals[$c['key']] = round((float) $rows->sum($c['key']), 2);
            }
        }

        return [
            'columns' => $columns,
            'rows' => $rows->map(fn ($r) => array_intersect_key($r, array_flip($spec['columns'])))->all(),
            'totals' => $totals,
            'count' => $rows->count(),
            'truncated' => $truncated,
        ];
    }

    /** A short title: "My bookings · 2026-12-01 → 2026-12-31". */
    public function title(array $spec): string
    {
        $ar = app()->getLocale() === 'ar';
        $name = [
            'bookings' => $ar ? 'حجوزاتي' : 'My bookings',
            'payments' => $ar ? 'المدفوعات' : 'Payments',
            'sailings' => $ar ? 'الرحلات المتاحة والأسعار' : 'Sailings and prices',
        ][$spec['dataset']];
        if (($spec['filters']['payment'] ?? null) === 'due') {
            $name .= $ar ? ' (عليها مبلغ متبقٍ)' : ' (amount due)';
        }
        $period = match (true) {
            $spec['from'] && $spec['to'] => $spec['from'].' → '.$spec['to'],
            (bool) $spec['from'] => ($ar ? 'من ' : 'from ').$spec['from'],
            (bool) $spec['to'] => ($ar ? 'حتى ' : 'until ').$spec['to'],
            default => null,
        };

        return $period ? "$name · $period" : $name;
    }

    /** Value as shown in the chat and the print page. */
    public static function display(mixed $value, string $type): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            $type === 'money' => '$'.number_format((float) $value, (float) $value == floor((float) $value) ? 0 : 2),
            $type === 'int' => (string) (int) $value,
            default => (string) $value,
        };
    }

    // --- datasets -----------------------------------------------------------------------------------------------

    /** The user's bookings: their own, and those they made for clients as an agent. */
    private function ownBookings(User $user): Builder
    {
        return Booking::where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('agent_id', $user->id));
    }

    private function bookings(array $spec, User $user): Collection
    {
        $ar = app()->getLocale() === 'ar';
        $f = $spec['filters'];
        $field = $spec['date_field'] === 'booked' ? 'confirmed_at' : null;

        $query = $this->ownBookings($user)
            ->with(['sailing.cruise', 'cabins.cabinCategory', 'cabinCategory', 'travellers'])
            ->when(($f['status'] ?? 'confirmed') !== 'all', fn ($q) => $q->where('status', ($f['status'] ?? 'confirmed') === 'cancelled' ? BookingStatus::Cancelled : BookingStatus::Confirmed))
            ->when($f['payment'] ?? null, fn ($q, $p) => $p === 'due'
                ? $q->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::Partial])
                : $q->where('payment_status', $p));

        if ($field) {
            $query->when($spec['from'], fn ($q, $d) => $q->where($field, '>=', $d))
                ->when($spec['to'], fn ($q, $d) => $q->where($field, '<', CarbonImmutable::parse($d)->addDay()->toDateString()));
        } else {
            $query->whereHas('sailing', fn ($s) => $s
                ->when($spec['from'], fn ($q, $d) => $q->whereDate('departure_date', '>=', $d))
                ->when($spec['to'], fn ($q, $d) => $q->whereDate('departure_date', '<=', $d)));
        }

        return $query->limit(self::MAX_ROWS + 1)->get()
            ->filter(fn (Booking $b) => ! isset($f['cruise']) || $this->matches($b->sailing?->cruise, $f['cruise']))
            ->map(function (Booking $b) use ($ar) {
                $primary = $b->primaryTraveller();

                return [
                    'reference' => $b->reference,
                    'customer' => $primary ? trim($primary->first_name.' '.$primary->last_name) : $b->contact_email,
                    'cruise' => $b->sailing?->cruise?->tr('name'),
                    'sailing' => $b->sailing?->code,
                    'departure' => $b->sailing?->departure_date?->toDateString(),
                    'return' => $b->sailing?->return_date?->toDateString(),
                    'cabins' => $this->cabinNames($b, $ar),
                    'guests' => (int) $b->adults + (int) $b->children + (int) $b->infants,
                    'status' => $this->word('status', $b->status->value, $ar),
                    'payment_status' => $this->word('payment', $b->payment_status->value, $ar),
                    'original' => round((float) $b->fare_total + (float) $b->agent_discount_total, 2),
                    'commission' => round((float) $b->agent_discount_total, 2),
                    'total' => round((float) $b->grand_total, 2),
                    'paid' => round((float) $b->amount_paid, 2),
                    // A cancelled booking owes nothing more (its refund is in the payments)
                    'due' => $b->status === BookingStatus::Cancelled ? 0.0 : round($b->amountDue(), 2),
                    'booked_at' => $b->confirmed_at?->toDateString() ?? $b->created_at?->toDateString(),
                ];
            })
            ->values();
    }

    private function payments(array $spec, User $user): Collection
    {
        $ar = app()->getLocale() === 'ar';
        $f = $spec['filters'];

        return Payment::whereIn('booking_id', $this->ownBookings($user)->select('id'))
            ->with(['booking.sailing.cruise', 'booking.travellers'])
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($spec['from'], fn ($q, $d) => $q->where('received_at', '>=', $d))
            ->when($spec['to'], fn ($q, $d) => $q->where('received_at', '<', CarbonImmutable::parse($d)->addDay()->toDateString()))
            ->limit(self::MAX_ROWS + 1)
            ->get()
            ->filter(fn (Payment $p) => ! isset($f['cruise']) || $this->matches($p->booking?->sailing?->cruise, $f['cruise']))
            ->map(function (Payment $p) use ($ar) {
                $primary = $p->booking?->primaryTraveller();
                $type = $p->type->value;

                return [
                    'date' => $p->received_at?->toDateString(),
                    'reference' => $p->booking?->reference,
                    'customer' => $primary ? trim($primary->first_name.' '.$primary->last_name) : $p->booking?->contact_email,
                    'cruise' => $p->booking?->sailing?->cruise?->tr('name'),
                    'type' => $this->word('type', $type, $ar),
                    'method' => $this->word('method', $p->method->value, $ar),
                    // Refunds count against what was paid
                    'amount' => round($type === 'refund' ? -(float) $p->amount : (float) $p->amount, 2),
                    'receipt' => $p->reference,
                ];
            })
            ->values();
    }

    private function sailings(array $spec, User $user): Collection
    {
        $ar = app()->getLocale() === 'ar';
        $f = $spec['filters'];
        $agent = $this->agents->appliesTo($user) ? $user : null;

        $sailings = Sailing::upcoming()
            ->whereHas('cruise', fn ($q) => $q->active())
            ->when($spec['from'], fn ($q, $d) => $q->whereDate('departure_date', '>=', $d))
            ->when($spec['to'], fn ($q, $d) => $q->whereDate('departure_date', '<=', $d))
            ->with(['cruise', 'cabinPrices.cabinCategory', 'cabinPrices.sailing'])
            ->get()
            ->filter(fn (Sailing $s) => ! isset($f['cruise']) || $this->matches($s->cruise, $f['cruise']));

        $rows = collect();
        foreach ($sailings as $s) {
            foreach ($s->cabinPrices as $p) {
                /** @var SailingCabinPrice $p */
                $cabin = $p->cabinCategory;
                $name = $ar && filled($cabin?->name_ar) ? $cabin->name_ar : $cabin?->name_en;
                if (isset($f['cabin']) && ! str_contains(mb_strtolower($name.' '.$cabin?->slug.' '.$cabin?->name_en), mb_strtolower($f['cabin']))) {
                    continue;
                }
                if (! empty($f['available_only']) && $p->is_sold_out) {
                    continue;
                }
                $tiers = $agent ? AgentPricing::discounted($p->tiers(), $this->agents->percentFor($p, $agent)) : $p->tiers();
                $rows->push([
                    'cruise' => $s->cruise?->tr('name'),
                    'sailing' => $s->code,
                    'departure' => $s->departure_date?->toDateString(),
                    'return' => $s->return_date?->toDateString(),
                    'nights' => (int) $s->cruise?->nights,
                    'cabin' => $name,
                    'single' => $tiers['single'],
                    'double' => $tiers['double'],
                    'extra' => $tiers['extra'],
                    'per_person' => $tiers['double'] > 0 ? round($tiers['double'] / 2, 2) : null,
                    'available' => $p->is_sold_out ? ($ar ? 'كامل العدد' : 'Sold out') : ($ar ? 'متاح' : 'Available'),
                ]);
            }
        }

        return $rows;
    }

    // --- helpers ------------------------------------------------------------------------------------------------

    private function matches(?object $cruise, string $query): bool
    {
        $q = mb_strtolower($query);

        return $cruise !== null && collect([$cruise->slug, $cruise->name, $cruise->name_ar])
            ->contains(fn ($v) => $v && str_contains(mb_strtolower((string) $v), $q));
    }

    private function cabinNames(Booking $b, bool $ar): string
    {
        $cabins = $b->cabins->isNotEmpty() ? $b->cabins->pluck('cabinCategory') : collect([$b->cabinCategory]);

        return $cabins->filter()->map(fn ($c) => $ar && filled($c->name_ar) ? $c->name_ar : $c->name_en)
            ->countBy()->map(fn ($n, $name) => $n > 1 ? "$n × $name" : $name)->join('، ');
    }

    private function word(string $group, string $value, bool $ar): string
    {
        $words = [
            'status' => ['confirmed' => ['Confirmed', 'مؤكد'], 'cancelled' => ['Cancelled', 'ملغي']],
            'payment' => ['unpaid' => ['Unpaid', 'غير مدفوع'], 'partial' => ['Partly paid', 'مدفوع جزئيًا'], 'paid' => ['Paid', 'مدفوع'], 'refunded' => ['Refunded', 'مسترد']],
            'type' => ['payment' => ['Payment', 'دفعة'], 'refund' => ['Refund', 'استرداد']],
            'method' => ['bank_transfer' => ['Bank transfer', 'تحويل بنكي'], 'cash' => ['Cash', 'نقدًا'], 'card' => ['Card', 'بطاقة'], 'payment_link' => ['Payment link', 'رابط دفع']],
        ];

        return $words[$group][$value][$ar ? 1 : 0] ?? $value;
    }
}
