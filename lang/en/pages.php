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
        'contact' => 'Contact us',
        'agents' => 'Become our agent',
        'guide' => 'Pre-boarding guide',
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

    'contact' => [
        'title' => 'Contact us | Safer Cruise',
        'description' => 'Questions about an AROYA cruise or your booking? Send the Safer Cruise team a message, call or WhatsApp us.',
        'heading' => 'Contact us',
        'intro' => 'Questions about a cruise, a cabin or your booking? Send us a message and our team will get back to you.',
        'form_title' => 'Send us a message',
        'fields' => [
            'name' => 'Full name',
            'email' => 'Email address',
            'phone' => 'Phone (optional)',
            'message' => 'Message',
        ],
        'placeholders' => [
            'name' => 'Your name',
            'email' => 'you@example.com',
            'message' => 'How can we help?',
        ],
        'message_hint' => '10 to 2,000 characters.',
        'send' => 'Send message',
        'sent_title' => 'Thank you, your message has been sent.',
        'sent_body' => 'Our team will reply as soon as possible, usually within one working day.',
        'other_ways' => 'Other ways to reach us',
        'call' => 'Call us',
        'email_us' => 'Email us',
        'whatsapp' => 'WhatsApp',
        'whatsapp_body' => 'Chat with our team',
        'visit' => 'Our office',
    ],

    // Headings and texts of the page itself: Admin → Cruise pages → Become an agent
    'agents' => [
        'title' => 'Become our agent | Safer Cruise',
        'description' => 'Travel agencies: join the Safer agents program and sell AROYA Red Sea cruises at agent prices, with a dedicated support team.',
        'fields' => [
            'company_name' => 'Company name',
            'contact_name' => 'Contact person',
            'email' => 'Email address',
            'phone' => 'Mobile number',
        ],
        'placeholders' => [
            'company_name' => 'Your travel agency',
            'contact_name' => 'Full name',
            'email' => 'you@agency.com',
        ],
        'send' => 'Send application',
        'sent_title' => 'Thank you, your application has been received.',
        'sent_body' => 'Our partnerships team will review it and contact you soon.',
    ],

    // Headings and texts of the page itself: Admin → Cruise pages → Pre-boarding guide
    'guide' => [
        'title' => 'Pre-boarding guide | Safer Cruise',
        'description' => 'Everything to do before you board AROYA: visas for Türkiye, Egypt and Greece, check-in times, the terminal, prohibited items and travelling with children.',
        'sections' => 'Sections',
        'nav' => [
            'visas' => 'Visas',
            'steps' => 'Steps',
            'checkin' => 'Check-in',
            'terminal' => 'Terminal',
            'prohibited' => 'Prohibited items',
            'kids' => 'Kids & teens',
            'faq' => 'Questions',
        ],
        'mandatory' => 'Mandatory',
        'step' => 'Step :number',
        'open_link' => 'Official page',
        'download_form' => 'Download form',
        'search_label' => 'Search the questions',
        'no_results' => 'No question matches your search.',
    ],

    // Hidden anti-spam field on public forms (partials.honeypot)
    'honeypot' => 'Leave this field empty',

];
