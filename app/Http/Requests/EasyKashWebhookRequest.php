<?php

namespace App\Http\Requests;

use App\Services\EasyKashPaymentService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

/**
 * EasyKash's server-to-server callback. Only accepted with a valid HMAC signature; answers in JSON (never a redirect).
 */
class EasyKashWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EasyKashPaymentService::class)->verifySignature($this->all());
    }

    public function rules(): array
    {
        return [
            'customerReference' => ['required'],
            'status' => ['required', 'string'],
            'Amount' => ['required', 'numeric'],
            'easykashRef' => ['nullable'],
            'signatureHash' => ['required', 'string'],
        ];
    }

    protected function failedAuthorization(): void
    {
        Log::warning('EasyKash webhook with an invalid signature', ['ip' => $this->ip(), 'customer_reference' => $this->input('customerReference')]);

        throw new HttpResponseException(response()->json(['message' => 'Invalid signature.'], 403));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Invalid payload.', 'errors' => $validator->errors()], 422));
    }
}
