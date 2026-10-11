<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Options;
use App\Admin\Resource;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Builder;

class TestimonialResource extends Resource
{
    public static string $model = Testimonial::class;

    public static string $slug = 'testimonials';

    public static string $label = 'Testimonials';

    public static string $singular = 'Testimonial';

    public static function query(): Builder
    {
        return Testimonial::query()->orderBy('sort_order');
    }

    public static function fields(): array
    {
        return [
            Field::text('name', 'Guest name')->arabic()->rules(['required', 'string', 'max:255'])->index(),
            Field::text('from_city', 'From')->arabic()->rules(['nullable', 'string', 'max:255'])->index(),
            Field::select('cruise_id', 'Cruise', ['' => '—'] + Options::cruises())->rules(['nullable', 'exists:cruises,id']),
            Field::number('rating', 'Rating (1–5)')->rules(['required', 'integer', 'min:1', 'max:5'])->index(),
            Field::textarea('text', 'Review')->arabic()->rules(['required', 'string', 'max:2000']),
            Field::number('sort_order', 'Order')->default(0)->rules(['nullable', 'integer', 'min:0']),
            Field::checkbox('is_published', 'Show on the homepage')->default(true)->index(),
        ];
    }
}
