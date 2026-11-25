<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\BookingController;
use App\Mail\BookingCancelled;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A new booking is held 24 hours (bookings.hold_until, config cruise.hold_hours): nothing paid by then, it is
 * cancelled automatically (bookings:cancel-unpaid, App\Services\UnpaidBookings) and the guest is emailed.
 */
class BookingHoldTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Riyadh (app.timezone) is an hour ahead of Cairo in winter: the hold is shown in Cairo time
        $this->travelTo('2026-11-30 10:00:00');
        Mail::fake();
    }

    private function book(string $locale = 'en'): Booking
    {
        $payload = [
            'sailing' => 'RSD-261213',
            'cabins' => [['cabin' => 'balcony', 'guests' => ['adult' => 2, 'child' => 0, 'infant' => 0], 'travellers' => [
                'adult-1' => ['first' => 'Noura', 'last' => 'Alqahtani', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                'adult-2' => ['first' => 'Khalid', 'last' => 'Alqahtani', 'dob' => '1988-07-19', 'gender' => 'Male', 'nationality' => 'Saudi Arabia'],
            ]]],
            'contact' => ['email' => 'noura@example.com', 'dial' => '+966', 'phone' => '055 123 4567'],
            'agreed' => true,
        ];
        $request = $locale === 'ar' ? $this->withCredentials()->withUnencryptedCookie('sc_lang', 'ar') : $this;
        $ref = $request->postJson('/booking/confirm', $payload)->assertCreated()->json('reference');

        return Booking::where('reference', $ref)->firstOrFail();
    }

    private function pay(Booking $booking, float $amount): void
    {
        Payment::create(['booking_id' => $booking->id, 'type' => 'payment', 'method' => 'bank_transfer', 'amount' => $amount, 'currency' => 'USD', 'received_at' => now()]);
        $booking->syncPaymentTotals();
    }

    public function test_a_new_booking_is_held_24_hours_and_says_so(): void
    {
        $booking = $this->book();

        $this->assertSame('2026-12-01 10:00:00', $booking->hold_until->format('Y-m-d H:i:s'));
        $this->get(BookingController::signedConfirmationUrl($booking))->assertOk()
            ->assertSee('This booking is held for 24 hours, until 1 Dec 2026, 09:00 Cairo time. If no payment is received by then, it is cancelled automatically.');
        Mail::assertSent(BookingConfirmed::class, fn ($m) => str_contains($m->render(), 'Your booking is held for 24 hours, until 1 Dec 2026, 09:00 Cairo time.'));

        $admin = User::where('email', 'admin@safercruise.test')->firstOrFail();
        $this->actingAs($admin, 'admin')->get(route('admin.bookings.show', $booking))->assertSee('Held 24 hours, until 1 Dec 2026, 09:00 Cairo time');

        // Paid: no more hold
        $this->pay($booking, 500);
        $this->get(BookingController::signedConfirmationUrl($booking))->assertDontSee('until 1 Dec 2026, 09:00');
    }

    public function test_an_unpaid_booking_is_cancelled_when_the_hold_is_over_and_the_guest_is_emailed(): void
    {
        $unpaid = $this->book();
        $partPaid = $this->book();
        $this->pay($partPaid, 100);
        // A booking from before the hold (no hold_until) is never cancelled this way
        $older = $this->book();
        $older->forceFill(['hold_until' => null])->save();

        // Not yet
        $this->travelTo('2026-12-01 09:59:00');
        $this->artisan('bookings:cancel-unpaid')->assertSuccessful();
        $this->assertSame(BookingStatus::Confirmed, $unpaid->fresh()->status);

        $this->travelTo('2026-12-01 10:00:00');
        $this->artisan('bookings:cancel-unpaid')->expectsOutputToContain('1 unpaid booking(s) cancelled: '.$unpaid->reference)->assertSuccessful();

        $unpaid->refresh();
        $this->assertSame(BookingStatus::Cancelled, $unpaid->status);
        $this->assertSame('2026-12-01 10:00', $unpaid->cancelled_at->format('Y-m-d H:i'));
        $this->assertSame(PaymentStatus::Unpaid, $unpaid->payment_status);
        $this->assertStringContainsString('Cancelled automatically: nothing paid within 24 hours of booking', $unpaid->admin_notes);
        $this->assertSame(BookingStatus::Confirmed, $partPaid->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $older->fresh()->status);

        Mail::assertSent(BookingCancelled::class, 1);
        Mail::assertSent(BookingCancelled::class, function (BookingCancelled $m) use ($unpaid) {
            $html = $m->render();

            return $m->hasTo('noura@example.com') && $m->booking->is($unpaid)
                && $m->envelope()->subject === "Your booking {$unpaid->reference} was cancelled"
                && str_contains($html, 'within 24 hours of booking, so it was cancelled automatically');
        });
    }

    public function test_the_cancellation_email_is_in_the_booking_language_and_runs_once(): void
    {
        $booking = $this->book('ar');
        $this->get(BookingController::signedConfirmationUrl($booking))->assertOk()
            ->assertSee('الحجز معلّق لمدة 24 ساعة، حتى 1 ديسمبر 2026 الساعة 09:00 بتوقيت القاهرة. إذا لم يتم الدفع خلال هذه المدة، يُلغى الحجز تلقائيًا.');
        $this->travelTo('2026-12-01 11:00:00');
        $this->artisan('bookings:cancel-unpaid')->assertSuccessful();
        $this->artisan('bookings:cancel-unpaid')->expectsOutputToContain('0 unpaid booking(s) cancelled')->assertSuccessful();

        Mail::assertSent(BookingCancelled::class, 1);
        Mail::assertSent(BookingCancelled::class, fn ($m) => $m->envelope()->subject === "تم إلغاء حجزك {$booking->reference}"
            && str_contains($m->render(), 'لم نستلم أي دفعة لهذا الحجز خلال 24 ساعة'));
        // The voucher now says cancelled, with no hold
        $this->get(BookingController::signedConfirmationUrl($booking))->assertOk()->assertDontSee('الحجز معلّق لمدة 24 ساعة');
    }

    public function test_no_hold_when_turned_off(): void
    {
        config(['cruise.hold_hours' => 0]);
        $booking = $this->book();
        $this->assertNull($booking->hold_until);

        $this->travelTo('2026-12-10');
        $this->artisan('bookings:cancel-unpaid')->assertSuccessful();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }
}
