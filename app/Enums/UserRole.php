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
    /**
     * The developer's account: full control of the panel, and invisible in it (not in staff(), so no list, form or
     * role choice shows it). Made only by Database\Seeders\SuperAdminSeeder from .env, never from the panel.
     */
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Agent => 'Agent',
            self::Seller => 'Seller',
            self::Supervisor => 'Supervisor',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super admin',
        };
    }

    /** Can sign in to the admin panel (the super admin too). Customers and agents sign in on the website. */
    public function isStaff(): bool
    {
        return $this === self::SuperAdmin || in_array($this, self::staff(), true);
    }

    /** @return list<self> the staff roles the panel shows and can assign (not the hidden super admin) */
    public static function staff(): array
    {
        return [self::Admin, self::Supervisor, self::Seller];
    }
}
