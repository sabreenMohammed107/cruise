<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cruise extends Model
{
    use HasArabicCopy, SoftDeletes;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['name', 'summary'];

    protected $fillable = [
        'ship_id', 'slug', 'name', 'name_ar', 'nights', 'departure_port', 'arrival_port', 'route', 'tags',
        'visa_free', 'rating', 'reviews_count', 'popularity', 'image', 'gallery', 'summary', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'route' => 'array',
            'tags' => 'array',
            'gallery' => 'array',
            'visa_free' => 'boolean',
            'is_active' => 'boolean',
            'rating' => 'decimal:1',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function itinerary(): HasMany
    {
        return $this->hasMany(ItineraryDay::class)->orderBy('day_number');
    }

    public function sailings(): HasMany
    {
        return $this->hasMany(Sailing::class)->orderBy('departure_date');
    }

    /** The route: ports in sailing order (Admin → Route ports). A port may appear twice, e.g. Jeddah … Jeddah. */
    public function ports(): BelongsToMany
    {
        return $this->belongsToMany(Port::class, 'cruise_port')->withPivot('position')->orderByPivot('position');
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Saves the route as ordered stops (port ids, repeats allowed) and refreshes the `route` column.
     *
     * @param  array<int|string>  $portIds
     */
    public function syncRoute(array $portIds): void
    {
        $this->ports()->detach();
        foreach (array_values($portIds) as $i => $id) {
            $this->ports()->attach((int) $id, ['position' => $i + 1]);
        }
        $this->refreshRoute();
    }

    /**
     * `route` keeps the English names of the stops (the keys core.js and destinations match on);
     * cruise_port is the source.
     */
    public function refreshRoute(): void
    {
        $this->unsetRelation('ports');
        $this->forceFill(['route' => $this->ports()->pluck('ports.name_en')->all()])->save();
    }

    /** English stop names in order: from the ports, or the stored `route` for a cruise without port rows. */
    public function routeNames(): array
    {
        return $this->ports->isNotEmpty() ? $this->ports->pluck('name_en')->all() : ($this->route ?? []);
    }

    /**
     * Whether the route stops at a port. Route entries may carry a country ("Yanbu, Saudi Arabia"),
     * so this matches the start of the entry, as destinations.js does.
     */
    public function callsAt(string $port): bool
    {
        return collect($this->route)->contains(fn (string $stop) => str_starts_with($stop, $port));
    }
}
