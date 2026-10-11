<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Enums\PromoType;
use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PromoCodeResource extends Resource
{
    public static string $model = PromoCode::class;

    public static string $slug = 'promo-codes';

    public static string $label = 'Promo codes';

    public static string $singular = 'Promo code';

    public static string $title = 'code';

    public static array $search = ['code', 'label'];

    public static function fields(): array
    {
        return [
            Field::text('code', 'Code')->rules(fn (?Model $r) => ['required', 'alpha_num', 'max:40', Rule::unique('promo_codes', 'code')->ignore($r?->id)])
                ->help('Letters and numbers. Guests can type it in any case.')->index(),
            Field::select('type', 'Discount type', ['percent' => 'Percent of cruise fare', 'fixed' => 'Fixed amount ($)'])
                ->rules(['required', Rule::enum(PromoType::class)])->index(),
            Field::number('value', 'Value', '0.01')->rules(['required', 'numeric', 'min:0.01', fn ($attr, $value, $fail) => request('type') === 'percent' && $value > 100 ? $fail('A percent discount can be at most 100.') : null])->index(),
            Field::text('label', 'Label shown to guests')->arabic()->rules(['required', 'string', 'max:255'])->help('e.g. "10% off cruise fare"')->wide(),
            Field::date('starts_at', 'Valid from')->rules(['nullable', 'date']),
            Field::date('ends_at', 'Valid until')->rules(['nullable', 'date', 'after_or_equal:starts_at']),
            Field::number('max_uses', 'Maximum uses')->rules(['nullable', 'integer', 'min:1'])->help('Empty = unlimited.'),
            Field::number('used_count', 'Used')->indexOnly(),
            Field::checkbox('is_active', 'Active')->default(true)->index(),
        ];
    }
}
