<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Models\ShipFaq;

/**
 * "Questions about the ship".
 */
class ShipFaqResource extends ShipContentResource
{
    public static string $model = ShipFaq::class;

    public static string $slug = 'ship-faqs';

    public static string $label = 'Ship FAQs';

    public static string $singular = 'Question';

    public static string $title = 'question';

    public static function fields(): array
    {
        return [
            static::shipField(),
            Field::text('question', 'Question')->arabic()->rules(['required', 'string', 'max:255'])->wide()->index(),
            Field::textarea('answer', 'Answer')->arabic()->rules(['required', 'string', 'max:2000']),
            static::orderField(),        ];
    }
}
