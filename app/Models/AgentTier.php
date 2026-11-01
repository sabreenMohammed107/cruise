<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An agent type (Admin → Agents → Agent types), e.g. Standard, Gold or one agency's special contract.
 * Its agent rates come before the rates for every type (App\Services\AgentPricing).
 */
class AgentTier extends Model
{
    protected $fillable = ['name', 'note', 'sort_order'];

    public function agents(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(AgentRate::class);
    }

    /** @return array<int, string> id => name, in order, for selects */
    public static function options(): array
    {
        return static::orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }
}
