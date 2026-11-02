<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItineraryDay extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['time_label', 'description'];

    /**
     * port: the day's name, an English port name (also the key SC.port() translates), "At sea", or free text such as
     * "Arrival at Alexandria". port_ar: its Arabic name, optional, since port names are already shown in Arabic.
     */
    protected $fillable = ['cruise_id', 'day_number', 'port', 'port_ar', 'time_label', 'description'];

    public function cruise(): BelongsTo
    {
        return $this->belongsTo(Cruise::class);
    }

    /** A sea day, however the admin typed it ("At sea", "At Sea", "at sea "). */
    public function isAtSea(): bool
    {
        return strcasecmp(trim((string) $this->port), 'At sea') === 0;
    }
}
