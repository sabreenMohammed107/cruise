<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Messages from the website's "Contact us" page: list (newest first, optionally unread only), read, delete.
 * Opening a message marks it as read.
 */
class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $unreadOnly = $request->boolean('unread');

        $messages = ContactMessage::query()
            ->when($unreadOnly, fn ($q) => $q->unread())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.contact-messages.index', [
            'messages' => $messages,
            'unreadOnly' => $unreadOnly,
            'unreadCount' => ContactMessage::unread()->count(),
        ]);
    }

    public function show(ContactMessage $contactMessage): View
    {
        $contactMessage->markAsRead();

        return view('admin.contact-messages.show', ['msg' => $contactMessage]);
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return redirect()->route('admin.contact-messages.index')->with('status', 'Message deleted.');
    }
}
