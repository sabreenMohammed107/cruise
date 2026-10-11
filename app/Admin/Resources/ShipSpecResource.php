<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Models\ShipSpec;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * "Ship facts" / "AROYA at a glance" figures.
 */
class ShipSpecResource extends ShipContentResource
{
    public static string $model = ShipSpec::class;

    public static string $slug = 'ship-specs';

    public static string $label = 'Ship facts';

    public static string $singular = 'Ship fact';

    public static string $title = 'label';

    public static function fields(): array
    {
        return [
            static::shipField(),
            Field::text('label', 'Label')->arabic()->rules(['required', 'string', 'max:255'])->index(),
            Field::text('key', 'Key')->rules(fn (?Model $r) => ['required', 'alpha_dash', 'max:40',
                Rule::unique('ship_specs', 'key')->where('ship_id', request('ship_id'))->ignore($r?->id)])
                ->help('tonnage, cabins, length, speed, passengers, crew or decks: the ship page hero uses length, decks, cabins and passengers.'),
            Field::text('value', 'Value')->arabic()->rules(['required', 'string', 'max:40'])->help('As displayed, e.g. "151,300".')->index(),
            Field::text('unit', 'Unit')->arabic()->rules(['nullable', 'string', 'max:20'])->help('e.g. GT, m, knots')->index(),
            static::orderField(),        ];
    }
}
