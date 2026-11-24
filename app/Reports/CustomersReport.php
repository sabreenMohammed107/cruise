<?php

namespace App\Reports;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingTraveller;
use App\Models\User;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Report 11 (reports-plan.pdf): who buys. The customers of the bookings sold in the period (confirmed_at, cancelled
 * bookings left out): new or returning, their nationalities, the party they travel with (adults / children /
 * infants), the currency and language they booked in, and the customers who spent the most.
 *
 * A customer is the booking's account (bookings.user_id), or its contact email for a booking without one. "New":
 * their first booking ever (any channel, cancelled ones too) is in the period; "returning": they had booked before.
 * "All time" figures (bookings, spend, booked more than once) count confirmed bookings only.
 * Nationalities are the travellers' own (booking_travellers.nationality), so a family of four counts four times.
 */
class CustomersReport extends Report
{
    public static function key(): string
    {
        return 'customers';
    }

    public static function title(): string
    {
        return 'Customers';
    }

    public static function description(): string
    {
        return 'New and returning customers, nationalities, the party they travel with, currency and language, and the customers who spent the most.';
    }

    public static function icon(): string
    {
        return 'user';
    }

    public function view(): string
    {
        return 'admin.reports.customers';
    }

    public function data(ReportFilters $filters, Request $request): array
    {
        $comparison = $filters->comparison();
        $current = $this->buyers($filters, $filters->from, $filters->to);
        $previous = $comparison ? $this->buyers($filters, ...$comparison) : null;

        return [
            'current' => $current['totals'],
            'previous' => $previous['totals'] ?? null,
            'comparison' => $comparison,
            'nationalities' => $this->nationalities($filters),
            'parties' => $this->parties($current['bookings']),
            'currencies' => $this->split($current['bookings'], 'display_currency'),
            'languages' => $this->split($current['bookings'], 'locale'),
            'top' => $current['customers']->take(20)->values(),
        ];
    }

    /**
     * The confirmed bookings of a period, their customers (best spenders first) and the figures at the top.
     *
     * @return array{bookings: Collection, customers: Collection, totals: array}
     */
    public function buyers(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $bookings = $filters->sold($from, $to)->where('status', BookingStatus::Confirmed)
            ->with('user:id,name,email,nationality,created_at')
            ->orderBy('confirmed_at')->orderBy('id')
            ->get(['id', 'user_id', 'contact_email', 'grand_total', 'adults', 'children', 'infants', 'confirmed_at', 'display_currency', 'locale']);
        $key = fn (Booking $b) => $b->user_id ? 'u'.$b->user_id : 'e'.mb_strtolower(trim((string) $b->contact_email));

        // Every customer's whole history, whatever the filters: their first booking (a cancelled one too), and their
        // confirmed bookings and spend
        $users = $bookings->pluck('user_id')->filter()->unique()->values();
        $emails = $bookings->whereNull('user_id')->map(fn ($b) => mb_strtolower(trim((string) $b->contact_email)))->filter()->unique()->values();
        $history = collect();
        $lifetime = fn ($q) => $q->visibleTo($filters->viewer)->whereNotNull('confirmed_at')->toBase()->selectRaw(
            'MIN(confirmed_at) as first, COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as bookings, COALESCE(SUM(CASE WHEN status = ? THEN grand_total ELSE 0 END), 0) as spent',
            [BookingStatus::Confirmed->value, BookingStatus::Confirmed->value]
        );
        if ($users->isNotEmpty()) {
            $lifetime(Booking::query()->whereIn('user_id', $users->all()))->addSelect('user_id')->groupBy('user_id')->get()
                ->each(fn ($r) => $history->put('u'.$r->user_id, $r));
        }
        if ($emails->isNotEmpty()) {
            $lifetime(Booking::query()->whereNull('user_id')->whereIn('contact_email', $emails->all()))->addSelect('contact_email')->groupBy('contact_email')->get()
                ->each(fn ($r) => $history->put('e'.mb_strtolower($r->contact_email), $r));
        }

        $customers = $bookings->groupBy($key)->map(function (Collection $group, string $k) use ($history, $from) {
            /** @var Booking $first */
            $first = $group->first();
            $h = $history[$k] ?? null;
            $firstEver = $h?->first ? CarbonImmutable::parse($h->first) : $first->confirmed_at->toImmutable();

            return [
                'key' => $k,
                'user_id' => $first->user_id,
                'name' => $first->user?->name,
                'email' => $first->user?->email ?? $first->contact_email,
                'nationality' => $first->user?->nationality,
                'bookings' => $group->count(),
                'guests' => (int) $group->sum(fn ($b) => $b->adults + $b->children + $b->infants),
                'revenue' => (float) $group->sum('grand_total'),
                'lifetime_bookings' => (int) ($h->bookings ?? $group->count()),
                'lifetime_spent' => (float) ($h->spent ?? $group->sum('grand_total')),
                'first_booking' => $firstEver,
                'new' => $firstEver->greaterThanOrEqualTo($from),
            ];
        })->sortBy([['revenue', 'desc'], ['bookings', 'desc']])->values();

        $adults = (int) $bookings->sum('adults');
        $children = (int) $bookings->sum('children');
        $infants = (int) $bookings->sum('infants');
        $revenue = (float) $bookings->sum('grand_total');
        $new = $customers->where('new', true);

        return [
            'bookings' => $bookings,
            'customers' => $customers,
            'totals' => [
                'customers' => $customers->count(),
                'new' => $new->count(),
                'returning' => $customers->count() - $new->count(),
                'repeat' => $customers->where('lifetime_bookings', '>', 1)->count(),
                'bookings' => $bookings->count(),
                'revenue' => $revenue,
                'new_revenue' => (float) $new->sum('revenue'),
                'per_customer' => $customers->count() ? $revenue / $customers->count() : 0.0,
                'adults' => $adults,
                'children' => $children,
                'infants' => $infants,
                'guests' => $adults + $children + $infants,
                'guests_per_booking' => $bookings->count() ? ($adults + $children + $infants) / $bookings->count() : 0.0,
                // Website sign-ups in the period (accounts, whatever the other filters)
                'accounts' => User::customers()->visibleTo($filters->viewer)->whereBetween('created_at', [$from, $to])->count(),
            ],
        ];
    }

