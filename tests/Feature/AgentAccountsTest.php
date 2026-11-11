<?php

namespace Tests\Feature;

use App\Enums\AgentRequestStatus;
use App\Enums\UserRole;
use App\Http\Requests\AgentRequest;
use App\Models\AgentApplication;
use App\Models\AgentRate;
use App\Models\AgentTier;
use App\Models\Booking;
use App\Models\CabinCategory;
use App\Models\Cruise;
use App\Models\User;
use App\Notifications\AgentApproved;
use App\Services\AgentPricing;
use App\Support\ScDataPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Agent workflow: approving a "Become an agent" application makes an agent account and emails its sign-in details;
 * signed-in agents see and pay the agent rates (Admin → Agents → Agent rates).
 */
class AgentAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
        Notification::fake();
    }

    private function application(array $override = []): AgentApplication
    {
        return AgentApplication::create($override + [
            'company_name' => 'Red Sea Travel',
            'contact_name' => 'Omar Al Saleh',
            'email' => 'Omar@RedSeaTravel.example',
            'phone_dial' => '+966',
            'phone' => '501234567',
        ]);
    }

    private function staff(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function agent(): User
    {
        $agent = User::factory()->create(['email' => 'agent@example.com', 'password' => 'secret123']);
        $agent->forceFill(['role' => UserRole::Agent])->save();

        return $agent;
    }

    /** RSD-261213, balcony (double 7280, 3rd guest 1680), 2 adults + 1 child: same payload as BookingFlowTest. */
    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'sailing' => 'RSD-261213',
            'cabin' => 'balcony',
            'guests' => ['adult' => 2, 'child' => 1, 'infant' => 0],
            'travellers' => [
                'adult-1' => ['first' => 'Noura', 'last' => 'Alqahtani', 'dob' => '1991-03-02', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
                'adult-2' => ['first' => 'Khalid', 'last' => 'Alqahtani', 'dob' => '1988-07-19', 'gender' => 'Male', 'nationality' => 'Saudi Arabia'],
                'child-1' => ['first' => 'Sara', 'last' => 'Alqahtani', 'dob' => '2018-05-01', 'gender' => 'Female', 'nationality' => 'Saudi Arabia'],
            ],
            'contact' => ['email' => 'noura@example.com', 'dial' => '+966', 'phone' => '055 123 4567'],
            'promoCode' => null,
            'agreed' => true,
        ], $override);
    }

    public function test_approving_makes_an_agent_account_and_emails_a_temporary_password(): void
    {
        $application = $this->application();
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin')
            ->post(route('admin.agent-requests.approve', $application))
            ->assertRedirect()->assertSessionHas('status');

        $agent = User::where('email', 'omar@redseatravel.example')->sole();
        $this->assertSame(UserRole::Agent, $agent->role);
        $this->assertSame(['Omar', 'Al Saleh', 'Red Sea Travel', '+966', '501234567'], [$agent->first_name, $agent->last_name, $agent->company_name, $agent->phone_dial, $agent->phone]);
        $this->assertFalse($agent->isStaff());

        $application->refresh();
        $this->assertSame(AgentRequestStatus::Accepted, $application->status);
        $this->assertSame($agent->id, $application->user_id);
        $this->assertNotNull($application->approved_at);

        $password = null;
        Notification::assertSentTo($agent, AgentApproved::class, function (AgentApproved $n) use ($agent, &$password) {
            $password = $n->password;
            $html = $n->toMail($agent)->render();

            return $n->company === 'Red Sea Travel' && str_contains($html, $n->password) && str_contains($html, 'omar@redseatravel.example');
        });
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $password);
        $this->assertTrue(Hash::check($password, $agent->password));

        // The agent signs in on the website with it, and is kept out of the admin panel
        auth('admin')->logout();
        $this->post('/login', ['email' => 'omar@redseatravel.example', 'password' => $password])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($agent, 'web');
        $this->post('/admin/login', ['email' => 'omar@redseatravel.example', 'password' => $password]);
        $this->assertGuest('admin');

        // Approving twice does nothing
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin')
            ->post(route('admin.agent-requests.approve', $application))->assertSessionHas('error');
        Notification::assertSentToTimes($agent, AgentApproved::class, 1);
    }

    public function test_a_customer_email_is_never_turned_into_an_agent(): void
    {
        // An application made before the customer registered (the form refuses it now)
        $application = $this->application(['email' => 'REEM@example.com']);
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');

        $this->get(route('admin.agent-requests.show', $application))->assertSee("already has a customer account, so it can't be approved", false);
        $this->post(route('admin.agent-requests.approve', $application))->assertSessionHasErrors('approve');

        $this->assertSame(UserRole::Customer, User::where('email', 'reem@example.com')->sole()->role);
        $this->assertSame(AgentRequestStatus::Pending, $application->fresh()->status);
        Notification::assertNothingSent();

        // Nor from Admin → Customers
        $reem = User::where('email', 'reem@example.com')->sole();
        $this->get(route('admin.customers.edit', $reem->id))->assertOk()->assertDontSee('<option value="agent"', false);
        $this->put(route('admin.customers.update', $reem->id), ['first_name' => 'Reem', 'last_name' => 'Alharbi', 'email' => $reem->email, 'role' => 'agent'])
            ->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Customer, $reem->fresh()->role);
    }

    public function test_the_application_form_needs_an_email_of_its_own(): void
    {
        $form = ['company_name' => 'Red Sea Travel', 'contact_name' => 'Omar Saleh', 'dial' => '+966', 'phone' => '501 234 567'];

        // Has an account (any case)
        $this->post('/agents', $form + ['email' => 'Reem@Example.com'])->assertSessionHasErrors(['email' => __('pages.agents.email_has_account')]);
        $this->post('/agents', $form + ['email' => 'seller@safercruise.test'])->assertSessionHasErrors('email');

        // One open application per email; after a rejection they can apply again
        $this->post('/agents', $form + ['email' => 'omar@redsea.example'])->assertSessionHasNoErrors();
        $this->post('/agents', $form + ['email' => 'OMAR@redsea.example'])->assertSessionHasErrors(['email' => __('pages.agents.email_already_applied')]);
        AgentApplication::sole()->forceFill(['status' => AgentRequestStatus::Rejected])->save();
        $this->post('/agents', $form + ['email' => 'omar@redsea.example'])->assertSessionHasNoErrors();
        $this->assertSame(2, AgentApplication::count());
    }

    public function test_the_application_form_needs_a_company_email(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class); // more than the route's 5 posts a minute
        config(['agents.business_email_only' => true]); // TODO: drop once the restriction is back on by default
        $form = ['company_name' => 'Red Sea Travel', 'contact_name' => 'Omar Saleh', 'dial' => '+966', 'phone' => '501 234 567'];

        foreach (['omar@gmail.com', 'Omar@YAHOO.com', 'omar@hotmail.com', 'omar@outlook.com', 'omar@icloud.com', 'omar@aol.com',
            'omar@yahoo.co.uk', 'omar@hotmail.fr', 'omar@outlook.sa', 'omar@live.com.eg', 'omar@mail.yahoo.com', 'omar@mailinator.com'] as $email) {
            $this->post('/agents', $form + ['email' => $email])->assertSessionHasErrors(['email' => __('pages.agents.email_not_business')]);
        }
        $this->assertSame(0, AgentApplication::count());

        // Company domains that only look like a provider are fine
        $this->post('/agents', $form + ['email' => 'omar@live.redsea-travel.com'])->assertSessionHasNoErrors();
        $this->post('/agents', $form + ['email' => 'omar@gmailtravel.com'])->assertSessionHasNoErrors();
        $this->assertSame(2, AgentApplication::count());

        // Arabic message
        app()->setLocale('ar');
        $this->assertStringContainsString('Gmail', __('pages.agents.email_not_business'));
        $this->assertNotSame(trans('pages.agents.email_not_business', [], 'en'), __('pages.agents.email_not_business'));
    }

    public function test_restrictions_are_temporarily_off_by_default(): void
    {
        // TODO: Remove when agents.business_email_only / agents.max_per_domain are re-enabled after launch/testing
        $this->withoutMiddleware(ThrottleRequests::class);
        $form = ['company_name' => 'Red Sea Travel', 'contact_name' => 'Omar Saleh', 'dial' => '+966', 'phone' => '501 234 567'];

        // Free email providers are accepted
        $this->post('/agents', $form + ['email' => 'omar@gmail.com'])->assertSessionHasNoErrors();
        $this->post('/agents', $form + ['email' => 'sara@yahoo.co.uk'])->assertSessionHasNoErrors();
        // Several people of one company
        $this->post('/agents', $form + ['email' => 'ahmed@redsea.example'])->assertSessionHasNoErrors();
        $this->post('/agents', $form + ['email' => 'ali@redsea.example'])->assertSessionHasNoErrors();
        $this->post('/agents', $form + ['email' => 'mona@gmail.com'])->assertSessionHasNoErrors();
        $this->assertSame(5, AgentApplication::count());

        // Each email still on its own: one open application per address
        $this->post('/agents', $form + ['email' => 'Omar@Gmail.com'])->assertSessionHasErrors(['email' => __('pages.agents.email_already_applied')]);
    }

    public function test_one_agent_per_company_domain(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['agents.max_per_domain' => 1]); // TODO: drop once the restriction is back on by default
        $form = ['company_name' => 'Red Sea Travel', 'contact_name' => 'Ahmed', 'dial' => '+966', 'phone' => '501 234 567'];

        $this->post('/agents', $form + ['email' => 'ahmed@redsea.example'])->assertSessionHasNoErrors();
        // Same company, another person, any case: refused while the first application is open
        $this->post('/agents', $form + ['email' => 'Ali@RedSea.example'])->assertSessionHasErrors(['email' => __('pages.agents.domain_taken')]);
        // Another company, and a domain that only ends the same, are fine
        $this->post('/agents', $form + ['email' => 'ali@other.example'])->assertSessionHasNoErrors();
        $this->post('/agents', $form + ['email' => 'ali@notredsea.example'])->assertSessionHasNoErrors();

        // Approved: the domain stays taken by the agent account
        $first = AgentApplication::where('email', 'ahmed@redsea.example')->sole();
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin')->post(route('admin.agent-requests.approve', $first));
        $this->assertSame(AgentRequestStatus::Accepted, $first->fresh()->status);
        auth('admin')->logout();
        $this->post('/agents', $form + ['email' => 'ali@redsea.example'])->assertSessionHasErrors(['email' => __('pages.agents.domain_taken')]);

        // A rejected application frees its domain
        $other = AgentApplication::where('email', 'ali@other.example')->sole();
        $other->forceFill(['status' => AgentRequestStatus::Rejected])->save();
        $this->post('/agents', $form + ['email' => 'sara@other.example'])->assertSessionHasNoErrors();

        // The limit is a setting: 0 = no limit
        config(['agents.max_per_domain' => 0]);
        $this->post('/agents', $form + ['email' => 'ali@redsea.example'])->assertSessionHasNoErrors();
    }

    public function test_a_double_submit_saves_one_application(): void
    {
        $form = ['company_name' => 'Lowcost holidays', 'contact_name' => 'Yasser', 'email' => 'yasser@lowcost.example', 'dial' => '+20', 'phone' => '1145282888'];
        $this->get('/agents')->assertSee('data-submit-once', false);

        // The second click's request passes the "already applied" check while the first is still saving: simulate
        // the first one being saved right after this request was validated
        $this->app->afterResolving(AgentRequest::class, function () use ($form) {
            if (! AgentApplication::count()) {
                AgentApplication::create(['company_name' => $form['company_name'], 'contact_name' => $form['contact_name'], 'email' => 'Yasser@lowcost.example', 'phone_dial' => '+20', 'phone' => '1145282888']);
            }
        });

        $this->post('/agents', $form)->assertSessionHasNoErrors()->assertSessionHas('agent_sent');
        $this->assertSame(1, AgentApplication::count());
    }

    public function test_a_staff_email_cannot_become_an_agent(): void
    {
        $application = $this->application(['email' => 'seller@safercruise.test']);

        $this->actingAs($this->staff('admin@safercruise.test'), 'admin')
            ->post(route('admin.agent-requests.approve', $application))->assertSessionHasErrors('approve');

        $this->assertSame(UserRole::Seller, $this->staff('seller@safercruise.test')->role);
        $this->assertSame(AgentRequestStatus::Pending, $application->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_only_admins_approve_and_the_status_form_cannot_accept(): void
    {
        $application = $this->application();
        $this->actingAs($this->staff('supervisor@safercruise.test'), 'admin');

        $this->get(route('admin.agent-requests.show', $application))->assertOk()->assertDontSee('Approve and send sign-in details');
        $this->post(route('admin.agent-requests.approve', $application))->assertForbidden();
        $this->patch(route('admin.agent-requests.update', $application), ['status' => 'accepted'])->assertSessionHasErrors('status');
        $this->patch(route('admin.agent-requests.update', $application), ['status' => 'rejected'])->assertSessionHas('status');
        $this->assertSame(AgentRequestStatus::Rejected, $application->fresh()->status);

        $this->actingAs($this->staff('admin@safercruise.test'), 'admin')
            ->get(route('admin.agent-requests.show', $application))->assertSee('Approve and send sign-in details');
    }

    public function test_resending_makes_a_new_password(): void
    {
        $application = $this->application();
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin')->post(route('admin.agent-requests.approve', $application));
        $agent = User::where('email', 'omar@redseatravel.example')->sole();
        $first = $agent->password;

        $this->get(route('admin.agent-requests.show', $application))->assertSee('Send new password')->assertSee('Agent account');
        $this->post(route('admin.agent-requests.credentials', $application))->assertSessionHas('status');

        $this->assertNotSame($first, $agent->fresh()->password);
        Notification::assertSentToTimes($agent, AgentApproved::class, 2);
    }

    public function test_the_most_specific_active_rate_wins(): void
    {
        $cruise = Cruise::firstOrFail();
        $other = Cruise::whereKeyNot($cruise->id)->firstOrFail();
        [$suite, $balcony] = [CabinCategory::where('slug', 'suite')->firstOrFail(), CabinCategory::where('slug', 'balcony')->firstOrFail()];
        $pct = fn (Cruise $c, CabinCategory $cab) => (new AgentPricing)->percent($c->id, $cab->id);

        $this->assertSame(0.0, $pct($cruise, $suite));

        AgentRate::create(['percent' => 5]);
        AgentRate::create(['cabin_category_id' => $suite->id, 'percent' => 7]);
        AgentRate::create(['cruise_id' => $cruise->id, 'percent' => 10]);
        AgentRate::create(['cruise_id' => $cruise->id, 'cabin_category_id' => $suite->id, 'percent' => 15]);
        AgentRate::create(['cruise_id' => $other->id, 'cabin_category_id' => $balcony->id, 'percent' => 40, 'is_active' => false]);

        $this->assertSame(15.0, $pct($cruise, $suite));
        $this->assertSame(10.0, $pct($cruise, $balcony));
        $this->assertSame(7.0, $pct($other, $suite));
        $this->assertSame(5.0, $pct($other, $balcony)); // the 40% rate is switched off
    }

    public function test_agents_see_agent_prices_and_customers_the_public_ones(): void
    {
        AgentRate::create(['percent' => 10]);
        $presenter = app(ScDataPresenter::class);
        $sailing = fn (array $data) => collect($data['cruises'])->flatMap(fn ($c) => $c['sailings'])->firstWhere('id', 'RSD-261213');

        $public = $sailing($presenter->make(User::where('email', 'reem@example.com')->firstOrFail()));
        $this->assertSame(['single' => 5460.0, 'double' => 7280.0, 'extra' => 1680.0], $public['prices']['balcony']);
        $this->assertArrayNotHasKey('publicPrices', $public);

        $agent = $this->agent();
        $data = $presenter->make($agent);
        $agentSailing = $sailing($data);
        $this->assertTrue($data['user']['isAgent']);
        $this->assertSame(['single' => 4914.0, 'double' => 6552.0, 'extra' => 1512.0], $agentSailing['prices']['balcony']);
        $this->assertSame($public['prices']['balcony'], $agentSailing['publicPrices']['balcony']);
        $this->assertSame(10.0, $agentSailing['agentPercent']['balcony']);

        $this->actingAs($agent, 'web')->get('/cruises')->assertOk()->assertSee('data-agent-bar', false);
    }

    public function test_an_agent_booking_is_charged_the_agent_rate(): void
    {
        AgentRate::create(['percent' => 10]);
        $agent = $this->agent();

        $response = $this->actingAs($agent, 'web')->postJson('/booking/confirm', $this->payload(['promoCode' => 'safer10']))->assertCreated();

        $booking = Booking::where('reference', $response->json('reference'))->firstOrFail();
        // 6552 + 1512 = 8064 agent fare (public 8960, saved 896); the promo is on the agent fare: round5(806.4) = 805
        $this->assertSame(['8064.00', '896.00', '805.00', '7259.00'], [$booking->fare_total, $booking->agent_discount_total, $booking->discount_total, $booking->grand_total]);
        // Stored as JSON, so whole numbers come back as ints
        $this->assertEquals(['percent' => 10, 'publicFare' => 8960, 'saving' => 896], $booking->price_snapshot['agent']);
        $this->assertSame($agent->id, $booking->user_id);

        $this->actingAs($this->staff('admin@safercruise.test'), 'admin')
            ->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Agent commission (10%)');
    }

    public function test_customers_and_guests_pay_the_public_price_with_an_agent_rate_set(): void
    {
        AgentRate::create(['percent' => 10]);

        $response = $this->postJson('/booking/confirm', $this->payload())->assertCreated();

        $booking = Booking::where('reference', $response->json('reference'))->firstOrFail();
        $this->assertSame(['8960.00', '0.00', '8960.00'], [$booking->fare_total, $booking->agent_discount_total, $booking->grand_total]);
        $this->assertNull($booking->price_snapshot['agent']);
    }

    public function test_the_sailing_price_grid_sets_each_cabins_agent_percent_for_the_cruise(): void
    {
        AgentRate::create(['percent' => 10]);
        $sailing = \App\Models\Sailing::where('code', 'RSD-261213')->with('cabinPrices', 'cruise')->firstOrFail();
        $balcony = CabinCategory::where('slug', 'balcony')->firstOrFail();
        $suite = CabinCategory::where('slug', 'suite')->firstOrFail();
        $this->actingAs($this->staff('supervisor@safercruise.test'), 'admin');

        // Empty fields; the general 10% is the fallback the page's script shows as placeholder
        $this->get(route('admin.sailings.edit', $sailing))->assertOk()
            ->assertSee('Agent %')->assertSee('name="agent[all]['.$balcony->id.']"', false)->assertSee('&quot;all&quot;:10', false);

        $form = fn (array $agent) => [
            'code' => $sailing->code, 'departure_date' => $sailing->departure_date->toDateString(), 'status' => $sailing->status->value,
            'prices' => $sailing->cabinPrices->mapWithKeys(fn ($p) => [$p->cabin_category_id => [
                'single' => $p->price_single, 'double' => $p->price_double, 'extra' => $p->price_extra, 'sold_out' => (int) $p->is_sold_out,
            ]])->all(),
            'agent' => ['all' => $agent],
        ];

        $this->put(route('admin.sailings.update', $sailing), $form([$balcony->id => '15', $suite->id => '']))->assertSessionHasNoErrors();
        $rate = AgentRate::where(['cruise_id' => $sailing->cruise_id, 'cabin_category_id' => $balcony->id])->sole();
        $this->assertSame('15.00', $rate->percent);
        $this->assertSame(15.0, (new AgentPricing)->percent($sailing->cruise_id, $balcony->id));
        $this->assertSame(10.0, (new AgentPricing)->percent($sailing->cruise_id, $suite->id));
        $this->get(route('admin.sailings.edit', $sailing))->assertSee('value="15"', false);

        $this->put(route('admin.sailings.update', $sailing), $form([$balcony->id => '101']))->assertSessionHasErrors('agent.all.'.$balcony->id);

        // Emptied: back to the general rate
        $this->put(route('admin.sailings.update', $sailing), $form([$balcony->id => '']))->assertSessionHasNoErrors();
        $this->assertSame(1, AgentRate::count());
        $this->assertSame(10.0, (new AgentPricing)->percent($sailing->cruise_id, $balcony->id));
    }

    public function test_an_agents_type_has_its_own_rates_before_the_ones_for_every_type(): void
    {
        $cruise = Cruise::firstOrFail();
        $suite = CabinCategory::where('slug', 'suite')->firstOrFail();
        $standard = AgentTier::create(['name' => 'Standard']);
        $gold = AgentTier::create(['name' => 'Gold']);
        AgentRate::create(['percent' => 5]);
        AgentRate::create(['cruise_id' => $cruise->id, 'percent' => 6]);
        AgentRate::create(['agent_tier_id' => $gold->id, 'percent' => 10]);
        AgentRate::create(['agent_tier_id' => $gold->id, 'cruise_id' => $cruise->id, 'cabin_category_id' => $suite->id, 'percent' => 12]);
        $pct = fn (?AgentTier $tier, int $cabinId) => (new AgentPricing)->percent($cruise->id, $cabinId, $tier?->id);
        $balconyId = CabinCategory::where('slug', 'balcony')->value('id');

        // Gold: its own rates, even over a more specific rate for every type
        $this->assertSame(12.0, $pct($gold, $suite->id));
        $this->assertSame(10.0, $pct($gold, $balconyId));
        // Standard has no rates of its own, and no type: the rates for every type
        $this->assertSame(6.0, $pct($standard, $balconyId));
        $this->assertSame(6.0, $pct(null, $balconyId));
        $this->assertSame(5.0, (new AgentPricing)->percent(Cruise::whereKeyNot($cruise->id)->value('id'), $balconyId));
    }

    public function test_approving_with_a_type_and_booking_at_that_types_rate(): void
    {
        $gold = AgentTier::create(['name' => 'Gold']);
        AgentRate::create(['percent' => 5]);
        AgentRate::create(['agent_tier_id' => $gold->id, 'percent' => 10]);
        $application = $this->application();

        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $this->get(route('admin.agent-requests.show', $application))->assertSee('name="agent_tier_id"', false)->assertSee('Gold');
        $this->post(route('admin.agent-requests.approve', $application), ['agent_tier_id' => $gold->id])->assertSessionHas('status');

        $agent = User::where('email', 'omar@redseatravel.example')->sole();
        $this->assertSame($gold->id, $agent->agent_tier_id);
        $this->get(route('admin.agents.index', ['tier' => $gold->id]))->assertSee('omar@redseatravel.example');

        // Gold 10%: 6552 + 1512 = 8064 (same as the general-rate test above)
        $response = $this->actingAs($agent, 'web')->postJson('/booking/confirm', $this->payload())->assertCreated();
        $booking = Booking::where('reference', $response->json('reference'))->firstOrFail();
        $this->assertSame(['8064.00', '896.00'], [$booking->fare_total, $booking->agent_discount_total]);

        // Without a type: the 5% for every type
        $agent->forceFill(['agent_tier_id' => null])->save();
        $this->assertSame(5.0, app(ScDataPresenter::class)->make($agent->fresh())['cruises'][0]['sailings'][0]['agentPercent']['balcony'] ?? null);
    }

    public function test_the_sailing_grid_sets_agent_percent_per_type(): void
    {
        $gold = AgentTier::create(['name' => 'Gold']);
        AgentRate::create(['agent_tier_id' => $gold->id, 'percent' => 10]);
        $sailing = \App\Models\Sailing::where('code', 'RSD-261213')->with('cabinPrices')->firstOrFail();
        $balcony = CabinCategory::where('slug', 'balcony')->firstOrFail();
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');

        // Every type's column is in the page (tabs switch without a reload); ?tier= opens Gold's
        $this->get(route('admin.sailings.edit', [$sailing, 'tier' => $gold->id]))->assertOk()
            ->assertSee('All agent types')->assertSee('data-agent-tab="'.$gold->id.'"', false)
            ->assertSee('name="agent[all]['.$balcony->id.']"', false)->assertSee('name="agent['.$gold->id.']['.$balcony->id.']"', false)
            ->assertSee('name="agent_tab" value="'.$gold->id.'"', false)
            ->assertSee('&quot;'.$gold->id.'&quot;:10', false);

        $form = fn (array $agent, string $tab = 'all') => [
            'code' => $sailing->code, 'departure_date' => $sailing->departure_date->toDateString(), 'status' => $sailing->status->value,
            'prices' => $sailing->cabinPrices->mapWithKeys(fn ($p) => [$p->cabin_category_id => [
                'single' => $p->price_single, 'double' => $p->price_double, 'extra' => $p->price_extra, 'sold_out' => (int) $p->is_sold_out,
            ]])->all(),
            'agent_tab' => $tab,
            'agent' => $agent,
        ];

        // One save, both columns; back on the Gold tab
        $this->put(route('admin.sailings.update', $sailing), $form(['all' => [$balcony->id => '6'], $gold->id => [$balcony->id => '14']], (string) $gold->id))
            ->assertRedirect(route('admin.sailings.edit', [$sailing, 'tier' => $gold->id]));

        $this->assertSame('14.00', AgentRate::where(['agent_tier_id' => $gold->id, 'cruise_id' => $sailing->cruise_id, 'cabin_category_id' => $balcony->id])->sole()->percent);
        $this->assertSame(14.0, (new AgentPricing)->percent($sailing->cruise_id, $balcony->id, $gold->id));
        $this->assertSame(6.0, (new AgentPricing)->percent($sailing->cruise_id, $balcony->id)); // the "all" column, for other agents

        // Unknown type keys are refused
        $this->put(route('admin.sailings.update', $sailing), $form(['999' => [$balcony->id => '5']]))->assertSessionHasErrors('agent');
    }

    public function test_bookings_screen_separates_b2c_and_agent_bookings(): void
    {
        $gold = AgentTier::create(['name' => 'Gold']);
        AgentRate::create(['agent_tier_id' => $gold->id, 'percent' => 10]);
        $agent = $this->agent();
        $agent->forceFill(['company_name' => 'Nile Travel', 'agent_tier_id' => $gold->id])->save();

        $b2c = $this->postJson('/booking/confirm', $this->payload())->assertCreated()->json('reference');
        $byAgent = $this->actingAs($agent, 'web')->postJson('/booking/confirm', $this->payload())->assertCreated()->json('reference');

        // Who booked and their type at booking time are kept on the booking
        $booking = Booking::where('reference', $byAgent)->sole();
        $this->assertSame([$agent->id, $gold->id], [$booking->agent_id, $booking->agent_tier_id]);
        $this->assertNull(Booking::where('reference', $b2c)->sole()->agent_id);
        $agent->forceFill(['role' => UserRole::Customer, 'agent_tier_id' => null])->save();
        $this->assertTrue($booking->fresh()->isAgentBooking());

        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $all = Booking::count();
        $this->get(route('admin.bookings.index'))->assertOk()
            ->assertSeeText('All bookings ('.$all.')')->assertSeeText('B2C customers ('.($all - 1).')')->assertSeeText('Agents (1)')
            ->assertSee('Agent · Nile Travel · Gold')->assertSee('Commission $896.00');

        $this->get(route('admin.bookings.index', ['channel' => 'agent']))->assertSee($byAgent)->assertDontSee($b2c);
        $this->get(route('admin.bookings.index', ['channel' => 'b2c']))->assertSee($b2c)->assertDontSee($byAgent);
        $this->get(route('admin.bookings.index', ['agent_tier' => $gold->id]))->assertSee($byAgent)->assertDontSee($b2c);
        $this->get(route('admin.bookings.index', ['agent_tier' => 'none']))->assertDontSee($byAgent);
        $this->get(route('admin.bookings.index', ['agent' => $agent->id]))->assertSee($byAgent)->assertDontSee($b2c);
        $this->get(route('admin.bookings.index', ['channel' => 'phone']))->assertSessionHasErrors('channel');

        $this->get(route('admin.bookings.show', $booking))->assertSee('Agent · Nile Travel · Gold');
    }

    public function test_agents_list_links_bookings_and_the_statement_totals_commission(): void
    {
        AgentRate::create(['percent' => 10]);
        $agent = $this->agent();
        $agent->forceFill(['company_name' => 'Nile Travel'])->save();

        // Two bookings at 10%: 8960 public → 896 commission, 8064 net each; the second is then cancelled
        $refs = collect(range(1, 2))->map(fn () => $this->actingAs($agent, 'web')->postJson('/booking/confirm', $this->payload())->assertCreated()->json('reference'));
        Booking::where('reference', $refs[1])->sole()->update(['status' => \App\Enums\BookingStatus::Cancelled]);
        $paid = Booking::where('reference', $refs[0])->sole();
        $paid->forceFill(['amount_paid' => 3000])->save();

        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');
        $this->get(route('admin.agents.index'))->assertOk()
            ->assertSee('Sales (net)')->assertSee('$8,064.00')->assertSee('$896.00')
            ->assertSee(route('admin.agents.show', $agent->id), false)
            ->assertSee(e(route('admin.bookings.index', ['channel' => 'agent', 'agent' => $agent->id])), false)->assertSee('Bookings (2)');

        $this->get(route('admin.agents.show', $agent->id))->assertOk()
            ->assertSee('Nile Travel')->assertSee($refs[0])->assertSee($refs[1])->assertSee('1 cancelled, not counted')
            ->assertSeeInOrder(['Original price', '$8,960.00', 'Total commission', '$896.00', 'Net sales', '$8,064.00'])
            ->assertSeeInOrder(['Paid', '$3,000.00', 'Due', '$5,064.00'])
            // Not used: no paid / awaiting split of the commission, and no promo column without a promo code
            ->assertDontSee('Commission on paid bookings')->assertDontSee('>Promo</th>', false);

        // A promo code on a booking brings the column back
        $paid->forceFill(['discount_total' => 400, 'grand_total' => 7664])->save();
        $this->get(route('admin.agents.show', $agent->id))->assertSee('>Promo</th>', false)->assertSee('− $400.00');

        // Customers have no statement
        $this->get(route('admin.agents.show', User::where('email', 'reem@example.com')->value('id')))->assertNotFound();
    }

    public function test_admin_manages_agent_rates_and_agents(): void
    {
        $cruise = Cruise::firstOrFail();
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin');

        $this->post(route('admin.agent-rates.store'), ['cruise_id' => '', 'cabin_category_id' => '', 'percent' => 8, 'is_active' => 1])->assertRedirect();
        $this->post(route('admin.agent-rates.store'), ['cruise_id' => $cruise->id, 'cabin_category_id' => '', 'percent' => 12, 'is_active' => 1])->assertRedirect();
        // One rate per cruise × cabin, and at most 100%
        $this->post(route('admin.agent-rates.store'), ['cruise_id' => '', 'cabin_category_id' => '', 'percent' => 9])->assertSessionHasErrors('percent');
        $this->post(route('admin.agent-rates.store'), ['cruise_id' => '', 'cabin_category_id' => CabinCategory::first()->id, 'percent' => 101])->assertSessionHasErrors('percent');
        $this->assertSame([8.0, 12.0], AgentRate::orderBy('id')->pluck('percent')->map(fn ($p) => (float) $p)->all());

        $this->get(route('admin.agent-rates.index'))->assertOk()->assertSee('All cruises')->assertSee($cruise->name);

        // Agents list; "Customer" takes the rates away
        $agent = $this->agent();
        $this->get(route('admin.agents.index'))->assertOk()->assertSee('agent@example.com');
        $this->get(route('admin.customers.index'))->assertDontSee('agent@example.com');
        $this->put(route('admin.agents.update', $agent->id), [
            'first_name' => 'Agent', 'last_name' => 'Smith', 'email' => 'agent@example.com', 'company_name' => 'Smith Tours', 'role' => 'customer',
        ])->assertRedirect();
        $this->assertSame([UserRole::Customer, 'Smith Tours'], [$agent->fresh()->role, $agent->fresh()->company_name]);
        $this->put(route('admin.agents.update', $agent->id), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'agent@example.com', 'role' => 'admin'])->assertNotFound();
    }
}
