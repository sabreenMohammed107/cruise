<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgentRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AgentApplication;
use App\Models\User;
use App\Notifications\AgentApproved;
use App\Services\AgentAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Applications from the website's "Become an agent" page: list (newest first, by status), details, and
 * the status the team moves them through (pending → reviewing → accepted / rejected).
 * "Accepted" is reached only by approving (admins: gate manage-users), which makes the agent account and emails
 * its sign-in details (App\Services\AgentAccounts, App\Notifications\AgentApproved).
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
        return view('admin.agent-requests.show', [
            'application' => $agentRequest->load('user'),
            // Not yet approved, but the email already has an account: it can't be approved (AgentAccounts)
            'existingAccount' => $agentRequest->user_id ? null : User::whereRaw('lower(email) = ?', [strtolower(trim($agentRequest->email))])->first(),
        ]);
    }

    public function update(Request $request, AgentApplication $agentRequest): RedirectResponse
    {
        $data = $request->validate(['status' => [
            'required',
            // Accepting makes an account, so it has its own button (approve)
            Rule::enum(AgentRequestStatus::class)->except([AgentRequestStatus::Accepted]),
        ]]);

        if ($agentRequest->status === AgentRequestStatus::Accepted) {
            return back()->with('error', 'This application was approved. To take agent rates away, change the account type under Agents.');
        }

        // Not mass assignable, so the public form can never set it
        $agentRequest->status = AgentRequestStatus::from($data['status']);
        $agentRequest->save();

        return back()->with('status', 'Status updated.');
    }

    /** Approve: agent account (new, or the customer account with this email) + sign-in details by email. */
    public function approve(Request $request, AgentApplication $agentRequest, AgentAccounts $accounts): RedirectResponse
    {
        $data = $request->validate(['agent_tier_id' => ['nullable', Rule::exists('agent_tiers', 'id')]]);

        if ($agentRequest->status === AgentRequestStatus::Accepted) {
            return back()->with('error', 'This application is already approved.');
        }

        [$user, $password] = $accounts->approve($agentRequest, isset($data['agent_tier_id']) ? (int) $data['agent_tier_id'] : null);

        return $this->sendSignInDetails($user, $agentRequest, $password, 'Approved: '.$agentRequest->company_name.' is now an agent');
    }

    /** A new temporary password, emailed again (the first email never arrived, or the agent lost it). */
    public function resendCredentials(AgentApplication $agentRequest, AgentAccounts $accounts): RedirectResponse
    {
        $user = $agentRequest->user;
        if (! $user?->isAgent()) {
            return back()->with('error', 'This application has no agent account.');
        }

        return $this->sendSignInDetails($user, $agentRequest, $accounts->resetPassword($user), 'A new temporary password was made');
    }

    /**
     * Sent now, not queued (see AgentApproved). The account is saved either way; if the email fails the admin is told,
     * and "Send new password" tries again with a fresh password.
     */
    private function sendSignInDetails(User $user, AgentApplication $application, ?string $password, string $done): RedirectResponse
    {
        try {
            $user->notify(new AgentApproved($application->company_name, $password));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "{$done}, but the email to {$user->email} could not be sent. Check the mail settings, then use \"Send new password\".");
        }

        return back()->with('status', "{$done}. Sign-in details were emailed to {$user->email}.");
    }
}
