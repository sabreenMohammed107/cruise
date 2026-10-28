<?php

namespace App\Http\Controllers;

use App\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The header's USD / EGP switch: remembers the visitor's currency in the session and reloads the page.
 */
class CurrencyController extends Controller
{
    public function __invoke(Request $request, CurrencyService $currency): RedirectResponse
    {
        $data = $request->validate(['currency' => ['required', Rule::in($currency->currencies())]]);
        $currency->setActive($data['currency']);

        return back();
    }
}
