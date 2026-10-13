<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Ship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ShipResource extends Resource
{
    public static string $model = Ship::class;

    public static string $slug = 'ships';

    public static string $label = 'Ships';

    public static string $singular = 'Ship';

    public static function fields(): array
    {
        return [
            Field::text('name', 'Name')->arabic()->rules(['required', 'string', 'max:255'])->index(),
            Field::text('slug', 'Code')->rules(fn (?Model $r) => ['required', 'alpha_dash', 'max:255', Rule::unique('ships', 'slug')->ignore($r?->id)]),
            Field::text('tagline', 'Tagline')->arabic()->rules(['nullable', 'string', 'max:255'])->help('Shown above the name on the ship page.')->index(),
            Field::text('flag', 'Flag')->arabic()->rules(['nullable', 'string', 'max:255']),
            Field::text('home_port', 'Home port')->arabic()->rules(['nullable', 'string', 'max:255']),
            Field::textarea('description', 'Short description')->arabic()->rules(['nullable', 'string', 'max:1000']),
            Field::lines('story', '"Built for the Red Sea" paragraphs')->arabic()->rules(['nullable', 'string'])->help('One paragraph per line.'),
            Field::image('image', 'Side view photo'),
            Field::image('hero_image', 'Hero photo'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->cruises()->exists();
    }
}
