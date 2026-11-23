<?php

namespace App\Services\Assistant\Tools;

use App\Models\User;

/**
 * Buttons in the chat: one choice, or several (checkboxes, some ticked). The turn ends here (AgentLoop stops after
 * this tool): the visitor's click comes back as their next message.
 */
class OfferChoices extends Tool
{
    public function name(): string
    {
        return 'offer_choices';
    }

    public function description(): string
    {
        return 'Show the visitor clickable buttons instead of making them type: a question with 2-12 short options. '
            .'multiple=true shows checkboxes (use "selected" for the ones ticked by default) and a confirm button. '
            .'Your turn ends after this call; their choice arrives as their next message. Use it for each step of a report '
            .'(period, columns) and whenever there are a few clear options.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'question' => ['type' => 'string', 'description' => 'Short question, in the visitor\'s language.'],
                'options' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 2, 'maxItems' => 12],
                'multiple' => ['type' => 'boolean', 'description' => 'Checkboxes (several answers) instead of buttons.'],
                'selected' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'With multiple: options ticked at first.'],
            ],
            'required' => ['question', 'options'],
        ];
    }

    public function stopsTurn(): bool
    {
        return true;
    }

    public function handle(array $input, ?User $user): array
    {
        $clean = fn ($list) => array_values(array_unique(array_filter(array_map(
            fn ($o) => is_scalar($o) ? mb_substr(trim((string) $o), 0, 60) : '',
            (array) $list,
        ), fn ($o) => $o !== '')));

        $options = array_slice($clean($input['options'] ?? []), 0, 12);
        if (count($options) < 2) {
            return ['error' => 'Give at least two options.'];
        }
        $multiple = (bool) ($input['multiple'] ?? false);

        return [
            'shown' => true,
            'note' => 'The visitor sees the buttons. Wait for their answer.',
            '_ui' => [
                'type' => 'choices',
                'question' => mb_substr(trim((string) ($input['question'] ?? '')), 0, 200),
                'options' => $options,
                'multiple' => $multiple,
                'selected' => $multiple ? array_values(array_intersect($clean($input['selected'] ?? []), $options)) : [],
            ],
        ];
    }
}
