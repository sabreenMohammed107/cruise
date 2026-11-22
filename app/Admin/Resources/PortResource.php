<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\Port;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin → Cruises → Route ports (/admin/routes): the ports cruise routes are built from (step 16).
 */
class PortResource extends Resource
{
    public static string $model = Port::class;

    public static string $slug = 'routes';

    public static string $label = 'Route ports';

    public static string $singular = 'Route port';

    public static string $title = 'name_en';

    public static array $search = ['name_en', 'name_ar'];

    public static function query(): Builder
    {
        // Cruises calling here (a round trip has its home port twice, so count distinct cruises)
        return Port::query()->select('ports.*')
            ->addSelect(['cruises_count' => DB::table('cruise_port')->selectRaw('count(distinct cruise_id)')->whereColumn('port_id', 'ports.id')])
            ->ordered();
    }

    /** ?role=home|call: the "Port type" filter next to the search box. */
    public static function filter(Builder $query, Request $request): Builder
    {
        $role = $request->query('role');

        return $query->when(is_string($role) && isset(Port::ROLES[$role]), fn (Builder $q) => $q->where('role', $role));
    }

    public static function indexToolbar(): ?string
    {
        return 'admin.routes.toolbar';
    }

    public static function fields(): array
    {
        return [
            Field::text('name_en', 'Port name (English)')->rules(fn (?Model $r) => ['required', 'string', 'max:120', Rule::unique('ports', 'name_en')->ignore($r?->id)])
                ->help('Use the same name as the Destination of this port (e.g. "Al Wajh"), so "Cruises that call here" finds the cruises. Renaming updates every cruise route.')->index(),
            Field::text('name_ar', 'Port name (Arabic)')->rules(['required', 'string', 'max:120'])->index(),
            Field::select('role', 'Port type', Port::ROLES)->default(Port::CALL)->index()
                ->rules(fn (?Model $r) => ['required', Rule::in(array_keys(Port::ROLES)),
                    // A cruise still departing from / arriving here keeps it a departure & arrival port
                    function (string $attribute, mixed $value, Closure $fail) use ($r) {
                        if ($value === Port::CALL && $r?->exists && ($names = $r->homeCruises()->pluck('name'))->isNotEmpty()) {
                            $fail('These cruises depart from or arrive at this port: '.$names->implode(', ').'. Change their departure / arrival port first.');
                        }
                    }])
                ->help('Departure & arrival port: cruises can start or end here, and it is listed in the homepage search. Port of call only: cruises just stop here on the way.'),
            Field::number('cruises_count', 'Cruises')->indexOnly(),
            // Departure & arrival ports only: ports of call are listed by name (the form hides it, so it saves empty)
            Field::number('sort_order', 'Order')
                ->inline(fn (Port $port) => $port->role === Port::HOME)
                ->showWhen('role', Port::HOME)
                ->rules(['nullable', 'integer', 'min:0', 'max:65535'])
                ->help('Smallest first: the order of the departure ports in the website\'s "Departure port" filter and homepage search. Leave empty for no set order: those ports come after the numbered ones (on the website: soonest sailing first, then by name).'),
            Field::checkbox('is_active', 'Can be added to routes')->default(true)->index()
                ->help('Hidden ports stay on the cruises that already call there.'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->cruises()->withTrashed()->exists();
    }
}
