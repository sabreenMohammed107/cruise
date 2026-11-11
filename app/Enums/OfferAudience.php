<?php

namespace App\Enums;

/** Who an offer is for (App\Models\Offer). */
enum OfferAudience: string
{
    case All = 'all';

    /** Guests and customers: everyone except signed-in agents. */
    case Customers = 'b2c';

    /** Signed-in agents only. */
    case Agents = 'b2b';
}
