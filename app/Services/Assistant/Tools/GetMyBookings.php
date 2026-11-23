<?php

namespace App\Services\Assistant\Tools;

use App\Models\Booking;
use App\Models\User;

/**
 * The signed-in customer's own bookings (account dashboard). The customer comes from the website session (guard
 * "web"), never from Claude's input, so one customer can't read another's bookings whatever the chat says.
 */
class GetMyBookings extends Tool
{
    public function name(): string
    {
        return 'get_my_bookings';
    }

    public function description(): string
    {
        return 'The signed-in customer\'s own bookings: reference, cruise, sailing date, cabins, guests, booking status, '
            .'payment status, total, paid and amount due, and the voucher link. Only the customer who is chatting.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'which' => ['type' => 'string', 'enum' => ['upcoming', 'past', 'cancelled', 'all'], 'description' => 'Default: upcoming.'],
            ],
        ];
    }

    public function needsUser(): bool
    {
        return true;
    }

    public function handle(array $input, ?User $user): array
    {
        if (! $user) {
            return ['error' => 'The visitor is not signed in. Ask them to sign in at '.route('login').' to see their bookings.'];
        }

        $which = in_array($input['which'] ?? null, ['upcoming', 'past', 'cancelled', 'all'], true) ? $input['which'] : 'upcoming';

        $bookings = Booking::where('user_id', $user->id)
            ->when($which !== 'all', fn ($q) => $q->{$which}())
            ->with(['sailing.cruise', 'cabins.cabinCategory', 'cabinCategory'])
            ->latest('confirmed_at')
            ->limit($this->limit())
            ->get()
            ->map(fn (Booking $b) => [
                'reference' => $b->reference,
                'cruise' => $b->sailing?->cruise?->tr('name'),
                'departure_date' => $this->date($b->sailing?->departure_date),
                'cabins' => $b->cabinSummary(),
                'guests' => $b->guestCounts(),
                'status' => $b->status->value,
                'payment_status' => $b->payment_status->value,
                'total' => $b->money($b->grand_total),
                'paid' => $b->money($b->amount_paid),
                'amount_due' => $b->money($b->amountDue()),
                'voucher_url' => route('booking.confirmation', $b),
            ])
            ->all();

        return ['which' => $which, 'count' => count($bookings), 'bookings' => $bookings];
    }
}
