<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgentRequest;
use App\Models\AgentApplication;
use App\Models\HomeSection;
use App\Support\ScDataPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            AgentApplication::create($request->toSave());
        }

        return redirect()->to(route('agents').'#apply')->with('agent_sent', true);
    }
}
