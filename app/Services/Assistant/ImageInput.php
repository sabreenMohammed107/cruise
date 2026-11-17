<?php

namespace App\Services\Assistant;

/**
 * A photo sent in the chat (chosen accounts only, Assistant::imagesAllowed()). The browser shrinks it and sends it as a
 * data URL; it goes to Claude with that one message and is never saved: the conversation keeps only a note that an
 * image was sent (AgentLoop).
 */
final class ImageInput
{
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** The longest side Claude uses without shrinking the image itself; the browser resizes to this. */
    public const MAX_SIDE = 1568;

    /**
     * The API's image block for a data URL, or null when it isn't a JPEG, PNG or WebP image within the size limit
     * (checked on the content, not on what the browser says it is).
     *
     * @return array{type: string, source: array{type: string, media_type: string, data: string}}|null
     */
    public static function fromDataUrl(string $url): ?array
    {
        if (! preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=\s]+)$#', $url, $m)) {
            return null;
        }
        $bytes = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > (int) config('assistant.max_image_bytes')) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info['mime'] ?? null, self::TYPES, true)) {
            return null;
        }

        return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $info['mime'], 'data' => base64_encode($bytes)]];
    }

    /** Images sent so far in the conversation (the notes AgentLoop keeps). */
    public static function countIn(array $messages): int
    {
        $count = 0;
        foreach ($messages as $message) {
            if (($message['role'] ?? null) !== 'user') {
                continue;
            }
            $count += ! empty($message['image']) ? 1 : 0;
            if (is_array($message['content'] ?? null)) {
                foreach ($message['content'] as $block) {
                    $count += ! empty($block['image']) ? 1 : 0;
                }
            }
        }

        return $count;
    }
}
