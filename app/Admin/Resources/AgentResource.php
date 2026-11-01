<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\AgentTier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Agents: website accounts with the role "agent", made by approving an agent request (Admin\AgentRequestController).
 * They see the agent rates (AgentRateResource). Account type "Customer" takes the rates away (they move to Customers).
 */
class AgentResource extends UserResource
{
    public static string $slug = 'agents';

    public static string $label = 'Agents';

    public static string $singular = 'Agent';

    public static array $search = ['name', 'company_name', 'email', 'phone'];

    public static function query(): Builder
    {
        // Their agent bookings, and the balance of the ones not cancelled (Admin\AgentController::show has the details)
        $live = fn (Builder $q) => $q->where('status', '!=', BookingStatus::Cancelled);

        return User::query()->agents()->with('agentTier')
            ->withCount(['agentBookings as bookings_count'])
            ->withSum(['agentBookings as sales_total' => $live], 'grand_total')
            ->withSum(['agentBookings as commission_total' => $live], 'agent_discount_total')
            ->latest('id');
    }

    public static function scope(): Builder
    {
        return User::query()->agents();
    }

    /** ?tier= : the agents of one type (Agent types → Agents). */
    public static function filter(Builder $query, Request $request): Builder
    {
        return $query->when($request->integer('tier'), fn (Builder $q, int $tier) => $q->where('agent_tier_id', $tier));
    }

    public static function fields(): array
    {
        return [
            Field::text('company_name', 'Company')->rules(['nullable', 'string', 'max:255'])->index(),
            Field::select('agent_tier_id', 'Agent type', ['' => 'No type'] + AgentTier::options())
                ->rules(['nullable', Rule::exists('agent_tiers', 'id')])
                ->help('The agent gets the rates of this type first (Agents → Agent rates), then the rates for all agent types.')->index(),
            ...static::personFields(),
            Field::text('full_phone', 'Phone')->indexOnly(),
            Field::number('bookings_count', 'Bookings')->indexOnly(),
            // Not counting cancelled bookings
            Field::money('sales_total', 'Sales (net)')->indexOnly(),
            Field::money('commission_total', 'Commission')->indexOnly(),
            Field::select('role', 'Account type', static::roleOptions([UserRole::Agent, UserRole::Customer]))->default(UserRole::Agent->value)
                ->rules(['required', Rule::enum(UserRole::class)->only([UserRole::Agent, UserRole::Customer])])
                ->help('Customer takes the agent rates away: the account moves to Customers and sees the public prices.'),
            static::passwordField(),
        ];
    }

    public static function fill(Model $record, Request $request): void
    {
        parent::fill($record, $request);
        $record->setAttribute('company_name', $request->input('company_name'));
        // Not mass assignable: only set here and on approval
        $record->setAttribute('agent_tier_id', $request->input('agent_tier_id') ?: null);
    }

    /** The agent's statement (bookings, commission, paid and due), and their bookings in Admin → Bookings. */
    public static function rowActions(Model $record): array
    {
        return ['View' => route('admin.agents.show', $record->id)]
            + ($record->bookings_count ? ["Bookings ({$record->bookings_count})" => route('admin.bookings.index', ['channel' => 'agent', 'agent' => $record->id])] : []);
    }

    public static function canDelete(Model $record): bool
    {
        // Accounts with bookings are kept, so their booking history stays linked to them
        return ! $record->bookings()->exists() && ! $record->agentBookings()->exists();
    }
}
