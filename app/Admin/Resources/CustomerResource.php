<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Customers: accounts made on the website (role "customer"). Filters, a details page
 * (Admin\CustomerController::show) and a spreadsheet export of the filtered list.
 */
class CustomerResource extends UserResource
{
    public static string $slug = 'customers';

    public static string $label = 'Customers';

    public static string $singular = 'Customer';

    public static function query(): Builder
    {
        return User::query()->customers()->withCount('bookings')->latest('id');
    }

    public static function scope(): Builder
    {
        return User::query()->customers();
    }

    public static function fields(): array
    {
        $person = static::personFields();
        foreach ($person as $field) {
            if ($field->name === 'nationality') {
                $field->index();
            }
        }

        return [
            ...$person,
            Field::text('full_phone', 'Phone')->indexOnly(),
            Field::number('bookings_count', 'Bookings')->indexOnly(),
            Field::date('created_at', 'Registered')->indexOnly(),
            Field::select('role', 'Account type', static::roleOptions())->default(UserRole::Customer->value)->rules(['required', Rule::enum(UserRole::class)])
                ->help('Choose Seller or Admin to give this person access to the admin panel (they move to Admins & staff).'),
            static::passwordField(),
        ];
    }

    /**
     * ?nationality=…, ?bookings=with|without, ?from=Y-m-d&to=Y-m-d (registration date). Also used by the export.
     *
     * @return array{nationality?: string, bookings?: string, from?: string, to?: string}
     */
    public static function filters(Request $request): array
    {
        return array_filter($request->validate([
            'nationality' => ['nullable', Rule::in(config('cruise.nationalities'))],
            'bookings' => ['nullable', Rule::in(['with', 'without'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]));
    }

    public static function filter(Builder $query, Request $request): Builder
    {
        $f = static::filters($request);

        return $query
            ->when($f['nationality'] ?? null, fn (Builder $q, $v) => $q->where('nationality', $v))
            ->when(($f['bookings'] ?? null) === 'with', fn (Builder $q) => $q->has('bookings'))
            ->when(($f['bookings'] ?? null) === 'without', fn (Builder $q) => $q->doesntHave('bookings'))
            ->when($f['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    public static function indexToolbar(): ?string
    {
        return 'admin.customers.toolbar';
    }

    public static function rowActions(Model $record): array
    {
        return ['View' => route('admin.customers.show', $record->id)];
    }

    public static function canDelete(Model $record): bool
    {
        // Accounts with bookings are kept, so their booking history stays linked to them
        return ! ($record->bookings_count ?? $record->bookings()->count());
    }
}
