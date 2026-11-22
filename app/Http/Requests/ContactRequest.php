<?php

namespace App\Http\Requests;

/**
 * "Contact us" form (pages.contact). The mobile number is required.
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

    /**
     * An empty number gets the same "The … field is required." message as the other fields; the other phone messages
     * come from pages.contact.phone_errors (they name the field like its label, e.g. "رقم الموبايل" in Arabic).
     */
    protected function phoneMessage(string $key): string
    {
        return $key === 'phone_required'
            ? __('validation.required', ['attribute' => __('pages.contact.fields.phone')])
            : __("pages.contact.phone_errors.$key");
    }
}
