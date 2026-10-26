<?php

/*
| Words built by the page scripts (core.js, pages/*.js): sailing cards, tags, filters, chips, cabin and terms
| pop-ups, cruise details, destinations, toasts. Sent to the browser in SC_DATA.i18n.{en,ar}.ui and read with
| SC.t('ui.…') / SC.tc('ui.…', n). Same keys in lang/ar/ui.php. Placeholders: :name.
*/

return [

    'error' => 'Something went wrong. Please try again.',
    'per_person_short' => 'per person',
    'from' => 'Starting from',
    'from_price' => 'From :price',
    'from_per_person' => 'Starting from per person',
    'photo_n' => 'Photo :n',
    'photo_of' => ':n of :total',
    'previous_photo' => 'Previous photo',
    'next_photo' => 'Next photo',
    'close' => 'Close',
    'guest_rating' => 'guest rating',
    'stars' => ':rating out of 5 stars',
    'cruises' => '{1} :count cruise|[2,*] :count cruises',
    'guests_up_to' => 'Up to :count',
    'guests_up_to_long' => 'Up to :count guests',
    'balcony_size' => ':size balcony',
    'list_or' => ' or ',

    'card' => [
        'departs' => 'Departs',
        'returns' => 'Returns',
        'length' => 'Length',
        'ship_from' => ':ship, from :port',
        'select' => 'Select sailing',
        'also_departs' => 'Also departs :dates',
        'and_more' => ' and :count more',
        'image_alt' => ':cruise on :ship',
        'ask_whatsapp' => 'Ask about this sailing on WhatsApp',
        'whatsapp_message' => "Hello, I'm interested in :cruise departing :date.",
    ],

    'tags' => [
        'visaFree' => 'No visa needed', // other tags: Admin → Cruises → Highlights (step 22)
    ],

    'favorites' => [
        'save' => 'Save to favourites',
        'remove' => 'Remove from favourites',
        'saved' => 'Saved to your favourites',
        'removed' => 'Removed from favourites',
        'error' => 'Could not update your favourites. Try again.',
    ],

    'catalog' => [
        'visiting' => 'Visiting',
        'any_port' => 'Any port',
        'highlights' => [
            'visaFree' => 'No visa needed', // the rest: Admin → Cruises → Highlights (step 22)
        ],
        'chip_from_port' => 'From :port',
        'chip_visiting' => 'Visiting :port',
        'chip_from_date' => 'From :date',
        'chip_up_to' => 'Up to :price',
        'previous_page' => 'Previous page',
        'next_page' => 'Next page',
    ],

    'cabin' => [
        'private_balcony' => 'Private balcony',
        'no_balcony' => 'No balcony',
        'max_guests' => 'Maximum guests',
        'guests' => 'Guests',
        'size' => 'Size',
        'cabin_size' => 'Cabin size',
        'balcony' => 'Balcony',
        'beds' => 'Beds',
        'bedroom' => 'Bedroom and lounge',
        'in_cabin' => 'In the cabin',
        'photos' => 'Cabin photos',
        'choose' => 'Choose this cabin',
        'choose_suite' => 'Choose this suite',
        'view_details' => 'View details',
        'all_details' => 'All details',
        'details_photos' => 'View details and photos',
        'find_sailings' => 'Find sailings',
        'photo_alt' => ':cabin, photo :n',
        'show_photo' => 'Show photo :n',
    ],

    'terms' => [
        'title' => 'Terms & conditions and cancellation policy',
        'terms' => 'Terms & conditions',
        'cancellation' => 'Cancellation policy',
        'intro' => 'Penalties are calculated from the number of days before departure.',
        'when' => 'When you cancel',
        'penalty' => 'Penalty',
        'back' => 'Back to my booking',
    ],

    'browse_sailings' => 'Browse sailings',
    'ship_zone_alt' => ':zone on :ship',

    'home' => [
        'email_invalid' => 'Enter an email like name@example.com',
        'subscribed' => 'Subscribed. Watch your inbox for new sailings.',
    ],

    'destinations' => [
        'all_ports' => 'All ports',
        'things_to_do' => 'Things to do',
        'cruises_here' => 'Cruises that call here',
        'next_departure' => ':nights, next departure :date',
        'none' => 'No upcoming sailings call here yet.',
        'all_sailings_to' => 'All sailings to :port',
        // Step 21: server-rendered page, nearest sailings first
        'sailings_count' => '{1} :count upcoming sailing|[2,*] :count upcoming sailings',
        'showing_next' => 'The next :shown of :total sailings',
        'see_all' => 'See all :count sailings to :port',
        'from_pp' => 'Starting from per person',
    ],

    'details' => [
        'round_trip' => 'Round trip from :port',
        'route' => ':from to :to',
        'link_copied' => 'Link copied',
        'copy_failed' => 'Copy the link from your address bar',
        'open_photo' => 'Open photo :n',
        'photo_alt' => ':cruise photo :n',
        'duration' => 'Duration',
        'days' => '{1} :count day|[2,*] :count days',
        'ship' => 'Ship',
        'sailing_code' => 'Sailing :code',
        'from_pp' => 'Starting from :price per person',
        'sold_out_change' => ':cabin is sold out on this date. Choose another cabin.',
        'day' => 'Day',
        'expand_all' => 'Expand all',
        'collapse_all' => 'Collapse all',
        'too_small' => '<b>:cabin</b> fits up to :max guests, and you\'ve added :count.',
        'choose_instead' => 'Choose :cabins instead.',
        'reduce_guests' => 'Reduce the number of guests.',
        'no_longer_fits' => 'Your cabin no longer fits everyone. Choose a larger one.',
        'sold_out_date' => 'Sold out on this date',
        'sold_out' => 'Sold out',
        'fits_up_to' => 'Fits up to :count guests',
        'selected' => 'Selected',
        'cabin_selected' => ':cabin selected',
        // Cabin price rows: tiered occupancy, cabin price for the whole cruise
        'single' => '1 guest',
        'double' => '2 guests',
        'extra' => '3rd guest (adult or child)',
        'infant' => 'Infant (under 2)',
        'free' => 'Free',
        'per_cabin' => 'Cabin price for the whole cruise',
        // Several cabins in one booking (step 15)
        'cabin_n' => 'Cabin :n',
        'your_cabins' => 'Your cabins',
        'add_cabin' => 'Add another cabin',
        'remove_cabin' => 'Remove cabin :n',
        'cabin_added' => 'Cabin :n added. Choose its guests and cabin type.',
        'editing_cabin' => 'Choosing guests and cabin type for cabin :n of :total',
        'cabins_count' => '{1} :count cabin|[2,*] :count cabins',
        'choose_cabin_n' => 'Choose a cabin type for cabin :n.',
        'your_sailing' => 'Your sailing',
        'guests' => 'Guests',
        'cabin' => 'Cabin',
        'not_chosen' => 'Not chosen yet',
        'tax_included' => 'Tax included',
        'total' => 'Total',
        'choose_to_see_total' => 'Choose a cabin to see your total price.',
        'continue_guests' => 'Continue to guest details',
        'choose_cabin' => 'Choose a cabin',
        'choose_to_continue' => 'Choose a cabin to continue',
    ],

];
