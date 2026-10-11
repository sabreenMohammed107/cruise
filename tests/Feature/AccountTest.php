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
}
