<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function reem(): User
    {
        return User::where('email', 'reem@example.com')->firstOrFail();
    }

    public function test_account_needs_sign_in(): void
    {
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_sign_in_register_and_sign_out(): void
    {
        $this->post('/login', ['email' => 'reem@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'reem@example.com', 'password' => 'password'])->assertRedirect('/account');
        $this->post('/logout')->assertRedirect('/');

        $this->post('/register', [
            'first_name' => 'Omar', 'last_name' => 'Saleh', 'email' => 'omar@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect('/account');
        $this->assertSame(\App\Enums\UserRole::Customer, User::where('email', 'omar@example.com')->firstOrFail()->role);
    }

    public function test_registration_cannot_choose_a_role(): void
    {
        $this->post('/register', [
            'first_name' => 'Eve', 'last_name' => 'Hacker', 'email' => 'eve@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'admin',
        ]);

        $this->assertFalse(User::where('email', 'eve@example.com')->firstOrFail()->isAdmin());
    }

    public function test_dashboard_gets_the_account_bookings(): void
    {
        $account = $this->actingAs($this->reem())->get('/account')->assertOk()->viewData('scData')['account'];

        $this->assertSame('Reem', $account['profile']['first']);
        $this->assertEqualsCanonicalizing(['SC2611-K4P2Q', 'SC2603-H7M1Z', 'SC2605-T9R3B'], array_column($account['bookings'], 'ref'));
        $booking = collect($account['bookings'])->firstWhere('ref', 'SC2611-K4P2Q');
        $this->assertSame('unpaid', $booking['paymentStatus']);
        $this->assertArrayHasKey('child-1', $booking['travellers']);
    }

    public function test_profile_notifications_password_and_favourites(): void
    {
        $user = $this->reem();
        $this->actingAs($user);

        $this->putJson('/account/profile', ['first' => 'Reem', 'last' => 'Alotaibi', 'email' => 'reem@example.com', 'dial' => '+966', 'phone' => '559876543', 'dob' => '1990-04-12', 'nationality' => 'Saudi Arabia'])
            ->assertOk()->assertJsonPath('profile.last', 'Alotaibi');
        $this->putJson('/account/profile', ['first' => 'Reem', 'last' => 'A', 'email' => 'admin@safercruise.test'])->assertJsonValidationErrors('email');

        $this->putJson('/account/notifications', ['offers' => true, 'whatsapp' => false, 'reminders' => true])->assertOk();
        $this->assertFalse($user->fresh()->notification_prefs['whatsapp']);

        $this->putJson('/account/password', ['current_password' => 'nope', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertJsonValidationErrors('current_password');
        $this->putJson('/account/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();

        $this->postJson('/account/favorites/red-sea-discovery')->assertOk();
        $this->deleteJson('/account/favorites/new-year-at-sea')->assertOk();
        $this->assertEqualsCanonicalizing(['red-sea-discovery', 'egypt-jordan-voyage'], $user->favorites()->pluck('slug')->all());
    }

    public function test_profile_rejects_bad_phone_dob_and_names(): void
    {
        $user = $this->reem();
        $this->actingAs($user);
        $ok = ['first' => 'Reem', 'last' => 'Alotaibi', 'email' => 'reem@example.com'];
        $put = fn (array $change) => $this->putJson('/account/profile', $change + $ok);

        // Phone: per country, and needs a country code
        $put(['dial' => '+20', 'phone' => '0111761593'])->assertJsonValidationErrors('phone');    // 9 digits after the 0
        $put(['dial' => '+966', 'phone' => '123456789'])->assertJsonValidationErrors('phone');    // must start with 5
        $put(['dial' => '+20', 'phone' => '01117abc935'])->assertJsonValidationErrors('phone');
        $put(['phone' => '1117615935'])->assertJsonValidationErrors('dial');
        $put(['dial' => '+20', 'phone' => '01117615935'])->assertOk();
        $this->assertSame(['+20', '1117615935'], [$user->fresh()->phone_dial, $user->fresh()->phone]);

        // Date of birth: a real date, 18 to 120 years ago
        $put(['dob' => now()->addDay()->toDateString()])->assertJsonValidationErrors('dob');
        $put(['dob' => now()->subYears(10)->toDateString()])->assertJsonValidationErrors('dob');
        $put(['dob' => '1850-01-01'])->assertJsonValidationErrors('dob');
        $put(['dob' => '2000-02-31'])->assertJsonValidationErrors('dob');

        // Names: letters only; nationality from the list
        $put(['first' => '<script>'])->assertJsonValidationErrors('first');
        $put(['last' => '123'])->assertJsonValidationErrors('last');
        $put(['nationality' => 'Atlantis'])->assertJsonValidationErrors('nationality');
    }

    public function test_password_change_is_rate_limited(): void
    {
        $this->actingAs($this->reem());
        foreach (range(1, 5) as $i) {
            $this->putJson('/account/password', ['current_password' => 'nope', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertStatus(422);
        }
        $this->putJson('/account/password', ['current_password' => 'nope', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertTooManyRequests();
    }

    public function test_put_and_delete_also_work_as_post_with_a_method_override(): void
    {
        // SC.api() sends them this way, because IIS on the live server answers real PUT/DELETE with 405
        $user = $this->reem();
        $this->actingAs($user);

        $this->postJson('/account/notifications', ['offers' => true, 'whatsapp' => false, 'reminders' => true], ['X-HTTP-Method-Override' => 'PUT'])->assertOk();
        $this->assertFalse($user->fresh()->notification_prefs['whatsapp']);

        $this->postJson('/account/favorites/new-year-at-sea', [], ['X-HTTP-Method-Override' => 'DELETE'])->assertOk();
        $this->assertNotContains('new-year-at-sea', $user->favorites()->pluck('slug')->all());
    }
}
