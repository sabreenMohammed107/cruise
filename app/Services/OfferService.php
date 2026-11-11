<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Sailing;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which offers (App\Models\Offer) a sailing has for a visitor right now: live, covering the sailing, and for this
 * audience (customers or agents). PricingService::selectOffers() checks their conditions (cabin types, nationalities,
 * ages) against the booking and prices them; ScDataPresenter publishes them so the page scripts show the same prices
 * (SC.quoteRooms in core.js).
 */
class OfferService
{
    /** @var Collection<int, Offer>|null live offers of this request (the website lists many sailings at once) */
    private ?Collection $live = null;

    /** @return Collection<int, Offer> */
    public function live(): Collection
    {
        return $this->live ??= Offer::live()->orderBy('id')->get();
    }

    /**
     * Offers covering this sailing for this visitor. $lock (inside BookingService's transaction): read again and
     * locked, so two bookings can't both take an offer's last use.
     *
     * @return Collection<int, Offer>
     */
    public function forSailing(Sailing $sailing, ?User $user, bool $lock = false): Collection
    {
        $offers = $lock ? Offer::live()->orderBy('id')->lockForUpdate()->get() : $this->live();

        return self::filter($offers, $sailing, (bool) $user?->isAgent());
    }

    /**
     * @param  Collection<int, Offer>  $offers
     * @return Collection<int, Offer>
     */
    public static function filter(Collection $offers, Sailing $sailing, bool $agent): Collection
    {
        return $offers->filter(fn (Offer $o) => $o->covers($sailing) && $o->isFor($agent))->values();
    }
}
