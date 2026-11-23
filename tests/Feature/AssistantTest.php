<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AgentRate;
use App\Models\AssistantConversation;
use App\Models\Booking;
use App\Models\Offer;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;
use App\Models\User;
use App\Services\Assistant\ToolRegistry;
use App\Services\Assistant\Tools;
use App\Services\OfferService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        config(['assistant.enabled' => true, 'assistant.audience' => 'everyone', 'assistant.api_key' => 'test-key', 'assistant.base_url' => 'https://claude.test']);
    }

    private static function text(string $text): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]];
    }

    private static function toolUse(string $name, array $input, string $id = 'toolu_1'): array
    {
        return ['content' => [['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input]], 'stop_reason' => 'tool_use', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]];
    }

    public function test_widget_shows_only_when_enabled(): void
    {
        $this->get('/')->assertOk()->assertSee('sc-assistant-panel', false)->assertSee('assets/js/assistant.js', false);

        config(['assistant.enabled' => false]);
        $this->get('/')->assertOk()->assertDontSee('sc-assistant-panel', false);
        $this->postJson('/assistant/message', ['message' => 'hi'])->assertNotFound();
    }

    public function test_runs_a_tool_and_returns_the_final_answer(): void
    {
        Http::fake(['claude.test/*' => Http::sequence()
            ->push(self::toolUse('search_cruises', ['month' => '2026-12']))
            ->push(self::text('Red Sea Discovery sails on 13 December.'))]);

        $this->postJson('/assistant/message', ['message' => 'Any cruises in December?'])
            ->assertOk()
            ->assertJson(['reply' => 'Red Sea Discovery sails on 13 December.']);

        // Second call carried the tool result with real data from the database
        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);
        [$first] = $recorded[0];
        $this->assertSame('test-key', $first->header('x-api-key')[0]);
        $this->assertContains('search_cruises', array_column($first['tools'], 'name'));
        $this->assertNotContains('get_my_bookings', array_column($first['tools'], 'name'), 'only for signed-in customers');

        [$second] = $recorded[1];
        $result = collect($second['messages'])->last()['content'][0];
        $this->assertSame('tool_result', $result['type']);
        $this->assertSame('toolu_1', $result['tool_use_id']);
        $data = json_decode($result['content'], true);
        $this->assertGreaterThan(0, $data['count']);
        foreach ($data['cruises'] as $cruise) {
            foreach ($cruise['sailings'] as $s) {
                $this->assertStringStartsWith('2026-12', $s['departure_date']);
            }
        }

        $conversation = AssistantConversation::sole();
        $this->assertSame(1, $conversation->user_messages);
        $this->assertCount(4, $conversation->messages); // user, tool_use, tool_result, answer
        $this->assertSame(30, $conversation->input_tokens + $conversation->output_tokens);
    }

    public function test_conversation_continues_in_the_same_session(): void
    {
        Http::fake(['claude.test/*' => Http::sequence()->push(self::text('Hello!'))->push(self::text('Sure.'))->push(self::text('Again.'))->push(self::text('New one.'))]);

        $this->postJson('/assistant/message', ['message' => 'Hi'])->assertOk();
        $this->postJson('/assistant/message', ['message' => 'Tell me more'])->assertOk();

        $this->assertSame(1, AssistantConversation::count());
        [$second] = Http::recorded()[1];
        $this->assertCount(3, $second['messages']);
        $this->assertSame('Tell me more', $second['messages'][2]['content']);

        // The widget names the chat (to keep its messages on screen across pages), the same on the next page
        $chat = $this->postJson('/assistant/message', ['message' => 'Hi'])->json('chat');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{20}$/', $chat);
        $this->assertStringNotContainsString(AssistantConversation::sole()->token, $chat);
        $this->get('/')->assertSee('data-chat="'.$chat.'"', false);

        // "New chat" starts over
        $this->postJson('/assistant/reset')->assertOk();
        $this->get('/')->assertSee('data-chat=""', false);
        $this->postJson('/assistant/message', ['message' => 'Hi again'])->assertOk();
        $this->assertSame(2, AssistantConversation::count());
    }

    public function test_thinking_goes_back_only_within_the_same_turn(): void
    {
        $thinking = ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'];
        $toolUse = self::toolUse('get_policies', []);
        array_unshift($toolUse['content'], $thinking);
        $answer = self::text('Done.');
        array_unshift($answer['content'], $thinking);
        Http::fake(['claude.test/*' => Http::sequence()->push($toolUse)->push($answer)->push(self::text('Again.'))]);

        $this->postJson('/assistant/message', ['message' => 'Rules?'])->assertOk();
        $this->postJson('/assistant/message', ['message' => 'And?'])->assertOk()->assertJson(['reply' => 'Again.']);

        // Within the turn: the tool call goes back with its thinking
        [$second] = Http::recorded()[1];
        $this->assertSame('thinking', $second['messages'][1]['content'][0]['type']);
        // The next turn: no earlier thinking (it is tied to the earlier request), the rest unchanged
        [$third] = Http::recorded()[2];
        $this->assertStringNotContainsString('"thinking"', json_encode($third['messages']));
        $this->assertSame(['tool_use'], array_column($third['messages'][1]['content'], 'type'));
        $this->assertSame('Done.', $third['messages'][3]['content'][0]['text']);
        // Saved as received
        $this->assertSame('thinking', AssistantConversation::sole()->messages[1]['content'][0]['type']);
    }

    public function test_a_long_chat_sends_the_same_history_in_every_tool_round(): void
    {
        // Only the last 4 messages are sent; a tool round adds 2, which must not move the start within the turn
        config(['assistant.history_messages' => 4]);
        $toolUse = self::toolUse('get_policies', []);
        array_unshift($toolUse['content'], ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig']);
        Http::fake(['claude.test/*' => Http::sequence()
            ->push(self::text('One.'))->push(self::text('Two.'))
            ->push($toolUse)->push(self::text('Three.'))]);

        $this->postJson('/assistant/message', ['message' => 'a'])->assertOk();
        $this->postJson('/assistant/message', ['message' => 'b'])->assertOk();
        $this->postJson('/assistant/message', ['message' => 'c'])->assertOk()->assertJson(['reply' => 'Three.']);

        [$round1] = Http::recorded()[2];
        [$round2] = Http::recorded()[3];
        // Round 2 = round 1 + the tool call (with its thinking) + its result
        $this->assertSame($round1['messages'], array_slice($round2['messages'], 0, count($round1['messages'])));
        $this->assertCount(count($round1['messages']) + 2, $round2['messages']);
        $this->assertSame('thinking', $round2['messages'][count($round1['messages'])]['content'][0]['type']);
        // Our message times are never sent to Claude
        $this->assertStringNotContainsString('"at"', $round2->body());
        $this->assertArrayHasKey('at', AssistantConversation::sole()->messages[0]);
    }

    public function test_a_refused_request_is_tried_again_without_thinking_then_alone(): void
    {
        $refused = Http::response(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'messages.1.content.0: Invalid `signature` in `thinking` block']], 400);
        $toolUse = self::toolUse('get_policies', []);
        array_unshift($toolUse['content'], ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig']);
        Http::fake(['claude.test/*' => Http::sequence()
            ->push($toolUse)
            ->pushResponse($refused)->push(self::text('Answer without thinking.'))   // 2nd try works
            ->pushResponse($refused)->pushResponse($refused)->push(self::text('Answer alone.'))]); // 3rd try works

        $this->postJson('/assistant/message', ['message' => 'Rules?'])->assertOk()->assertJson(['reply' => 'Answer without thinking.']);
        [$retry] = Http::recorded()[2];
        $this->assertStringNotContainsString('"thinking"', $retry->body());

        $this->postJson('/assistant/message', ['message' => 'And the age rules?'])->assertOk()->assertJson(['reply' => 'Answer alone.']);
        [$alone] = Http::recorded()[5];
        $this->assertSame([['role' => 'user', 'content' => 'And the age rules?']], $alone['messages']);
    }

    public function test_a_cut_off_answer_never_leaves_a_tool_call_without_its_result(): void
    {
        $cut = self::toolUse('get_policies', []);
        $cut['stop_reason'] = 'max_tokens';
        array_unshift($cut['content'], ['type' => 'text', 'text' => 'Let me check']);
        Http::fake(['claude.test/*' => Http::sequence()->push($cut)->push(self::text('Next.'))]);

        $this->postJson('/assistant/message', ['message' => 'Rules?'])->assertOk()->assertJson(['reply' => 'Let me check']);
        $this->assertStringNotContainsString('tool_use', json_encode(AssistantConversation::sole()->messages));
        $this->postJson('/assistant/message', ['message' => 'Hello?'])->assertOk()->assertJson(['reply' => 'Next.']);
    }

    public function test_a_broken_tool_does_not_stop_the_chat(): void
    {
        $this->app->bind(Tools\GetPolicies::class, fn () => throw new \RuntimeException('broken'));
        Http::fake(['claude.test/*' => Http::response(self::text('Still here.'))]);

        $this->postJson('/assistant/message', ['message' => 'Hi'])->assertOk()->assertJson(['reply' => 'Still here.']);
        [$request] = Http::recorded()[0];
        $this->assertNotContains('get_policies', array_column($request['tools'], 'name'));
        $this->assertContains('search_cruises', array_column($request['tools'], 'name'));
    }

    public function test_api_failure_gives_a_friendly_message(): void
    {
        Http::fake(['claude.test/*' => Http::response(['error' => ['message' => 'bad']], 400)]);

        $this->postJson('/assistant/message', ['message' => 'Hi'])
            ->assertStatus(503)
            ->assertJson(['reply' => __('assistant.error'), 'error' => true]);
    }

    public function test_message_rules_and_conversation_limit(): void
    {
        $this->postJson('/assistant/message', ['message' => ''])->assertUnprocessable();
        $this->postJson('/assistant/message', ['message' => str_repeat('a', 1001)])->assertUnprocessable();

        config(['assistant.max_user_messages' => 1]);
        Http::fake(['claude.test/*' => Http::response(self::text('ok'))]);
        $this->postJson('/assistant/message', ['message' => 'one'])->assertOk();
        $this->postJson('/assistant/message', ['message' => 'two'])->assertOk()->assertJson(['limit' => true]);
        Http::assertSentCount(1);
    }

    public function test_tool_rounds_are_capped(): void
    {
        config(['assistant.max_tool_rounds' => 2]);
        Http::fake(['claude.test/*' => Http::response(self::toolUse('get_policies', []))]);

        $this->postJson('/assistant/message', ['message' => 'loop'])->assertOk()->assertJson(['reply' => __('assistant.fallback')]);
        Http::assertSentCount(3);

        // History still alternates and ends with the assistant, so the next message is a valid request
        $messages = AssistantConversation::sole()->messages;
        $this->assertSame('assistant', end($messages)['role']);
        // An empty tool input is sent back as a JSON object
        [$last] = Http::recorded()[2];
        $this->assertStringContainsString('"input":{}', $last->body());
    }

    public function test_unknown_tool_and_bad_input_come_back_as_errors(): void
    {
        $registry = app(ToolRegistry::class);
        $tools = $registry->for(null);

        [$result, $error] = $registry->run($tools, 'drop_tables', [], null);
        $this->assertTrue($error);

        [$result, $error] = $registry->run($tools, 'get_cruise_details', ['slug' => 'nope'], null);
        $this->assertTrue($error);

        [$result, $error] = $registry->run($tools, 'get_my_bookings', [], null);
        $this->assertTrue($error, 'not offered to guests');
    }

    public function test_quote_matches_the_booking_page_price(): void
    {
        $price = SailingCabinPrice::whereHas('sailing', fn ($q) => $q->where('code', 'RSD-261213'))
            ->whereHas('cabinCategory', fn ($q) => $q->where('slug', 'balcony'))->with('sailing', 'cabinCategory')->sole();
        $expected = app(PricingService::class)->quoteBooking(
            [['price' => $price, 'guests' => ['adult' => 2, 'child' => 1, 'infant' => 0]]],
            null,
            app(OfferService::class)->forSailing($price->sailing, null),
            $price->sailing->departure_date,
        );

        $quote = app(Tools\QuotePrice::class)->handle(['sailing_code' => 'RSD-261213', 'cabin' => 'Balcony', 'adults' => 2, 'children' => 1], null);

        $this->assertArrayNotHasKey('error', $quote);
        $this->assertEquals(round($expected['total'], 2), $quote['total']['usd']);
        $this->assertEquals(round($expected['fees'], 2), $quote['port_taxes']['usd']);
        $this->assertStringContainsString('/cruises/', $quote['book_url']);
    }

    public function test_quote_applies_the_guest_rules(): void
    {
        $tool = app(Tools\QuotePrice::class);

        $this->assertArrayHasKey('error', $tool->handle(['sailing_code' => 'RSD-261213', 'cabin' => 'balcony', 'adults' => 0, 'infants' => 1], null));
        $this->assertArrayHasKey('error', $tool->handle(['sailing_code' => 'RSD-261213', 'cabin' => 'balcony', 'adults' => 30], null));
        $this->assertArrayHasKey('error', $tool->handle(['sailing_code' => 'NO-SUCH', 'cabin' => 'balcony', 'adults' => 2], null));
        $this->assertArrayHasKey('error', $tool->handle(['sailing_code' => 'RSD-261213', 'cabin' => 'penthouse', 'adults' => 2], null));
    }

    public function test_my_bookings_are_only_the_signed_in_customers(): void
    {
        $reem = User::where('email', 'reem@example.com')->sole();
        $other = Booking::whereNull('user_id')->orWhere('user_id', '!=', $reem->id)->first();

        $result = app(Tools\GetMyBookings::class)->handle(['which' => 'all'], $reem);
        $this->assertGreaterThan(0, $result['count']);
        $references = array_column($result['bookings'], 'reference');
        $this->assertEqualsCanonicalizing(Booking::where('user_id', $reem->id)->pluck('reference')->all(), $references);
        if ($other) {
            $this->assertNotContains($other->reference, $references);
        }

        // Signed in, the tool is offered; the conversation belongs to that account
        Http::fake(['claude.test/*' => Http::response(self::text('Here are your trips.'))]);
        $this->actingAs($reem, 'web')->postJson('/assistant/message', ['message' => 'My bookings?'])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => in_array('get_my_bookings', array_column($r['tools'], 'name'), true));
        $this->assertSame($reem->id, AssistantConversation::sole()->user_id);
    }

    public function test_from_prices_are_the_ones_on_the_cruise_cards(): void
    {
        $rsd = fn (?User $user) => collect(app(Tools\SearchCruises::class)->handle(['month' => '2026-12'], $user)['cruises'])
            ->pluck('sailings')->flatten(1)->firstWhere('code', 'RSD-261213');
        $sailing = Sailing::where('code', 'RSD-261213')->sole();

        // A guest, no offer: lowest Double ÷ 2
        $this->assertEquals($sailing->fromPrice(), $rsd(null)['from_price_per_person']['usd']);
        $this->assertNull($rsd(null)['price_before_offer']);

        // An agent: the agent rate, like the card they see
        $agent = User::factory()->create();
        $agent->forceFill(['role' => UserRole::Agent])->save();
        AgentRate::create(['percent' => 5]);
        $this->assertEquals(round($sailing->fromPrice() * 0.95, 2), $rsd($agent)['from_price_per_person']['usd']);

        // A fare offer: the price after it, with the price before and the offer's name
        Offer::create(['name' => 'Summer deal', 'name_ar' => 'عرض الصيف', 'cruise_id' => $sailing->cruise_id,
            'target' => 'fare', 'type' => 'percent', 'value' => 10, 'ends_on' => '2026-10-31']);
        app()->forgetInstance(OfferService::class); // live offers are read once per request
        $guest = $rsd(null);
        $this->assertEquals($sailing->fromPrice(), $guest['price_before_offer']['usd']);
        $this->assertEquals(round($sailing->fromPrice() * 0.9, 2), $guest['from_price_per_person']['usd']);
        $this->assertSame(['Summer deal'], $guest['offers']);
    }

    public function test_the_chat_gives_contact_details_but_never_says_it_sent_anything(): void
    {
        Http::fake(['claude.test/*' => Http::response(self::text('ok'))]);
        $this->postJson('/assistant/message', ['message' => 'I want to talk to someone'])->assertOk();

        [$request] = Http::recorded()[0];
        $this->assertContains('contact_team', array_column($request['tools'], 'name'));
        $this->assertStringContainsString('Never say or suggest that you sent, forwarded or passed', json_encode($request['system']));
        $this->assertStringContainsString('does not send anything to the team', $request->body());

        // The tool only gives the details, and says so
        $contact = app(Tools\ContactTeam::class)->handle(['summary' => 'Hi'], null);
        $this->assertStringStartsWith('https://wa.me/', $contact['whatsapp_url']);
        $this->assertStringContainsString('Nothing was sent', $contact['note']);
    }

    public function test_other_tools_return_site_data(): void
    {
        $details = app(Tools\GetCruiseDetails::class)->handle(['slug' => 'red-sea-discovery'], null);
        $this->assertNotEmpty($details['itinerary']);
        $this->assertNotEmpty($details['sailings'][0]['cabins']);

        $this->assertNotEmpty(app(Tools\ListDestinations::class)->handle([], null)['destinations']);
        $this->assertNotEmpty(app(Tools\GetShipInfo::class)->handle(['topics' => ['faqs']], null)['ships'][0]['faqs']);
        $this->assertNotEmpty(app(Tools\GetPolicies::class)->handle([], null)['cancellation']);

        // Arabic copy when the site is in Arabic
        app()->setLocale('ar');
        $ar = app(Tools\GetPolicies::class)->handle([], null);
        $this->assertSame(config('cruise.terms.cancellation_ar')[0][1], $ar['cancellation'][0]['penalty']);
    }
}
