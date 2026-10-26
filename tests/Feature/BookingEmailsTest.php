<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmed;
use App\Mail\NewBookingForTeam;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Step 20: both booking emails are written in the website language the guest booked in, and show the booking
 * details (trip, cabins and travellers, price).
 */
class BookingEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
    }

    /** RSD-261213, two cabins: balcony 2 adults + 1 child, interior 1 adult + 1 infant, with SAFER10. */
    private function book(?string $lang = null): Booking
    {
        $payload = [
            'sailing' => 'RSD-261213',
            'cabins' => [
                ['cabin' => 'balcony', 'guests' => ['adult' => 2, 'child' => 1, 'infant' => 0], 'travellers' => [
                    'adult-1' => ['first' => 'Noura', 'last' => 'Alqahtani', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                    'adult-2' => ['first' => 'Khalid', 'last' => 'Alqahtani', 'dob' => '1988-07-19', 'gender' => 'Male', 'nationality' => 'Saudi Arabia'],
                    'child-1' => ['first' => 'Sara', 'last' => 'Alqahtani', 'dob' => '2018-05-01', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                ]],
                ['cabin' => 'interior', 'guests' => ['adult' => 1, 'child' => 0, 'infant' => 1], 'travellers' => [
                    'adult-1' => ['first' => 'Huda', 'last' => 'Saleh', 'dob' => '1960-01-15', 'gender' => 'Female', 'nationality' => 'Egypt'],
                    'infant-1' => ['first' => 'Omar', 'last' => 'Saleh', 'dob' => '2025-12-01', 'gender' => 'Male', 'nationality' => 'Egypt'],
                ]],
            ],
            'contact' => ['email' => 'noura@example.com', 'dial' => '+966', 'phone' => '055 123 4567'],
            'promoCode' => 'safer10',
            'agreed' => true,
        ];
        // withCredentials: JSON test requests only send cookies when asked to (booking.js sends them, same origin)
        $request = $lang ? $this->withUnencryptedCookie('sc_lang', $lang)->withCredentials() : $this;
        $ref = $request->postJson('/booking/confirm', $payload)->assertCreated()->json('reference');

        return Booking::where('reference', $ref)->firstOrFail();
    }

    private function sent(string $mailable): object
    {
        $mail = null;
        Mail::assertSent($mailable, function ($m) use (&$mail) {
            $mail = $m;

            return true;
        });

        return $mail;
    }

    public function test_english_booking_gets_english_emails_with_the_details(): void
    {
        $booking = $this->book();
        $this->assertSame('en', $booking->locale);

        $guest = $this->sent(BookingConfirmed::class);
        $this->assertSame('en', $guest->locale);
        $html = $guest->render();
        $guest->assertHasSubject("Your cruise is booked: {$booking->reference}");
        $this->assertStringContainsString('<html lang="en" dir="ltr">', $html);
        foreach (['Hello Noura,', 'Red Sea Discovery', 'RSD-261213', 'Sunday 13 December 2026', 'Jeddah → Safaga → Sokhna',
            'Cabin 1 · Balcony Cabin', 'Cabin 2 · Interior Cabin', '2 adults + 1 child', '1 adult + 1 infant',
            'Khalid Alqahtani', 'Omar Saleh', 'Infant', 'Primary guest', 'Discount (SAFER10)', '−$1,245', '$11,180', 'Tax included',
            'within 24 hours'] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
        $this->assertStringNotContainsString('Born ', $html); // dates of birth are for the team only

        $team = $this->sent(NewBookingForTeam::class);
        $team->assertHasSubject("New booking {$booking->reference}: arrange payment");
        $html = $team->render();
        foreach (['Booked in', 'English', 'Guest checkout (no account)', 'Amount due', 'Born 15 Jan 1960', 'tel:+966551234567',
            'https://wa.me/966551234567', route('admin.bookings.show', $booking)] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
    }

    public function test_arabic_booking_gets_arabic_emails(): void
    {
        $booking = $this->book('ar');
        $this->assertSame('ar', $booking->locale);

        $guest = $this->sent(BookingConfirmed::class);
        $this->assertSame('ar', $guest->locale);
        $guest->assertHasSubject("تم حجز رحلتك البحرية: {$booking->reference}");
        $html = $guest->render();
        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        foreach (['مرحبًا Noura،', 'اكتشاف البحر الأحمر', 'الأحد 13 ديسمبر 2026', 'جدة ← سفاجا ← السخنة', 'الكابينة 1 · كابينة بلكونة',
            'الضيف الرئيسي', 'الخصم (SAFER10)', 'الإجمالي', 'خلال 24 ساعة'] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
        $this->assertStringNotContainsString('Your cruise is booked', $html);

        $team = $this->sent(NewBookingForTeam::class);
        $this->assertSame('ar', $team->locale);
        $team->assertHasSubject("حجز جديد {$booking->reference}: ترتيب الدفع");
        $this->assertStringContainsString('العربية', $team->render());
    }

    public function test_emails_use_the_footer_contact_settings(): void
    {
        \App\Models\SiteSetting::create(['key' => 'email', 'value' => 'hello@safer.travel']);
        $this->book();

        $this->assertStringContainsString('mailto:hello@safer.travel', $this->sent(BookingConfirmed::class)->render());
    }

    public function test_bookings_made_before_the_language_was_kept_get_english_emails(): void
    {
        $booking = Booking::firstOrFail();
        $booking->forceFill(['locale' => 'xx'])->save();

        $this->assertSame('en', (new BookingConfirmed($booking))->locale);
        $this->assertStringContainsString('Your cruise is booked', (new BookingConfirmed($booking->fresh()))->render());
    }

    public function test_date_sailing_codes_show_as_a_date(): void
    {
        $label = function (string $code, string $lang) {
            app()->setLocale($lang);

            return \App\Mail\BookingEmailData::sailingLabel($code);
        };

        $this->assertSame('3 November 2026', $label('03112026-4', 'en'));
        $this->assertSame('3 نوفمبر 2026', $label('03112026-4', 'ar'));
        $this->assertSame('29 December 2026', $label('29122026', 'en'));
        $this->assertSame('RSD-270214', $label('RSD-270214', 'en'));   // not a date code: as it is
        $this->assertSame('31022026-1', $label('31022026-1', 'en'));   // not a real date
    }

    public function test_header_link_and_trimmed_guest_text(): void
    {
        $this->book('ar');
        $html = $this->sent(BookingConfirmed::class)->render();

        // White in every mail app: on the link, on a span inside it, and in a head rule
        $this->assertStringContainsString('<a href="https://safer.travel" class="sc-white-link" style="color:#FFFFFF !important;', $html);
        $this->assertStringContainsString('<span style="color:#FFFFFF;text-decoration:none;">safer.travel</span>', $html);
        $this->assertStringContainsString('a.sc-white-link, a.sc-white-link span', $html);
        $this->assertStringContainsString('لديك سؤال؟ راسلنا عبر واتساب.', $html);
        $this->assertStringNotContainsString('رُدّ على هذه الرسالة', $html);
        $this->assertStringNotContainsString('الهوية الوطنية', $html);
        $this->assertStringNotContainsString('Reply to this email', trans('emails.questions', [], 'en'));
        $this->assertStringNotContainsString('national ID', trans('emails.next_documents', [], 'en'));
    }

    public function test_email_language_files_have_the_same_keys(): void
    {
        $keys = fn (string $locale) => array_keys(\Illuminate\Support\Arr::dot(trans('emails', [], $locale)));
        $this->assertSame($keys('en'), $keys('ar'));
    }
}
