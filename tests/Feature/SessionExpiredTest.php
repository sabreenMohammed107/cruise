<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * "419 Page expired" on the live site: an old host-only session cookie next to the domain one (ForgetStaleSessionCookie)
 * and a friendlier answer to an expired form (bootstrap/app.php).
 */
class SessionExpiredTest extends TestCase
{
    use RefreshDatabase;

    /** The host-only session cookie this response expires, if any. */
    private function clearedHostCookie($response): ?Cookie
    {
        return collect($response->headers->getCookies())
            ->first(fn (Cookie $c) => $c->getName() === config('session.cookie') && $c->getDomain() === null && $c->isCleared());
    }

    public function test_an_old_host_only_session_cookie_is_cleared(): void
    {
        config(['session.domain' => '.safer.test']);
        $name = config('session.cookie');

        // The browser sends the cookie twice (the old host-only one first): the answer expires the host-only one
        $response = $this->withServerVariables(['HTTP_COOKIE' => "$name=old-host-only; $name=current"])->get('/login')->assertOk();
        $this->assertNotNull($this->clearedHostCookie($response));

        // Once: nothing to clear
        $this->assertNull($this->clearedHostCookie($this->withServerVariables(['HTTP_COOKIE' => "$name=current"])->get('/login')));

        // Without a cookie domain there is only ever one cookie: nothing to do
        config(['session.domain' => null]);
        $this->assertNull($this->clearedHostCookie($this->withServerVariables(['HTTP_COOKIE' => "$name=a; $name=b"])->get('/login')));
    }

    public function test_an_expired_form_goes_back_with_a_message_instead_of_419(): void
    {
        // CSRF checks are off in tests: a route that fails the way an expired form does
        Route::middleware('web')->post('/_expired-form', fn () => throw new TokenMismatchException('CSRF token mismatch.'));

        $this->from('/login')->post('/_expired-form', ['email' => 'agent@example.com', 'password' => 'secret'])
            ->assertRedirect('/login')
            ->assertSessionHas('status', __('site.auth.session_expired'))
            ->assertSessionHasInput('email', 'agent@example.com')
            ->assertSessionMissing('_old_input.password');

        // The message shows on the page
        $this->get('/login')->assertSee(__('site.auth.session_expired'));

        // Page scripts (JSON) still get a 419 to handle
        $this->postJson('/_expired-form')->assertStatus(419);
    }
}
