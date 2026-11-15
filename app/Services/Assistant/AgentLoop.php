<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * One visitor message → one assistant answer:
 *
 *   send the conversation + tools to Claude
 *   while Claude answers with tool_use: run each tool (ToolRegistry), send the results back
 *   the final text is the answer
 *
 * A tool that ends the turn (offer_choices: buttons) stops the loop without calling Claude again; its results wait
 * as the last message, and the visitor's next message (their click) is added to it. What the tools give the widget
 * (buttons, report tables) comes back as "ui".
 *
 * The whole exchange (tool calls included) is saved on the conversation, so the next message has the context.
 */
class AgentLoop
{
    public function __construct(
        private ClaudeClient $claude,
        private ToolRegistry $registry,
        private SystemPrompt $prompt,
    ) {}

    /** @return array{text: string, ui: list<array>} the answer and what the widget shows with it */
    public function reply(AssistantConversation $conversation, string $message, ?User $user): array
    {
        $messages = $conversation->messages ?? [];
        $last = $messages === [] ? null : $messages[array_key_last($messages)];
        if ($last && $last['role'] === 'user' && is_array($last['content'])) {
            // Answer to buttons: the tool results of the last turn are waiting, the click joins them
            $messages[array_key_last($messages)]['content'][] = ['type' => 'text', 'text' => $message, 'at' => now()->toIso8601String()];
        } else {
            $messages[] = ['role' => 'user', 'content' => $message, 'at' => now()->toIso8601String()];
        }
        $turnStart = count($messages);
        // Where the history sent to Claude starts: fixed for the whole turn (moving it between tool rounds would
        // change what came before this turn's thinking, and the API rejects that)
        $from = $this->windowStart($messages);

        $tools = $this->registry->for($user);
        $definitions = $this->registry->definitions($tools);
        $system = $this->prompt->blocks($user);
        $usage = ['input' => 0, 'output' => 0];
        $answer = null;
        $ui = [];

        for ($round = 0; $round <= (int) config('assistant.max_tool_rounds'); $round++) {
            $response = $this->ask($system, $definitions, $this->window($messages, $turnStart, $from), $conversation);
            $usage['input'] += (int) ($response['usage']['input_tokens'] ?? 0)
                + (int) ($response['usage']['cache_read_input_tokens'] ?? 0)
                + (int) ($response['usage']['cache_creation_input_tokens'] ?? 0);
            $usage['output'] += (int) ($response['usage']['output_tokens'] ?? 0);

            $content = array_values((array) ($response['content'] ?? []));
            $calls = array_filter($content, fn ($block) => ($block['type'] ?? null) === 'tool_use');
            if (($response['stop_reason'] ?? null) !== 'tool_use' || $calls === []) {
                // The answer (or a cut-off one: max_tokens, refusal). A tool call that won't be run is not kept: a
                // tool call with no result after it would make the next request invalid.
                $content = array_values(array_filter($content, fn ($b) => ($b['type'] ?? null) !== 'tool_use'));
                if ($content !== []) {
                    $messages[] = ['role' => 'assistant', 'content' => $content, 'at' => now()->toIso8601String()];
                    $answer = $this->text($content);
                }
                break;
            }
            $messages[] = ['role' => 'assistant', 'content' => $content, 'at' => now()->toIso8601String()];

            $results = [];
            $stop = false;
            foreach ($calls as $call) {
                [$result, $isError, $widget, $stops] = $this->registry->run($tools, (string) $call['name'], $call['input'] ?? [], $user, $conversation);
                if ($widget) {
                    $ui[] = $widget;
                }
                $stop = $stop || $stops;
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $call['id'],
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ] + ($isError ? ['is_error' => true] : []);
            }
            $messages[] = ['role' => 'user', 'content' => $results, 'at' => now()->toIso8601String()];

            if ($stop) {
                $answer = $this->text($content);
                break;
            }
        }

