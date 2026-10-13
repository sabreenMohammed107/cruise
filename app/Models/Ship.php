<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ship extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['name', 'tagline', 'flag', 'home_port', 'description', 'story'];

    protected $fillable = [
        'slug', 'name', 'tagline', 'flag', 'home_port', 'description', 'story', 'image', 'hero_image',
    ];

    protected function casts(): array
    {
        return ['story' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ShipSpec::class)->orderBy('sort_order');
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class)->orderBy('sort_order');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(ShipZone::class)->orderBy('sort_order');
    }

    public function essentials(): HasMany
    {
        return $this->hasMany(ShipEssential::class)->orderBy('sort_order');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(ShipFaq::class)->orderBy('sort_order');
    }

    public function cabinCategories(): HasMany
    {
        return $this->hasMany(CabinCategory::class)->orderBy('sort_order');
    }

    public function cruises(): HasMany
    {
        return $this->hasMany(Cruise::class);
    }
}
