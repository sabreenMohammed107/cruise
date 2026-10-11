<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Options;
use App\Admin\Resource;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base for the ordered lists that make up the ship page (specs, zones, essentials, FAQs, facilities).
 */
abstract class ShipContentResource extends Resource
{
    public static function query(): Builder
    {
        return static::$model::query()->orderBy('ship_id')->orderBy('sort_order');
    }

    protected static function shipField(): Field
    {
        return Field::select('ship_id', 'Ship', Options::ships())->rules(['required', 'exists:ships,id']);
    }

    protected static function orderField(): Field
    {
        return Field::number('sort_order', 'Order')->default(0)->rules(['nullable', 'integer', 'min:0'])->index();
    }

    protected static function iconField(): Field
    {
        return Field::select('icon', 'Icon', Options::icons())->rules(['required', 'in:'.implode(',', array_keys(Options::icons()))])->index();
    }
}
