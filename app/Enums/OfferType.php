<?php

namespace App\Enums;

/** How much an offer takes off (App\Models\Offer::amountOff()). */
enum OfferType: string
{
    case Percent = 'percent';

    /** A fixed amount per cabin (fare), per guest (age, 3rd guest) or per single cabin; never more than the price. */
    case Fixed = 'fixed';

    /** All of it (100%). */
    case Free = 'free';

    /**
     * A final price instead of a discount: what's left is the value (per cabin, per guest or per single cabin, as
     * Fixed), so the discount is the price above it. Never below 0.
     */
    case FixedPrice = 'fixed_price';
}
