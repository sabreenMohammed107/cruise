<?php

namespace App\Http\Requests;

/**
 * "Contact us" form (pages.contact). The mobile number is optional.
 */
class ContactRequest extends PublicFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255'],
            ...$this->phoneRules(),
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return collect(['name', 'email', 'phone', 'message'])
            ->mapWithKeys(fn ($field) => [$field => __("pages.contact.fields.$field")])
            ->all();
    }
}
