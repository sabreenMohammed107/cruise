<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantPackage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The website chat's conversations as listed in the panel (Admin → Assistant chats and Admin → Assistant tickets):
 * the filters, the list, and the counts. Chats with at least one visitor message.
 */
final class ChatList
{
    /** @return array{q: string, who: ?string, lang: ?string, period: ?string} */
    public static function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'who' => in_array($request->query('who'), ['signed_in', 'guests'], true) ? $request->query('who') : null,
            'lang' => in_array($request->query('lang'), ['en', 'ar'], true) ? $request->query('lang') : null,
            'period' => in_array($request->query('period'), ['today', '7', '30'], true) ? $request->query('period') : null,
        ];
    }

    /** @param  ?AssistantPackage  $package  only the conversations started during this package (Admin → Assistant tickets) */
    public static function paginate(array $filters, ?AssistantPackage $package = null): LengthAwarePaginator
    {
        return AssistantConversation::query()
            ->with('user')
            ->when($package, fn ($q) => $q->where('created_at', '>=', $package->starts_at)->where('created_at', '<', $package->ends_at))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $q->where(fn (Builder $w) => $w
                    ->when(ctype_digit($filters['q']), fn ($x) => $x->orWhere('id', (int) $filters['q']))
                    ->orWhere('ip', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->when($filters['who'] === 'signed_in', fn ($q) => $q->whereNotNull('user_id'))
            ->when($filters['who'] === 'guests', fn ($q) => $q->whereNull('user_id'))
            ->when($filters['lang'], fn ($q) => $q->where('locale', $filters['lang']))
            ->when($filters['period'] === 'today', fn ($q) => $q->where('created_at', '>=', today()))
            ->when(in_array($filters['period'], ['7', '30'], true), fn ($q) => $q->where('created_at', '>=', today()->subDays((int) $filters['period'] - 1)))
            ->where('user_messages', '>', 0)
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();
    }

    /** Chats today, chats and messages this month, and this month's tokens (for the cost, on the super admin's page). */
    public static function stats(): array
    {
        $month = AssistantConversation::where('user_messages', '>', 0)->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('count(*) as chats, coalesce(sum(user_messages), 0) as messages, coalesce(sum(input_tokens), 0) as input, coalesce(sum(output_tokens), 0) as output')
            ->first();

        return [
            'today' => AssistantConversation::where('user_messages', '>', 0)->where('created_at', '>=', today())->count(),
            'chats' => (int) $month->chats,
            'messages' => (int) $month->messages,
            'input' => (int) $month->input,
            'output' => (int) $month->output,
        ];
    }
}
