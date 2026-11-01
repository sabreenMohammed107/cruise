<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A percent off the cabin prices for agents (Admin → Agents → Agent rates). No agent type = every type, no cruise =
 * every cruise, no cabin = every cabin; App\Services\AgentPricing picks the rate that fits the agent best.
 */
class AgentRate extends Model
{
    protected $fillable = ['agent_tier_id', 'cruise_id', 'cabin_category_id', 'percent', 'is_active'];

    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function agentTier(): BelongsTo
    {
        return $this->belongsTo(AgentTier::class);
    }

    public function cruise(): BelongsTo
    {
        return $this->belongsTo(Cruise::class);
    }

    public function cabinCategory(): BelongsTo
    {
        return $this->belongsTo(CabinCategory::class);
    }

    /** "Gold · Nile Discovery · Suite", "All agent types · All cruises · All cabins": the record's name in the admin. */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => implode(' · ', [
            $this->agentTier?->name ?? 'All agent types',
            $this->cruise?->name ?? 'All cruises',
            $this->cabinCategory?->name_en ?? 'All cabins',
        ]));
    }
}
