<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    /** Travel agent: a website account (not the panel) that sees agent rates. Made by approving an agent request. */
    case Agent = 'agent';
    /** Sales staff: signs in to the admin panel and works on the bookings assigned to them. */
    case Seller = 'seller';
    /** Runs the panel day to day: adds and edits everything and assigns bookings, but can't delete or manage people. */
    case Supervisor = 'supervisor';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Agent => 'Agent',
            self::Seller => 'Seller',
            self::Supervisor => 'Supervisor',
            self::Admin => 'Admin',
        };
    }

    /** Can sign in to the admin panel. Customers and agents sign in on the website. */
    public function isStaff(): bool
    {
        return in_array($this, self::staff(), true);
    }

    /** @return list<self> */
    public static function staff(): array
    {
        return [self::Admin, self::Supervisor, self::Seller];
    }
}
