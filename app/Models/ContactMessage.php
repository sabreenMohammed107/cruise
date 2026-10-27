<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A message from the website's "Contact us" page (App\Http\Controllers\ContactController).
 */
class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'phone_dial', 'phone', 'message'];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    /** "+966 501234567", or null without a number. */
    protected function fullPhone(): Attribute
    {
        return Attribute::get(fn () => $this->phone ? trim("{$this->phone_dial} {$this->phone}") : null);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function markAsRead(): void
    {
        if (! $this->is_read) {
            $this->forceFill(['is_read' => true])->save();
        }
    }
}
