<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Options;
use App\Admin\Resource;
use App\Models\AgentRate;
use App\Models\AgentTier;
use App\Models\CabinCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Agent rates: the percent signed-in agents get off the cabin prices (App\Services\AgentPricing). One rate per
 * agent type × cruise × cabin; "All agent types" / "All cruises" / "All cabins" are the fallbacks. The agent's own
 * type comes first, and the most specific active rate wins. ?tier= shows one type's rates (Agent types → Rates).
 */
class AgentRateResource extends Resource
{
    public static string $model = AgentRate::class;

    public static string $slug = 'agent-rates';

    public static string $label = 'Agent rates';

    public static string $singular = 'Agent rate';

    public static string $title = 'label';

    public static function query(): Builder
    {
        // Each type's rates together, "All agent types" first; general before specific within a type
        return AgentRate::query()->with(['agentTier', 'cruise', 'cabinCategory'])
            ->orderByRaw('agent_tier_id is not null')->orderBy('agent_tier_id')
            ->orderByRaw('cruise_id is not null')->orderByRaw('cabin_category_id is not null')->orderBy('id');
    }

    public static function filter(Builder $query, Request $request): Builder
    {
        return $query->when($request->integer('tier'), fn (Builder $q, int $tier) => $q->where('agent_tier_id', $tier));
    }

    public static function fields(): array
    {
        return [
            Field::select('agent_tier_id', 'Agent type', ['' => 'All agent types'] + AgentTier::options())
                ->default(request()->integer('tier') ?: null)
                ->rules(['nullable', Rule::exists('agent_tiers', 'id')])
                ->help('An agent gets the rates of their type first; "All agent types" is for the rest.')->index(),
            Field::select('cruise_id', 'Cruise', ['' => 'All cruises'] + Options::cruises())
                ->rules(['nullable', Rule::exists('cruises', 'id')])->index(),
            Field::select('cabin_category_id', 'Cabin', ['' => 'All cabins'] + CabinCategory::orderBy('sort_order')->pluck('name_en', 'id')->all())
                ->rules(['nullable', Rule::exists('cabin_categories', 'id')])->index(),
            // The one-per-combination check sits here: empty type / cruise / cabin skip their own rules (nullable)
            Field::number('percent', 'Agent discount (%)', '0.01')
                ->rules(fn (?Model $record) => ['required', 'numeric', 'min:0', 'max:100', function ($attribute, $value, $fail) use ($record) {
                    if (static::taken($record)) {
                        $fail('There is already a rate for this agent type, cruise and cabin. Edit that one instead.');
                    }
                }])
                ->help('Taken off the single, double and 3rd-guest prices (0 = no agent discount). Within a type the most specific rate wins: this cruise and cabin, then this cruise, then this cabin, then All cruises · All cabins. A cruise\'s cabin rates can also be set in the "Agent %" column of its sailings.')
                ->index(),
            Field::checkbox('is_active', 'Active')->default(true)->index(),
        ];
    }

    /** Another rate for the type × cruise × cabin being saved (empty = "all"). */
    private static function taken(?Model $record): bool
    {
        $same = fn (Builder $q, string $column) => ($id = request($column)) ? $q->where($column, $id) : $q->whereNull($column);

        return AgentRate::query()
            ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
            ->tap(fn (Builder $q) => $same($q, 'agent_tier_id'))
            ->tap(fn (Builder $q) => $same($q, 'cruise_id'))
            ->tap(fn (Builder $q) => $same($q, 'cabin_category_id'))
            ->exists();
    }
}
