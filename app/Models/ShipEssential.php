<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipEssential extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['title', 'text'];

    protected $fillable = ['ship_id', 'icon', 'title', 'text', 'sort_order'];

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }
}
