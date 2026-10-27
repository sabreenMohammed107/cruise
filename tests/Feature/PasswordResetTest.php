<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function reem(): User
    {
        return User::where('email', 'reem@example.com')->firstOrFail();
    }

    public function test_pages_render_and_login_links_to_them(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/some-token?email=reem@example.com')->assertOk()->assertSee('value="reem@example.com"', false);
    }

    public function test_reset_link_is_queued_and_resets_the_password(): void
    {
        Notification::fake();

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'reem@example.com'])
            ->assertRedirect('/forgot-password')->assertSessionHas('reset_sent', 'reem@example.com');
        $this->get('/forgot-password')->assertSee(__('site.auth.check_inbox'))->assertDontSee('id="email"', false);

        $token = null;
        Notification::assertSentTo($this->reem(), ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return $n instanceof ShouldQueue;
        });

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'reem@example.com',
            'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertRedirect('/login')->assertSessionHas('status');

        $this->assertTrue(Hash::check('newpass123', $this->reem()->password));
        $this->post('/login', ['email' => 'reem@example.com', 'password' => 'newpass123'])->assertRedirect('/account');

        // The token is single-use
        $this->post('/logout');
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'reem@example.com',
            'password' => 'other1234', 'password_confirmation' => 'other1234',
        ])->assertSessionHasErrors('email');
    }

    public function test_unknown_and_staff_emails_get_the_same_answer_but_no_email(): void
    {
        Notification::fake();
        $staff = User::staff()->firstOrFail();

        foreach (['nobody@example.com', $staff->email] as $email) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
                ->assertRedirect('/forgot-password')->assertSessionHas('reset_sent', $email);
        }

        Notification::assertNothingSent();
    }

    public function test_bad_token_and_weak_password_are_rejected(): void
    {
        $this->post('/reset-password', [
            'token' => 'wrong', 'email' => 'reem@example.com',
            'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertSessionHasErrors('email');

        $this->post('/reset-password', [
            'token' => 'wrong', 'email' => 'reem@example.com',
            'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $this->reem()->password));
    }

    public function test_sending_is_rate_limited(): void
    {
        foreach (range(1, 3) as $i) {
            $this->post('/forgot-password', ['email' => "n{$i}@example.com"])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => 'n4@example.com'])->assertTooManyRequests();
    }

    public function test_email_renders_in_the_visitors_language(): void
    {
        app()->setLocale('ar');
        $mail = (new ResetPassword('tok'))->toMail($this->reem());
        $this->assertSame('ar', (new ResetPassword('tok'))->locale);

        $html = (string) $mail->render();
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString(route('password.reset', ['token' => 'tok', 'email' => 'reem@example.com']), $html);
    }
}
