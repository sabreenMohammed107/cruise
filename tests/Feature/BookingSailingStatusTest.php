<?php

namespace Tests\Feature;

use App\Enums\SailingStatus;
use App\Http\Requests\ConfirmBookingRequest;
use App\Models\Booking;
use App\Models\Sailing;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Only open, upcoming sailings of a live cruise can be booked: a closed, cancelled or archived sailing is refused
 * by POST /booking/confirm (ConfirmBookingRequest) and again by BookingService, even from a direct link.
 */
class BookingSailingStatusTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const CODE = 'RSD-261213'; // departs 13 Dec 2026

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        $this->withoutMiddleware(ThrottleRequests::class); // more than the route's 10 posts a minute
        Mail::fake();
    }

    private function payload(): array
    {
        return [
            'sailing' => self::CODE,
            'cabin' => 'balcony',
            'guests' => ['adult' => 2, 'child' => 0, 'infant' => 0],
            'travellers' => [
                'adult-1' => ['first' => 'Noura', 'last' => 'Alqahtani', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                'adult-2' => ['first' => 'Khalid', 'last' => 'Alqahtani', 'dob' => '1988-07-19', 'gender' => 'Male', 'nationality' => 'Saudi Arabia'],
            ],
            'contact' => ['email' => 'noura@example.com', 'dial' => '+966', 'phone' => '055 123 4567'],
            'agreed' => true,
        ];
    }

    private function sailing(): Sailing
    {
        return Sailing::where('code', self::CODE)->sole();
    }

    private function assertRefused(): void
    {
        $before = Booking::count();
        $this->postJson('/booking/confirm', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sailing' => __('site.booking.errors.sailing_gone')]);
        $this->assertSame($before, Booking::count());
    }

    public function test_an_open_upcoming_sailing_can_be_booked(): void
    {
        $this->assertTrue($this->sailing()->isBookable());
        $this->postJson('/booking/confirm', $this->payload())->assertCreated();
    }

    public function test_a_closed_sailing_is_refused(): void
    {
        $this->sailing()->update(['status' => SailingStatus::Closed]);
        $this->assertRefused();
    }

    public function test_a_cancelled_sailing_is_refused(): void
    {
        $this->sailing()->update(['status' => SailingStatus::Cancelled]);
        $this->assertRefused();
    }

    public function test_an_archived_sailing_is_refused_even_while_still_open(): void
    {
        // Archived from 00:00 on the departure day, before sailings:close marks it closed
        $this->travelTo('2026-12-13 00:00:01');
        $this->assertSame(SailingStatus::Open, $this->sailing()->status);
        $this->assertRefused();

        $this->travelTo('2026-12-20');
        $this->assertRefused();
    }

    public function test_a_sailing_of_a_hidden_or_deleted_cruise_is_refused(): void
    {
        $this->sailing()->cruise->update(['is_active' => false]);
        $this->assertRefused();

        $this->sailing()->cruise->update(['is_active' => true]);
        $this->sailing()->cruise->delete();
        $this->assertRefused();
    }

    public function test_an_unknown_sailing_code_is_refused(): void
    {
        $this->postJson('/booking/confirm', ['sailing' => 'NOPE-000000'] + $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sailing' => __('site.booking.errors.sailing_gone')]);
    }

    public function test_a_sailing_closed_after_validation_is_refused_by_the_service(): void
    {
        // The admin closes the sailing between the request's checks and the save
        $this->app->afterResolving(ConfirmBookingRequest::class, fn () => $this->sailing()->update(['status' => SailingStatus::Closed]));

        $this->assertRefused();
    }

    public function test_the_service_refuses_a_closed_sailing_when_called_directly(): void
    {
        $sailing = $this->sailing();
        $price = $sailing->cabinPrices()->firstOrFail();
        $sailing->update(['status' => SailingStatus::Closed]);

        try {
            app(BookingService::class)->confirm(
                cabins: [['price' => $price, 'guests' => ['adult' => 1, 'child' => 0, 'infant' => 0], 'travellers' => []]],
                contact: ['email' => 'noura@example.com', 'dial' => '+966', 'phone' => '551234567'],
            );
            $this->fail('A closed sailing was booked.');
        } catch (ValidationException $e) {
            $this->assertSame([__('site.booking.errors.sailing_gone')], $e->errors()['sailing']);
        }
        $this->assertSame(0, Booking::where('sailing_id', $sailing->id)->count());
    }

    public function test_the_message_is_translated(): void
    {
        app()->setLocale('ar');
        $this->assertNotSame(trans('site.booking.errors.sailing_gone', [], 'en'), __('site.booking.errors.sailing_gone'));
    }
}
