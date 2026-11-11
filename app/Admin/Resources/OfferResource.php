<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Options;
use App\Admin\Resource;
use App\Enums\OfferAgentMode;
use App\Enums\OfferAudience;
use App\Enums\OfferTarget;
use App\Enums\OfferType;
use App\Models\CabinCategory;
use App\Models\Offer;
use App\Models\Sailing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Time-limited offers (App\Models\Offer, priced by App\Services\PricingService). The form asks three things, and
 * each choice shows only its own fields (Field::showWhen):
 *
 *   1. what the discount is on (OfferTarget) and how much (OfferType)
 *   2. conditions, each switched on by its own tick box: cabin types · nationalities
 *   3. where, when, how many and for whom: cruise / sailing, dates, caps, customers / agents, promo codes
 */
class OfferResource extends Resource
{
    public static string $model = Offer::class;

    public static string $slug = 'offers';

    public static string $label = 'Offers';

    public static string $singular = 'Offer';

    public static string $title = 'name';

    public static array $search = ['name', 'name_ar'];

    /** Condition switches (form only) and the columns each one fills: off clears them (fill()). */
    private const CONDITIONS = [
        'if_cabins' => ['cabin_categories'],
        'if_nationality' => ['nationalities'],
    ];

    public static function query(): Builder
    {
        return Offer::query()->with(['cruise', 'sailing'])->latest('id');
    }

