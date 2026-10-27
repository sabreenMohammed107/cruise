<?php

namespace App\Models;

use App\Enums\AgentRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * An application from the website's "Become an agent" page (App\Http\Controllers\AgentController).
 * Named "application" so it doesn't clash with the form request App\Http\Requests\AgentRequest.
 */
class AgentApplication extends Model
{
    protected $table = 'agent_requests';

    protected $fillable = ['company_name', 'contact_name', 'email', 'phone_dial', 'phone'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'status' => AgentRequestStatus::class,
        ];
    }

    /** "+966 501234567", or null without a number. */
    protected function fullPhone(): Attribute
    {
        return Attribute::get(fn () => $this->phone ? trim("{$this->phone_dial} {$this->phone}") : null);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AgentRequestStatus::Pending);
    }
}
