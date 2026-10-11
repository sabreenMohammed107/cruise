<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use App\Enums\TravellerBand;
use Illuminate\Database\Eloquent\Model;

class AgeBand extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['label', 'hint'];

    protected $fillable = [
        'key', 'label', 'label_ar', 'hint', 'min_months', 'max_months_exclusive', 'fee_per_night', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'key' => TravellerBand::class,
            'fee_per_night' => 'decimal:2',
        ];
    }
}
