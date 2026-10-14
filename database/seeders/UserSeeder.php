<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Cruise;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local demo accounts (admin, seller, customer). All use the password "password". Never run this seeder in production.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'admin@safercruise.test'], [
            'first_name' => 'Safer',
            'last_name' => 'Admin',
            'password' => 'password',
        ]);
        $admin->forceFill(['role' => UserRole::Admin, 'email_verified_at' => now()])->save();

        // A seller: signs in to /admin and sees the bookings an admin assigns to them.
        $seller = User::updateOrCreate(['email' => 'seller@safercruise.test'], [
            'first_name' => 'Sara',
            'last_name' => 'Seller',
            'password' => 'password',
        ]);
        $seller->forceFill(['role' => UserRole::Seller, 'email_verified_at' => now()])->save();

        // The demo customer shown on dashboard.html.
        $reem = User::updateOrCreate(['email' => 'reem@example.com'], [
            'first_name' => 'Reem',
            'last_name' => 'Alharbi',
            'phone_dial' => '+966',
            'phone' => '501234567',
            'date_of_birth' => '1990-04-12',
            'nationality' => 'Saudi Arabia',
            'notification_prefs' => ['offers' => true, 'whatsapp' => true, 'reminders' => true],
            'password' => 'password',
        ]);
        $reem->forceFill(['role' => UserRole::Customer, 'email_verified_at' => now()])->save();

        $reem->favorites()->syncWithoutDetaching(
            Cruise::whereIn('slug', ['new-year-at-sea', 'egypt-jordan-voyage'])->pluck('id')
        );
    }
}
