<?php

namespace App\Services;

use App\Models\AgentRate;
use App\Models\SailingCabinPrice;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Agent rates: signed-in agents (role "agent") pay the cabin prices less a percent set in Admin → Agents → Agent rates.
 * The agent's type (Standard, Gold, … users.agent_tier_id) comes first, then the rates for every type; within each,
 * the most specific active rate wins:
 *
 *   this cruise + this cabin  →  this cruise, any cabin  →  any cruise, this cabin  →  any cruise, any cabin
 *
 * So a type is a complete price list of its own ("Gold: 10% on everything"), and "All agent types" is the fallback.
 * No rate at all: 0 (public prices).
 *
 * Used by ScDataPresenter (the prices published to the page scripts) and BookingService (the saved totals), so what
 * an agent sees is what they are charged. Scoped to the request (AppServiceProvider), so rates load once.
 */
class AgentPricing
{
    /** @var Collection<int, AgentRate>|null */
    private ?Collection $rates = null;

    public function appliesTo(?User $user): bool
    {
        return (bool) $user?->isAgent();
    }

    /** Percent off for this cruise × cabin for an agent of this type (null: no type), 0 when no rate matches. */
    public function percent(int $cruiseId, int $cabinCategoryId, ?int $tierId = null): float
    {
        return $this->resolve($tierId, [[$cruiseId, $cabinCategoryId], [$cruiseId, null], [null, $cabinCategoryId], [null, null]]);
    }

    /**
     * What this cruise × cabin gets for this type without the type's own cruise × cabin rate (the sailing form shows
     * it as the placeholder of its "Agent %" field).
     */
    public function fallbackPercent(int $cruiseId, int $cabinCategoryId, ?int $tierId = null): float
    {
        $general = [[$cruiseId, $cabinCategoryId], [$cruiseId, null], [null, $cabinCategoryId], [null, null]];

        // For a type, every general rate still applies after its own; for "all types", the cruise × cabin pair is the field itself
        return $tierId
            ? ($this->find($tierId, array_slice($general, 1)) ?? $this->find(null, $general) ?? 0.0)
            : ($this->find(null, array_slice($general, 1)) ?? 0.0);
    }

    /**
     * A type's own fallback for this cruise × cabin, without its cruise × cabin rate and without the rates for every
     * type (null: the type has none, so "All agent types" applies). The sailing form combines it with the live
     * "All agent types" column.
     */
    public function typeFallback(int $cruiseId, int $cabinCategoryId, int $tierId): ?float
    {
        return $this->find($tierId, [[$cruiseId, null], [null, $cabinCategoryId], [null, null]]);
    }

    /** Percent for a price row of a loaded sailing (sailing.cruise_id is enough), for this agent's type. */
    public function percentFor(SailingCabinPrice $price, ?User $agent = null): float
    {
        return $this->percent((int) $price->sailing->cruise_id, (int) $price->cabin_category_id, $agent?->agent_tier_id ? (int) $agent->agent_tier_id : null);
    }

    /**
     * Cabin prices less the percent, to the cent. Same shape as SailingCabinPrice::tiers().
     *
     * @param  array{single: float, double: float, extra: float, infant?: float}  $tiers
     * @return array{single: float, double: float, extra: float, infant: float}
     */
    public static function discounted(array $tiers, float $percent): array
    {
        $off = fn (float $amount) => round($amount * (100 - $percent) / 100, 2);

        return ['single' => $off($tiers['single']), 'double' => $off($tiers['double']), 'extra' => $off($tiers['extra']), 'infant' => 0.0];
    }

    /** @param  list<array{0: ?int, 1: ?int}>  $levels  [cruise, cabin] pairs, most specific first */
    private function resolve(?int $tierId, array $levels): float
    {
        return ($tierId ? $this->find($tierId, $levels) : null) ?? $this->find(null, $levels) ?? 0.0;
    }

    /** The first level with an active rate for exactly this type (null: "All agent types"). */
    private function find(?int $tierId, array $levels): ?float
    {
        $this->rates ??= AgentRate::where('is_active', true)->get();
        // Some drivers return keys as strings
        $id = fn ($value) => $value === null ? null : (int) $value;

        foreach ($levels as [$cruise, $cabin]) {
            $rate = $this->rates->first(fn (AgentRate $r) => $id($r->agent_tier_id) === $tierId
                && $id($r->cruise_id) === $cruise && $id($r->cabin_category_id) === $cabin);
            if ($rate) {
                return (float) $rate->percent;
            }
        }

        return null;
    }
}
