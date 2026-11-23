<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Services\Assistant\AgentLoop;
use App\Services\Assistant\Assistant;
use App\Services\Assistant\BookingDraft;
use App\Services\Assistant\ImageInput;
use App\Services\Assistant\Tickets;
use App\Services\BookingService;
use App\Services\CurrencyService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Website chat assistant (public/assets/js/assistant.js). The conversation is found by a token kept in the visitor's
 * session, never one sent by the browser, so nobody can continue or read someone else's chat.
 */
class AssistantController extends Controller
{
    private const SESSION_KEY = 'assistant.conversation';

    public function message(Request $request, AgentLoop $agent): JsonResponse
    {
        abort_unless(Assistant::available(), 404);

        $data = $request->validate([
            'message' => ['required_without:image', 'nullable', 'string', 'max:'.config('assistant.max_message_length')],
            // A photo as a data URL (the browser shrinks it): chosen accounts only, never saved (ImageInput)
            'image' => ['nullable', 'string', 'max:'.(int) ceil(config('assistant.max_image_bytes') * 4 / 3 + 64)],
            'new' => ['sometimes', 'boolean'],
        ]);
        $image = null;
        if (filled($data['image'] ?? null)) {
            abort_unless(Assistant::imagesAllowed(), 403);
            $image = ImageInput::fromDataUrl($data['image']);
            if (! $image) {
                return response()->json(['reply' => __('assistant.image_invalid'), 'error' => true, 'chat' => self::chatId($request)], 422);
            }
        }

        $user = $request->user('web');
        $conversation = $request->boolean('new') ? null : $this->current($request, $user?->id);
        if (! $conversation) {
            $conversation = AssistantConversation::start($user, app()->getLocale(), $request->ip());
            $request->session()->put(self::SESSION_KEY, $conversation->token);
        }

        if ($conversation->user_messages >= Assistant::limit('per_chat')) {
            return response()->json(['reply' => __('assistant.limit'), 'limit' => true, 'chat' => self::chatId($request)]);
        }
        if ($image && ImageInput::countIn($conversation->messages ?? []) >= (int) config('assistant.max_images_per_chat')) {
            return response()->json(['reply' => __('assistant.image_limit'), 'error' => true, 'chat' => self::chatId($request)]);
        }

        // One answer can take several calls to Claude (tool rounds, retries): more than PHP's usual 30 seconds
        @set_time_limit(max(180, (int) config('assistant.timeout') * 2));

        try {
            $text = trim((string) ($data['message'] ?? ''));
            $reply = $agent->reply($conversation, $text !== '' ? $text : __('assistant.image_default'), $user, $image);
        } catch (Throwable $e) {
            Log::error('Assistant reply failed', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);

            return response()->json(['reply' => __('assistant.error'), 'error' => true, 'chat' => self::chatId($request)], 503);
        }

        return response()->json(['reply' => $reply['text'], 'ui' => $reply['ui'], 'chat' => self::chatId($request)]);
    }

    /**
     * The "Confirm booking" button under a summary from the prepare_booking tool: the visitor's own click books, with
     * the details checked again by the booking page's rules and saved by BookingService (emails, agent rate, guest
     * account), exactly like the booking page. The chat keeps a note of it, so the assistant knows what happened.
     */
    public function book(Request $request, BookingService $bookings, CurrencyService $currency): JsonResponse
    {
        abort_unless(Assistant::available(), 404);
        $data = $request->validate(['id' => ['required', 'string', 'max:40']]);

        $user = $request->user('web');
        $conversation = $this->current($request, $user?->id);
        $payload = $conversation ? BookingDraft::pull($conversation, $data['id'], $user) : null;
        if (! $payload) {
            return response()->json(['reply' => __('assistant.booking.expired'), 'error' => true, 'chat' => self::chatId($request)], 422);
        }

        try {
            $checked = BookingDraft::validate($payload, $user);
            $booking = $bookings->confirm(
                cabins: $checked->cabins,
                contact: ['email' => $checked->input('contact.email'), 'dial' => $checked->input('contact.dial'), 'phone' => $checked->normalisedPhone()],
                promo: $checked->promo,
                user: $user,
            );
        } catch (ValidationException $e) {
            $reply = __('assistant.booking.failed', ['error' => $e->validator->errors()->first()]);
            $this->note($conversation, 'The visitor pressed "Confirm booking" but the booking was refused: '.$e->validator->errors()->first(), $reply);

            return response()->json(['reply' => $reply, 'error' => true, 'chat' => self::chatId($request)], 422);
        } catch (Throwable $e) {
            Log::error('Assistant booking failed', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);

            return response()->json(['reply' => __('assistant.error'), 'error' => true, 'chat' => self::chatId($request)], 503);
        }

        $request->session()->push(BookingController::VIEWABLE, $booking->reference);
        $url = route('booking.confirmation', $booking);
        $code = $booking->display_currency ?: $currency->active();
        $total = $currency->format($booking->grand_total, $code, $booking->exchange_rate ?: $currency->rate($code));
        $reply = __('assistant.booking.done', ['reference' => $booking->reference, 'total' => $total, 'hours' => config('cruise.payment_contact_hours'), 'url' => $url]);
        $this->note($conversation, "The visitor pressed \"Confirm booking\": booked, reference {$booking->reference}, total {$total} (unpaid; the team arranges payment).", $reply);

        return response()->json(['reply' => $reply, 'reference' => $booking->reference, 'url' => $url, 'chat' => self::chatId($request)], 201);
    }

    /** Adds the button's outcome to the chat history (a note from the visitor's side, then the reply shown). */
    private function note(AssistantConversation $conversation, string $note, string $reply): void
    {
        $messages = $conversation->messages ?? [];
        $at = now()->toIso8601String();
        $last = $messages === [] ? null : array_key_last($messages);
        if ($last !== null && $messages[$last]['role'] === 'user') {
            // After the tool results of prepare_booking (the turn ended there) or a message with no answer
            $content = $messages[$last]['content'];
            $messages[$last]['content'] = is_array($content)
                ? [...$content, ['type' => 'text', 'text' => "[{$note}]", 'at' => $at]]
                : $content."\n[{$note}]";
        } else {
            $messages[] = ['role' => 'user', 'content' => "[{$note}]", 'at' => $at];
        }
        $messages[] = ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => $reply]], 'at' => $at];
        $conversation->forceFill(['messages' => $messages])->save();
    }

    /**
     * Names the visitor's current chat for the widget, which keeps the messages on screen in the browser
     * (localStorage) across page loads and shows them only while this is the same chat of the same account. Not the
     * token: knowing it gives nothing. '' when there is no chat.
     */
    public static function chatId(Request $request): string
    {
        $token = $request->session()->get(self::SESSION_KEY);

        return $token ? substr(hash_hmac('sha256', $token.'|'.$request->user('web')?->id, (string) config('app.key')), 0, 20) : '';
    }

    /** Starts a new chat (the widget's "new chat" button). */
    public function reset(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['ok' => true]);
    }

    /**
     * The visitor's chat, while it belongs to the same account (signing in or out starts a new one, so a shared
     * browser never shows one customer's booking answers to the next).
     */
    private function current(Request $request, ?int $userId): ?AssistantConversation
    {
        $token = $request->session()->get(self::SESSION_KEY);
        $conversation = $token ? AssistantConversation::where('token', $token)->first() : null;
        // A conversation from before the running tickets package stays with the old package: a new one starts
        $package = Tickets::current();
        if ($conversation && $package && $conversation->created_at->lessThan($package->starts_at)) {
            return null;
        }

        return $conversation && $conversation->user_id === $userId ? $conversation : null;
    }
}