    /** Travellers of the period's bookings by nationality, most first. */
    private function nationalities(ReportFilters $filters): Collection
    {
        $ids = $filters->sold()->where('status', BookingStatus::Confirmed)->select('id');
        $rows = BookingTraveller::query()->whereIn('booking_id', $ids)->toBase()
            ->selectRaw("COALESCE(NULLIF(nationality, ''), 'Not given') as nationality, COUNT(*) as travellers, COUNT(DISTINCT booking_id) as bookings")
            ->groupBy('nationality')->orderByDesc('travellers')->get();
        $total = max(1, (int) $rows->sum('travellers'));

        return $rows->map(fn ($r) => [
            'name' => $r->nationality,
            'travellers' => (int) $r->travellers,
            'bookings' => (int) $r->bookings,
            'share' => (int) $r->travellers / $total * 100,
        ]);
    }

    /** Bookings by who travels together. */
    private function parties(Collection $bookings): Collection
    {
        $kind = fn (Booking $b) => match (true) {
            $b->children + $b->infants > 0 => 'Families (with children or infants)',
            $b->adults === 1 => 'Solo travellers',
            $b->adults === 2 => 'Couples and pairs (2 adults)',
            default => 'Groups (3 adults or more)',
        };
        $total = max(1, $bookings->count());

        return collect(['Solo travellers', 'Couples and pairs (2 adults)', 'Families (with children or infants)', 'Groups (3 adults or more)'])
            ->map(function (string $label) use ($bookings, $kind, $total) {
                $group = $bookings->filter(fn ($b) => $kind($b) === $label);

                return [
                    'name' => $label,
                    'bookings' => $group->count(),
                    'guests' => (int) $group->sum(fn ($b) => $b->adults + $b->children + $b->infants),
                    'revenue' => (float) $group->sum('grand_total'),
                    'average' => $group->count() ? (float) $group->sum('grand_total') / $group->count() : 0.0,
                    'share' => $group->count() / $total * 100,
                ];
            });
    }

