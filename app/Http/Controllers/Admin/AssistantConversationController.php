<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\AssistantPackage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Assistant\Assistant;
use App\Services\Assistant\ChatList;
use App\Services\Assistant\Tickets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin → Assistant chats: what visitors asked the website chat (App\Services\Assistant) and what it answered, with
 * the steps it took, the tokens used and their cost, the on / off switch and the message limits. The super admin only
 * (can:manage-assistant, routes/admin.php).
 */
class AssistantConversationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = ChatList::filters($request);
        $chats = ChatList::paginate($filters);

        return view('admin.assistant.index', [
            'chats' => $chats,
            'filters' => $filters,
            'stats' => $this->stats(),
            'configured' => Assistant::configured(),
            'switchedOn' => Assistant::switchedOn(),
            'model' => config('assistant.model'),
            'audience' => Assistant::audience(),
            'testers' => Assistant::testers(),
            'agents' => User::agents()->orderBy('name')->get(['id', 'name', 'email']),
            'limits' => collect(Assistant::LIMITS)->map(fn ($l, $name) => ['label' => $l[1], 'value' => Assistant::limit($name), 'default' => Assistant::defaultLimit($name)])->all(),
            // The tickets package (Admin → Assistant tickets): created and renewed here
            'package' => Tickets::current() ?? Tickets::latest(),
            'pricesSet' => config('assistant.price_input_per_mtok') !== null && config('assistant.price_output_per_mtok') !== null,
        ]);
    }

    public function show(AssistantConversation $conversation): View
    {
        return view('admin.assistant.show', [
            'chat' => $conversation->load('user'),
            'transcript' => $conversation->transcript(),
            'cost' => Assistant::cost($conversation->input_tokens, $conversation->output_tokens),
        ]);
    }

    /** Switches the website chat on or off (site_settings "assistant_enabled"). */
    public function toggle(Request $request): RedirectResponse
    {
        $on = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];
        SiteSetting::updateOrCreate(['key' => Assistant::SETTING], ['value' => $on ? '1' : '0']);

        return redirect()->route('admin.assistant.index')->with('status', $on ? 'The website chat is on.' : 'The website chat is off: visitors no longer see it.');
    }

    /**
     * Who sees the chat: everyone, or (test mode) the super admin and the agents ticked here only
     * (site_settings "assistant_audience" and "assistant_testers").
     */
    public function audience(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['testers', 'everyone'])],
            'testers' => ['array'],
            'testers.*' => ['integer', Rule::exists('users', 'id')->where('role', 'agent')],
        ]);
        SiteSetting::updateOrCreate(['key' => Assistant::AUDIENCE], ['value' => $data['audience']]);
        SiteSetting::updateOrCreate(['key' => Assistant::TESTERS], ['value' => implode(',', array_unique(array_map('intval', $data['testers'] ?? [])))]);
        SiteSetting::flush();

        return redirect()->route('admin.assistant.index')->with('status', $data['audience'] === 'everyone'
            ? 'Every visitor now sees the chat.'
            : 'Test mode: only you and the chosen agents see the chat.');
    }

    /** Messages a minute / an hour per visitor and per chat (site_settings, Assistant::limit()). */
    public function limits(Request $request): RedirectResponse
    {
        $data = $request->validate(collect(Assistant::LIMITS)->mapWithKeys(fn ($l, $name) => [$name => ['required', 'integer', 'min:1', 'max:10000']])->all());
        foreach (Assistant::LIMITS as $name => [$key]) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => (string) $data[$name]]);
        }
        SiteSetting::flush();

        return redirect()->route('admin.assistant.index')->with('status', 'Limits saved.');
    }

    public function destroy(AssistantConversation $conversation): RedirectResponse
    {
        $conversation->delete();

        return redirect()->route('admin.assistant.index')->with('status', 'Chat deleted.');
    }

    /**
     * A new tickets package, or a renewal (Admin → Assistant tickets): the tickets for the USD amount, from the chosen
     * start (now by default) for one month. It replaces the running package: that one ends when this one starts.
     */
    public function package(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tickets' => ['required', 'integer', 'min:1', 'max:100000000'],
            'price_usd' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
        ]);
        $start = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();
        $end = $start->copy()->addMonthNoOverflow();

        // The packages still running (or scheduled) end when this one starts
        AssistantPackage::where('ends_at', '>', $start)->each(function (AssistantPackage $old) use ($start) {
            $old->update(['ends_at' => $old->starts_at->greaterThan($start) ? $old->starts_at : $start]);
        });

        AssistantPackage::create([
            'tickets' => $data['tickets'],
            'price_usd' => $data['price_usd'],
            'starts_at' => $start,
            'ends_at' => $end,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->to(route('admin.assistant.index').'#package')->with('status', $start->isFuture()
            ? 'Package saved: it starts on '.$start->format('j M Y, H:i').'.'
            : 'Package started: '.number_format($data['tickets']).' tickets until '.$end->format('j M Y, H:i').'.');
    }

    /** Chats and messages today / this month, with this month's and all-time cost. Chats with messages. */
    private function stats(): array
    {
        $stats = ChatList::stats();
        $all = AssistantConversation::where('user_messages', '>', 0)->selectRaw('coalesce(sum(input_tokens), 0) as input, coalesce(sum(output_tokens), 0) as output')->first();

        return $stats + [
            'cost' => Assistant::cost($stats['input'], $stats['output']),
            'cost_all' => Assistant::cost((int) $all->input, (int) $all->output),
        ];
    }
}
