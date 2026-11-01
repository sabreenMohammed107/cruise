<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\View\View;

/**
 * An agent's statement (Admin → Agents → View): every booking they made at agent rates with its original price,
 * commission, net total, paid and due, and the totals. Cancelled bookings are listed but left out of the totals.
 * The commission is taken off the price at booking time (nothing is paid out to the agent), so "due" is what the agent
 * still owes.
 * The list and the edit form are the generic screens (Admin\Resources\AgentResource).
 */
class AgentController extends Controller
{
    public function show(User $agent): View
    {
        // Agents, and former agents who still have agent bookings
        abort_unless($agent->isAgent() || $agent->agentBookings()->exists(), 404);

        $bookings = $agent->agentBookings()->with(['sailing.cruise', 'cabinCategory', 'cabins.cabinCategory', 'agentTier'])->latest()->get();
        $live = $bookings->where('status', '!=', BookingStatus::Cancelled);
        $sum = fn ($rows, callable $amount) => (float) $rows->sum($amount);

        return view('admin.agents.show', [
            'agent' => $agent->load('agentTier'),
            'bookings' => $bookings,
            'totals' => [
                'count' => $live->count(),
                'cancelled' => $bookings->count() - $live->count(),
                'original' => $sum($live, fn (Booking $b) => $b->fare_total + $b->agent_discount_total),
                'commission' => $sum($live, fn (Booking $b) => $b->agent_discount_total),
                'promo' => $sum($live, fn (Booking $b) => $b->discount_total),
                'net' => $sum($live, fn (Booking $b) => $b->grand_total),
                'paid' => $sum($live, fn (Booking $b) => $b->amount_paid),
                'due' => $sum($live, fn (Booking $b) => $b->amountDue()),
            ],
            // The promo column only when a booking used a code (cancelled ones too, as they are listed)
            'hasPromo' => $bookings->contains(fn (Booking $b) => (float) $b->discount_total > 0),
        ]);
    }
}
