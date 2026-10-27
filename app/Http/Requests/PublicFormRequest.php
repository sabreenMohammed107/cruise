<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Forms anyone on the website can send ("Contact us", "Become an agent").
 *
 * They carry a hidden field (partials.honeypot) that people leave empty; bots that fill every input are
 * answered as if the form was sent, but nothing is saved (the controller checks isBot()).
 *
 * The mobile number comes from the site's phone widget (partials.phone-field): the country code in "dial",
 * the number in "phone", checked with the same rules as the booking form (App\Support\PhoneNumber).
 */
abstract class PublicFormRequest extends FormRequest
{
    public const HONEYPOT = 'company_website';

    /** Whether the form needs a mobile number (otherwise it is optional). */
    protected bool $phoneRequired = false;

    public function authorize(): bool
    {
        return true;
    }

    public function isBot(): bool
    {
        return $this->filled(self::HONEYPOT);
    }

    protected function phoneRules(): array
    {
        return [
            'dial' => [$this->phoneRequired ? 'required' : 'required_with:phone', Rule::in(PhoneNumber::dialCodes())],
            'phone' => [$this->phoneRequired ? 'required' : 'nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'dial.*' => __('site.booking.errors.dial'),
            'phone.required' => __('site.booking.errors.phone_required'),
            'phone.*' => __('site.booking.errors.phone_length'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['dial', 'phone']) || ! $this->filled('phone')) {
                return;
            }
            if ($error = PhoneNumber::error($this->input('dial'), $this->input('phone'))) {
                $validator->errors()->add('phone', $error);
            }
        }];
    }

    /** The validated fields to save, with the number as the site stores it: phone_dial "+966", phone "501234567". */
    public function toSave(): array
    {
        $phone = PhoneNumber::normalise($this->input('phone'));

        return $this->safe()->except(['dial', 'phone']) + [
            'phone_dial' => $phone === '' ? null : $this->input('dial'),
            'phone' => $phone === '' ? null : $phone,
        ];
    }
}
