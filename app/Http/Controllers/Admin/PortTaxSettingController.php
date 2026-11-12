<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\CurrencyService;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Pricing & offers → Port taxes: the port & departure taxes added to every booking, in USD per paying guest
 * (adults and children; infants pay none). Saved in site_settings "port_tax_per_guest"; config
 * cruise.port_tax_per_guest until saved. 0 turns them off. App\Services\PricingService::portTaxPerGuest().
 *
 * Bookings keep the amount of their booking day (fees_total, price_snapshot): a change applies to new bookings only.
 */
class PortTaxSettingController extends Controller
{
    public function edit(CurrencyService $currency): View
    {
        return view('admin.port-tax.edit', [
            'portTax' => PricingService::portTaxPerGuest(),
            'egpRate' => $currency->rate('EGP'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'port_tax_per_guest' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
        ], [
            'port_tax_per_guest.required' => 'Enter an amount (0 for no port taxes).',
            'port_tax_per_guest.min' => 'The amount cannot be negative.',
        ]);

        SiteSetting::updateOrCreate(['key' => 'port_tax_per_guest'], ['value' => (string) (float) $data['port_tax_per_guest']]);

        return redirect()->route('admin.port-tax.edit')->with('status', 'Port & departure taxes saved.');
    }
}
