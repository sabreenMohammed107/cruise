<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A first-time visitor sees the website in Arabic with prices in Egyptian pounds (config/cruise.php
 * default_locale / default_display_currency). The header switches still work and are remembered; the admin panel
 * stays in English. (phpunit.xml sets English / USD for the other tests.)
 */
class DefaultLanguageCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        config(['cruise.default_locale' => 'ar', 'cruise.default_display_currency' => 'EGP']);
    }

    public function test_a_first_visit_is_in_arabic_with_egyptian_pounds(): void
    {
        $page = $this->get('/cruises')->assertOk()->assertSee('<html lang="ar" dir="rtl"', false);

        $data = $page->viewData('scData');
        $this->assertSame('EGP', $data['displayCurrency']);
        $this->assertGreaterThan(1, $data['exchangeRate']);
        $this->assertSame('USD', $data['currency']); // prices are still stored and booked in USD
    }

    public function test_the_visitors_own_choice_wins(): void
    {
        // English button (sc_lang cookie) and USD in the currency switch (session)
        $this->withUnencryptedCookie('sc_lang', 'en')->get('/cruises')->assertOk()->assertSee('<html lang="en" dir="ltr"', false);
        $this->post('/currency', ['currency' => 'USD'])->assertSessionHas('currency', 'USD');
        $this->assertSame('USD', $this->get('/cruises')->viewData('scData')['displayCurrency']);

        // A cookie that is neither language falls back to the default
        $this->withUnencryptedCookie('sc_lang', 'fr')->get('/cruises')->assertSee('<html lang="ar" dir="rtl"', false);
    }

    public function test_a_booking_made_on_the_defaults_is_in_arabic_and_egp(): void
    {
        Mail::fake();
        $ref = $this->postJson('/booking/confirm', [
            'sailing' => 'RSD-261213',
            'cabin' => 'balcony',
            'guests' => ['adult' => 2, 'child' => 0, 'infant' => 0],
            'travellers' => [
                'adult-1' => ['first' => 'Noura', 'last' => 'Alqahtani', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                'adult-2' => ['first' => 'Khalid', 'last' => 'Alqahtani', 'dob' => '1988-07-19', 'gender' => 'Male', 'nationality' => 'Saudi Arabia'],
            ],
            'contact' => ['email' => 'noura.default@example.com', 'dial' => '+966', 'phone' => '055 123 4567'],
            'promoCode' => null,
            'agreed' => true,
        ])->assertCreated()->json('reference');

        $booking = Booking::where('reference', $ref)->sole();
        $this->assertSame('ar', $booking->locale);
        $this->assertSame('EGP', $booking->display_currency);
        $this->assertSame('USD', $booking->currency);
    }

    public function test_the_admin_panel_stays_in_english(): void
    {
        $admin = \App\Models\User::where('email', 'admin@safercruise.test')->firstOrFail();
        $this->actingAs($admin, 'admin')->get('/admin')->assertOk()->assertSee('Next departures');
        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_defaults_can_be_changed_back(): void
    {
        config(['cruise.default_locale' => 'en', 'cruise.default_display_currency' => 'USD']);

        $page = $this->get('/cruises')->assertOk()->assertSee('<html lang="en" dir="ltr"', false);
        $this->assertSame('USD', $page->viewData('scData')['displayCurrency']);
    }
}
