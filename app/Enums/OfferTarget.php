<?php

namespace App\Enums;

/** What an offer takes its discount from (App\Models\Offer, App\Services\PricingService::quoteBooking()). */
enum OfferTarget: string
{
    /** The whole cabin price: a percent of it, or a fixed amount per cabin. */
    case Fare = 'fare';

    /** Each paying guest of the age range: a percent of their share of the cabin price, or a fixed amount per guest. */
    case Age = 'age';

    /** The 3rd guest of a cabin (the "extra" price): a percent of it, or a fixed amount per 3rd guest. */
    case ThirdGuest = 'third_guest';

    /** A guest alone in a cabin: the single supplement (single price − half the double price). */
    case Single = 'single';

    /** The 2nd guest of a cabin: what they add to the price (double − single). */
    case SecondGuest = 'second_guest';

    /** Some nights of the cruise free: the cabin price × free nights ÷ nights. */
    case FreeNights = 'free_nights';

    /** The cabin at the price of another (cheaper) cabin type: the difference between the two. */
    case Upgrade = 'upgrade';

    /** Admin labels. */
    public function label(): string
    {
        return match ($this) {
            self::Fare => 'The whole cabin price',
            self::Age => 'Guests of an age range',
            self::ThirdGuest => 'The 3rd guest in a cabin',
            self::Single => 'Single traveller (the single supplement)',
            self::SecondGuest => 'The 2nd guest in a cabin',
            self::FreeNights => 'Free nights',
            self::Upgrade => 'Cabin upgrade (the price of a cheaper cabin)',
        };
    }
}
