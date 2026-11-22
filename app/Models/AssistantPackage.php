<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A website chat package: `tickets` for `price_usd`, from `starts_at` to `ends_at` (one month). Messages use tickets
 * in proportion to their cost (App\Services\Assistant\Tickets).
 */
class AssistantPackage extends Model
{
    protected $fillable = ['tickets', 'price_usd', 'starts_at', 'ends_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'tickets' => 'integer',
            'price_usd' => 'float',
            'tickets_used' => 'float',
            'messages' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The package running now (started, not ended); the latest one if they overlap. */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('starts_at', '<=', now())->where('ends_at', '>', now())->latest('starts_at')->latest('id');
    }

    /** Tickets one USD buys in this package. */
    public function ticketsPerUsd(): float
    {
        return $this->price_usd > 0 ? $this->tickets / $this->price_usd : 0.0;
    }

    public function ticketsFor(float $usd): float
    {
        return $usd * $this->ticketsPerUsd();
    }

    public function remaining(): float
    {
        return max(0.0, $this->tickets - $this->tickets_used);
    }

    public function isUsedUp(): bool
    {
        return $this->remaining() <= 0;
    }

    /** Conversations started during the package (with at least one visitor message), or only those started today. */
    public function conversationsCount(bool $today = false): int
    {
        return AssistantConversation::where('user_messages', '>', 0)
            ->where('created_at', '>=', $today ? max($this->starts_at, today()) : $this->starts_at)
            ->where('created_at', '<', $this->ends_at)
            ->count();
    }

    /**
     * Visitor messages sent during the package, counted from the conversations' transcripts: each typed message, and
     * each button answer added to a turn (what AssistantConversation::user_messages counts). For packages from before
     * `messages` was counted as they happen (migration 2026_11_16_000003).
     */
    public function countMessagesFromConversations(): int
    {
        $count = 0;
        $inPackage = fn ($at) => is_string($at) && ($time = Carbon::parse($at))->greaterThanOrEqualTo($this->starts_at) && $time->lessThan($this->ends_at);

        AssistantConversation::where('created_at', '<', $this->ends_at)->where('updated_at', '>=', $this->starts_at)
            ->select(['id', 'messages'])->lazyById()
            ->each(function (AssistantConversation $conversation) use (&$count, $inPackage) {
                foreach ($conversation->messages ?? [] as $message) {
                    if (($message['role'] ?? null) !== 'user') {
                        continue;
                    }
                    if (is_string($message['content'] ?? null)) {
                        $count += $inPackage($message['at'] ?? null) ? 1 : 0;
                    } elseif (is_array($message['content'] ?? null)) {
                        foreach ($message['content'] as $block) {
                            $count += ($block['type'] ?? null) === 'text' && $inPackage($block['at'] ?? null) ? 1 : 0;
                        }
                    }
                }
            });

        return $count;
    }

    /** "scheduled", "active", "used_up" or "expired". */
    public function status(): string
    {
        return match (true) {
            $this->starts_at->isFuture() => 'scheduled',
            ! $this->ends_at->isFuture() => 'expired',
            $this->isUsedUp() => 'used_up',
            default => 'active',
        };
    }
}
