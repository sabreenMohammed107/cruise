<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Accounts created on the website are always customers, so admins are made here
 * (or by another admin under Admin → Users).
 */
class MakeAdmin extends Command
{
    protected $signature = 'user:make-admin {email : Email of an existing account} {--revoke : Make the account a customer again}';

    protected $description = 'Give an existing user access to the admin panel (or take it away with --revoke)';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();

        if (! $user) {
            $this->error("No account uses {$this->argument('email')}. Register it on the website first.");

            return self::FAILURE;
        }

        $role = $this->option('revoke') ? UserRole::Customer : UserRole::Admin;
        $user->forceFill(['role' => $role])->save();

        $this->info("{$user->email} is now: {$role->value}.");

        return self::SUCCESS;
    }
}
