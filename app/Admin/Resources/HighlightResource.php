<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Highlight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Admin → Cruises → Highlights (/admin/highlights): the "Highlights" ticked on each cruise (step 22).
 */
class HighlightResource extends Resource
{
    public static string $model = Highlight::class;

    public static string $slug = 'highlights';

    public static string $label = 'Highlights';

    public static string $singular = 'Highlight';

    public static string $title = 'name_en';

    public static array $search = ['name_en', 'name_ar', 'key'];

    public static function query(): Builder
    {
        return Highlight::query()->ordered();
    }

    public static function fields(): array
    {
        return [
            Field::text('name_en', 'Name (English)')->rules(['required', 'string', 'max:60'])->index(),
            Field::text('name_ar', 'Name (Arabic)')->rules(['required', 'string', 'max:60'])->index(),
            Field::text('key', 'Code')->rules(fn (?Model $r) => ['required', 'alpha_dash', 'max:40', Rule::unique('highlights', 'key')->ignore($r?->id)])
                ->readonlyOnEdit()
                ->help('Short code in English, e.g. "family". Used in the cruises-list address (?hl=family); it can\'t be changed later.'),
            Field::checkbox('show_on_cards', 'Show as a tag on the cruise card photos')->index(),
            Field::checkbox('show_on_cruise_page', 'Show as a tag on the cruise page')->default(true),
            Field::select('tone', 'Tag colour on the cruise page', Highlight::TONES)->default('ocean')->rules(['required', Rule::in(array_keys(Highlight::TONES))]),
            Field::number('sort_order', 'Order')->default(0)->rules(['nullable', 'integer', 'min:0']),
            Field::checkbox('is_active', 'Active')->default(true)->index()
                ->help('Active highlights are in the cruises-list filter. Inactive ones are hidden on the website; cruises keep them ticked.'),
        ];
    }

    /** Not while a cruise has it ticked: untick it on those cruises first, or make it inactive. */
    public static function canDelete(Model $record): bool
    {
        return ! $record->inUse();
    }
}
