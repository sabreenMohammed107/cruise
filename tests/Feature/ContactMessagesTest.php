<?php

namespace Tests\Feature;

use App\Http\Requests\ContactRequest;
use App\Mail\NewContactMessage;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private array $valid = [
        'name' => 'Reem Alharbi',
        'email' => 'reem@example.com',
        'dial' => '+966',
        'phone' => '0501 234 567',
        'message' => 'Is the balcony cabin available on the December sailing?',
    ];

    private function admin(): User
    {
        return User::where('email', 'admin@safercruise.test')->firstOrFail();
    }

    public function test_contact_page_shows_the_form_in_both_languages(): void
    {
        $this->get('/contact')->assertOk()->assertSee('Send us a message')->assertSee('name="'.ContactRequest::HONEYPOT.'"', false);
        $this->withCredentials()->withUnencryptedCookie('sc_lang', 'ar')->get('/contact')->assertOk()->assertSee('أرسل لنا رسالة');
    }

    public function test_a_message_is_saved_and_the_team_is_emailed(): void
    {
        Mail::fake();

        $this->post('/contact', $this->valid)->assertRedirect('/contact')->assertSessionHas('contact_sent');

        $message = ContactMessage::sole();
        $this->assertSame('Reem Alharbi', $message->name);
        // Stored like the booking and account forms: code apart, number without spaces or the leading zero
        $this->assertSame(['+966', '501234567'], [$message->phone_dial, $message->phone]);
        $this->assertFalse($message->is_read);
        Mail::assertQueued(NewContactMessage::class, fn ($mail) => $mail->contactMessage->is($message) && $mail->hasReplyTo('reem@example.com'));
        $this->get('/contact')->assertSee('your message has been sent');
    }

    public function test_the_phone_is_optional_and_checked_for_its_country(): void
    {
        Mail::fake();

        $this->post('/contact', ['dial' => '+966', 'phone' => ''] + $this->valid)->assertSessionHasNoErrors();
        $this->assertSame([null, null], [ContactMessage::sole()->phone_dial, ContactMessage::sole()->phone]);

        // Saudi numbers: 9 digits starting with 5; Egyptian: 10 digits starting with 1 (same as the booking form)
        $this->post('/contact', ['phone' => '401234567'] + $this->valid)->assertSessionHasErrors(['phone' => __('site.booking.errors.phone_sa')]);
        $this->post('/contact', ['dial' => '+20', 'phone' => '501234567'] + $this->valid)->assertSessionHasErrors(['phone' => __('site.booking.errors.phone_eg')]);
        $this->post('/contact', ['dial' => '+999'] + $this->valid)->assertSessionHasErrors('dial');
        $this->post('/contact', ['dial' => '+20', 'phone' => '1012345678'] + $this->valid)->assertSessionHasNoErrors();
        $this->assertSame('+20 1012345678', ContactMessage::latest('id')->first()->full_phone);
    }

    public function test_invalid_messages_are_rejected(): void
    {
        $this->post('/contact', ['name' => '', 'email' => 'not-an-email', 'dial' => '+966', 'phone' => 'call me', 'message' => 'Too short'])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'message']);
        $this->post('/contact', ['message' => str_repeat('a', 2001)] + $this->valid)->assertSessionHasErrors('message');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_bots_that_fill_the_honeypot_get_no_message_saved(): void
    {
        Mail::fake();

        $this->post('/contact', $this->valid + [ContactRequest::HONEYPOT => 'https://spam.example'])
            ->assertRedirect('/contact')->assertSessionHas('contact_sent');

        $this->assertSame(0, ContactMessage::count());
        Mail::assertNothingQueued();
    }

    public function test_sending_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $this->valid)->assertRedirect('/contact');
        }
        $this->post('/contact', $this->valid)->assertStatus(429);
    }

    public function test_message_text_is_escaped(): void
    {
        $message = ContactMessage::create(['message' => '<script>alert(1)</script> hello there'] + $this->valid);

        $this->actingAs($this->admin(), 'admin')->get(route('admin.contact-messages.show', $message))
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_admin_lists_reads_and_deletes_messages(): void
    {
        $old = ContactMessage::create($this->valid);
        $this->travel(1)->minutes();
        $new =ContactMessage::create(['name' => 'Omar'] + $this->valid);
        $this->actingAs($this->admin(), 'admin');

        $this->get(route('admin.contact-messages.index'))->assertOk()->assertSeeInOrder(['Omar', 'Reem Alharbi'])->assertSee('Unread (2)');

        $this->get(route('admin.contact-messages.show', $old))->assertOk()->assertSee($this->valid['message']);
        $this->assertTrue($old->fresh()->is_read);
        $this->get(route('admin.contact-messages.index', ['unread' => 1]))->assertSee('Omar')->assertDontSee('Reem Alharbi');

        $this->delete(route('admin.contact-messages.destroy', $new))->assertRedirect(route('admin.contact-messages.index'));
        $this->assertModelMissing($new);
    }

    public function test_sellers_cannot_see_contact_messages(): void
    {
        $message = ContactMessage::create($this->valid);
        $this->actingAs(User::where('email', 'seller@safercruise.test')->firstOrFail(), 'admin');

        $this->get(route('admin.contact-messages.index'))->assertForbidden();
        $this->get(route('admin.contact-messages.show', $message))->assertForbidden();
        $this->delete(route('admin.contact-messages.destroy', $message))->assertForbidden();
    }
}
