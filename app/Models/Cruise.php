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
        'visa_free', 'rating', 'reviews_count', 'popularity', 'price_factor', 'image_slot', 'gallery_slots', 'summary', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'route' => 'array',
            'tags' => 'array',
            'gallery_slots' => 'array',
            'visa_free' => 'boolean',
            'is_active' => 'boolean',
            'rating' => 'decimal:1',
            'price_factor' => 'decimal:2',
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
     * Whether the route stops at a port. Route entries may carry a country ("Yanbu, Saudi Arabia"),
     * so this matches the start of the entry, as destinations.js does.
     */
    public function callsAt(string $port): bool
    {
        return collect($this->route)->contains(fn (string $stop) => str_starts_with($stop, $port));
    }
}
