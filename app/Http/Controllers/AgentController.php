<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgentRequest;
use App\Models\AgentApplication;
use App\Models\HomeSection;
use App\Rules\BusinessEmail;
use App\Support\ScDataPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * "Become an agent" page: texts from Admin → Cruise pages → Become an agent (config/pages.php), and the
 * application form (read in Admin → Agents → Agent requests).
 */
class AgentController extends Controller
{
    public function show(Request $request, ScDataPresenter $presenter): View
    {
        return view('pages.agents', [
            'scData' => $presenter->minimal($request->user('web')),
            'sections' => HomeSection::forPage('agents'),
        ]);
    }

    public function store(AgentRequest $request): RedirectResponse
    {
        // A bot filled the hidden field: answer as usual so it learns nothing, but keep nothing
        if (! $request->isBot()) {
            $email = strtolower(trim((string) $request->input('email')));
            $domain = BusinessEmail::domainOf($email);
            // A double click sends the form twice at once: both pass AgentRequest's "already applied" check before
            // either is saved. Same for two people of one company applying at the same moment and the
            // one-agent-per-domain check. One save per domain at a time, checked again inside the lock; the second
            // request finds the first application and just shows the same "sent" message.
            Cache::lock('agent-application:'.sha1((string) $domain), 10)->block(5, function () use ($request, $email, $domain) {
                if (! AgentApplication::open()->whereRaw('lower(email) = ?', [$email])->exists() && ! AgentRequest::domainIsFull($domain)) {
                    AgentApplication::create($request->toSave());
                }
            });
        }

        return redirect()->to(route('agents').'#apply')->with('agent_sent', true);
    }
}
