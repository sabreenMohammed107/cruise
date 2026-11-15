<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AssistantConversation;
use App\Models\Booking;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Assistant\Assistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The chat's test mode (who sees it) and booking from the chat (prepare_booking + the "Confirm booking" button). */
class AssistantBookingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        Mail::fake();
        config(['assistant.enabled' => true, 'assistant.audience' => 'testers', 'assistant.api_key' => 'test-key', 'assistant.base_url' => 'https://claude.test']);
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private static function text(string $text): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]];
    }

    private static function prepare(array $input): array
    {
        return ['content' => [
            ['type' => 'text', 'text' => 'Here is the summary.'],
            ['type' => 'tool_use', 'id' => 'toolu_b', 'name' => 'prepare_booking', 'input' => $input],
        ], 'stop_reason' => 'tool_use', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]];
    }

    private static function booking(array $overrides = []): array
    {
        return $overrides + [
            'sailing_code' => 'RSD-261213',
            'cabins' => [[
                'cabin' => 'Balcony',
                'travellers' => [
                    ['type' => 'adult', 'first_name' => 'Ahmed', 'last_name' => 'Ali', 'date_of_birth' => '1985-04-02', 'gender' => 'male', 'nationality' => 'Egyptian'],
                    ['type' => 'adult', 'first_name' => 'Mona', 'last_name' => 'Ali', 'date_of_birth' => '1988-07-19', 'gender' => 'female', 'nationality' => 'egypt'],
                ],
            ]],
            'contact_email' => 'ahmed@example.com',
            'contact_dial_code' => '+20',
            'contact_phone' => '1001234567',
        ];
    }

    public function test_test_mode_shows_the_chat_only_to_the_super_admin_and_chosen_agents(): void
    {
        $chosen = $this->user(UserRole::Agent);
        $otherAgent = $this->user(UserRole::Agent);
        SiteSetting::updateOrCreate(['key' => Assistant::TESTERS], ['value' => (string) $chosen->id]);

        // Guests, other agents, customers and admins: no chat, and messages are refused
        $this->get('/')->assertDontSee('sc-assistant-panel', false);
        $this->postJson('/assistant/message', ['message' => 'hi'])->assertNotFound();
        foreach ([$otherAgent, $this->user(UserRole::Customer), $this->user(UserRole::Admin)] as $user) {
            $this->actingAs($user, 'web')->get('/')->assertDontSee('sc-assistant-panel', false);
            $this->actingAs($user, 'web')->postJson('/assistant/message', ['message' => 'hi'])->assertNotFound();
            auth('web')->logout();
        }

        $this->actingAs($chosen, 'web')->get('/')->assertSee('sc-assistant-panel', false);
        auth('web')->logout();
        $super = $this->user(UserRole::SuperAdmin);
        $this->actingAs($super, 'web')->get('/')->assertSee('sc-assistant-panel', false);
        auth('web')->logout();
        // Signed in to the panel only
        $this->actingAs($super, 'admin')->get('/')->assertSee('sc-assistant-panel', false);

        // "Everyone"
        auth('admin')->logout();
        SiteSetting::updateOrCreate(['key' => Assistant::AUDIENCE], ['value' => 'everyone']);
        $this->get('/')->assertSee('sc-assistant-panel', false);
    }

    public function test_super_admin_chooses_who_sees_the_chat(): void
    {
        $agent = $this->user(UserRole::Agent);
        $customer = $this->user(UserRole::Customer);
        $super = $this->user(UserRole::SuperAdmin);

        $this->actingAs($super, 'admin')->get('/admin/assistant')->assertOk()->assertSee('Who sees the chat')->assertSee($agent->email);

        $this->actingAs($super, 'admin')->put('/admin/assistant/audience', ['audience' => 'testers', 'testers' => [$customer->id]])
            ->assertSessionHasErrors('testers.0');
        $this->actingAs($super, 'admin')->put('/admin/assistant/audience', ['audience' => 'testers', 'testers' => [$agent->id]])
            ->assertRedirect('/admin/assistant');
        $this->assertSame([$agent->id], Assistant::testers());
        $this->assertSame('testers', Assistant::audience());

        // Other staff can't
        $this->actingAs($this->user(UserRole::Admin), 'admin')->put('/admin/assistant/audience', ['audience' => 'everyone'])->assertForbidden();
        $this->assertSame('testers', Assistant::audience());
    }

    public function test_an_agent_books_from_the_chat_only_by_pressing_confirm(): void
    {
        $agent = $this->user(UserRole::Agent);
        SiteSetting::updateOrCreate(['key' => Assistant::TESTERS], ['value' => (string) $agent->id]);
        Http::fake(['claude.test/*' => Http::sequence()->push(self::prepare(self::booking()))->push(self::text('Your booking is done.'))]);
        $before = Booking::count();

        $res = $this->actingAs($agent, 'web')->postJson('/assistant/message', ['message' => 'Book it for Ahmed and Mona Ali'])->assertOk();
        $card = collect($res->json('ui'))->firstWhere('type', 'booking');
        $this->assertNotNull($card);
        $this->assertSame('Here is the summary.', $res->json('reply'));
        $this->assertStringContainsString('Ahmed Ali', implode(' ', $card['lines']));
        $this->assertSame($before, Booking::count(), 'nothing is booked before the click');
        $this->assertCount(1, Http::recorded(), 'the turn ends with the summary');

        // A wrong or old button does nothing, and leaves the real summary bookable
        $this->postJson('/assistant/booking', ['id' => 'nope'])->assertStatus(422);

        $done = $this->actingAs($agent, 'web')->postJson('/assistant/booking', ['id' => $card['id']])->assertCreated();
        $booking = Booking::latest('id')->first();
        $this->assertSame($before + 1, Booking::count());
        $this->assertSame($done->json('reference'), $booking->reference);
        $this->assertSame($agent->id, $booking->agent_id);
        $this->assertSame(2, $booking->adults);
        $this->assertSame(['Ahmed', 'Mona'], $booking->travellers()->orderBy('slot')->pluck('first_name')->all());
        $this->assertSame('Egypt', $booking->travellers()->first()->nationality);
        $this->assertSame('ahmed@example.com', $booking->contact_email);
        $this->assertStringContainsString($booking->reference, $done->json('reply'));
        $this->get($done->json('url'))->assertOk();

        // The chat knows, and a second click can't book again
        $last = collect(AssistantConversation::sole()->messages)->last();
        $this->assertSame('assistant', $last['role']);
        $this->assertStringContainsString($booking->reference, json_encode(AssistantConversation::sole()->messages));
        $this->actingAs($agent, 'web')->postJson('/assistant/booking', ['id' => $card['id']])->assertStatus(422);
        $this->assertSame($before + 1, Booking::count());

        // The next message goes to Claude with a valid history (the button's note after the tool result)
        $this->actingAs($agent, 'web')->postJson('/assistant/message', ['message' => 'Thanks'])->assertOk();
        [$next] = Http::recorded()[1];
        $roles = array_column($next['messages'], 'role');
        $this->assertSame(['user', 'assistant', 'user', 'assistant', 'user'], $roles);
    }

    public function test_wrong_details_come_back_to_claude_and_nothing_is_kept(): void
    {
        $super = $this->user(UserRole::SuperAdmin);
        $bad = self::booking();
        $bad['cabins'][0]['travellers'][] = ['type' => 'child', 'first_name' => 'Omar', 'last_name' => 'Ali', 'date_of_birth' => '1990-01-01', 'gender' => 'male', 'nationality' => 'Egypt'];
        $bad['contact_email'] = 'not-an-email';
        $before = Booking::count();
        Http::fake(['claude.test/*' => Http::sequence()->push(self::prepare($bad))->push(self::text('Please check the details.'))]);

        $this->actingAs($super, 'web')->postJson('/assistant/message', ['message' => 'Book'])
            ->assertOk()->assertJson(['reply' => 'Please check the details.']);

        [$second] = Http::recorded()[1];
        $result = json_decode(collect($second['messages'])->last()['content'][0]['content'], true);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['problems']);
        $this->assertSame($before, Booking::count());
    }
}
