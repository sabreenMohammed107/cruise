<?php

/*
| Fixed words of the page templates (resources/views/pages/*.blade.php): breadcrumbs, page titles,
| in-page navigation, filters, buttons and screen-reader labels. Same keys in lang/ar/pages.php.
| Editable section copy lives in the database (Admin → Homepage / Cruise pages), not here.
*/

return [

    'brand' => 'Safer Cruise',

    'breadcrumb' => [
        'label' => 'Breadcrumb',
        'home' => 'Home',
        'cruises' => 'Cruises',
        'ship' => 'The ship',
        'destinations' => 'Destinations',
    ],

    'home' => [
        'title' => 'Safer Cruise | Red Sea cruises aboard AROYA',
        'description' => 'Book AROYA cruises from Jeddah and Yanbu with Safer Cruise. Clear prices for adults, children and infants.',
        'search_button' => 'Search cruises',
        'cabin_categories' => 'Cabin categories',
        'previous_photo' => 'Previous photo',
        'next_photo' => 'Next photo',
        'previous_destinations' => 'Previous destinations',
        'next_destinations' => 'Next destinations',
        'destinations' => 'Destinations',
        'email' => 'Email address',
        'email_placeholder' => 'you@example.com',
        'subscribe' => 'Subscribe',
    ],

    'ship' => [
        'title' => 'The ship: :ship | Safer Cruise',
        'description' => "Meet :ship, Saudi Arabia's first cruise ship: specs, dining, pools, spa, cabins and onboard essentials.",
        'sections' => 'Sections',
        'nav' => [
            'about' => 'About the ship',
            'onboard' => 'Life on board',
            'cabins' => 'Cabins',
            'essentials' => 'Good to know',
            'faq' => 'Questions',
        ],
    ],

    'catalog' => [
        'title' => 'All cruises | Safer Cruise',
        'description' => 'Every AROYA departure from Jeddah and Yanbu, with prices per person and filters by port, date, nights and cabin.',
        'search_label' => 'Search by cruise name',
        'filters' => 'Filters',
        'clear_all' => 'Clear all',
        'close_filters' => 'Close filters',
        'departure_port' => 'Departure port',
        'dates' => 'Dates',
        'month' => 'Month',
        'any_month' => 'Any month',
        'departing_after' => 'Departing on or after',
        'nights' => 'Nights',
        'price' => 'Price per person',
        'up_to' => 'Up to',
        'cabin_type' => 'Cabin type',
        'ship' => 'Ship',
        'highlights' => 'Highlights',
        'show_results' => 'Show results',
        'sailings' => 'sailings',
        'sort_by' => 'Sort by',
        'sort' => [
            'popular' => 'Most popular',
            'date' => 'Departure date',
            'price_asc' => 'Price: low to high',
            'price_desc' => 'Price: high to low',
            'rating' => 'Guest rating',
        ],
        'layout' => 'Layout',
        'grid_view' => 'Grid view',
        'list_view' => 'List view',
        'pagination' => 'Pagination',
    ],

    'cruise' => [
        'title' => ':cruise | Safer Cruise',
        'description' => ':cruise: :nights nights aboard :ship. See every departure date and the price for each guest.',
        'copy_link' => 'Copy link to this cruise',
        'all_photos' => 'View all photos',
        'sections' => 'Sections',
        'nav' => [
            'overview' => 'Overview',
            'itinerary' => 'Itinerary',
            'cabins' => 'Cabins & prices',
            'amenities' => 'On board',
            'safety' => 'Health & safety',
        ],
        'departure_dates' => 'Departure dates',
        'expand_all' => 'Expand all',
        'booking_summary' => 'Booking summary',
        'choose_cabin' => 'Choose a cabin',
        'continue' => 'Continue',
    ],

    'destinations' => [
        'title' => 'Destinations | Safer Cruise',
        'description' => 'Every port AROYA calls at in Saudi Arabia, Egypt and Jordan, with excursions, visa notes and the cruises that visit.',
        'filter_country' => 'Filter by country',
        'ports' => 'Ports',
    ],

];
