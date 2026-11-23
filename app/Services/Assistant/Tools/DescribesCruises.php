<?php

namespace App\Services\Assistant\Tools;

use App\Models\Cruise;
use App\Models\Port;
use App\Models\Sailing;
use App\Models\User;
use App\Enums\OfferTarget;
use App\Services\AgentPricing;
use App\Services\OfferService;
use App\Services\PricingService;

/** Cruise and sailing summaries shared by the cruise tools. */
trait DescribesCruises
{
    /** @var array<string, array> cardPrice() per sailing and visitor, within one tool call */
    private array $cardPrices = [];

    /** Stops in order, in the visitor's language. */
    protected function routeOf(Cruise $cruise): array
    {
        if ($cruise->ports->isEmpty()) {
            return $cruise->route ?? [];
        }

        return $cruise->ports->map(fn (Port $p) => $this->isArabic() && filled($p->name_ar) ? $p->name_ar : $p->name_en)->all();
    }

    /** Whether the cruise stops at a port named (in English or Arabic) like $query. */
    protected function callsAtPort(Cruise $cruise, string $query): bool
    {
        $q = mb_strtolower($query);
        foreach ($cruise->ports as $port) {
            if (str_contains(mb_strtolower((string) $port->name_en), $q) || str_contains(mb_strtolower((string) $port->name_ar), $q)) {
                return true;
            }
        }

        return collect($cruise->route ?? [])->contains(fn ($stop) => str_contains(mb_strtolower((string) $stop), $q));
    }

    /**
     * "From, per person" as the cruise card shows it to this visitor (SC.fromPrice / SC.sailingView in core.js): the
     * lowest price for two adults sharing ÷ 2, port taxes not included, at the agent rate for a signed-in agent, and
     * after the sailing's offers when one changes that price (fare, free nights, upgrade, second guest). Same cabins
     * as Sailing::fromPrice(): not sold out (all of them when every one is), with a Double price when any has one.
     *
     * @return array{from: ?float, before: ?float, offers: list<string>} before: the price without the offers
     */
    protected function cardPrice(Sailing $sailing, ?User $user): array
    {
        $key = $sailing->id.':'.($user?->id ?? 0);
        if (isset($this->cardPrices[$key])) {
            return $this->cardPrices[$key];
        }

        $prices = $sailing->isFullySoldOut() ? $sailing->cabinPrices : $sailing->cabinPrices->where('is_sold_out', false);
        $priced = $prices->filter(fn ($p) => (float) $p->price_double > 0);
        $pick = $priced->isNotEmpty() ? $priced : $prices;
        if ($pick->isEmpty()) {
            return $this->cardPrices[$key] = ['from' => null, 'before' => null, 'offers' => []];
        }

        $pricing = app(PricingService::class);
        $agents = app(AgentPricing::class);
        $agent = $agents->appliesTo($user) ? $user : null;
        $offers = app(OfferService::class)->forSailing($sailing, $user)
            ->filter(fn ($o) => in_array($o->target, [OfferTarget::Fare, OfferTarget::FreeNights, OfferTarget::Upgrade, OfferTarget::SecondGuest], true));

        $from = $before = null;
        $used = [];
        foreach ($pick as $price) {
            $cabin = ['price' => $price, 'guests' => ['adult' => 2, 'child' => 0, 'infant' => 0]]
                + ($agent ? ['agentPercent' => $agents->percentFor($price, $agent)] : []);
            $plain = $pricing->quoteBooking([$cabin], null, [], $sailing->departure_date, $agent)['fare'] / 2;
            $after = $plain;
            if ($offers->isNotEmpty() && (float) $price->price_double > 0) {
                $quote = $pricing->quoteBooking([$cabin], null, $offers, $sailing->departure_date, $agent);
                $after = ($quote['total'] - $quote['fees']) / 2;
                if ($after < $plain) {
                    $used = array_merge($used, array_map(fn ($o) => $this->isArabic() && filled($o['nameAr'] ?? null) ? $o['nameAr'] : $o['name'], $quote['offers']));
                }
            }
            $from = $from === null ? $after : min($from, $after);
            $before = $before === null ? $plain : min($before, $plain);
        }

        return $this->cardPrices[$key] = [
            'from' => $from,
            'before' => $before - $from >= 0.5 ? $before : null,
            'offers' => array_values(array_unique($used)),
        ];
    }

    protected function sailingSummary(Cruise $cruise, Sailing $sailing, ?User $user): array
    {
        $price = $this->cardPrice($sailing, $user);

        return [
            'code' => $sailing->code,
            'departure_date' => $this->date($sailing->departure_date),
            'return_date' => $this->date($sailing->return_date),
            'from_price_per_person' => $this->money($price['from']),
            'price_before_offer' => $price['before'] !== null ? $this->money($price['before']) : null,
            'offers' => $price['offers'] ?: null,
            'sold_out' => $sailing->isFullySoldOut(),
            'book_url' => route('cruises.show', $cruise).'?sailing='.rawurlencode($sailing->code),
        ];
    }

    protected function cruiseSummary(Cruise $cruise): array
    {
        return [
            'slug' => $cruise->slug,
            'name' => $cruise->tr('name'),
            'nights' => (int) $cruise->nights,
            'route' => $this->routeOf($cruise),
            'url' => route('cruises.show', $cruise),
        ];
    }
}
