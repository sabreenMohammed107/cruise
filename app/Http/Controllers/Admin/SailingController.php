<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\SailingStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentRate;
use App\Models\AgentTier;
use App\Models\Cruise;
use App\Models\Sailing;
use App\Services\AgentPricing;
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

    public function create(Request $request, Cruise $cruise): View
    {
        $cruise->load('ship.cabinCategories');

        // Start from the prices of the cruise's latest sailing, so a new date only needs the changes
        return view('admin.sailings.form', ['cruise' => $cruise, 'sailing' => null, 'prices' => $this->pricing->latestPrices($cruise)] + $this->agentRates($cruise, $request));
    }

    public function store(Request $request, Cruise $cruise): RedirectResponse
    {
        $data = $this->validated($request, $cruise, null);

        $sailing = DB::transaction(function () use ($cruise, $data) {
            $sailing = $cruise->sailings()->create($this->attributes($cruise, $data));
            $this->savePrices($sailing, $data['prices']);
            $this->saveAgentRates($cruise, $data['agent'] ?? []);

            return $sailing;
        });

        return redirect()->route('admin.sailings.edit', [$sailing, 'tier' => $this->tabParam($data)])->with('status', "Sailing {$sailing->code} created.");
    }

    public function edit(Request $request, Sailing $sailing): View
    {
        $sailing->load('cruise.ship.cabinCategories');
        $prices = $sailing->cabinPrices()->get()->keyBy('cabin_category_id');

        return view('admin.sailings.form', ['cruise' => $sailing->cruise, 'sailing' => $sailing, 'prices' => $prices] + $this->agentRates($sailing->cruise, $request));
    }

    public function update(Request $request, Sailing $sailing): RedirectResponse
    {
        $cruise = $sailing->cruise;
        $data = $this->validated($request, $cruise, $sailing);

        DB::transaction(function () use ($sailing, $cruise, $data) {
            $sailing->update($this->attributes($cruise, $data));
            $this->savePrices($sailing, $data['prices']);
            $this->saveAgentRates($cruise, $data['agent'] ?? []);
        });

        return redirect()->route('admin.sailings.edit', [$sailing, 'tier' => $this->tabParam($data)])->with('status', "Sailing {$sailing->code} saved.");
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
        // "Agent %" columns: agent[all][cabin] for every type, agent[{type id}][cabin] for each type
        $tierKeys = ['all', ...array_map('strval', array_keys(AgentTier::options()))];

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
            // Agent % per agent type and cabin; empty = the type's general rate. Same cabin keys as prices
            'agent_tab' => ['nullable', Rule::in($tierKeys)],
            'agent' => ['nullable', 'array:'.implode(',', $tierKeys)],
            'agent.*' => ['array'],
            'agent.*.*' => ['nullable', 'numeric', 'min:0', 'max:100', fn ($attribute, $value, $fail) => in_array((int) Str::afterLast($attribute, '.'), $cabinIds, true) || $fail('Unknown cabin category.')],
        ], [
            'agent.*.*.max' => 'An agent commission can be at most 100%.',
            'agent.*.*.min' => 'An agent commission can\'t be negative.',
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

    /** The tab to reopen after saving: a type id, or null for "All agent types". */
    private function tabParam(array $data): ?string
    {
        return ($data['agent_tab'] ?? 'all') === 'all' ? null : $data['agent_tab'];
    }

    /**
     * The "Agent %" columns of every agent type, all in the page, so the type tabs switch without a reload
     * (admin.sailings.form). Per type key ("all" or the type id):
     *   rates     cabin id => the type's own cruise × cabin rate (the field's value)
     *   fallback  cabin id => what the cabin gets without it: for "all" the rates for every type (0 if none); for a
     *             type its own wider rates, or null when it has none and the "all" column applies (read live)
     * ?tier= opens a type's tab.
     *
     * @return array{agentTabs: array<string, string>, agentTab: string, agentRates: array<string, array<int, float>>, agentFallback: array<string, array<int, ?float>>}
     */
    private function agentRates(Cruise $cruise, Request $request): array
    {
        $agents = app(AgentPricing::class);
        $cabins = $cruise->ship->cabinCategories;
        $tabs = ['all' => 'All'] + collect(AgentTier::options())->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all();
        $own = AgentRate::where('cruise_id', $cruise->id)->whereNotNull('cabin_category_id')->where('is_active', true)->get();

        $rates = $fallback = [];
        foreach (array_keys($tabs) as $key) {
            $tierId = $key === 'all' ? null : (int) $key;
            $rates[$key] = $own->filter(fn (AgentRate $r) => ($r->agent_tier_id === null ? null : (int) $r->agent_tier_id) === $tierId)
                ->mapWithKeys(fn (AgentRate $r) => [(int) $r->cabin_category_id => (float) $r->percent])->all();
            $fallback[$key] = $cabins->mapWithKeys(fn ($c) => [$c->id => $tierId
                ? $agents->typeFallback($cruise->id, $c->id, $tierId)
                : $agents->fallbackPercent($cruise->id, $c->id)])->all();
        }
        $tab = (string) $request->query('tier', 'all');

        return [
            'agentTabs' => $tabs,
            'agentTab' => array_key_exists($tab, $tabs) ? $tab : 'all',
            'agentRates' => $rates,
            'agentFallback' => $fallback,
        ];
    }

    /**
     * Saves the "Agent %" columns as type × cruise × cabin agent rates, so they apply to every sailing of the cruise.
     * An emptied field removes the cabin's own rate: it then gets the type's general one again (agentFallback).
     *
     * @param  array<string, array<int, ?string>>  $columns  "all" / type id => cabin id => percent
     */
    private function saveAgentRates(Cruise $cruise, array $columns): void
    {
        foreach ($columns as $key => $percents) {
            foreach ($percents as $cabinId => $percent) {
                $match = ['agent_tier_id' => $key === 'all' ? null : (int) $key, 'cruise_id' => $cruise->id, 'cabin_category_id' => $cabinId];
                if ($percent === null || $percent === '') {
                    AgentRate::where($match)->where('is_active', true)->delete();
                } else {
                    AgentRate::updateOrCreate($match, ['percent' => $percent, 'is_active' => true]);
                }
            }
        }
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
