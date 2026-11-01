<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\AgentTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Agent types: Standard, Gold, one agency's special contract… Each agent has one (Agents), and each type has its own
 * agent rates (Agent rates, "Agent type"). Deleting a type deletes its rates; its agents get the rates for every type.
 */
class AgentTierResource extends Resource
{
    public static string $model = AgentTier::class;

    public static string $slug = 'agent-tiers';

    public static string $label = 'Agent types';

    public static string $singular = 'Agent type';

    public static array $search = ['name', 'note'];

    public static function query(): Builder
    {
        return AgentTier::query()->withCount(['agents', 'rates'])->orderBy('sort_order')->orderBy('name');
    }

    public static function fields(): array
    {
        return [
            Field::text('name', 'Name')->rules(['required', 'string', 'max:100'])->help('e.g. Standard, Gold, or an agency\'s name for a special contract.')->index(),
            Field::text('note', 'Note')->rules(['nullable', 'string', 'max:100'])->help('Optional, for the team, e.g. "Special contract".')->index(),
            Field::number('sort_order', 'Order')->default(0)->rules(['nullable', 'integer', 'min:0']),
            Field::number('agents_count', 'Agents')->indexOnly(),
            Field::number('rates_count', 'Rates')->indexOnly(),
        ];
    }

    public static function rowActions(Model $record): array
    {
        return [
            'Rates' => route('admin.agent-rates.index', ['tier' => $record->id]),
            "Agents ({$record->agents_count})" => route('admin.agents.index', ['tier' => $record->id]),
        ];
    }
}
