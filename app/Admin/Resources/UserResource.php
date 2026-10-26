<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Options;
use App\Admin\Resource;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admins & staff: people who sign in to the admin panel (admins and sellers). Website customers have
 * their own screen (CustomerResource). Changing someone's role moves them between the two screens.
 */
class UserResource extends Resource
{
    public static string $model = User::class;

    public static string $slug = 'users';

    public static string $label = 'Admins & staff';

    public static string $singular = 'Staff member';

    public static array $search = ['name', 'email', 'phone'];

    public static function query(): Builder
    {
        return User::query()->staff()->withCount('assignedBookings')->orderBy('role')->orderBy('name');
    }

    public static function scope(): Builder
    {
        return User::query()->staff();
    }

    public static function fields(): array
    {
        return [
            ...static::personFields(),
            // Staff roles only: customers are managed on their own screen (Customers)
            Field::select('role', 'Role', static::roleOptions(UserRole::staff()))->default(UserRole::Seller->value)
                ->rules(['required', Rule::enum(UserRole::class)->only(UserRole::staff())])->index()
                ->help('Admin: the whole panel. Seller: the dashboard and the bookings assigned to them.'),
            Field::number('assigned_bookings_count', 'Assigned bookings')->indexOnly(),
            static::passwordField(),
        ];
    }

    /** @return list<Field> name, email, phone and nationality, shared with Customers */
    protected static function personFields(): array
    {
        return [
            Field::text('name', 'Name')->indexOnly(),
            Field::text('first_name', 'First name')->rules(['required', 'string', 'max:255']),
            Field::text('last_name', 'Last name')->rules(['required', 'string', 'max:255']),
            Field::email('email', 'Email')->rules(fn (?Model $r) => ['required', 'email:rfc,strict', 'max:255', Rule::unique('users', 'email')->ignore($r?->id)])->index(),
            Field::select('phone_dial', 'Country code', ['' => '—'] + Options::dialCodes())
                ->rules(['nullable', Rule::in(array_column(config('cruise.dial_codes'), 0))]),
            Field::text('phone', 'Mobile number')->rules(['nullable', 'regex:/^\d{7,12}$/']),
            Field::select('nationality', 'Nationality', ['' => '—'] + array_combine(config('cruise.nationalities'), config('cruise.nationalities')))
                ->rules(['nullable', Rule::in(config('cruise.nationalities'))]),
        ];
    }

    /**
     * @param  list<UserRole>|null  $roles  defaults to every role
     * @return array<string, string>
     */
    protected static function roleOptions(?array $roles = null): array
    {
        return collect($roles ?? array_reverse(UserRole::cases()))->mapWithKeys(fn (UserRole $r) => [$r->value => $r->label()])->all();
    }

    protected static function passwordField(): Field
    {
        return Field::password('password', 'Password')->rules(fn (?Model $r) => [$r ? 'nullable' : 'required', Password::min(8)->numbers()])
            ->help('Leave empty to keep the current password.');
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
        return $record->assigned_bookings_count
            ? ["Bookings ({$record->assigned_bookings_count})" => route('admin.bookings.index', ['seller' => $record->id])]
            : [];
    }

    public static function canDelete(Model $record): bool
    {
        return $record->id !== auth()->id();
    }
}
