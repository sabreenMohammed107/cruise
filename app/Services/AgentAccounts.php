<?php

namespace App\Services;

use App\Enums\AgentRequestStatus;
use App\Enums\UserRole;
use App\Models\AgentApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns an approved "Become an agent" application into a new website account with the role "agent"
 * (Admin\AgentRequestController). Agents sign in on the website like customers, never in the admin panel.
 * Every agent has an email of their own: no customer or staff account becomes an agent.
 */
class AgentAccounts
{
    /**
     * A new agent account with a temporary password. Emails are unique: an email that already has an account
     * (a customer, staff…) is refused, and no account is ever turned into an agent. The application is marked
     * accepted and linked to the account.
     *
     * @param  ?int  $tierId  the agent type (Standard, Gold, …), null for none
     * @return array{0: User, 1: string} the account and its temporary password
     *
     * @throws ValidationException when the email already has an account
     */
    public function approve(AgentApplication $application, ?int $tierId = null): array
    {
        return DB::transaction(function () use ($application, $tierId) {
            $email = strtolower(trim($application->email));

            // The form checks this too (AgentRequest); the email may have got an account since the application
            $existing = User::whereRaw('lower(email) = ?', [$email])->lockForUpdate()->first();
            if ($existing) {
                $kind = $existing->isStaff() ? 'a staff account (Admins & staff)' : ($existing->isAgent() ? 'an agent account' : 'a customer account');
                throw ValidationException::withMessages([
                    'approve' => "{$application->email} already has {$kind}. Agent accounts need an email of their own: ask the applicant to apply again with another email.",
                ]);
            }

            [$first, $last] = $this->splitName($application->contact_name);
            $password = $this->temporaryPassword();
            $user = new User([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone_dial' => $application->phone_dial,
                'phone' => $application->phone,
                'password' => $password,
            ]);

            $user->company_name = $application->company_name;
            // Not mass assignable (UserResource::fill, AgentResource::fill)
            $user->role = UserRole::Agent;
            $user->agent_tier_id = $tierId;
            $user->save();

            $application->forceFill([
                'status' => AgentRequestStatus::Accepted,
                'user_id' => $user->id,
                'approved_at' => now(),
            ])->save();

            return [$user, $password];
        });
    }

    /** A new temporary password for an agent who lost theirs or never got the email. */
    public function resetPassword(User $user): string
    {
        $password = $this->temporaryPassword();
        $user->password = $password;
        $user->save();

        return $password;
    }

    /** 12 letters and digits (at least one of each): passes the account password rules and is easy to type. */
    private function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }

    /** "Omar Al Saleh" → ["Omar", "Al Saleh"]; one word → [word, null]. */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2);

        return [$parts[0], $parts[1] ?? null];
    }
}
