<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    public static string $model = User::class;

    public static string $slug = 'users';

    public static string $label = 'Users';

    public static string $singular = 'User';

    public static array $search = ['name', 'email', 'phone'];

    public static function query(): Builder
    {
        return User::query()->withCount('bookings')->latest('id');
    }

    public static function fields(): array
    {
        return [
            Field::text('name', 'Name')->indexOnly(),
            Field::text('first_name', 'First name')->rules(['required', 'string', 'max:255']),
            Field::text('last_name', 'Last name')->rules(['required', 'string', 'max:255']),
            Field::email('email', 'Email')->rules(fn (?Model $r) => ['required', 'email:rfc,strict', 'max:255', Rule::unique('users', 'email')->ignore($r?->id)])->index(),
            Field::select('phone_dial', 'Country code', ['' => '—'] + collect(config('cruise.dial_codes'))->mapWithKeys(fn ($d) => [$d[0] => "$d[1] $d[0]"])->all())
                ->rules(['nullable', Rule::in(array_column(config('cruise.dial_codes'), 0))]),
            Field::text('phone', 'Mobile number')->rules(['nullable', 'regex:/^\d{7,12}$/']),
            Field::select('nationality', 'Nationality', ['' => '—'] + array_combine(config('cruise.nationalities'), config('cruise.nationalities')))
                ->rules(['nullable', Rule::in(config('cruise.nationalities'))]),
            Field::select('role', 'Role', ['customer' => 'Customer', 'admin' => 'Admin'])->default('customer')->rules(['required', Rule::enum(UserRole::class)])->index(),
            Field::number('bookings_count', 'Bookings')->indexOnly(),
            Field::password('password', 'Password')->rules(fn (?Model $r) => [$r ? 'nullable' : 'required', Password::min(8)->numbers()])
                ->help('Leave empty to keep the current password.'),
        ];
    }

    /** "name" is filled by the model from first/last name; role is not mass-assignable. */
    public static function fill(Model $record, Request $request): void
    {
        foreach (['first_name', 'last_name', 'email', 'phone_dial', 'phone', 'nationality'] as $attribute) {
            $record->setAttribute($attribute, $request->input($attribute));
        }
        $record->email = strtolower($record->email);
        if ($request->filled('password')) {
            $record->password = $request->input('password');
        }
        // An admin can't change their own role (and lock themselves out).
        if (! $record->exists || $record->id !== $request->user()->id) {
            $record->role = UserRole::from($request->input('role'));
        }
    }

    public static function rowActions(Model $record): array
    {
        return $record->bookings_count
            ? ["Bookings ({$record->bookings_count})" => route('admin.bookings.index', ['q' => $record->email])]
            : [];
    }

    public static function canDelete(Model $record): bool
    {
        return $record->id !== auth()->id();
    }
}
