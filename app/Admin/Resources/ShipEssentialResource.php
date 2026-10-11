<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Models\ShipEssential;

/**
 * "Good to know" items on the ship page.
 */
class ShipEssentialResource extends ShipContentResource
{
    public static string $model = ShipEssential::class;

    public static string $slug = 'ship-essentials';

    public static string $label = 'Good to know';

    public static string $singular = '"Good to know" item';

    public static string $title = 'title';

    public static function fields(): array
    {
        return [
            static::shipField(),
            Field::text('title', 'Title')->arabic()->rules(['required', 'string', 'max:255'])->index(),
            static::iconField(),
            Field::textarea('text', 'Text')->arabic()->rules(['required', 'string', 'max:1000']),
            static::orderField(),        ];
    }
}
