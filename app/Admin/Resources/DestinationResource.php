<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Destination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class DestinationResource extends Resource
{
    public static string $model = Destination::class;

    public static string $slug = 'destinations';

    public static string $label = 'Destinations';

    public static string $singular = 'Destination';

    public static array $search = ['name', 'country'];

    public static function query(): Builder
    {
        return Destination::query()->orderBy('sort_order');
    }

    public static function fields(): array
    {
        return [
            Field::text('name', 'Port name')->rules(['required', 'string', 'max:255'])
                ->help('Must match the port name in cruise routes, so "Cruises that call here" can find them.')->index(),
            Field::text('slug', 'URL anchor')->rules(fn (?Model $r) => ['required', 'alpha_dash', 'max:255', Rule::unique('destinations', 'slug')->ignore($r?->id)])
                ->help('Used in /destinations#{slug}.'),
            Field::text('name_ar', 'Name (Arabic)')->rules(['nullable', 'string', 'max:255']),
            Field::text('country', 'Country')->arabic()->rules(['required', 'string', 'max:255'])->index(),
            Field::imageSlot('image_slot', 'Photo'),
            Field::text('blurb', 'Short line')->arabic()->rules(['nullable', 'string', 'max:255'])->help('Shown on the homepage slider.')->wide(),
            Field::textarea('description', 'Description')->arabic()->rules(['nullable', 'string', 'max:3000']),
            Field::lines('highlights', 'Things to do')->arabic()->rules(['nullable', 'string'])->help('One per line.'),
            Field::textarea('visa_note', 'Visa note')->arabic()->rules(['nullable', 'string', 'max:1000']),
            Field::number('sort_order', 'Order')->default(0)->rules(['nullable', 'integer', 'min:0']),
            Field::checkbox('is_active', 'Show on the website')->default(true)->index(),
        ];
    }
}
