<?php

namespace Tests\Feature;

use App\Enums\TravellerBand;
use App\Services\AgeBandValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ages are counted on the departure date (SC.checkBand in core.js).
 */
class AgeBandValidatorTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private AgeBandValidator $validator;

    private Carbon $departure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        $this->validator = app(AgeBandValidator::class);
        $this->departure = Carbon::parse('2026-11-15');
    }

    public function test_teenagers_book_as_children_until_eighteen(): void
    {
        // 12 to 17 are children now (step 14: adults are 18+)
        $this->assertNull($this->validator->check(TravellerBand::Child, '2014-11-15', $this->departure)); // 12
        $this->assertNull($this->validator->check(TravellerBand::Child, '2008-11-16', $this->departure)); // 17, turns 18 the day after
        $this->assertSame(
            'Adults must be 18 or older on the departure date. Change the date or go back and add this guest as a child.',
            $this->validator->check(TravellerBand::Adult, '2010-03-01', $this->departure), // 16
        );
    }

    public function test_guest_who_is_eighteen_on_departure_day_must_book_as_adult(): void
    {
        $this->assertSame(
            'Children must be 2–17 years old on the departure date.',
            $this->validator->check(TravellerBand::Child, '2008-11-15', $this->departure),
        );
        $this->assertNull($this->validator->check(TravellerBand::Adult, '2008-11-15', $this->departure));
        $this->assertNotNull($this->validator->check(TravellerBand::Adult, '2008-11-16', $this->departure));

        app()->setLocale('ar');
        $this->assertSame(
            'يجب أن يكون عمر الطفل بين 2 و17 سنة في تاريخ المغادرة.',
            $this->validator->check(TravellerBand::Child, '2008-11-15', $this->departure),
        );
    }

    public function test_child_under_two_is_rejected(): void
    {
        $this->assertNull($this->validator->check(TravellerBand::Child, '2024-11-15', $this->departure));
        $this->assertNotNull($this->validator->check(TravellerBand::Child, '2024-11-16', $this->departure));
    }

    public function test_infant_must_be_at_least_six_months_on_departure(): void
    {
        $this->assertNull($this->validator->check(TravellerBand::Infant, '2026-05-15', $this->departure));
        $this->assertSame(
            'Infants must be at least 6 months and under 2 years old on the departure date.',
            $this->validator->check(TravellerBand::Infant, '2026-05-16', $this->departure),
        );
    }

    public function test_infant_under_six_months_gets_the_rule_in_both_languages(): void
    {
        // 5 months, a few weeks and newborn on 15 Nov 2026
        foreach (['2026-05-16', '2026-09-20', '2026-10-01'] as $dob) {
            $this->assertSame(
                'Infants must be at least 6 months and under 2 years old on the departure date.',
                $this->validator->check(TravellerBand::Infant, $dob, $this->departure),
            );
        }

        app()->setLocale('ar');
        $this->assertSame(
            'يجب أن يكون عمر الرضيع 6 أشهر على الأقل وأقل من سنتين في تاريخ المغادرة.',
            $this->validator->check(TravellerBand::Infant, '2026-05-16', $this->departure),
        );
    }

    public function test_infant_age_is_counted_on_the_departure_date_not_today(): void
    {
        // 4 months old today (11 Oct 2026) but 6 months on the 15 Nov 2026 departure: allowed
        $this->assertNull($this->validator->check(TravellerBand::Infant, '2026-05-15', $this->departure));
        // the same baby on an earlier sailing (1 Nov 2026) is 5 months old: rejected
        $this->assertNotNull($this->validator->check(TravellerBand::Infant, '2026-05-15', Carbon::parse('2026-11-01')));
    }

    public function test_infant_must_be_under_two_on_departure(): void
    {
        $this->assertNull($this->validator->check(TravellerBand::Infant, '2024-11-16', $this->departure));
        $this->assertNotNull($this->validator->check(TravellerBand::Infant, '2024-11-15', $this->departure));
    }

    public function test_missing_or_future_date_of_birth(): void
    {
        $this->assertSame('Enter a date of birth.', $this->validator->check(TravellerBand::Adult, null, $this->departure));
        $this->assertSame("Date of birth can't be in the future.", $this->validator->check(TravellerBand::Infant, '2026-10-12', $this->departure));
    }
}
