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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Departures of a cruise and their price grid: per cabin category, the tiered occupancy
 * prices (single, double, 3rd guest) for the whole cruise. Infants are always free.
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
        $cruise->load('ship.cabinCategories');

        // Start from the prices of the cruise's latest sailing, so a new date only needs the changes
        return view('admin.sailings.form', ['cruise' => $cruise, 'sailing' => null, 'prices' => $this->pricing->latestPrices($cruise)]);
    }

    public function store(Request $request, Cruise $cruise): RedirectResponse
    {
        $data = $this->validated($request, $cruise, null);

        $sailing = DB::transaction(function () use ($cruise, $data) {
            $sailing = $cruise->sailings()->create($this->attributes($cruise, $data));
            $this->savePrices($sailing, $data['prices']);

            return $sailing;
        });

        return redirect()->route('admin.sailings.edit', $sailing)->with('status', "Sailing {$sailing->code} created.");
    }

    public function edit(Sailing $sailing): View
    {
        $sailing->load('cruise.ship.cabinCategories');
        $prices = $sailing->cabinPrices()->get()->keyBy('cabin_category_id');

        return view('admin.sailings.form', ['cruise' => $sailing->cruise, 'sailing' => $sailing, 'prices' => $prices]);
    }

    public function update(Request $request, Sailing $sailing): RedirectResponse
    {
        $cruise = $sailing->cruise;
        $data = $this->validated($request, $cruise, $sailing);

        DB::transaction(function () use ($sailing, $cruise, $data) {
            $sailing->update($this->attributes($cruise, $data));
            $this->savePrices($sailing, $data['prices']);
        });

        return back()->with('status', "Sailing {$sailing->code} saved.");
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

    private function validated(Request $request, Cruise $cruise, ?Sailing $sailing): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $cabinIds = $cruise->ship->cabinCategories()->pluck('id')->all();

        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('sailings', 'code')->ignore($sailing?->id)],
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(SailingStatus::class)],
            'prices' => ['required', 'array'],
            // keys must be cabin categories of this cruise's ship
            'prices.*' => ['array', fn ($attribute, $value, $fail) => in_array((int) Str::after($attribute, 'prices.'), $cabinIds, true) || $fail('Unknown cabin category.')],
            'prices.*.single' => ['required', 'numeric', 'min:0'],
            'prices.*.double' => ['required', 'numeric', 'min:0'],
            'prices.*.extra' => ['required', 'numeric', 'min:0'],
            'prices.*.capacity' => ['nullable', 'integer', 'min:0'],
            'prices.*.sold_out' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Use capital letters, numbers and dashes, e.g. RSD-261115.',
            'prices.required' => 'Enter the prices of every cabin.',
            'prices.*.*.required' => 'Enter a price (0 or more).',
        ]);
    }

    private function attributes(Cruise $cruise, array $data): array
    {
        $departure = Carbon::parse($data['departure_date']);

        return [
            'code' => $data['code'],
            'departure_date' => $departure,
            'return_date' => $departure->copy()->addDays($cruise->nights),
            'status' => $data['status'],
        ];
    }

    /** @param  array<int, array{single: string, double: string, extra: string, capacity?: ?string, sold_out?: ?string}>  $rows */
    private function savePrices(Sailing $sailing, array $rows): void
    {
        foreach ($rows as $cabinId => $row) {
            $sailing->cabinPrices()->updateOrCreate(['cabin_category_id' => $cabinId], [
                'price_single' => $row['single'],
                'price_double' => $row['double'],
                'price_extra' => $row['extra'],
                'capacity' => $row['capacity'] ?? null,
                'is_sold_out' => (bool) ($row['sold_out'] ?? false),
            ]);
        }
    }
}
