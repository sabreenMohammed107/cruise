<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Models\ShipZone;

/**
 * "Life on board" image rows on the ship page.
 */
class ShipZoneResource extends ShipContentResource
{
    public static string $model = ShipZone::class;

    public static string $slug = 'ship-zones';

    public static string $label = 'Life on board';

    public static string $singular = 'Life on board row';

    public static string $title = 'title';

    public static function fields(): array
    {
        return [
            static::shipField(),
            Field::text('title', 'Title')->arabic()->rules(['required', 'string', 'max:255'])->index(),
            static::iconField(),
            Field::imageSlot('image_slot', 'Photo'),
            Field::textarea('text', 'Text')->arabic()->rules(['required', 'string', 'max:2000']),
            static::orderField(),        ];
    }
}
