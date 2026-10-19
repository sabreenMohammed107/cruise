<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 18: footer description, contact details and social links edited in Admin → Footer & contact.
 */
class FooterSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function asAdmin(): static
    {
        return $this->actingAs(User::where('email', 'admin@safercruise.test')->firstOrFail(), 'admin');
    }

    /** Every field with its current value, plus changes. */
    private function form(array $changes = []): array
    {
        $form = [];
        foreach (SiteSetting::fields() as $key => $field) {
            $form[$key] = SiteSetting::value($key);
            if ($field['bi'] ?? false) {
                $form[$key.'_ar'] = SiteSetting::value($key, 'ar');
            }
        }

        return array_merge($form, $changes);
    }

    public function test_footer_shows_the_defaults_until_an_admin_saves(): void
    {
        $this->assertSame(0, SiteSetting::count());

        $this->get('/')->assertOk()
            ->assertSee('licensed by the Ministry of Tourism')
            ->assertSee('href="tel:+905521501111"', false)->assertSee('+90 552 150 1111')
            ->assertSee('href="mailto:Info@Safer.Travel"', false)
            ->assertSee('Watan Square, Al-Fateh, Istanbul, Turkey')
            ->assertSee('href="https://www.facebook.com/SaferTourismOfficial"', false)
            ->assertSee('https://wa.me/905016451111?text=', false)
            ->assertDontSee('>TikTok<', false);

        $this->withUnencryptedCookie('sc_lang', 'ar')->get('/')->assertOk()
            ->assertSee('مرخّصة من وزارة السياحة')->assertSee('للأفراد:')->assertSee('ميدان وطن، الفاتح، إسطنبول، تركيا');
    }

    public function test_admin_edits_the_footer_in_both_languages(): void
    {
        $this->asAdmin()->get('/admin/footer')->assertOk()
            ->assertSee('Footer & contact')->assertSee('name="about_ar"', false)->assertSee('name="tiktok"', false)
            ->assertSee('Watan Square');

        $this->put('/admin/footer', $this->form([
            'about' => 'Cruises on the Red Sea.', 'about_ar' => 'رحلات في البحر الأحمر.',
            'phone_1_label' => 'Sales:', 'phone_1_label_ar' => 'المبيعات:', 'phone_1' => '+966 12 345 6789',
            'phone_2' => '',
            'email' => 'hello@safer.travel',
            'address' => 'Jeddah, Saudi Arabia', 'address_ar' => '',
            'whatsapp' => '966500000000',
            'facebook' => '', 'tiktok' => 'https://www.tiktok.com/@safer',
        ]))->assertRedirect('/admin/footer')->assertSessionHasNoErrors();

        $this->get('/')->assertOk()
            ->assertSee('Cruises on the Red Sea.')->assertDontSee('licensed by the Ministry of Tourism')
            ->assertSee('Sales:')->assertSee('href="tel:+966123456789"', false)
            ->assertDontSee('+90 544 501 9185')                         // emptied: line hidden
            ->assertSee('href="mailto:hello@safer.travel"', false)
            ->assertDontSee('facebook.com', false)->assertSee('href="https://www.tiktok.com/@safer"', false)
            ->assertSee('https://wa.me/966500000000?text=', false);

        $ar = $this->withUnencryptedCookie('sc_lang', 'ar')->get('/')->assertOk()
            ->assertSee('رحلات في البحر الأحمر.')->assertSee('المبيعات:')
            ->assertSee('Jeddah, Saudi Arabia'); // empty Arabic shows the English
        $this->assertSame('966500000000', $ar->viewData('scData')['whatsappNumber']);
    }

    public function test_footer_values_are_validated(): void
    {
        $this->asAdmin();

        $this->put('/admin/footer', $this->form(['instagram' => 'javascript:alert(1)']))->assertSessionHasErrors('instagram');
        $this->put('/admin/footer', $this->form(['website_url' => 'safer.travel']))->assertSessionHasErrors('website_url');
        $this->put('/admin/footer', $this->form(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->put('/admin/footer', $this->form(['whatsapp' => '+90 501 645']))->assertSessionHasErrors('whatsapp');
        $this->put('/admin/footer', $this->form(['phone_1' => 'call us']))->assertSessionHasErrors('phone_1');
        $this->assertSame(0, SiteSetting::count());

        // An emptied WhatsApp number falls back to config/cruise.php
        $this->put('/admin/footer', $this->form(['whatsapp' => '']))->assertSessionHasNoErrors();
        $this->assertSame(config('cruise.whatsapp_number'), SiteSetting::whatsapp());
    }

    public function test_footer_screen_is_for_admins_only(): void
    {
        $this->get('/admin/footer')->assertRedirect('/admin/login');
        $this->actingAs(User::where('email', 'seller@safercruise.test')->firstOrFail(), 'admin')
            ->get('/admin/footer')->assertForbidden();
    }
}
