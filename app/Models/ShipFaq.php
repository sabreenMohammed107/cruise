<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipFaq extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['question', 'answer'];

    protected $fillable = ['ship_id', 'question', 'answer', 'sort_order'];

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }
}
