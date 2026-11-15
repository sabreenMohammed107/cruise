<?php

namespace App\Services\Assistant\Tools;

use App\Models\AgeBand;
use App\Models\User;
use App\Services\PricingService;

/** Booking rules: how booking and payment work, age bands, cabin limits, port taxes, terms and cancellation. */
class GetPolicies extends Tool
{
    public function name(): string
    {
        return 'get_policies';
    }

    public function description(): string
    {
        return 'Booking rules and policies: how to book and pay (no online payment), age bands (adult / child / infant), '
            .'cabin guest limits, port & departure taxes, booking terms and the cancellation penalties.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function handle(array $input, ?User $user): array
    {
        $ar = $this->isArabic();

        return [
            'how_to_book' => 'Pick a cruise, sailing and cabin on the website, enter the travellers, then "Confirm booking". '
                .'The booking is confirmed at once with a reference. There is no online payment: the team contacts the guest within '
                .config('cruise.payment_contact_hours').' hours to arrange payment (bank transfer, cash, card or payment link).',
            'age_bands' => AgeBand::orderBy('sort_order')->get()->map(fn (AgeBand $b) => [
                'band' => $b->key->value,
                'label' => $b->tr('label'),
                'rule' => $b->tr('hint'),
            ])->all(),
            'cabin_rules' => 'Each cabin needs at least one adult. Adults, children and infants together must fit the cabin\'s maximum guests. '
                .'Infants are free. Up to '.config('cruise.max_cabins_per_booking').' cabins in one booking.',
            'pricing' => 'Cabin prices are for the whole cabin and the whole cruise: single (1 paying guest), double (2 paying guests) '
                .'and an extra price for the 3rd guest. "From … per person" is the double price divided by 2.',
            'port_taxes_per_paying_guest' => $this->money(PricingService::portTaxPerGuest()),
            'terms' => config($ar ? 'cruise.terms.text_ar' : 'cruise.terms.text'),
            'cancellation' => array_map(
                fn ($row) => ['period' => $row[0], 'penalty' => $row[1]],
                config($ar ? 'cruise.terms.cancellation_ar' : 'cruise.terms.cancellation'),
            ),
            'pre_boarding_guide_url' => route('guide'),
        ];
    }
}