        if ($answer === null || (trim($answer) === '' && $ui === [])) {
            // Out of rounds (or an empty answer): close the turn so the history stays valid for the next message
            $answer = __('assistant.fallback');
            if (end($messages)['role'] === 'assistant') {
                array_pop($messages);
            }
            $messages[] = ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => $answer]], 'at' => now()->toIso8601String()];
        }

        $conversation->forceFill([
            'messages' => $messages,
            'user_messages' => $conversation->user_messages + 1,
            'input_tokens' => $conversation->input_tokens + $usage['input'],
            'output_tokens' => $conversation->output_tokens + $usage['output'],
        ])->save();

        return ['text' => trim($answer), 'ui' => $ui];
    }

    /**
     * Sends to Claude. When Claude says the request is invalid (400: a thinking block it no longer accepts, or a
     * history that went wrong some other way), tries again without any thinking blocks (the API allows leaving all
     * of them out), and then with the visitor's last message alone, so the visitor still gets an answer. Other errors
     * (network, overloaded, rate limit) are already retried by ClaudeClient.
     */
    private function ask(array $system, array $tools, array $sent, AssistantConversation $conversation): array
    {
        try {
            return $this->claude->send($system, $tools, $sent);
        } catch (RequestException $e) {
            if ($e->response->status() !== 400) {
                throw $e;
            }
            Log::warning('Assistant request refused, trying without thinking', ['conversation' => $conversation->id, 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }

        $plain = array_map(function (array $m) {
            if ($m['role'] === 'assistant' && is_array($m['content'])) {
                $kept = array_values(array_filter($m['content'], fn ($b) => ! in_array($b['type'] ?? null, ['thinking', 'redacted_thinking'], true)));
                $m['content'] = $kept !== [] ? $kept : [['type' => 'text', 'text' => '…']];
            }

            return $m;
        }, $sent);
        try {
            return $this->claude->send($system, $tools, $plain);
        } catch (RequestException $e) {
            if ($e->response->status() !== 400) {
                throw $e;
            }
            Log::warning('Assistant request refused again, sending the last message alone', ['conversation' => $conversation->id, 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }

        // The visitor's last own words, with nothing before them
        $text = '';
        foreach (array_reverse($sent) as $m) {
            if ($m['role'] !== 'user') {
                continue;
            }
            $text = is_string($m['content']) ? $m['content'] : collect($m['content'])->where('type', 'text')->pluck('text')->implode("\n");
            if ($text !== '') {
                break;
            }
        }

        return $this->claude->send($system, $tools, [['role' => 'user', 'content' => $text !== '' ? $text : '…']]);
    }

    /**
     * Index of the first message to send: the last history_messages messages, moved on to a visitor's own message
     * (never tool results, whose tool call would be missing). Null when there is none in reach.
     */
    private function windowStart(array $messages): ?int
    {
        $from = max(0, count($messages) - max(2, (int) config('assistant.history_messages')));
        for ($i = $from; $i < count($messages); $i++) {
            if ($messages[$i]['role'] === 'user' && ! $this->hasToolResults($messages[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The messages sent to Claude, from $from on, in the API's shape (our "at" times left out).
     *
     * Thinking blocks are sent back only for this turn's tool rounds. A thinking block is tied to everything sent
     * before it (system prompt included, which has today's date), so after the history is cut or the next day comes
     * the API rejects old ones. Leaving out all the earlier ones is allowed, and saves tokens.
     */
    private function window(array $messages, int $turnStart, ?int $from): array
    {
        foreach ($messages as $i => $m) {
            if ($i < $turnStart && $m['role'] === 'assistant' && is_array($m['content'])) {
                $kept = array_values(array_filter($m['content'], fn ($b) => ! in_array($b['type'] ?? null, ['thinking', 'redacted_thinking'], true)));
                $messages[$i]['content'] = $kept !== [] ? $kept : [['type' => 'text', 'text' => '…']];
            }
        }

        if ($from === null) {
            // Only tool results in reach: send the visitor's last words alone
            $text = collect((array) $messages[$turnStart - 1]['content'])->where('type', 'text')->pluck('text')->implode("
");
            $sent = [['role' => 'user', 'content' => $text !== '' ? $text : '…']];
            // and this turn's tool rounds after them
            foreach (array_slice($messages, $turnStart) as $m) {
                $sent[] = $m;
            }
        } else {
            $sent = array_slice($messages, $from);
        }

        return array_map(function (array $m) {
            unset($m['at']);
            if (is_array($m['content'])) {
                $m['content'] = array_map(function ($b) {
                    unset($b['at']);
                    // Saved as JSON, an empty tool input {} comes back as []; the API wants an object
                    return ($b['type'] ?? null) === 'tool_use' && ($b['input'] ?? []) === [] ? ['input' => (object) []] + $b : $b;
                }, $m['content']);
            }

            return $m;
        }, array_values($sent));
    }

    private function hasToolResults(array $message): bool
    {
        return is_array($message['content']) && collect($message['content'])->contains('type', 'tool_result');
    }

    private function text(array $content): string
    {
        return implode("\n\n", array_map(
            fn ($b) => (string) $b['text'],
            array_filter($content, fn ($b) => ($b['type'] ?? null) === 'text' && isset($b['text'])),
        ));
    }
}
