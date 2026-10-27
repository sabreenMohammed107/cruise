<?php

namespace App\Admin\Resources;

use App\Admin\CruiseFilters;
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
            // Sailings the website lists (open, departing after today): without one the cruise page is a 404
            ->withCount(['sailings as bookable_sailings_count' => fn (Builder $q) => $q->upcoming()])
            // Closest departure from today on that isn't cancelled (index column "Next sailing")
            ->withMin(['sailings as next_sailing' => fn (Builder $q) => $q
                ->whereDate('departure_date', '>=', today())
                ->where('status', '!=', SailingStatus::Cancelled)], 'departure_date')
            // Closest next sailing first; cruises with none scheduled at the end; ties by name
            ->orderByRaw('next_sailing is null')
            ->orderBy('next_sailing')
            ->orderBy('name');
    }

    /**
     * Only cruises still running (Cruise::current()); finished sailings are in the archive. Year and month
     * keep the cruises with a sailing not back yet that departs then; port and nights are the cruise's own.
     */
    public static function filter(Builder $query, Request $request): Builder
    {
        $years = CruiseFilters::currentYears();
        $f = CruiseFilters::from($request, $years);

        return $query->current()
            ->when(isset($f['year']) || isset($f['month']), fn (Builder $q) => $q->whereHas('sailings', fn (Builder $s) => $s
                ->current()
                ->departingIn(isset($f['year']) ? [$f['year']] : $years, $f['month'] ?? null)))
            ->when($f['port'] ?? null, fn (Builder $q, $v) => $q->where('departure_port', $v))
            ->when($f['nights'] ?? null, fn (Builder $q, $v) => $q->where('nights', $v));
    }

    public static function indexToolbar(): ?string
    {
        return 'admin.cruises.toolbar';
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
            // The route (ordered stops from Route ports) is edited under the fields: admin.cruises.route
            Field::checkboxes('tags', 'Highlights', Options::highlights())
                ->help('Add, rename or hide highlights in Cruises → Highlights.'),
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
        $sailings = route('admin.cruises.sailings.index', $record);

        return [
            "Sailings ({$record->sailings_count})" => $sailings,
            // "View" only when the page exists; otherwise say why and lead to the sailings to open one
            ...match (true) {
                ! $record->is_active => [],
                $record->bookable_sailings_count > 0 => ['View' => route('cruises.show', $record)],
                default => ['Not on website: no open sailing' => $sailings],
            },
        ];
    }

    public static function formExtra(): ?string
    {
        return 'admin.cruises.extra';
    }

    public static function extraRules(?Model $record): array
    {
        return [
            'route_ports' => ['required', 'array', 'min:2', 'max:30'],
            // A port may repeat (round trips start and end at the same port)
            'route_ports.*' => ['integer', Rule::exists('ports', 'id')],
            'itinerary' => ['nullable', 'array'],
            'itinerary.*.port' => ['nullable', 'string', 'max:255'],
            'itinerary.*.time' => ['nullable', 'string', 'max:40'],
            'itinerary.*.time_ar' => ['nullable', 'string', 'max:40'],
            'itinerary.*.text' => ['nullable', 'string', 'max:2000'],
            'itinerary.*.text_ar' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function messages(): array
    {
        return parent::messages() + [
            'route_ports.required' => 'Add the ports of the route, in sailing order.',
            'route_ports.min' => 'A route needs at least two stops: where the cruise starts and where it ends.',
            'route_ports.*.exists' => 'One of the route ports no longer exists. Remove it and add the stop again.',
        ];
    }

    public static function fill(Model $record, Request $request): void
    {
        parent::fill($record, $request);
        $record->tags = $record->tags ?: [];
        $record->route ??= []; // filled from the stops by afterSave()
        $record->reviews_count ??= 0;
        $record->popularity ??= 0;
    }

    /**
     * Saves the route stops in order, then rewrites the day-by-day itinerary from the editor rows
     * (rows without a port are dropped).
     */
    public static function afterSave(Model $record, Request $request): void
    {
        $record->syncRoute($request->input('route_ports', []));

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
