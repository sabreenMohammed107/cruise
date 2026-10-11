<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\SailingStatus;
use App\Http\Controllers\Controller;
use App\Models\Cruise;
use App\Models\Sailing;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Departures of a cruise and their price grid (one row per cabin category).
 */
class SailingController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    public function index(Cruise $cruise): View
    {
        $sailings = $cruise->sailings()
            ->with('cabinPrices.cabinCategory')
            ->withCount(['bookings as booked_count' => fn ($q) => $q->where('status', BookingStatus::Confirmed)])
            ->get();

        return view('admin.sailings.index', compact('cruise', 'sailings'));
    }

    public function create(Cruise $cruise): View
    {
        return view('admin.sailings.form', ['cruise' => $cruise, 'sailing' => null, 'prices' => collect()]);
    }

    public function store(Request $request, Cruise $cruise): RedirectResponse
    {
        $data = $this->validated($request, null);

        $sailing = DB::transaction(function () use ($cruise, $data) {
            $sailing = $cruise->sailings()->create($this->attributes($cruise, $data));
            $this->pricing->syncSailingPrices($sailing);

            return $sailing;
        });

        return redirect()->route('admin.sailings.edit', $sailing)
            ->with('status', "Sailing {$sailing->code} created with rate-card prices. Adjust them below if needed.");
    }

    public function edit(Sailing $sailing): View
    {
        $sailing->load('cruise.ship.cabinCategories');
        $prices = $sailing->cabinPrices()->get()->keyBy('cabin_category_id');

        return view('admin.sailings.form', ['cruise' => $sailing->cruise, 'sailing' => $sailing, 'prices' => $prices]);
    }

    public function update(Request $request, Sailing $sailing): RedirectResponse
    {
        $data = $this->validated($request, $sailing);
        $cruise = $sailing->cruise;

        DB::transaction(function () use ($sailing, $cruise, $data) {
            $sailing->update($this->attributes($cruise, $data));

            foreach ($data['prices'] ?? [] as $cabinId => $row) {
                $sailing->cabinPrices()->updateOrCreate(['cabin_category_id' => $cabinId], [
                    'price_adult' => $row['adult'],
                    'price_child' => $row['child'],
                    'price_infant' => $row['infant'],
                    'capacity' => $row['capacity'] ?? null,
                    'is_sold_out' => (bool) ($row['sold_out'] ?? false),
                ]);
            }
        });

        return back()->with('status', "Sailing {$sailing->code} saved.");
    }

    /**
     * Overwrite this sailing's prices with the rate card (cabin rates × nights × factors).
     */
    public function recalculate(Sailing $sailing): RedirectResponse
    {
        $this->pricing->syncSailingPrices($sailing);

        return back()->with('status', "Prices for {$sailing->code} recalculated from the rate card.");
    }

    public function destroy(Sailing $sailing): RedirectResponse
    {
        $cruise = $sailing->cruise;

        if ($sailing->bookings()->exists()) {
            return back()->with('error', "{$sailing->code} has bookings. Set its status to Cancelled or Closed instead.");
        }

        $sailing->delete();

        return redirect()->route('admin.cruises.sailings.index', $cruise)->with('status', "Sailing {$sailing->code} deleted.");
    }

    private function validated(Request $request, ?Sailing $sailing): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('sailings', 'code')->ignore($sailing?->id)],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'price_factor' => ['required', 'numeric', 'min:0.1', 'max:5'],
            'status' => ['required', Rule::enum(SailingStatus::class)],
            'prices' => ['nullable', 'array'],
            'prices.*.adult' => ['required', 'numeric', 'min:0'],
            'prices.*.child' => ['required', 'numeric', 'min:0'],
            'prices.*.infant' => ['required', 'numeric', 'min:0'],
            'prices.*.capacity' => ['nullable', 'integer', 'min:0'],
            'prices.*.sold_out' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Use capital letters, numbers and dashes, e.g. RSD-261115.',
        ]);
    }

    private function attributes(Cruise $cruise, array $data): array
    {
        $departure = Carbon::parse($data['departure_date']);

        return [
            'code' => $data['code'],
            'departure_date' => $departure,
            'return_date' => $departure->copy()->addDays($cruise->nights),
            'price_factor' => $data['price_factor'],
            'status' => $data['status'],
        ];
    }
}
