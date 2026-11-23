<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AssistantConversation;
use App\Models\AssistantReport;
use App\Models\Booking;
use App\Models\User;
use App\Services\Assistant\Reports\ReportEngine;
use App\Services\Assistant\Tools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class AssistantReportsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private User $reem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11');
        config(['assistant.enabled' => true, 'assistant.audience' => 'everyone', 'assistant.api_key' => 'test-key', 'assistant.base_url' => 'https://claude.test']);
        $this->reem = User::where('email', 'reem@example.com')->sole();
    }

    private function report(array $spec, ?User $user = null): array
    {
        $engine = app(ReportEngine::class);
        $user ??= $this->reem;

        return $engine->run($engine->normalize($spec, $user), $user);
    }

    private function agent(): User
    {
        $agent = User::factory()->create(['email' => 'agent@example.com']);
        $agent->forceFill(['role' => UserRole::Agent])->save();

        return $agent;
    }

    public function test_bookings_report_has_only_the_users_own_bookings(): void
    {
        $other = User::factory()->create();
        Booking::where('user_id', $this->reem->id)->first()->replicate(['reference'])->forceFill(['reference' => 'SC-OTHER', 'user_id' => $other->id])->save();

        $all = $this->report(['dataset' => 'bookings', 'filters' => ['status' => 'all']]);
        $this->assertEqualsCanonicalizing(
            Booking::where('user_id', $this->reem->id)->pluck('reference')->all(),
            array_column($all['rows'], 'reference'),
        );
        $this->assertNotContains('SC-OTHER', array_column($all['rows'], 'reference'));

        // Default: confirmed only, default columns, totals of the money columns
        $confirmed = $this->report(['dataset' => 'bookings']);
        $this->assertSame(Booking::where('user_id', $this->reem->id)->where('status', 'confirmed')->count(), $confirmed['count']);
        $this->assertSame(['reference', 'customer', 'cruise', 'departure', 'total', 'paid', 'due'], array_column($confirmed['columns'], 'key'));
        $this->assertEquals(array_sum(array_column($confirmed['rows'], 'due')), $confirmed['totals']['due']);
    }

    public function test_filters_period_columns_and_sort(): void
    {
        $due = $this->report(['dataset' => 'bookings', 'filters' => ['payment' => 'due'], 'columns' => ['reference', 'due', 'nonsense']]);
        $this->assertSame(['reference', 'due'], array_column($due['columns'], 'key'));
        $this->assertSame(['SC2611-K4P2Q'], array_column($due['rows'], 'reference'));

        $november = $this->report(['dataset' => 'bookings', 'from' => '2026-11-01', 'to' => '2026-11-30']);
        $this->assertSame(['SC2611-K4P2Q'], array_column($november['rows'], 'reference'));

        $desc = $this->report(['dataset' => 'bookings', 'sort' => ['by' => 'departure', 'dir' => 'desc']]);
        $dates = array_column($desc['rows'], 'departure');
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);

        $payments = $this->report(['dataset' => 'payments', 'filters' => ['type' => 'refund']]);
        $this->assertGreaterThan(0, $payments['count']);
        $this->assertLessThan(0, $payments['rows'][0]['amount'], 'refunds count as negative');
    }

    public function test_commission_columns_are_for_agents_only(): void
    {
        $this->assertArrayNotHasKey('commission', app(ReportEngine::class)->columns('bookings', $this->reem));
        $this->assertNotContains('commission', array_column($this->report(['dataset' => 'bookings', 'columns' => ['reference', 'commission']])['columns'], 'key'));

        $agent = $this->agent();
        Booking::where('reference', 'SC2611-K4P2Q')->update(['agent_id' => $agent->id, 'agent_discount_total' => 400]);
        $report = $this->report(['dataset' => 'bookings', 'columns' => ['reference', 'original', 'commission', 'total']], $agent);
        $this->assertSame(['SC2611-K4P2Q'], array_column($report['rows'], 'reference'), 'bookings the agent made for clients');
        $this->assertEquals(400, $report['totals']['commission']);
    }

    public function test_sailings_report(): void
    {
        $report = $this->report(['dataset' => 'sailings', 'from' => '2026-12-01', 'to' => '2026-12-31', 'filters' => ['cabin' => 'balcony']]);
        $this->assertGreaterThan(0, $report['count']);
        foreach ($report['rows'] as $row) {
            $this->assertStringStartsWith('2026-12', $row['departure']);
            $this->assertSame('Balcony Cabin', $row['cabin']);
        }
        $this->assertSame([], $report['totals'], 'no totals of prices');
    }

    public function test_arabic_labels(): void
    {
        app()->setLocale('ar');
        $report = $this->report(['dataset' => 'bookings', 'filters' => ['status' => 'all'], 'columns' => ['reference', 'status']]);
        $this->assertSame(['رقم الحجز', 'الحالة'], array_column($report['columns'], 'label'));
        $this->assertContains('ملغي', array_column($report['rows'], 'status'));

        // Cancelled bookings have nothing due
        app()->setLocale('en');
        $cancelled = $this->report(['dataset' => 'bookings', 'filters' => ['status' => 'cancelled'], 'columns' => ['reference', 'paid', 'due']]);
        $this->assertGreaterThan(0, $cancelled['count']);
        $this->assertEquals(0, $cancelled['totals']['due']);
    }

    public function test_run_report_tool_saves_the_report_and_gives_the_widget_a_table(): void
    {
        $tool = app(Tools\RunReport::class);
        $result = $tool->handle(['dataset' => 'bookings', 'filters' => ['payment' => 'due']], $this->reem);

        $report = AssistantReport::sole();
        $this->assertSame($this->reem->id, $report->user_id);
        $this->assertSame($report->id, $result['report_id']);
        $this->assertSame(1, $result['rows']);
        $due = Booking::where('reference', 'SC2611-K4P2Q')->sole()->amountDue();
        $this->assertSame(ReportEngine::display($due, 'money'), $result['_ui']['totals'][array_search('due', array_column($result['_ui']['columns'], 'key'))]);
        $this->assertStringContainsString('/assistant/reports/'.$report->id.'/excel', $result['_ui']['excel_url']);

        $this->assertArrayHasKey('error', $tool->handle(['dataset' => 'bookings'], null));
    }

    public function test_downloads_are_for_the_owner_only(): void
    {
        $result = app(Tools\RunReport::class)->handle(['dataset' => 'bookings', 'filters' => ['status' => 'all']], $this->reem);
        $id = $result['report_id'];

        $this->get("/assistant/reports/$id/excel")->assertRedirect('/login');
        $this->actingAs(User::factory()->create(), 'web')->get("/assistant/reports/$id/excel")->assertNotFound();
        $this->actingAs(User::factory()->create(), 'web')->get("/assistant/reports/$id/print")->assertNotFound();

        $response = $this->actingAs($this->reem, 'web')->get("/assistant/reports/$id/excel")->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $file = $response->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('SC2611-K4P2Q', $sheet);
        $due = Booking::where('reference', 'SC2611-K4P2Q')->sole()->amountDue();
        $this->assertStringContainsString('<v>'.$due.'</v>', $sheet, 'amounts are numbers');
        $zip->close();

        $this->actingAs($this->reem, 'web')->get("/assistant/reports/$id/print")->assertOk()
            ->assertSee('SC2611-K4P2Q')->assertSee('Amounts in US dollars');
    }

    public function test_save_and_run_a_template(): void
    {
        $result = app(Tools\RunReport::class)->handle(['dataset' => 'bookings', 'columns' => ['reference', 'due'], 'filters' => ['payment' => 'due']], $this->reem);

        $this->actingAs($this->reem, 'web')->postJson("/assistant/reports/{$result['report_id']}/template", ['name' => 'Unpaid'])->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($this->reem, 'web')->postJson("/assistant/reports/{$result['report_id']}/template", ['name' => 'Unpaid'])->assertOk();
        $this->assertSame(1, AssistantReport::templates()->count(), 'same name replaces');

        $this->assertSame(['Unpaid'], array_column(app(Tools\ListReportTemplates::class)->handle([], $this->reem)['templates'], 'name'));
        $this->assertSame([], app(Tools\ListReportTemplates::class)->handle([], User::factory()->create())['templates']);

        $again = app(Tools\RunReport::class)->handle(['template' => 'Unpaid'], $this->reem);
        $this->assertSame(['reference', 'due'], array_column($again['_ui']['columns'], 'key'));
        $this->assertArrayHasKey('error', app(Tools\RunReport::class)->handle(['template' => 'Unpaid'], User::factory()->create()));
    }

    public function test_choice_buttons_end_the_turn_and_the_click_continues_it(): void
    {
        Http::fake(['claude.test/*' => Http::sequence()
            ->push(['content' => [
                ['type' => 'text', 'text' => 'Sure.'],
                ['type' => 'tool_use', 'id' => 'toolu_c', 'name' => 'offer_choices', 'input' => ['question' => 'Which period?', 'options' => ['This month', 'December']]],
            ], 'stop_reason' => 'tool_use', 'usage' => []])
            ->push(['content' => [['type' => 'tool_use', 'id' => 'toolu_r', 'name' => 'run_report', 'input' => ['dataset' => 'bookings', 'from' => '2026-11-01', 'to' => '2026-11-30']]], 'stop_reason' => 'tool_use', 'usage' => []])
            ->push(['content' => [['type' => 'text', 'text' => '1 booking, $3,840 due.']], 'stop_reason' => 'end_turn', 'usage' => []])]);

        $first = $this->actingAs($this->reem, 'web')->postJson('/assistant/message', ['message' => 'My bookings'])->assertOk();
        $first->assertJson(['reply' => 'Sure.', 'ui' => [['type' => 'choices', 'question' => 'Which period?', 'options' => ['This month', 'December']]]]);
        Http::assertSentCount(1);

        $second = $this->actingAs($this->reem, 'web')->postJson('/assistant/message', ['message' => 'December'])->assertOk();
        $second->assertJson(['reply' => '1 booking, $3,840 due.']);
        $this->assertSame('report', $second->json('ui.0.type'));
        $this->assertSame(1, $second->json('ui.0.count'));

        // The click went with the waiting tool result, in one user message
        [$request] = Http::recorded()[1];
        $last = collect($request['messages'])->last();
        $this->assertSame('tool_result', $last['content'][0]['type']);
        $this->assertSame('toolu_c', $last['content'][0]['tool_use_id']);
        $this->assertSame(['type' => 'text', 'text' => 'December'], $last['content'][1]);
        // The widget's data is not sent to Claude
        $this->assertStringNotContainsString('"_ui"', $request->body());

        $chat = AssistantConversation::sole();
        $this->assertSame(2, $chat->user_messages);
        $this->assertSame(['My bookings', 'December'], collect($chat->transcript())->where('kind', 'user')->pluck('text')->values()->all());
    }

    public function test_signed_in_visitors_start_with_report_topics(): void
    {
        $this->actingAs($this->reem, 'web')->get('/')->assertSee('My bookings')->assertDontSee('My commissions');
        $this->actingAs($this->agent(), 'web')->get('/')->assertSee('My commissions');
        auth('web')->logout();
        $this->get('/')->assertSee('Which cruises sail next month?');
    }
}
