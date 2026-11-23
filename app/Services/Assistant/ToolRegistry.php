<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\Tools;
use Illuminate\Support\Facades\Log;
use Throwable;

/** The tools the assistant may call, and running one by name. */
class ToolRegistry
{
    /** @var list<class-string<Tools\Tool>> */
    public const TOOLS = [
        Tools\SearchCruises::class,
        Tools\GetCruiseDetails::class,
        Tools\QuotePrice::class,
        Tools\ListDestinations::class,
        Tools\GetShipInfo::class,
        Tools\GetPolicies::class,
        Tools\ContactTeam::class,
        Tools\OfferChoices::class,
        Tools\PrepareBooking::class,
        Tools\GetMyBookings::class,
        Tools\RunReport::class,
        Tools\ListReportTemplates::class,
    ];

    /** @return array<string, Tools\Tool> by name; tools for signed-in users only when one is chatting */
    public function for(?User $user): array
    {
        $tools = [];
        foreach (self::TOOLS as $class) {
            try {
                $tool = app($class);
                if ($tool->needsUser() && ! $user) {
                    continue;
                }
                $tools[$tool->name()] = $tool;
            } catch (Throwable $e) {
                // A broken tool is left out; the chat keeps working with the others
                Log::error('Assistant tool could not be loaded', ['tool' => $class, 'error' => $e->getMessage()]);
            }
        }

        return $tools;
    }

    /**
     * API tool definitions; the last one marks the end of the cached prompt prefix (prompt caching).
     *
     * @param  array<string, Tools\Tool>  $tools
     */
    public function definitions(array $tools): array
    {
        $defs = array_values(array_map(fn (Tools\Tool $t) => $t->definition(), $tools));
        if ($defs !== []) {
            $defs[array_key_last($defs)]['cache_control'] = ['type' => 'ephemeral'];
        }

        return $defs;
    }

    /**
     * Runs a tool. Unknown tools and failures come back as an error result Claude can explain, never as an exception.
     *
     * @param  array<string, Tools\Tool>  $tools
     * @return array{0: array, 1: bool, 2: ?array, 3: bool} the result for Claude, whether it is an error, what the
     *                                                       chat widget shows (or null), whether the turn ends here
     */
    public function run(array $tools, string $name, mixed $input, ?User $user, ?AssistantConversation $conversation = null): array
    {
        $tool = $tools[$name] ?? null;
        if (! $tool) {
            return [['error' => "Unknown tool: {$name}"], true, null, false];
        }

        try {
            $tool->conversation = $conversation;
            $result = $tool->handle(is_array($input) ? $input : [], $user);
            $ui = $result['_ui'] ?? null;
            unset($result['_ui']);
            $isError = isset($result['error']);

            return [$result, $isError, $ui, ! $isError && $tool->stopsTurn()];
        } catch (Throwable $e) {
            Log::warning('Assistant tool failed', ['tool' => $name, 'error' => $e->getMessage()]);

            return [['error' => 'The data could not be loaded right now. Offer the contact_team options.'], true, null, false];
        }
    }
}
