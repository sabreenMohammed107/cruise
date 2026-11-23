<?php

namespace App\Services\Assistant;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Whether the website chat runs, and its token cost.
 *
 * On when: an API key is set and ASSISTANT_ENABLED isn't false (config assistant.enabled), and an admin hasn't
 * switched it off in Admin → Assistant chats (site_settings "assistant_enabled" = "0").
 */
final class Assistant
{
    public const SETTING = 'assistant_enabled';

    /**
     * Who sees the chat (Admin → Assistant chats): "testers" (the super admin and the accounts in TESTERS only, while
     * it is being tried on the live site) or "everyone". Default: config assistant.audience (ASSISTANT_AUDIENCE).
     */
    public const AUDIENCE = 'assistant_audience';

    /** Website accounts (ids, comma separated) that see the chat in test mode, besides the super admin. */
    public const TESTERS = 'assistant_testers';

    /** Limits set in Admin → Assistant chats (site_settings): name => [setting key, label] */
    public const LIMITS = [
        'per_minute' => ['assistant_limit_minute', 'Messages a minute, per visitor'],
        'per_hour' => ['assistant_limit_hour', 'Messages an hour, per visitor'],
        'per_chat' => ['assistant_limit_chat', 'Messages in one chat'],
    ];

    /** A limit from Admin → Assistant chats, or its default when never saved. */
    public static function limit(string $name): int
    {
        $saved = SiteSetting::value(self::LIMITS[$name][0]);

        return ctype_digit($saved) && (int) $saved > 0 ? (int) $saved : self::defaultLimit($name);
    }

    public static function defaultLimit(string $name): int
    {
        return match ($name) {
            'per_minute' => 10,
            'per_hour' => 60,
            'per_chat' => (int) config('assistant.max_user_messages'),
        };
    }

    public static function configured(): bool
    {
        return (bool) config('assistant.enabled');
    }

    public static function switchedOn(): bool
    {
        return SiteSetting::value(self::SETTING) !== '0';
    }

    public static function enabled(): bool
    {
        return self::configured() && self::switchedOn();
    }

    /** "testers" or "everyone". */
    public static function audience(): string
    {
        $saved = SiteSetting::value(self::AUDIENCE);
        if (in_array($saved, ['testers', 'everyone'], true)) {
            return $saved;
        }

        return config('assistant.audience') === 'everyone' ? 'everyone' : 'testers';
    }

    /** @return list<int> the website accounts that see the chat in test mode (besides the super admin) */
    public static function testers(): array
    {
        $ids = array_filter(array_map('trim', explode(',', SiteSetting::value(self::TESTERS))), 'ctype_digit');

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * The chat is shown to, and answers, this visit: it is on, and it is for everyone, or (test mode) the visitor is
     * the super admin (signed in on the website or in the panel) or a chosen account signed in on the website.
     */
    public static function available(): bool
    {
        // Off, or the tickets package is used up or over (Admin → Assistant tickets)
        if (! self::enabled() || ! Tickets::allows()) {
            return false;
        }
        if (self::audience() === 'everyone') {
            return true;
        }
        $user = Auth::guard('web')->user();
        if ($user instanceof User && ($user->isSuperAdmin() || in_array($user->id, self::testers(), true))) {
            return true;
        }

        return (bool) Auth::guard('admin')->user()?->isSuperAdmin();
    }

    /**
     * Sending photos in the chat: only the super admin and the accounts chosen in Admin → Assistant chats (testers),
     * also when the chat is open to everyone.
     */
    public static function imagesAllowed(): bool
    {
        $user = Auth::guard('web')->user();
        if ($user instanceof User && ($user->isSuperAdmin() || in_array($user->id, self::testers(), true))) {
            return true;
        }

        return (bool) Auth::guard('admin')->user()?->isSuperAdmin();
    }

    /**
     * Estimated cost in USD from the prices in config/assistant.php (per million tokens), or null when they aren't
     * set. An estimate: cached prompt tokens are cheaper than this counts them.
     */
    public static function cost(int $inputTokens, int $outputTokens): ?float
    {
        $in = config('assistant.price_input_per_mtok');
        $out = config('assistant.price_output_per_mtok');
        if (! is_numeric($in) || ! is_numeric($out)) {
            return null;
        }

        return ($inputTokens * (float) $in + $outputTokens * (float) $out) / 1_000_000;
    }
}
