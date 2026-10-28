<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Accounts created on the website are always customers, so staff are made here
 * (or by an admin under Admin → Customers / Admins & staff).
 */
class MakeAdmin extends Command
{
    protected $signature = 'user:make-admin {email : Email of an existing account}
        {--supervisor : Make the account a supervisor (adds and edits, no deleting or managing people)}
        {--seller : Make the account a seller instead of an admin}
        {--revoke : Make the account a customer again}';

    protected $description = 'Give an existing user access to the admin panel as an admin, supervisor or seller (or take it away with --revoke)';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();

        if (! $user) {
            $this->error("No account uses {$this->argument('email')}. Register it on the website first.");

            return self::FAILURE;
        }

        $role = match (true) {
            (bool) $this->option('revoke') => UserRole::Customer,
            (bool) $this->option('supervisor') => UserRole::Supervisor,
            (bool) $this->option('seller') => UserRole::Seller,
            default => UserRole::Admin,
        };
        $user->forceFill(['role' => $role])->save();

        $this->info("{$user->email} is now: {$role->value}.");

        return self::SUCCESS;
    }
}
