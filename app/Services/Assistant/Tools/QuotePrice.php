<?php

namespace App\Services\Assistant\Tools;

use App\Models\CabinCategory;
use App\Models\PromoCode;
use App\Models\Sailing;
use App\Models\User;
use App\Services\AgentPricing;
use App\Services\OfferService;
use App\Services\PricingService;

/**
 * An exact price for one cabin, the same numbers as the booking page: PricingService::quoteBooking() with the
 * sailing's live offers, the promo code, port taxes and (for a signed-in agent) the agent rate. Checks the same guest
 * rules as ConfirmBookingRequest. Nothing is booked or held.
 */
class QuotePrice extends Tool
{
    public function name(): string
    {
        return 'quote_price';
    }

    public function description(): string
    {
        return 'Exact price of one cabin on one sailing for a number of guests, exactly as the booking page will charge: '
            .'cabin fare, current offers, optional promo code, port & departure taxes, total. Adults are 18+, children 2-17, '
            .'infants under 2 (free; need an adult). Nothing is booked. For several cabins, call once per cabin and add the totals.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sailing_code' => ['type' => 'string', 'description' => 'Sailing code from search_cruises / get_cruise_details, e.g. "RSD-261213".'],
                'cabin' => ['type' => 'string', 'description' => 'Cabin type slug or name, e.g. "balcony".'],
                'adults' => ['type' => 'integer', 'minimum' => 0],
                'children' => ['type' => 'integer', 'minimum' => 0],
                'infants' => ['type' => 'integer', 'minimum' => 0],
                'promo_code' => ['type' => 'string', 'description' => 'Only if the visitor gave one.'],
            ],
            'required' => ['sailing_code', 'cabin', 'adults'],
        ];
    }

    public function handle(array $input, ?User $user): array
    {
        $sailing = Sailing::with('cruise', 'cabinPrices.cabinCategory')->where('code', (string) $this->str($input, 'sailing_code'))->first();
        if (! $sailing || ! $sailing->isBookable()) {
            return ['error' => 'This sailing cannot be booked (unknown code, closed or already departed).'];
        }

        $wanted = mb_strtolower((string) $this->str($input, 'cabin'));
        $price = $sailing->cabinPrices->first(fn ($p) => $p->cabinCategory && $this->cabinMatches($p->cabinCategory, $wanted));
        if (! $price) {
            return [
                'error' => 'This cabin type is not offered on this sailing.',
                'cabins_on_this_sailing' => $sailing->cabinPrices->map(fn ($p) => $p->cabinCategory?->slug)->filter()->values()->all(),
            ];
        }
        if ($price->is_sold_out) {
            return ['error' => 'This cabin type is sold out on this sailing. Suggest another cabin or date.'];
        }

        $guests = [
            'adult' => $this->int($input, 'adults', 0, 20) ?? 0,
            'child' => $this->int($input, 'children', 0, 20) ?? 0,
            'infant' => $this->int($input, 'infants', 0, 20) ?? 0,
        ];
        if ($guests['adult'] < 1) {
            return ['error' => 'Every cabin needs at least one adult (18+), and every infant must travel with an adult.'];
        }
        $capacity = $price->cabinCategory->capacity();
        if (array_sum($guests) > $capacity) {
            return ['error' => "This cabin takes at most {$capacity} guests (adults, children and infants together). Suggest a bigger cabin or two cabins."];
        }

        $promo = null;
        $promoNote = null;
        if ($code = $this->str($input, 'promo_code')) {
            $promo = PromoCode::findRedeemable($code);
            $promoNote = $promo ? null : 'The promo code is not valid or has expired; the price is without it.';
        }

        $cabin = ['price' => $price, 'guests' => $guests];
        $agents = app(AgentPricing::class);
        $agent = $agents->appliesTo($user) ? $user : null;
        if ($agent) {
            $cabin['agentPercent'] = $agents->percentFor($price, $agent);
        }

        $offers = app(OfferService::class)->forSailing($sailing, $user);
        $quote = app(PricingService::class)->quoteBooking([$cabin], $promo, $offers, $sailing->departure_date, $agent);

        return [
            'cruise' => $sailing->cruise?->tr('name'),
            'sailing_code' => $sailing->code,
            'departure_date' => $this->date($sailing->departure_date),
            'cabin' => $this->isArabic() && filled($price->cabinCategory->name_ar) ? $price->cabinCategory->name_ar : $price->cabinCategory->name_en,
            'guests' => $guests,
            'cabin_fare' => $this->money($quote['fare']),
            'offers' => array_map(fn ($o) => ['name' => $this->isArabic() && filled($o['nameAr'] ?? null) ? $o['nameAr'] : $o['name'], 'discount' => $this->money($o['discount'])], $quote['offers']),
            'promo_discount' => $quote['discount'] > 0 ? $this->money($quote['discount']) : null,
            'promo_note' => $quote['promoBlocked'] ? 'This promo code cannot be combined with the current offer.' : $promoNote,
            'port_taxes' => $this->money($quote['fees']),
            'total' => $this->money($quote['total']),
            'agent_rate' => $agent ? true : null,
            'book_url' => route('cruises.show', $sailing->cruise).'?sailing='.rawurlencode($sailing->code),
            'note' => 'Booking is confirmed on the website; there is no online payment: the team contacts the guest within '
                .config('cruise.payment_contact_hours').' hours to arrange payment. Age-based offers are applied on the booking page once birth dates are entered.',
        ];
    }

    private function cabinMatches(CabinCategory $cabin, string $wanted): bool
    {
        if ($wanted === '') {
            return false;
        }
        foreach ([$cabin->slug, $cabin->name_en, $cabin->name_ar] as $name) {
            $name = mb_strtolower((string) $name);
            if ($name !== '' && ($name === $wanted || str_contains($name, $wanted))) {
                return true;
            }
        }

        return false;
    }
}
