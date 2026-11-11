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

    // The developer's hidden account (UserRole::SuperAdmin), made by Database\Seeders\SuperAdminSeeder.
    // Only in .env (never in the code or git): SUPER_ADMIN_EMAIL, SUPER_ADMIN_PASSWORD.
    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    'resources' => [
        'Sailing' => [
            Resources\CruiseResource::class,
            Resources\PortResource::class,
            Resources\HighlightResource::class,
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
            Resources\OfferResource::class,
            Resources\PromoCodeResource::class,
            Resources\AgentTierResource::class,
            Resources\AgentRateResource::class,
            Resources\AgeBandResource::class,
            Resources\TestimonialResource::class,
            Resources\NewsletterSubscriberResource::class,
        ],
        'People' => [
            Resources\CustomerResource::class,
            Resources\AgentResource::class,
            Resources\UserResource::class,
        ],
    ],

];
