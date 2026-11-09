<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\AgentApplication;
use App\Models\User;
use App\Rules\BusinessEmail;

/**
 * "Become an agent" application (pages.agents). The mobile number is required.
 */
class AgentRequest extends PublicFormRequest
{
    protected bool $phoneRequired = true;

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            // "bail": a gmail.com address gets the "company email" message alone, without the database checks
            'email' => ['bail', 'required', 'string', 'email:rfc,strict', 'max:255', new BusinessEmail, function ($attribute, $value, $fail) {
                // Agent accounts are new accounts: an email that already has one (a customer, staff…) can't apply,
                // nor one with an application still open
                $email = strtolower(trim((string) $value));
                if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
                    $fail(__('pages.agents.email_has_account'));
                } elseif (AgentApplication::open()->whereRaw('lower(email) = ?', [$email])->exists()) {
                    $fail(__('pages.agents.email_already_applied'));
                } elseif (self::domainIsFull(BusinessEmail::domainOf($email))) {
                    $fail(__('pages.agents.domain_taken'));
                }
            }],
            ...$this->phoneRules(),
        ];
    }

    /**
     * The company domain already has its agents (config agents.max_per_domain, 1 by default): agent accounts and
     * applications still open both count, so ali@company.com can't apply while ahmed@company.com is an agent or waiting.
     * A rejected application frees the domain again.
     */
    public static function domainIsFull(?string $domain): bool
    {
        $max = (int) config('agents.max_per_domain', 1);
        if ($domain === null || $max < 1) {
            return false;
        }

        // "%@company.com", with LIKE's own wildcards taken literally ("!" escapes the same on MySQL and SQLite)
        $pattern = '%@'.preg_replace('/[!%_]/', '!$0', $domain);
        $sql = "lower(email) like ? escape '!'";
        $taken = User::where('role', UserRole::Agent)->whereRaw($sql, [$pattern])->count()
            + AgentApplication::open()->whereRaw($sql, [$pattern])->count();

        return $taken >= $max;
    }

    public function attributes(): array
    {
        return collect(['company_name', 'contact_name', 'email', 'phone'])
            ->mapWithKeys(fn ($field) => [$field => __("pages.agents.fields.$field")])
            ->all();
    }
}