    public static function fields(): array
    {
        $blank = fn ($v) => $v === null || $v === '';
        // A condition switch: ticked when editing an offer that has a value in one of its columns
        $switch = fn (string $name, string $label, string $help) => Field::checkbox($name, $label)->wide()->help($help)
            ->virtual(fn (Offer $o) => collect(self::CONDITIONS[$name])->contains(fn ($c) => filled($o->getAttribute($c))));

        return [
            // ---- The offer ----
            Field::heading('The offer'),
            Field::text('name', 'Offer name shown to guests')->arabic()->rules(['required', 'string', 'max:80'])
                ->help('Shown on the cards and in the price, e.g. "Summer deal" or "Kids sail free".')->index(),
            Field::text('discount_label', 'Discount')->indexOnly(),
            Field::text('scope_label', 'Applies to')->indexOnly(),

            // ---- 1. What the discount is on ----
            Field::heading('Discount', 'What the offer takes off, and how much.'),
            Field::select('target', 'Discount on', collect(OfferTarget::cases())->mapWithKeys(fn (OfferTarget $t) => [$t->value => $t->label()])->all())
                ->default(OfferTarget::Fare->value)->rules(['required', Rule::enum(OfferTarget::class), function ($attribute, $value, $fail) use ($blank) {
                    if ($value === OfferTarget::Age->value && $blank(request('min_age')) && $blank(request('max_age'))) {
                        $fail('Set the age range of this offer ("From age", "Up to age" or both).');
                    }
                }])
                ->help('Cabin price: the whole cabin. Age range: each guest of that age, on their share. 3rd / 2nd guest: what that guest adds to the price. Single traveller: the extra a guest alone pays (single price − half the double). Free nights: e.g. 7 nights for the price of 6. Upgrade: the cabin at a cheaper cabin\'s price.'),
            Field::number('free_nights', 'Free nights')->showWhen('target', OfferTarget::FreeNights->value)
                ->rules(['nullable', 'required_if:target,free_nights', 'integer', 'min:1', 'max:30'])
                ->help('Nights off the cabin price: 1 on a 7-night cruise = 7 nights for the price of 6. Never more than the cruise\'s nights.'),
            Field::select('upgrade_from', 'At the price of', ['' => 'Choose a cabin'] + CabinCategory::orderBy('sort_order')->pluck('name_en', 'slug')->all())
                ->showWhen('target', OfferTarget::Upgrade->value)
                ->rules(['nullable', 'required_if:target,upgrade', Rule::exists('cabin_categories', 'slug')])
                ->help('Guests pay this cabin\'s price (same guests, same sailing) for a better one. Pick the better cabins with "Only some cabin types" below. With "Discount type" you can also give part of the difference, or a fixed upgrade price.'),
            Field::number('min_age', 'From age (years)')->showWhen('target', OfferTarget::Age->value)
                ->rules(['nullable', 'integer', 'min:0', 'max:120'])
                ->help('Age on the departure date. Empty = no lower limit.'),
            Field::number('max_age', 'Up to age (years, included)')->showWhen('target', OfferTarget::Age->value)
                ->rules(['nullable', 'integer', 'min:0', 'max:120', function ($attribute, $value, $fail) use ($blank) {
                    if (! $blank($value) && ! $blank(request('min_age')) && (int) $value < (int) request('min_age')) {
                        $fail('"Up to age" must be the same as or above "From age".');
                    }
                }])
                ->help('E.g. 0 to 11 = under 12; 60 and empty = 60 and over.'),
            // Free nights are always free: no type or value
            Field::select('type', 'Discount type', [
                OfferType::Percent->value => 'Percent off',
                OfferType::Fixed->value => 'Fixed amount off ($)',
                OfferType::FixedPrice->value => 'Fixed final price ($)',
                OfferType::Free->value => 'Free (100%)',
            ])->showWhen('target', ...array_values(array_diff(array_column(OfferTarget::cases(), 'value'), [OfferTarget::FreeNights->value])))
                ->default(OfferType::Percent->value)->rules(['nullable', 'required_unless:target,free_nights', Rule::enum(OfferType::class)])
                ->help('Fixed final price: what is left to pay, e.g. the cabin at $999 or the 3rd guest at $99.'),
            Field::number('value', 'Value', '0.01')->showWhen('type', OfferType::Percent->value, OfferType::Fixed->value, OfferType::FixedPrice->value)
                ->rules(['nullable', 'required_if:type,percent,fixed,fixed_price', 'numeric', 'min:0', fn ($attr, $value, $fail) => request('type') === 'percent' && $value > 100 ? $fail('A percent discount can be at most 100.') : null])
                ->help('Fixed amount and final price: per cabin for the cabin price, single traveller, 2nd guest and upgrade; per guest for age and 3rd guest.'),
            Field::number('max_discount', 'Maximum discount per booking ($)', '0.01')
                ->rules(['nullable', 'numeric', 'min:0.01'])->help('Empty = no maximum.'),

            // ---- 2. Conditions ----
            Field::heading('Conditions', 'Optional. Tick the ones this offer needs: it applies only when all of them are met.'),
            $switch('if_cabins', 'Only some cabin types', 'E.g. suites only.'),
            Field::checkboxes('cabin_categories', 'Cabin types', CabinCategory::orderBy('sort_order')->pluck('name_en', 'slug')->all())
                ->showWhen('if_cabins', '1'),
            $switch('if_nationality', 'Some nationalities', 'Every traveller of the cabin (or, for age offers, the guest) must have one of them.'),
            Field::checkboxes('nationalities', 'Nationalities', array_combine(config('cruise.nationalities'), config('cruise.nationalities')))
                ->showWhen('if_nationality', '1'),

            // ---- 3. Where, when, for whom ----
            Field::heading('Where and when'),
            Field::select('cruise_id', 'Cruise', ['' => 'All cruises'] + Options::cruises())
                ->rules(['nullable', Rule::exists('cruises', 'id')])
                ->help('Every sailing of this cruise. Leave on "All cruises" for every cruise.'),
            Field::select('sailing_id', 'Only this sailing', ['' => 'All sailings of the cruise'] + Options::sailings())
                ->rules(['nullable', Rule::exists('sailings', 'id'), function ($attribute, $value, $fail) {
                    $cruise = request('cruise_id');
                    if ($value && $cruise && (int) Sailing::whereKey($value)->value('cruise_id') !== (int) $cruise) {
                        $fail('That sailing belongs to another cruise. Pick its cruise, or "All cruises".');
                    }
                }])
                ->help('One departure only. Wins over an offer on the whole cruise.'),
            Field::date('starts_on', 'Offer starts on')->rules(['nullable', 'date'])->help('Empty = from today.'),
            Field::date('ends_on', 'Offer ends on (included)')->rules(['required', 'date', 'after_or_equal:starts_on'])->index(),
            Field::number('max_uses', 'Maximum bookings')
                ->rules(['nullable', 'integer', 'min:1'])->help('The offer stops after this many bookings. Empty = unlimited.'),
            Field::number('used_count', 'Bookings')->indexOnly(),

            Field::heading('Who gets it'),
            Field::select('audience', 'For', [
                OfferAudience::All->value => 'Everyone (customers and agents)',
                OfferAudience::Customers->value => 'Customers only (B2C)',
                OfferAudience::Agents->value => 'Agents only (B2B)',
            ])->default(OfferAudience::All->value)->rules(['required', Rule::enum(OfferAudience::class)])->index(),
            Field::select('agent_mode', 'For agents', [
                OfferAgentMode::Stack->value => 'On top of their commission',
                OfferAgentMode::Replace->value => 'Instead of their commission (public price − offer)',
            ])->showWhen('audience', OfferAudience::All->value, OfferAudience::Agents->value)
                ->default(OfferAgentMode::Stack->value)->rules(['nullable', Rule::enum(OfferAgentMode::class)])
                ->help('When an agent books with this offer.'),
            Field::checkbox('combinable', 'Can be combined with promo codes')->default(false)
                ->help('Off: a guest getting this offer can\'t use a promo code too.'),
            Field::checkbox('is_active', 'Active')->default(true),
            Field::text('status_label', 'Status')->indexOnly(),
        ];
    }