    /** Bookings and revenue by one column (currency, language). */
    private function split(Collection $bookings, string $column): Collection
    {
        $total = max(1, $bookings->count());
        $names = ['en' => 'English', 'ar' => 'Arabic'];

        return $bookings->groupBy(fn ($b) => (string) ($b->{$column} ?: '—'))->map(fn (Collection $g, string $value) => [
            'name' => $column === 'locale' ? ($names[$value] ?? strtoupper($value)) : $value,
            'bookings' => $g->count(),
            'revenue' => (float) $g->sum('grand_total'),
            'share' => $g->count() / $total * 100,
        ])->sortByDesc('bookings')->values();
    }

    /** Sheets: Summary (with nationalities, parties, currency, language) and Customers (everyone who bought). */
    public function workbook(ReportFilters $filters): Xlsx
    {
        $data = $this->data($filters, request());
        $c = $data['current'];
        $p = $data['previous'];
        $customers = $this->buyers($filters, $filters->from, $filters->to)['customers'];

        $xlsx = (new Xlsx)->sheet('Summary', [40, 16, 16, 14, 14]);
        $this->heading($xlsx, $filters);
        self::keyFigures($xlsx, $c, $p, [
            ['Customers who bought', 'customers', 'int'],
            ['   New (first booking ever)', 'new', 'int'],
            ['   Returning (booked before)', 'returning', 'int'],
            ['Customers with more than one booking, all time', 'repeat', 'int'],
            ['Bookings (cancelled not included)', 'bookings', 'int'],
            ['Net revenue', 'revenue', 'money'],
            ['   From new customers', 'new_revenue', 'money'],
            ['Revenue per customer', 'per_customer', 'money'],
            ['Guests', 'guests', 'int'],
            ['   Adults', 'adults', 'int'],
            ['   Children', 'children', 'int'],
            ['   Infants', 'infants', 'int'],
            ['Guests per booking', 'guests_per_booking', 'number'],
            ['Accounts opened on the website', 'accounts', 'int'],
        ]);

        self::table($xlsx, 'Travellers by nationality', ['Nationality', 'Travellers', 'Bookings', 'Share'],
            $data['nationalities']->map(fn ($r) => [$r['name'], [$r['travellers'], 'int'], [$r['bookings'], 'int'], [$r['share'] / 100, 'percent']]));
        self::table($xlsx, 'Who travels together', ['Party', 'Bookings', 'Guests', 'Net revenue', 'Average booking'],
            $data['parties']->map(fn ($r) => [$r['name'], [$r['bookings'], 'int'], [$r['guests'], 'int'], [$r['revenue'], 'money'], [$r['average'], 'money']]));
        self::table($xlsx, 'Currency the guest booked in', ['Currency', 'Bookings', 'Net revenue (USD)', 'Share'],
            $data['currencies']->map(fn ($r) => [$r['name'], [$r['bookings'], 'int'], [$r['revenue'], 'money'], [$r['share'] / 100, 'percent']]));
        self::table($xlsx, 'Language of the booking', ['Language', 'Bookings', 'Net revenue', 'Share'],
            $data['languages']->map(fn ($r) => [$r['name'], [$r['bookings'], 'int'], [$r['revenue'], 'money'], [$r['share'] / 100, 'percent']]));

        $xlsx->sheet('Customers', [26, 30, 16, 11, 10, 9, 14, 12, 14, 14], table: true)
            ->row(['Customer', 'Email', 'Nationality', 'New?', 'Bookings', 'Guests', 'Net revenue', 'Bookings, all time', 'Spent, all time', 'First booking'], 32);
        foreach ($customers as $r) {
            $xlsx->row([$r['name'] ?? 'Guest (no account)', $r['email'], $r['nationality'], $r['new'] ? 'New' : 'Returning', [$r['bookings'], 'int'], [$r['guests'], 'int'],
                [$r['revenue'], 'money'], [$r['lifetime_bookings'], 'int'], [$r['lifetime_spent'], 'money'], [$r['first_booking'], 'date']]);
        }
        $xlsx->row([['Total: '.$customers->count().' customers', 'total'], ['', 'total'], ['', 'total'], ['', 'total'], [$customers->sum('bookings'), 'total_int'],
            [$customers->sum('guests'), 'total_int'], [$customers->sum('revenue'), 'total_money'], ['', 'total'], ['', 'total'], ['', 'total']], 22);

        return $xlsx;
    }
}
