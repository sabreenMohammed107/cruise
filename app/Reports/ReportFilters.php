<?php

namespace App\Reports;

use App\Models\Booking;
use App\Models\Cruise;
use App\Models\Sailing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The filters every report shares (reports-plan.pdf, section 3.5): a period with a comparison period, channel,
 * cruise, sailing, seller and agent. Read from the query string, so a filtered report is a link that can be shared.
 *
 * Who sees what: admins and supervisors see every booking (but the super admin's test accounts' ones); a seller
 * sees only the bookings assigned to them, whatever "seller" says (same as the dashboard).
 */
final class ReportFilters
{
    public const PRESETS = [
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
        'last_month' => 'Last month',
        'quarter' => 'This quarter',
        'year' => 'This year',
        'custom' => 'Custom',
    ];

    public const COMPARE = ['previous' => 'Previous period', 'year' => 'Same period last year', 'none' => 'No comparison'];

    public function __construct(
        public readonly User $viewer,
        public readonly string $preset,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $compare = 'previous',
        public readonly ?string $channel = null,
        public readonly ?int $cruise = null,
        public readonly ?int $sailing = null,
        public readonly ?string $seller = null,
        public readonly ?int $agent = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $v = $request->validate([
            'preset' => ['nullable', Rule::in(array_keys(self::PRESETS))],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom', 'after_or_equal:from'],
            'compare' => ['nullable', Rule::in(array_keys(self::COMPARE))],
            'channel' => ['nullable', Rule::in(['b2c', 'agent'])],
            'cruise' => ['nullable', 'integer'],
            'sailing' => ['nullable', 'integer'],
            // A staff member's id, or "none" for unassigned bookings
            'seller' => ['nullable', 'regex:/^(none|\d+)$/'],
            'agent' => ['nullable', 'integer'],
        ]);
        $viewer = $request->user();

        // The filter form always sends the preset with its dates: dates changed by hand make it a custom range
        $preset = $v['preset'] ?? 'month';
        if (isset($v['from'], $v['to']) && $preset !== 'custom') {
            [$f, $t] = self::range($preset);
            if ($f->toDateString() !== $v['from'] || $t->toDateString() !== $v['to']) {
                $preset = 'custom';
            }
        }
        if ($preset === 'custom' && ! isset($v['from'], $v['to'])) {
            $preset = 'month';
        }
        [$from, $to] = self::range($preset, $v['from'] ?? null, $v['to'] ?? null);

        return new self(
            viewer: $viewer,
            preset: $preset,
            from: $from,
            to: $to,
            compare: $v['compare'] ?? 'previous',
            channel: $v['channel'] ?? null,
            cruise: isset($v['cruise']) ? (int) $v['cruise'] : null,
            sailing: isset($v['sailing']) ? (int) $v['sailing'] : null,
            // A seller's reports are always their own bookings
            seller: $viewer->isSeller() ? (string) $viewer->id : ($v['seller'] ?? null),
            agent: isset($v['agent']) ? (int) $v['agent'] : null,
        );
    }

