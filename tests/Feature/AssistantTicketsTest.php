<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AssistantPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Admin → Assistant tickets: packages of tickets for the website chat (App\Services\Assistant\Tickets). */
class AssistantTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-16 10:00');
        config([
            'assistant.enabled' => true, 'assistant.audience' => 'everyone', 'assistant.api_key' => 'test-key', 'assistant.base_url' => 'https://claude.test',
            // $0.10 per million input tokens, $0.50 per million output tokens
            'assistant.price_input_per_mtok' => 0.10, 'assistant.price_output_per_mtok' => 0.50,
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email' => 'dev@example.com']);
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user;
    }

    /** One Claude answer that cost $0.10 (1M input tokens, nothing out) */
    private function fakeClaude(): void
    {
        Http::fake(['claude.test/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Hello!']], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 1_000_000, 'output_tokens' => 0]])]);
    }

    private function package(array $override = []): AssistantPackage
    {
        return AssistantPackage::create($override + ['tickets' => 2000, 'price_usd' => 20, 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
    }

    public function test_only_the_super_admin_opens_the_page_and_creates_packages(): void
    {
        foreach (['admin@safercruise.test', 'supervisor@safercruise.test', 'seller@safercruise.test'] as $email) {
            $staff = User::where('email', $email)->sole();
            $this->actingAs($staff, 'admin')->get('/admin/assistant-tickets')->assertForbidden();
            $this->actingAs($staff, 'admin')->post('/admin/assistant/package', ['tickets' => 10, 'price_usd' => 1])->assertForbidden();
        }

        $dev = $this->superAdmin();
        $this->actingAs($dev, 'admin')->get('/admin/assistant-tickets')->assertOk()->assertSee('No package yet');
        $this->actingAs($dev, 'admin')->get('/admin')->assertSee(route('admin.assistant-tickets.index'));

        // Created (and renewed) on Admin → Assistant chats
        $this->actingAs($dev, 'admin')->get('/admin/assistant')->assertOk()->assertSee('Tickets package')->assertSee('Start package');
        $this->actingAs($dev, 'admin')->post('/admin/assistant/package', ['tickets' => 2000, 'price_usd' => 20])->assertRedirect();
        $package = AssistantPackage::sole();
        $this->assertSame(2000, $package->tickets);
        $this->assertTrue($package->starts_at->equalTo(now()));
        $this->assertTrue($package->ends_at->equalTo(now()->addMonthNoOverflow()), 'one month from the start');
        $this->actingAs($dev, 'admin')->get('/admin/assistant')->assertSee('$20.00 for 2,000 tickets');
    }

    public function test_the_tickets_page_shows_tickets_and_chats_but_no_prices_or_renewing(): void
    {
        $this->fakeClaude();
        $this->package();
        $this->postJson('/assistant/message', ['message' => 'Any cruises?'])->assertOk();

        $page = $this->actingAs($this->superAdmin(), 'admin')->get('/admin/assistant-tickets')->assertOk()
            ->assertSee('Active')->assertSee('1,990 / 2,000')
            ->assertSee('Conversations today')->assertSee('Conversations in this package')->assertSee('Messages in this package')->assertSee('Any cruises?');
        // What the page shows (the layout's scripts carry the site's currency settings)
        $shown = preg_replace('#<script\b.*?</script>#si', '', $page->getContent());
        $this->assertDoesNotMatchRegularExpression('/\$\s?\d/', $shown, 'no dollar amounts');
        foreach (['USD', 'Cost', 'Renew', route('admin.assistant.package')] as $text) {
            $this->assertStringNotContainsString($text, $shown);
        }
    }

    public function test_the_tickets_page_only_counts_and_lists_the_packages_conversations(): void
    {
        $this->fakeClaude();
        $this->postJson('/assistant/message', ['message' => 'Before the package'])->assertOk();

        $this->travel(5)->minutes();
        $this->package();
        $this->travel(5)->minutes();
        // Another visitor: a new conversation
        $this->flushSession();
        $this->postJson('/assistant/message', ['message' => 'During the package'])->assertOk();

        $this->actingAs($this->superAdmin(), 'admin')->get('/admin/assistant-tickets')->assertOk()
            ->assertSee('During the package')->assertDontSee('Before the package')
            ->assertViewHas('stats', ['today' => 1, 'conversations' => 1, 'messages' => 1]);
        // The super admin's page still lists every conversation
        $this->get('/admin/assistant')->assertSee('Before the package')->assertSee('During the package');
    }

    public function test_a_new_package_starts_new_conversations(): void
    {
        $this->fakeClaude();
        $this->postJson('/assistant/message', ['message' => 'Before the package'])->assertOk();
        $this->postJson('/assistant/message', ['message' => 'Still before'])->assertOk();
        $this->assertSame(1, \App\Models\AssistantConversation::count(), 'one conversation');

        $this->travel(5)->minutes();
        $this->package();
        $this->travel(5)->minutes();
        // Same visitor, same browser: the old conversation stays with the time before the package
        $this->postJson('/assistant/message', ['message' => 'After the renewal'])->assertOk();
        $this->assertSame(2, \App\Models\AssistantConversation::count());
        $this->assertSame(2, \App\Models\AssistantConversation::oldest('id')->first()->user_messages);

        $this->actingAs($this->superAdmin(), 'admin')->get('/admin/assistant-tickets')
            ->assertViewHas('stats', ['today' => 1, 'conversations' => 1, 'messages' => 1])
            ->assertSee('After the renewal')->assertDontSee('Before the package');
    }

    public function test_messages_of_a_package_can_be_counted_from_the_transcripts(): void
    {
        $this->fakeClaude();
        $this->postJson('/assistant/message', ['message' => 'Before'])->assertOk();
        $this->travel(5)->minutes();
        $package = $this->package();
        $this->travel(1)->minutes();
        $this->flushSession();
        $this->postJson('/assistant/message', ['message' => 'One'])->assertOk();
        $this->postJson('/assistant/message', ['message' => 'Two'])->assertOk();

        $this->assertSame(2, $package->fresh()->messages);
        $package->forceFill(['messages' => 0])->save();
        $this->assertSame(2, $package->fresh()->countMessagesFromConversations(), 'the message before the package is not counted');
    }

    public function test_without_a_package_the_chat_runs_as_before(): void
    {
        $this->fakeClaude();
        $this->postJson('/assistant/message', ['message' => 'Hi'])->assertOk()->assertJson(['reply' => 'Hello!']);
    }

    public function test_each_message_uses_tickets_in_proportion_to_its_cost(): void
    {
        $this->fakeClaude();
        $package = $this->package();

        // $0.10 at 100 tickets per dollar
        $this->postJson('/assistant/message', ['message' => 'Hi'])->assertOk();
        $this->assertEqualsWithDelta(10.0, $package->fresh()->tickets_used, 0.0001);
        $this->postJson('/assistant/message', ['message' => 'Again'])->assertOk();
        $this->assertEqualsWithDelta(20.0, $package->fresh()->tickets_used, 0.0001);
    }

    public function test_the_chat_stops_when_the_tickets_are_used_up_until_renewed(): void
    {
        $this->fakeClaude();
        // 15 tickets for $0.15: 100 tickets per dollar, 10 a message
        $package = $this->package(['tickets' => 15, 'price_usd' => 0.15]);

        $this->postJson('/assistant/message', ['message' => 'Hi'])->assertOk();
        $this->postJson('/assistant/message', ['message' => 'Again'])->assertOk();
        $this->assertTrue($package->fresh()->isUsedUp());
        $this->postJson('/assistant/message', ['message' => 'Third'])->assertNotFound();
        $this->get('/')->assertDontSee('sc-assistant-panel', false);

        $dev = $this->superAdmin();
        $this->actingAs($dev, 'admin')->get('/admin/assistant-tickets')->assertSee('Used up');
        $this->actingAs($dev, 'admin')->get('/admin/assistant')->assertSee('Used up: renew');
        $this->actingAs($dev, 'admin')->post('/admin/assistant/package', ['tickets' => 2000, 'price_usd' => 20])->assertRedirect();
        auth('admin')->logout();

        $this->assertTrue($package->fresh()->ends_at->lessThanOrEqualTo(now()), 'the renewal replaces the old package');
        $this->postJson('/assistant/message', ['message' => 'Back?'])->assertOk();
        $this->assertEqualsWithDelta(10.0, AssistantPackage::latest('id')->first()->tickets_used, 0.0001, 'the new package counts from zero');
        // Messages are counted per package
        $this->assertSame(2, $package->fresh()->messages);
        $this->assertSame(1, AssistantPackage::latest('id')->first()->messages);
        $this->actingAs($dev, 'admin')->get('/admin/assistant-tickets')->assertOk()->assertSeeInOrder(['1', 'Messages in this package'])
            // Packages table: tickets, used, messages, status
            ->assertSeeInOrder(['Packages', '2,000', '10', '1', 'Active', '15', '20', '2', 'Ended'], false);
    }

    public function test_the_package_ends_after_a_month_even_with_tickets_left(): void
    {
        $this->fakeClaude();
        $this->package();
        $this->postJson('/assistant/message', ['message' => 'Hi'])->assertOk();

        $this->travelTo(now()->addMonth()->addMinute());
        $this->postJson('/assistant/message', ['message' => 'Still there?'])->assertNotFound();
    }

    public function test_a_package_starting_later_waits_for_its_start(): void
    {
        $this->fakeClaude();
        $this->package(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addMonth()]);

        $this->postJson('/assistant/message', ['message' => 'Hi'])->assertNotFound();
        $this->travelTo(now()->addDays(2)->addMinute());
        $this->postJson('/assistant/message', ['message' => 'Hi'])->assertOk();
    }
}
