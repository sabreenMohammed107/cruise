<?php

namespace App\Admin;

use App\Models\Cruise;
use App\Models\Highlight;
use App\Models\Ship;

/**
 * Select options shared by admin resources.
 */
final class Options
{
    public static function ships(): array
    {
        return Ship::orderBy('name')->pluck('name', 'id')->all();
    }

    /** Cruise highlights for the cruise form (Admin → Cruises → Highlights): key => name. */
    public static function highlights(): array
    {
        return Highlight::ordered()->get()
            ->mapWithKeys(fn (Highlight $h) => [$h->key => $h->name_en.($h->is_active ? '' : ' (hidden)')])
            ->all();
    }

    public static function cruises(): array
    {
        return Cruise::orderBy('name')->pluck('name', 'id')->all();
    }

    /** Icon names drawn by core.js (SC.icon). */
    public static function icons(): array
    {
        $icons = ['anchor', 'award', 'bag', 'bed', 'buoy', 'calendar', 'card', 'chat', 'check', 'coffee', 'crown', 'droplet',
            'flower', 'globe', 'heart', 'home', 'info', 'lock', 'moon', 'music', 'pin', 'pulse', 'shield', 'ship', 'smile',
            'star', 'sun', 'tag', 'ticket', 'users', 'utensils', 'waves'];

        return array_combine($icons, $icons);
    }
}