    /**
     * A ticked condition needs its values. Added here: list fields and empty "nullable" fields skip their own rules.
     */
    public static function rules(?Model $record): array
    {
        $rules = parent::rules($record);
        $blank = fn ($v) => $v === null || $v === '' || $v === [];
        $needs = fn (string $message, string ...$fields) => function ($attribute, $value, $fail) use ($blank, $message, $fields) {
            if (request()->boolean($attribute) && collect($fields)->every(fn ($f) => $blank(request($f)))) {
                $fail($message);
            }
        };

        $rules['if_cabins'][] = $needs('Tick the cabin types of this offer.', 'cabin_categories');
        $rules['if_nationality'][] = $needs('Tick the nationalities of this offer.', 'nationalities');

        return $rules;
    }

    /**
     * Only what the choices show is kept (the form script disables the rest; this makes sure without it): a sailing
     * brings its cruise, ages only for age offers, a value only for percent / fixed, conditions only when ticked.
     */
    public static function fill(Model $record, Request $request): void
    {
        parent::fill($record, $request);

        if ($record->sailing_id) {
            $record->cruise_id = Sailing::whereKey($record->sailing_id)->value('cruise_id');
        }
        if ($record->target !== OfferTarget::Age) {
            $record->min_age = null;
            $record->max_age = null;
        }
        if ($record->target !== OfferTarget::FreeNights) {
            $record->free_nights = null;
        } else {
            $record->type = OfferType::Free;
        }
        if ($record->target !== OfferTarget::Upgrade) {
            $record->upgrade_from = null;
        }
        if ($record->type === OfferType::Free) {
            $record->value = 100;
        }
        $record->agent_mode ??= OfferAgentMode::Stack;
        // Both conditions are lists: kept when their switch is ticked, empty (no condition) otherwise
        foreach (self::CONDITIONS as $switch => [$column]) {
            $record->setAttribute($column, $request->boolean($switch) ? (array_values((array) $request->input($column, [])) ?: null) : null);
        }
    }
}
