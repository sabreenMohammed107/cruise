<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A report the chat assistant made (App\Services\Assistant\Reports\ReportEngine), or a saved template. */
class AssistantReport extends Model
{
    protected $fillable = ['user_id', 'assistant_conversation_id', 'title', 'spec', 'template_name'];

    protected function casts(): array
    {
        return ['spec' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeTemplates(Builder $query): void
    {
        $query->whereNotNull('template_name');
    }
}
