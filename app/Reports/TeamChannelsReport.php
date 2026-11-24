<?php

namespace App\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentType;
use App\Models\AgentTier;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Reports 8, 9 and 10 (reports-plan.pdf, "Sales team and channels"), on one page:
 *
 * - Staff: each staff member's bookings (bookings.seller_id) sold in the period, their revenue, what was paid on them
 *   and what is still due; plus the payments each one recorded in the period (payments.recorded_by, by the day the
 *   money came in, so a payment today on a booking sold last month counts today).
 * - Agents (B2B): by agent type (bookings.agent_tier_id, the type when they booked) and by agent: bookings, revenue
 *   and the agent discount given (agent_discount_total, off the public fare).
 * - Offers and promo codes: how many bookings of the period used each one, what it took off, and the revenue of
 *   those bookings. Offers come from each booking's price_snapshot.offers (a booking can use several offers, so it
 *   counts under each of them); promo codes from bookings.promo_code_id / discount_total.
 *
 * Same rules as the sales summary (report 1): the period is the sale date (confirmed_at), cancelled bookings are left
 * out of the figures, amounts are USD, and a seller only sees the bookings assigned to them.
 */
class TeamChannelsReport extends Report
{
    public static function key(): string
    {
        return 'team-channels';
    }

    public static function title(): string
    {
        return 'Sales team & channels';
    }

    public static function description(): string
    {
        return 'Each staff member\'s bookings, revenue and collection; agents and agent types with the discount they got; and what every offer and promo code gave away against what it brought in.';
    }

    public static function icon(): string
    {
        return 'users';
    }

    public function view(): string
    {
        return 'admin.reports.team-channels';
    }

    public function data(ReportFilters $filters, Request $request): array
    {
        $comparison = $filters->comparison();

        return [
            'current' => $this->headline($filters, $filters->from, $filters->to),
            'previous' => $comparison ? $this->headline($filters, ...$comparison) : null,
            'comparison' => $comparison,
            'staff' => $this->staff($filters, $comparison),
            'tiers' => $this->tiers($filters),
            'agents' => $this->agents($filters, $comparison),
            'promos' => $this->promos($filters),
            'offers' => $this->offers($filters),
        ];
    }

