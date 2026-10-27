<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgentRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Applications from the website's "Become an agent" page: list (newest first, by status), details, and
 * the status the team moves them through (pending → reviewing → accepted / rejected).
 * The page's texts are edited in Admin → Cruise pages → Become an agent.
 */
class AgentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = AgentRequestStatus::tryFrom((string) $request->query('status'));

        $applications = AgentApplication::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.agent-requests.index', [
            'applications' => $applications,
            'status' => $status,
            'counts' => AgentApplication::toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(AgentApplication $agentRequest): View
    {
        return view('admin.agent-requests.show', ['application' => $agentRequest]);
    }

    public function update(Request $request, AgentApplication $agentRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(AgentRequestStatus::class)]]);

        // Not mass assignable, so the public form can never set it
        $agentRequest->status = AgentRequestStatus::from($data['status']);
        $agentRequest->save();

        return back()->with('status', 'Status updated.');
    }
}
