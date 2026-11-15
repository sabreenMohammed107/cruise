<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One website chat with the assistant (App\Services\Assistant\AgentLoop). */
class AssistantConversation extends Model
{
    protected $fillable = ['token', 'user_id', 'locale', 'messages', 'user_messages', 'input_tokens', 'output_tokens', 'ip'];

    protected function casts(): array
    {
        return ['messages' => 'array'];
    }

    public static function start(?User $user, string $locale, ?string $ip): self
    {
        return self::create([
            'token' => Str::random(40),
            'user_id' => $user?->id,
            'locale' => $locale,
            'messages' => [],
            'ip' => $ip,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The visitor's first message, for the admin list. */
    public function firstQuestion(): ?string
    {
        return collect($this->transcript())->firstWhere('kind', 'user')['text'] ?? null;
    }

    /**
     * The chat for the admin screen, in order: visitor messages, assistant answers, and each tool call with its
     * result next to it.
     *
     * Each entry has "at" (ISO time) for messages saved since times were kept, otherwise null.
     *
     * @return list<array{kind: 'user'|'assistant', text: string, at: ?string}|array{kind: 'tool', name: string, input: array, result: mixed, error: bool, at: ?string}>
     */
    public function transcript(): array
    {
        $results = [];
        foreach ($this->messages ?? [] as $m) {
            if ($m['role'] === 'user' && is_array($m['content'])) {
                foreach ($m['content'] as $block) {
                    if (($block['type'] ?? null) === 'tool_result') {
                        $content = $block['content'] ?? null;
                        $decoded = is_string($content) ? json_decode($content, true) : null;
                        $results[$block['tool_use_id'] ?? ''] = ['result' => $decoded ?? $content, 'error' => (bool) ($block['is_error'] ?? false)];
                    }
                }
            }
        }

        $entries = [];
        foreach ($this->messages ?? [] as $m) {
            if ($m['role'] === 'user') {
                if (is_string($m['content'])) {
                    $entries[] = ['kind' => 'user', 'text' => $m['content'], 'at' => $m['at'] ?? null];
                } else {
                    // A click on buttons, sent with the waiting tool results
                    foreach ($m['content'] as $block) {
                        if (($block['type'] ?? null) === 'text') {
                            $entries[] = ['kind' => 'user', 'text' => (string) $block['text'], 'at' => $block['at'] ?? $m['at'] ?? null];
                        }
                    }
                }

                continue;
            }
            foreach ((array) $m['content'] as $block) {
                if (($block['type'] ?? null) === 'text' && trim((string) ($block['text'] ?? '')) !== '') {
                    $entries[] = ['kind' => 'assistant', 'text' => (string) $block['text'], 'at' => $m['at'] ?? null];
                } elseif (($block['type'] ?? null) === 'tool_use') {
                    $done = $results[$block['id'] ?? ''] ?? ['result' => null, 'error' => false];
                    $entries[] = ['kind' => 'tool', 'name' => (string) ($block['name'] ?? ''), 'input' => (array) ($block['input'] ?? []), 'at' => $m['at'] ?? null] + $done;
                }
            }
        }

        return $entries;
    }

    /** @return array<string, int> tool name => times called */
    public function toolsUsed(): array
    {
        return collect($this->transcript())->where('kind', 'tool')->countBy('name')->all();
    }
}
