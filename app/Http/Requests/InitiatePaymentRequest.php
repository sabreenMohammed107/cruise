<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Http\Controllers\BookingController;
use App\Models\Booking;
use App\Services\EasyKashPaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * "Pay online" on the booking confirmation page: whoever may see the booking may pay what it still owes.
 */
class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BookingController::canView($this, $this->booking());
    }

    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $booking = $this->booking();

                $message = match (true) {
                    ! app(EasyKashPaymentService::class)->enabled() => __('site.payment.errors.unavailable'),
                    $booking->status === BookingStatus::Cancelled => __('site.payment.errors.cancelled'),
                    $booking->amountDue() <= 0 => __('site.payment.errors.nothing_due'),
                    default => null,
                };

                if ($message) {
                    $validator->errors()->add('payment', $message);
                }
            },
        ];
    }

    public function booking(): Booking
    {
        return $this->route('booking');
    }
}
