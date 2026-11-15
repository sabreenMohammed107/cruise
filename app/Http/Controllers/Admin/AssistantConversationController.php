<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Assistant\Assistant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'who' => in_array($request->query('who'), ['signed_in', 'guests'], true) ? $request->query('who') : null,
            'lang' => in_array($request->query('lang'), ['en', 'ar'], true) ? $request->query('lang') : null,
            'period' => in_array($request->query('period'), ['today', '7', '30'], true) ? $request->query('period') : null,
        ];

        $chats = AssistantConversation::query()
            ->with('user')
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $q->where(fn (Builder $w) => $w
                    ->when(ctype_digit($filters['q']), fn ($x) => $x->orWhere('id', (int) $filters['q']))
                    ->orWhere('ip', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->when($filters['who'] === 'signed_in', fn ($q) => $q->whereNotNull('user_id'))
            ->when($filters['who'] === 'guests', fn ($q) => $q->whereNull('user_id'))
            ->when($filters['lang'], fn ($q) => $q->where('locale', $filters['lang']))
            ->when($filters['period'] === 'today', fn ($q) => $q->where('created_at', '>=', today()))
            ->when(in_array($filters['period'], ['7', '30'], true), fn ($q) => $q->where('created_at', '>=', today()->subDays((int) $filters['period'] - 1)))
            ->where('user_messages', '>', 0)
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

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

    /** Chats and messages today / this month, tokens and cost this month, cost since the start. Chats with messages. */
    private function stats(): array
    {
        $all = AssistantConversation::where('user_messages', '>', 0)->selectRaw('coalesce(sum(input_tokens), 0) as input, coalesce(sum(output_tokens), 0) as output')->first();
        $month = AssistantConversation::where('user_messages', '>', 0)->where('created_at', '>=', now()->startOfMonth());
        $totals = (clone $month)->selectRaw('count(*) as chats, coalesce(sum(user_messages), 0) as messages, coalesce(sum(input_tokens), 0) as input, coalesce(sum(output_tokens), 0) as output')->first();

        return [
            'today' => AssistantConversation::where('user_messages', '>', 0)->where('created_at', '>=', today())->count(),
            'chats' => (int) $totals->chats,
            'messages' => (int) $totals->messages,
            'input' => (int) $totals->input,
            'output' => (int) $totals->output,
            'cost' => Assistant::cost((int) $totals->input, (int) $totals->output),
            'cost_all' => Assistant::cost((int) $all->input, (int) $all->output),
        ];
    }
}