    /** @return array{CarbonImmutable, CarbonImmutable} start of the first day, end of the last day (app timezone) */
    public static function range(string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = CarbonImmutable::today();

        [$start, $end] = match ($preset) {
            'today' => [$today, $today],
            'week' => [$today->startOfWeek(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$today->firstOfQuarter(), $today],
            'year' => [$today->startOfYear(), $today],
            'custom' => [CarbonImmutable::parse($from), CarbonImmutable::parse($to)],
            default => [$today->startOfMonth(), $today],
        };

        return [$start->startOfDay(), $end->endOfDay()];
    }

    /**
     * The period to compare with, or null. "Previous": the same span one step earlier (this month to date against
     * the same days of last month, last month against the month before; a custom range against the same number of
     * days just before it). "Year": the same dates a year earlier.
     *
     * @return array{CarbonImmutable, CarbonImmutable}|null
     */
    public function comparison(): ?array
    {
        if ($this->compare === 'none') {
            return null;
        }
        if ($this->compare === 'year') {
            return [$this->from->subYearNoOverflow(), $this->to->subYearNoOverflow()];
        }

        return match ($this->preset) {
            'today' => [$this->from->subDay(), $this->to->subDay()],
            'week' => [$this->from->subWeek(), $this->to->subWeek()],
            'month' => [$this->from->subMonthNoOverflow(), $this->to->subMonthNoOverflow()],
            'last_month' => [$this->from->subMonthNoOverflow(), $this->from->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$this->from->subMonthsNoOverflow(3), $this->to->subMonthsNoOverflow(3)],
            'year' => [$this->from->subYearNoOverflow(), $this->to->subYearNoOverflow()],
            default => [$this->from->subDays($this->days()), $this->from->subDay()->endOfDay()],
        };
    }

    /** Number of calendar days in the period. */
    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * Bookings the viewer may see, with every filter but the period applied.
     *
     * @return Builder<Booking>
     */
    public function bookings(): Builder
    {
        return Booking::query()
            ->visibleTo($this->viewer)
            ->when($this->channel, fn (Builder $q, string $channel) => $q->channel($channel))
            ->when($this->sailing, fn (Builder $q, int $id) => $q->where('sailing_id', $id))
            ->when($this->cruise, fn (Builder $q, int $id) => $q->whereHas('sailing', fn (Builder $s) => $s->where('cruise_id', $id)))
            ->when($this->seller, fn (Builder $q, string $seller) => $seller === 'none' ? $q->whereNull('seller_id') : $q->where('seller_id', (int) $seller))
            ->when($this->agent, fn (Builder $q, int $id) => $q->where('agent_id', $id));
    }

    /**
     * Bookings sold (confirmed) in a period: the current one by default.
     *
     * @return Builder<Booking>
     */
    public function sold(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Builder
    {
        return $this->bookings()->whereBetween('confirmed_at', [$from ?? $this->from, $to ?? $this->to]);
    }

    /** The filters as query-string values, for links (export, pagination) that keep them. */
    public function query(): array
    {
        return array_filter([
            'preset' => $this->preset,
            'from' => $this->preset === 'custom' ? $this->from->toDateString() : null,
            'to' => $this->preset === 'custom' ? $this->to->toDateString() : null,
            'compare' => $this->compare !== 'previous' ? $this->compare : null,
            'channel' => $this->channel,
            'cruise' => $this->cruise,
            'sailing' => $this->sailing,
            'seller' => $this->viewer->isSeller() ? null : $this->seller,
            'agent' => $this->agent,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * The filters in use, in words, for the top of an export: ["Channel: Agents (B2B)", "Cruise: Red Sea Discovery"].
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $name = function (int $id) {
            $user = User::find($id);

            return $user ? ($user->company_name ?: $user->name) : "#$id";
        };
        $sailing = $this->sailing ? Sailing::with('cruise')->find($this->sailing) : null;

        return array_values(array_filter([
            $this->channel ? 'Channel: '.($this->channel === 'agent' ? 'Agents (B2B)' : 'Website (B2C)') : null,
            $this->cruise ? 'Cruise: '.(Cruise::withTrashed()->find($this->cruise)?->name ?? "#{$this->cruise}") : null,
            $this->sailing ? 'Sailing: '.($sailing ? $sailing->departure_date?->format('j M Y').' · '.($sailing->cruise?->name ?? $sailing->code) : "#{$this->sailing}") : null,
            $this->viewer->isSeller() ? 'Assigned to: '.$this->viewer->name
                : ($this->seller ? 'Assigned to: '.($this->seller === 'none' ? 'Unassigned' : $name((int) $this->seller)) : null),
            $this->agent ? 'Agent: '.$name($this->agent) : null,
        ]));
    }

    /** "1 Nov – 24 Nov 2026" */
    public static function label(CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('j M Y');
        }

        return $from->format($from->year === $to->year ? 'j M' : 'j M Y').' – '.$to->format('j M Y');
    }
}
