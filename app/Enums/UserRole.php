<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    /** Sales staff: signs in to the admin panel and works on the bookings assigned to them. */
    case Seller = 'seller';
    /** Runs the panel day to day: adds and edits everything and assigns bookings, but can't delete or manage people. */
    case Supervisor = 'supervisor';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Seller => 'Seller',
            self::Supervisor => 'Supervisor',
            self::Admin => 'Admin',
        };
    }

    /** Can sign in to the admin panel. */
    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    /** @return list<self> */
    public static function staff(): array
    {
        return [self::Admin, self::Supervisor, self::Seller];
    }
}
