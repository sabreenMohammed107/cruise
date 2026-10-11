<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bookings come from the website only. The team follows up on payment (offline), records
 * payments and refunds here, and cancels bookings when needed.
 */
class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'payment' => ['nullable', Rule::enum(PaymentStatus::class)],
        ]);

        $bookings = Booking::with(['sailing.cruise', 'cabinCategory'])
            ->when($filters['q'] ?? null, function (Builder $query, string $q) {
                $query->where(fn (Builder $w) => $w
                    ->where('reference', 'like', "%{$q}%")
                    ->orWhere('contact_email', 'like', "%{$q}%")
                    ->orWhere('contact_phone', 'like', "%{$q}%")
                    ->orWhereHas('travellers', fn (Builder $t) => $t->where('last_name', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment'] ?? null, fn (Builder $query, string $payment) => $query->where('payment_status', $payment))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'filters'));
    }

    public function show(Booking $booking): View
    {
        $booking->load(['sailing.cruise.ship', 'cabinCategory', 'travellers', 'payments.recordedBy', 'promoCode', 'user']);

        return view('admin.bookings.show', [
            'booking' => $booking,
            'policy' => $this->cancellationRule($booking),
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $booking->update($request->validate(['admin_notes' => ['nullable', 'string', 'max:5000']]));

        return back()->with('status', 'Notes saved.');
    }

    public function cancel(Booking $booking): RedirectResponse
    {
        abort_if($booking->status === BookingStatus::Cancelled, 409);

        $booking->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);
        $booking->syncPaymentTotals();

        return back()->with('status', "Booking {$booking->reference} cancelled. Record any refund below.");
    }

    public function storePayment(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(PaymentType::class)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'received_at' => ['required', 'date', 'before_or_equal:now'],
        ]);

        $booking->payments()->create($data + [
            'currency' => $booking->currency,
            'recorded_by' => $request->user()->id,
        ]);
        $booking->syncPaymentTotals();

        $what = $data['type'] === PaymentType::Refund->value ? 'Refund' : 'Payment';

        return back()->with('status', "{$what} of ".config('cruise.currency_symbol').number_format($data['amount'], 2).' recorded.');
    }

    public function destroyPayment(Booking $booking, Payment $payment): RedirectResponse
    {
        abort_unless($payment->booking_id === $booking->id, 404);

        $payment->delete();
        $booking->syncPaymentTotals();

        return back()->with('status', 'Payment entry removed.');
    }

    /**
     * Which line of the cancellation policy (config/cruise.php) applies today.
     */
    private function cancellationRule(Booking $booking): array
    {
        $days = (int) today()->diffInDays($booking->sailing->departure_date, false);
        $rows = config('cruise.terms.cancellation');

        $row = match (true) {
            $days >= 121 => $rows[0],
            $days >= 61 => $rows[1],
            $days >= 15 => $rows[2],
            default => $rows[3],
        };

        return ['days' => $days, 'when' => $row[0], 'penalty' => $row[1]];
    }
}
