<?php

use App\Admin\Resources;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
| Generic CRUD screens (App\Admin\Resource subclasses), grouped as in the sidebar.
| Bookings, sailings and photo slots have their own controllers (routes/admin.php).
*/

return [

    'resources' => [
        'Sailing' => [
            Resources\CruiseResource::class,
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
            Resources\UserResource::class,
        ],
    ],

];
