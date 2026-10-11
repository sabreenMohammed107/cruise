<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255'],
        ], [
            'email.*' => 'Enter an email like name@example.com',
        ]);

        // Re-subscribing clears an earlier unsubscribe.
        NewsletterSubscriber::updateOrCreate(
            ['email' => strtolower($data['email'])],
            ['subscribed_at' => now(), 'unsubscribed_at' => null],
        );

        return response()->json(['subscribed' => true]);
    }
}
