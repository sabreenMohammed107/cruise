<?php

namespace App\Models;

use App\Models\Concerns\HasArabicCopy;
use App\Enums\PromoType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PromoCode extends Model
{
    use HasArabicCopy;

    /** Text columns with an Arabic twin ({column}_ar), see HasArabicCopy. */
    public const TRANSLATABLE = ['label'];

    protected $fillable = ['code', 'type', 'value', 'label', 'starts_at', 'ends_at', 'max_uses', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => PromoType::class,
            'value' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Codes are stored and matched in upper case, as the template does.
     */
    protected function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public static function findRedeemable(?string $code): ?self
    {
        if (! $code) {
            return null;
        }

        $promo = static::where('code', strtoupper(trim($code)))->first();

        return $promo?->isRedeemable() ? $promo : null;
    }

    public function isRedeemable(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_active
            && (! $this->starts_at || $this->starts_at->lte($at))
            && (! $this->ends_at || $this->ends_at->gte($at))
            && ($this->max_uses === null || $this->used_count < $this->max_uses);
    }
}
