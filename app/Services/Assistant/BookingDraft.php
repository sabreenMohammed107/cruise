<?php

namespace App\Services\Assistant;

use App\Http\Requests\ConfirmBookingRequest;
use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * A booking made in the chat (Tools\PrepareBooking → the "Confirm booking" button → AssistantController::book()).
 *
 * The details are checked by exactly the booking page's rules (ConfirmBookingRequest: sailing still bookable, cabin,
 * guests, age bands against birth dates, names, nationality, phone, promo code), then kept here for 30 minutes,
 * tied to the chat and the account. Only the visitor's own click on the button books: Claude can't.
 */
final class BookingDraft
{
    public const MINUTES = 30;

    /**
     * The booking page's checks on a payload shaped like its "Confirm" request.
     *
     * @throws ValidationException
     */
    public static function validate(array $payload, ?User $user): ConfirmBookingRequest
    {
        $request = ConfirmBookingRequest::create('/booking/confirm', 'POST', $payload);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        return $request;
    }

    public static function put(AssistantConversation $conversation, array $payload, ?User $user): string
    {
        $id = bin2hex(random_bytes(8));
        Cache::put(self::key($conversation), ['id' => $id, 'payload' => $payload, 'user_id' => $user?->id], now()->addMinutes(self::MINUTES));

        return $id;
    }

    /** The draft behind this button, taken out (a second click can't book twice), or null when gone or not theirs. */
    public static function pull(AssistantConversation $conversation, string $id, ?User $user): ?array
    {
        $ok = fn ($draft) => is_array($draft) && hash_equals((string) $draft['id'], $id) && $draft['user_id'] === $user?->id;
        // An older card's button leaves the newest draft alone; of two clicks on the same one, only one gets it
        if (! $ok(Cache::get(self::key($conversation)))) {
            return null;
        }
        $draft = Cache::pull(self::key($conversation));

        return $ok($draft) ? $draft['payload'] : null;
    }

    private static function key(AssistantConversation $conversation): string
    {
        return 'assistant-booking:'.$conversation->id;
    }
}
