<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\BookingConfirmed;
use App\Mail\NewBookingForTeam;
use App\Models\Booking;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Assistant\Assistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The super admin's test accounts (the agents chosen in Admin → Assistant chats, App\Support\TestAccounts): only the
 * super admin sees them and their bookings in the panel, and the team's email for their bookings goes to the test
 * address.
 */
class TestAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
        config(['cruise.team_email' => 'team@safer.example', 'assistant.test_email' => 'tests@example.com']);
    }

    private function user(string $email, UserRole $role): User
    {
        $user = User::factory()->create(['email' => $email, 'company_name' => ucfirst(strtok($email, '@')).' Travel']);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function staff(string $email): User
    {
        return User::where('email', $email)->sole();
    }

    /** RSD-261213, balcony, 2 adults (same payload as AgentAccountsTest). */
    private function book(User $agent, string $email): Booking
    {
        $reference = $this->actingAs($agent, 'web')->postJson('/booking/confirm', [
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
        ])->assertCreated()->json('reference');
        auth('web')->logout();

        return Booking::where('reference', $reference)->sole();
    }

    /** @return array{0: User, 1: Booking, 2: User, 3: Booking} the test agent and its booking, a real agent and theirs */
    private function scenario(): array
    {
        $tester = $this->user('tester@example.com', UserRole::Agent);
        $agent = $this->user('real@example.com', UserRole::Agent);
        SiteSetting::updateOrCreate(['key' => Assistant::TESTERS], ['value' => (string) $tester->id]);

        $hidden = $this->book($tester, 'test-client@example.com');
        $shown = $this->book($agent, 'real-client@example.com');
        // Assigned to the seller, who would otherwise see it
        $hidden->update(['seller_id' => $this->staff('seller@safercruise.test')->id]);
        $shown->update(['seller_id' => $this->staff('seller@safercruise.test')->id]);

        return [$tester, $hidden, $agent, $shown];
    }

    public function test_the_test_accounts_bookings_email_the_test_address_not_the_team(): void
    {
        [, $hidden, , $shown] = $this->scenario();

        Mail::assertSent(NewBookingForTeam::class, fn ($mail) => $mail->booking->is($hidden) && $mail->hasTo('tests@example.com') && ! $mail->hasTo('team@safer.example'));
        Mail::assertSent(NewBookingForTeam::class, fn ($mail) => $mail->booking->is($shown) && $mail->hasTo('team@safer.example'));
        Mail::assertNotSent(NewBookingForTeam::class, fn ($mail) => $mail->booking->is($shown) && $mail->hasTo('tests@example.com'));
        // The customer's own confirmation is unchanged
        Mail::assertSent(BookingConfirmed::class, fn ($mail) => $mail->booking->is($hidden) && $mail->hasTo('test-client@example.com'));
    }

    public function test_admins_supervisors_and_sellers_never_see_the_test_account_or_its_bookings(): void
    {
        [$tester, $hidden, $agent, $shown] = $this->scenario();

        foreach (['admin@safercruise.test', 'supervisor@safercruise.test', 'seller@safercruise.test'] as $email) {
            $staff = $this->staff($email);
            $this->actingAs($staff, 'admin');

            $this->get('/admin/bookings')->assertOk()->assertSee($shown->reference)->assertDontSee($hidden->reference)->assertDontSee('Tester Travel');
            $this->get('/admin')->assertOk()->assertDontSee($hidden->reference);
            $this->get('/admin/bookings/'.$hidden->reference)->assertNotFound();
            $this->patch('/admin/bookings/'.$hidden->reference, ['admin_notes' => 'x'])->assertNotFound();

            if ($staff->role !== UserRole::Seller) {
                $this->get('/admin/agents')->assertOk()->assertSee('real@example.com')->assertDontSee('tester@example.com');
                $this->get('/admin/agents/'.$tester->id)->assertNotFound();
                $this->get('/admin/agents/'.$agent->id)->assertOk();
                // Admins get 404 (hidden); supervisors can't cancel at all, so the route gate answers 403 first
                $this->post('/admin/bookings/'.$hidden->reference.'/cancel')
                    ->assertStatus($staff->role === UserRole::Admin ? 404 : 403);
            }
        }
        $this->actingAs($this->staff('admin@safercruise.test'), 'admin')->get('/admin/agents/'.$tester->id.'/edit')->assertNotFound();
        $this->assertNull($hidden->fresh()->admin_notes);
    }

    public function test_the_super_admin_sees_everything(): void
    {
        [$tester, $hidden, , $shown] = $this->scenario();
        $dev = $this->user('dev@example.com', UserRole::SuperAdmin);
        $this->actingAs($dev, 'admin');

        $this->get('/admin/bookings')->assertOk()->assertSee($hidden->reference)->assertSee($shown->reference);
        $this->get('/admin/bookings/'.$hidden->reference)->assertOk();
        $this->get('/admin/agents')->assertOk()->assertSee('tester@example.com');
        $this->get('/admin/agents/'.$tester->id)->assertOk();
    }

    public function test_the_test_agent_still_sees_its_own_bookings_on_the_website(): void
    {
        [$tester, $hidden] = $this->scenario();

        $this->actingAs($tester, 'web')->get('/account')->assertOk()->assertSee($hidden->reference);
    }
}
