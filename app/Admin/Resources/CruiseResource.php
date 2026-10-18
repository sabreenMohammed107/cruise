<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Options;
use App\Admin\Resource;
use App\Enums\SailingStatus;
use App\Models\Cruise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CruiseResource extends Resource
{
    public static string $model = Cruise::class;

    public static string $slug = 'cruises';

    public static string $label = 'Cruises';

    public static string $singular = 'Cruise';

    public static array $search = ['name', 'slug', 'departure_port'];

    public static function query(): Builder
    {
        return Cruise::query()
            ->withCount('sailings')
            // Closest departure from today on that isn't cancelled (index column "Next sailing")
            ->withMin(['sailings as next_sailing' => fn (Builder $q) => $q
                ->whereDate('departure_date', '>=', today())
                ->where('status', '!=', SailingStatus::Cancelled)], 'departure_date')
            ->orderBy('name');
    }

    public static function fields(): array
    {
        return [
            Field::text('name', 'Name')->rules(['required', 'string', 'max:255'])->index(),
            Field::text('slug', 'URL slug')->rules(fn (?Model $r) => ['required', 'alpha_dash', 'max:255', Rule::unique('cruises', 'slug')->ignore($r?->id)])
                ->help('Used in the address: /cruises/{slug}. Changing it breaks old links.')->index(),
            Field::text('name_ar', 'Name (Arabic)')->rules(['nullable', 'string', 'max:255']),
            Field::select('ship_id', 'Ship', Options::ships())->rules(['required', 'exists:ships,id']),
            Field::number('nights', 'Nights')->rules(['required', 'integer', 'min:1', 'max:60'])->index(),
            Field::text('departure_port', 'Departure port')->rules(['required', 'string', 'max:255'])->index(),
            Field::date('next_sailing', 'Next sailing')->indexOnly(),
            Field::text('arrival_port', 'Arrival port')->rules(['required', 'string', 'max:255']),
            Field::lines('route', 'Route')->rules(['required', 'string'])
                ->help('One port per line, in order. Use the same port names as Destinations, e.g. "Al Wajh".'),
            Field::checkboxes('tags', 'Highlights', ['family' => 'Family friendly', 'excursions' => 'Excursions', 'weekend' => 'Weekend', 'festive' => 'Festive sailing']),
            Field::checkbox('visa_free', 'No visa needed'),
            Field::number('rating', 'Guest rating', '0.1')->rules(['nullable', 'numeric', 'min:0', 'max:5']),
            Field::number('reviews_count', 'Number of reviews')->rules(['nullable', 'integer', 'min:0']),
            Field::number('popularity', 'Popularity (0–100)')->rules(['nullable', 'integer', 'min:0', 'max:100'])->help('Used for "Most popular" sorting.'),
            Field::image('image', 'Card image'),
            Field::images('gallery', 'Gallery'),
            Field::textarea('summary', 'Summary')->arabic()->rules(['nullable', 'string', 'max:2000']),
            Field::checkbox('is_active', 'Show on the website')->default(true)->index(),
        ];
    }

    public static function rowActions(Model $record): array
    {
        return [
            "Sailings ({$record->sailings_count})" => route('admin.cruises.sailings.index', $record),
            'View' => route('cruises.show', $record),
        ];
    }

    public static function formExtra(): ?string
    {
        return 'admin.cruises.itinerary';
    }

    public static function extraRules(?Model $record): array
    {
        return [
            'itinerary' => ['nullable', 'array'],
            'itinerary.*.port' => ['nullable', 'string', 'max:255'],
            'itinerary.*.time' => ['nullable', 'string', 'max:40'],
            'itinerary.*.time_ar' => ['nullable', 'string', 'max:40'],
            'itinerary.*.text' => ['nullable', 'string', 'max:2000'],
            'itinerary.*.text_ar' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function fill(Model $record, Request $request): void
    {
        parent::fill($record, $request);
        $record->tags = $record->tags ?: [];
        $record->reviews_count ??= 0;
        $record->popularity ??= 0;
    }

    /**
     * Rewrites the day-by-day itinerary from the editor rows (rows without a port are dropped).
     */
    public static function afterSave(Model $record, Request $request): void
    {
        $days = collect($request->input('itinerary', []))
            ->filter(fn ($day) => filled($day['port'] ?? null))
            ->values();

        $record->itinerary()->delete();
        foreach ($days as $i => $day) {
            $record->itinerary()->create([
                'day_number' => $i + 1,
                'port' => $day['port'],
                'time_label' => $day['time'] ?? null,
                'description' => $day['text'] ?? null,
                'time_label_ar' => $day['time_ar'] ?? null,
                'description_ar' => $day['text_ar'] ?? null,
            ]);
        }
    }

    public static function canDelete(Model $record): bool
    {
        // Cruises are soft-deleted; bookings keep pointing at their sailings.
        return true;
    }
}
