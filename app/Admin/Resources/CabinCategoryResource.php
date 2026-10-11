<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Options;
use App\Admin\Resource;
use App\Models\CabinCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CabinCategoryResource extends Resource
{
    public static string $model = CabinCategory::class;

    public static string $slug = 'cabin-categories';

    public static string $label = 'Cabin categories';

    public static string $singular = 'Cabin category';

    public static string $title = 'name_en';

    public static function query(): Builder
    {
        return CabinCategory::query()->orderBy('sort_order');
    }

    public static function fields(): array
    {
        return [
            Field::text('name_en', 'Name')->rules(['required', 'string', 'max:255'])->index(),
            Field::text('name_ar', 'Name (Arabic)')->rules(['nullable', 'string', 'max:255']),
            Field::select('ship_id', 'Ship', Options::ships())->rules(['required', 'exists:ships,id']),
            Field::text('slug', 'Code')->rules(fn (?Model $r) => ['required', 'alpha_dash', 'max:40',
                Rule::unique('cabin_categories', 'slug')->where('ship_id', request('ship_id'))->ignore($r?->id)])
                ->help('Short ID used by the booking pages, e.g. "balcony".')->index(),
            Field::text('badge', 'Badge')->arabic()->rules(['nullable', 'string', 'max:40'])->help('e.g. "Best value". Leave empty for none.'),
            Field::number('max_guests', 'Maximum guests')->rules(['required', 'integer', 'min:1', 'max:9'])->index(),
            Field::number('base_rate_adult', 'Adult rate per night ($)', '0.01')->rules(['required', 'numeric', 'min:0'])->index(),
            Field::number('base_rate_child', 'Child rate per night ($)', '0.01')->rules(['required', 'numeric', 'min:0']),
            Field::number('base_rate_infant', 'Infant rate per night ($)', '0.01')->rules(['required', 'numeric', 'min:0'])
                ->help('Rates feed every sailing\'s prices. After changing them, use "Recalculate prices" on the sailings.'),
            Field::text('short_description', 'Short description')->arabic()->rules(['nullable', 'string', 'max:255'])->wide(),
            Field::textarea('description', 'Description')->arabic()->rules(['nullable', 'string', 'max:3000']),
            Field::text('size', 'Size')->arabic()->rules(['nullable', 'string', 'max:40'])->help('e.g. "20 m²"'),
            Field::text('balcony_size', 'Balcony size')->arabic()->rules(['nullable', 'string', 'max:40'])->help('Empty = no balcony.'),
            Field::text('beds', 'Beds')->arabic()->rules(['nullable', 'string', 'max:255'])->wide(),
            Field::textarea('bedroom', 'Bedroom and lounge')->arabic()->rules(['nullable', 'string', 'max:1000']),
            Field::lines('features', 'In the cabin')->arabic()->rules(['nullable', 'string'])->help('One feature per line.'),
            Field::imageSlots('image_slots', 'Photos', 4),
            Field::number('sort_order', 'Order')->default(0)->rules(['nullable', 'integer', 'min:0']),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->sailingPrices()->exists();
    }
}
