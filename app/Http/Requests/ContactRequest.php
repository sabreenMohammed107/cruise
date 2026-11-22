<?php

namespace App\Http\Requests;

/**
 * "Contact us" form (pages.contact). The mobile number is required: left empty it gets the same
 * "The … field is required." line as the other fields; its other messages are worded in pages.contact.errors.
 */
class ContactRequest extends PublicFormRequest
{
    protected bool $phoneRequired = true;

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

    protected function phoneMessage(string $key): string
    {
        if ($key === 'phone_required') {
            return __('validation.required', ['attribute' => __('pages.contact.fields.phone')]);
        }

        return trans()->has("pages.contact.errors.$key") ? __("pages.contact.errors.$key") : parent::phoneMessage($key);
    }
}
