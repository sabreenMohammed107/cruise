<?php

use App\Admin\Resources;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
| Generic CRUD screens (App\Admin\Resource subclasses); routes are made for each of them.
| The sidebar (its groups, order and icons) is App\Admin\Menu.
| Bookings and sailings have their own controllers (routes/admin.php).
*/

return [

    'resources' => [
        'Sailing' => [
            Resources\CruiseResource::class,
            Resources\PortResource::class,
            Resources\CabinCategoryResource::class,
            Resources\DestinationResource::class,
        ],
        'Ship page' => [
            Resources\ShipResource::class,
            Resources\ShipSpecResource::class,
            Resources\ShipZoneResource::class,
            Resources\ShipEssentialResource::class,
            Resources\ShipFaqResource::class,
            Resources\FacilityResource::class,
        ],
        'Sales & content' => [
            Resources\PromoCodeResource::class,
            Resources\AgeBandResource::class,
            Resources\TestimonialResource::class,
            Resources\NewsletterSubscriberResource::class,
        ],
        'People' => [
            Resources\CustomerResource::class,
            Resources\UserResource::class,
        ],
    ],

];
