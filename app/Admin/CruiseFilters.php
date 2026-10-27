<?php

namespace App\Admin;

use App\Models\Cruise;
use App\Models\Sailing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Filters of the admin's cruises list and archive (admin.cruises.filters): year, month, departure port and
 * nights, laid out like the bookings filters. The choices come from the data, read with MIN / MAX / DISTINCT
 * on indexed columns, so the bar stays quick however many years of sailings there are.
 */
final class CruiseFilters
{
    /**
     * Years that have sailings in $sailings, newest first (e.g. [2027, 2026]).
     *
     * @return list<int>
     */
    public static function years(Builder $sailings): array
    {
        $range = $sailings->toBase()->selectRaw('min(departure_date) as first, max(departure_date) as last')->first();
        if (! $range?->first) {
            return [];
        }

        return range((int) substr($range->last, 0, 4), (int) substr($range->first, 0, 4));
    }

    /** @return list<string> departure ports of the cruises, A–Z */
    public static function ports(): array
    {
        return Cruise::withTrashed()->distinct()->orderBy('departure_port')->pluck('departure_port')->all();
    }

    /** @return list<int> cruise lengths in nights, shortest first */
    public static function nights(): array
    {
        return Cruise::withTrashed()->distinct()->orderBy('nights')->pluck('nights')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<int, string> 1 => "January", … */
    public static function months(): array
    {
        return collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])->all();
    }

    /**
     * The filters in the request, checked against the choices offered (empty ones dropped).
     *
     * @param  list<int>  $years
     * @return array{year?: int, month?: int, port?: string, nights?: int}
     */
    public static function from(Request $request, array $years): array
    {
        $f = array_filter($request->validate([
            'year' => ['nullable', 'integer', Rule::in($years)],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'port' => ['nullable', 'string', Rule::in(self::ports())],
            'nights' => ['nullable', 'integer', Rule::in(self::nights())],
        ], ['*.in' => 'That choice is not available.']));

        return array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $f);
    }

    /**
     * Applies year / month / port / nights to a sailings query. Without a year, a month means that month
     * in every year offered.
     *
     * @param  list<int>  $years
     */
    public static function applyToSailings(Builder $sailings, array $filters, array $years): Builder
    {
        $inYears = isset($filters['year']) ? [$filters['year']] : $years;

        return $sailings
            ->when(isset($filters['year']) || isset($filters['month']), fn ($q) => $q->departingIn($inYears, $filters['month'] ?? null))
            ->ofCruises($filters['port'] ?? null, $filters['nights'] ?? null);
    }

    /** Years with sailings not back yet (the cruises list's year choices). */
    public static function currentYears(): array
    {
        return array_reverse(self::years(Sailing::query()->current()));
    }
}
