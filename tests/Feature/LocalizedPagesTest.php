<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Step 10: sign in, register, booking, confirmation and account follow the language of the
 * header's عربي button (sc_lang cookie), in Blade, in SC_DATA.i18n and in server messages.
 */
class LocalizedPagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
    }

    // withCredentials(): JSON requests in tests only carry cookies when asked (fetch() in the browser sends them)
    private function inArabic(): static
    {
        return $this->withCredentials()->withUnencryptedCookie('sc_lang', 'ar');
    }

    private function inEnglish(): static
    {
        return $this->withCredentials()->withUnencryptedCookie('sc_lang', 'en');
    }

    /** The page without SC_DATA, which carries the words of both languages for the scripts. */
    private function assertNoEnglish(\Illuminate\Testing\TestResponse $response, string ...$texts): void
    {
        $html = preg_replace('#<script>window\.SC_DATA = .*?</script>#s', '', $response->getContent());
        foreach ($texts as $text) {
            $this->assertStringNotContainsString(e($text), $html);
        }
    }

    public function test_sign_in_and_register_switch_language_and_direction(): void
    {
        $this->inEnglish()->get('/login')->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('<title>Sign in | Safer Cruise</title>', false)
            ->assertSee('>Email</label>', false)->assertSee('Keep me signed in')->assertSee('Create an account');

        $login = $this->inArabic()->get('/login')->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('<title>تسجيل الدخول | Safer Cruise</title>', false)
            ->assertSee('>البريد الإلكتروني</label>', false)->assertSee('تذكّرني')->assertSee('إنشاء حساب');
        $this->assertNoEnglish($login, 'Keep me signed in', 'See your bookings', 'New to Safer Cruise?');
        // email and password are typed left to right on the Arabic page
        $this->assertMatchesRegularExpression('/<input id="email"[^>]*dir="ltr"/', $login->getContent());
        $this->assertMatchesRegularExpression('/<input id="password"[^>]*dir="ltr"/', $login->getContent());

        $register = $this->inArabic()->get('/register')->assertOk()
            ->assertSee('<title>إنشاء حساب | Safer Cruise</title>', false)
            ->assertSee('>الاسم الأول</label>', false)->assertSee('>تأكيد كلمة المرور</label>', false)
            ->assertSee('8 أحرف على الأقل، منها رقم واحد.')->assertSee('لديك حساب بالفعل؟');
        $this->assertNoEnglish($register, 'First name', 'Repeat password', 'Already have an account?');
    }

    public function test_sign_in_and_register_errors_are_in_arabic(): void
    {
        $this->inArabic()->post('/login', ['email' => 'reem@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'البريد الإلكتروني وكلمة المرور لا يطابقان أي حساب.']);

        // Laravel's own rules use lang/ar/validation.php
        $this->inArabic()->post('/login', [])
            ->assertSessionHasErrors(['email' => 'حقل البريد الإلكتروني مطلوب.', 'password' => 'حقل كلمة المرور مطلوب.']);

        $this->inArabic()->post('/register', ['first_name' => 'Reem', 'last_name' => 'A', 'email' => 'reem@example.com', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])
            ->assertSessionHasErrors([
                'email' => 'يوجد حساب بهذا البريد الإلكتروني. سجّل الدخول بدلًا من ذلك.',
                'password' => 'يجب أن تحتوي كلمة المرور على رقم واحد على الأقل.',
            ]);

        $this->inEnglish()->post('/login', ['email' => 'reem@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => "That email and password don't match an account."]);
    }

    public function test_booking_page_and_its_script_words_in_both_languages(): void
    {
        $this->inEnglish()->get('/booking')->assertOk()
            ->assertSee("Who's sailing?")->assertSee('Review & confirm booking')->assertSee('data-terms="terms">terms &amp; conditions</a>', false);

        $ar = $this->inArabic()->get('/booking')->assertOk()
            ->assertSee('<title>احجز رحلتك | Safer Cruise</title>', false)
            ->assertSee('من سيسافر؟')->assertSee('مراجعة الحجز وتأكيده')->assertSee('data-terms="terms">الشروط والأحكام</a>', false);
        $this->assertNoEnglish($ar, "Who's sailing?", 'Booking summary', 'Confirm booking', 'Back to cabins');

        // booking.js and SC.checkBand read these through SC.t()
        $i18n = $ar->viewData('scData')['i18n'];
        $this->assertSame('أدخل تاريخ الميلاد.', $i18n['ar']['booking']['errors']['dob_required']);
        $this->assertSame('Enter a date of birth.', $i18n['en']['booking']['errors']['dob_required']);
        $this->assertSame('أنثى', $i18n['ar']['genders']['Female']);
        $this->assertSame('السعودية', $i18n['ar']['nationalities']['Saudi Arabia']);
        $this->assertSame(config('cruise.nationalities'), array_keys($i18n['ar']['nationalities']));
    }

    private function bookingPayload(array $override = []): array
    {
        return array_replace_recursive([
            'sailing' => 'RSD-261213', 'cabin' => 'balcony',
            'guests' => ['adult' => 1, 'child' => 0, 'infant' => 0],
            'travellers' => ['adult-1' => ['first' => 'نورة', 'last' => 'القحطاني', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia']],
            'contact' => ['email' => 'noura@example.com', 'dial' => '+966', 'phone' => '551234567'],
            'promoCode' => null, 'agreed' => true,
        ], $override);
    }

    public function test_booking_errors_from_the_server_are_in_arabic(): void
    {
        $this->inArabic()->postJson('/booking/confirm', $this->bookingPayload([
            'travellers' => ['adult-1' => ['dob' => '2020-01-01']],
            'contact' => ['phone' => '123'],
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'cabins.0.travellers.adult-1.dob' => 'بالغ 1: يجب أن يكون عمر البالغ 18 سنة أو أكثر في تاريخ المغادرة. غيّر التاريخ أو ارجع وأضف هذا الضيف كطفل.',
            'contact.phone' => 'أرقام الجوال السعودية من 9 أرقام وتبدأ بـ 5.',
        ]);

        $this->inArabic()->postJson('/booking/promo', ['code' => 'NOPE'])->assertUnprocessable()
            ->assertJsonPath('message', 'هذا الرمز غير صالح لهذه الرحلة.');
    }

    public function test_confirmation_voucher_in_arabic(): void
    {
        $url = $this->inArabic()->postJson('/booking/confirm', $this->bookingPayload())->assertCreated()->json('redirect');

        $voucher = $this->inArabic()->get($url)->assertOk()
            ->assertSee('<title>تم تأكيد الحجز | Safer Cruise</title>', false)
            ->assertSee('تم حجز رحلتك')->assertSee('رقم الحجز')->assertSee('مؤكد')
            ->assertSee('13 ديسمبر 2026')                 // month name in Arabic
            ->assertSee('7 ليالٍ')->assertSee('بالغ واحد')
            ->assertSee('السعودية')->assertSee('تحميل القسيمة')
            ->assertSee('<b class="text-ink" dir="ltr">noura@example.com</b>', false);
        $this->assertNoEnglish($voucher, 'Your cruise is booked', 'Booking reference', 'Download voucher', 'Saudi Arabia');

        $this->inEnglish()->get($url)->assertOk()->assertSee('Your cruise is booked')->assertSee('13 Dec 2026')->assertSee('1 adult');
    }

    public function test_account_page_in_arabic(): void
    {
        $reem = User::where('email', 'reem@example.com')->firstOrFail();

        $account = $this->actingAs($reem)->inArabic()->get('/account')->assertOk()
            ->assertSee('<title>حسابي | Safer Cruise</title>', false)
            ->assertSee('مرحبًا بعودتك')->assertSee('الملف الشخصي والإعدادات')->assertSee('تغيير كلمة المرور');
        $this->assertNoEnglish($account, 'Welcome back', 'Profile & settings', 'Change password', 'Save changes');

        $this->actingAs($reem)->inArabic()->putJson('/account/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertUnprocessable()->assertJsonPath('errors.current_password.0', 'هذه ليست كلمة المرور الحالية.');
    }
}
