<?php

namespace App\Http\Requests;

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
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255'],
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
