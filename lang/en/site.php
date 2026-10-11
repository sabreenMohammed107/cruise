<?php

/*
| Fixed website words (not editable in the admin). Blade prints them with @bi('site.…'), which outputs English
| and Arabic side by side; the page scripts read the same file from SC_DATA.i18n through SC.t() / SC.tc().
| Plural strings use Laravel's trans_choice format, which SC.tc() understands too.
*/

return [

    // Header and mobile menu (the logo text "SaferCruise / by safer.travel" is a fixed brand and is not translated)
    'nav' => [
        'home' => 'Home',
        'catalog' => 'Cruises',
        'ship' => 'The ship',
        'destinations' => 'Destinations',
        'my_bookings' => 'My bookings',
        'my_account' => 'My account',
        'admin' => 'Admin',
        'sign_in' => 'Sign in',
        'sign_out' => 'Sign out',
        'switch_language' => 'عربي',            // label of the language button: shows the other language
        'switch_language_long' => 'عربي (RTL)',
        'main' => 'Main',
        'mobile' => 'Mobile',
        'menu' => 'Menu',
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
        'logo' => 'Safer Cruise home',
    ],

    'footer' => [
        'about' => 'Safer Cruise is the cruise booking site of Safar Tourism, an international travel agency licensed by the Ministry of Tourism (License No. 14079).',
        'visit' => 'Visit safer.travel',
        'explore' => 'Explore',
        'all_cruises' => 'All cruises',
        'help' => 'Help',
        'terms' => 'Terms & conditions',
        'cancellation' => 'Cancellation policy',
        'contact' => 'Contact us',
        'individuals' => 'Individuals:',
        'companies' => 'Companies:',
        'address' => 'Watan Square, Al-Fateh, Istanbul, Turkey',
        'rights' => '© :year Safer. All rights reserved.',
        'prices_usd' => 'All prices in US dollars (USD).',
        'chat' => 'Chat with us',
        'chat_label' => 'Chat with us on WhatsApp',
        'whatsapp_message' => 'Hello, I would like to ask about AROYA cruises on Safer Cruise.',
    ],

    'search' => [
        'destination' => 'Destination',
        'any_port' => 'Any port',
        'month' => 'Departure month',
        'any_month' => 'Any month',
        'nights' => 'Nights',
        'any_length' => 'Any length',
        'guests' => 'Guests',
        'done' => 'Done',
        'match' => 'sailings match',
    ],

    'nights' => '{1} :count night|[2,*] :count nights',

    'guests' => [
        'adult' => '{1} :count adult|[2,*] :count adults',
        'child' => '{1} :count child|[2,*] :count children',
        'infant' => '{1} :count infant|[2,*] :count infants',
        'join' => ' + ',
    ],

    // Guest counter rows (label and hint of each age band)
    'bands' => [
        'adult' => ['label' => 'Adult', 'hint' => '12 years and over'],
        'child' => ['label' => 'Child', 'hint' => '2 to 11 years'],
        'infant' => ['label' => 'Infant', 'hint' => '6 to 23 months'],
        'fewer' => 'Fewer',
        'more' => 'More',
    ],

    // Itinerary sea days (routes store "At sea"; shown through SC.port)
    'at_sea' => 'At sea',

    // Locale for month names in the search widget (Intl.DateTimeFormat)
    'date_locale' => 'en-GB',

];
