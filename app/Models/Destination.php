<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['name', 'country', 'blurb', 'description', 'highlights', 'visa_note'];

    protected $fillable = [
        'slug', 'name', 'name_ar', 'country', 'image_slot', 'blurb', 'description', 'highlights', 'visa_note',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * "Cruises that call here": active cruises whose route includes this port.
     * Route entries look like "Yanbu" or "Yanbu, Saudi Arabia" (the template matches with startsWith).
     *
     * @return Collection<int, Cruise>
     */
    public function cruises(): Collection
    {
        return Cruise::active()->get()->filter(
            fn (Cruise $cruise) => $cruise->callsAt($this->name)
        )->values();
    }
}
