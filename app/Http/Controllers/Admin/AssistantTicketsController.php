<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantPackage;
use App\Services\Assistant\ChatList;
use App\Services\Assistant\Tickets;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Assistant tickets: the website chat's package in tickets (App\Services\Assistant\Tickets), past packages,
 * and the chats. No prices or costs: packages are created and renewed in Admin → Assistant chats (the super admin's
 * page), so this one can be opened to the admins later (Gate "manage-assistant-tickets").
 */
class AssistantTicketsController extends Controller
{
    public function index(Request $request): View
    {
        $package = Tickets::current() ?? Tickets::latest();
        $filters = ChatList::filters($request);

        return view('admin.assistant.tickets', [
            'package' => $package,
            'status' => $package?->status() ?? 'none',
            'packages' => AssistantPackage::latest('starts_at')->latest('id')->paginate(10, ['*'], 'packages'),
            // Everything below belongs to the package shown: a renewal starts from zero, the old package's conversations
            // stay with it. Before the first package: this month's counts and every conversation.
            'stats' => $package
                ? ['today' => $package->conversationsCount(today: true), 'conversations' => $package->conversationsCount(), 'messages' => $package->messages]
                : ['today' => ($month = ChatList::stats())['today'], 'conversations' => $month['chats'], 'messages' => $month['messages']],
            'filters' => $filters,
            'chats' => ChatList::paginate($filters, $package),
        ]);
    }
}
