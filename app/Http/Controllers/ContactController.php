<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\NewContactMessage;
use App\Models\ContactMessage;
use App\Support\ScDataPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * "Contact us" page: the form, and saving what visitors send (read in Admin → Contact messages).
 */
class ContactController extends Controller
{
    public function show(Request $request, ScDataPresenter $presenter): View
    {
        return view('pages.contact', ['scData' => $presenter->minimal($request->user('web'))]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $sent = redirect()->route('contact')->with('contact_sent', true);

        // A bot filled the hidden field: answer as usual so it learns nothing, but keep nothing
        if ($request->isBot()) {
            return $sent;
        }

        $message = ContactMessage::create($request->toSave());
        $this->notifyTeam($message);

        return $sent;
    }

    /**
     * The message is saved either way; a mail failure is reported, not shown to the visitor.
     */
    private function notifyTeam(ContactMessage $message): void
    {
        try {
            if ($team = config('cruise.team_email')) {
                Mail::to($team)->queue(new NewContactMessage($message));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
