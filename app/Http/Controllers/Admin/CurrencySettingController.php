<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin → Pricing & offers → Currency: where the website's EGP prices get their rate (live API or the admin's
 * rate), the admin's rate (also the fallback when the API fails) and a check of the live rate. Prices stay in USD.
 */
class CurrencySettingController extends Controller
{
    public function edit(CurrencyService $currency): View
    {
        return view('admin.currency.edit', [
            'mode' => $currency->mode(),
            'manualRate' => $currency->manualRate(),
            'live' => $currency->liveRate(),
            'activeRate' => $currency->rate('EGP'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency_mode' => ['required', Rule::in([CurrencyService::MODE_API, CurrencyService::MODE_MANUAL])],
            'manual_exchange_rate' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,4'],
        ], [
            'manual_exchange_rate.required' => 'Enter a rate: it is also used when the live rate is unavailable.',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                SiteSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
        });

        return redirect()->route('admin.currency.edit')->with('status', 'Currency settings saved.');
    }

    /** Fetch the live rate now instead of waiting for the cache to expire. */
    public function refresh(CurrencyService $currency): RedirectResponse
    {
        $live = $currency->liveRate(refresh: true);
        $redirect = redirect()->route('admin.currency.edit');

        return $live['source'] === 'api'
            ? $redirect->with('status', 'Live rate updated: 1 USD = '.number_format($live['rate'], 4).' EGP.')
            : $redirect->with('error', 'The live rate could not be fetched ('.$live['error'].'). The website uses the fallback rate meanwhile.');
    }
}
