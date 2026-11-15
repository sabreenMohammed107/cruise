<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Claude Messages API (POST /v1/messages), through Laravel's HTTP client so tests can Http::fake() it.
 */
class ClaudeClient
{
    /**
     * @param  list<array{type: string, text: string}>  $system
     * @param  list<array<string, mixed>>  $tools
     * @param  list<array{role: string, content: mixed}>  $messages
     * @return array<string, mixed> the API response: content blocks, stop_reason, usage
     *
     * @throws RequestException
     */
    public function send(array $system, array $tools, array $messages): array
    {
        $payload = [
            'model' => config('assistant.model'),
            'max_tokens' => config('assistant.max_tokens'),
            'system' => $system,
            'messages' => $messages,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        return Http::baseUrl(rtrim((string) config('assistant.base_url'), '/'))
            ->withHeaders([
                'x-api-key' => (string) config('assistant.api_key'),
                'anthropic-version' => (string) config('assistant.api_version'),
            ])
            ->acceptJson()
            ->timeout((int) config('assistant.timeout'))
            ->retry(2, 1000, fn ($e) => $e instanceof ConnectionException || $e instanceof RequestException &&in_array($e->response->status(), [429, 500, 502, 503, 529], true), throw: true)
            ->post('/v1/messages', $payload)
            ->throw()
            ->json();
    }
}
