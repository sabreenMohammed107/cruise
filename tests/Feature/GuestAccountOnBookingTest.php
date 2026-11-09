<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\BookingConfirmed;
use App\Mail\NewBookingForTeam;
use App\Models\Booking;
use App\Models\User;
use App\Services\GuestAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * A guest booking with an email that has no account makes a customer account, linked to the booking, and the
 * booking email carries its sign-in details (App\Services\GuestAccounts). Existing accounts are never touched (D10).
 */
class GuestAccountOnBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
    }

    /** RSD-261213, balcony, 2 adults (same party as BookingFlowTest). */
    private function payload(string $email = 'Noura.New@Example.com'): array
    {
        return [
            'sailing' => 'RSD-261213',
            'cabin' => 'balcony',
            'guests' => ['adult' => 2, 'child' => 0, 'infant' => 0],
            'travellers' => [
                'adult-1' => ['first' => 'Noura', 'last' => 'Alqahtani', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                'adult-2' => ['first' => 'Khalid', 'last' => 'Alqahtani', 'dob' => '1988-07-19', 'gender' => 'Male', 'nationality' => 'Saudi Arabia'],
            ],
            'contact' => ['email' => $email, 'dial' => '+966', 'phone' => '055 123 4567'],
            'promoCode' => null,
            'agreed' => true,
        ];
    }

    private function book(array $payload, ?string $lang = null): Booking
    {
        $request = $lang ? $this->withUnencryptedCookie('sc_lang', $lang)->withCredentials() : $this;
        $ref = $request->postJson('/booking/confirm', $payload)->assertCreated()->json('reference');

        return Booking::where('reference', $ref)->sole();
    }

    private function guestMail(Booking $booking): BookingConfirmed
    {
        $found = null;
        Mail::assertSent(BookingConfirmed::class, function (BookingConfirmed $m) use ($booking, &$found) {
            return $m->booking->is($booking) && ($found = $m);
        });

        return $found;
    }

    private function teamMail(Booking $booking): NewBookingForTeam
    {
        $found = null;
        Mail::assertSent(NewBookingForTeam::class, function (NewBookingForTeam $m) use ($booking, &$found) {
            return $m->booking->is($booking) && ($found = $m);
        });

        return $found;
    }

    public function test_a_guest_booking_makes_an_account_and_emails_the_sign_in_details(): void
    {
        $booking = $this->book($this->payload());

        // A customer account from the primary guest and the contact details, linked to the booking
        $user = User::where('email', 'noura.new@example.com')->sole();
        $this->assertSame(UserRole::Customer, $user->fresh()->role);
        $this->assertSame(['Noura', 'Alqahtani', '+966', $booking->contact_phone, '1991-03-02', 'Saudi Arabia'],
            [$user->first_name, $user->last_name, $user->phone_dial, $user->phone, $user->date_of_birth->toDateString(), $user->nationality]);
        $this->assertSame($user->id, $booking->user_id);

        // The guest email welcomes them and gives the email and temporary password; only the hash is stored
        $mail = $this->guestMail($booking);
        $mail->assertTo('noura.new@example.com');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $mail->password);
        $this->assertNotSame($mail->password, $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check($mail->password, $user->password));
        $html = $mail->render();
        foreach ([__('emails.new_account_title'), 'noura.new@example.com', $mail->password, route('login'), e(__('emails.new_account_change_password')),
            $booking->reference, 'Balcony Cabin'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }

        // The team sees that the account was made with this booking; their email has no password
        $team = $this->teamMail($booking)->render();
        $this->assertStringContainsString(__('emails.team_account_created'), $team);
        $this->assertStringNotContainsString($mail->password, $team);

        // The guest signs in with it and finds the booking in their account
        $this->post('/login', ['email' => 'noura.new@example.com', 'password' => $mail->password])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user, 'web');
        $this->get(route('account'))->assertOk()->assertSee($booking->reference);
    }

    public function test_the_welcome_is_in_the_language_the_guest_booked_in(): void
    {
        $booking = $this->book($this->payload(), 'ar');

        $mail = $this->guestMail($booking);
        $this->assertSame('ar', $mail->locale);
        $html = $mail->render();
        $this->assertStringContainsString(e(trans('emails.new_account_title', [], 'ar')), $html);
        $this->assertStringContainsString(e(trans('emails.new_account_button', [], 'ar')), $html);
        $this->assertStringContainsString($mail->password, $html);
    }

    public function test_an_email_that_already_has_an_account_is_left_alone(): void
    {
        $reem = User::where('email', 'reem@example.com')->sole();
        $hash = $reem->password;
        $users = User::count();

        // Booked signed out with Reem's email (any case): not added to her account, no new account, no password sent
        $booking = $this->book($this->payload('REEM@example.com'));

        $this->assertNull($booking->user_id);
        $this->assertSame($users, User::count());
        $this->assertSame($hash, $reem->fresh()->password);
        $mail = $this->guestMail($booking);
        $this->assertNull($mail->password);
        $this->assertStringNotContainsString(e(__('emails.new_account_title')), $mail->render());
        $this->assertStringContainsString(__('emails.guest_checkout'), $this->teamMail($booking)->render());
    }

    public function test_a_signed_in_customer_books_on_their_own_account(): void
    {
        $reem = User::where('email', 'reem@example.com')->sole();
        $users = User::count();

        // Even with another contact email: no account is made for it
        $booking = $this->actingAs($reem, 'web')->book($this->payload('someone.else@example.com'));

        $this->assertSame($reem->id, $booking->user_id);
        $this->assertSame($users, User::count());
        $this->assertNull($this->guestMail($booking)->password);
    }

    public function test_a_second_guest_booking_with_the_same_email_makes_no_second_account(): void
    {
        $first = $this->book($this->payload());
        $second = $this->book($this->payload('noura.new@example.com'));

        $this->assertSame(1, User::where('email', 'noura.new@example.com')->count());
        $this->assertNotNull($first->user_id);
        // The address isn't verified: the second booking is not added to the account (D10), and no new password is sent
        $this->assertNull($second->user_id);
        $this->assertNull($this->guestMail($second)->password);
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['cruise.guest_accounts' => false]);

        $booking = $this->book($this->payload());

        $this->assertNull($booking->user_id);
        $this->assertFalse(User::where('email', 'noura.new@example.com')->exists());
        $this->assertNull($this->guestMail($booking)->password);
    }

    public function test_a_failure_making_the_account_never_stops_the_booking(): void
    {
        $this->mock(GuestAccounts::class)->shouldReceive('createFor')->andThrow(new RuntimeException('database hiccup'));

        $booking = $this->book($this->payload());

        $this->assertNull($booking->user_id);
        $this->assertNull($this->guestMail($booking)->password);
        Mail::assertSent(NewBookingForTeam::class);
    }
}
