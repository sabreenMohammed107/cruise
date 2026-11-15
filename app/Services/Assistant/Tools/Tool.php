<?php

namespace App\Services\Assistant\Tools;

use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\CurrencyService;
use Carbon\CarbonInterface;

/**
 * A read-only tool Claude can call. Claude sends the input (checked against inputSchema() by the API, and again here:
 * never trust it), Laravel runs handle() and sends the array back as JSON. Tools only return what the public website
 * already shows, plus the signed-in customer's own bookings (GetMyBookings): never other customers, payments or staff.
 */
abstract class Tool
{
    /** The chat this call belongs to (set by ToolRegistry::run()). */
    public ?AssistantConversation $conversation = null;

    abstract public function name(): string;

    /** What the tool does and when to use it: Claude picks tools by this text. */
    abstract public function description(): string;

    /** JSON schema of the input. */
    abstract public function inputSchema(): array;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    abstract public function handle(array $input, ?User $user): array;

    /** Only offered to signed-in customers (GetMyBookings). */
    public function needsUser(): bool
    {
        return false;
    }

    /**
     * The turn ends after this tool (OfferChoices): the visitor answers next, Claude isn't called again now.
     * A result's "_ui" key is never sent to Claude: it goes to the chat widget (buttons, a report table).
     */
    public function stopsTurn(): bool
    {
        return false;
    }

    /** @return array{name: string, description: string, input_schema: array} the API's tool definition */
    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'input_schema' => $this->inputSchema(),
        ];
    }

    protected function isArabic(): bool
    {
        return app()->getLocale() === 'ar';
    }

    /**
     * A price as the visitor sees it on the site (USD or EGP, the header switch) plus the USD amount, which is what is
     * booked and stored.
     *
     * @return array{usd: float, display: string}|null
     */
    protected function money(float|int|string|null $usd): ?array
    {
        if ($usd === null) {
            return null;
        }
        $currency = app(CurrencyService::class);
        $code = $currency->active();

        return ['usd' => round((float) $usd, 2), 'display' => $currency->format($usd, $code, $currency->rate($code))];
    }

    protected function date(?CarbonInterface $date): ?string
    {
        return $date?->toDateString();
    }

    protected function limit(): int
    {
        return (int) config('assistant.max_results');
    }

    /** A trimmed string input, or null when missing / empty. */
    protected function str(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, 100) : null;
    }

    protected function int(array $input, string $key, int $min = 0, int $max = 1000): ?int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? max($min, min($max, (int) $value)) : null;
    }
}
