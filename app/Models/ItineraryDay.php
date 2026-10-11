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

    protected $fillable = ['cruise_id', 'day_number', 'port', 'time_label', 'description'];

    public function cruise(): BelongsTo
    {
        return $this->belongsTo(Cruise::class);
    }
}
