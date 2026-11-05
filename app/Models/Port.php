<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A port a cruise route can call at (Admin → Cruises → Route ports), named in English and Arabic.
 * name_en is also the key the website uses: it is stored in cruises.route and matched against destinations.name.
 */
class Port extends Model
{
    /** Cruises depart from / arrive at it (departure and arrival port of the cruise form, homepage search). */
    public const HOME = 'home';

    /** Cruises only stop there on the way. */
    public const CALL = 'call';

    public const ROLES = [self::HOME => 'Departure & arrival port', self::CALL => 'Port of call only'];

    protected $fillable = ['name_en', 'name_ar', 'role', 'sort_order', 'is_active'];

    protected $attributes = ['role' => self::CALL];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Cruises keep the English stop names in `route`: refresh them when a port is renamed
        static::updated(function (Port $port) {
            if ($port->wasChanged('name_en')) {
                $port->cruises()->withTrashed()->get()->unique('id')->each->refreshRoute();
                // Departure / arrival ports are stored by name too
                foreach (['departure_port', 'arrival_port'] as $column) {
                    Cruise::withTrashed()->where($column, $port->getOriginal('name_en'))->update([$column => $port->name_en]);
                }
            }
        });
    }

    /** Cruises calling here (a round trip lists its home port twice). */
    public function cruises(): BelongsToMany
    {
        return $this->belongsToMany(Cruise::class, 'cruise_port')->withPivot('position');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name_en');
    }

    /** Departure & arrival ports. */
    public function scopeHome(Builder $query): void
    {
        $query->where('role', self::HOME);
    }

    /** Cruises departing from or arriving at this port (by name, as cruises store it). */
    public function homeCruises(): Builder
    {
        return Cruise::withTrashed()->where(fn (Builder $q) => $q
            ->where('departure_port', $this->getOriginal('name_en') ?? $this->name_en)
            ->orWhere('arrival_port', $this->getOriginal('name_en') ?? $this->name_en));
    }

    /** The name in the current language (Arabic falls back to English). */
    public function label(): string
    {
        return app()->getLocale() === 'ar' && filled($this->name_ar) ? $this->name_ar : $this->name_en;
    }

    /**
     * Port ids for English names, in the same order, adding ports that don't exist yet (seeders).
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    public static function idsFor(array $names): array
    {
        return array_map(fn (string $name) => static::firstOrCreate(['name_en' => $name])->id, $names);
    }
}
