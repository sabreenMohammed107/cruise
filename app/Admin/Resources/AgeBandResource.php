<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\AgeBand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The three fixed bands (adult / child / infant): labels, age limits and fees can change, the bands can't.
 */
class AgeBandResource extends Resource
{
    public static string $model = AgeBand::class;

    public static string $slug = 'age-bands';

    public static string $label = 'Age bands & fees';

    public static string $singular = 'Age band';

    public static string $title = 'label';

    public static function query(): Builder
    {
        return AgeBand::query()->orderBy('sort_order');
    }

    public static function fields(): array
    {
        return [
            Field::text('label', 'Label')->rules(['required', 'string', 'max:40'])->index(),
            Field::text('label_ar', 'Label (Arabic)')->rules(['nullable', 'string', 'max:40']),
            Field::text('hint', 'Hint')->arabic()->rules(['nullable', 'string', 'max:255'])->help('e.g. "2 to 17 years". Shown under the guest counters and on the traveller forms.')->index(),
            Field::number('min_months', 'Minimum age (months)')->rules(['required', 'integer', 'min:0', 'max:1200'])
                ->help('Age on the departure date. 18 years = 216 months, 2 years = 24, 6 months = 6. Keep the bands touching: Adult minimum = Child "Under", Child minimum = Infant "Under".')->index(),
            Field::number('max_months_exclusive', 'Under (months)')->rules(['nullable', 'integer', 'gt:min_months', 'max:1200'])
                ->help('Guests must be younger than this. Empty = no upper limit (adults).')->index(),
            Field::number('fee_per_night', 'Taxes & port fees per night ($)', '0.01')->rules(['required', 'numeric', 'min:0'])->index(),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
