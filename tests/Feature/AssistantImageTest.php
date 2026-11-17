<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AssistantConversation;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Assistant\Assistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Photos in the chat (ImageInput): the chosen accounts only, sent to Claude with one message, never saved. */
class AssistantImageTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** A 1×1 PNG */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        config(['assistant.enabled' => true, 'assistant.audience' => 'everyone', 'assistant.api_key' => 'test-key', 'assistant.base_url' => 'https://claude.test']);
        Http::fake(['claude.test/*' => Http::response(['content' => [['type' => 'text', 'text' => 'That is Jeddah.']], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])]);
    }

    private function tester(): User
    {
        $agent = User::factory()->create();
        $agent->forceFill(['role' => UserRole::Agent])->save();
        SiteSetting::updateOrCreate(['key' => Assistant::TESTERS], ['value' => (string) $agent->id]);

        return $agent;
    }

    private static function photo(): string
    {
        return 'data:image/png;base64,'.self::PNG;
    }

    /** The messages of the n-th request sent to Claude */
    private function sent(int $n): array
    {
        return Http::recorded()[$n][0]->data()['messages'];
    }

    public function test_a_chosen_account_sends_a_photo_that_goes_to_claude_once_and_is_never_saved(): void
    {
        $this->actingAs($this->tester(), 'web');
        $this->get('/')->assertSee('data-assistant-attach', false)->assertSee(__('assistant.image_hint'));

        $this->postJson('/assistant/message', ['message' => 'Where is this?', 'image' => self::photo()])->assertOk()->assertJson(['reply' => 'That is Jeddah.']);

        $last = collect($this->sent(0))->last();
        $this->assertSame('image', $last['content'][0]['type']);
        $this->assertSame(['type' => 'base64', 'media_type' => 'image/png', 'data' => self::PNG], $last['content'][0]['source']);
        $this->assertSame(['type' => 'text', 'text' => 'Where is this?'], $last['content'][1]);

        // Not in the database: only a mark that a photo was sent
        $chat = AssistantConversation::sole();
        $this->assertStringNotContainsString(self::PNG, json_encode($chat->messages));
        $this->assertTrue($chat->messages[0]['image']);

        // The next message: no image, a note in its place
        $this->postJson('/assistant/message', ['message' => 'Any cruises there?'])->assertOk();
        $json = json_encode($this->sent(1));
        $this->assertStringNotContainsString(self::PNG, $json);
        $this->assertStringContainsString('The visitor sent an image with this message', $json);
        $this->assertStringNotContainsString('"image"', json_encode(collect($this->sent(1))->pluck('content')->all()));
    }

    public function test_a_photo_alone_asks_where_the_place_is(): void
    {
        $this->actingAs($this->tester(), 'web');
        $this->postJson('/assistant/message', ['image' => self::photo()])->assertOk();

        $last = collect($this->sent(0))->last();
        $this->assertSame(__('assistant.image_default'), $last['content'][1]['text']);
    }

    public function test_other_visitors_cannot_send_photos(): void
    {
        $this->get('/')->assertSee('sc-assistant-panel', false)->assertDontSee('data-assistant-attach', false)->assertDontSee(__('assistant.image_hint'));
        $this->postJson('/assistant/message', ['message' => 'Where?', 'image' => self::photo()])->assertForbidden();

        $agent = User::factory()->create();
        $agent->forceFill(['role' => UserRole::Agent])->save();
        $this->actingAs($agent, 'web')->postJson('/assistant/message', ['message' => 'Where?', 'image' => self::photo()])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_only_real_images_are_accepted(): void
    {
        $this->actingAs($this->tester(), 'web');
        $this->postJson('/assistant/message', ['message' => 'x', 'image' => 'data:image/png;base64,'.base64_encode('<?php echo 1;')])->assertStatus(422);
        $this->postJson('/assistant/message', ['message' => 'x', 'image' => 'data:image/gif;base64,'.self::PNG])->assertStatus(422);
        $this->postJson('/assistant/message', ['message' => 'x', 'image' => 'https://example.com/a.png'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_photos_per_chat_are_limited(): void
    {
        config(['assistant.max_images_per_chat' => 1]);
        $this->actingAs($this->tester(), 'web');
        $this->postJson('/assistant/message', ['message' => 'One', 'image' => self::photo()])->assertOk();
        $this->postJson('/assistant/message', ['message' => 'Two', 'image' => self::photo()])->assertOk()->assertJson(['reply' => __('assistant.image_limit')]);
        $this->assertCount(1, Http::recorded());
    }
}
