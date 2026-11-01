<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * The developer's super admin account (UserRole::SuperAdmin): full control of the admin panel, and never shown in it.
 * Email and password come from .env (config admin.super_admin), so they are never in the code or in git:
 *
 *   SUPER_ADMIN_EMAIL=you@example.com
 *   SUPER_ADMIN_PASSWORD="a long password"
 *
 *   php artisan db:seed --class=SuperAdminSeeder --force
 *
 * Safe to run again: it updates the same account (e.g. after changing the password in .env).
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) config('admin.super_admin.email')));
        $password = (string) config('admin.super_admin.password');

        $check = Validator::make(compact('email', 'password'), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12'],
        ]);
        if ($check->fails()) {
            throw new RuntimeException('Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD (12+ characters) in .env first: '.implode(' ', $check->errors()->all()));
        }

        // An existing customer, agent or staff account with this email would be taken over: refuse instead
        $existing = User::whereRaw('lower(email) = ?', [$email])->first();
        if ($existing && ! $existing->isSuperAdmin()) {
            throw new RuntimeException("{$email} already has a {$existing->role->label()} account. Use an email of its own for the super admin.");
        }

        $user = $existing ?? new User(['email' => $email]);
        $user->fill(['first_name' => 'Super', 'last_name' => 'Admin', 'password' => $password]);
        $user->forceFill(['role' => UserRole::SuperAdmin, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $this->command?->info("Super admin ready: {$email} (sign in at /admin/login).");
    }
}
