<?php

namespace Tests\Feature;

use App\Enums\AgentRequestStatus;
use App\Http\Requests\AgentRequest;
use App\Models\AgentApplication;
use App\Models\HomeSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private array $valid = [
        'company_name' => 'Red Sea Travel',
        'contact_name' => 'Omar Saleh',
        'email' => 'omar@redseatravel.example',
        'dial' => '+966',
        'phone' => '501 234 567',
    ];

    private function admin(): User
    {
        return User::where('email', 'admin@safercruise.test')->firstOrFail();
    }

    public function test_agents_page_shows_its_sections_and_the_form_in_both_languages(): void
    {
        $this->get('/agents')->assertOk()
            ->assertSee('Become our agent')->assertSee('Benefits of joining the program')->assertSee('Exclusive discounts')
            ->assertSee('name="'.AgentRequest::HONEYPOT.'"', false);

        $this->withCredentials()->withUnencryptedCookie('sc_lang', 'ar')->get('/agents')->assertOk()
            ->assertSee('كن وكيلنا')->assertSee('اسم الشركة');

        // Mobile number with the site's flag + country-code widget, as on the booking form
        $this->get('/agents')->assertSee('name="dial"', false)->assertSee('data-phone-field', false)
            ->assertSee('intlTelInput.min.js', false)->assertSee('assets/js/phone-fields.js', false);
    }

    public function test_page_texts_are_edited_in_the_admin(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.pages.index'))->assertOk()->assertSee('Become an agent');

        $this->put(route('admin.pages.update', ['agents', 'vision']), [
            'title' => 'Where we are heading', 'title_ar' => 'إلى أين نتجه',
            'body' => 'Partnerships that last.', 'is_visible' => 1,
        ])->assertRedirect();

        $this->assertSame('Where we are heading', HomeSection::where(['page' => 'agents', 'key' => 'vision'])->sole()->title);
        $this->get('/agents')->assertSee('Where we are heading')->assertSee('Partnerships that last.');
    }

    public function test_an_application_is_saved_as_pending(): void
    {
        // A status sent with the form is ignored
        $this->post('/agents', $this->valid + ['status' => 'accepted'])->assertRedirect(route('agents').'#apply')->assertSessionHas('agent_sent');

        $application = AgentApplication::sole();
        $this->assertSame('Red Sea Travel', $application->company_name);
        $this->assertSame(AgentRequestStatus::Pending, $application->status);
        $this->assertSame('+966 501234567', $application->full_phone);
        $this->get('/agents')->assertSee('your application has been received');
    }

    public function test_invalid_applications_are_rejected(): void
    {
        $this->post('/agents', ['company_name' => '', 'contact_name' => str_repeat('a', 256), 'email' => 'nope', 'dial' => '+966', 'phone' => 'call me'])
            ->assertSessionHasErrors(['company_name', 'contact_name', 'email', 'phone']);
        $this->post('/agents', ['phone' => str_repeat('1', 21)] + $this->valid)->assertSessionHasErrors('phone');
        $this->post('/agents', ['phone' => ''] + $this->valid)->assertSessionHasErrors(['phone' => __('site.booking.errors.phone_required')]);
        $this->post('/agents', ['phone' => '12345'] + $this->valid)->assertSessionHasErrors(['phone' => __('site.booking.errors.phone_sa')]);
        $this->post('/agents', ['dial' => ''] + $this->valid)->assertSessionHasErrors('dial');

        $this->assertSame(0, AgentApplication::count());
    }

    public function test_bots_that_fill_the_honeypot_get_nothing_saved(): void
    {
        $this->post('/agents', $this->valid + [AgentRequest::HONEYPOT => 'https://spam.example'])
            ->assertRedirect()->assertSessionHas('agent_sent');

        $this->assertSame(0, AgentApplication::count());
    }

    public function test_applying_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/agents', $this->valid)->assertRedirect();
        }
        $this->post('/agents', $this->valid)->assertStatus(429);
    }

    public function test_admin_lists_filters_and_updates_applications(): void
    {
        $first = AgentApplication::create($this->valid);
        $this->travel(1)->minutes();
        $second = AgentApplication::create(['company_name' => 'Hijaz Tours'] + $this->valid);
        $this->actingAs($this->admin(), 'admin');

        $this->get(route('admin.agent-requests.index'))->assertOk()
            ->assertSeeInOrder(['Hijaz Tours', 'Red Sea Travel'])->assertSee('Pending (2)');

        $this->get(route('admin.agent-requests.show', $first))->assertOk()->assertSee('omar@redseatravel.example');
        $this->patch(route('admin.agent-requests.update', $first), ['status' => 'accepted'])->assertRedirect();
        $this->assertSame(AgentRequestStatus::Accepted, $first->fresh()->status);

        $this->get(route('admin.agent-requests.index', ['status' => 'accepted']))->assertSee('Red Sea Travel')->assertDontSee('Hijaz Tours');
        $this->patch(route('admin.agent-requests.update', $second), ['status' => 'approved'])->assertSessionHasErrors('status');
    }

    public function test_sellers_cannot_see_agent_requests(): void
    {
        $application = AgentApplication::create($this->valid);
        $this->actingAs(User::where('email', 'seller@safercruise.test')->firstOrFail(), 'admin');

        $this->get(route('admin.agent-requests.index'))->assertForbidden();
        $this->get(route('admin.agent-requests.show', $application))->assertForbidden();
        $this->patch(route('admin.agent-requests.update', $application), ['status' => 'accepted'])->assertForbidden();
    }
}
