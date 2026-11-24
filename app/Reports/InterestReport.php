<?php

namespace App\Reports;

use App\Enums\AgentRequestStatus;
use App\Enums\BookingStatus;
use App\Models\AgentApplication;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Cruise;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Report 14 (reports-plan.pdf): interest that hasn't become a booking yet. Favourites per cruise, contact
 * messages, newsletter sign-ups and agent applications made in the period (each by its own date), how many of these
 * people booked, and the list of potential customers: everyone among them who has never booked (confirmed), with
 * how they showed interest, to follow up.
 *
 * "Booked" matches by email: the booking's contact email or its account's email (confirmed bookings, any time).
 * Only the cruise filter applies (favourites); contact messages and the newsletter belong to admins and
 * supervisors, so the report does too.
 */
class InterestReport extends Report
{
    public static function key(): string
    {
        return 'interest';
    }

    public static function title(): string
    {
        return 'Interest & potential customers';
    }

    public static function description(): string
    {
        return 'Favourites per cruise, contact messages, newsletter sign-ups and agent applications, how many became bookings, and who to follow up.';
    }

    public static function icon(): string
    {
        return 'heart';
    }

    public static function ability(): ?string
    {
        return 'manage-content';
    }

    public static function filters(): array
    {
        return ['cruise'];
    }

    public function view(): string
    {
        return 'admin.reports.interest';
    }

    public function data(ReportFilters $filters, Request $request): array
    {
        $comparison = $filters->comparison();
        $now = $this->collect($filters, $filters->from, $filters->to);

        return [
            'current' => $now['totals'],
            'previous' => $comparison ? $this->collect($filters, ...$comparison)['totals'] : null,
            'comparison' => $comparison,
            'cruises' => $this->cruises($filters),
            'sources' => $now['sources'],
            'applications' => $now['applications'],
            'leads' => $now['leads'],
        ];
    }

