<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Models\Facility;

/**
 * Onboard facilities ("Life on board" grid on the homepage, ship and cruise pages).
 */
class FacilityResource extends ShipContentResource
{
    public static string $model = Facility::class;

    public static string $slug = 'facilities';

    public static string $label = 'Facilities';

    public static string $singular = 'Facility';

    public static string $title = 'label';

    public static function fields(): array
    {
        return [
            static::shipField(),
            Field::text('label', 'Label')->arabic()->rules(['required', 'string', 'max:255'])->index(),
            Field::text('value', 'Figure')->arabic()->rules(['required', 'string', 'max:40'])->help('e.g. "35+" or "2,000 m²"')->index(),
            static::iconField(),
            Field::textarea('description', 'Text')->arabic()->rules(['nullable', 'string', 'max:1000']),
            static::orderField(),        ];
    }
}
