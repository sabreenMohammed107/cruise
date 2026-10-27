<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * "Personal details" on /account (dashboard.js). Same rules as the browser check there; names and phone
 * follow the booking form (ConfirmBookingRequest, App\Support\PhoneNumber), so a saved profile always
 * fills in a valid booking.
 */
class UpdateProfileRequest extends FormRequest
{
    /** The account holder books as an adult; the date input gets the same limits (account/dashboard.blade.php). */
    public const MIN_AGE = 18;

    public const MAX_AGE = 120;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first' => trim((string) $this->input('first')),
            'last' => trim((string) $this->input('last')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => PhoneNumber::normalise($this->input('phone')) ?: null,
            'dial' => $this->input('dial') ?: null,
            'dob' => $this->input('dob') ?: null,
            'nationality' => $this->input('nationality') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'first' => ['required', 'string', 'max:50', 'regex:'.ConfirmBookingRequest::NAME_PATTERN],
            'last' => ['required', 'string', 'max:50', 'regex:'.ConfirmBookingRequest::NAME_PATTERN],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'dial' => ['nullable', 'required_with:phone', Rule::in(PhoneNumber::dialCodes())],
            'phone' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.self::latestDob(), 'after:'.self::earliestDob()],
            'nationality' => ['nullable', Rule::in(config('cruise.nationalities'))],
        ];
    }

    public function messages(): array
    {
        return [
            'first.*' => __('site.account.first_required'),
            'last.*' => __('site.account.last_required'),
            'email.unique' => __('site.account.email_taken'),
            'email.*' => __('site.booking.errors.email_invalid'),
            'dial.*' => __('site.booking.errors.dial'),
            'phone.*' => __('site.booking.errors.phone_length'),
            'dob.before_or_equal' => __('site.account.dob_too_young', ['min' => self::MIN_AGE]),
            'dob.after' => __('site.account.dob_invalid'),
            'dob.*' => __('site.account.dob_invalid'),
            'nationality.*' => __('site.booking.errors.nationality'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->filled('phone') && ! $validator->errors()->hasAny(['dial', 'phone'])
                && ($error = PhoneNumber::error($this->input('dial'), $this->input('phone')))) {
                $validator->errors()->add('phone', $error);
            }
        }];
    }

    /** Columns of the users table. */
    public function toSave(): array
    {
        $data = $this->validated();

        return [
            'first_name' => $data['first'],
            'last_name' => $data['last'],
            'email' => $data['email'],
            'phone_dial' => $data['phone'] ? $data['dial'] : null,
            'phone' => $data['phone'],
            'date_of_birth' => $data['dob'],
            'nationality' => $data['nationality'],
        ];
    }

    /** Born on or before this day = at least MIN_AGE today. */
    public static function latestDob(): string
    {
        return now()->subYears(self::MIN_AGE)->toDateString();
    }

    public static function earliestDob(): string
    {
        return now()->subYears(self::MAX_AGE)->toDateString();
    }
}
