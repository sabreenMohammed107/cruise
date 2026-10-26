<?php

namespace Tests\Feature;

use App\Admin\Options;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Step 25: every country of the phone widget (intl-tel-input 17.0.19, as on safer.travel) can be used.
 */
class PhoneCodesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function booking(string $dial, string $phone): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/booking/confirm', [
            'sailing' => 'RSD-261213', 'cabin' => 'interior', 'guests' => ['adult' => 1, 'child' => 0, 'infant' => 0],
            'travellers' => ['adult-1' => ['first' => 'Kenji', 'last' => 'Sato', 'dob' => '1985-02-03', 'gender' => 'Male', 'nationality' => 'Other']],
            'contact' => ['email' => 'kenji@example.com', 'dial' => $dial, 'phone' => $phone], 'promoCode' => null, 'agreed' => true,
        ]);
    }

    public function test_the_list_has_every_country_of_the_widget(): void
    {
        $codes = config('cruise.dial_codes');
        $this->assertCount(244, $codes);
        $this->assertContains(['+966', 'SA', 'Saudi Arabia', 0], $codes);
        $this->assertContains(['+81', 'JP', 'Japan', 0], $codes);
        $this->assertContains(['+1', 'CA', 'Canada', 1], $codes);
        $this->assertSame(['sa', 'ae', 'eg', 'kw', 'qa', 'bh', 'om'], config('cruise.phone_preferred_countries'));
    }

    public function test_bookings_and_profiles_accept_any_listed_code(): void
    {
        $this->travelTo('2026-10-11');
        Mail::fake();

        $ref = $this->booking('+81', '9012345678')->assertCreated()->json('reference');
        $this->assertSame('+81', Booking::where('reference', $ref)->value('contact_dial'));
        $this->booking('+999', '9012345678')->assertUnprocessable()->assertJsonValidationErrors('contact.dial');

        $reem = User::where('email', 'reem@example.com')->firstOrFail();
        $profile = ['first' => 'Reem', 'last' => 'Alotaibi', 'email' => 'reem@example.com', 'dial' => '+1', 'phone' => '4155550123', 'dob' => '1990-04-12', 'nationality' => 'Saudi Arabia'];
        $this->actingAs($reem)->putJson('/account/profile', $profile)->assertOk();
        $this->assertSame('+1', $reem->fresh()->phone_dial);
    }

    public function test_pages_get_the_widget_settings_not_the_whole_list(): void
    {
        $data = $this->get('/booking')->assertOk()->assertSee('intl-tel-input/17.0.19/js/intlTelInput.min.js', false)->viewData('scData');
        $this->assertSame(['initialCountry' => 'sa', 'preferredCountries' => ['sa', 'ae', 'eg', 'kw', 'qa', 'bh', 'om']], $data['phone']);
        $this->assertArrayNotHasKey('dialCodes', $data);
    }

    public function test_admin_choices_share_one_entry_per_code(): void
    {
        $options = Options::dialCodes();
        $this->assertSame('+966', array_key_first($options));                    // the widget's first country
        $this->assertSame('+966 Saudi Arabia', $options['+966']);
        $this->assertSame(['+966', '+971', '+20', '+965', '+974', '+973', '+968'], array_slice(array_keys($options), 0, 7));
        $this->assertSame('+1 United States, Canada, Dominican Republic, …', $options['+1']);   // main country first
        $this->assertCount(206, $options);                                       // one per code
    }
}
