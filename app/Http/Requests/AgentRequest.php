<?php

namespace App\Http\Requests;

use App\Models\AgentApplication;
use App\Models\User;

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
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255', function ($attribute, $value, $fail) {
                // Agent accounts are new accounts: an email that already has one (a customer, staff…) can't apply,
                // nor one with an application still open
                $email = strtolower(trim((string) $value));
                if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
                    $fail(__('pages.agents.email_has_account'));
                } elseif (AgentApplication::open()->whereRaw('lower(email) = ?', [$email])->exists()) {
                    $fail(__('pages.agents.email_already_applied'));
                }
            }],
            ...$this->phoneRules(),
        ];
    }

    public function attributes(): array
    {
        return collect(['company_name', 'contact_name', 'email', 'phone'])
            ->mapWithKeys(fn ($field) => [$field => __("pages.agents.fields.$field")])
            ->all();
    }
}
