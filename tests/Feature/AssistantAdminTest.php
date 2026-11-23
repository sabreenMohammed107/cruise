<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\Assistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantAdminTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11 12:00');
        config(['assistant.enabled' => true, 'assistant.audience' => 'everyone', 'assistant.api_key' => 'test-key', 'assistant.base_url' => 'https://claude.test']);
    }

    private function staff(string $email): User
    {
        return User::where('email', $email)->sole();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email' => 'dev@safercruise.test']);
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user;
    }

    private function chat(?User $user = null, string $locale = 'en', ?string $question = 'Any cruises in December?'): AssistantConversation
    {
        $c = AssistantConversation::start($user, $locale, '10.0.0.1');
        $c->forceFill([
            'user_messages' => 1,
            'input_tokens' => 1200,
            'output_tokens' => 300,
            'messages' => [
                ['role' => 'user', 'content' => $question, 'at' => '2026-10-11T09:05:00+00:00'],
                ['role' => 'assistant', 'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'search_cruises', 'input' => ['month' => '2026-12']]], 'at' => '2026-10-11T09:05:02+00:00'],
                ['role' => 'user', 'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => json_encode(['count' => 1, 'cruises' => [['name' => 'Red Sea Discovery']]])]], 'at' => '2026-10-11T09:05:02+00:00'],
                ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Red Sea Discovery sails on 13 December.']], 'at' => '2026-10-11T09:06:00+00:00'],
            ],
        ])->save();

        return $c;
    }

    public function test_super_admin_sees_the_chats_and_one_chat(): void
    {
        $reem = User::where('email', 'reem@example.com')->sole();
        $chat = $this->chat($reem);
        $this->chat(null, 'ar', 'هل توجد رحلات في ديسمبر؟');
        AssistantConversation::start(null, 'en', null); // opened, never used: not listed

        $dev = $this->superAdmin();
        $this->actingAs($dev, 'admin')->get('/admin/assistant')->assertOk()
            ->assertSee('Assistant chats')
            ->assertSee($reem->name)
            ->assertSee('Any cruises in December?')
            ->assertSee('هل توجد رحلات في ديسمبر؟')
            ->assertSee('Guest')
            ->assertViewHas('chats', fn ($chats) => $chats->total() === 2)
            ->assertViewHas('stats', fn ($s) => $s['today'] === 2 && $s['messages'] === 2 && $s['input'] === 2400);

        $page = $this->actingAs($dev, 'admin')->get('/admin/assistant/'.$chat->id)->assertOk()
            ->assertSee('Searched cruises')
            ->assertDontSee('&quot;month&quot;', false) // no JSON
            ->assertDontSee('Got back')
            ->assertSee('11 Oct 2026, 12:05') // 09:05 UTC in the site's time zone (Riyadh)
            ->assertSee('Customer details');
        // The account's name on its messages, not "Visitor"; newest first
        $html = $page->getContent();
        $this->assertStringContainsString('<span>'.e($reem->name).'</span>', $html);
        $this->assertLessThan(strpos($html, 'Any cruises in December?'), strpos($html, 'Red Sea Discovery sails on 13 December.'));

        // A guest's messages say "Visitor"
        $guestChat = AssistantConversation::where('locale', 'ar')->sole();
        $this->actingAs($dev, 'admin')->get('/admin/assistant/'.$guestChat->id)->assertSee('<span>Visitor</span>', false);

        // Sidebar link
        $this->actingAs($dev, 'admin')->get('/admin')->assertSee(route('admin.assistant.index'));
    }

    public function test_only_the_super_admin(): void
    {
        $chat = $this->chat();

        foreach (['admin@safercruise.test', 'supervisor@safercruise.test', 'seller@safercruise.test'] as $email) {
            $staff = $this->staff($email);
            $this->actingAs($staff, 'admin')->get('/admin/assistant')->assertForbidden();
            $this->actingAs($staff, 'admin')->get('/admin/assistant/'.$chat->id)->assertForbidden();
            $this->actingAs($staff, 'admin')->put('/admin/assistant/limits', ['per_minute' => 1, 'per_hour' => 1, 'per_chat' => 1])->assertForbidden();
            $this->actingAs($staff, 'admin')->delete('/admin/assistant/'.$chat->id)->assertForbidden();
            $this->actingAs($staff, 'admin')->get('/admin')->assertDontSee(route('admin.assistant.index'));
        }
        $this->assertModelExists($chat);

        $this->actingAs($this->superAdmin(), 'admin')->delete('/admin/assistant/'.$chat->id)->assertRedirect('/admin/assistant');
        $this->assertModelMissing($chat);

        // Customers on the website guard can't open the panel
        auth('admin')->logout();
        $this->actingAs(User::where('email', 'reem@example.com')->sole(), 'web')->get('/admin/assistant')->assertRedirect('/admin/login');
    }

    public function test_filters(): void
    {
        $reem = User::where('email', 'reem@example.com')->sole();
        $this->chat($reem);
        $this->chat(null, 'ar');
        $dev = $this->superAdmin();

        $count = fn (array $q) => $this->actingAs($dev, 'admin')->get('/admin/assistant?'.http_build_query($q))->viewData('chats')->total();

        $this->assertSame(1, $count(['who' => 'signed_in']));
        $this->assertSame(1, $count(['who' => 'guests']));
        $this->assertSame(1, $count(['lang' => 'ar']));
        $this->assertSame(1, $count(['q' => 'reem@']));
        $this->assertSame(2, $count(['period' => 'today']));

        $this->travelTo('2026-10-20');
        $this->assertSame(0, $count(['period' => 'today']));
        $this->assertSame(2, $count(['period' => '30']));
    }

    public function test_switching_the_chat_off_hides_it_from_the_website(): void
    {
        $dev = $this->superAdmin();
        $this->get('/')->assertSee('sc-assistant-panel', false);

        $this->actingAs($dev, 'admin')->put('/admin/assistant/status', ['enabled' => 0])->assertRedirect('/admin/assistant');
        $this->get('/')->assertDontSee('sc-assistant-panel', false);
        $this->postJson('/assistant/message', ['message' => 'hi'])->assertNotFound();
        $this->actingAs($dev, 'admin')->get('/admin/assistant')->assertSee('Switch the chat on');

        $this->actingAs($dev, 'admin')->put('/admin/assistant/status', ['enabled' => 1]);
        $this->get('/')->assertSee('sc-assistant-panel', false);
    }

    public function test_without_an_api_key_the_screen_says_it_is_not_set_up(): void
    {
        config(['assistant.enabled' => false]);
        $this->actingAs($this->superAdmin(), 'admin')->get('/admin/assistant')->assertOk()
            ->assertSee('Not set up')->assertDontSee('Switch the chat off');
    }

    public function test_limits_are_set_on_the_screen(): void
    {
        $dev = $this->superAdmin();
        $this->assertSame([10, 60, 30], [Assistant::limit('per_minute'), Assistant::limit('per_hour'), Assistant::limit('per_chat')]);

        $this->actingAs($dev, 'admin')->put('/admin/assistant/limits', ['per_minute' => 0, 'per_hour' => 'x', 'per_chat' => 5])
            ->assertSessionHasErrors(['per_minute', 'per_hour']);
        $this->actingAs($dev, 'admin')->put('/admin/assistant/limits', ['per_minute' => 2, 'per_hour' => 100, 'per_chat' => 1])
            ->assertRedirect('/admin/assistant');
        $this->assertSame([2, 100, 1], [Assistant::limit('per_minute'), Assistant::limit('per_hour'), Assistant::limit('per_chat')]);
        $this->actingAs($dev, 'admin')->get('/admin/assistant')->assertSee('Limits: 2 messages a minute and 100 an hour per visitor, 1 messages per chat.');

        Http::fake(['claude.test/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']], 'stop_reason' => 'end_turn', 'usage' => []])]);
        auth('admin')->logout();
        // Per chat: the second message asks for a new chat
        $this->postJson('/assistant/message', ['message' => 'one'])->assertOk()->assertJson(['reply' => 'ok']);
        $this->postJson('/assistant/message', ['message' => 'two'])->assertOk()->assertJson(['limit' => true]);
        // Per minute: the third message in a minute is refused
        $this->postJson('/assistant/message', ['message' => 'three'])->assertStatus(429);
        Http::assertSentCount(1);
    }

    public function test_cost(): void
    {
        $chat = $this->chat();
        $dev = $this->superAdmin();

        // Claude Haiku 5.5 prices by default: 1200 × $0.10 + 300 × $0.50 per million = $0.00027
        $this->actingAs($dev, 'admin')->get('/admin/assistant')
            ->assertSee('Cost this month (USD)')->assertSee('Total cost so far (USD)')->assertSee('$0.0003')
            ->assertViewHas('stats', fn ($s) => abs($s['cost_all'] - 0.00027) < 1e-9);
        $this->actingAs($dev, 'admin')->get('/admin/assistant/'.$chat->id)->assertSee('$0.0003');

        config(['assistant.price_input_per_mtok' => null]);
        $this->actingAs($dev, 'admin')->get('/admin/assistant')->assertSee('Tokens this month');
    }
}