    /**
     * Everyone who showed interest in a period, by source, whether they booked, and the ones still to win.
     *
     * @return array{totals: array, sources: Collection, applications: Collection, leads: Collection}
     */
    public function collect(ReportFilters $filters, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $viewer = $filters->viewer;
        $between = [$from, $to];

        // One entry per email: [email, name, phone, source, detail, at]
        $entries = collect();
        DB::table('favorites')->join('users', 'users.id', '=', 'favorites.user_id')->join('cruises', 'cruises.id', '=', 'favorites.cruise_id')
            ->whereBetween('favorites.created_at', $between)
            ->when($filters->cruise, fn ($q, $id) => $q->where('favorites.cruise_id', $id))
            ->whereIn('favorites.user_id', User::query()->visibleTo($viewer)->select('id'))
            ->get(['users.email', 'users.name', 'users.phone_dial', 'users.phone', 'cruises.name as cruise', 'favorites.created_at'])
            ->each(fn ($f) => $entries->push(['email' => $f->email, 'name' => $f->name, 'phone' => trim($f->phone_dial.' '.$f->phone) ?: null,
                'source' => 'Favourite', 'detail' => $f->cruise, 'at' => CarbonImmutable::parse($f->created_at)]));
        $messages = $filters->cruise ? collect() : ContactMessage::whereBetween('created_at', $between)->get();
        $messages->each(fn ($m) => $entries->push(['email' => $m->email, 'name' => $m->name, 'phone' => trim(($m->phone_dial ?? '').' '.($m->phone ?? '')) ?: null,
            'source' => 'Contact message', 'detail' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', $m->message)), 0, 90, '…'), 'at' => $m->created_at->toImmutable()]));
        $subscribers = $filters->cruise ? collect() : NewsletterSubscriber::whereBetween(DB::raw('COALESCE(subscribed_at, created_at)'), $between)->get();
        $subscribers->each(fn ($s) => $entries->push(['email' => $s->email, 'name' => null, 'phone' => null,
            'source' => 'Newsletter', 'detail' => $s->unsubscribed_at ? 'Unsubscribed since' : null, 'at' => ($s->subscribed_at ?? $s->created_at)->toImmutable()]));
        $applications = $filters->cruise ? collect() : AgentApplication::with('user')->whereBetween('created_at', $between)->orderByDesc('created_at')->get();
        $applications->each(fn ($a) => $entries->push(['email' => $a->email, 'name' => $a->contact_name.' · '.$a->company_name, 'phone' => $a->full_phone,
            'source' => 'Agent application', 'detail' => ucfirst($a->status->value), 'at' => $a->created_at->toImmutable(), 'rejected' => $a->status === AgentRequestStatus::Rejected]));

        $booked = $this->bookedSince($entries->pluck('email')->all(), $viewer);
        // Agents book as themselves: an application counts as converted once its agent account has a booking
        $agentBookings = Booking::query()->visibleTo($viewer)->where('status', BookingStatus::Confirmed)
            ->whereIn('agent_id', $applications->pluck('user_id')->filter()->all())->toBase()
            ->selectRaw('agent_id, COUNT(*) as n, COALESCE(SUM(grand_total), 0) as revenue')->groupBy('agent_id')->get()->keyBy('agent_id');

        $converted = fn (array $e) => ($first = $booked[mb_strtolower($e['email'])] ?? null) !== null && $first->greaterThanOrEqualTo($e['at']->startOfDay());
        $sources = collect(['Favourite', 'Contact message', 'Newsletter', 'Agent application'])->map(function (string $source) use ($entries, $booked, $converted) {
            $people = $entries->where('source', $source)->unique(fn ($e) => mb_strtolower($e['email']));

            return [
                'name' => $source,
                'entries' => $entries->where('source', $source)->count(),
                'people' => $people->count(),
                'had_booked' => $people->filter(fn ($e) => isset($booked[mb_strtolower($e['email'])]) && ! $converted($e))->count(),
                'converted' => $people->filter($converted)->count(),
                'not_booked' => $people->reject(fn ($e) => isset($booked[mb_strtolower($e['email'])]))->count(),
            ];
        });

        // Potential customers: never booked, newest first; one line per email with every way they showed interest
        $leads = $entries->reject(fn ($e) => isset($booked[mb_strtolower($e['email'])]) || ! empty($e['rejected']))
            ->groupBy(fn ($e) => mb_strtolower($e['email']))
            ->map(fn (Collection $g, string $email) => [
                'email' => $g->first()['email'],
                'name' => $g->pluck('name')->filter()->first(),
                'phone' => $g->pluck('phone')->filter()->first(),
                'sources' => $g->pluck('source')->unique()->values()->all(),
                'interests' => $g->where('source', 'Favourite')->pluck('detail')->unique()->values()->all(),
                'note' => $g->where('source', '!=', 'Favourite')->pluck('detail')->filter()->first(),
                'first' => $g->min('at'),
                'last' => $g->max('at'),
            ])
            ->sortByDesc(fn ($l) => $l['last']->timestamp)->values();

        $apps = $applications->map(fn (AgentApplication $a) => [
            'application' => $a,
            'bookings' => (int) ($agentBookings[$a->user_id]->n ?? 0),
            'revenue' => (float) ($agentBookings[$a->user_id]->revenue ?? 0),
        ]);

        return [
            'totals' => [
                'favourites' => $entries->where('source', 'Favourite')->count(),
                'messages' => $messages->count(),
                'unread' => $messages->where('is_read', false)->count(),
                'subscribers' => $subscribers->count(),
                'unsubscribed' => $filters->cruise ? 0 : NewsletterSubscriber::whereBetween('unsubscribed_at', $between)->count(),
                'applications' => $applications->count(),
                'agents_selling' => $apps->where('bookings', '>', 0)->count(),
                'people' => $entries->pluck('email')->map(fn ($e) => mb_strtolower($e))->unique()->count(),
                'converted' => $entries->filter($converted)->unique(fn ($e) => mb_strtolower($e['email']))->count(),
                'leads' => $leads->count(),
            ],
            'sources' => $sources,
            'applications' => $apps,
            'leads' => $leads,
        ];
    }

    /**
     * Emails (lower case) that have a confirmed booking, with the day of their first one.
     *
     * @return array<string, CarbonImmutable>
     */
    private function bookedSince(array $emails, User $viewer): array
    {
        $emails = array_values(array_unique(array_filter(array_map(fn ($e) => mb_strtolower(trim((string) $e)), $emails))));
        if (! $emails) {
            return [];
        }
        $first = [];
        foreach (array_chunk($emails, 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            Booking::query()->visibleTo($viewer)->where('status', BookingStatus::Confirmed)
                ->where(fn (Builder $q) => $q->whereRaw("LOWER(contact_email) IN ($marks)", $chunk)
                    ->orWhereHas('user', fn (Builder $u) => $u->whereRaw("LOWER(email) IN ($marks)", $chunk)))
                ->with('user:id,email')->get(['id', 'user_id', 'contact_email', 'confirmed_at'])
                ->each(function (Booking $b) use (&$first, $chunk) {
                    foreach (array_unique([mb_strtolower((string) $b->contact_email), mb_strtolower((string) $b->user?->email)]) as $email) {
                        if (in_array($email, $chunk, true) && $b->confirmed_at) {
                            $day = $b->confirmed_at->toImmutable()->startOfDay();
                            $first[$email] = isset($first[$email]) && $first[$email]->lessThan($day) ? $first[$email] : $day;
                        }
                    }
                });
        }

        return $first;
    }

    /**
     * Favourites per cruise, all time and in the period, and how many of the people who saved it booked that cruise.
     */
    private function cruises(ReportFilters $filters): Collection
    {
        $visible = User::query()->visibleTo($filters->viewer)->select('id');
        $rows = DB::table('favorites')->whereIn('user_id', $visible)
            ->when($filters->cruise, fn ($q, $id) => $q->where('cruise_id', $id))
            ->selectRaw('cruise_id, COUNT(*) as total, COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as added', [$filters->from, $filters->to])
            ->groupBy('cruise_id')->get();
        // Of the people who saved a cruise, those with a confirmed booking on one of its sailings
        $booked = DB::table('favorites')->whereIn('favorites.user_id', $visible)
            ->when($filters->cruise, fn ($q, $id) => $q->where('favorites.cruise_id', $id))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('bookings')->join('sailings', 'sailings.id', '=', 'bookings.sailing_id')
                ->whereColumn('bookings.user_id', 'favorites.user_id')->whereColumn('sailings.cruise_id', 'favorites.cruise_id')
                ->where('bookings.status', BookingStatus::Confirmed->value))
            ->selectRaw('favorites.cruise_id, COUNT(*) as n')->groupBy('favorites.cruise_id')->pluck('n', 'cruise_id');
        $names = Cruise::withTrashed()->whereKey($rows->pluck('cruise_id')->all())->pluck('name', 'id');

        return $rows->map(fn ($r) => [
            'name' => $names[$r->cruise_id] ?? 'Deleted cruise',
            'total' => (int) $r->total,
            'added' => (int) $r->added,
            'booked' => (int) ($booked[$r->cruise_id] ?? 0),
            'not_booked' => (int) $r->total - (int) ($booked[$r->cruise_id] ?? 0),
            'rate' => $r->total ? ($booked[$r->cruise_id] ?? 0) / $r->total * 100 : null,
        ])->sortByDesc('total')->values();
    }

    /** Sheets: Summary (key figures, sources, favourites per cruise), Potential customers, Agent applications. */
    public function workbook(ReportFilters $filters): Xlsx
    {
        $data = $this->data($filters, request());

        $xlsx = (new Xlsx)->sheet('Summary', [36, 14, 14, 16, 14, 14]);
        $this->heading($xlsx, $filters);
        self::keyFigures($xlsx, $data['current'], $data['previous'], [
            ['Favourites added', 'favourites', 'int'],
            ['Contact messages', 'messages', 'int'],
            ['   still unread', 'unread', 'int'],
            ['Newsletter sign-ups', 'subscribers', 'int'],
            ['Newsletter unsubscribes', 'unsubscribed', 'int'],
            ['Agent applications', 'applications', 'int'],
            ['   of them selling now', 'agents_selling', 'int'],
            ['People who showed interest', 'people', 'int'],
            ['   booked afterwards', 'converted', 'int'],
            ['   never booked (potential customers)', 'leads', 'int'],
        ]);
        self::table($xlsx, 'By source', ['Source', 'Entries', 'People', 'Booked afterwards', 'Had booked before', 'Never booked'],
            $data['sources']->map(fn ($r) => [$r['name'], [$r['entries'], 'int'], [$r['people'], 'int'], [$r['converted'], 'int'], [$r['had_booked'], 'int'], [$r['not_booked'], 'int']]));
        self::table($xlsx, 'Favourites per cruise', ['Cruise', 'Favourites, all time', 'Added in period', 'Booked that cruise', 'Not booked yet', 'Booked %'],
            $data['cruises']->map(fn ($r) => [$r['name'], [$r['total'], 'int'], [$r['added'], 'int'], [$r['booked'], 'int'], [$r['not_booked'], 'int'],
                $r['rate'] === null ? ['—', 'muted'] : [$r['rate'] / 100, 'percent']]));

        $xlsx->sheet('Potential customers', [26, 30, 18, 30, 34, 40, 14, 14], table: true)
            ->row(['Name', 'Email', 'Phone', 'How they showed interest', 'Cruises saved', 'Note', 'First', 'Last'], 32);
        foreach ($data['leads'] as $l) {
            $xlsx->row([$l['name'], $l['email'], $l['phone'], implode(', ', $l['sources']), implode(', ', $l['interests']) ?: null, $l['note'], [$l['first'], 'date'], [$l['last'], 'date']]);
        }
        $xlsx->row([['Total: '.$data['leads']->count().' potential customers', 'total'], ...array_fill(0, 7, ['', 'total'])], 22);

        $xlsx->sheet('Agent applications', [26, 22, 30, 18, 12, 14, 12, 14], table: true)
            ->row(['Company', 'Contact', 'Email', 'Phone', 'Status', 'Applied on', 'Bookings', 'Net revenue'], 32);
        foreach ($data['applications'] as $r) {
            $a = $r['application'];
            $xlsx->row([$a->company_name, $a->contact_name, $a->email, $a->full_phone, ucfirst($a->status->value), [$a->created_at, 'date'], [$r['bookings'], 'int'], [$r['revenue'], 'money']]);
        }
        $xlsx->row([['Total: '.$data['applications']->count().' applications', 'total'], ...array_fill(0, 5, ['', 'total']),
            [$data['applications']->sum('bookings'), 'total_int'], [$data['applications']->sum('revenue'), 'total_money']], 22);

        return $xlsx;
    }
}
