<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Facility extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['value', 'label', 'description'];

    protected $fillable = ['ship_id', 'icon', 'value', 'label', 'description', 'sort_order'];

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }
}