    /**
     * The figures at the top of the page, for one period.
     *
     * @return array<string, float|int|null>
     */
    public function headline(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = (array) $this->confirmed($filters, $from, $to)->selectRaw('
            COUNT(*) as bookings,
            COALESCE(SUM(grand_total), 0) as revenue,
            COALESCE(SUM(CASE WHEN agent_id IS NOT NULL THEN 1 ELSE 0 END), 0) as agent_bookings,
            COALESCE(SUM(CASE WHEN agent_id IS NOT NULL THEN grand_total ELSE 0 END), 0) as agent_revenue,
            COALESCE(SUM(CASE WHEN agent_id IS NOT NULL THEN fare_total + agent_discount_total ELSE 0 END), 0) as agent_public_fare,
            COALESCE(SUM(agent_discount_total), 0) as agent_discount,
            COALESCE(SUM(discount_total), 0) as promo,
            COALESCE(SUM(offer_discount_total), 0) as offers,
            COALESCE(SUM(CASE WHEN discount_total > 0 OR offer_discount_total > 0 THEN 1 ELSE 0 END), 0) as discounted
        ')->first();
        $pay = (array) $this->paymentTotals($this->payments($filters, $from, $to))->first();

        $t = array_map('floatval', $row + $pay);
        foreach (['bookings', 'agent_bookings', 'discounted', 'payments'] as $count) {
            $t[$count] = (int) $t[$count];
        }
        $t['net_received'] = $t['received'] - $t['refunded'];
        $t['discounts'] = $t['promo'] + $t['offers'];
        $t['agent_share'] = $t['revenue'] > 0 ? $t['agent_revenue'] / $t['revenue'] * 100 : null;
        $t['agent_discount_rate'] = $t['agent_public_fare'] > 0 ? $t['agent_discount'] / $t['agent_public_fare'] * 100 : null;

        return $t;
    }

    /**
     * One row per staff member (the whole team, so who sold nothing shows too; only the chosen one with the staff
     * filter, only themselves for a seller), best revenue first, then "Unassigned" bookings and payments recorded by
     * someone else (e.g. a former staff member).
     *
     * @return array{rows: Collection<int, array>, total: array}
     */
    private function staff(ReportFilters $filters, ?array $comparison): array
    {
        $sold = $this->byStaff($filters, $filters->from, $filters->to);
        $before = $comparison ? $this->byStaff($filters, ...$comparison) : collect();
        $cancelled = $filters->sold()->where('status', BookingStatus::Cancelled)->toBase()
            ->selectRaw('seller_id, COUNT(*) as n')->groupBy('seller_id')->get()
            ->keyBy(fn ($r) => $r->seller_id === null ? 'none' : (string) $r->seller_id);
        $recorded = $this->paymentTotals($this->payments($filters, $filters->from, $filters->to)->addSelect('recorded_by')->groupBy('recorded_by'))
            ->get()->keyBy(fn ($r) => $r->recorded_by === null ? 'none' : (string) $r->recorded_by);

        $team = match (true) {
            $filters->seller === 'none' => collect(),
            $filters->seller !== null => User::whereKey((int) $filters->seller)->get(['id', 'name', 'role']),
            default => User::assignable()->visibleTo($filters->viewer)->get(['id', 'name', 'role']),
        };
        // Bookings still assigned to someone who is no longer on the team list (role changed)
        $missing = $sold->keys()->merge($before->keys())->merge($cancelled->keys())
            ->reject(fn ($k) => $k === 'none' || $team->contains('id', (int) $k))->unique();
        if ($missing->isNotEmpty() && $filters->seller === null) {
            $team = $team->concat(User::whereKey($missing->map(fn ($k) => (int) $k)->all())->get(['id', 'name', 'role']));
        }

        $row = function (string $key, string $name, ?string $role, ?object $payments) use ($sold, $before, $cancelled, $comparison) {
            $s = $sold[$key] ?? null;
            $revenue = (float) ($s->revenue ?? 0);
            $collected = (float) ($s->collected ?? 0);

            return [
                'id' => $key === 'none' ? null : (int) $key,
                'name' => $name,
                'role' => $role,
                'bookings' => (int) ($s->bookings ?? 0),
                'guests' => (int) ($s->guests ?? 0),
                'revenue' => $revenue,
                'collected' => $collected,
                'outstanding' => (float) ($s->outstanding ?? 0),
                'collection_rate' => $revenue > 0 ? $collected / $revenue * 100 : null,
                'cancelled' => (int) ($cancelled[$key]->n ?? 0),
                'payments' => (int) ($payments->payments ?? 0),
                'received' => (float) ($payments->received ?? 0),
                'refunded' => (float) ($payments->refunded ?? 0),
                'net_received' => (float) ($payments->received ?? 0) - (float) ($payments->refunded ?? 0),
                'previous' => $comparison ? (float) ($before[$key]->revenue ?? 0) : null,
            ];
        };

        $rows = $team->unique('id')
            ->map(fn (User $u) => $row((string) $u->id, $u->name, $u->role?->label(), $recorded[(string) $u->id] ?? null))
            ->sortBy([['revenue', 'desc'], ['bookings', 'desc'], ['name', 'asc']])->values();

        if ($sold->has('none') || $cancelled->has('none') || $before->has('none')) {
            $rows->push($row('none', 'Unassigned', null, null));
        }
        // Payments on these bookings recorded by someone not listed above
        $others = $recorded->reject(fn ($r, $k) => $k !== 'none' && $team->contains('id', (int) $k));
        if ($others->isNotEmpty()) {
            $rows->push([
                'id' => null, 'name' => 'Recorded by others', 'role' => null,
                'bookings' => 0, 'guests' => 0, 'revenue' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0, 'collection_rate' => null,
                'cancelled' => 0, 'previous' => null,
                'payments' => (int) $others->sum('payments'),
                'received' => (float) $others->sum('received'),
                'refunded' => (float) $others->sum('refunded'),
                'net_received' => (float) $others->sum('received') - (float) $others->sum('refunded'),
                'others' => true,
            ]);
        }

        $total = [];
        foreach (['bookings', 'guests', 'revenue', 'collected', 'outstanding', 'cancelled', 'payments', 'received', 'refunded', 'net_received'] as $k) {
            $total[$k] = $rows->sum($k);
        }
        $total['collection_rate'] = $total['revenue'] > 0 ? $total['collected'] / $total['revenue'] * 100 : null;
        $total['previous'] = $comparison ? $rows->sum('previous') : null;

        return ['rows' => $rows, 'total' => $total];
    }

    /** Confirmed bookings of a period per assigned staff member ("none": unassigned). */
    private function byStaff(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->confirmed($filters, $from, $to)->selectRaw('
            seller_id,
            COUNT(*) as bookings,
            COALESCE(SUM(adults + children + infants), 0) as guests,
            COALESCE(SUM(grand_total), 0) as revenue,
            COALESCE(SUM(amount_paid), 0) as collected,
            COALESCE(SUM(CASE WHEN grand_total > amount_paid THEN grand_total - amount_paid ELSE 0 END), 0) as outstanding
        ')->groupBy('seller_id')->get()->keyBy(fn ($r) => $r->seller_id === null ? 'none' : (string) $r->seller_id);
    }

    /** Agent bookings of the period per agent type, as it was when they booked. */
    private function tiers(ReportFilters $filters): Collection
    {
        $rows = $this->confirmed($filters, $filters->from, $filters->to)->whereNotNull('agent_id')->selectRaw('
            agent_tier_id,
            COUNT(DISTINCT agent_id) as agents,
            COUNT(*) as bookings,
            COALESCE(SUM(fare_total + agent_discount_total), 0) as public_fare,
            COALESCE(SUM(agent_discount_total), 0) as agent_discount,
            COALESCE(SUM(grand_total), 0) as revenue
        ')->groupBy('agent_tier_id')->get();
        $names = AgentTier::whereKey($rows->pluck('agent_tier_id')->filter()->all())->pluck('name', 'id');

        return $rows->map(fn ($r) => [
            'name' => $r->agent_tier_id ? ($names[$r->agent_tier_id] ?? 'Deleted agent type') : 'No agent type',
            'agents' => (int) $r->agents,
            'bookings' => (int) $r->bookings,
            'public_fare' => (float) $r->public_fare,
            'agent_discount' => (float) $r->agent_discount,
            'discount_rate' => (float) $r->public_fare > 0 ? (float) $r->agent_discount / (float) $r->public_fare * 100 : null,
            'revenue' => (float) $r->revenue,
        ])->sortByDesc('revenue')->values();
    }

    /** One row per agent who sold in the period or in the comparison period (so an agent who stopped shows too). */
    private function agents(ReportFilters $filters, ?array $comparison): Collection
    {
        $query = fn (CarbonImmutable $from, CarbonImmutable $to) => $this->confirmed($filters, $from, $to)->whereNotNull('agent_id')->selectRaw('
            agent_id,
            COUNT(*) as bookings,
            COALESCE(SUM(adults + children + infants), 0) as guests,
            COALESCE(SUM(fare_total + agent_discount_total), 0) as public_fare,
            COALESCE(SUM(agent_discount_total), 0) as agent_discount,
            COALESCE(SUM(grand_total), 0) as revenue,
            COALESCE(SUM(amount_paid), 0) as collected,
            COALESCE(SUM(CASE WHEN grand_total > amount_paid THEN grand_total - amount_paid ELSE 0 END), 0) as outstanding,
            MAX(confirmed_at) as last_sale
        ')->groupBy('agent_id')->get()->keyBy(fn ($r) => (int) $r->agent_id);

        $now = $query($filters->from, $filters->to);
        $before = $comparison ? $query(...$comparison) : collect();
        $ids = $now->keys()->merge($before->keys())->unique()->values();
        $users = User::with('agentTier:id,name')->whereKey($ids->all())->get(['id', 'name', 'company_name', 'agent_tier_id'])->keyBy('id');

        return $ids->map(function (int $id) use ($now, $before, $users, $comparison) {
            $r = $now[$id] ?? null;
            $u = $users[$id] ?? null;
            $publicFare = (float) ($r->public_fare ?? 0);

            return [
                'id' => $id,
                'name' => $u ? ($u->company_name ?: $u->name) : 'Deleted account',
                'contact' => $u?->company_name ? $u->name : null,
                'tier' => $u?->agentTier?->name,
                'bookings' => (int) ($r->bookings ?? 0),
                'guests' => (int) ($r->guests ?? 0),
                'public_fare' => $publicFare,
                'agent_discount' => (float) ($r->agent_discount ?? 0),
                'discount_rate' => $publicFare > 0 ? (float) $r->agent_discount / $publicFare * 100 : null,
                'revenue' => (float) ($r->revenue ?? 0),
                'collected' => (float) ($r->collected ?? 0),
                'outstanding' => (float) ($r->outstanding ?? 0),
                'last_sale' => $r?->last_sale ? CarbonImmutable::parse($r->last_sale) : null,
                'previous' => $comparison ? (float) ($before[$id]->revenue ?? 0) : null,
            ];
        })->sortBy([['revenue', 'desc'], ['name', 'asc']])->values();
    }

    /** Promo codes used by the bookings of the period. */
    private function promos(ReportFilters $filters): Collection
    {
        $rows = $this->confirmed($filters, $filters->from, $filters->to)
            ->where(fn (QueryBuilder $q) => $q->whereNotNull('promo_code_id')->orWhere('discount_total', '>', 0))
            ->selectRaw('promo_code_id, COUNT(*) as bookings, COALESCE(SUM(discount_total), 0) as discount, COALESCE(SUM(grand_total), 0) as revenue')
            ->groupBy('promo_code_id')->get();
        $codes = PromoCode::whereKey($rows->pluck('promo_code_id')->filter()->all())->get()->keyBy('id');

        return $rows->map(function ($r) use ($codes) {
            $promo = $codes[$r->promo_code_id] ?? null;

            return self::effect([
                'name' => $promo?->code ?? 'Deleted code',
                'detail' => $promo?->label,
                'status' => $promo ? self::promoStatus($promo) : null,
                'used_count' => $promo?->used_count,
                'max_uses' => $promo?->max_uses,
            ], (int) $r->bookings, (float) $r->discount, (float) $r->revenue);
        })->sortByDesc('discount')->values();
    }

    /**
     * Offers used by the bookings of the period, from each booking's price_snapshot.offers (what each offer took
     * off that booking). What a booking's offers took off but its snapshot doesn't itemise goes on its own line.
     */
    private function offers(ReportFilters $filters): Collection
    {
        $lines = [];
        $add = function (string $key, ?int $id, string $name, float $discount, float $revenue) use (&$lines) {
            $lines[$key] ??= ['id' => $id, 'name' => $name, 'bookings' => 0, 'discount' => 0.0, 'revenue' => 0.0];
            $lines[$key]['bookings']++;
            $lines[$key]['discount'] += $discount;
            $lines[$key]['revenue'] += $revenue;
        };

        $this->confirmed($filters, $filters->from, $filters->to)->where('offer_discount_total', '>', 0)
            ->select(['id', 'grand_total', 'offer_discount_total', 'price_snapshot'])->orderBy('id')
            ->each(function ($b) use ($add) {
                $snapshot = json_decode((string) $b->price_snapshot, true);
                $itemised = 0.0;
                foreach ((is_array($snapshot) ? $snapshot['offers'] ?? [] : []) as $o) {
                    $off = (float) ($o['discount'] ?? 0);
                    if (! isset($o['id']) || $off <= 0) {
                        continue;
                    }
                    $add('o'.$o['id'], (int) $o['id'], (string) ($o['name'] ?? 'Offer #'.$o['id']), $off, (float) $b->grand_total);
                    $itemised += $off;
                }
                if ((float) $b->offer_discount_total - $itemised >= 0.01) {
                    $add('other', null, 'Not itemised on the booking', (float) $b->offer_discount_total - $itemised, (float) $b->grand_total);
                }
            });

        $offers = Offer::whereKey(array_filter(array_column($lines, 'id')))->get()->keyBy('id');

        return collect($lines)->map(function (array $l) use ($offers) {
            $offer = $l['id'] ? ($offers[$l['id']] ?? null) : null;

            return self::effect([
                'name' => $offer?->name ?? $l['name'],
                'detail' => $offer ? $offer->discount_label.' · '.$offer->scope_label : ($l['id'] ? 'Deleted offer' : null),
                'status' => $offer?->status_label,
                'used_count' => $offer?->used_count,
                'max_uses' => $offer?->max_uses,
            ], $l['bookings'], round($l['discount'], 2), $l['revenue']);
        })->sortByDesc('discount')->values();
    }

    /**
     * What an offer or promo code gave and brought: discount share = discount / (revenue + discount), the part of the
     * price before it that it gave away; revenue per $1 off = revenue / discount.
     */
    private static function effect(array $about, int $bookings, float $discount, float $revenue): array
    {
        return $about + [
            'bookings' => $bookings,
            'discount' => $discount,
            'revenue' => $revenue,
            'average' => $bookings ? $revenue / $bookings : 0.0,
            'share' => $revenue + $discount > 0 ? $discount / ($revenue + $discount) * 100 : null,
            'per_dollar' => $discount > 0 ? $revenue / $discount : null,
        ];
    }

    /** Admin list wording, like Offer::status_label. */
    private static function promoStatus(PromoCode $promo): string
    {
        return match (true) {
            ! $promo->is_active => 'Off',
            $promo->ends_at && $promo->ends_at->isPast() => 'Ended',
            $promo->max_uses !== null && $promo->used_count >= $promo->max_uses => 'Used up',
            $promo->starts_at && $promo->starts_at->isFuture() => 'Scheduled',
            default => 'Live',
        };
    }

    /** Confirmed (not cancelled) bookings sold in a period, as a plain query for sums. */
    private function confirmed(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): QueryBuilder
    {
        return $filters->sold($from, $to)->where('status', BookingStatus::Confirmed)->toBase();
    }

    /** Payments and refunds received in a period on the bookings the filters keep (whenever they were sold). */
    private function payments(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): QueryBuilder
    {
        return Payment::query()
            ->whereIn('booking_id', $filters->bookings()->select('bookings.id'))
            ->whereBetween('received_at', [$from, $to])
            ->toBase();
    }

    private function paymentTotals(QueryBuilder $payments): QueryBuilder
    {
        return $payments->selectRaw('
            COUNT(*) as payments,
            COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as received,
            COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as refunded
        ', [PaymentType::Payment->value, PaymentType::Refund->value]);
    }

    /**
     * Sheets: Summary, Staff, Agent types, Agents, Promo codes, Offers: the page's tables, each with a filter and a
     * totals row.
     */
    public function workbook(ReportFilters $filters): Xlsx
    {
        $data = $this->data($filters, request());
        $c = $data['current'];
        $p = $data['previous'];
        $hasP = $p !== null;
        $change = fn (float|int $now, float|int|null $before) => $before === null ? null
            : (($d = SalesSummaryReport::change($now, $before)) === null ? ['—', 'muted'] : [$d / 100, 'percent']);
        $pct = fn (?float $v) => $v === null ? ['—', 'muted'] : [$v / 100, 'percent'];
        $totalPct = fn (?float $v) => $v === null ? ['', 'total'] : [$v / 100, 'total_percent'];

        // Summary
        $xlsx = (new Xlsx)->sheet('Summary', [40, 18, 18, 14]);
        $this->heading($xlsx, $filters);
        $xlsx->row([['Key figures', 'section'], ['', 'section'], ['', 'section'], ['', 'section']])
            ->row([['', 'header'], ['This period', 'header'], [$hasP ? 'Comparison' : '', 'header'], [$hasP ? 'Change' : '', 'header']], 22);
        foreach ([
            ['Bookings (cancelled not included)', 'bookings', 'int'],
            ['Net revenue', 'revenue', 'money'],
            ['Payments received (by the day received)', 'received', 'money'],
            ['Refunds paid out', 'refunded', 'money'],
            ['Net received', 'net_received', 'money'],
            ['Bookings by agents', 'agent_bookings', 'int'],
            ['Sold by agents (B2B)', 'agent_revenue', 'money'],
            ['Agent discount given', 'agent_discount', 'money'],
            ['Promo code discounts', 'promo', 'money'],
            ['Offer discounts', 'offers', 'money'],
            ['Bookings with a promo code or offer', 'discounted', 'int'],
        ] as [$label, $key, $style]) {
            $xlsx->row([$label, [$c[$key], $style], $hasP ? [$p[$key], $style] : null, $change($c[$key], $hasP ? $p[$key] : null)]);
        }
        $xlsx->row(['Agents\' share of the revenue', $pct($c['agent_share']), $hasP ? $pct($p['agent_share']) : null])
            ->row(['Agent discount, % of the public fare', $pct($c['agent_discount_rate']), $hasP ? $pct($p['agent_discount_rate']) : null])
            ->row()
            ->row([['Payments are counted on the day the money came in, on bookings sold at any time; everything else is the bookings sold in the period.', 'subtitle']]);

        // Staff
        $staff = $data['staff'];
        $columns = [['Staff member', 24], ['Role', 12], ['Bookings', 10], ['Guests', 9], ['Net revenue', 14], ['Paid', 14], ['Still to collect', 14],
            ['Collection rate', 11], ['Cancelled', 10], ['Payments recorded', 11], ['Received', 14], ['Refunded', 13], ['Net received', 14]];
        if ($hasP) {
            array_push($columns, ['Revenue, comparison', 14], ['Change', 10]);
        }
        $xlsx->sheet('Staff', array_column($columns, 1), table: true)->row(array_column($columns, 0), 32);
        foreach ($staff['rows'] as $r) {
            $xlsx->row([
                $r['name'], $r['role'], [$r['bookings'], 'int'], [$r['guests'], 'int'], [$r['revenue'], 'money'], [$r['collected'], 'money'],
                [$r['outstanding'], 'money'], $pct($r['collection_rate']), [$r['cancelled'], 'int'], [$r['payments'], 'int'],
                [$r['received'], 'money'], [$r['refunded'], 'money'], [$r['net_received'], 'money'],
                ...($hasP ? [$r['previous'] === null ? null : [$r['previous'], 'money'], $change($r['revenue'], $r['previous'])] : []),
            ]);
        }
        $t = $staff['total'];
        $xlsx->row([
            ['Total', 'total'], ['', 'total'], [$t['bookings'], 'total_int'], [$t['guests'], 'total_int'], [$t['revenue'], 'total_money'],
            [$t['collected'], 'total_money'], [$t['outstanding'], 'total_money'], $totalPct($t['collection_rate']), [$t['cancelled'], 'total_int'],
            [$t['payments'], 'total_int'], [$t['received'], 'total_money'], [$t['refunded'], 'total_money'], [$t['net_received'], 'total_money'],
            ...($hasP ? [[$t['previous'], 'total_money'], ['', 'total']] : []),
        ], 22);

        // Agent types
        $tiers = $data['tiers'];
        $xlsx->sheet('Agent types', [24, 10, 10, 15, 15, 12, 15], table: true)
            ->row(['Agent type', 'Agents', 'Bookings', 'Public fare', 'Agent discount', 'Discount %', 'Net revenue'], 32);
        foreach ($tiers as $r) {
            $xlsx->row([$r['name'], [$r['agents'], 'int'], [$r['bookings'], 'int'], [$r['public_fare'], 'money'], [$r['agent_discount'], 'money'],
                $pct($r['discount_rate']), [$r['revenue'], 'money']]);
        }
        $tierFare = $tiers->sum('public_fare');
        $xlsx->row([['Total', 'total'], [$tiers->sum('agents'), 'total_int'], [$tiers->sum('bookings'), 'total_int'], [$tierFare, 'total_money'],
            [$tiers->sum('agent_discount'), 'total_money'], $totalPct($tierFare > 0 ? $tiers->sum('agent_discount') / $tierFare * 100 : null),
            [$tiers->sum('revenue'), 'total_money']], 22);

        // Agents
        $agents = $data['agents'];
        $columns = [['Agent', 26], ['Contact', 20], ['Agent type', 14], ['Bookings', 10], ['Guests', 9], ['Public fare', 14], ['Agent discount', 14],
            ['Discount %', 10], ['Net revenue', 14], ['Paid', 14], ['Still to collect', 14], ['Last sale', 13]];
        if ($hasP) {
            array_push($columns, ['Revenue, comparison', 14], ['Change', 10]);
        }
        $xlsx->sheet('Agents', array_column($columns, 1), table: true)->row(array_column($columns, 0), 32);
        foreach ($agents as $r) {
            $xlsx->row([
                $r['name'], $r['contact'], $r['tier'], [$r['bookings'], 'int'], [$r['guests'], 'int'], [$r['public_fare'], 'money'],
                [$r['agent_discount'], 'money'], $pct($r['discount_rate']), [$r['revenue'], 'money'], [$r['collected'], 'money'],
                [$r['outstanding'], 'money'], $r['last_sale'] ? [$r['last_sale'], 'date'] : null,
                ...($hasP ? [[$r['previous'], 'money'], $change($r['revenue'], $r['previous'])] : []),
            ]);
        }
        $agentFare = $agents->sum('public_fare');
        $xlsx->row([
            ['Total', 'total'], ['', 'total'], ['', 'total'], [$agents->sum('bookings'), 'total_int'], [$agents->sum('guests'), 'total_int'],
            [$agentFare, 'total_money'], [$agents->sum('agent_discount'), 'total_money'],
            $totalPct($agentFare > 0 ? $agents->sum('agent_discount') / $agentFare * 100 : null), [$agents->sum('revenue'), 'total_money'],
            [$agents->sum('collected'), 'total_money'], [$agents->sum('outstanding'), 'total_money'], ['', 'total'],
            ...($hasP ? [[$agents->sum('previous'), 'total_money'], ['', 'total']] : []),
        ], 22);

        // Promo codes and offers
        foreach (['Promo codes' => ['Code', $data['promos']], 'Offers' => ['Offer', $data['offers']]] as $sheet => [$first, $rows]) {
            $xlsx->sheet($sheet, [24, 40, 11, 10, 14, 14, 14, 11, 13, 11, 9], table: true)
                ->row([$first, 'Details', 'Status', 'Bookings', 'Discount given', 'Net revenue', 'Average booking', 'Discount share', 'Revenue per $1 off', 'Used, all time', 'Limit'], 32);
            foreach ($rows as $r) {
                $xlsx->row([
                    $r['name'], $r['detail'], $r['status'], [$r['bookings'], 'int'], [$r['discount'], 'money'], [$r['revenue'], 'money'],
                    [$r['average'], 'money'], $pct($r['share']), $r['per_dollar'] === null ? ['—', 'muted'] : [$r['per_dollar'], 'money'],
                    $r['used_count'] === null ? null : [$r['used_count'], 'int'], $r['max_uses'] === null ? ($r['used_count'] === null ? null : ['No limit', 'muted']) : [$r['max_uses'], 'int'],
                ]);
            }
            $discount = (float) $rows->sum('discount');
            $revenue = (float) $rows->sum('revenue');
            $xlsx->row([
                [$sheet === 'Offers' ? 'Total (a booking with two offers counts under each)' : 'Total', 'total'], ['', 'total'], ['', 'total'],
                [$rows->sum('bookings'), 'total_int'], [$discount, 'total_money'], [$revenue, 'total_money'], ['', 'total'],
                $totalPct($revenue + $discount > 0 ? $discount / ($revenue + $discount) * 100 : null),
                $discount > 0 ? [$revenue / $discount, 'total_money'] : ['', 'total'], ['', 'total'], ['', 'total'],
            ], 22);
        }

        return $xlsx;
    }
}
