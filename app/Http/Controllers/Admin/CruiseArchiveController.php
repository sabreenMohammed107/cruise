<?php

namespace App\Http\Controllers\Admin;

use App\Admin\CruiseFilters;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Cruise;
use App\Models\Sailing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Cruises → Archive: sailings already back (return date before today), one year at a time so the
 * list stays quick however many years build up. Nothing is moved: a sailing shows here from the day after it
 * returns. Filters: year (always one; the latest by default), month, departure port, nights.
 */
class CruiseArchiveController extends Controller
{
    public function __invoke(Request $request): View
    {
        $years = CruiseFilters::years(Sailing::query()->finished());
        $filters = CruiseFilters::from($request, $years);
        $filters['year'] ??= $years[0] ?? null;

        $sailings = $filters['year'] === null
            ? Sailing::query()->whereRaw('1 = 0')
            : CruiseFilters::applyToSailings(Sailing::query()->finished(), $filters, $years);

        return view('admin.cruises.archive', [
            'sailings' => $sailings
                ->with('cruise:id,slug,name,departure_port,nights,deleted_at')
                ->withCount(['bookings as confirmed_bookings_count' => fn (Builder $q) => $q->where('status', BookingStatus::Confirmed)])
                ->orderByDesc('departure_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'years' => $years,
            // Cruises with every sailing over: gone from the cruises list, here to schedule again if needed
            'finishedCruises' => Cruise::finished()->count(),
        ]);
    }
}
