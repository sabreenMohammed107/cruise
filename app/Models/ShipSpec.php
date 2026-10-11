<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipSpec extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['label', 'value', 'unit'];

    protected $fillable = ['ship_id', 'key', 'label', 'value', 'unit', 'sort_order'];

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }
}
