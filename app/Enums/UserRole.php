<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    /** Sales staff: signs in to the admin panel and works on the bookings assigned to them. */
    case Seller = 'seller';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Seller => 'Seller',
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
        return [self::Admin, self::Seller];
    }
}
