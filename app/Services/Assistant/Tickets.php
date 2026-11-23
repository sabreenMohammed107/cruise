<?php

namespace App\Services\Assistant;

use App\Models\AssistantPackage;

/**
 * The website chat's tickets (Admin → Assistant tickets): the super admin sells the chat as packages of tickets for a
 * USD amount, valid one month. Each visitor message uses tickets in proportion to what it cost (Assistant::cost()),
 * and the chat stops when the package is used up or over, until it is renewed. Nothing to do with the Anthropic
 * Console balance.
 *
 * Until the first package is created, the chat runs without tickets (as before this feature).
 */
final class Tickets
{
    public static function current(): ?AssistantPackage
    {
        return AssistantPackage::current()->first();
    }

    /** The latest package, whatever its state: what the page shows when none is running. */
    public static function latest(): ?AssistantPackage
    {
        return AssistantPackage::latest('starts_at')->latest('id')->first();
    }

    /** Tickets are in use once a package was created. */
    public static function enforced(): bool
    {
        return AssistantPackage::exists();
    }

    /** The chat may answer: no packages yet, or a running package with tickets left. */
    public static function allows(): bool
    {
        if (! self::enforced()) {
            return true;
        }
        $package = self::current();

        return $package !== null && ! $package->isUsedUp();
    }

    /**
     * Takes one message's tickets from the running package. Returns the tickets used, or null when nothing was
     * charged (no running package, or no prices set for the model, config assistant.price_*).
     */
    public static function charge(int $inputTokens, int $outputTokens): ?float
    {
        $package = self::current();
        if (! $package) {
            return null;
        }
        $usd = Assistant::cost($inputTokens, $outputTokens);
        $tickets = $usd === null ? 0.0 : round($package->ticketsFor($usd), 4);
        // The message is counted in the package either way; its tickets only when the model's prices are set
        AssistantPackage::whereKey($package->id)->incrementEach(['messages' => 1] + ($tickets > 0 ? ['tickets_used' => $tickets] : []));

        return $usd === null ? null : $tickets;
    }
}
